<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Torna `purchase_orders.supplier_id` nullable.
 *
 * Alinha com a arquitetura real do varejo de moda (e com o Mercury v1):
 * a ordem de compra é organizada por MARCA (quem assina a coleção), não
 * por fornecedor. Fornecedor é um conceito de `order_payments` (quem
 * emite NF e recebe pagamento). Uma mesma ordem de compra pode virar
 * múltiplos pagamentos para fornecedores diferentes.
 *
 * Importação histórica v1 não traz fornecedor na planilha — as ordens
 * importadas ficarão com `supplier_id=null`. Criação manual via UI pode
 * continuar exigindo supplier (decisão do frontend).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            // Drop + recria a FK porque não dá pra alterar nullability
            // em colunas com constraint em alguns drivers
            $table->dropForeign(['supplier_id']);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('supplier_id')->nullable()->change();
            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            // Atenção: dados existentes com supplier_id NULL precisarão
            // ser preenchidos antes do rollback em produção
            $table->unsignedBigInteger('supplier_id')->nullable(false)->change();
            $table->foreign('supplier_id')
                ->references('id')
                ->on('suppliers')
                ->restrictOnDelete();
        });
    }
};
