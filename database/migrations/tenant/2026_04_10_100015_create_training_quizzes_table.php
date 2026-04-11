<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->nullable()->constrained('training_contents')->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('training_courses')->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('passing_score')->default(70); // %
            $table->unsignedSmallInteger('max_attempts')->nullable(); // null = unlimited
            $table->boolean('show_answers')->default(false);
            $table->unsignedSmallInteger('time_limit_minutes')->nullable(); // null = no limit
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
            $table->foreignId('deleted_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->index('content_id');
            $table->index('course_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_quizzes');
    }
};
