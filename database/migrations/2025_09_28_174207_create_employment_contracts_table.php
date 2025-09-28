<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employment_contracts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('position_id');
            $table->unsignedBigInteger('movement_type_id');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('store_id', 4);
            $table->timestamps();

            // Foreign key constraints (if tables exist)
            // $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            // $table->foreign('position_id')->references('id')->on('positions')->onDelete('cascade');
            // $table->foreign('movement_type_id')->references('id')->on('movement_types')->onDelete('cascade');

            // Indexes
            $table->index('employee_id');
            $table->index('position_id');
            $table->index('movement_type_id');
            $table->index('store_id');
            $table->index('start_date');
            $table->index('end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employment_contracts');
    }
};
