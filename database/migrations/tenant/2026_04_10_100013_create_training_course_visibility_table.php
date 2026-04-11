<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_course_visibility', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('training_courses')->cascadeOnDelete();
            $table->string('target_type', 20); // store, role, user
            $table->string('target_id', 20);
            $table->timestamps();

            $table->unique(['course_id', 'target_type', 'target_id'], 'course_visibility_unique');
            $table->index('course_id');
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_course_visibility');
    }
};
