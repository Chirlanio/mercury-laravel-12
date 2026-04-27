<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Match CIGAM em 2 pontas:
 *  - cigam_dispatched_at: origem registrou saída (movement_code=5+S+invoice_number)
 *  - cigam_received_at:   destino registrou entrada (movement_code=5+E+invoice_number)
 *
 * Renomeia `cigam_matched_at` → `cigam_received_at` (semântica clara).
 * Renomeia items.matched_quantity → received_quantity + adiciona dispatched_quantity.
 *
 * Métricas habilitadas:
 *  - Aderência da origem = SUM(dispatched_quantity) / SUM(qty_requested) por loja
 *  - Tempo de trânsito CIGAM = cigam_received_at - cigam_dispatched_at
 *  - % despachados mas não recebidos: WHERE cigam_dispatched_at NOT NULL AND cigam_received_at IS NULL
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('relocations', function (Blueprint $table) {
            $table->renameColumn('cigam_matched_at', 'cigam_received_at');
        });

        Schema::table('relocations', function (Blueprint $table) {
            $table->timestamp('cigam_dispatched_at')->nullable()->after('cigam_received_at');
            $table->index('cigam_dispatched_at');
        });

        Schema::table('relocation_items', function (Blueprint $table) {
            $table->renameColumn('matched_quantity', 'received_quantity');
        });

        Schema::table('relocation_items', function (Blueprint $table) {
            $table->unsignedInteger('dispatched_quantity')->default(0)->after('received_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('relocation_items', function (Blueprint $table) {
            $table->dropColumn('dispatched_quantity');
        });
        Schema::table('relocation_items', function (Blueprint $table) {
            $table->renameColumn('received_quantity', 'matched_quantity');
        });

        Schema::table('relocations', function (Blueprint $table) {
            $table->dropIndex(['cigam_dispatched_at']);
            $table->dropColumn('cigam_dispatched_at');
        });
        Schema::table('relocations', function (Blueprint $table) {
            $table->renameColumn('cigam_received_at', 'cigam_matched_at');
        });
    }
};
