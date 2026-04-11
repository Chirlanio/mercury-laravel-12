<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_course_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('training_courses')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('status', 20)->default('enrolled'); // enrolled, in_progress, completed, dropped
            $table->dateTime('enrolled_at')->useCurrent();
            $table->dateTime('completed_at')->nullable();
            $table->decimal('completion_percent', 5, 2)->default(0);
            $table->boolean('certificate_generated')->default(false);
            $table->string('certificate_path', 255)->nullable();
            $table->timestamps();

            $table->unique(['course_id', 'user_id']);
            $table->index('user_id');
            $table->index('status');
            $table->index(['course_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_course_enrollments');
    }
};
