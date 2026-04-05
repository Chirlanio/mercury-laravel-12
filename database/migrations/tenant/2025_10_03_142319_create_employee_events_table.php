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
        Schema::create('employee_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('event_type_id')->constrained('employee_event_types')->onDelete('cascade');
            $table->date('start_date')->nullable(); // Data de início ou data única
            $table->date('end_date')->nullable(); // Data de fim (para férias e licenças)
            $table->string('document_path')->nullable(); // Caminho do documento anexado
            $table->text('notes')->nullable(); // Observações adicionais
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null'); // Usuário que registrou o evento
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_events');
    }
};
