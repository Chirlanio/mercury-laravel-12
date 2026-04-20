<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recria a tabela budget_status_histories caso tenha sido apagada
 * manualmente. Idempotente — usa hasTable guard.
 *
 * Estrutura idêntica à da migration 700001_create_budget_tables que
 * era a fonte original. Quando criada corretamente por aquela migration,
 * este up() não faz nada (hasTable retorna true).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('budget_status_histories')) {
            return;
        }

        Schema::create('budget_status_histories', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('budget_upload_id');
            $table->foreign('budget_upload_id')
                ->references('id')
                ->on('budget_uploads')
                ->cascadeOnDelete();

            $table->string('event', 50);                    // 'created', 'activated', 'deactivated', 'deleted'
            $table->boolean('from_active')->nullable();
            $table->boolean('to_active')->nullable();
            $table->text('note')->nullable();

            $table->unsignedBigInteger('changed_by_user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('budget_upload_id');
            $table->index('event');
        });
    }

    public function down(): void
    {
        // Não derruba a tabela — a estrutura "original" pertence a
        // 700001_create_budget_tables. Rollback aqui é no-op para evitar
        // quebrar rollbacks da migration original.
    }
};
