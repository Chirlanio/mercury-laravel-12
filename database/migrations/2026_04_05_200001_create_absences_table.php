<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('absence_date');
            $table->enum('type', ['unjustified', 'justified', 'late', 'early_leave'])->default('unjustified');
            $table->boolean('is_justified')->default(false);
            $table->unsignedBigInteger('medical_certificate_id')->nullable();
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
            $table->foreign('medical_certificate_id')->references('id')->on('medical_certificates')->onDelete('set null');
            $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');
            $table->unique(['employee_id', 'absence_date']);
            $table->index(['employee_id', 'is_archived']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absences');
    }
};
