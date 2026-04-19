<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo Budgets (Orçamentos) — Fase 1 MVP.
 *
 * Paridade estrutural com `adms_budgets_uploads` + `adms_budgets_items`
 * da v1, adaptado ao modelo v2:
 *   - `scope_label` VARCHAR (substitui FK `adms_area_id` da v1, que não
 *     existe em v2). Tenant usa como quiser: "Administrativo", "TI 2026",
 *     "Geral". É um identificador lógico do grupo de linhas.
 *   - FKs reais nos items: accounting_class_id + management_class_id +
 *     cost_center_id + store_id (obrigatórios, não-nullable — decisão
 *     tomada na Fase 0: toda linha deve ter CC). O import (Fase 2) vai
 *     resolver via preview+reconciliação.
 *
 * Versionamento v1 preservado: major_version + minor_version + version_label
 * ("1.0", "1.01", "2.0"). Regras no BudgetVersionService.
 *
 * Soft delete manual + audit + status_histories para rastrear transições
 * (upload novo desativa o anterior etc.). State machine muito simples:
 * só `is_active` bool — sem workflow de aprovação no MVP.
 */
return new class extends Migration
{
    public function up(): void
    {
        // -------------------------------------------------------------
        // budget_uploads — cabeçalho de cada orçamento (versão ativa)
        // -------------------------------------------------------------
        Schema::create('budget_uploads', function (Blueprint $table) {
            $table->id();

            // Identificação do orçamento
            $table->integer('year');                        // 2026, 2027...
            $table->string('scope_label', 100);             // "Administrativo", "TI", "Geral"

            // Versionamento (paridade v1)
            $table->string('version_label', 20);            // "1.0", "1.01", "2.0"
            $table->integer('major_version')->default(1);
            $table->integer('minor_version')->default(0);
            $table->string('upload_type', 10);              // 'novo' | 'ajuste'

            // Arquivo original armazenado (para re-download)
            $table->string('original_filename');
            $table->string('stored_path');                  // caminho relativo no disk
            $table->integer('file_size_bytes')->nullable();

            // Status
            $table->boolean('is_active')->default(false);   // uma versão ativa por (year+scope)
            $table->text('notes')->nullable();

            // Totais calculados (cache para listagem rápida)
            $table->decimal('total_year', 17, 2)->default(0);
            $table->integer('items_count')->default(0);

            // Audit
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by_user_id')->nullable();
            $table->string('deleted_reason', 500)->nullable();

            $table->index(['year', 'scope_label']);
            $table->index(['year', 'scope_label', 'is_active']);
            $table->index('is_active');
            $table->index('deleted_at');
        });

        // -------------------------------------------------------------
        // budget_items — linhas do orçamento com FKs resolvidas + 12 meses
        // -------------------------------------------------------------
        Schema::create('budget_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('budget_upload_id');
            $table->foreign('budget_upload_id')
                ->references('id')
                ->on('budget_uploads')
                ->cascadeOnDelete();

            // FKs obrigatórias (decisão da Fase 0: toda linha tem CC)
            $table->unsignedBigInteger('accounting_class_id');
            $table->foreign('accounting_class_id')
                ->references('id')
                ->on('accounting_classes')
                ->restrictOnDelete();

            $table->unsignedBigInteger('management_class_id');
            $table->foreign('management_class_id')
                ->references('id')
                ->on('management_classes')
                ->restrictOnDelete();

            $table->unsignedBigInteger('cost_center_id');
            $table->foreign('cost_center_id')
                ->references('id')
                ->on('cost_centers')
                ->restrictOnDelete();

            // Store é opcional — algumas linhas são "geral" sem loja específica
            $table->unsignedBigInteger('store_id')->nullable();
            $table->foreign('store_id')
                ->references('id')
                ->on('stores')
                ->nullOnDelete();

            // Metadados da linha (snapshot do Excel original)
            $table->string('supplier', 255)->nullable();    // texto livre, v1 preservada
            $table->text('justification')->nullable();
            $table->string('account_description', 255)->nullable();
            $table->string('class_description', 255)->nullable();

            // 12 valores mensais — decimal padrão BR
            $table->decimal('month_01_value', 15, 2)->default(0);
            $table->decimal('month_02_value', 15, 2)->default(0);
            $table->decimal('month_03_value', 15, 2)->default(0);
            $table->decimal('month_04_value', 15, 2)->default(0);
            $table->decimal('month_05_value', 15, 2)->default(0);
            $table->decimal('month_06_value', 15, 2)->default(0);
            $table->decimal('month_07_value', 15, 2)->default(0);
            $table->decimal('month_08_value', 15, 2)->default(0);
            $table->decimal('month_09_value', 15, 2)->default(0);
            $table->decimal('month_10_value', 15, 2)->default(0);
            $table->decimal('month_11_value', 15, 2)->default(0);
            $table->decimal('month_12_value', 15, 2)->default(0);

            // Total anual — calculado no service/model, não GENERATED
            // (SQLite dos testes não suporta decimal generated column)
            $table->decimal('year_total', 17, 2)->default(0);

            $table->timestamps();

            $table->index('budget_upload_id');
            $table->index('accounting_class_id');
            $table->index('management_class_id');
            $table->index('cost_center_id');
            $table->index('store_id');
            $table->index(['budget_upload_id', 'cost_center_id']);
        });

        // -------------------------------------------------------------
        // budget_status_histories — audit trail de transições is_active
        // -------------------------------------------------------------
        Schema::create('budget_status_histories', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('budget_upload_id');
            $table->foreign('budget_upload_id')
                ->references('id')
                ->on('budget_uploads')
                ->cascadeOnDelete();

            $table->string('event', 50);                    // 'created', 'activated', 'deactivated', 'deleted'
            $table->boolean('from_active')->nullable();
            $table->boolean('to_active')->nullable();
            $table->text('note')->nullable();

            $table->unsignedBigInteger('changed_by_user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('budget_upload_id');
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_status_histories');
        Schema::dropIfExists('budget_items');
        Schema::dropIfExists('budget_uploads');
    }
};
