<?php

use App\Models\CentralMenuPageDefault;
use App\Models\CentralPage;
use Illuminate\Database\Migrations\Migration;

/**
 * Removes the /helpdesk-reports menu entry after reports were unified
 * into the main helpdesk page as a tab (/helpdesk?tab=reports).
 *
 * The backend route is preserved as a redirect to the unified tab, so
 * existing bookmarks and deep links continue to work — but there's no
 * reason to keep it in the sidebar.
 */
return new class extends Migration
{
    public function up(): void
    {
        $page = CentralPage::where('route', '/helpdesk-reports')->first();
        if (! $page) {
            return;
        }

        // Drop menu defaults pointing at this page (all roles).
        CentralMenuPageDefault::where('central_page_id', $page->id)->delete();

        // Drop the page itself — the backend route remains as a redirect.
        $page->delete();
    }

    public function down(): void
    {
        // No-op: this migration intentionally does not recreate the menu entry.
        // If you need to restore it, re-run the original seed migration or
        // add the entry manually via the Admin Central UI.
    }
};
