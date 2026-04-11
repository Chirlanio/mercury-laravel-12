<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('neighborhoods')) {
            return;
        }

        // Skip if already populated
        if (DB::table('neighborhoods')->count() > 0) {
            return;
        }

        try {
            $legacyDb = new \PDO(
                'mysql:host=localhost;dbname=u401878354_meiaso26_bd_me;charset=utf8mb4',
                'root',
                ''
            );

            $stmt = $legacyDb->query('SELECT name, city FROM adms_neighborhoods ORDER BY name');
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $now = now();
            $inserts = [];
            foreach ($rows as $row) {
                $inserts[] = [
                    'name' => $row['name'],
                    'city' => $row['city'] !== 'N/A' ? $row['city'] : null,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (! empty($inserts)) {
                DB::table('neighborhoods')->insert($inserts);
            }

            Log::info('Neighborhoods seeded from legacy: '.count($inserts).' records');
        } catch (\Exception $e) {
            Log::warning('Could not seed neighborhoods from legacy: '.$e->getMessage());
        }
    }

    public function down(): void
    {
        // No rollback needed
    }
};
