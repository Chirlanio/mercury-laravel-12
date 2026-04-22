<?php

namespace App\Http\Controllers;

use App\Services\DRE\ChartOfAccountsImporter;
use App\Services\DRE\DreActualsImporter;
use App\Services\DRE\DreBudgetsImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Importação manual de dados da DRE (chart, actuals, budgets) via HTTP.
 *
 * Execução síncrona: o arquivo é validado, lido e gravado dentro do ciclo
 * da request. Para planilhas até ~5k linhas isso roda em poucos segundos.
 * Jobs async + polling de status ficaram no backlog — lançar quando o
 * volume pedir (arquitetura §3.7).
 *
 * `store()` devolve o relatório via flash (`import_report`) — a página
 * renderiza a tabela de erros PT sem segundo roundtrip. Redireciona de
 * volta para a mesma rota para que F5 não reenvie o upload.
 */
class DreImportController extends Controller
{
    // ------------------------------------------------------------------
    // Chart of accounts (plano de contas)
    // ------------------------------------------------------------------

    public function chartForm(Request $request): Response
    {
        abort_unless(
            $request->user()?->hasPermissionTo('dre.manage_mappings'),
            403
        );

        return Inertia::render('DRE/Imports/Chart', [
            'flash' => [
                'import_report' => session('import_report'),
            ],
        ]);
    }

    public function chartStore(Request $request, ChartOfAccountsImporter $importer): RedirectResponse
    {
        abort_unless(
            $request->user()?->hasPermissionTo('dre.manage_mappings'),
            403
        );

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480'],
            'source' => ['nullable', 'string', 'max:30'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $path = $data['file']->getRealPath();
        $source = $data['source'] ?? 'CIGAM';
        $dryRun = (bool) ($data['dry_run'] ?? false);

        $report = $importer->import($path, $source, $dryRun);

        return redirect()
            ->route('dre.imports.chart')
            ->with('import_report', $report->toArray());
    }

    // ------------------------------------------------------------------
    // Actuals (realizado manual)
    // ------------------------------------------------------------------

    public function actualsForm(Request $request): Response
    {
        abort_unless(
            $request->user()?->hasPermissionTo('dre.import_actuals'),
            403
        );

        return Inertia::render('DRE/Imports/Actuals', [
            'flash' => [
                'import_report' => session('import_report'),
            ],
        ]);
    }

    public function actualsStore(Request $request, DreActualsImporter $importer): RedirectResponse
    {
        abort_unless(
            $request->user()?->hasPermissionTo('dre.import_actuals'),
            403
        );

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $path = $data['file']->getRealPath();
        $dryRun = (bool) ($data['dry_run'] ?? false);

        $report = $importer->import($path, $dryRun);

        return redirect()
            ->route('dre.imports.actuals')
            ->with('import_report', $report->toArray());
    }

    // ------------------------------------------------------------------
    // Budgets (orçado manual)
    // ------------------------------------------------------------------

    public function budgetsForm(Request $request): Response
    {
        abort_unless(
            $request->user()?->hasPermissionTo('dre.import_budgets'),
            403
        );

        return Inertia::render('DRE/Imports/Budgets', [
            'flash' => [
                'import_report' => session('import_report'),
            ],
        ]);
    }

    public function budgetsStore(Request $request, DreBudgetsImporter $importer): RedirectResponse
    {
        abort_unless(
            $request->user()?->hasPermissionTo('dre.import_budgets'),
            403
        );

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480'],
            'budget_version' => ['required', 'string', 'max:30'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $path = $data['file']->getRealPath();
        $version = $data['budget_version'];
        $dryRun = (bool) ($data['dry_run'] ?? false);

        $report = $importer->import($path, $version, $dryRun);

        return redirect()
            ->route('dre.imports.budgets')
            ->with('import_report', $report->toArray());
    }
}
