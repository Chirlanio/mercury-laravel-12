<?php

use Database\Seeders\DreManagementLineSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * DRE — Prompt #2.
 *
 * Invoca o `DreManagementLineSeeder` como parte do `migrate`.
 *
 * Motivação: o projeto aplica seeders de dados de referência via
 * migrations (padrão estabelecido em `reseed_real_accounting_classes`,
 * `seed_management_classes_from_real_data`, etc.). Isso garante que
 * `RefreshDatabase` em testes populate tudo sem precisar de `--seed`.
 *
 * O próprio `TenantDatabaseSeeder` também registra o seeder — o método
 * `run()` é idempotente (retorna se `L01` já existe).
 */
return new class extends Migration
{
    public function up(): void
    {
        (new DreManagementLineSeeder())->run();
    }

    public function down(): void
    {
        // Não desfaz a semeadura — a migration anterior
        // (`200003_clear_prompt1...`) já lida com limpeza quando
        // necessário. Down vazio deliberado.
    }
};
