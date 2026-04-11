<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_content_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('content_id')->constrained('training_contents')->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('training_courses')->nullOnDelete();
            $table->string('status', 20)->default('not_started'); // not_started, in_progress, completed
            $table->decimal('progress_percent', 5, 2)->default(0);
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->unsignedInteger('last_position_seconds')->default(0);
            $table->unsignedInteger('total_time_spent_seconds')->default(0);
            $table->unsignedInteger('views_count')->default(0);
            $table->dateTime('last_accessed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'content_id', 'course_id'], 'user_content_course_unique');
            $table->index('user_id');
            $table->index('content_id');
            $table->index('course_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_content_progress');
    }
};
