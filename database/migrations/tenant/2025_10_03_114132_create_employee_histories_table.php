<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employee_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->string('event_type'); // 'promotion', 'position_change', 'transfer', 'salary_change', 'status_change', 'other'
            $table->string('title'); // Título do evento
            $table->text('description')->nullable(); // Descrição detalhada
            $table->string('old_value')->nullable(); // Valor anterior
            $table->string('new_value')->nullable(); // Novo valor
            $table->date('event_date'); // Data do evento
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null'); // Quem registrou
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_histories');
    }
};
