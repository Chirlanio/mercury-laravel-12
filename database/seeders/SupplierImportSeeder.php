<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupplierImportSeeder extends Seeder
{
    /**
     * Import suppliers from legacy adms_suppliers backup.
     * Maps columns: corporate_social→razao_social, fantasy_name→nome_fantasia,
     * cnpj_cpf→cnpj, status_id→is_active, created→created_at, modified→updated_at
     */
    public function run(): void
    {
        $sqlFile = database_path('../docs/adms_suppliers_backup.sql');

        if (!file_exists($sqlFile)) {
            $this->command->error('SQL backup file not found: docs/adms_suppliers_backup.sql');
            return;
        }

        $sql = file_get_contents($sqlFile);

        // Create the legacy table temporarily
        DB::statement('DROP TABLE IF EXISTS `adms_suppliers`');

        // Execute the full SQL (CREATE TABLE + INSERTs)
        // Split by semicolons but handle values with semicolons inside strings
        $statements = $this->splitSqlStatements($sql);

        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                DB::unprepared($statement);
            }
        }

        $legacyCount = DB::table('adms_suppliers')->count();
        $this->command->info("Loaded {$legacyCount} records from legacy backup.");

        // Clear existing suppliers
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('suppliers')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // Insert with column mapping
        DB::statement("
            INSERT INTO `suppliers` (`id`, `codigo_for`, `cnpj`, `razao_social`, `nome_fantasia`, `contact`, `email`, `is_active`, `created_at`, `updated_at`)
            SELECT
                `id`,
                LPAD(`id`, 6, '0') AS `codigo_for`,
                CASE
                    WHEN `cnpj_cpf` IS NOT NULL AND `cnpj_cpf` != ''
                    THEN REPLACE(REPLACE(REPLACE(REPLACE(`cnpj_cpf`, '.', ''), '-', ''), '/', ''), ' ', '')
                    ELSE NULL
                END AS `cnpj`,
                `corporate_social` AS `razao_social`,
                `fantasy_name` AS `nome_fantasia`,
                CASE
                    WHEN `contact` IS NOT NULL AND `contact` != ''
                    THEN REPLACE(REPLACE(REPLACE(REPLACE(`contact`, '(', ''), ')', ''), '-', ''), ' ', '')
                    ELSE NULL
                END AS `contact`,
                NULLIF(`email`, '') AS `email`,
                CASE WHEN `status_id` = 1 THEN 1 ELSE 0 END AS `is_active`,
                `created` AS `created_at`,
                COALESCE(`modified`, `created`) AS `updated_at`
            FROM `adms_suppliers`
            ORDER BY `id`
        ");

        $importedCount = DB::table('suppliers')->count();
        $this->command->info("Imported {$importedCount} suppliers into Laravel table.");

        // Drop the temporary legacy table
        DB::statement('DROP TABLE IF EXISTS `adms_suppliers`');
        $this->command->info('Legacy table dropped. Import complete.');
    }

    /**
     * Split SQL into individual statements, respecting quoted strings.
     */
    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $current = '';
        $inString = false;
        $stringChar = '';
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($inString) {
                $current .= $char;
                // Check for escaped quote
                if ($char === '\\') {
                    // Next char is escaped, add it and skip
                    if ($i + 1 < $length) {
                        $current .= $sql[++$i];
                    }
                } elseif ($char === $stringChar) {
                    // Check for doubled quote (e.g., '' in SQL)
                    if ($i + 1 < $length && $sql[$i + 1] === $stringChar) {
                        $current .= $sql[++$i];
                    } else {
                        $inString = false;
                    }
                }
            } else {
                if ($char === '\'' || $char === '"') {
                    $inString = true;
                    $stringChar = $char;
                    $current .= $char;
                } elseif ($char === ';') {
                    $trimmed = trim($current);
                    if (!empty($trimmed) && !str_starts_with($trimmed, '--')) {
                        $statements[] = $trimmed;
                    }
                    $current = '';
                } elseif ($char === '-' && $i + 1 < $length && $sql[$i + 1] === '-') {
                    // Skip single-line comment
                    $eol = strpos($sql, "\n", $i);
                    if ($eol === false) break;
                    $i = $eol;
                } else {
                    $current .= $char;
                }
            }
        }

        // Handle last statement without trailing semicolon
        $trimmed = trim($current);
        if (!empty($trimmed) && !str_starts_with($trimmed, '--')) {
            $statements[] = $trimmed;
        }

        return $statements;
    }
}
