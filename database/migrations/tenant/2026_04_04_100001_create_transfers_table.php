<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('origin_store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('destination_store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('invoice_number')->nullable();
            $table->integer('volumes_qty')->nullable();
            $table->integer('products_qty')->nullable();
            $table->enum('transfer_type', ['transfer', 'relocation', 'return', 'exchange'])->default('transfer');
            $table->enum('status', ['pending', 'in_transit', 'delivered', 'confirmed', 'cancelled'])->default('pending');
            $table->text('observations')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('driver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('receiver_name')->nullable();
            $table->date('pickup_date')->nullable();
            $table->time('pickup_time')->nullable();
            $table->date('delivery_date')->nullable();
            $table->time('delivery_time')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['origin_store_id', 'status']);
            $table->index(['destination_store_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
