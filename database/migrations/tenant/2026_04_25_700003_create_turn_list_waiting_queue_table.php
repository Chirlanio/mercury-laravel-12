<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fila ativa de consultoras aguardando atendimento.
 *
 * Cada loja tem sua própria fila (segregada por store_code). O campo
 * `position` é 1..N dentro de cada loja — não é global.
 *
 * Unique key (employee_id, store_code) garante que uma consultora não
 * pode estar em duas filas simultaneamente. Validações cruzadas
 * (não pode entrar se está atendendo/em pausa) ficam no Service.
 *
 * Padrão v1 (`ldv_waiting_queue`) preservado: registros antigos (>12h)
 * são limpos pelo command `turn-list:cleanup` daily 23:00 + cron.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turn_list_waiting_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('store_code', 10);
            $table->unsignedInteger('position')->comment('Posição na fila (1=primeira)');
            $table->timestamp('entered_at')->useCurrent();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Unique: 1 consultora por loja na fila
            $table->unique(['employee_id', 'store_code'], 'uk_queue_employee_store');

            // Indexes pra ordenação rápida e cleanup
            $table->index(['store_code', 'position'], 'idx_queue_store_position');
            $table->index('entered_at', 'idx_queue_entered_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turn_list_waiting_queue');
    }
};
