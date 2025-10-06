<?php

namespace App\Http\Controllers;

use App\Models\AccessLevel;
use App\Models\Menu;
use App\Models\Page;
use App\Models\AccessLevelPage;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccessLevelPageController extends Controller
{
    /**
     * Exibe a interface de gerenciamento de páginas/permissões
     * para um nível de acesso e menu específicos
     *
     * @param AccessLevel $accessLevel
     * @param Menu $menu
     * @return \Inertia\Response
     */
    public function manage(AccessLevel $accessLevel, Menu $menu)
    {
        // Buscar todas as páginas ativas
        $allPages = Page::active()->ordered()->get();

        // Buscar permissões existentes para este access level e menu
        $existingPermissions = AccessLevelPage::where('access_level_id', $accessLevel->id)
            ->where('menu_id', $menu->id)
            ->with('page')
            ->orderBy('order')
            ->get();

        // Mapear permissões existentes por page_id
        $permissionsMap = $existingPermissions->keyBy('page_id');

        // Preparar dados das páginas com suas permissões
        $pagesWithPermissions = $allPages->map(function ($page) use ($permissionsMap, $accessLevel, $menu) {
            $permission = $permissionsMap->get($page->id);

            return [
                'id' => $page->id,
                'page_name' => $page->page_name,
                'menu_controller' => $page->menu_controller,
                'menu_method' => $page->menu_method,
                'icon' => $page->icon,
                'is_public' => $page->is_public,
                'is_active' => $page->is_active,
                // Dados da permissão (se existir)
                'has_permission' => $permission ? $permission->permission : false,
                'order' => $permission ? $permission->order : 999,
                'dropdown' => $permission ? $permission->dropdown : false,
                'lib_menu' => $permission ? $permission->lib_menu : false,
                'access_level_page_id' => $permission ? $permission->id : null,
            ];
        });

        return Inertia::render('AccessLevelPages/Manage', [
            'accessLevel' => [
                'id' => $accessLevel->id,
                'name' => $accessLevel->name,
            ],
            'menu' => [
                'id' => $menu->id,
                'name' => $menu->name,
                'icon' => $menu->icon,
            ],
            'pages' => $pagesWithPermissions,
        ]);
    }

    /**
     * Atualiza as permissões de páginas para um nível de acesso e menu
     *
     * @param Request $request
     * @param AccessLevel $accessLevel
     * @param Menu $menu
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updatePermissions(Request $request, AccessLevel $accessLevel, Menu $menu)
    {
        $validated = $request->validate([
            'pages' => 'required|array',
            'pages.*.page_id' => 'required|exists:pages,id',
            'pages.*.permission' => 'boolean',
            'pages.*.order' => 'required|integer|min:1',
            'pages.*.dropdown' => 'boolean',
            'pages.*.lib_menu' => 'boolean',
        ]);

        // Remover todas as permissões existentes para este access level + menu
        AccessLevelPage::where('access_level_id', $accessLevel->id)
            ->where('menu_id', $menu->id)
            ->delete();

        // Inserir novas permissões (somente para páginas com permission = true)
        foreach ($validated['pages'] as $pageData) {
            // Só criar registro se a permissão estiver ativada
            if ($pageData['permission'] ?? false) {
                AccessLevelPage::create([
                    'access_level_id' => $accessLevel->id,
                    'menu_id' => $menu->id,
                    'page_id' => $pageData['page_id'],
                    'permission' => true,
                    'order' => $pageData['order'],
                    'dropdown' => $pageData['dropdown'] ?? false,
                    'lib_menu' => $pageData['lib_menu'] ?? true,
                ]);
            }
        }

        return back()->with('success', 'Permissões atualizadas com sucesso!');
    }

    /**
     * Retorna as páginas disponíveis para um menu
     * (endpoint JSON para uso em modais/AJAX)
     *
     * @param AccessLevel $accessLevel
     * @param Menu $menu
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPages(AccessLevel $accessLevel, Menu $menu)
    {
        $allPages = Page::active()->ordered()->get();

        $existingPermissions = AccessLevelPage::where('access_level_id', $accessLevel->id)
            ->where('menu_id', $menu->id)
            ->with('page')
            ->orderBy('order')
            ->get()
            ->keyBy('page_id');

        $pagesWithPermissions = $allPages->map(function ($page) use ($existingPermissions) {
            $permission = $existingPermissions->get($page->id);

            return [
                'id' => $page->id,
                'page_name' => $page->page_name,
                'menu_controller' => $page->menu_controller,
                'menu_method' => $page->menu_method,
                'icon' => $page->icon,
                'has_permission' => $permission ? $permission->permission : false,
                'order' => $permission ? $permission->order : 999,
                'dropdown' => $permission ? $permission->dropdown : false,
                'lib_menu' => $permission ? $permission->lib_menu : false,
            ];
        });

        return response()->json([
            'accessLevel' => [
                'id' => $accessLevel->id,
                'name' => $accessLevel->name,
            ],
            'menu' => [
                'id' => $menu->id,
                'name' => $menu->name,
                'icon' => $menu->icon,
            ],
            'pages' => $pagesWithPermissions,
        ]);
    }
}
