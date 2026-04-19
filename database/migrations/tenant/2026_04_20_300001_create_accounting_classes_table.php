<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plano de Contas Contábil — fundação do DRE (Fase 0.2).
 *
 * Hierarquia auto-referencial permite grupos sintéticos (agregadores —
 * accepts_entries=false) e contas analíticas (folhas — accepts_entries=true).
 *
 * Cada conta pertence a exatamente um DreGroup, determinando onde entra
 * no relatório de DRE. A `nature` (débito/crédito) é livre — tipicamente
 * segue a natureza natural do grupo, mas pode divergir (ex: desconto
 * obtido é receita na prática, mas algumas entidades contabilizam como
 * redutor de despesa financeira).
 *
 * Soft delete manual seguindo o padrão de Reversal/Return/CostCenter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_classes', function (Blueprint $table) {
            $table->id();

            $table->string('code', 30);
            $table->string('name');
            $table->text('description')->nullable();

            $table->unsignedBigInteger('parent_id')->nullable();
            $table->foreign('parent_id')
                ->references('id')
                ->on('accounting_classes')
                ->nullOnDelete();

            $table->string('nature', 10);          // AccountingNature enum
            $table->string('dre_group', 40);       // DreGroup enum
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
            $table->index('dre_group');
            $table->index('accepts_entries');
            $table->index('is_active');
            $table->index('deleted_at');
        });

        // Agora que a tabela existe, adiciona a FK pendente em cost_centers
        // que foi preparada (coluna sem FK) no Commit 1 da Fase 0.1.
        Schema::table('cost_centers', function (Blueprint $table) {
            $table->foreign('default_accounting_class_id')
                ->references('id')
                ->on('accounting_classes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cost_centers', function (Blueprint $table) {
            $table->dropForeign(['default_accounting_class_id']);
        });

        Schema::dropIfExists('accounting_classes');
    }
};
