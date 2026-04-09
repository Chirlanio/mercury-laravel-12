<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audit_cycle_id')->nullable()->constrained('stock_audit_cycles')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('stock_audit_vendors')->nullOnDelete();
            $table->string('audit_type', 20); // total, parcial, especifica, aleatoria, diaria
            $table->string('status', 30)->default('draft');
            $table->foreignId('manager_responsible_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('stockist_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->unsignedInteger('random_sample_size')->nullable();
            $table->boolean('requires_second_count')->default(false);
            $table->boolean('requires_third_count')->default(false);
            $table->boolean('count_1_finalized')->default(false);
            $table->boolean('count_2_finalized')->default(false);
            $table->boolean('count_3_finalized')->default(false);
            $table->string('reconciliation_phase', 10)->nullable(); // A, B, C
            $table->foreignId('authorized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->decimal('accuracy_percentage', 5, 2)->nullable();
            $table->unsignedInteger('total_items_counted')->default(0);
            $table->unsignedInteger('total_divergences')->default(0);
            $table->decimal('financial_loss', 12, 2)->default(0);
            $table->decimal('financial_surplus', 12, 2)->default(0);
            $table->decimal('financial_loss_cost', 12, 2)->default(0);
            $table->decimal('financial_surplus_cost', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('delete_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index('status');
            $table->index('audit_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_audits');
    }
};
