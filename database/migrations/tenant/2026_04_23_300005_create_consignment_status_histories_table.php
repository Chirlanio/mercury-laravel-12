<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail de transições de status de consignações. Uma linha por
 * transição, gravada pelo ConsignmentTransitionService. Usado pela
 * timeline do modal de detalhes (StandardModal.Timeline) e por
 * relatórios de tempo médio em cada estado.
 *
 * `from_status` é null apenas na criação inicial (draft).
 *
 * Override permissionado (OVERRIDE_CONSIGNMENT_LOCK) grava no campo
 * `note` a justificativa obrigatória.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consignment_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignment_id')->constrained('consignments')->cascadeOnDelete();

            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);

            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();

            // Contexto extra JSON — flexível para anotar overrides,
            // reconciliação CIGAM, cancelamentos em lote, etc.
            $table->json('context')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['consignment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignment_status_histories');
    }
};
