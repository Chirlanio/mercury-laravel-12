<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropUnique(['store_id', 'employee_id', 'date_sales']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->unsignedBigInteger('employee_id')->nullable()->change();
            $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
            $table->unique(['store_id', 'employee_id', 'date_sales']);
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropUnique(['store_id', 'employee_id', 'date_sales']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->unsignedBigInteger('employee_id')->nullable(false)->change();
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->unique(['store_id', 'employee_id', 'date_sales']);
        });
    }
};
