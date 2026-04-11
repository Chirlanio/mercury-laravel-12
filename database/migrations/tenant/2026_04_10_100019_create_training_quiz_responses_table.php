<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_quiz_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attempt_id')->constrained('training_quiz_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('training_quiz_questions')->cascadeOnDelete();
            $table->json('selected_options'); // array of option IDs
            $table->boolean('is_correct')->default(false);
            $table->unsignedSmallInteger('points_earned')->default(0);

            $table->index('attempt_id');
            $table->index('question_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_quiz_responses');
    }
};
