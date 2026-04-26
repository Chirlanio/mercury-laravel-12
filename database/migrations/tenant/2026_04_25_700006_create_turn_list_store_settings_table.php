<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuração da Lista da Vez por loja.
 *
 * `return_to_position` — quando uma consultora termina pausa, volta na
 * posição ORIGINAL da fila (true) ou no FIM (false). Default true.
 *
 * Atendimentos NÃO usam essa flag — comportamento de "voltar na vez" em
 * atendimentos vem do outcome (`restore_queue_position` em
 * turn_list_attendance_outcomes).
 *
 * Tabela esparsa: lojas sem registro usam o default (true). Apenas lojas
 * que customizam aparecem aqui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turn_list_store_settings', function (Blueprint $table) {
            $table->id();
            $table->string('store_code', 10)->unique();
            $table->boolean('return_to_position')->default(true)->comment('Volta posição original ao retornar de pausa');
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turn_list_store_settings');
    }
};
