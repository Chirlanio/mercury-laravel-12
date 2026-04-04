<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->enum('status', [
                'pending', 'under_analysis', 'awaiting_response',
                'balance_transfer', 'adjusted', 'no_adjustment', 'cancelled',
            ])->default('pending');
            $table->text('observation')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by_user_id')->nullable();
            $table->string('delete_reason', 500)->nullable();
            $table->timestamps();

            $table->foreign('deleted_by_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['store_id', 'status']);
            $table->index('status');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
