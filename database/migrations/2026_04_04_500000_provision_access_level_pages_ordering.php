<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Provisiona e ordena registros em access_level_pages para todas as
     * combinações de página × nível de acesso.
     *
     * Lógica:
     * - Para cada nível de acesso, percorre TODAS as páginas ordenadas por id (ordem de cadastro)
     * - Se já existe registro, atualiza apenas o campo `order` (mantém permission, dropdown, lib_menu, menu_id)
     * - Se não existe, cria com permission=false (exceto Super Admin que recebe true), order sequencial
     * - A ordem final é: 1, 2, 3... seguindo a sequência de id das páginas
     */
    public function up(): void
    {
        $accessLevels = DB::table('access_levels')->orderBy('order')->get();
        $pages = DB::table('pages')->orderBy('id')->get();
        $superAdminId = $accessLevels->first()?->id;
        $now = now();

        foreach ($accessLevels as $level) {
            // Buscar registros existentes para este nível
            $existing = DB::table('access_level_pages')
                ->where('access_level_id', $level->id)
                ->get()
                ->keyBy('page_id');

            $order = 1;

            foreach ($pages as $page) {
                $record = $existing->get($page->id);

                if ($record) {
                    // Já existe: atualiza apenas a ordem
                    DB::table('access_level_pages')
                        ->where('id', $record->id)
                        ->update([
                            'order' => $order,
                            'updated_at' => $now,
                        ]);
                } else {
                    // Não existe: cria registro com permissão baseada no nível
                    DB::table('access_level_pages')->insert([
                        'access_level_id' => $level->id,
                        'page_id' => $page->id,
                        'permission' => $level->id === $superAdminId,
                        'order' => $order,
                        'dropdown' => false,
                        'lib_menu' => false,
                        'menu_id' => null,
                        'created_at' => $now,
                        'updated_at' => null,
                    ]);
                }

                $order++;
            }
        }
    }

    /**
     * Reverse: remove apenas os registros criados por esta migration
     * (permission=false, dropdown=false, lib_menu=false, menu_id=null)
     * que não existiam antes.
     */
    public function down(): void
    {
        // Remove registros "default" que foram criados (sem menu, sem permissão)
        DB::table('access_level_pages')
            ->where('permission', false)
            ->where('dropdown', false)
            ->where('lib_menu', false)
            ->whereNull('menu_id')
            ->delete();
    }
};
