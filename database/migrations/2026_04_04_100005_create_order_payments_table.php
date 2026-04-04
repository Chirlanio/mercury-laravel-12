<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->unsignedBigInteger('area_id')->nullable();
            $table->string('supplier_name');
            $table->text('description')->nullable();
            $table->decimal('total_value', 12, 2);
            $table->string('payment_type')->nullable();
            $table->enum('status', ['backlog', 'doing', 'waiting', 'done'])->default('backlog');
            $table->string('number_nf')->nullable();
            $table->string('launch_number')->nullable();
            $table->date('due_date')->nullable();
            $table->date('date_paid')->nullable();
            $table->integer('installments')->default(1);
            $table->string('bank_name')->nullable();
            $table->string('agency')->nullable();
            $table->string('checking_account')->nullable();
            $table->string('pix_key_type')->nullable();
            $table->string('pix_key')->nullable();
            $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index('status');
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_payments');
    }
};
