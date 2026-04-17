<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reversal_reasons')) {
            return;
        }

        Schema::create('reversal_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('is_active');
        });

        // Seed inicial — paridade com adms_motivo_estorno (v1) + motivos comuns do varejo
        $now = now();
        DB::table('reversal_reasons')->insert([
            ['code' => 'FURO_ESTOQUE', 'name' => 'Furo de estoque', 'description' => 'Produto vendido mas sem saldo físico no estoque.', 'is_active' => true, 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'DESISTENCIA', 'name' => 'Cliente desistiu da compra', 'description' => 'Cliente solicitou cancelamento da venda antes ou logo após a finalização.', 'is_active' => true, 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'TAMANHO_ERRADO', 'name' => 'Tamanho errado', 'description' => 'Produto registrado com tamanho incorreto na venda.', 'is_active' => true, 'sort_order' => 30, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'VALOR_INCORRETO', 'name' => 'Valor registrado incorreto', 'description' => 'Produto registrado com preço diferente do correto.', 'is_active' => true, 'sort_order' => 40, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'DUPLICIDADE', 'name' => 'Venda duplicada', 'description' => 'Mesma venda registrada mais de uma vez no sistema.', 'is_active' => true, 'sort_order' => 50, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'QUALIDADE', 'name' => 'Defeito / Qualidade', 'description' => 'Produto apresentou defeito de qualidade após a venda.', 'is_active' => true, 'sort_order' => 60, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'TROCA', 'name' => 'Troca de produto', 'description' => 'Cliente trocou o produto por outro item — estorno do item original.', 'is_active' => true, 'sort_order' => 70, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'OUTROS', 'name' => 'Outros', 'description' => 'Outros motivos — detalhar na observação do estorno.', 'is_active' => true, 'sort_order' => 999, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('reversal_reasons');
    }
};
