<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liga order_payments a purchase_orders. Nullable porque há ordens de
 * pagamento avulsas (não vinculadas a compra) e porque dados existentes
 * pré-Fase 1 ficam com NULL.
 *
 * onDelete('set null'): excluir uma ordem de compra preserva o histórico
 * financeiro — a ordem de pagamento permanece, só perde o link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('order_payments', 'purchase_order_id')) {
                $table->foreignId('purchase_order_id')
                    ->nullable()
                    ->after('supplier_id')
                    ->constrained('purchase_orders')
                    ->nullOnDelete();
                $table->index('purchase_order_id', 'idx_order_payments_purchase_order_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            if (Schema::hasColumn('order_payments', 'purchase_order_id')) {
                $table->dropForeign(['purchase_order_id']);
                $table->dropIndex('idx_order_payments_purchase_order_id');
                $table->dropColumn('purchase_order_id');
            }
        });
    }
};
