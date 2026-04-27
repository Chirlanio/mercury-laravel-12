<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tipos de remanejo (paridade com adms_tps_remanejos da v1).
 *
 * Categoriza a motivação da solicitação para permitir filtros e análise
 * agregada no dashboard. O tipo NÃO afeta a state machine — é puramente
 * descritivo.
 *
 * Seed inline (igual reversal_reasons / return_reasons) — sem seeder
 * separado pra reduzir arquivos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('relocation_types')) {
            return;
        }

        Schema::create('relocation_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('is_active');
        });

        $now = now();
        DB::table('relocation_types')->insert([
            ['code' => 'PLANEJAMENTO', 'name' => 'Planejamento', 'description' => 'Remanejo programado pela equipe de planejamento com base em curva ABC e análise de giro.', 'is_active' => true, 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'BALANCEAMENTO', 'name' => 'Balanceamento', 'description' => 'Equilíbrio de estoque entre lojas com excesso (origem) e ruptura (destino) do mesmo produto.', 'is_active' => true, 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'RUPTURA', 'name' => 'Ruptura', 'description' => 'Atendimento urgente a uma situação de ruptura crítica em loja com demanda comprovada.', 'is_active' => true, 'sort_order' => 30, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'SOLICITACAO_LOJA', 'name' => 'Solicitação da Loja', 'description' => 'Pedido aberto pela própria loja destino solicitando reposição específica.', 'is_active' => true, 'sort_order' => 40, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'REPOSICAO_COLECAO', 'name' => 'Reposição de Coleção', 'description' => 'Distribuição inicial ou complementar de coleção nova entre as lojas.', 'is_active' => true, 'sort_order' => 50, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('relocation_types');
    }
};
