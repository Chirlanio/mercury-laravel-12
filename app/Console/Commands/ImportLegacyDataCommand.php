<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\Sale;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLegacyDataCommand extends Command
{
    protected $signature = 'legacy:import
        {--table= : Table to import (employees, contracts, sales, all)}
        {--sql-file= : Path to the SQL backup file}
        {--dry-run : Show what would be imported without executing}';

    protected $description = 'Import data from the legacy Mercury SQL backup';

    protected string $sqlFile;

    public function handle(): int
    {
        $this->sqlFile = $this->option('sql-file')
            ?? 'C:\\wamp64\\www\\mercury\\u401878354_meiaso26_bd_me.sql';

        if (!file_exists($this->sqlFile)) {
            $this->error("SQL file not found: {$this->sqlFile}");
            return 1;
        }

        $table = $this->option('table') ?? $this->choice(
            'Which table to import?',
            ['employees', 'contracts', 'sales', 'all'],
            'all'
        );

        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN - No data will be modified.');
        }

        $tables = $table === 'all' ? ['employees', 'contracts', 'sales'] : [$table];

        foreach ($tables as $t) {
            match ($t) {
                'employees' => $this->importEmployees($dryRun),
                'contracts' => $this->importContracts($dryRun),
                'sales' => $this->importSales($dryRun),
                default => $this->error("Unknown table: $t"),
            };
        }

        return 0;
    }

    protected function importEmployees(bool $dryRun): void
    {
        $this->info('Importing employees...');

        $records = $this->parseInserts('adms_employees', [
            'id', 'name_employee', 'short_name', 'user_image', 'doc_cpf', 'email',
            'telephone', 'date_admission', 'date_dismissal', 'position_id', 'cupom_site',
            'adms_store_id', 'adms_level_education_id', 'adms_sex_id', 'date_birth',
            'adms_area_id', 'pcd', 'apprentice', 'nivel', 'adms_status_employee_id',
            'created_at', 'modified_at',
        ]);

        $this->info("Found {$records->count()} employee records in backup.");

        $existingCpfs = Employee::pluck('id', 'cpf')->toArray();
        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;

        $levelMap = [
            'Júnior' => 'Junior',
            'Junior' => 'Junior',
            'Pleno' => 'Pleno',
            'Sênior' => 'Senior',
            'Senior' => 'Senior',
        ];

        $bar = $this->output->createProgressBar($records->count());

        foreach ($records as $record) {
            $bar->advance();

            $cpf = $record['doc_cpf'] ?? '';
            if (empty($cpf) || strlen($cpf) !== 11) {
                $skipped++;
                continue;
            }

            $birthDate = $record['date_birth'] ?? '2000-01-01';
            if ($birthDate === '0000-00-00' || empty($birthDate)) {
                $birthDate = '2000-01-01';
            }

            $admissionDate = $record['date_admission'] ?? '2020-01-01';
            if ($admissionDate === '0000-00-00' || empty($admissionDate)) {
                $admissionDate = '2020-01-01';
            }

            $data = [
                'name' => $record['name_employee'] ?? 'SEM NOME',
                'short_name' => $record['short_name'] ?? '',
                'profile_image' => $record['user_image'] ?: null,
                'cpf' => $cpf,
                'admission_date' => $admissionDate,
                'dismissal_date' => ($record['date_dismissal'] && $record['date_dismissal'] !== '0000-00-00')
                    ? $record['date_dismissal'] : null,
                'position_id' => (int) ($record['position_id'] ?? 1),
                'site_coupon' => $record['cupom_site'] ?: null,
                'store_id' => $record['adms_store_id'] ?? 'Z999',
                'education_level_id' => (int) ($record['adms_level_education_id'] ?? 1),
                'gender_id' => (int) ($record['adms_sex_id'] ?? 1),
                'birth_date' => $birthDate,
                'area_id' => (int) ($record['adms_area_id'] ?? 1),
                'is_pcd' => (bool) ($record['pcd'] ?? false),
                'is_apprentice' => (bool) ($record['apprentice'] ?? false),
                'level' => $levelMap[$record['nivel'] ?? 'Júnior'] ?? 'Junior',
                'status_id' => (int) ($record['adms_status_employee_id'] ?? 2),
            ];

            if ($dryRun) {
                if (isset($existingCpfs[$cpf])) {
                    $updated++;
                } else {
                    $inserted++;
                }
                continue;
            }

            try {
                if (isset($existingCpfs[$cpf])) {
                    Employee::where('cpf', $cpf)->update($data);
                    $updated++;
                } else {
                    $emp = Employee::create($data);
                    $existingCpfs[$cpf] = $emp->id;
                    $inserted++;
                }
            } catch (\Exception $e) {
                $errors++;
                if ($errors <= 5) {
                    $this->newLine();
                    $this->warn("Error importing employee CPF {$cpf}: " . $e->getMessage());
                }
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Employees: {$inserted} inserted, {$updated} updated, {$skipped} skipped, {$errors} errors.");
    }

    protected function importContracts(bool $dryRun): void
    {
        $this->info('Importing employment contracts...');

        $records = $this->parseInserts('adms_employment_contracts', [
            'id', 'adms_employee_id', 'adms_position_id', 'adms_type_moviment_id',
            'date_initial', 'date_final', 'adms_store_id', 'created_at', 'modified_at',
        ]);

        $this->info("Found {$records->count()} contract records in backup.");

        $existingEmployeeIds = Employee::pluck('id')->toArray();
        $inserted = 0;
        $skipped = 0;
        $errors = 0;

        if (!$dryRun) {
            // Clear existing contracts to avoid duplicates
            DB::table('employment_contracts')->truncate();
        }

        $bar = $this->output->createProgressBar($records->count());

        foreach ($records as $record) {
            $bar->advance();

            $employeeId = (int) ($record['adms_employee_id'] ?? 0);
            if (!in_array($employeeId, $existingEmployeeIds)) {
                $skipped++;
                continue;
            }

            $startDate = $record['date_initial'] ?? '2020-01-01';
            if ($startDate === '0000-00-00' || empty($startDate)) {
                $startDate = '2020-01-01';
            }

            $endDate = $record['date_final'] ?? null;
            if ($endDate === '0000-00-00' || empty($endDate)) {
                $endDate = null;
            }

            $data = [
                'employee_id' => $employeeId,
                'position_id' => (int) ($record['adms_position_id'] ?? 1),
                'movement_type_id' => (int) ($record['adms_type_moviment_id'] ?? 1),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'store_id' => $record['adms_store_id'] ?? 'Z999',
                'created_at' => $record['created_at'] ?? now(),
                'updated_at' => $record['modified_at'] ?? now(),
            ];

            if ($dryRun) {
                $inserted++;
                continue;
            }

            try {
                DB::table('employment_contracts')->insert($data);
                $inserted++;
            } catch (\Exception $e) {
                $errors++;
                if ($errors <= 5) {
                    $this->newLine();
                    $this->warn("Error importing contract for employee {$employeeId}: " . $e->getMessage());
                }
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Contracts: {$inserted} inserted, {$skipped} skipped (employee not found), {$errors} errors.");
    }

    protected function importSales(bool $dryRun): void
    {
        $this->info('Importing sales (streaming mode)...');

        $storeCodeMap = Store::pluck('id', 'code')->toArray();
        $employeeCpfMap = Employee::pluck('id', 'cpf')->toArray();
        $inserted = 0;
        $skippedCpf = 0;
        $skippedStore = 0;
        $skippedDuplicate = 0;
        $errors = 0;
        $total = 0;

        if (!$dryRun) {
            $existingCount = Sale::count();
            if ($existingCount > 0) {
                if (!$this->confirm("There are {$existingCount} existing sales records. Clear them before import?", true)) {
                    $this->warn('Sales import cancelled.');
                    return;
                }
                Sale::truncate();
            }
        }

        $columns = [
            'id', 'date_sales', 'adms_store_id', 'adms_cpf_employee', 'user_hash',
            'total_sales', 'qtde_total', 'created_at', 'updated_at',
        ];

        $batch = [];
        $seen = [];

        $this->info("Parsing and importing adms_total_sales from SQL file...");

        $this->streamInserts('adms_total_sales', $columns, function ($record) use (
            &$inserted, &$skippedCpf, &$skippedStore, &$skippedDuplicate, &$errors, &$total,
            &$batch, &$seen, $storeCodeMap, $employeeCpfMap, $dryRun
        ) {
            $total++;

            $storeCode = $record['adms_store_id'] ?? '';
            $cpf = $record['adms_cpf_employee'] ?? '';
            $dateSales = $record['date_sales'] ?? '';

            if ($dateSales === '0000-00-00' || empty($dateSales)) {
                $errors++;
                return;
            }

            $storeId = $storeCodeMap[$storeCode] ?? null;
            if (!$storeId) {
                $skippedStore++;
                return;
            }

            $employeeId = $employeeCpfMap[$cpf] ?? null;
            if (!$employeeId) {
                $skippedCpf++;
                return;
            }

            $key = "{$storeId}_{$employeeId}_{$dateSales}";
            if (isset($seen[$key])) {
                $skippedDuplicate++;
                return;
            }
            $seen[$key] = true;

            $row = [
                'store_id' => $storeId,
                'employee_id' => $employeeId,
                'date_sales' => $dateSales,
                'total_sales' => (float) ($record['total_sales'] ?? 0),
                'qtde_total' => (int) ($record['qtde_total'] ?? 0),
                'user_hash' => substr($record['user_hash'] ?? '', 0, 32) ?: null,
                'source' => 'manual',
                'created_at' => $record['created_at'] ?? now(),
                'updated_at' => $record['updated_at'] ?? now(),
            ];

            $inserted++;

            if ($dryRun) {
                return;
            }

            $batch[] = $row;

            if (count($batch) >= 500) {
                try {
                    DB::table('sales')->insert($batch);
                } catch (\Exception $e) {
                    $errors += count($batch);
                    $inserted -= count($batch);
                }
                $batch = [];

                if ($total % 10000 === 0) {
                    $this->output->write("\r  Processed: {$total} records, {$inserted} inserted...");
                }
            }
        });

        // Insert remaining batch
        if (!$dryRun && !empty($batch)) {
            try {
                DB::table('sales')->insert($batch);
            } catch (\Exception $e) {
                $errors += count($batch);
                $inserted -= count($batch);
            }
        }

        $this->newLine();
        $this->info("Sales: {$total} total parsed, {$inserted} inserted, {$skippedCpf} skipped (CPF not found), {$skippedStore} skipped (store not found), {$skippedDuplicate} duplicates skipped, {$errors} errors.");
    }

    protected function streamInserts(string $tableName, array $columns, callable $callback): void
    {
        $handle = fopen($this->sqlFile, 'r');
        if (!$handle) {
            $this->error("Could not open SQL file.");
            return;
        }

        $inTable = false;
        $buffer = '';

        while (($line = fgets($handle)) !== false) {
            if (str_contains($line, "INSERT INTO `{$tableName}`")) {
                $inTable = true;
                $buffer = $line;
                continue;
            }

            if ($inTable) {
                $buffer .= $line;

                if (str_contains($line, ';')) {
                    $inTable = false;
                    $parsed = $this->parseValuesFromInsert($buffer, $columns);
                    foreach ($parsed as $record) {
                        $callback($record);
                    }
                    $buffer = '';
                }
            }
        }

        fclose($handle);
    }

    protected function parseInserts(string $tableName, array $columns): \Illuminate\Support\Collection
    {
        $this->info("Parsing {$tableName} from SQL file...");

        $records = collect();
        $handle = fopen($this->sqlFile, 'r');

        if (!$handle) {
            $this->error("Could not open SQL file.");
            return $records;
        }

        $inTable = false;
        $buffer = '';

        while (($line = fgets($handle)) !== false) {
            // Detect INSERT INTO for our table
            if (str_contains($line, "INSERT INTO `{$tableName}`")) {
                $inTable = true;
                $buffer = $line;
                continue;
            }

            if ($inTable) {
                $buffer .= $line;

                // Check if statement is complete (ends with ;)
                if (str_contains($line, ';')) {
                    $inTable = false;
                    $parsed = $this->parseValuesFromInsert($buffer, $columns);
                    $records = $records->concat($parsed);
                    $buffer = '';
                }
            }
        }

        fclose($handle);

        return $records;
    }

    protected function parseValuesFromInsert(string $sql, array $columns): array
    {
        $records = [];

        // Extract VALUES portion
        $pos = stripos($sql, 'VALUES');
        if ($pos === false) {
            return $records;
        }

        $valuesPart = substr($sql, $pos + 6);
        $valuesPart = rtrim($valuesPart, ";\n\r ");

        // Parse individual value tuples
        $depth = 0;
        $current = '';
        $inString = false;
        $escape = false;

        for ($i = 0; $i < strlen($valuesPart); $i++) {
            $char = $valuesPart[$i];

            if ($escape) {
                $current .= $char;
                $escape = false;
                continue;
            }

            if ($char === '\\') {
                $current .= $char;
                $escape = true;
                continue;
            }

            if ($char === "'" && !$escape) {
                $inString = !$inString;
                $current .= $char;
                continue;
            }

            if (!$inString) {
                if ($char === '(') {
                    $depth++;
                    if ($depth === 1) {
                        $current = '';
                        continue;
                    }
                } elseif ($char === ')') {
                    $depth--;
                    if ($depth === 0) {
                        $values = $this->parseTupleValues($current);
                        if (count($values) === count($columns)) {
                            $records[] = array_combine($columns, $values);
                        }
                        $current = '';
                        continue;
                    }
                }
            }

            $current .= $char;
        }

        return $records;
    }

    protected function parseTupleValues(string $tuple): array
    {
        $values = [];
        $current = '';
        $inString = false;
        $escape = false;

        for ($i = 0; $i < strlen($tuple); $i++) {
            $char = $tuple[$i];

            if ($escape) {
                $current .= $char;
                $escape = false;
                continue;
            }

            if ($char === '\\') {
                $escape = true;
                continue;
            }

            if ($char === "'" && !$escape) {
                $inString = !$inString;
                continue;
            }

            if ($char === ',' && !$inString) {
                $values[] = $this->cleanValue($current);
                $current = '';
                continue;
            }

            $current .= $char;
        }

        $values[] = $this->cleanValue($current);

        return $values;
    }

    protected function cleanValue(string $value): ?string
    {
        $value = trim($value);

        if ($value === 'NULL' || $value === 'null') {
            return null;
        }

        return $value;
    }
}
