<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dismissal_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_movement_id')->constrained('personnel_movements')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->boolean('uniform')->default(false);
            $table->boolean('phone_chip')->default(false);
            $table->boolean('original_card')->default(false);
            $table->boolean('aso')->default(false);
            $table->boolean('aso_resigns')->default(false);
            $table->boolean('send_aso_guide')->default(false);
            $table->date('signature_date_trct')->nullable();
            $table->date('termination_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dismissal_follow_ups');
    }
};
