<?php

namespace App\Services;

use App\Enums\CouponStatus;
use App\Enums\CouponType;
use App\Models\Coupon;
use App\Models\Employee;
use App\Models\SocialMedia;
use App\Models\Store;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Lookups do módulo Cupons — busca por CPF, listas auxiliares
 * (employees por loja, social_media) e sugestão de código.
 *
 * Não persiste nada — só leitura + cálculo de códigos sugeridos.
 */
class CouponLookupService
{
    /**
     * Retorna cupons ATIVOS do CPF informado, opcionalmente filtrados
     * por type/store. Usado pelo modal de criação pra exibir warning
     * quando há cupom prévio (item 3c do plano — detecção híbrida de troca
     * de loja/rede).
     *
     * NÃO bloqueia criação — apenas informa. Bloqueio real é via
     * CouponService::ensureUnique (chave variável por tipo).
     */
    public function existingActiveForCpf(
        string $cpf,
        ?CouponType $type = null,
        ?string $storeCode = null
    ): Collection {
        $cpfHash = Coupon::hashCpf($cpf);

        $query = Coupon::query()
            ->with(['employee:id,name,store_id', 'store:code,name', 'socialMedia:id,name'])
            ->forCpfHash($cpfHash)
            ->active()
            ->notDeleted();

        if ($type) {
            $query->where('type', $type->value);
        }

        if ($storeCode) {
            $query->where('store_code', $storeCode);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Colaboradores ativos de uma loja, para autocomplete no modal.
     * Retorna id, name, cpf formatado (ocultado) — o CPF real é carregado
     * client-side via outro lookup quando o colaborador é selecionado.
     */
    public function employeesByStore(string $storeCode): Collection
    {
        return Employee::query()
            ->where('store_id', $storeCode)
            ->where('status_id', 2) // 2=Ativo (1=Pendente, 3=Inativo)
            ->orderBy('name')
            ->select(['id', 'name', 'cpf', 'store_id'])
            ->get()
            ->map(fn (Employee $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'cpf_masked' => $this->maskCpfForDisplay($e->cpf),
            ]);
    }

    /**
     * Dados completos de 1 colaborador (quando user seleciona no
     * autocomplete, precisamos do CPF real pra preencher o form).
     *
     * @return array{id:int,name:string,cpf:?string,store_code:?string,store_name:?string,network_id:?int}|null
     */
    public function employeeDetails(int $employeeId): ?array
    {
        $employee = Employee::query()
            ->with('store:code,name,network_id')
            ->find($employeeId);

        if (! $employee) {
            return null;
        }

        return [
            'id' => $employee->id,
            'name' => $employee->name,
            'cpf' => $employee->cpf,
            'store_code' => $employee->store_id,
            'store_name' => $employee->store?->name,
            'network_id' => $employee->store?->network_id,
        ];
    }

    /**
     * Redes sociais ativas (usado no modal de influencer).
     */
    public function activeSocialMedia(): Collection
    {
        return SocialMedia::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'icon']);
    }

    /**
     * Gera sugestão de código baseada no nome. Remove acentos/espaços,
     * faz upper, limita a 15 chars, adiciona ano de 2 dígitos. Se o
     * código gerado já existir (coupon_site), tenta sufixo numérico.
     *
     * Ex: "Maria Silva" em 2026 → "MARIASILVA26". Se existir,
     * "MARIASILVA261". Limite 25 chars pra respeitar a coluna.
     */
    public function suggestCouponCode(string $name, ?int $year = null): string
    {
        // Aceita ano cheio (2026) ou curto (26) — normaliza sempre pra 2 dígitos
        $yearInt = $year !== null ? ($year % 100) : (int) date('y');
        $year = sprintf('%02d', $yearInt);
        $base = Str::of($name)
            ->ascii()
            ->upper()
            ->replaceMatches('/[^A-Z0-9]/', '')
            ->substr(0, 15)
            ->__toString();

        if ($base === '') {
            $base = 'CUPOM';
        }

        $candidate = $base.$year;
        $suffix = 0;

        while (
            Coupon::query()
                ->where('coupon_site', $candidate)
                ->whereNull('deleted_at')
                ->exists()
        ) {
            $suffix++;
            $candidate = $base.$year.$suffix;
            if (strlen($candidate) > 25) {
                // Encurta o base se ficar muito longo
                $base = substr($base, 0, max(1, 25 - strlen($year.$suffix)));
                $candidate = $base.$year.$suffix;
            }
        }

        return $candidate;
    }

    /**
     * Indica se uma loja é administrativa (network_id IN [6, 7] —
     * E-Commerce ou Operacional). Usado pra validar MS Indica.
     */
    public function isAdministrativeStore(string $storeCode): bool
    {
        $store = Store::where('code', $storeCode)->first(['network_id']);

        return $store !== null && in_array((int) $store->network_id, [6, 7], true);
    }

    protected function maskCpfForDisplay(?string $cpf): string
    {
        if (! $cpf) {
            return '';
        }

        $digits = preg_replace('/\D/', '', $cpf);
        if (strlen($digits) !== 11) {
            return $cpf;
        }

        return '***.'.substr($digits, 3, 3).'.'.substr($digits, 6, 3).'-**';
    }
}
