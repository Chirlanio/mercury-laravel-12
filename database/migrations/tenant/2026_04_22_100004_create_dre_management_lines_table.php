<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DRE — Prompt #1.
 *
 * Linhas da DRE gerencial (apresentação executiva). Estrutura única vigente —
 * sem versionamento paralelo (decisão do usuário em dre-descoberta §7 #5).
 * A evolução usa `effective_from`/`effective_to` em `dre_mappings` + snapshot
 * em `dre_period_closing_snapshots` para imutabilidade histórica.
 *
 * Cardinalidade: ~20 linhas (seed inicial entrega 16 linhas DRE-BR padrão
 * derivadas do enum DreGroup + 1 linha-fantasma L99_UNCLASSIFIED; CFO
 * insere depois via UI as linhas executivas específicas Headcount,
 * Marketing/Corporativo, EBITDA, Lucro Líquido s/ Cedro).
 *
 * `accumulate_until_sort_order` permite subtotais não-encadeados (ex:
 * EBITDA acumula 1..13 sem incluir Impostos de linha 15).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dre_management_lines', function (Blueprint $table) {
            $table->id();

            $table->string('code', 20);
            $table->smallInteger('sort_order');
            $table->boolean('is_subtotal')->default(false);
            $table->smallInteger('accumulate_until_sort_order')->nullable();

            $table->string('level_1', 150);
            $table->string('level_2', 150)->nullable();
            $table->string('level_3', 150)->nullable();
            $table->string('level_4', 150)->nullable();

            // nature: 'revenue' / 'expense' / 'subtotal'
            $table->string('nature', 10);

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedBigInteger('updated_by_user_id')->nullable();

            $table->timestamps();

            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by_user_id')->nullable();
            $table->string('deleted_reason', 500)->nullable();

            $table->unique('code', 'dre_management_lines_code_unique');
            // sort_order único só entre ativos (deleted_at IS NULL) — Laravel
            // abstrai isso via unique no MySQL mas SQLite trata como parcial
            // só via expressão; aqui usamos unique simples e a service cuida
            // de não recriar sort_order quando outra está soft-deleted.
            $table->unique('sort_order', 'dre_management_lines_sort_order_unique');

            $table->index('is_active');
            $table->index('is_subtotal');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dre_management_lines');
    }
};
