<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DRE — Prompt #2.
 *
 * Adiciona a coluna `type` em `chart_of_accounts` usando o enum PHP
 * `App\Enums\AccountType` (valores `synthetic`/`analytical`).
 *
 * Contexto: o ERP (CIGAM/TAYLOR/ZZNET) rotula cada conta com "S" ou "A".
 * O prompt #1 já havia contemplado essa distinção via flag boolean
 * `accepts_entries`, mas o prompt #2 pede a coluna explícita `type`
 * para alinhar com o vocabulário dos importadores e das APIs.
 *
 * Estratégia:
 *   - Adiciona `type` varchar(20) com valor default temporário.
 *   - Faz backfill a partir de `accepts_entries`:
 *       accepts_entries = true  → type = 'analytical'
 *       accepts_entries = false → type = 'synthetic'
 *   - Mantém `accepts_entries` intacto (retro-compat com ~30 arquivos
 *     que dependem dele). Prompts futuros podem eventualmente removê-lo
 *     quando tudo migrar para `type`.
 *
 * Observação sobre SQLite (tests): o driver não suporta `->comment()`
 * portavelmente, mas o método é aceito e ignorado — por isso usamos
 * comentários mesmo em migrations testadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->string('type', 20)
                ->nullable()
                ->after('name')
                ->comment('S=sintética/totalizadora, A=analítica/recebe lançamento (enum AccountType)');
        });

        // Backfill -----------------------------------------------------

        DB::table('chart_of_accounts')
            ->where('accepts_entries', true)
            ->update(['type' => 'analytical']);

        DB::table('chart_of_accounts')
            ->where('accepts_entries', false)
            ->update(['type' => 'synthetic']);

        // Índice por tipo — útil para `scopeAnalytical` e estatísticas.
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->index('type', 'chart_of_accounts_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropIndex('chart_of_accounts_type_idx');
            $table->dropColumn('type');
        });
    }
};
