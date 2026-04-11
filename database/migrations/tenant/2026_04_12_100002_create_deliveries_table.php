<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('store_id', 10);
            $table->foreign('store_id')->references('code')->on('stores')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();

            // Client info
            $table->string('client_name');
            $table->string('address')->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('contact_phone', 20)->nullable();

            // Sale info
            $table->decimal('sale_value', 10, 2)->nullable();
            $table->string('payment_method', 50)->nullable();
            $table->unsignedTinyInteger('installments')->nullable();
            $table->boolean('needs_card_machine')->default(false);
            $table->boolean('is_exchange')->default(false);
            $table->boolean('is_gift')->default(false);

            // Status
            $table->string('status', 20)->default('requested');
            $table->text('observations')->nullable();

            // Audit
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('deleted_at')->nullable();
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
