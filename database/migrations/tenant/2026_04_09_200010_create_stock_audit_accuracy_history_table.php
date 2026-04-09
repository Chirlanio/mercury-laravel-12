<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_audit_accuracy_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audit_id')->constrained('stock_audits')->cascadeOnDelete();
            $table->decimal('accuracy_percentage', 5, 2);
            $table->unsignedInteger('total_items');
            $table->unsignedInteger('total_divergences');
            $table->decimal('financial_loss', 12, 2);
            $table->decimal('financial_surplus', 12, 2);
            $table->decimal('financial_loss_cost', 12, 2)->default(0);
            $table->decimal('financial_surplus_cost', 12, 2)->default(0);
            $table->string('audit_type', 20);
            $table->date('audit_date');
            $table->timestamps();

            $table->index(['store_id', 'audit_date']);
        });

        // Log table for status transitions
        Schema::create('stock_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained('stock_audits')->cascadeOnDelete();
            $table->string('action_type', 50);
            $table->string('old_status', 30)->nullable();
            $table->string('new_status', 30)->nullable();
            $table->foreignId('changed_by_user_id')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_audit_logs');
        Schema::dropIfExists('stock_audit_accuracy_history');
    }
};
