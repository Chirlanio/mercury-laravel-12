<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promove `cost_centers` de módulo config simples para módulo standalone,
 * preparando também a fundação do futuro DRE:
 *  - `description` (texto livre)
 *  - `parent_id` (hierarquia self-reference para drill-down DRE)
 *  - `default_accounting_class_id` (coluna indexada agora — FK adicionada
 *    quando a tabela `accounting_classes` for criada na Fase 0.2)
 *  - audit de criação/atualização
 *  - soft delete manual (padrão Reversals/Returns, sem trait)
 *  - unique em `code` (corrige gap da migration original — validação usava
 *    `unique:cost_centers,code` sem índice de suporte).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cost_centers', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');

            $table->unsignedBigInteger('parent_id')->nullable()->after('area_id');
            $table->foreign('parent_id')
                ->references('id')
                ->on('cost_centers')
                ->nullOnDelete();

            $table->unsignedBigInteger('default_accounting_class_id')
                ->nullable()
                ->after('parent_id');

            $table->unsignedBigInteger('created_by_user_id')->nullable()->after('is_active');
            $table->unsignedBigInteger('updated_by_user_id')->nullable()->after('created_by_user_id');

            $table->timestamp('deleted_at')->nullable()->after('updated_at');
            $table->unsignedBigInteger('deleted_by_user_id')->nullable()->after('deleted_at');
            $table->string('deleted_reason', 500)->nullable()->after('deleted_by_user_id');

            $table->unique('code');
            $table->index('parent_id');
            $table->index('default_accounting_class_id');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('cost_centers', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropUnique(['code']);
            $table->dropIndex(['parent_id']);
            $table->dropIndex(['default_accounting_class_id']);
            $table->dropIndex(['deleted_at']);

            $table->dropColumn([
                'description',
                'parent_id',
                'default_accounting_class_id',
                'created_by_user_id',
                'updated_by_user_id',
                'deleted_at',
                'deleted_by_user_id',
                'deleted_reason',
            ]);
        });
    }
};
