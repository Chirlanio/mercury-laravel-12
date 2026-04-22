<?php

namespace App\Http\Controllers;

use App\Services\DRE\ChartOfAccountsImporter;
use App\Services\DRE\DreActualsImporter;
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

    /**
     * Template xlsx pré-formatado com todos os cabeçalhos aceitos.
     * Mesmo padrão de `BudgetController::template()`.
     */
    public function actualsTemplate(Request $request)
    {
        abort_unless(
            $request->user()?->hasPermissionTo('dre.import_actuals'),
            403
        );

        $headings = [
            'entry_date', 'store_code', 'account_code', 'cost_center_code',
            'amount', 'document', 'description', 'external_id',
        ];

        $exampleRows = [
            [
                '2026-04-15', 'Z421', '4.2.1.04.00032', '421',
                '350.00', 'NF-12345', 'Telefonia móvel — abril/2026', 'TEL-2026-04-Z421',
            ],
            [
                '2026-04-20', 'Z425', '4.2.1.04.00032', '425',
                '180.50', '', 'Recarga celular gerência', '',
            ],
        ];

        $export = new class($exampleRows, $headings) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings {
            public function __construct(public array $rows, public array $headings) {}

            public function array(): array
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return $this->headings;
            }
        };

        return \Maatwebsite\Excel\Facades\Excel::download(
            $export,
            'template-dre-realizado.xlsx',
            \Maatwebsite\Excel\Excel::XLSX
        );
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

    // Importar Orçado DRE foi removido da UI para evitar duplicidade com o
    // módulo Budgets (`BudgetUpload` → `BudgetToDreProjector` → `dre_budgets`).
    // Orçados manuais excepcionais agora só pelo command CLI:
    //   php artisan dre:import-budgets <path> --version=<label>
}
