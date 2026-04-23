<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log das sincronizações de clientes com CIGAM. Mesmo padrão do
 * product_sync_logs — tracking de progresso para runs longas (view
 * msl_dcliente_ pode ter centenas de milhares de registros).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('sync_type', 30);             // full | incremental | range
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('total_records')->default(0);
            $table->unsignedInteger('processed_records')->default(0);
            $table->unsignedInteger('inserted_records')->default(0);
            $table->unsignedInteger('updated_records')->default(0);
            $table->unsignedInteger('skipped_records')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->json('error_details')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('started_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_sync_logs');
    }
};
