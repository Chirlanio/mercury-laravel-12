<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('date_sales');
            $table->decimal('total_sales', 10, 2);
            $table->integer('qtde_total')->default(0);
            $table->string('user_hash', 32)->nullable();
            $table->enum('source', ['manual', 'cigam'])->default('manual');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['store_id', 'date_sales']);
            $table->index(['employee_id', 'date_sales']);
            $table->index('date_sales');
            $table->unique(['store_id', 'employee_id', 'date_sales']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
