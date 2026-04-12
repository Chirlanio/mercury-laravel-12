<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hd_departments') || ! Schema::hasTable('hd_categories')) {
            return;
        }

        // Skip if already seeded
        if (DB::table('hd_departments')->count() > 0) {
            return;
        }

        $now = now();

        // Departments
        $tiId = DB::table('hd_departments')->insertGetId([
            'name' => 'TI',
            'description' => 'Tecnologia da Informação',
            'icon' => 'ComputerDesktopIcon',
            'is_active' => true,
            'sort_order' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $dpId = DB::table('hd_departments')->insertGetId([
            'name' => 'DP',
            'description' => 'Departamento Pessoal',
            'icon' => 'UserGroupIcon',
            'is_active' => true,
            'sort_order' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $facId = DB::table('hd_departments')->insertGetId([
            'name' => 'Facilities',
            'description' => 'Manutenção e Infraestrutura',
            'icon' => 'WrenchScrewdriverIcon',
            'is_active' => true,
            'sort_order' => 3,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Categories - TI
        $tiCategories = [
            ['name' => 'Computador / Notebook', 'default_priority' => 2],
            ['name' => 'Impressora / Toner', 'default_priority' => 1],
            ['name' => 'Rede / Internet', 'default_priority' => 3],
            ['name' => 'Sistema Mercury', 'default_priority' => 3],
            ['name' => 'E-mail', 'default_priority' => 2],
            ['name' => 'Telefonia', 'default_priority' => 2],
            ['name' => 'Outros - TI', 'default_priority' => 1],
        ];

        // Categories - DP
        $dpCategories = [
            ['name' => 'Vale Transporte', 'default_priority' => 2],
            ['name' => 'Vale Alimentação', 'default_priority' => 2],
            ['name' => 'Folha de Pagamento', 'default_priority' => 3],
            ['name' => 'Férias', 'default_priority' => 2],
            ['name' => 'Admissão / Demissão', 'default_priority' => 3],
            ['name' => 'Atestados', 'default_priority' => 2],
            ['name' => 'Outros - DP', 'default_priority' => 1],
        ];

        // Categories - Facilities
        $facCategories = [
            ['name' => 'Ar Condicionado', 'default_priority' => 2],
            ['name' => 'Elétrica', 'default_priority' => 3],
            ['name' => 'Hidráulica', 'default_priority' => 3],
            ['name' => 'Limpeza', 'default_priority' => 1],
            ['name' => 'Mobiliário', 'default_priority' => 1],
            ['name' => 'Outros - Facilities', 'default_priority' => 1],
        ];

        foreach ($tiCategories as $cat) {
            DB::table('hd_categories')->insert(array_merge($cat, ['department_id' => $tiId, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]));
        }
        foreach ($dpCategories as $cat) {
            DB::table('hd_categories')->insert(array_merge($cat, ['department_id' => $dpId, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]));
        }
        foreach ($facCategories as $cat) {
            DB::table('hd_categories')->insert(array_merge($cat, ['department_id' => $facId, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]));
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('hd_categories')) {
            DB::table('hd_categories')->truncate();
        }
        if (Schema::hasTable('hd_departments')) {
            DB::table('hd_departments')->truncate();
        }
    }
};
