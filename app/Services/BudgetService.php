<?php

namespace App\Services;

use App\Enums\BudgetUploadType;
use App\Models\BudgetItem;
use App\Models\BudgetStatusHistory;
use App\Models\BudgetUpload;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CRUD do módulo Budgets — coordena versionamento, storage de arquivo,
 * persistência de items e regra de uma-versão-ativa-por-scope/year.
 *
 * O parse da planilha em si (Fase 2) vai viver em BudgetImportService
 * e devolverá o array de items já resolvidos em FKs. Este service só
 * recebe o array pronto + o arquivo original.
 */
class BudgetService
{
    public function __construct(
        protected BudgetVersionService $versions,
        protected BudgetFileStorageService $storage,
    ) {}

    /**
     * Cria um novo BudgetUpload (versão) + items. Desativa a versão
     * anterior do mesmo (year, scope_label) se existir.
     *
     * @param  array<int, array<string, mixed>>  $items  Items já resolvidos em FKs:
     *         [[ 'accounting_class_id' => 10, 'management_class_id' => 5,
     *            'cost_center_id' => 1, 'store_id' => null,
     *            'supplier' => '...', 'month_01_value' => 1000.00, ... ]]
     *
     * @throws ValidationException
     */
    public function create(
        array $data,
        array $items,
        UploadedFile $file,
        User $actor
    ): BudgetUpload {
        $this->validateHeader($data);

        if (empty($items)) {
            throw ValidationException::withMessages([
                'items' => 'Upload deve conter ao menos 1 linha de orçamento.',
            ]);
        }

        $type = BudgetUploadType::from($data['upload_type']);
        $year = (int) $data['year'];
        $scopeLabel = trim((string) $data['scope_label']);
        $areaDepartmentId = isset($data['area_department_id'])
            ? (int) $data['area_department_id']
            : null;

        // Fase 5: valida coerência da área com as MCs dos items.
        // Se a planilha tem MCs de áreas diferentes da selecionada, o user
        // escolheu área errada ou a planilha está misturando departamentos.
        if ($areaDepartmentId) {
            $this->validateAreaCoherence($areaDepartmentId, $items);
        }

        $version = $this->versions->resolveNextVersion($year, $scopeLabel, $type);

        // Storage ANTES da transaction DB — um arquivo órfão é aceitável
        // (limpeza manual posterior); uma transaction abortada com linhas
        // salvas sem arquivo seria pior.
        $storedPath = $this->storage->store($file, $year);

        return DB::transaction(function () use (
            $data,
            $items,
            $year,
            $scopeLabel,
            $areaDepartmentId,
            $type,
            $version,
            $file,
            $storedPath,
            $actor
        ) {
            // Desativa a versão anterior ativa (se houver)
            $previousActive = BudgetUpload::query()
                ->where('year', $year)
                ->where('scope_label', $scopeLabel)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->first();

            if ($previousActive) {
                $previousActive->update([
                    'is_active' => false,
                    'updated_by_user_id' => $actor->id,
                ]);

                $this->recordHistory($previousActive, 'deactivated', true, false, $actor, "Substituída por v{$version['label']}");
            }

            // Cria o novo upload
            $upload = BudgetUpload::create([
                'year' => $year,
                'scope_label' => $scopeLabel,
                'area_department_id' => $data['area_department_id'] ?? null,
                'version_label' => $version['label'],
                'major_version' => $version['major'],
                'minor_version' => $version['minor'],
                'upload_type' => $type->value,
                'original_filename' => $file->getClientOriginalName(),
                'stored_path' => $storedPath,
                'file_size_bytes' => $file->getSize(),
                'is_active' => true,
                'notes' => $data['notes'] ?? null,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);

            $this->persistItems($upload, $items);
            $this->refreshTotals($upload);

            $this->recordHistory($upload, 'created', null, true, $actor, "Versão {$version['label']} ({$type->label()})");

            return $upload->fresh();
        });
    }

    /**
     * Soft delete. **Nunca permite excluir a versão ativa diretamente** —
     * para substituir, faça novo upload (que desativa a anterior).
     *
     * @throws ValidationException
     */
    public function delete(BudgetUpload $upload, string $reason, User $actor): void
    {
        if ($upload->isDeleted()) {
            throw ValidationException::withMessages([
                'id' => 'Versão já excluída.',
            ]);
        }

        if ($upload->is_active) {
            throw ValidationException::withMessages([
                'id' => 'Versão ativa não pode ser excluída. Faça upload de uma nova versão para substituí-la ou use a opção de desativar primeiro.',
            ]);
        }

        $trimmed = trim($reason);
        if (strlen($trimmed) < 3) {
            throw ValidationException::withMessages([
                'deleted_reason' => 'Informe um motivo com ao menos 3 caracteres.',
            ]);
        }

        DB::transaction(function () use ($upload, $trimmed, $actor) {
            $upload->forceFill([
                'deleted_at' => now(),
                'deleted_by_user_id' => $actor->id,
                'deleted_reason' => $trimmed,
                'updated_by_user_id' => $actor->id,
            ])->save();

            $this->recordHistory($upload, 'deleted', false, false, $actor, $trimmed);
        });
    }

    /**
     * Atualiza apenas metadados editáveis (notes). Valores e items não
     * são editáveis — para corrigir faça novo upload type=ajuste.
     */
    public function updateMeta(BudgetUpload $upload, array $data, User $actor): BudgetUpload
    {
        if ($upload->isDeleted()) {
            throw ValidationException::withMessages([
                'id' => 'Versão excluída não pode ser editada.',
            ]);
        }

        $upload->update([
            'notes' => $data['notes'] ?? $upload->notes,
            'updated_by_user_id' => $actor->id,
        ]);

        return $upload->fresh();
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * @throws ValidationException
     */
    protected function validateHeader(array $data): void
    {
        $year = (int) ($data['year'] ?? 0);
        if ($year < 2000 || $year > 2100) {
            throw ValidationException::withMessages([
                'year' => 'Ano inválido (deve estar entre 2000 e 2100).',
            ]);
        }

        $scope = trim((string) ($data['scope_label'] ?? ''));
        if (strlen($scope) < 2) {
            throw ValidationException::withMessages([
                'scope_label' => 'Informe o escopo (ex: "Administrativo", "TI", "Geral").',
            ]);
        }

        $type = $data['upload_type'] ?? null;
        if (! $type || ! in_array($type, ['novo', 'ajuste'], true)) {
            throw ValidationException::withMessages([
                'upload_type' => 'Tipo de upload inválido. Use "novo" ou "ajuste".',
            ]);
        }
    }

    /**
     * Confere se todas as MCs dos items pertencem ao departamento (área)
     * selecionado. As MCs do seed têm `parent_id` apontando pro sintético
     * 8.1.DD — basta garantir que todas coincidam com `$areaDepartmentId`.
     *
     * Se o upload é legacy (sem área) ou se a MC está fora da hierarquia
     * gerencial, o check é pulado.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function validateAreaCoherence(int $areaDepartmentId, array $items): void
    {
        $mcIds = collect($items)
            ->pluck('management_class_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($mcIds)) {
            return;
        }

        // Busca as MCs dos items e os parent_id delas
        $mcs = \App\Models\ManagementClass::whereIn('id', $mcIds)
            ->get(['id', 'code', 'name', 'parent_id']);

        $divergent = $mcs->filter(
            fn ($mc) => $mc->parent_id !== null && $mc->parent_id !== $areaDepartmentId
        );

        if ($divergent->isEmpty()) {
            return;
        }

        $area = \App\Models\ManagementClass::find($areaDepartmentId);
        $list = $divergent->take(5)->map(fn ($m) => "{$m->code} ({$m->name})")->implode(', ');
        $more = $divergent->count() > 5 ? " e mais " . ($divergent->count() - 5) : '';

        throw ValidationException::withMessages([
            'area_department_id' => sprintf(
                'A planilha tem classes gerenciais fora da área "%s": %s%s. Verifique a área escolhida ou corrija a planilha.',
                $area?->name ?? 'selecionada',
                $list,
                $more
            ),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function persistItems(BudgetUpload $upload, array $items): void
    {
        foreach ($items as $data) {
            $yearTotal = 0;
            for ($i = 1; $i <= 12; $i++) {
                $col = 'month_'.str_pad((string) $i, 2, '0', STR_PAD_LEFT).'_value';
                $yearTotal += (float) ($data[$col] ?? 0);
            }

            BudgetItem::create([
                'budget_upload_id' => $upload->id,
                'accounting_class_id' => $data['accounting_class_id'],
                'management_class_id' => $data['management_class_id'],
                'cost_center_id' => $data['cost_center_id'],
                'store_id' => $data['store_id'] ?? null,
                'supplier' => $data['supplier'] ?? null,
                'justification' => $data['justification'] ?? null,
                'account_description' => $data['account_description'] ?? null,
                'class_description' => $data['class_description'] ?? null,
                'month_01_value' => (float) ($data['month_01_value'] ?? 0),
                'month_02_value' => (float) ($data['month_02_value'] ?? 0),
                'month_03_value' => (float) ($data['month_03_value'] ?? 0),
                'month_04_value' => (float) ($data['month_04_value'] ?? 0),
                'month_05_value' => (float) ($data['month_05_value'] ?? 0),
                'month_06_value' => (float) ($data['month_06_value'] ?? 0),
                'month_07_value' => (float) ($data['month_07_value'] ?? 0),
                'month_08_value' => (float) ($data['month_08_value'] ?? 0),
                'month_09_value' => (float) ($data['month_09_value'] ?? 0),
                'month_10_value' => (float) ($data['month_10_value'] ?? 0),
                'month_11_value' => (float) ($data['month_11_value'] ?? 0),
                'month_12_value' => (float) ($data['month_12_value'] ?? 0),
                'year_total' => round($yearTotal, 2),
            ]);
        }
    }

    /**
     * Recalcula `items_count` e `total_year` do header a partir dos items.
     */
    protected function refreshTotals(BudgetUpload $upload): void
    {
        $agg = DB::table('budget_items')
            ->where('budget_upload_id', $upload->id)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(year_total), 0) as total')
            ->first();

        $upload->forceFill([
            'items_count' => (int) ($agg?->cnt ?? 0),
            'total_year' => round((float) ($agg?->total ?? 0), 2),
        ])->save();
    }

    protected function recordHistory(
        BudgetUpload $upload,
        string $event,
        ?bool $fromActive,
        ?bool $toActive,
        User $actor,
        ?string $note = null
    ): void {
        BudgetStatusHistory::create([
            'budget_upload_id' => $upload->id,
            'event' => $event,
            'from_active' => $fromActive,
            'to_active' => $toActive,
            'note' => $note,
            'changed_by_user_id' => $actor->id,
            'created_at' => now(),
        ]);
    }
}
