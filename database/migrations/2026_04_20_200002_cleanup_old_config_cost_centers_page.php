<?php

use App\Models\CentralMenuPageDefault;
use App\Models\CentralPage;
use Illuminate\Database\Migrations\Migration;

/**
 * Cleanup da Fase 0.1 — remove a página antiga `/config/cost-centers`
 * do menu central. A rota da aplicação agora redireciona 301 para
 * `/cost-centers` (módulo standalone).
 *
 * Idempotente: se a página não existir, no-op.
 */
return new class extends Migration
{
    private const OLD_ROUTE = '/config/cost-centers';

    public function up(): void
    {
        $oldPage = CentralPage::where('route', self::OLD_ROUTE)->first();

        if (! $oldPage) {
            return;
        }

        CentralMenuPageDefault::where('central_page_id', $oldPage->id)->delete();
        $oldPage->delete();
    }

    public function down(): void
    {
        // Rollback não recria a página antiga — o cleanup é intencional e
        // permanente. Para reverter de fato, use a migration original do
        // CentralNavigationSeeder.
    }
};
