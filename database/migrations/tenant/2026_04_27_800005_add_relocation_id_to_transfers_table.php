<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona FK `relocation_id` em `transfers` para vincular o Transfer físico
 * gerado automaticamente quando um remanejo transita de in_separation para
 * in_transit (loja origem informa NF).
 *
 * Nullable porque a maioria dos transfers atuais (manuais, return, exchange,
 * damage_match) não nasce de um remanejo. Soft delete não cascata: se o
 * remanejo for excluído, o Transfer permanece (registro físico autônomo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->foreignId('relocation_id')
                ->nullable()
                ->after('id')
                ->constrained('relocations')
                ->nullOnDelete();

            $table->index('relocation_id', 'idx_transfers_relocation_id');
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropForeign(['relocation_id']);
            $table->dropIndex('idx_transfers_relocation_id');
            $table->dropColumn('relocation_id');
        });
    }
};
