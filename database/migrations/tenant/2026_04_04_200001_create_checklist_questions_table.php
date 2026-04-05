<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_area_id')->constrained('checklist_areas')->cascadeOnDelete();
            $table->text('description');
            $table->integer('points')->default(1);
            $table->integer('weight')->default(1);
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('checklist_area_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_questions');
    }
};
