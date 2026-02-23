<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('description');
            $table->string('brand_cigam_code')->nullable();
            $table->string('collection_cigam_code')->nullable();
            $table->string('subcollection_cigam_code')->nullable();
            $table->string('category_cigam_code')->nullable();
            $table->string('color_cigam_code')->nullable();
            $table->string('material_cigam_code')->nullable();
            $table->string('article_complement_cigam_code')->nullable();
            $table->string('supplier_codigo_for')->nullable();
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('sync_locked')->default(false);
            $table->timestamp('synced_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('brand_cigam_code');
            $table->index('collection_cigam_code');
            $table->index('category_cigam_code');
            $table->index('supplier_codigo_for');
            $table->index('is_active');
            $table->index('sync_locked');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
