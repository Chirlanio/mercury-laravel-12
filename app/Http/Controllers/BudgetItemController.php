<?php

namespace App\Http\Controllers;

use App\Models\BudgetItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Edição inline de items de orçamento — Melhoria 8 do roadmap.
 *
 * Só expõe o update — o item é criado/deletado via fluxo de upload
 * (Fase 1/2) que substitui a versão inteira do orçamento. Edição
 * inline é o canal para ajustes pontuais (valor errado num mês,
 * fornecedor, descrição) sem forçar re-upload da planilha completa.
 *
 * Campos editáveis (texto/valores):
 *   - supplier, justification, account_description, class_description
 *   - month_01_value..month_12_value
 *
 * NÃO editáveis (estruturais — mudanças exigem upload novo):
 *   - accounting_class_id, management_class_id, cost_center_id, store_id
 *   - budget_upload_id (imutável por design)
 *
 * Após o update:
 *   - Recalcula year_total do item (soma dos 12 meses)
 *   - Recalcula total_year + items_count do upload pai
 *   - Registro via Auditable trait (ActivityLog com diff)
 */
class BudgetItemController extends Controller
{
    public function update(Request $request, BudgetItem $budgetItem): JsonResponse
    {
        // Item que pertence a upload soft-deleted não pode ser editado
        $upload = $budgetItem->upload;
        if (! $upload || $upload->isDeleted()) {
            throw ValidationException::withMessages([
                'budget' => 'O orçamento deste item foi excluído e não pode mais ser editado.',
            ]);
        }

        $validated = $request->validate([
            'supplier' => 'nullable|string|max:255',
            'justification' => 'nullable|string|max:2000',
            'account_description' => 'nullable|string|max:255',
            'class_description' => 'nullable|string|max:255',
            'month_01_value' => 'nullable|numeric|min:0',
            'month_02_value' => 'nullable|numeric|min:0',
            'month_03_value' => 'nullable|numeric|min:0',
            'month_04_value' => 'nullable|numeric|min:0',
            'month_05_value' => 'nullable|numeric|min:0',
            'month_06_value' => 'nullable|numeric|min:0',
            'month_07_value' => 'nullable|numeric|min:0',
            'month_08_value' => 'nullable|numeric|min:0',
            'month_09_value' => 'nullable|numeric|min:0',
            'month_10_value' => 'nullable|numeric|min:0',
            'month_11_value' => 'nullable|numeric|min:0',
            'month_12_value' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($budgetItem, $validated, $upload) {
            // Aplica campos validados + recalcula year_total
            $budgetItem->fill($validated);

            $yearTotal = 0.0;
            for ($m = 1; $m <= 12; $m++) {
                $col = 'month_'.str_pad((string) $m, 2, '0', STR_PAD_LEFT).'_value';
                $yearTotal += (float) $budgetItem->{$col};
            }
            $budgetItem->year_total = round($yearTotal, 2);
            $budgetItem->save();

            // Recalcula total do upload pai (1 SELECT agregado)
            $agg = DB::table('budget_items')
                ->where('budget_upload_id', $upload->id)
                ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(year_total), 0) as total')
                ->first();

            $upload->update([
                'items_count' => (int) $agg->cnt,
                'total_year' => round((float) $agg->total, 2),
                'updated_by_user_id' => auth()->id(),
            ]);
        });

        $budgetItem->refresh();

        return response()->json([
            'item' => [
                'id' => $budgetItem->id,
                'supplier' => $budgetItem->supplier,
                'justification' => $budgetItem->justification,
                'account_description' => $budgetItem->account_description,
                'class_description' => $budgetItem->class_description,
                'months' => collect(range(1, 12))->mapWithKeys(function ($m) use ($budgetItem) {
                    $col = 'month_'.str_pad((string) $m, 2, '0', STR_PAD_LEFT).'_value';

                    return [$m => (float) $budgetItem->{$col}];
                })->all(),
                'year_total' => (float) $budgetItem->year_total,
            ],
            'upload' => [
                'id' => $upload->id,
                'total_year' => (float) $upload->fresh()->total_year,
                'items_count' => (int) $upload->fresh()->items_count,
            ],
        ]);
    }
}
