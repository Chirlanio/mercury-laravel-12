<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('plan_id')->nullable()->constrained('tenant_plans')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('BRL');
            $table->date('billing_period_start');
            $table->date('billing_period_end');
            $table->string('status')->default('pending'); // pending, paid, overdue, cancelled
            $table->timestamp('paid_at')->nullable();
            $table->date('due_at');
            $table->string('payment_method')->nullable();
            $table->string('transaction_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_invoices');
    }
};
