import { Head, useForm, usePage } from '@inertiajs/react';
import { ArrowUpTrayIcon, ArrowDownTrayIcon, DocumentArrowUpIcon, ExclamationTriangleIcon, CheckCircleIcon } from '@heroicons/react/24/outline';
import Button from '@/Components/Button';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import Checkbox from '@/Components/Checkbox';
import ImportReportPanel from '@/Components/DRE/Imports/ImportReportPanel';

/**
 * Upload de realizado manual da DRE (source=MANUAL_IMPORT).
 *
 * Execução síncrona. Após o submit o controller retorna via flash o
 * `import_report` com contadores + erros PT. Polling assíncrono ficou no
 * backlog — lançar quando planilhas >5k linhas aparecerem.
 */
export default function Actuals({ flash }) {
    const report = flash?.import_report ?? null;

    const { data, setData, post, processing, errors, reset } = useForm({
        file: null,
        dry_run: false,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('dre.imports.actuals.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => reset('file'),
        });
    };

    return (
        <>
            <Head title="Importar realizado DRE" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                    <div className="flex items-center gap-3">
                        <div className="rounded-lg bg-indigo-100 p-3">
                            <DocumentArrowUpIcon className="h-6 w-6 text-indigo-600" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">Importar realizado</h1>
                            <p className="text-sm text-gray-600">
                                Carrega um XLSX de lançamentos manuais em <code className="font-mono text-xs">dre_actuals</code> com <code>source=MANUAL_IMPORT</code>.
                                Formato em <code>docs/dre-imports-formatos.md</code>.
                            </p>
                        </div>
                    </div>

                    <form
                        onSubmit={handleSubmit}
                        className="bg-white shadow-sm rounded-lg p-6 space-y-4 border border-gray-200"
                    >
                        <div>
                            <div className="flex items-center justify-between">
                                <InputLabel htmlFor="file" value="Arquivo XLSX" />
                                <Button
                                    type="button"
                                    variant="secondary"
                                    size="xs"
                                    icon={ArrowDownTrayIcon}
                                    onClick={() => window.location.href = route('dre.imports.actuals.template')}
                                >
                                    Baixar modelo
                                </Button>
                            </div>
                            <input
                                id="file"
                                type="file"
                                accept=".xlsx,.xls,.csv"
                                onChange={(e) => setData('file', e.target.files?.[0] ?? null)}
                                className="mt-1 block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                            />
                            <InputError message={errors.file} className="mt-1" />
                            <p className="mt-1 text-xs text-gray-500">
                                Colunas: entry_date, store_code, account_code, cost_center_code (opt), amount, document (opt), description (opt), external_id (opt).
                            </p>
                        </div>

                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="dry_run"
                                name="dry_run"
                                checked={data.dry_run}
                                onChange={(e) => setData('dry_run', e.target.checked)}
                            />
                            <InputLabel htmlFor="dry_run" value="Dry-run (validar sem persistir)" className="!mb-0" />
                        </div>

                        <div className="flex justify-end">
                            <Button
                                type="submit"
                                variant="primary"
                                icon={ArrowUpTrayIcon}
                                loading={processing}
                                disabled={!data.file || processing}
                            >
                                {data.dry_run ? 'Validar arquivo' : 'Importar'}
                            </Button>
                        </div>
                    </form>

                    {report && (
                        <ImportReportPanel
                            report={report}
                            title={report.dry_run ? 'Resultado do dry-run' : 'Resultado da importação'}
                            showUpdated
                        />
                    )}

                    {!report && (
                        <div className="bg-amber-50 border border-amber-200 rounded-lg p-4 text-sm text-amber-800 flex gap-2">
                            <ExclamationTriangleIcon className="h-5 w-5 flex-shrink-0 mt-0.5" />
                            <div>
                                Lançamentos importados entram com <strong>source=MANUAL_IMPORT</strong> e ficam
                                visíveis na matriz DRE imediatamente após o commit. Use <strong>external_id</strong> para
                                re-importar o mesmo lote sem duplicar.
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
