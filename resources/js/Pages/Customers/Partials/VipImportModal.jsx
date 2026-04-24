import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import {
    ArrowUpTrayIcon, DocumentArrowDownIcon, ExclamationTriangleIcon,
    InformationCircleIcon,
} from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import Button from '@/Components/Button';
import StatusBadge from '@/Components/Shared/StatusBadge';

/**
 * Modal de import de lista VIP via XLSX.
 * Após upload, exibe summary + erros por linha (se houver).
 */
export default function VipImportModal({ show, onClose }) {
    const [summary, setSummary] = useState(null);

    const form = useForm({
        file: null,
        replace_year: false,
    });

    const handleSubmit = () => {
        if (!form.data.file) {
            return;
        }
        // useForm.post já serializa multipart quando há File em data
        form.post(route('customers.vip.import'), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: (page) => {
                const flashed = page.props?.flash?.vip_import_summary;
                if (flashed) {
                    setSummary(flashed);
                }
                form.setData('file', null);
            },
        });
    };

    const handleClose = () => {
        if (form.processing) return;
        setSummary(null);
        form.reset();
        onClose();
    };

    const downloadTemplate = () => {
        window.location.href = route('customers.vip.import.template');
    };

    return (
        <StandardModal
            show={show}
            onClose={handleClose}
            title="Importar lista VIP"
            subtitle="Aplica como curadoria manual em lote"
            headerColor="bg-purple-600"
            headerIcon={<ArrowUpTrayIcon className="w-6 h-6" />}
            maxWidth="3xl"
            footer={
                summary ? (
                    <StandardModal.Footer
                        onCancel={handleClose}
                        cancelLabel="Fechar"
                    />
                ) : (
                    <StandardModal.Footer
                        onCancel={handleClose}
                        onSubmit={handleSubmit}
                        submitLabel="Importar"
                        processing={form.processing}
                        submitDisabled={!form.data.file}
                    />
                )
            }
        >
            {summary ? (
                <ImportSummary summary={summary} onReset={() => { setSummary(null); form.reset(); }} />
            ) : (
                <ImportForm form={form} onDownloadTemplate={downloadTemplate} />
            )}
        </StandardModal>
    );
}

// ----------------------------------------------------------------------
// Form
// ----------------------------------------------------------------------
function ImportForm({ form, onDownloadTemplate }) {
    return (
        <>
            <StandardModal.Section title="Como funciona">
                <div className="rounded-md bg-indigo-50 border border-indigo-100 p-3 flex gap-2">
                    <InformationCircleIcon className="w-5 h-5 text-indigo-600 shrink-0 mt-0.5" />
                    <div className="text-xs text-indigo-900 space-y-1">
                        <p>
                            Arquivo <strong>XLSX</strong> com 3 colunas obrigatórias:
                        </p>
                        <ul className="list-disc list-inside ml-2 space-y-0.5">
                            <li><strong>cpf</strong> — 11 dígitos (pontuação opcional)</li>
                            <li><strong>ano</strong> — ano da Lista VIP (ex: 2026)</li>
                            <li><strong>status</strong> — Black ou Gold</li>
                        </ul>
                        <p className="mt-2">
                            Cada linha vira uma curadoria manual (`source = manual`). Snapshots
                            de faturamento existentes são preservados; rodadas auto subsequentes
                            <strong> não sobrescrevem</strong> a curadoria importada.
                        </p>
                        <p className="text-indigo-700">
                            Linhas com CPF não encontrado, status inválido ou ano fora do range
                            (2020–2100) são reportadas no relatório final — o batch não falha.
                        </p>
                    </div>
                </div>
                <div className="mt-3">
                    <button
                        type="button"
                        onClick={onDownloadTemplate}
                        className="inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:text-indigo-800 hover:underline"
                    >
                        <DocumentArrowDownIcon className="w-4 h-4" />
                        Baixar modelo XLSX
                    </button>
                </div>
            </StandardModal.Section>

            <StandardModal.Section title="Arquivo">
                <input
                    type="file"
                    accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                    onChange={(e) => form.setData('file', e.target.files[0] || null)}
                    className="block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                />
                {form.errors.file && (
                    <p className="text-red-600 text-xs mt-1">{form.errors.file}</p>
                )}
                {form.data.file && (
                    <p className="mt-2 text-xs text-gray-600">
                        Selecionado: <strong>{form.data.file.name}</strong>
                        ({Math.round(form.data.file.size / 1024)} KB)
                    </p>
                )}
            </StandardModal.Section>

            <StandardModal.Section title="Opções">
                <label className="flex items-start gap-3 cursor-pointer">
                    <input
                        type="checkbox"
                        checked={form.data.replace_year}
                        onChange={(e) => form.setData('replace_year', e.target.checked)}
                        className="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <div className="text-sm">
                        <div className="font-medium text-gray-900">
                            Substituir lista dos anos do arquivo
                        </div>
                        <div className="text-xs text-gray-600 mt-0.5">
                            Para cada ano presente no arquivo, remove os clientes da lista VIP
                            que <strong>não estão</strong> no arquivo (incluindo curadorias
                            manuais prévias). Use com cuidado — operação destrutiva.
                        </div>
                    </div>
                </label>
            </StandardModal.Section>
        </>
    );
}

// ----------------------------------------------------------------------
// Summary
// ----------------------------------------------------------------------
function ImportSummary({ summary, onReset }) {
    const hasErrors = summary.errors.length > 0;
    const hasWarnings = summary.warnings.length > 0;

    return (
        <>
            <StandardModal.Section title="Resultado">
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <SummaryCard label="Criadas" value={summary.imported} color="success" />
                    <SummaryCard label="Atualizadas" value={summary.updated} color="info" />
                    <SummaryCard label="Removidas" value={summary.total_removed} color="warning" />
                    <SummaryCard label="Com erro" value={summary.errors.length} color={hasErrors ? 'danger' : 'gray'} />
                </div>

                {summary.replaced_years.length > 0 && (
                    <p className="mt-3 text-xs text-gray-600">
                        Listas substituídas: {summary.replaced_years.join(', ')}
                    </p>
                )}
            </StandardModal.Section>

            {hasErrors && (
                <StandardModal.Section title={`Erros (${summary.errors.length})`}>
                    <div className="rounded-md bg-red-50 border border-red-200 p-3 flex gap-2 mb-3">
                        <ExclamationTriangleIcon className="w-5 h-5 text-red-600 shrink-0 mt-0.5" />
                        <p className="text-xs text-red-900">
                            Linhas listadas abaixo <strong>não foram importadas</strong>. Corrija
                            o arquivo (ou cadastre os clientes faltantes no CIGAM) e reimporte.
                        </p>
                    </div>
                    <div className="max-h-72 overflow-y-auto border rounded-md">
                        <table className="min-w-full divide-y divide-gray-200 text-sm">
                            <thead className="bg-gray-50 sticky top-0">
                                <tr>
                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Linha</th>
                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">CPF</th>
                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Erro</th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {summary.errors.map((err, i) => (
                                    <tr key={i}>
                                        <td className="px-3 py-2 font-mono text-xs">{err.line}</td>
                                        <td className="px-3 py-2 font-mono text-xs">{err.cpf || '—'}</td>
                                        <td className="px-3 py-2 text-xs text-red-700">{err.message}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </StandardModal.Section>
            )}

            {hasWarnings && (
                <StandardModal.Section title={`Avisos (${summary.warnings.length})`}>
                    <ul className="text-xs text-amber-700 space-y-1">
                        {summary.warnings.slice(0, 50).map((w, i) => (
                            <li key={i}>• {w}</li>
                        ))}
                    </ul>
                </StandardModal.Section>
            )}

            <StandardModal.Section title="Próximos passos">
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" onClick={onReset}>
                        Importar outro arquivo
                    </Button>
                    <Button variant="primary" onClick={() => router.reload({ preserveScroll: true })}>
                        Atualizar listagem
                    </Button>
                </div>
            </StandardModal.Section>
        </>
    );
}

function SummaryCard({ label, value, color }) {
    const COLOR_CLASSES = {
        success: 'bg-emerald-50 border-emerald-200 text-emerald-900',
        info: 'bg-blue-50 border-blue-200 text-blue-900',
        warning: 'bg-amber-50 border-amber-200 text-amber-900',
        danger: 'bg-red-50 border-red-200 text-red-900',
        gray: 'bg-gray-50 border-gray-200 text-gray-700',
    };
    return (
        <div className={`rounded-md border p-3 ${COLOR_CLASSES[color] || COLOR_CLASSES.gray}`}>
            <div className="text-xs uppercase font-medium opacity-75">{label}</div>
            <div className="text-2xl font-bold tabular-nums mt-1">{value}</div>
        </div>
    );
}
