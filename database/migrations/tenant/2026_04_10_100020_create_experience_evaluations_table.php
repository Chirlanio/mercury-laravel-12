<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experience_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('manager_id')->constrained('users');
            $table->string('store_id', 10);
            $table->string('milestone', 5); // 45 or 90
            $table->date('date_admission');
            $table->date('milestone_date');
            $table->string('manager_status', 20)->default('pending'); // pending, completed
            $table->string('employee_status', 20)->default('pending'); // pending, completed
            $table->dateTime('manager_completed_at')->nullable();
            $table->dateTime('employee_completed_at')->nullable();
            $table->string('employee_token', 64)->unique();
            $table->string('recommendation', 5)->nullable(); // yes, no (only for 90 days)
            $table->timestamps();

            $table->unique(['employee_id', 'milestone']);
            $table->index('milestone_date');
            $table->index('manager_id');
            $table->index('store_id');
            $table->index(['manager_status', 'employee_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experience_evaluations');
    }
};
