<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_audit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained('stock_audits')->cascadeOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('stock_audit_areas')->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('product_reference', 50);
            $table->string('product_description', 255);
            $table->string('product_barcode', 50); // snapshot of aux_reference
            $table->string('product_size', 20)->nullable();
            $table->decimal('system_quantity', 10, 2)->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('cost_price', 12, 2)->default(0);

            // Count round 1
            $table->decimal('count_1', 10, 2)->nullable();
            $table->foreignId('count_1_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('count_1_at')->nullable();

            // Count round 2
            $table->decimal('count_2', 10, 2)->nullable();
            $table->foreignId('count_2_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('count_2_at')->nullable();

            // Count round 3
            $table->decimal('count_3', 10, 2)->nullable();
            $table->foreignId('count_3_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('count_3_at')->nullable();

            // Reconciliation
            $table->decimal('accepted_count', 10, 2)->nullable();
            $table->string('resolution_type', 20)->nullable(); // auto, manual, uncounted
            $table->decimal('divergence', 10, 2)->default(0);
            $table->decimal('divergence_value', 12, 2)->default(0);
            $table->decimal('divergence_value_cost', 12, 2)->default(0);

            // Phase B - Auditor justification
            $table->boolean('is_justified')->default(false);
            $table->text('justification_note')->nullable();
            $table->foreignId('justified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('justified_at')->nullable();

            // Phase C - Store justification resolved
            $table->boolean('store_justified')->default(false);
            $table->decimal('store_justified_quantity', 10, 2)->nullable();

            $table->text('observation')->nullable();
            $table->timestamps();

            $table->index(['audit_id', 'area_id']);
            $table->index('product_barcode');
            $table->unique(['audit_id', 'product_variant_id'], 'audit_variant_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_audit_items');
    }
};
