<?php

namespace App\Services;

use App\Enums\CouponStatus;
use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\CouponStatusHistory;
use App\Models\Employee;
use App\Models\SocialMedia;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Importa cupons históricos (migração v1 → v2) a partir de planilha
 * XLSX/CSV. Upsert por chave (cpf_hash + type + store_code) para
 * idempotência em re-imports.
 *
 * Fluxo em dois passos (padrão Reversals/Returns):
 *  1. preview(): valida linhas, devolve N primeiras + erros sem persistir.
 *  2. import(): re-executa validação e grava o que passou.
 *
 * Nunca transiciona por conta própria — se status vier como `active`,
 * grava e cria histórico null→active (paridade com o v1 que tinha o
 * cupom já em produção). NÃO dispara notificações.
 */
class CouponImportService
{
    /**
     * Mapeamento de headers PT-BR → campos canônicos. Todas as variantes
     * aceitas pra tolerar planilhas de origens diferentes.
     */
    private const HEADER_MAP = [
        // Tipo
        'tipo' => 'type',
        'type' => 'type',
        'categoria' => 'type',

        // CPF
        'cpf' => 'cpf',
        'documento' => 'cpf',

        // Loja
        'loja' => 'store_code',
        'codigo_loja' => 'store_code',
        'store_code' => 'store_code',

        // Colaborador (pra Consultor/MsIndica)
        'colaborador' => 'employee_name',
        'funcionario' => 'employee_name',
        'nome_colaborador' => 'employee_name',
        'nome_funcionario' => 'employee_name',
        'employee_name' => 'employee_name',
        'employee' => 'employee_name',
        'matricula' => 'employee_id',
        'employee_id' => 'employee_id',

        // Influencer
        'influencer' => 'influencer_name',
        'nome_influencer' => 'influencer_name',
        'influencer_name' => 'influencer_name',
        'cidade' => 'city',
        'city' => 'city',
        'rede_social' => 'social_media_name',
        'rede' => 'social_media_name',
        'social_media' => 'social_media_name',
        'social_media_name' => 'social_media_name',
        'link' => 'social_media_link',
        'link_perfil' => 'social_media_link',
        'link_social' => 'social_media_link',
        'social_media_link' => 'social_media_link',

        // Código e campanha
        'cupom_sugerido' => 'suggested_coupon',
        'sugerido' => 'suggested_coupon',
        'suggested_coupon' => 'suggested_coupon',
        'cupom' => 'coupon_site',
        'cupom_emitido' => 'coupon_site',
        'codigo' => 'coupon_site',
        'coupon_site' => 'coupon_site',
        'campanha' => 'campaign_name',
        'campaign' => 'campaign_name',
        'campaign_name' => 'campaign_name',

        // Validade
        'valido_de' => 'valid_from',
        'data_inicio' => 'valid_from',
        'valid_from' => 'valid_from',
        'valido_ate' => 'valid_until',
        'data_fim' => 'valid_until',
        'valid_until' => 'valid_until',
        'max_uses' => 'max_uses',
        'maximo_usos' => 'max_uses',

        // Status (opcional — default draft)
        'status' => 'status',
        'situacao' => 'status',

        // Observações
        'obs' => 'notes',
        'observacao' => 'notes',
        'observacoes' => 'notes',
        'notes' => 'notes',
    ];

    private const TYPE_ALIASES = [
        'consultor' => CouponType::CONSULTOR,
        'consultora' => CouponType::CONSULTOR,
        'consultor(a)' => CouponType::CONSULTOR,
        'influencer' => CouponType::INFLUENCER,
        'influenciador' => CouponType::INFLUENCER,
        'ms_indica' => CouponType::MS_INDICA,
        'ms indica' => CouponType::MS_INDICA,
        'msindica' => CouponType::MS_INDICA,
        'indica' => CouponType::MS_INDICA,
    ];

    private const STATUS_ALIASES = [
        'draft' => CouponStatus::DRAFT,
        'rascunho' => CouponStatus::DRAFT,
        'requested' => CouponStatus::REQUESTED,
        'solicitado' => CouponStatus::REQUESTED,
        'pendente' => CouponStatus::REQUESTED,
        'issued' => CouponStatus::ISSUED,
        'emitido' => CouponStatus::ISSUED,
        'active' => CouponStatus::ACTIVE,
        'ativo' => CouponStatus::ACTIVE,
        'cadastrado' => CouponStatus::ACTIVE,
        'expired' => CouponStatus::EXPIRED,
        'expirado' => CouponStatus::EXPIRED,
        'cancelled' => CouponStatus::CANCELLED,
        'cancelado' => CouponStatus::CANCELLED,
    ];

    /**
     * @return array{
     *   rows: array<int, array>,
     *   errors: array<int, array{row:int, messages:array<string>}>,
     *   valid_count: int,
     *   invalid_count: int,
     * }
     */
    public function preview(string $filePath, int $limit = 10): array
    {
        $raw = $this->readFile($filePath);
        $normalized = $this->normalizeRows($raw);

        $rows = [];
        $errors = [];
        $validCount = 0;
        $invalidCount = 0;

        foreach ($normalized as $idx => $row) {
            $rowNumber = $idx + 2; // +1 header, +1 base-1
            [$data, $rowErrors] = $this->validateRow($row);

            if (empty($rowErrors)) {
                $validCount++;
                if (count($rows) < $limit) {
                    $rows[] = $data;
                }
            } else {
                $invalidCount++;
                if (count($errors) < 50) {
                    $errors[] = ['row' => $rowNumber, 'messages' => $rowErrors];
                }
            }
        }

        return [
            'rows' => $rows,
            'errors' => $errors,
            'valid_count' => $validCount,
            'invalid_count' => $invalidCount,
        ];
    }

    /**
     * @return array{
     *   created: int,
     *   updated: int,
     *   skipped: int,
     *   errors: array<int, array{row:int, messages:array<string>}>,
     * }
     */
    public function import(string $filePath, User $actor): array
    {
        $raw = $this->readFile($filePath);
        $normalized = $this->normalizeRows($raw);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        DB::transaction(function () use ($normalized, $actor, &$created, &$updated, &$skipped, &$errors) {
            foreach ($normalized as $idx => $row) {
                $rowNumber = $idx + 2;
                [$data, $rowErrors] = $this->validateRow($row);

                if (! empty($rowErrors)) {
                    $skipped++;
                    if (count($errors) < 50) {
                        $errors[] = ['row' => $rowNumber, 'messages' => $rowErrors];
                    }
                    continue;
                }

                // Dedup/upsert por (cpf_hash, type, store_code)
                $cpfHash = Coupon::hashCpf($data['cpf']);
                $existing = Coupon::query()
                    ->where('cpf_hash', $cpfHash)
                    ->where('type', $data['type']->value)
                    ->when(
                        $data['type']->requiresStoreAndEmployee(),
                        fn ($q) => $q->where('store_code', $data['store_code'])
                    )
                    ->whereNull('deleted_at')
                    ->first();

                $payload = [
                    'type' => $data['type']->value,
                    'status' => ($data['status'] ?? CouponStatus::DRAFT)->value,
                    'employee_id' => $data['employee_id'] ?? null,
                    'store_code' => $data['store_code'] ?? null,
                    'influencer_name' => $data['influencer_name'] ?? null,
                    'cpf' => $data['cpf'],
                    'social_media_id' => $data['social_media_id'] ?? null,
                    'social_media_link' => $data['social_media_link'] ?? null,
                    'city' => $data['city'] ?? null,
                    'suggested_coupon' => $data['suggested_coupon'] ?? null,
                    'coupon_site' => $data['coupon_site'] ?? null,
                    'campaign_name' => $data['campaign_name'] ?? null,
                    'valid_from' => $data['valid_from'] ?? null,
                    'valid_until' => $data['valid_until'] ?? null,
                    'max_uses' => $data['max_uses'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'updated_by_user_id' => $actor->id,
                ];

                if ($existing) {
                    $existing->update($payload);
                    $updated++;
                } else {
                    $payload['created_by_user_id'] = $actor->id;
                    $coupon = Coupon::create($payload);

                    CouponStatusHistory::create([
                        'coupon_id' => $coupon->id,
                        'from_status' => null,
                        'to_status' => $coupon->status->value,
                        'changed_by_user_id' => $actor->id,
                        'note' => 'Importado via planilha',
                        'created_at' => now(),
                    ]);
                    $created++;
                }
            }
        });

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    // ------------------------------------------------------------------
    // File parsing
    // ------------------------------------------------------------------

    private function readFile(string $filePath): array
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (! in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
            throw new \InvalidArgumentException('Arquivo deve ser XLSX, XLS ou CSV.');
        }

        $import = new class implements ToArray, WithHeadingRow {
            public array $rows = [];

            public function array(array $array): void
            {
                $this->rows = $array;
            }
        };

        Excel::import($import, $filePath);

        return $import->rows;
    }

    /**
     * Renomeia headers para chaves canônicas.
     */
    private function normalizeRows(array $raw): array
    {
        $normalized = [];
        foreach ($raw as $row) {
            $mapped = [];
            foreach ($row as $key => $value) {
                $canonicalKey = self::HEADER_MAP[$this->slugify((string) $key)] ?? null;
                if ($canonicalKey) {
                    $mapped[$canonicalKey] = is_string($value) ? trim($value) : $value;
                }
            }
            if (! empty($mapped)) {
                $normalized[] = $mapped;
            }
        }

        return $normalized;
    }

    private function slugify(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/[áàãâä]/u', 'a', $s);
        $s = preg_replace('/[éèêë]/u', 'e', $s);
        $s = preg_replace('/[íìîï]/u', 'i', $s);
        $s = preg_replace('/[óòõôö]/u', 'o', $s);
        $s = preg_replace('/[úùûü]/u', 'u', $s);
        $s = preg_replace('/[ç]/u', 'c', $s);
        $s = preg_replace('/[^a-z0-9]+/', '_', $s);
        return trim($s, '_');
    }

    // ------------------------------------------------------------------
    // Validação + resolução de FKs
    // ------------------------------------------------------------------

    /**
     * @return array{0: array, 1: array<string>}
     */
    private function validateRow(array $row): array
    {
        $errors = [];
        $data = [];

        // Tipo
        $typeRaw = strtolower(trim((string) ($row['type'] ?? '')));
        $type = self::TYPE_ALIASES[$typeRaw] ?? null;
        if (! $type) {
            $errors[] = 'Tipo inválido ou faltando (Consultor/Influencer/MS Indica).';
            return [$data, $errors];
        }
        $data['type'] = $type;

        // CPF
        $cpfRaw = preg_replace('/\D/', '', (string) ($row['cpf'] ?? ''));
        if (strlen($cpfRaw) !== 11) {
            $errors[] = 'CPF inválido ou faltando (11 dígitos).';
        } else {
            $data['cpf'] = $cpfRaw;
        }

        // Status (opcional — default draft)
        if (! empty($row['status'])) {
            $statusRaw = strtolower(trim((string) $row['status']));
            $status = self::STATUS_ALIASES[$statusRaw] ?? null;
            if (! $status) {
                $errors[] = "Status '{$row['status']}' inválido.";
            } else {
                $data['status'] = $status;
            }
        }

        // Campos específicos por tipo
        if ($type->requiresStoreAndEmployee()) {
            $storeCode = strtoupper(trim((string) ($row['store_code'] ?? '')));
            if ($storeCode === '') {
                $errors[] = 'Loja é obrigatória para '.$type->label().'.';
            } else {
                $store = Store::where('code', $storeCode)->first();
                if (! $store) {
                    $errors[] = "Loja '{$storeCode}' não encontrada.";
                } else {
                    // MS Indica exige loja administrativa
                    if ($type === CouponType::MS_INDICA && ! in_array((int) $store->network_id, [6, 7], true)) {
                        $errors[] = "MS Indica exige loja administrativa (network 6/7). Loja {$storeCode} é network {$store->network_id}.";
                    }
                    $data['store_code'] = $storeCode;
                }
            }

            // Employee: aceita ID numérico ou nome (case-insensitive match na loja)
            $employeeId = $row['employee_id'] ?? null;
            $employeeName = $row['employee_name'] ?? null;

            if ($employeeId) {
                $emp = Employee::find((int) $employeeId);
                if (! $emp) {
                    $errors[] = "Colaborador ID {$employeeId} não encontrado.";
                } else {
                    $data['employee_id'] = $emp->id;
                }
            } elseif ($employeeName && isset($data['store_code'])) {
                $emp = Employee::where('store_id', $data['store_code'])
                    ->whereRaw('LOWER(name) = ?', [strtolower(trim($employeeName))])
                    ->first();
                if (! $emp) {
                    $errors[] = "Colaborador '{$employeeName}' não encontrado na loja {$data['store_code']}.";
                } else {
                    $data['employee_id'] = $emp->id;
                }
            } else {
                $errors[] = 'Colaborador é obrigatório (informe nome ou matricula).';
            }
        }

        if ($type === CouponType::INFLUENCER) {
            $data['influencer_name'] = trim((string) ($row['influencer_name'] ?? ''));
            if ($data['influencer_name'] === '') {
                $errors[] = 'Nome do influencer é obrigatório.';
            }

            $data['city'] = trim((string) ($row['city'] ?? ''));
            if ($data['city'] === '') {
                $errors[] = 'Cidade é obrigatória para influencer.';
            }

            // Rede social: aceita ID ou nome
            $smName = trim((string) ($row['social_media_name'] ?? ''));
            if ($smName === '') {
                $errors[] = 'Rede social é obrigatória para influencer.';
            } else {
                $sm = SocialMedia::whereRaw('LOWER(name) = ?', [strtolower($smName)])->first();
                if (! $sm) {
                    $errors[] = "Rede social '{$smName}' não encontrada.";
                } else {
                    $data['social_media_id'] = $sm->id;
                }
            }

            $data['social_media_link'] = trim((string) ($row['social_media_link'] ?? '')) ?: null;
        }

        // Opcionais comuns
        foreach (['suggested_coupon', 'coupon_site', 'campaign_name', 'notes'] as $f) {
            $val = trim((string) ($row[$f] ?? ''));
            if ($val !== '') {
                $data[$f] = $val;
            }
        }

        foreach (['valid_from', 'valid_until'] as $f) {
            $val = $row[$f] ?? null;
            if ($val) {
                try {
                    $data[$f] = $this->parseDate($val);
                } catch (\Throwable $e) {
                    $errors[] = "Data em '{$f}' inválida ({$val}).";
                }
            }
        }

        if (! empty($row['max_uses'])) {
            $uses = (int) $row['max_uses'];
            if ($uses > 0) {
                $data['max_uses'] = $uses;
            }
        }

        return [$data, $errors];
    }

    private function parseDate(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            // Excel serial date (days since 1900-01-01 com bug do 1900 leap year)
            $ts = ((int) $value - 25569) * 86400;
            return date('Y-m-d', $ts);
        }

        $str = trim((string) $value);
        // Tenta d/m/Y antes de DateTime default (que prefere m/d/Y)
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $str, $m)) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
        }

        return (new \DateTime($str))->format('Y-m-d');
    }
}
