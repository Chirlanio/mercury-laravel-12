<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop old tables (disable FK checks for clean rebuild)
        $driver = \Illuminate\Support\Facades\Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0');
        } else {
            \Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = OFF');
        }

        Schema::dropIfExists('order_payment_status_history');
        Schema::dropIfExists('order_payment_allocations');
        Schema::dropIfExists('order_payment_installments');
        Schema::dropIfExists('order_payments');

        if ($driver === 'mysql') {
            \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1');
        } else {
            \Illuminate\Support\Facades\DB::statement('PRAGMA foreign_keys = ON');
        }

        // Create full schema matching legacy v1
        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();

            // Relationships (nullable FKs — referenced tables may not exist in test DB)
            $table->unsignedBigInteger('area_id')->nullable();
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();

            // Payment details
            $table->text('description');
            $table->decimal('total_value', 15, 2);
            $table->date('date_payment');
            $table->string('payment_type')->nullable(); // PIX, Transferência, Boleto, etc.
            $table->integer('installments')->default(0);

            // Banking fields
            $table->string('bank_name')->nullable();
            $table->string('agency', 20)->nullable();
            $table->string('checking_account', 25)->nullable();
            $table->string('type_account')->nullable();
            $table->string('name_supplier', 100)->nullable();
            $table->string('document_number_supplier', 20)->nullable();

            // PIX fields
            $table->string('pix_key_type')->nullable();
            $table->string('pix_key', 255)->nullable();

            // Advance payment
            $table->boolean('advance')->default(false);
            $table->decimal('advance_amount', 15, 2)->default(0);
            $table->boolean('advance_paid')->default(false);
            $table->decimal('diff_payment_advance', 15, 2)->default(0);

            // Fiscal fields
            $table->string('number_nf', 50)->nullable();
            $table->string('launch_number', 50)->nullable();
            $table->boolean('proof')->default(false);
            $table->boolean('payment_prepared')->default(false);

            // Status workflow (Kanban)
            $table->string('status', 20)->default('backlog');
            $table->date('date_paid')->nullable();

            // Allocation
            $table->boolean('has_allocation')->default(false);

            // Files
            $table->string('file_name', 255)->nullable();

            // Observations
            $table->text('observations')->nullable();

            // Audit
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Soft delete with audit
            $table->timestamp('deleted_at')->nullable();
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('delete_reason', 500)->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['status', 'deleted_at', 'date_payment']);
            $table->index(['status', 'deleted_at', 'total_value']);
            $table->index('store_id');
            $table->index('date_paid');
            $table->index('deleted_at');
            $table->index('date_payment');
        });

        // Installments table
        Schema::create('order_payment_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_payment_id')->constrained('order_payments')->cascadeOnDelete();
            $table->integer('installment_number');
            $table->decimal('installment_value', 15, 2);
            $table->date('date_payment');
            $table->boolean('is_paid')->default(false);
            $table->date('date_paid')->nullable();
            $table->foreignId('paid_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['order_payment_id', 'installment_number'], 'uk_op_installment');
            $table->index('order_payment_id');
            $table->index('date_payment');
            $table->index('is_paid');
        });

        // Allocations table (rateio)
        Schema::create('order_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_payment_id')->constrained('order_payments')->cascadeOnDelete();
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->decimal('allocation_percentage', 5, 2);
            $table->decimal('allocation_value', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('order_payment_id');
        });

        // Status history table
        Schema::create('order_payment_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_payment_id')->constrained('order_payments')->cascadeOnDelete();
            $table->string('old_status')->nullable();
            $table->string('new_status');
            $table->foreignId('changed_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('order_payment_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_payment_status_history');
        Schema::dropIfExists('order_payment_allocations');
        Schema::dropIfExists('order_payment_installments');
        Schema::dropIfExists('order_payments');
    }
};
