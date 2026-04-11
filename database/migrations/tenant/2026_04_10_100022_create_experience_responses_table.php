<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experience_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained('experience_evaluations')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('experience_questions');
            $table->string('form_type', 10); // employee, manager
            $table->text('response_text')->nullable();
            $table->unsignedTinyInteger('rating_value')->nullable();
            $table->boolean('yes_no_value')->nullable();
            $table->timestamps();

            $table->unique(['evaluation_id', 'question_id', 'form_type'], 'eval_question_form_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experience_responses');
    }
};
