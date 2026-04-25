<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona FK opcional para o ticket Helpdesk aberto automaticamente
 * quando a verba é rejeitada (Fase 6 — integração Helpdesk).
 *
 * Sem foreign constraint formal porque o módulo Helpdesk pode ou não
 * estar instalado em cada tenant — fail-safe via Schema::hasTable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('helpdesk_ticket_id')->nullable()->after('accountability_rejection_reason');
            $table->index('helpdesk_ticket_id', 'idx_te_helpdesk_ticket');
        });
    }

    public function down(): void
    {
        Schema::table('travel_expenses', function (Blueprint $table) {
            $table->dropIndex('idx_te_helpdesk_ticket');
            $table->dropColumn('helpdesk_ticket_id');
        });
    }
};
