<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained('training_quizzes')->cascadeOnDelete();
            $table->text('question_text');
            $table->string('question_type', 20)->default('single'); // single, multiple, boolean
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedSmallInteger('points')->default(1);
            $table->text('explanation')->nullable();

            $table->index('quiz_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_quiz_questions');
    }
};
