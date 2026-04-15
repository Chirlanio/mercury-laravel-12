import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState, useRef } from 'react';
import {
    ArrowLeftIcon, ArrowUpTrayIcon, CheckCircleIcon, XCircleIcon,
    DocumentArrowUpIcon, ExclamationTriangleIcon, TagIcon, ScaleIcon,
} from '@heroicons/react/24/outline';
import Button from '@/Components/Button';
import EmptyState from '@/Components/Shared/EmptyState';

export default function Import() {
    const { importStats } = usePage().props;
    const fileInputRef = useRef(null);
    const [file, setFile] = useState(null);
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
        if (!file) return;
        setImporting(true);
        const formData = new FormData();
        formData.append('file', file);
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

    const unknownBrands = preview?.brands_detected?.filter((b) => !b.is_known) || [];
    const knownBrands = preview?.brands_detected?.filter((b) => b.is_known) || [];
    const pendingSizes = preview?.sizes_pending || [];
    const missingColumns = preview?.missing_columns || [];
    const canImport = preview?.can_import || false;

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
                                        <div><span className="text-green-700">Ordens criadas:</span> <span className="ml-1 font-bold text-green-900">{importStats.orders_created}</span></div>
                                        <div><span className="text-green-700">Ordens atualizadas:</span> <span className="ml-1 font-bold text-green-900">{importStats.orders_updated}</span></div>
                                        <div><span className="text-green-700">Itens criados:</span> <span className="ml-1 font-bold text-green-900">{importStats.items_created}</span></div>
                                        <div><span className="text-green-700">Itens atualizados:</span> <span className="ml-1 font-bold text-green-900">{importStats.items_updated}</span></div>
                                    </div>
                                    {(importStats.rows_rejected > 0 || importStats.items_rejected > 0) && (
                                        <div className="mt-3 bg-yellow-50 border border-yellow-200 rounded p-2">
                                            <div className="flex items-center text-yellow-800">
                                                <ExclamationTriangleIcon className="h-4 w-4 mr-1" />
                                                <span className="text-sm font-medium">
                                                    {importStats.rows_rejected} linha(s) + {importStats.items_rejected} item(ns) rejeitado(s)
                                                </span>
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

                    {/* Upload area */}
                    <div className="bg-white shadow-sm rounded-lg p-6 mb-6">
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

                    {previewing && (
                        <div className="bg-white shadow-sm rounded-lg p-6 mb-6 text-center text-gray-500">
                            Lendo planilha...
                        </div>
                    )}

                    {preview && (
                        <>
                            {/* Pendências bloqueantes */}
                            {missingColumns.length > 0 && (
                                <div className="mb-4 bg-red-50 border border-red-200 rounded-lg p-4">
                                    <div className="flex items-start">
                                        <XCircleIcon className="h-5 w-5 text-red-600 mr-2 flex-shrink-0 mt-0.5" />
                                        <div className="flex-1">
                                            <h3 className="text-sm font-medium text-red-900">Colunas obrigatórias ausentes</h3>
                                            <p className="text-xs text-red-700 mt-1">
                                                {missingColumns.join(', ')}
                                            </p>
                                            <p className="text-xs text-red-600 mt-2">
                                                Verifique o header da planilha — é necessário ter todas as colunas obrigatórias.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {unknownBrands.length > 0 && (
                                <div className="mb-4 bg-red-50 border border-red-200 rounded-lg p-4">
                                    <div className="flex items-start">
                                        <TagIcon className="h-5 w-5 text-red-600 mr-2 flex-shrink-0 mt-0.5" />
                                        <div className="flex-1">
                                            <h3 className="text-sm font-medium text-red-900">
                                                {unknownBrands.length} marca(s) não cadastrada(s) no catálogo CIGAM
                                            </h3>
                                            <div className="mt-2 flex flex-wrap gap-1">
                                                {unknownBrands.map((b, i) => (
                                                    <span key={i} className="inline-flex items-center bg-red-100 text-red-800 text-xs px-2 py-0.5 rounded">
                                                        {b.name}
                                                    </span>
                                                ))}
                                            </div>
                                            <p className="text-xs text-red-700 mt-3">
                                                O catálogo de marcas (<code className="bg-red-100 px-1 rounded">product_brands</code>) é sincronizado do CIGAM.
                                                Execute o sync ou cadastre essas marcas no ERP antes de importar. Ordens com marca desconhecida são <strong>rejeitadas</strong>.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {pendingSizes.length > 0 && (
                                <div className="mb-4 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                    <div className="flex items-start">
                                        <ScaleIcon className="h-5 w-5 text-yellow-600 mr-2 flex-shrink-0 mt-0.5" />
                                        <div className="flex-1">
                                            <div className="flex justify-between items-start">
                                                <h3 className="text-sm font-medium text-yellow-900">
                                                    {pendingSizes.length} tamanho(s) sem mapeamento
                                                </h3>
                                                <Link
                                                    href={route('purchase-orders.size-mappings.index')}
                                                    className="text-xs text-yellow-700 hover:text-yellow-900 underline"
                                                >
                                                    Configurar agora →
                                                </Link>
                                            </div>
                                            <div className="mt-2 flex flex-wrap gap-1">
                                                {pendingSizes.map((s, i) => (
                                                    <span key={i} className="inline-flex items-center bg-yellow-100 text-yellow-800 text-xs px-2 py-0.5 rounded font-mono">
                                                        {s}
                                                    </span>
                                                ))}
                                            </div>
                                            <p className="text-xs text-yellow-700 mt-3">
                                                Tamanhos duplos (33/34, 35/36) e outros labels não existentes no catálogo CIGAM precisam de um mapeamento manual pro tamanho oficial correspondente.
                                                <strong> Itens</strong> com tamanho pendente serão rejeitados individualmente — o resto da importação prossegue.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* Marcas reconhecidas */}
                            {knownBrands.length > 0 && unknownBrands.length === 0 && (
                                <div className="mb-4 bg-green-50 border border-green-200 rounded-lg p-4 text-sm text-green-900">
                                    <div className="flex items-center">
                                        <CheckCircleIcon className="h-4 w-4 mr-2" />
                                        <span className="font-medium">
                                            {knownBrands.length} marca(s) reconhecida(s):
                                        </span>
                                        <span className="ml-2 text-xs">
                                            {knownBrands.slice(0, 8).map((b) => b.name).join(', ')}
                                            {knownBrands.length > 8 && ` + ${knownBrands.length - 8} outras`}
                                        </span>
                                    </div>
                                </div>
                            )}

                            {/* Preview table */}
                            <div className="bg-white shadow-sm rounded-lg p-6 mb-6">
                                <div className="flex justify-between items-center mb-4">
                                    <div>
                                        <h2 className="text-lg font-medium text-gray-900">
                                            Preview ({preview.total} linha{preview.total !== 1 ? 's' : ''})
                                        </h2>
                                        {preview.size_columns?.length > 0 && (
                                            <p className="text-xs text-gray-500 mt-1">
                                                {preview.size_columns.length} colunas de tamanho detectadas
                                            </p>
                                        )}
                                    </div>
                                    <Button
                                        variant="primary"
                                        onClick={handleConfirmImport}
                                        disabled={importing || !canImport}
                                        icon={ArrowUpTrayIcon}
                                    >
                                        {importing ? 'Importando...' : 'Confirmar Importação'}
                                    </Button>
                                </div>

                                {!canImport && (
                                    <div className="mb-4 text-xs text-gray-500 italic">
                                        Resolva as pendências acima antes de prosseguir. A importação não permite marcas desconhecidas.
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
                        </>
                    )}

                    {/* Format guide */}
                    <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-900">
                        <h3 className="font-medium mb-2">Formato esperado — planilha v1 Mercury</h3>
                        <p className="mb-2">
                            Cada <strong>linha</strong> representa 1 referência × N tamanhos (matriz horizontal).
                            Múltiplas linhas com o mesmo <code className="bg-blue-100 px-1 rounded">Nr Pedido</code> são agrupadas em uma ordem única.
                        </p>
                        <div className="text-xs space-y-2">
                            <div>
                                <strong>25 colunas fixas:</strong> Referência, Descrição, Material, Cor, Tipo, Grupo, Subgrupo,
                                <strong className="text-blue-800"> Marca</strong>, Estação, Coleção, Custo Unit, Preço Venda, Precif, Qtd Pedido,
                                Custo total, Venda total, <strong className="text-blue-800">Nr Pedido</strong>, Status,
                                <strong className="text-blue-800"> Destino</strong>, Dt Pedido, Previsão, Pagamento, Nota fiscal, Emissão Nf, Confirmação
                            </div>
                            <div>
                                <strong>Colunas de tamanho:</strong> PP, P, M, G, GG · 01 · 33–40 · 33/34, 35/36 · 33.5–39.5 · 70–105 · etc.
                            </div>
                            <ul className="ml-4 list-disc space-y-0.5">
                                <li><strong>Marca:</strong> deve existir em <code className="bg-blue-100 px-1 rounded">product_brands</code> (sincronizado do CIGAM). Marcas desconhecidas fazem a ordem ser rejeitada</li>
                                <li><strong>Destino:</strong> código da loja (ex: Z424) ou nome completo (ex: "CD MEIA SOLA")</li>
                                <li><strong>Status:</strong> PENDENTE, FATURADO, FATURADO PARCIAL, CANCELADO, ENTREGUE</li>
                                <li><strong>Datas:</strong> dd/mm/yyyy ou serial Excel</li>
                                <li><strong>Valores:</strong> aceita "172,90" (BR) ou "172.90"</li>
                                <li><strong>Fornecedor:</strong> não vem na planilha. Cada ordem importada fica sem fornecedor — vinculação acontece em OrderPayments quando for pagar</li>
                                <li><strong>Re-importar:</strong> ordens pendentes têm cabeçalho atualizado; itens são upserted por (referência, tamanho)</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
