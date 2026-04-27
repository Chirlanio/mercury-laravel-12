<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Coluna dedicada pra idempotência do hook Helpdesk de divergência de
 * despacho — separada de `helpdesk_ticket_id` (que já é usada pelo hook
 * de rejeição/cancelamento). Mesmo remanejo pode acabar gerando dois
 * tickets (um pra divergência no despacho, outro se for cancelado depois).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('relocations', function (Blueprint $table) {
            $table->unsignedBigInteger('dispatch_helpdesk_ticket_id')->nullable()->after('helpdesk_ticket_id');
        });
    }

    public function down(): void
    {
        Schema::table('relocations', function (Blueprint $table) {
            $table->dropColumn('dispatch_helpdesk_ticket_id');
        });
    }
};
