<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona colunas pra rastrear validação de NF no despacho — quando o
 * usuário confirma in_separation → in_transit, o sistema valida a NF
 * contra movements (CIGAM). Se houver divergências (faltando/sobrando/
 * qty diferente), o usuário pode confirmar mesmo assim e o sistema
 * persiste o snapshot pro time de planejamento/logística investigar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('relocations', function (Blueprint $table) {
            $table->boolean('dispatch_has_discrepancies')->default(false)->after('cigam_received_at');
            $table->json('dispatch_discrepancies_json')->nullable()->after('dispatch_has_discrepancies');
            $table->timestamp('dispatch_validated_at')->nullable()->after('dispatch_discrepancies_json');
        });
    }

    public function down(): void
    {
        Schema::table('relocations', function (Blueprint $table) {
            $table->dropColumn(['dispatch_has_discrepancies', 'dispatch_discrepancies_json', 'dispatch_validated_at']);
        });
    }
};
