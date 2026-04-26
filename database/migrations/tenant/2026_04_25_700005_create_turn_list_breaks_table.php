<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pausas das consultoras (Intervalo, Almoço).
 *
 * Diferenças vs `turn_list_attendances`:
 *  - `original_queue_position` é NOT NULL (consultora sempre sai da fila
 *    para entrar em pausa — se não estava na fila, ela vai pro Disponível
 *    sem registrar pausa).
 *  - Volta para a posição original é controlado por `turn_list_store_settings.
 *    return_to_position` (toggle por loja), não pelo break_type.
 *  - Compara `elapsed_seconds` com `break_type.max_duration_minutes` em
 *    runtime — se exceder, o painel destaca em vermelho (alerta).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turn_list_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('store_code', 10);
            $table->foreignId('break_type_id')->constrained('turn_list_break_types')->restrictOnDelete();

            // Posição original na fila — sempre obrigatória, pq pausas só
            // acontecem a partir da fila.
            $table->unsignedInteger('original_queue_position');

            $table->string('status', 20)->default('active')->comment('TurnListAttendanceStatus enum');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['store_code', 'status'], 'idx_brk_store_status');
            $table->index(['employee_id', 'status'], 'idx_brk_employee_status');
            $table->index('started_at', 'idx_brk_started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turn_list_breaks');
    }
};
