<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona 'damage_match' ao enum transfer_type da tabela transfers.
 *
 * Usado pelo módulo DamagedProducts: ao aceitar um match (par trocado ou
 * avaria complementar) entre duas lojas, uma transferência é gerada
 * automaticamente com transfer_type='damage_match' pra distinguir do fluxo
 * normal de transferência operada manualmente.
 *
 * Em SQLite (testes em memória) o ALTER de enum é no-op — SQLite trata enum
 * como TEXT sem constraint. Em MySQL/MariaDB usa MODIFY COLUMN.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement(
            "ALTER TABLE transfers MODIFY COLUMN transfer_type ".
            "ENUM('transfer','relocation','return','exchange','damage_match') ".
            "NOT NULL DEFAULT 'transfer'"
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Reverter: limpa registros damage_match antes de derrubar o valor do enum
        DB::table('transfers')->where('transfer_type', 'damage_match')->update(['transfer_type' => 'transfer']);

        DB::statement(
            "ALTER TABLE transfers MODIFY COLUMN transfer_type ".
            "ENUM('transfer','relocation','return','exchange') ".
            "NOT NULL DEFAULT 'transfer'"
        );
    }
};
