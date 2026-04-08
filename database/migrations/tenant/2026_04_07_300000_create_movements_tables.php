<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movement_types', function (Blueprint $table) {
            $table->id();
            $table->integer('code')->unique();
            $table->string('description', 100);
            $table->dateTime('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('movements', function (Blueprint $table) {
            $table->id();
            $table->date('movement_date');
            $table->time('movement_time')->nullable();
            $table->string('store_code', 4);
            $table->string('cpf_customer', 14)->nullable();
            $table->string('invoice_number', 50)->nullable();
            $table->integer('movement_code');
            $table->string('cpf_consultant', 14)->nullable();
            $table->string('ref_size', 50)->nullable();
            $table->string('barcode', 50)->nullable();
            $table->decimal('sale_price', 12, 2)->default(0);
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('realized_value', 12, 2)->default(0);
            $table->decimal('discount_value', 12, 2)->default(0);
            $table->decimal('quantity', 10, 3)->default(0);
            $table->char('entry_exit', 1);
            $table->decimal('net_value', 12, 2)->default(0);
            $table->decimal('net_quantity', 10, 3)->default(0);
            $table->string('sync_batch_id', 36)->nullable();
            $table->dateTime('synced_at')->nullable();
            $table->timestamps();

            // Indexes for performance on 5M+ rows
            $table->index(['movement_date', 'movement_code', 'store_code', 'cpf_consultant'], 'idx_mov_sales_agg');
            $table->index(['store_code', 'movement_code', 'movement_date'], 'idx_mov_store_code_date');
            $table->index('movement_date', 'idx_mov_date');
            $table->index('barcode', 'idx_mov_barcode');
            $table->index('sync_batch_id', 'idx_mov_batch');
        });

        Schema::create('movement_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('sync_type', 20);
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('total_records')->default(0);
            $table->unsignedInteger('processed_records')->default(0);
            $table->unsignedInteger('inserted_records')->default(0);
            $table->unsignedInteger('deleted_records')->default(0);
            $table->unsignedInteger('skipped_records')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->json('error_details')->nullable();
            $table->date('date_range_start')->nullable();
            $table->date('date_range_end')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->unsignedBigInteger('started_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('started_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movement_sync_logs');
        Schema::dropIfExists('movements');
        Schema::dropIfExists('movement_types');
    }
};
