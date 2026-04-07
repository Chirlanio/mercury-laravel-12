<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('confirmed_sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('store_id');
            $table->decimal('sale_value', 12, 2);
            $table->tinyInteger('reference_month');
            $table->smallInteger('reference_year');
            $table->unsignedBigInteger('confirmed_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
            $table->foreign('confirmed_by_user_id')->references('id')->on('users')->onDelete('set null');
            $table->unique(['employee_id', 'store_id', 'reference_month', 'reference_year'], 'confirmed_sales_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('confirmed_sales');
    }
};
