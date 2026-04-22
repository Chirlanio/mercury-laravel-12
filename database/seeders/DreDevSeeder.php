<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\CostCenter;
use App\Models\DreManagementLine;
use App\Models\DreMapping;
use App\Models\Employee;
use App\Models\Network;
use App\Models\OrderPayment;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Seed realista para DRE (playbook prompt #14).
 *
 * NÃO é auto-registrado. Use:
 *   php artisan db:seed --class=DreDevSeeder
 *
 * Popula um cenário tipo-produção pequeno, suficiente para abrir
 * `/dre/matrix` e ver:
 *   - 5 lojas em 2 redes.
 *   - 50 OrderPayments (status=done) distribuídos nos últimos 6 meses.
 *   - 30 Sales (auto-projetadas via `SaleToDreProjector`).
 *   - 3 mappings (específico c/ CC + coringa + expirado).
 *   - 1 fechamento do mês passado (snapshots gerados).
 *   - Orçamento via `dre_budgets` direto (rápido — evita fluxo BudgetUpload).
 *
 * Idempotente-ish: roda 2x com sufixo único nos códigos para não colidir.
 *
 * Pressuposto: o tenant já tem o seed inicial (plano de contas executivo
 * 20 linhas + L99 + contas contábeis base). O DreDevSeeder só popula
 * movimento — ele NÃO recria estrutura.
 */
class DreDevSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('DreDevSeeder — populando cenário realista…');

        $suffix = substr(uniqid(), -4);
        $admin = User::query()->orderBy('id')->firstOrFail();

        // -----------------------------------------------------------------
        // 1. Redes + 5 lojas
        // -----------------------------------------------------------------
        $networks = collect([
            Network::firstOrCreate(
                ['nome' => "DRE Dev Rede A {$suffix}"],
                ['type' => 'comercial', 'active' => true],
            ),
            Network::firstOrCreate(
                ['nome' => "DRE Dev Rede B {$suffix}"],
                ['type' => 'comercial', 'active' => true],
            ),
        ]);

        // `stores.code` = varchar(4), então usamos o padrão Z### com números livres.
        $stores = collect();
        foreach (range(1, 5) as $i) {
            $code = 'Z'.random_int(700, 899);
            // Evita colisão com códigos ativos.
            while (Store::where('code', $code)->exists()) {
                $code = 'Z'.random_int(700, 899);
            }
            $stores->push(
                Store::factory()->create([
                    'code' => $code,
                    'name' => "DRE Dev Loja {$i}",
                    'network_id' => $networks[$i % 2]->id,
                ]),
            );
        }
        $this->command->info("  5 lojas em 2 redes criadas (sufixo {$suffix}).");

        // -----------------------------------------------------------------
        // 2. Contas + CCs + mappings
        // -----------------------------------------------------------------
        // `reduced_code` é UNIQUE no plano de contas — gerar explícito com o
        // suffix do run evita colisões entre seeds consecutivos.
        $revenue = ChartOfAccount::factory()->revenue()->analytical()->create([
            'code' => "DRE.DEV.REV.{$suffix}",
            'reduced_code' => "RDV-{$suffix}",
            'name' => "Receita Dev {$suffix}",
            'account_group' => 3,
        ]);

        $expense1 = ChartOfAccount::factory()->analytical()->create([
            'code' => "DRE.DEV.EXP1.{$suffix}",
            'reduced_code' => "EDV1-{$suffix}",
            'name' => "Despesa Telefonia Dev {$suffix}",
            'account_group' => 4,
        ]);

        $expense2 = ChartOfAccount::factory()->analytical()->create([
            'code' => "DRE.DEV.EXP2.{$suffix}",
            'reduced_code' => "EDV2-{$suffix}",
            'name' => "Despesa Marketing Dev {$suffix}",
            'account_group' => 4,
        ]);

        $cc = CostCenter::create([
            'code' => "DRE-DEV-CC-{$suffix}",
            'name' => "CC Dev {$suffix}",
            'is_active' => true,
            'created_by_user_id' => $admin->id,
        ]);

        $line = DreManagementLine::query()
            ->where('code', 'L99_UNCLASSIFIED')
            ->firstOrFail();

        // Mapping específico (com CC)
        DreMapping::create([
            'chart_of_account_id' => $expense1->id,
            'cost_center_id' => $cc->id,
            'dre_management_line_id' => $line->id,
            'effective_from' => now()->subMonths(6)->startOfMonth()->format('Y-m-d'),
            'effective_to' => null,
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        // Mapping coringa (sem CC) — usa receita
        DreMapping::create([
            'chart_of_account_id' => $revenue->id,
            'cost_center_id' => null,
            'dre_management_line_id' => $line->id,
            'effective_from' => now()->subMonths(6)->startOfMonth()->format('Y-m-d'),
            'effective_to' => null,
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        // Mapping expirado (já fora de vigência) — útil para testar precedência
        DreMapping::create([
            'chart_of_account_id' => $expense2->id,
            'cost_center_id' => null,
            'dre_management_line_id' => $line->id,
            'effective_from' => now()->subYear()->startOfMonth()->format('Y-m-d'),
            'effective_to' => now()->subMonths(7)->endOfMonth()->format('Y-m-d'),
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        $this->command->info('  3 mappings criados (específico, coringa, expirado).');

        // -----------------------------------------------------------------
        // 3. OrderPayments — 50, distribuídos nos últimos 6 meses
        //    (observer projeta automaticamente em dre_actuals)
        // -----------------------------------------------------------------
        $expenseAccounts = [$expense1, $expense2];
        $opCount = 0;
        for ($m = 5; $m >= 0; $m--) {
            $monthBase = now()->subMonths($m)->startOfMonth();
            foreach (range(1, 8) as $i) {
                $account = $expenseAccounts[array_rand($expenseAccounts)];
                $store = $stores[array_rand($stores->all())];
                $day = min(28, random_int(1, 28));
                $value = random_int(100, 2000) + random_int(0, 99) / 100;

                OrderPayment::create([
                    'description' => "DRE Dev OP {$suffix} m{$m} #{$i}",
                    'total_value' => $value,
                    'competence_date' => $monthBase->copy()->day($day)->format('Y-m-d'),
                    'date_payment' => $monthBase->copy()->day($day)->addDays(3)->format('Y-m-d'),
                    'accounting_class_id' => $account->id,
                    'cost_center_id' => $account->id === $expense1->id ? $cc->id : null,
                    'store_id' => $store->id,
                    'status' => OrderPayment::STATUS_DONE,
                    'created_by_user_id' => $admin->id,
                ]);
                $opCount++;
                if ($opCount >= 50) {
                    break 2;
                }
            }
        }
        $this->command->info("  {$opCount} OrderPayments criados (projetados via observer).");

        // -----------------------------------------------------------------
        // 4. Sales — 30 distribuídas; SaleToDreProjector projeta na conta
        //    `sale_chart_of_account_id` da loja (configuramos só uma).
        // -----------------------------------------------------------------
        $stores[0]->update(['sale_chart_of_account_id' => $revenue->id]);

        $saleCount = 0;
        for ($m = 5; $m >= 0; $m--) {
            $monthBase = now()->subMonths($m)->startOfMonth();
            foreach (range(1, 5) as $i) {
                $store = $stores[0]; // só a loja 0 tem conta configurada
                $day = min(28, random_int(1, 28));
                $total = random_int(300, 4000) + random_int(0, 99) / 100;

                $employee = Employee::factory()->create([
                    'store_id' => $store->code,
                    'area_id' => 1,
                ]);

                Sale::create([
                    'store_id' => $store->id,
                    'employee_id' => $employee->id,
                    'date_sales' => $monthBase->copy()->day($day)->format('Y-m-d'),
                    'total_sales' => $total,
                    'qtde_total' => random_int(1, 20),
                    'source' => 'manual',
                ]);
                $saleCount++;
                if ($saleCount >= 30) {
                    break 2;
                }
            }
        }
        $this->command->info("  {$saleCount} Sales criadas.");

        // -----------------------------------------------------------------
        // 5. Orçamento via dre_budgets direto — 12 meses do ano corrente
        // -----------------------------------------------------------------
        $year = (int) now()->year;
        $budgetVersion = "dre_dev_{$suffix}";

        foreach (range(1, 12) as $m) {
            // Receita prevista (positivo)
            $this->insertBudgetRow($revenue->id, $stores[0]->id, null, 5000, $year, $m, $budgetVersion);
            // Despesa prevista (negativo)
            $this->insertBudgetRow($expense1->id, $stores[0]->id, $cc->id, -1200, $year, $m, $budgetVersion);
            $this->insertBudgetRow($expense2->id, $stores[0]->id, null, -800, $year, $m, $budgetVersion);
        }
        $this->command->info("  Orçamento em dre_budgets populado (budget_version={$budgetVersion}).");

        // -----------------------------------------------------------------
        // 6. Fechamento do mês passado — gera snapshots
        // -----------------------------------------------------------------
        $lastMonthEnd = now()->subMonth()->endOfMonth();
        try {
            app(\App\Services\DRE\DrePeriodClosingService::class)->close(
                closedUpToDate: Carbon::parse($lastMonthEnd->format('Y-m-d')),
                closedBy: $admin,
                notes: 'Fechamento gerado por DreDevSeeder.',
            );
            $this->command->info("  Fechamento criado até {$lastMonthEnd->format('Y-m-d')} (snapshots persistidos).");
        } catch (\Throwable $e) {
            $this->command->warn("  Fechamento pulado: {$e->getMessage()} (provavelmente já existe).");
        }

        $this->command->info('DreDevSeeder — concluído. Abra /dre/matrix.');
    }

    /**
     * Cria uma linha em `dre_budgets` sem passar pelo projetor de
     * `BudgetUpload` — `budget_upload_id=null` marca origem manual/seeder.
     */
    private function insertBudgetRow(
        int $accountId,
        ?int $storeId,
        ?int $ccId,
        float $amount,
        int $year,
        int $month,
        string $version,
    ): void {
        \App\Models\DreBudget::create([
            'entry_date' => sprintf('%04d-%02d-01', $year, $month),
            'chart_of_account_id' => $accountId,
            'cost_center_id' => $ccId,
            'store_id' => $storeId,
            'amount' => round($amount, 2),
            'budget_version' => $version,
            'budget_upload_id' => null,
        ]);
    }
}
