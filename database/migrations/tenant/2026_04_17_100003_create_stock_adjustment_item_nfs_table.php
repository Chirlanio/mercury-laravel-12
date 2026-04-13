<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stock_adjustment_item_nfs')) {
            return;
        }

        Schema::create('stock_adjustment_item_nfs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')
                ->constrained('stock_adjustments')
                ->cascadeOnDelete();
            $table->foreignId('stock_adjustment_item_id')
                ->nullable()
                ->constrained('stock_adjustment_items')
                ->cascadeOnDelete();
            $table->string('nf_entrada', 50)->nullable();
            $table->string('nf_saida', 50)->nullable();
            $table->string('nf_entrada_serie', 10)->nullable();
            $table->string('nf_saida_serie', 10)->nullable();
            $table->date('nf_entrada_date')->nullable();
            $table->date('nf_saida_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->index('stock_adjustment_id');
            $table->index('stock_adjustment_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_item_nfs');
    }
};
