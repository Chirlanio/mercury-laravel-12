<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_quiz_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('training_quiz_questions')->cascadeOnDelete();
            $table->string('option_text', 500);
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            $table->index('question_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_quiz_options');
    }
};
