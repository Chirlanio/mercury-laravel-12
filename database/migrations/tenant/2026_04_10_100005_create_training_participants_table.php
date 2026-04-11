<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained('trainings')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('participant_name', 255);
            $table->string('participant_email', 255)->nullable();
            $table->dateTime('attendance_time')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->boolean('is_late')->default(false);
            $table->boolean('certificate_generated')->default(false);
            $table->string('certificate_path', 255)->nullable();
            $table->dateTime('certificate_sent_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->unique(['training_id', 'employee_id']);
            $table->index('employee_id');
            $table->index('training_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_participants');
    }
};
