import { Head, useForm } from '@inertiajs/react';
import { ArrowUpTrayIcon, BookOpenIcon } from '@heroicons/react/24/outline';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Button from '@/Components/Button';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import Checkbox from '@/Components/Checkbox';
import ImportReportPanel from '@/Components/DRE/Imports/ImportReportPanel';

/**
 * Upload do plano de contas do ERP (CIGAM/TAYLOR/ZZNET).
 *
 * Reaproveita `ChartOfAccountsImporter` — a mesma lógica que o command
 * `dre:import-chart` usa. Gera plano de contas + centros de custo num
 * passo só.
 */
export default function Chart({ flash }) {
    const report = flash?.import_report ?? null;

    const { data, setData, post, processing, errors, reset } = useForm({
        file: null,
        source: 'CIGAM',
        dry_run: false,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('dre.imports.chart.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => reset('file'),
        });
    };

    const chart = report
        ? {
              total_read: report.total_read,
              created: (report.accounts?.created ?? 0) + (report.cost_centers?.created ?? 0),
              updated: (report.accounts?.updated ?? 0) + (report.cost_centers?.updated ?? 0),
              skipped: 0,
              dry_run: report.dry_run,
              errors: report.read_errors ?? [],
          }
        : null;

    return (
        <AuthenticatedLayout>
            <Head title="Importar plano de contas" />

            <div className="py-10">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                    <div className="flex items-center gap-3">
                        <div className="rounded-lg bg-slate-100 p-3">
                            <BookOpenIcon className="h-6 w-6 text-slate-600" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">Importar plano de contas</h1>
                            <p className="text-sm text-gray-600">
                                Atualiza <code className="font-mono text-xs">chart_of_accounts</code> + <code>cost_centers</code>
                                a partir do XLSX do ERP. Idempotente por <code>reduced_code</code>.
                            </p>
                        </div>
                    </div>

                    <form
                        onSubmit={handleSubmit}
                        className="bg-white shadow-sm rounded-lg p-6 space-y-4 border border-gray-200"
                    >
                        <div>
                            <InputLabel htmlFor="source" value="ERP de origem" />
                            <TextInput
                                id="source"
                                value={data.source}
                                onChange={(e) => setData('source', e.target.value)}
                                placeholder="CIGAM"
                                className="mt-1 block w-full"
                                maxLength={30}
                            />
                            <InputError message={errors.source} className="mt-1" />
                        </div>

                        <div>
                            <InputLabel htmlFor="file" value="Arquivo XLSX" />
                            <input
                                id="file"
                                type="file"
                                accept=".xlsx,.xls,.csv"
                                onChange={(e) => setData('file', e.target.files?.[0] ?? null)}
                                className="mt-1 block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200"
                            />
                            <InputError message={errors.file} className="mt-1" />
                        </div>

                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="dry_run"
                                name="dry_run"
                                checked={data.dry_run}
                                onChange={(e) => setData('dry_run', e.target.checked)}
                            />
                            <InputLabel htmlFor="dry_run" value="Dry-run (simular sem gravar)" className="!mb-0" />
                        </div>

                        <div className="flex justify-end">
                            <Button
                                type="submit"
                                variant="dark"
                                icon={<ArrowUpTrayIcon className="h-4 w-4" />}
                                loading={processing}
                                disabled={!data.file || processing}
                            >
                                {data.dry_run ? 'Simular' : 'Importar'}
                            </Button>
                        </div>
                    </form>

                    {chart && (
                        <ImportReportPanel
                            report={chart}
                            title={chart.dry_run ? 'Resultado do dry-run' : 'Resultado da importação'}
                            showUpdated
                        />
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
