<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * DRE — Prompt #2.
 *
 * Remove as 17 linhas plantadas pela migration seed do prompt #1
 * (16 DRE-BR derivadas do enum DreGroup + L99_UNCLASSIFIED).
 *
 * Estas linhas foram um placeholder temporário — a estrutura executiva
 * real da DRE do Grupo Meia Sola (20 linhas com EBITDA, Margem de
 * Contribuição, Custo de Ocupação etc.) vem do `DreManagementLineSeeder`
 * deste prompt.
 *
 * A linha-fantasma `L99_UNCLASSIFIED` foi postergada para o prompt #5
 * (tela de Pendências de mapping) — ela só ganha utilidade visual
 * quando há `dre_actuals` sem `dre_mapping` resolvível, o que ainda não
 * ocorre nesta fase.
 *
 * Idempotência: se a tabela já está vazia (migração rodada antes), no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Usamos DELETE em vez de TRUNCATE porque SQLite usado nos tests
        // trata TRUNCATE como DELETE e não aceita a sintaxe literal.
        DB::table('dre_management_lines')->delete();
    }

    public function down(): void
    {
        // Não restauramos as 17 linhas antigas — a estrutura canônica
        // agora é a do DreManagementLineSeeder. Down vazio deliberado.
    }
};
