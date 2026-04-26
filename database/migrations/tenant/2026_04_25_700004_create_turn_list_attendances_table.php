<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Atendimentos da Lista da Vez.
 *
 * `original_queue_position` (nullable) — capturada no `start()` antes da
 * consultora sair da fila. É usada quando o outcome de finalização tiver
 * `restore_queue_position=1` (ex: "Troca convertida/Retorna vez", "Preferência
 * /Retorna vez") — nesses casos a consultora volta na posição original
 * AJUSTADA pelo algoritmo no Service:
 *
 *   adjustedPosition = max(1, original_queue_position - aheadCount)
 *
 *   onde aheadCount = quantas consultoras ESTAVAM À FRENTE na fila E também
 *   saíram pra atender DEPOIS desta (e ainda estão atendendo). Evita "buracos"
 *   na fila quando várias consultoras saem juntas.
 *
 * Sem soft delete — atendimentos finalizados são imutáveis (auditoria).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turn_list_attendances', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique()->comment('Identificador público (substitui hash_id v1)');

            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->string('store_code', 10);

            // Posição original na fila (capturada no start) — usada no
            // restore_queue_position se outcome tiver essa flag.
            $table->unsignedInteger('original_queue_position')->nullable();

            $table->string('status', 20)->default('active')->comment('TurnListAttendanceStatus enum');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();

            // Outcome escolhido ao finalizar (FK em turn_list_attendance_outcomes).
            // Nullable porque atendimentos em andamento ainda não têm outcome.
            $table->foreignId('outcome_id')->nullable()->constrained('turn_list_attendance_outcomes')->nullOnDelete();

            // Volta para fila ao finalizar? Default true — fluxo padrão é
            // continuar disponível pra próximo cliente.
            $table->boolean('return_to_queue')->default(true);

            $table->text('notes')->nullable();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexes — board snapshot é a query mais frequente
            $table->index(['store_code', 'status'], 'idx_att_store_status');
            $table->index(['employee_id', 'status'], 'idx_att_employee_status');
            $table->index('started_at', 'idx_att_started_at');
            $table->index(['store_code', 'started_at'], 'idx_att_store_date');
            $table->index('outcome_id', 'idx_att_outcome');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turn_list_attendances');
    }
};
