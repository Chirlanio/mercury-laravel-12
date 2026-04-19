<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plano de Contas Gerencial (Fase 0.3 — última peça da fundação Budgets/DRE).
 *
 * ManagementClass é a visão **interna/operacional** do financeiro — como
 * a empresa organiza suas despesas para gestão. Diferente do plano
 * contábil (que segue normas BR para DRE), o plano gerencial reflete
 * como os gestores agrupam custos para tomada de decisão.
 *
 * Relacionamento com AccountingClass: opcional no MVP. Cada management
 * class pode apontar para uma accounting_class correspondente (para
 * mapear automaticamente no DRE), mas começa vazio — tenant popula
 * conforme uso. Quando obrigatório (tenant ativa DRE), a migration
 * auxiliar futura poderá validar.
 *
 * Relacionamento com CostCenter: opcional. Vínculo default útil para
 * import de orçamento — quando uma linha da planilha traz só a
 * management_class, o sistema resolve o cost_center pelo vínculo default
 * da management_class.
 *
 * Sem seed inicial — v1 não tem plano gerencial estruturado, tenant
 * popula conforme faz os primeiros uploads de orçamento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('management_classes', function (Blueprint $table) {
            $table->id();

            $table->string('code', 30);
            $table->string('name');
            $table->text('description')->nullable();

            $table->unsignedBigInteger('parent_id')->nullable();
            $table->foreign('parent_id')
                ->references('id')
                ->on('management_classes')
                ->nullOnDelete();

            // Vínculo contábil — opcional no MVP, obrigatório quando DRE for ativado
            $table->unsignedBigInteger('accounting_class_id')->nullable();
            $table->foreign('accounting_class_id')
                ->references('id')
                ->on('accounting_classes')
                ->nullOnDelete();

            // Vínculo default de centro de custo — útil para import de orçamento
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->foreign('cost_center_id')
                ->references('id')
                ->on('cost_centers')
                ->nullOnDelete();

            $table->boolean('accepts_entries')->default(true);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();

            $table->timestamps();

            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by_user_id')->nullable();
            $table->string('deleted_reason', 500)->nullable();

            $table->unique('code');
            $table->index('parent_id');
            $table->index('accounting_class_id');
            $table->index('cost_center_id');
            $table->index('accepts_entries');
            $table->index('is_active');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('management_classes');
    }
};
