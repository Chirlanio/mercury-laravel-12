<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AccessLevelPageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        // Verificar se as foreign keys existem antes de inserir
        $existingMenuIds = DB::table('menus')->pluck('id')->toArray();
        $existingAccessLevelIds = DB::table('access_levels')->pluck('id')->toArray();
        $existingPageIds = DB::table('pages')->pluck('id')->toArray();

        $accessLevelPages = [
            ['permission' => true, 'order' => 1, 'dropdown' => false, 'lib_menu' => true, 'menu_id' => 1, 'access_level_id' => 1, 'page_id' => 1],
            ['permission' => true, 'order' => 2, 'dropdown' => true, 'lib_menu' => true, 'menu_id' => 2, 'access_level_id' => 1, 'page_id' => 2],
            ['permission' => true, 'order' => 4, 'dropdown' => false, 'lib_menu' => true, 'menu_id' => 6, 'access_level_id' => 1, 'page_id' => 4],
            ['permission' => true, 'order' => 5, 'dropdown' => true, 'lib_menu' => false, 'menu_id' => 4, 'access_level_id' => 1, 'page_id' => 5],
            ['permission' => true, 'order' => 6, 'dropdown' => true, 'lib_menu' => false, 'menu_id' => 2, 'access_level_id' => 1, 'page_id' => 7],
            ['permission' => true, 'order' => 7, 'dropdown' => false, 'lib_menu' => false, 'menu_id' => 2, 'access_level_id' => 1, 'page_id' => 9],
            ['permission' => true, 'order' => 8, 'dropdown' => false, 'lib_menu' => false, 'menu_id' => 2, 'access_level_id' => 1, 'page_id' => 10],
            ['permission' => true, 'order' => 9, 'dropdown' => false, 'lib_menu' => false, 'menu_id' => 2, 'access_level_id' => 1, 'page_id' => 11],
            ['permission' => true, 'order' => 10, 'dropdown' => false, 'lib_menu' => false, 'menu_id' => 2, 'access_level_id' => 1, 'page_id' => 12],
            ['permission' => true, 'order' => 11, 'dropdown' => false, 'lib_menu' => false, 'menu_id' => 2, 'access_level_id' => 1, 'page_id' => 13],
            ['permission' => true, 'order' => 12, 'dropdown' => false, 'lib_menu' => false, 'menu_id' => 2, 'access_level_id' => 1, 'page_id' => 14],
            ['permission' => true, 'order' => 13, 'dropdown' => false, 'lib_menu' => false, 'menu_id' => 2, 'access_level_id' => 1, 'page_id' => 15],
            ['permission' => true, 'order' => 15, 'dropdown' => false, 'lib_menu' => false, 'menu_id' => 2, 'access_level_id' => 1, 'page_id' => 16],
            ['permission' => true, 'order' => 1, 'dropdown' => false, 'lib_menu' => true, 'menu_id' => 1, 'access_level_id' => 2, 'page_id' => 1],
            ['permission' => true, 'order' => 2, 'dropdown' => false, 'lib_menu' => false, 'menu_id' => 2, 'access_level_id' => 2, 'page_id' => 9],
            ['permission' => true, 'order' => 3, 'dropdown' => false, 'lib_menu' => false, 'menu_id' => 2, 'access_level_id' => 2, 'page_id' => 10],
            ['permission' => true, 'order' => 4, 'dropdown' => false, 'lib_menu' => false, 'menu_id' => 2, 'access_level_id' => 2, 'page_id' => 11],
            ['permission' => true, 'order' => 6, 'dropdown' => true, 'lib_menu' => true, 'menu_id' => 2, 'access_level_id' => 2, 'page_id' => 2],
            ['permission' => true, 'order' => 5, 'dropdown' => false, 'lib_menu' => false, 'menu_id' => 2, 'access_level_id' => 2, 'page_id' => 12],
            ['permission' => true, 'order' => 7, 'dropdown' => false, 'lib_menu' => false, 'menu_id' => 2, 'access_level_id' => 2, 'page_id' => 13],
            ['permission' => true, 'order' => 8, 'dropdown' => false, 'lib_menu' => false, 'menu_id' => 2, 'access_level_id' => 2, 'page_id' => 14],
            ['permission' => true, 'order' => 9, 'dropdown' => false, 'lib_menu' => false, 'menu_id' => 2, 'access_level_id' => 2, 'page_id' => 15],
            ['permission' => false, 'order' => 10, 'dropdown' => false, 'lib_menu' => false, 'menu_id' => 2, 'access_level_id' => 2, 'page_id' => 16],
            ['permission' => true, 'order' => 11, 'dropdown' => false, 'lib_menu' => true, 'menu_id' => 6, 'access_level_id' => 2, 'page_id' => 4],
            ['permission' => true, 'order' => 3, 'dropdown' => true, 'lib_menu' => true, 'menu_id' => 2, 'access_level_id' => 1, 'page_id' => 17],
            ['permission' => true, 'order' => 14, 'dropdown' => false, 'lib_menu' => false, 'menu_id' => 2, 'access_level_id' => 1, 'page_id' => 18],
        ];

        foreach ($accessLevelPages as $accessLevelPage) {
            // Verificar se todas as foreign keys existem
            if (!in_array($accessLevelPage['menu_id'], $existingMenuIds)) {
                echo "⚠️  AccessLevelPage ignorado - menu_id {$accessLevelPage['menu_id']} não existe\n";
                continue;
            }

            if (!in_array($accessLevelPage['access_level_id'], $existingAccessLevelIds)) {
                echo "⚠️  AccessLevelPage ignorado - access_level_id {$accessLevelPage['access_level_id']} não existe\n";
                continue;
            }

            if (!in_array($accessLevelPage['page_id'], $existingPageIds)) {
                echo "⚠️  AccessLevelPage ignorado - page_id {$accessLevelPage['page_id']} não existe\n";
                continue;
            }

            DB::table('access_level_pages')->updateOrInsert(
                [
                    'access_level_id' => $accessLevelPage['access_level_id'],
                    'page_id' => $accessLevelPage['page_id']
                ],
                [
                    'permission' => $accessLevelPage['permission'],
                    'order' => $accessLevelPage['order'],
                    'dropdown' => $accessLevelPage['dropdown'],
                    'lib_menu' => $accessLevelPage['lib_menu'],
                    'menu_id' => $accessLevelPage['menu_id'],
                    'created_at' => $now,
                    'updated_at' => null,
                ]
            );
        }
    }
}
