<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained('trainings')->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained('training_participants')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating'); // 1 to 5
            $table->text('comment')->nullable();
            $table->timestamps();

            // Indexes
            $table->unique(['training_id', 'participant_id']);
            $table->index('training_id');
            $table->index('rating');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_evaluations');
    }
};
