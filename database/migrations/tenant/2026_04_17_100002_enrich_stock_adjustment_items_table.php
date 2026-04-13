<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_adjustment_items', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_adjustment_items', 'direction')) {
                $table->enum('direction', ['increase', 'decrease'])
                    ->default('increase')
                    ->after('size');
            }
            if (! Schema::hasColumn('stock_adjustment_items', 'quantity')) {
                $table->unsignedInteger('quantity')->default(0)->after('direction');
            }
            if (! Schema::hasColumn('stock_adjustment_items', 'current_stock')) {
                $table->integer('current_stock')->nullable()->after('quantity');
            }
            if (! Schema::hasColumn('stock_adjustment_items', 'reason_id')) {
                $table->foreignId('reason_id')
                    ->nullable()
                    ->after('current_stock')
                    ->constrained('stock_adjustment_reasons')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('stock_adjustment_items', 'notes')) {
                $table->text('notes')->nullable()->after('reason_id');
            }
        });

        // Backfill: registros antigos ficam com quantity=1 para não serem inválidos
        DB::table('stock_adjustment_items')
            ->where('quantity', 0)
            ->update(['quantity' => 1]);
    }

    public function down(): void
    {
        Schema::table('stock_adjustment_items', function (Blueprint $table) {
            if (Schema::hasColumn('stock_adjustment_items', 'reason_id')) {
                $table->dropForeign(['reason_id']);
                $table->dropColumn('reason_id');
            }
            foreach (['notes', 'current_stock', 'quantity', 'direction'] as $col) {
                if (Schema::hasColumn('stock_adjustment_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
