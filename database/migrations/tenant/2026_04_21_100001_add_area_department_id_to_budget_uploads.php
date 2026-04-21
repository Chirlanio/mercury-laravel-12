<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 5 — Campo Área explícito no BudgetUpload.
 *
 * Antes o `scope_label` (texto livre) era o único identificador da área do
 * orçamento. O endpoint `management-classes/departments?year=Y` precisava
 * derivar a área listando MCs com budget_item ativo. Com a FK estrutural,
 * o filtro fica O(1) e a validação cruzada no import passa a ser possível.
 *
 * FK aponta para `management_classes` (tipicamente os sintéticos 8.1.DD —
 * Marketing, Operações, Fiscal, etc.). Nullable para não quebrar uploads
 * legacy. Obrigatoriedade vem em commit posterior depois do backfill em
 * produção (padrão C3 do roadmap).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('budget_uploads', 'area_department_id')) {
            return;
        }

        Schema::table('budget_uploads', function (Blueprint $table) {
            $table->unsignedBigInteger('area_department_id')
                ->nullable()
                ->after('scope_label');

            $table->foreign('area_department_id')
                ->references('id')
                ->on('management_classes')
                ->nullOnDelete();

            $table->index('area_department_id');
            $table->index(['year', 'area_department_id']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('budget_uploads', 'area_department_id')) {
            return;
        }

        Schema::table('budget_uploads', function (Blueprint $table) {
            $table->dropForeign(['area_department_id']);
            $table->dropIndex(['area_department_id']);
            $table->dropIndex(['year', 'area_department_id']);
            $table->dropColumn('area_department_id');
        });
    }
};
