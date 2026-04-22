import { Head, useForm } from '@inertiajs/react';
import { ArrowUpTrayIcon, DocumentArrowUpIcon, ExclamationTriangleIcon } from '@heroicons/react/24/outline';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Button from '@/Components/Button';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import Checkbox from '@/Components/Checkbox';
import ImportReportPanel from '@/Components/DRE/Imports/ImportReportPanel';

/**
 * Upload manual de orçado para `dre_budgets`. Alternativa ao fluxo
 * Budgets → BudgetToDreProjector — útil para cenários em que o orçamento
 * não foi lançado pelo módulo Budgets (ex: cenário consolidado externo).
 */
export default function Budgets({ flash }) {
    const report = flash?.import_report ?? null;

    const { data, setData, post, processing, errors, reset } = useForm({
        file: null,
        budget_version: '',
        dry_run: false,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('dre.imports.budgets.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => reset('file'),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Importar orçado DRE" />

            <div className="py-10">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                    <div className="flex items-center gap-3">
                        <div className="rounded-lg bg-emerald-100 p-3">
                            <DocumentArrowUpIcon className="h-6 w-6 text-emerald-600" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">Importar orçado</h1>
                            <p className="text-sm text-gray-600">
                                Carrega um XLSX de orçado para <code className="font-mono text-xs">dre_budgets</code>.
                                Informe o <strong>budget_version</strong> — as linhas importadas ficam acessíveis
                                ao selecionar essa versão na matriz.
                            </p>
                        </div>
                    </div>

                    <form
                        onSubmit={handleSubmit}
                        className="bg-white shadow-sm rounded-lg p-6 space-y-4 border border-gray-200"
                    >
                        <div>
                            <InputLabel htmlFor="budget_version" value="Budget version (obrigatório)" />
                            <TextInput
                                id="budget_version"
                                value={data.budget_version}
                                onChange={(e) => setData('budget_version', e.target.value)}
                                placeholder="ex: 2026.v1, action_plan_v1"
                                className="mt-1 block w-full"
                                maxLength={30}
                            />
                            <InputError message={errors.budget_version} className="mt-1" />
                        </div>

                        <div>
                            <InputLabel htmlFor="file" value="Arquivo XLSX" />
                            <input
                                id="file"
                                type="file"
                                accept=".xlsx,.xls,.csv"
                                onChange={(e) => setData('file', e.target.files?.[0] ?? null)}
                                className="mt-1 block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100"
                            />
                            <InputError message={errors.file} className="mt-1" />
                            <p className="mt-1 text-xs text-gray-500">
                                Colunas: entry_date (YYYY-MM ou YYYY-MM-DD), store_code (opt), account_code, cost_center_code (opt), amount, notes (opt).
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
                                variant="success"
                                icon={<ArrowUpTrayIcon className="h-4 w-4" />}
                                loading={processing}
                                disabled={!data.file || !data.budget_version || processing}
                            >
                                {data.dry_run ? 'Validar arquivo' : 'Importar'}
                            </Button>
                        </div>
                    </form>

                    {report && (
                        <ImportReportPanel
                            report={report}
                            title={report.dry_run ? 'Resultado do dry-run' : 'Resultado da importação'}
                        />
                    )}

                    {!report && (
                        <div className="bg-amber-50 border border-amber-200 rounded-lg p-4 text-sm text-amber-800 flex gap-2">
                            <ExclamationTriangleIcon className="h-5 w-5 flex-shrink-0 mt-0.5" />
                            <div>
                                A versão informada fica conhecida na matriz apenas depois do primeiro import bem-sucedido.
                                Para substituir uma versão existente, importe usando o mesmo <strong>budget_version</strong>
                                — não há dedup automático; recomenda-se usar labels incrementais (<code>2026.v2</code>, <code>2026.v3</code>).
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
