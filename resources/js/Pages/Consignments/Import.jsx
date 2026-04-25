import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState, useRef, useEffect } from 'react';
import {
    ArrowUpTrayIcon,
    CheckCircleIcon,
    XCircleIcon,
    DocumentArrowUpIcon,
    ExclamationTriangleIcon,
    ClockIcon,
    QuestionMarkCircleIcon,
} from '@heroicons/react/24/outline';
import Button from '@/Components/Button';
import EmptyState from '@/Components/Shared/EmptyState';
import LoadingSpinner from '@/Components/Shared/LoadingSpinner';
import PageHeader from '@/Components/Shared/PageHeader';

/**
 * Import de consignações — migração v1 → v2 via planilha XLSX/CSV.
 *
 * Formato: UMA LINHA POR ITEM com cabeçalho da consignação denormalizado
 * (colunas de destinatário/loja/NF repetidas). O backend agrupa por
 * (documento + loja + NF saída) e resolve produto via referência/tamanho.
 */
export default function Import() {
    const { flash } = usePage().props;
    const importResult = flash?.import_result ?? null;
    const fileInputRef = useRef(null);
    const [file, setFile] = useState(null);
    const [preview, setPreview] = useState(null);
    const [previewing, setPreviewing] = useState(false);
    const [importing, setImporting] = useState(false);
    const [importSeconds, setImportSeconds] = useState(0);
    const [error, setError] = useState(null);

    useEffect(() => {
        if (!importing) {
            setImportSeconds(0);
            return;
        }
        const interval = setInterval(() => setImportSeconds((s) => s + 1), 1000);
        return () => clearInterval(interval);
    }, [importing]);

    const handleFileChange = (e) => {
        const f = e.target.files?.[0];
        if (!f) return;
        setFile(f);
        setPreview(null);
        setError(null);

        const formData = new FormData();
        formData.append('file', f);
        setPreviewing(true);

        fetch(route('consignments.import.preview'), {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then(async (r) => {
                if (r.status === 419 || r.status === 401) {
                    throw new Error('Sessão expirou — recarregue a página (F5) e faça login novamente.');
                }
                if (r.status === 422) {
                    const body = await r.json().catch(() => null);
                    const fileErrors = body?.errors?.file || [];
                    throw new Error(fileErrors.length ? fileErrors.join(' ') : (body?.message || 'Arquivo inválido.'));
                }
                if (!r.ok) {
                    throw new Error(`Erro ${r.status} ao ler a planilha.`);
                }
                return r.json();
            })
            .then((data) => {
                setPreview(data);
                setPreviewing(false);
            })
            .catch((err) => {
                setError(err.message || 'Falha ao ler a planilha.');
                setPreviewing(false);
            });
    };

    const handleConfirm = () => {
        if (!file) return;
        setImporting(true);
        const formData = new FormData();
        formData.append('file', file);
        router.post(route('consignments.import.store'), formData, {
            forceFormData: true,
            onFinish: () => setImporting(false),
            onSuccess: () => {
                setFile(null);
                setPreview(null);
                if (fileInputRef.current) fileInputRef.current.value = '';
            },
        });
    };

    const reset = () => {
        setFile(null);
        setPreview(null);
        setError(null);
        if (fileInputRef.current) fileInputRef.current.value = '';
    };

    const formatDuration = (s) => {
        const m = Math.floor(s / 60);
        const r = s % 60;
        return m > 0 ? `${m}min ${r}s` : `${r}s`;
    };

    const canImport = preview && preview.valid_groups > 0;

    return (
        <>
            <Head title="Importar Consignações" />

            {importing && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 backdrop-blur-sm">
                    <div className="bg-white rounded-2xl shadow-2xl p-8 max-w-md mx-4 text-center">
                        <LoadingSpinner size="xl" />
                        <h2 className="mt-4 text-lg font-bold text-gray-900">Importando consignações…</h2>
                        <p className="mt-2 text-sm text-gray-600">
                            {preview?.valid_groups
                                ? `Processando ${preview.valid_groups} consignação(ões)`
                                : 'Processando planilha'}
                        </p>
                        <div className="mt-4 flex items-center justify-center gap-2 text-gray-500">
                            <ClockIcon className="h-4 w-4" />
                            <span className="font-mono text-sm">{formatDuration(importSeconds)}</span>
                        </div>
                        <div className="mt-4 w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                            <div
                                className="bg-emerald-500 h-2 rounded-full transition-all duration-1000 ease-out"
                                style={{ width: `${Math.min(95, importSeconds * 3)}%` }}
                            />
                        </div>
                    </div>
                </div>
            )}

            <div className="py-6 sm:py-12">
                <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                    <PageHeader
                        title="Importar Consignações"
                        subtitle="Migração de dados históricos da v1 via planilha XLSX/CSV."
                        actions={[
                            { type: 'back', href: route('consignments.index') },
                        ]}
                    />

                    {importResult && (
                        <div className="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                            <div className="flex items-start gap-2">
                                <CheckCircleIcon className="h-5 w-5 text-green-600 mt-0.5" />
                                <div className="flex-1">
                                    <h3 className="text-sm font-medium text-green-900">Importação concluída</h3>
                                    <div className="mt-2 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3 text-sm">
                                        <Metric label="Criadas" value={importResult.created} color="green" />
                                        <Metric label="Atualizadas" value={importResult.updated} color="blue" />
                                        <Metric label="Ignoradas" value={importResult.skipped} color="gray" />
                                        <Metric label="Itens criados" value={importResult.items_created} color="teal" />
                                        <Metric label="Órfãos" value={importResult.orphan_items} color={importResult.orphan_items > 0 ? 'amber' : 'gray'} />
                                    </div>
                                    {importResult.errors?.length > 0 && (
                                        <details className="mt-3">
                                            <summary className="cursor-pointer text-xs text-green-800 hover:underline">
                                                Ver {importResult.errors.length} linha(s) com erro
                                            </summary>
                                            <ul className="mt-2 text-xs text-red-700 space-y-0.5 max-h-48 overflow-y-auto">
                                                {importResult.errors.map((e, i) => (
                                                    <li key={i} className="font-mono">
                                                        <strong>L{e.row}:</strong> {e.messages.join('; ')}
                                                    </li>
                                                ))}
                                            </ul>
                                        </details>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Upload */}
                    <div className="bg-white shadow-sm rounded-lg p-6 mb-6">
                        <div className="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center">
                            <DocumentArrowUpIcon className="mx-auto h-12 w-12 text-gray-400" />
                            <div className="mt-3">
                                <label htmlFor="file-upload" className="cursor-pointer">
                                    <span className="text-emerald-600 hover:text-emerald-700 font-medium">Selecione uma planilha</span>
                                    <input
                                        id="file-upload"
                                        ref={fileInputRef}
                                        type="file"
                                        accept=".xlsx,.xls,.csv"
                                        onChange={handleFileChange}
                                        className="sr-only"
                                    />
                                </label>
                                <p className="text-sm text-gray-500 mt-1">XLSX, XLS ou CSV — máximo 10 MB</p>
                            </div>
                            {file && (
                                <div className="mt-4 inline-flex items-center bg-gray-100 rounded-full px-3 py-1 text-sm text-gray-700">
                                    {file.name}
                                    <button onClick={reset} className="ml-2 text-gray-500 hover:text-gray-700">
                                        <XCircleIcon className="h-4 w-4" />
                                    </button>
                                </div>
                            )}
                        </div>

                        {error && (
                            <div className="mt-4 bg-red-50 border border-red-200 rounded p-3 text-sm text-red-800">
                                {error}
                            </div>
                        )}
                    </div>

                    {previewing && (
                        <div className="bg-white shadow-sm rounded-lg p-6 mb-6 text-center">
                            <LoadingSpinner size="md" label="Lendo planilha…" />
                        </div>
                    )}

                    {preview && (
                        <div className="bg-white shadow-sm rounded-lg p-4 sm:p-6 mb-6">
                            <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h2 className="text-lg font-medium text-gray-900">Pré-visualização</h2>
                                    <div className="mt-1 flex flex-wrap gap-3 text-sm">
                                        <span className="text-green-700">
                                            <strong>{preview.valid_groups}</strong> consignação(ões) válidas
                                        </span>
                                        {preview.invalid_groups > 0 && (
                                            <span className="text-red-700">
                                                <strong>{preview.invalid_groups}</strong> inválidas
                                            </span>
                                        )}
                                        {preview.orphans?.length > 0 && (
                                            <span className="text-amber-700">
                                                <strong>{preview.orphans.length}</strong> item(ns) órfão(s)
                                            </span>
                                        )}
                                    </div>
                                </div>
                                <Button
                                    variant="primary"
                                    onClick={handleConfirm}
                                    disabled={importing || !canImport}
                                    icon={ArrowUpTrayIcon}
                                    className="min-h-[44px] w-full sm:w-auto"
                                >
                                    Confirmar importação
                                </Button>
                            </div>

                            {preview.errors?.length > 0 && (
                                <div className="mb-4 bg-red-50 border border-red-200 rounded p-3">
                                    <div className="flex items-start gap-2">
                                        <XCircleIcon className="h-5 w-5 text-red-600 shrink-0 mt-0.5" />
                                        <div className="flex-1 text-sm text-red-900">
                                            <div className="font-medium mb-1">{preview.errors.length} linha(s) com erro:</div>
                                            <ul className="text-xs space-y-0.5 max-h-40 overflow-y-auto font-mono">
                                                {preview.errors.slice(0, 20).map((e, i) => (
                                                    <li key={i}><strong>L{e.row}:</strong> {e.messages.join('; ')}</li>
                                                ))}
                                                {preview.errors.length > 20 && (
                                                    <li className="italic text-red-700">… +{preview.errors.length - 20} outros</li>
                                                )}
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {preview.orphans?.length > 0 && (
                                <div className="mb-4 bg-amber-50 border border-amber-200 rounded p-3">
                                    <div className="flex items-start gap-2">
                                        <ExclamationTriangleIcon className="h-5 w-5 text-amber-600 shrink-0 mt-0.5" />
                                        <div className="flex-1 text-sm text-amber-900">
                                            <div className="font-medium mb-1">
                                                {preview.orphans.length} item(ns) órfão(s) — produto não encontrado no catálogo
                                            </div>
                                            <div className="text-xs mb-2">
                                                Esses itens serão <strong>ignorados</strong> na importação. Cadastre os produtos antes
                                                (ou atualize a planilha) se quiser incluí-los.
                                            </div>
                                            <details>
                                                <summary className="cursor-pointer text-xs hover:underline">
                                                    Ver referências órfãs
                                                </summary>
                                                <ul className="mt-2 text-xs space-y-0.5 max-h-40 overflow-y-auto font-mono">
                                                    {preview.orphans.slice(0, 50).map((o, i) => (
                                                        <li key={i}>
                                                            <strong>L{o.row}:</strong> {o.reference}{o.size ? ` (Tam. ${o.size})` : ''}
                                                        </li>
                                                    ))}
                                                    {preview.orphans.length > 50 && (
                                                        <li className="italic">… +{preview.orphans.length - 50} outros</li>
                                                    )}
                                                </ul>
                                            </details>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {preview.groups?.length === 0 ? (
                                <EmptyState
                                    icon={DocumentArrowUpIcon}
                                    title="Nenhuma consignação válida"
                                    description="Todas as linhas apresentaram erro. Corrija a planilha e tente novamente."
                                    compact
                                />
                            ) : (
                                <div className="overflow-x-auto">
                                    <h3 className="text-sm font-medium text-gray-900 mb-2">
                                        Amostra ({preview.groups.length} de {preview.valid_groups})
                                    </h3>
                                    <table className="min-w-full text-xs">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-2 py-2 text-left font-medium text-gray-500 uppercase">Tipo</th>
                                                <th className="px-2 py-2 text-left font-medium text-gray-500 uppercase">Destinatário</th>
                                                <th className="px-2 py-2 text-left font-medium text-gray-500 uppercase">Loja</th>
                                                <th className="px-2 py-2 text-left font-medium text-gray-500 uppercase">NF saída</th>
                                                <th className="px-2 py-2 text-left font-medium text-gray-500 uppercase">Data</th>
                                                <th className="px-2 py-2 text-left font-medium text-gray-500 uppercase">Status</th>
                                                <th className="px-2 py-2 text-right font-medium text-gray-500 uppercase">Itens</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {preview.groups.map((g, i) => (
                                                <tr key={i}>
                                                    <td className="px-2 py-1 capitalize">{g.type?.value ?? g.type}</td>
                                                    <td className="px-2 py-1">{g.recipient_name}</td>
                                                    <td className="px-2 py-1 font-mono">{g.outbound_store_code}</td>
                                                    <td className="px-2 py-1 font-mono">{g.outbound_invoice_number}</td>
                                                    <td className="px-2 py-1">{g.outbound_invoice_date}</td>
                                                    <td className="px-2 py-1 capitalize">{g.status?.value ?? g.status}</td>
                                                    <td className="px-2 py-1 text-right">{g.items?.length ?? 0}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Format guide */}
                    <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-900">
                        <div className="flex items-start gap-2">
                            <QuestionMarkCircleIcon className="h-5 w-5 shrink-0 mt-0.5" />
                            <div>
                                <h3 className="font-medium mb-2">Formato esperado</h3>
                                <p className="text-xs mb-2">
                                    <strong>Uma linha por item</strong>. Colunas de cabeçalho (destinatário, loja, NF saída)
                                    se repetem. O sistema agrupa por <code className="bg-blue-100 px-1 rounded">documento + loja + NF saída</code>.
                                </p>
                                <div className="text-xs space-y-1">
                                    <div><strong>Destinatário/NF:</strong> Tipo, CPF/CNPJ, Nome, Telefone, Email, Loja (código), NF Saída, Data, Prazo dias, Status, Obs</div>
                                    <div><strong>Consultor</strong> (tipo Cliente): Matricula ou Nome</div>
                                    <div><strong>Item:</strong> Referência, Tamanho, Quantidade, Valor Unit</div>
                                    <div className="pt-1 text-blue-800">
                                        <strong>Tipos:</strong> cliente / influencer / ecommerce —
                                        {' '}<strong>Status:</strong> pendente / finalizada / cancelada —
                                        {' '}<strong>Datas:</strong> dd/mm/yyyy ou serial Excel
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

function Metric({ label, value, color }) {
    const colorMap = {
        green: 'text-green-900',
        blue: 'text-blue-900',
        gray: 'text-gray-900',
        teal: 'text-teal-900',
        amber: 'text-amber-900',
    };
    return (
        <div>
            <div className="text-xs text-gray-600">{label}</div>
            <div className={`text-lg font-bold ${colorMap[color] || colorMap.gray}`}>{value}</div>
        </div>
    );
}
