<?php

use App\Models\CentralMenuPageDefault;
use App\Models\CentralPage;
use Illuminate\Database\Migrations\Migration;

/**
 * Removes the four Helpdesk admin sub-entries previously added to the
 * sidebar by 2026_04_18_200001_add_helpdesk_admin_submenu.php:
 *
 *   - /helpdesk/admin/department-settings
 *   - /helpdesk/admin/intake-templates
 *   - /helpdesk/admin/articles
 *   - /helpdesk/admin/permissions
 *
 * These options are still reachable from the "Administração" dropdown
 * inside the Helpdesk page (resources/js/Pages/Helpdesk/Index.jsx), which
 * is the single entry point we want — the sidebar was rendering a second
 * "Helpdesk" block (direct link + dropdown header for the same menu),
 * causing a visible duplicate label.
 *
 * The backend routes and their controllers are preserved; only the
 * sidebar entries and their central_pages rows are removed. Existing
 * deep links continue to work.
 *
 * Note: CentralMenuResolver caches menu output for 5 minutes per
 * role/tenant (file cache). After running this migration, either wait
 * for the TTL to expire or run `php artisan cache:clear` so tenants see
 * the update immediately.
 */
return new class extends Migration
{
    public function up(): void
    {
        $routes = [
            '/helpdesk/admin/department-settings',
            '/helpdesk/admin/intake-templates',
            '/helpdesk/admin/articles',
            '/helpdesk/admin/permissions',
        ];

        $pageIds = CentralPage::whereIn('route', $routes)->pluck('id')->toArray();

        if (empty($pageIds)) {
            return;
        }

        CentralMenuPageDefault::whereIn('central_page_id', $pageIds)->delete();
        CentralPage::whereIn('id', $pageIds)->delete();
    }

    public function down(): void
    {
        // No-op: this migration intentionally does not recreate the menu
        // entries. If you need to restore them, re-run
        // 2026_04_18_200001_add_helpdesk_admin_submenu.php or add the
        // entries manually via the Admin Central UI.
    }
};
