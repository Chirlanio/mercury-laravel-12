<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5 — Backfill de area_department_id em uploads legacy.
 *
 * Para cada BudgetUpload sem área definida, detecta o departamento
 * (management_class sintético 8.1.DD) dominante no upload — aquele cujo
 * SUM(year_total) dos items é o maior. Atribui como `area_department_id`.
 *
 * Lógica:
 *   1. Join budget_items → management_classes → (parent_id)
 *   2. Agrupa por parent_id (= departamento) e soma year_total
 *   3. Pega o parent_id com maior total
 *   4. Atribui ao upload
 *
 * Idempotente — skippa uploads que já têm área.
 * Uploads sem items (edge case) permanecem com area_department_id=null.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('budget_uploads')
            || ! Schema::hasColumn('budget_uploads', 'area_department_id')) {
            return;
        }

        $uploads = DB::table('budget_uploads')
            ->whereNull('area_department_id')
            ->whereNull('deleted_at')
            ->pluck('id');

        foreach ($uploads as $uploadId) {
            // MC dominante do upload: a que agrega mais year_total
            // (quando várias MCs do mesmo dept aparecem, parent_id deduplicado
            // via GROUP BY ainda escolhe o mais relevante).
            $topDept = DB::table('budget_items')
                ->join('management_classes', 'management_classes.id', '=', 'budget_items.management_class_id')
                ->where('budget_items.budget_upload_id', $uploadId)
                ->whereNotNull('management_classes.parent_id')
                ->selectRaw('management_classes.parent_id as dept_id, SUM(budget_items.year_total) as total')
                ->groupBy('management_classes.parent_id')
                ->orderByDesc('total')
                ->first();

            if ($topDept && $topDept->dept_id) {
                DB::table('budget_uploads')
                    ->where('id', $uploadId)
                    ->update(['area_department_id' => $topDept->dept_id]);
            }
        }
    }

    public function down(): void
    {
        // No-op — o rollback natural é perder os valores backfilleados.
        // Como a coluna é nullable, manter os valores não causa harm.
    }
};
