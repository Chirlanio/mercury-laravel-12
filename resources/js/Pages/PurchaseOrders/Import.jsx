import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState, useRef } from 'react';
import {
    ArrowLeftIcon, ArrowUpTrayIcon, CheckCircleIcon, XCircleIcon,
    DocumentArrowUpIcon, ExclamationTriangleIcon, InformationCircleIcon,
} from '@heroicons/react/24/outline';
import Button from '@/Components/Button';
import EmptyState from '@/Components/Shared/EmptyState';
import InputLabel from '@/Components/InputLabel';

export default function Import({ suppliers = [] }) {
    const { flash, importStats } = usePage().props;
    const fileInputRef = useRef(null);
    const [file, setFile] = useState(null);
    const [defaultSupplierId, setDefaultSupplierId] = useState('');
    const [preview, setPreview] = useState(null);
    const [previewing, setPreviewing] = useState(false);
    const [importing, setImporting] = useState(false);
    const [error, setError] = useState(null);

    const handleFileChange = (e) => {
        const f = e.target.files?.[0];
        if (!f) return;
        setFile(f);
        setPreview(null);
        setError(null);

        // Auto-preview
        const formData = new FormData();
        formData.append('file', f);
        setPreviewing(true);
        fetch(route('purchase-orders.import.preview'), {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
            },
        })
            .then((r) => r.ok ? r.json() : Promise.reject(r))
            .then((data) => { setPreview(data); setPreviewing(false); })
            .catch(() => { setError('Falha ao ler a planilha. Verifique o formato.'); setPreviewing(false); });
    };

    const handleConfirmImport = () => {
        if (!file || !defaultSupplierId) return;
        setImporting(true);
        const formData = new FormData();
        formData.append('file', file);
        formData.append('default_supplier_id', defaultSupplierId);
        router.post(route('purchase-orders.import.store'), formData, {
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

    const canSubmit = file && defaultSupplierId && preview && preview.rows?.length > 0;

    return (
        <>
            <Head title="Importar Ordens de Compra" />

            <div className="py-12">
                <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6 flex items-center gap-3">
                        <Link href={route('purchase-orders.index')}>
                            <Button variant="outline" size="sm" icon={ArrowLeftIcon}>Voltar</Button>
                        </Link>
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">Importar Ordens de Compra</h1>
                            <p className="text-sm text-gray-600">Upload de planilha XLSX ou CSV no formato Mercury v1</p>
                        </div>
                    </div>

                    {/* Resultado da importação anterior */}
                    {importStats && (
                        <div className="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                            <div className="flex items-start">
                                <CheckCircleIcon className="h-5 w-5 text-green-600 mr-2 flex-shrink-0 mt-0.5" />
                                <div className="flex-1">
                                    <h3 className="text-sm font-medium text-green-900">Importação concluída</h3>
                                    <div className="mt-2 grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                                        <div>
                                            <span className="text-green-700">Ordens criadas:</span>
                                            <span className="ml-2 font-bold text-green-900">{importStats.orders_created}</span>
                                        </div>
                                        <div>
                                            <span className="text-green-700">Ordens atualizadas:</span>
                                            <span className="ml-2 font-bold text-green-900">{importStats.orders_updated}</span>
                                        </div>
                                        <div>
                                            <span className="text-green-700">Itens criados:</span>
                                            <span className="ml-2 font-bold text-green-900">{importStats.items_created}</span>
                                        </div>
                                        <div>
                                            <span className="text-green-700">Itens atualizados:</span>
                                            <span className="ml-2 font-bold text-green-900">{importStats.items_updated}</span>
                                        </div>
                                    </div>
                                    {importStats.rows_rejected > 0 && (
                                        <div className="mt-3 bg-yellow-50 border border-yellow-200 rounded p-2">
                                            <div className="flex items-center text-yellow-800">
                                                <ExclamationTriangleIcon className="h-4 w-4 mr-1" />
                                                <span className="text-sm font-medium">{importStats.rows_rejected} linha(s) rejeitada(s)</span>
                                            </div>
                                            {importStats.rejected?.length > 0 && (
                                                <ul className="mt-2 text-xs text-yellow-700 space-y-1 max-h-32 overflow-y-auto">
                                                    {importStats.rejected.slice(0, 10).map((r, i) => (
                                                        <li key={i}>Linha {r.row_number}: {r.reason}</li>
                                                    ))}
                                                    {importStats.rejected.length > 10 && (
                                                        <li className="italic">...e mais {importStats.rejected.length - 10}</li>
                                                    )}
                                                </ul>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Fornecedor padrão + Upload area */}
                    <div className="bg-white shadow-sm rounded-lg p-6 mb-6">
                        <div className="mb-4">
                            <InputLabel value="Fornecedor *" />
                            <select
                                value={defaultSupplierId}
                                onChange={(e) => setDefaultSupplierId(e.target.value)}
                                className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                required
                            >
                                <option value="">Selecione o fornecedor...</option>
                                {suppliers.map((s) => (
                                    <option key={s.id} value={s.id}>{s.nome_fantasia}</option>
                                ))}
                            </select>
                            <p className="mt-1 text-xs text-gray-500">
                                A planilha v1 não contém fornecedor — todas as ordens importadas serão vinculadas ao fornecedor selecionado.
                            </p>
                        </div>

                        <div className="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center">
                            <DocumentArrowUpIcon className="mx-auto h-12 w-12 text-gray-400" />
                            <div className="mt-3">
                                <label htmlFor="file-upload" className="cursor-pointer">
                                    <span className="text-indigo-600 hover:text-indigo-700 font-medium">Selecione uma planilha</span>
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

                    {/* Preview */}
                    {previewing && (
                        <div className="bg-white shadow-sm rounded-lg p-6 mb-6 text-center text-gray-500">
                            Lendo planilha...
                        </div>
                    )}

                    {preview && (
                        <div className="bg-white shadow-sm rounded-lg p-6 mb-6">
                            <div className="flex justify-between items-center mb-4">
                                <div>
                                    <h2 className="text-lg font-medium text-gray-900">
                                        Preview ({preview.total} linha{preview.total !== 1 ? 's' : ''} no total)
                                    </h2>
                                    {preview.size_columns?.length > 0 && (
                                        <p className="text-xs text-gray-500 mt-1">
                                            {preview.size_columns.length} coluna(s) de tamanho detectada(s):
                                            <span className="ml-1 font-mono">{preview.size_columns.join(', ')}</span>
                                        </p>
                                    )}
                                </div>
                                <Button
                                    variant="primary"
                                    onClick={handleConfirmImport}
                                    disabled={importing || !canSubmit}
                                    icon={ArrowUpTrayIcon}
                                >
                                    {importing ? 'Importando...' : 'Confirmar Importação'}
                                </Button>
                            </div>

                            {preview.missing_columns?.length > 0 && (
                                <div className="mb-4 bg-yellow-50 border border-yellow-200 rounded p-3 text-sm text-yellow-800">
                                    <div className="flex items-start">
                                        <ExclamationTriangleIcon className="h-4 w-4 mr-2 flex-shrink-0 mt-0.5" />
                                        <div>
                                            <strong>Colunas obrigatórias ausentes:</strong> {preview.missing_columns.join(', ')}
                                            <div className="text-xs mt-1">A importação pode falhar ou rejeitar linhas. Verifique o header da planilha.</div>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {!defaultSupplierId && (
                                <div className="mb-4 bg-blue-50 border border-blue-200 rounded p-3 text-sm text-blue-800">
                                    <div className="flex items-center">
                                        <InformationCircleIcon className="h-4 w-4 mr-2" />
                                        Selecione um fornecedor padrão acima antes de confirmar a importação.
                                    </div>
                                </div>
                            )}

                            {preview.rows.length === 0 ? (
                                <EmptyState
                                    icon={DocumentArrowUpIcon}
                                    title="Planilha vazia"
                                    description="Nenhuma linha encontrada — verifique se há header e dados."
                                    compact
                                />
                            ) : (
                                <div className="overflow-x-auto">
                                    <table className="min-w-full text-xs">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                {Object.keys(preview.rows[0] || {}).map((col) => (
                                                    <th key={col} className="px-2 py-2 text-left font-medium text-gray-500 uppercase whitespace-nowrap">
                                                        {col}
                                                    </th>
                                                ))}
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {preview.rows.map((row, i) => (
                                                <tr key={i}>
                                                    {Object.values(row).map((val, j) => (
                                                        <td key={j} className="px-2 py-1 text-gray-700 whitespace-nowrap">
                                                            {val !== null && val !== undefined ? String(val) : ''}
                                                        </td>
                                                    ))}
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                    {preview.total > preview.rows.length && (
                                        <p className="mt-2 text-xs text-gray-500 text-center italic">
                                            Mostrando {preview.rows.length} de {preview.total} linhas
                                        </p>
                                    )}
                                </div>
                            )}
                        </div>
                    )}

                    {/* Format guide — planilha v1 Mercury */}
                    <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-900">
                        <h3 className="font-medium mb-2">Formato esperado — planilha v1 Mercury</h3>
                        <p className="mb-2">
                            Cada <strong>linha</strong> representa 1 referência × N tamanhos (matriz horizontal).
                            Múltiplas linhas com o mesmo <code className="bg-blue-100 px-1 rounded">Nr Pedido</code> são agrupadas em uma ordem única.
                        </p>
                        <div className="text-xs space-y-2">
                            <div>
                                <strong>Colunas fixas:</strong> Referência, Descrição, Material, Cor, Tipo, Grupo, Subgrupo,
                                Marca, Estação, Coleção, Custo Unit, Preço Venda, Precif, Qtd Pedido, Custo total, Venda total,
                                <strong className="text-blue-800"> Nr Pedido</strong>, Status, <strong className="text-blue-800">Destino</strong>,
                                Dt Pedido, Previsão, Pagamento, Nota fiscal, Emissão Nf, Confirmação
                            </div>
                            <div>
                                <strong>Colunas de tamanho (qualquer nome não-fixo):</strong>
                                PP, P, M, G, GG · 01 · 33–40 · 33/34, 35/36 · 33.5–39.5 · 70–105 · etc.
                                Cada célula contém a quantidade para aquele tamanho.
                            </div>
                            <ul className="ml-4 list-disc space-y-0.5 text-[11px]">
                                <li><strong>Destino:</strong> código da loja (ex: Z424) ou nome completo (ex: "CD MEIA SOLA")</li>
                                <li><strong>Status:</strong> PENDENTE, FATURADO, FATURADO PARCIAL, CANCELADO, ENTREGUE — mais frequente do grupo define o status da ordem</li>
                                <li><strong>Datas:</strong> formato dd/mm/yyyy ou serial Excel</li>
                                <li><strong>Valores:</strong> aceita "172,90" (BR) ou "172.90"</li>
                                <li><strong>Fornecedor:</strong> não vem na planilha — definido pelo select acima para todas as ordens da importação</li>
                                <li><strong>Re-importar:</strong> ordens pendentes têm cabeçalho atualizado; itens são upserted por (referência, tamanho)</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
