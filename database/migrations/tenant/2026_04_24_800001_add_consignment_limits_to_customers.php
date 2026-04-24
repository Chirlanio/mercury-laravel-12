<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M20 — Limite configurável por cliente para consignações simultâneas.
 *
 * Dois tetos opcionais (nullable = sem limite) que bloqueiam a criação
 * de nova consignação quando somado ao que o cliente já tem em aberto:
 *  - consignment_max_items: teto de peças (outbound_items_count)
 *  - consignment_max_value: teto de valor (outbound_total_value)
 *
 * Validados em ConsignmentService::ensureRecipientEligibility (só quando
 * customer_id é conhecido — CPF isolado sem vínculo não tem limite).
 * Override via OVERRIDE_CONSIGNMENT_LOCK + justificativa (reusa o mesmo
 * fluxo da regra M9).
 *
 * Esses campos NÃO são tocados pela sincronização CIGAM (CustomerSyncService
 * só atualiza campos conhecidos do ERP).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->unsignedInteger('consignment_max_items')->nullable()->after('is_active');
            $table->decimal('consignment_max_value', 12, 2)->nullable()->after('consignment_max_items');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn(['consignment_max_items', 'consignment_max_value']);
        });
    }
};
