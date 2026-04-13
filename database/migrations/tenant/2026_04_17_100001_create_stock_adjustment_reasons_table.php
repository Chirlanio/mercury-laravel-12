<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stock_adjustment_reasons')) {
            return;
        }

        Schema::create('stock_adjustment_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            // Quais direções o motivo se aplica: increase, decrease ou both
            $table->enum('applies_to', ['increase', 'decrease', 'both'])->default('both');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('is_active');
            $table->index('applies_to');
        });

        // Seed baseado nos 11 motivos originais do v1 (tb_justificativas)
        $now = now();
        DB::table('stock_adjustment_reasons')->insert([
            ['code' => 'TRANSF_PENDENTE', 'name' => 'Transferência pendente', 'description' => 'Transferência enviada mas ainda não baixada no sistema.', 'applies_to' => 'both', 'is_active' => true, 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'CONSIG_PENDENTE', 'name' => 'Consignação pendente', 'description' => 'Produto em consignação ainda não registrado.', 'applies_to' => 'both', 'is_active' => true, 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'NF_COMPRA_PENDENTE', 'name' => 'Nota Fiscal de compra pendente', 'description' => 'Mercadoria recebida mas a NF de entrada ainda não foi lançada.', 'applies_to' => 'increase', 'is_active' => true, 'sort_order' => 30, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'VENDA_DUPLICADA', 'name' => 'Venda duplicada', 'description' => 'A mesma venda foi lançada mais de uma vez no sistema.', 'applies_to' => 'increase', 'is_active' => true, 'sort_order' => 40, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'VENDA_REGISTRADA', 'name' => 'Venda já registrada', 'description' => 'Produto vendido mas não baixado do estoque.', 'applies_to' => 'decrease', 'is_active' => true, 'sort_order' => 50, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'TRANSF_SALDO', 'name' => 'Transferência de saldo', 'description' => 'Ajuste de saldo entre lojas/depósitos.', 'applies_to' => 'both', 'is_active' => true, 'sort_order' => 60, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'PROD_SEM_TRANSF', 'name' => 'Produto enviado sem transferência', 'description' => 'Mercadoria chegou fisicamente sem transferência de saldo registrada.', 'applies_to' => 'increase', 'is_active' => true, 'sort_order' => 70, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'REF_ERRADA', 'name' => 'Referência errada', 'description' => 'Produto cadastrado ou lançado com referência incorreta.', 'applies_to' => 'both', 'is_active' => true, 'sort_order' => 80, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'AVARIA', 'name' => 'Produto avariado', 'description' => 'Produto danificado e precisa ser baixado do estoque.', 'applies_to' => 'decrease', 'is_active' => true, 'sort_order' => 90, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'PERDA', 'name' => 'Perda / Furto', 'description' => 'Produto extraviado, furtado ou perdido.', 'applies_to' => 'decrease', 'is_active' => true, 'sort_order' => 100, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'INVENTARIO', 'name' => 'Divergência de inventário', 'description' => 'Diferença apontada em contagem física de estoque.', 'applies_to' => 'both', 'is_active' => true, 'sort_order' => 110, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'OUTROS', 'name' => 'Outros', 'description' => 'Outros motivos — detalhar na observação.', 'applies_to' => 'both', 'is_active' => true, 'sort_order' => 999, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_reasons');
    }
};
