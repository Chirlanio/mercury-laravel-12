<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_id')->constrained('checklists')->cascadeOnDelete();
            $table->foreignId('checklist_question_id')->constrained('checklist_questions')->cascadeOnDelete();
            $table->enum('answer_status', ['pending', 'compliant', 'partial', 'non_compliant'])->default('pending');
            $table->decimal('score', 5, 2)->default(0);
            $table->text('justification')->nullable();
            $table->text('action_plan')->nullable();
            $table->foreignId('responsible_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('deadline_date')->nullable();
            $table->timestamps();

            $table->index('checklist_id');
            $table->unique(['checklist_id', 'checklist_question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_answers');
    }
};
