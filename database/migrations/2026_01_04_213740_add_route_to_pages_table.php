<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Mapeamento legado de rotas antigas para rotas Laravel
     * Este mapeamento será usado apenas para migrar dados existentes
     */
    private function getLegacyRouteMapping(): array
    {
        return [
            // Dashboard/Home
            'home/index' => '/dashboard',
            'dashboard/listar' => '/dashboard',

            // Usuários
            'usuarios/listar' => '/users',
            'nivel-acesso/listar' => '/access-levels',
            'users-online/list' => '/users-online',
            'employees/list' => '/employees',
            'employees/index' => '/employees',

            // Páginas e Menus
            'pagina/listar' => '/pages',
            'menu/listar' => '/menus',

            // Logs
            'activity-logs' => '/activity-logs',

            // Admin
            'editar-conf-email/edit-conf-email' => '/admin/email-settings',
            'editar-form-cad-usuario/edit-form-cad-usuario' => '/admin/login-settings',

            // Funcionários
            'fixed-assets/list' => '/fixed-assets',
            'count-fixed-assets/list' => '/count-fixed-assets',

            // Controle de Jornada
            'material-marketing/list' => '/material-marketing',
            'material-request/list' => '/material-request',
            'overtime-control/list' => '/work-shifts',

            // Estoque
            'ajuste/listar-ajuste' => '/stock-adjustments',
            'transferencia/listar-transf' => '/transfers',
            'relocation/list' => '/relocations',
            'consignments/list' => '/consignments',

            // Delivery
            'delivery/listar' => '/delivery',
            'delivery-routes/list' => '/delivery-routes',
            'situacao-delivery/listar' => '/delivery-status',

            // RH/Pessoas & Cultura
            'gente-gestao/listar' => '/pessoas-cultura',
            'editar-gente-gestao/edit-gente-gestao' => '/pessoas-cultura/edit',
            'jobs-candidates/list' => '/candidates',
            'candidate-files/list' => '/candidate-files',
            'referral/list' => '/referrals',
            'personnel-moviments/list' => '/personnel-movements',
            'vacancy-opening/list' => '/vacancy-openings',
            'medical-certificate/list' => '/medical-certificates',
            'absence-control/list' => '/absence-control',
            'internal-transfer-system/list' => '/internal-transfers',

            // Financeiro
            'order-payments/list' => '/order-payments',
            'supplier/list' => '/suppliers',
            'cost-centers/list' => '/cost-centers',
            'type-payments/list' => '/payment-types',
            'banks/list' => '/banks',
            'tipo-pagamento/listar' => '/payment-types',
            'bandeira/listar' => '/card-brands',
            'motivo-estorno/listar' => '/reversal-reasons',
            'estorno/listar' => '/reversals',
            'autorizacao-resp/listar' => '/authorizations',
            'situacao-order-payment/listar' => '/payment-order-status',
            'travel-expenses/list' => '/travel-expenses',

            // Configurações
            'cor/listar' => '/colors',
            'grupo-pg/listar' => '/page-groups',
            'tipo-pg/listar' => '/page-types',
            'situacao/listar' => '/statuses',
            'situacao-user/listar' => '/user-statuses',
            'situacao-pg/listar' => '/page-statuses',
            'situacao-ajuste/listar' => '/adjustment-statuses',
            'situacao-transf/listar' => '/transfer-statuses',
            'situacao-troca/listar' => '/exchange-statuses',
            'lojas/listar-lojas' => '/stores',
            'stores/index' => '/stores',
            'cargo/listar-cargo' => '/positions',
            'bairro/listar' => '/neighborhoods',
            'rota/listar' => '/routes',
            'marcas/listar' => '/brands',
            'drivers/list' => '/drivers',
            'cfop/listar' => '/cfops',

            // Qualidade
            'ordem-servico/listar' => '/service-orders',
            'defeitos/listar' => '/defects',
            'detalhes/listar' => '/details',
            'defeito-local/listar' => '/defect-locations',

            // Escola Digital
            'escola-digital/listar-videos' => '/escola-digital',

            // Biblioteca de Processos
            'process-library/list' => '/biblioteca-processos',
            'policies/list' => '/policies',

            // E-commerce
            'ecommerce/list' => '/ecommerce',
            'cupons/list' => '/coupons',
            'returns/list' => '/returns',
            'sales/list' => '/sales',
            'store-goals/list' => '/store-goals',

            // Checklist
            'checklist/list' => '/checklists',
            'checklist-service/list' => '/service-checklists',

            // Outros
            'arquivo/listar' => '/files',
            'order-control/list' => '/order-control',

            // Logout
            'login/logout' => '/logout',
        ];
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Adicionar coluna route
        Schema::table('pages', function (Blueprint $table) {
            $table->string('route', 100)->nullable()->after('menu_method');
        });

        // Migrar dados existentes usando o mapeamento legado
        $mapping = $this->getLegacyRouteMapping();
        $pages = DB::table('pages')->get();

        foreach ($pages as $page) {
            $oldRoute = strtolower(trim($page->menu_controller . '/' . $page->menu_method, '/'));

            // Verificar se existe mapeamento
            if (isset($mapping[$oldRoute])) {
                $newRoute = $mapping[$oldRoute];
            } else {
                // Se não existe mapeamento, gerar rota baseada no controller/method
                $newRoute = '/' . $oldRoute;
            }

            DB::table('pages')
                ->where('id', $page->id)
                ->update(['route' => $newRoute]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('route');
        });
    }
};
