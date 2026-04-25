import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState, useRef, useEffect } from 'react';
import {
    ArrowUpTrayIcon, CheckCircleIcon, XCircleIcon,
    DocumentArrowUpIcon, ExclamationTriangleIcon, TagIcon, ScaleIcon,
    ClockIcon,
} from '@heroicons/react/24/outline';
import Button from '@/Components/Button';
import EmptyState from '@/Components/Shared/EmptyState';
import LoadingSpinner from '@/Components/Shared/LoadingSpinner';
import PageHeader from '@/Components/Shared/PageHeader';

export default function Import() {
    const { importStats } = usePage().props;
    const fileInputRef = useRef(null);
    const [file, setFile] = useState(null);
    const [preview, setPreview] = useState(null);
    const [previewing, setPreviewing] = useState(false);
    const [importing, setImporting] = useState(false);
    const [importSeconds, setImportSeconds] = useState(0);
    const [error, setError] = useState(null);

    // Timer de progresso visual — conta segundos durante importação
    useEffect(() => {
        if (!importing) {
            setImportSeconds(0);
            return;
        }
        const interval = setInterval(() => {
            setImportSeconds((s) => s + 1);
        }, 1000);
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
        fetch(route('purchase-orders.import.preview'), {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            redirect: 'manual',
            credentials: 'same-origin',
        })
            .then(async (r) => {
                if (r.type === 'opaqueredirect' || r.status === 302 || r.status === 401 || r.status === 419) {
                    throw new Error('Sua sessão expirou. Recarregue a página (F5) e faça login novamente.');
                }
                if (r.status === 422) {
                    const body = await r.json().catch(() => null);
                    const fileErrors = body?.errors?.file || [];
                    throw new Error(fileErrors.length ? fileErrors.join(' ') : (body?.message || 'Arquivo inválido.'));
                }
                if (!r.ok) {
                    const text = await r.text().catch(() => '');
                    throw new Error(`Erro ${r.status} ao ler planilha${text ? ': ' + text.slice(0, 200) : ''}`);
                }
                const contentType = r.headers.get('content-type') || '';
                if (!contentType.includes('application/json')) {
                    throw new Error('Resposta inesperada do servidor. Sua sessão pode ter expirado — recarregue a página.');
                }
                return r.json();
            })
            .then((data) => {
                if (!data || typeof data !== 'object' || !('headers_detected' in data)) {
                    throw new Error('Resposta do servidor não contém os dados esperados.');
                }
                setPreview(data);
                setPreviewing(false);
            })
            .catch((err) => {
                setError(err.message || 'Falha ao ler a planilha.');
                setPreviewing(false);
            });
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
    const knownBrands = preview?.brands_detected?.filter((b) => b.is_known && (b.resolved_via ?? 'direct') === 'direct') || [];
    const aliasedBrands = preview?.brands_aliased || [];
    const pendingSizes = preview?.sizes_pending || [];
    const missingColumns = preview?.missing_columns || [];
    const canImport = preview?.can_import || false;

    const formatDuration = (seconds) => {
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        return m > 0 ? `${m}min ${s}s` : `${s}s`;
    };

    return (
        <>
            <Head title="Importar Ordens de Compra" />

            {/* Overlay de importação em andamento */}
            {importing && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 backdrop-blur-sm">
                    <div className="bg-white rounded-2xl shadow-2xl p-8 max-w-md mx-4 text-center">
                        <LoadingSpinner size="xl" />
                        <h2 className="mt-4 text-lg font-bold text-gray-900">Importando ordens de compra...</h2>
                        <p className="mt-2 text-sm text-gray-600">
                            {preview?.total
                                ? `Processando ${preview.total.toLocaleString('pt-BR')} linhas da planilha`
                                : 'Processando planilha'}
                        </p>
                        <div className="mt-4 flex items-center justify-center gap-2 text-gray-500">
                            <ClockIcon className="h-4 w-4" />
                            <span className="font-mono text-sm">{formatDuration(importSeconds)}</span>
                        </div>
                        <p className="mt-3 text-xs text-gray-400">
                            Não feche esta página. A importação pode levar alguns minutos para planilhas grandes.
                        </p>
                        {/* Progress bar simulada — avança lentamente pra dar feedback visual */}
                        <div className="mt-4 w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                            <div
                                className="bg-indigo-500 h-2 rounded-full transition-all duration-1000 ease-out"
                                style={{
                                    width: `${Math.min(95, importSeconds * 2)}%`,
                                }}
                            />
                        </div>
                    </div>
                </div>
            )}

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    {/* Header */}
                    <PageHeader
                        title="Importar Ordens de Compra"
                        subtitle="Upload de planilha XLSX ou CSV no formato Mercury v1"
                        actions={[
                            {
                                label: 'Aliases de Marca',
                                icon: TagIcon,
                                variant: 'outline',
                                href: route('purchase-orders.brand-aliases.index'),
                            },
                            {
                                label: 'Mapeamento de Tamanhos',
                                icon: ScaleIcon,
                                variant: 'outline',
                                href: route('purchase-orders.size-mappings.index'),
                            },
                            { type: 'back', href: route('purchase-orders.index') },
                        ]}
                    />

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
                                        <div className="mt-3 bg-yellow-50 border border-yellow-200 rounded p-3">
                                            <div className="flex items-center text-yellow-800 mb-2">
                                                <ExclamationTriangleIcon className="h-4 w-4 mr-1" />
                                                <span className="text-sm font-medium">
                                                    {importStats.rows_rejected} linha(s) + {importStats.items_rejected} item(ns) rejeitado(s)
                                                </span>
                                            </div>
                                            {importStats.rejected_reasons && Object.keys(importStats.rejected_reasons).length > 0 && (
                                                <div className="mb-2">
                                                    <div className="text-xs font-medium text-yellow-900 mb-1">Motivos (agrupado):</div>
                                                    <ul className="text-xs text-yellow-800 space-y-0.5">
                                                        {Object.entries(importStats.rejected_reasons).slice(0, 10).map(([reason, count], i) => (
                                                            <li key={i} className="flex justify-between">
                                                                <span>{reason}</span>
                                                                <span className="font-mono font-bold ml-2">{count}</span>
                                                            </li>
                                                        ))}
                                                    </ul>
                                                </div>
                                            )}
                                            {importStats.rejected?.length > 0 && (
                                                <details className="mt-2">
                                                    <summary className="text-xs text-yellow-700 cursor-pointer hover:text-yellow-900">
                                                        Ver {Math.min(importStats.rejected.length, 10)} exemplos específicos
                                                    </summary>
                                                    <ul className="mt-2 text-xs text-yellow-700 space-y-1 max-h-48 overflow-y-auto">
                                                        {importStats.rejected.slice(0, 10).map((r, i) => (
                                                            <li key={i} className="font-mono">
                                                                <strong>Linha {r.row_number}:</strong> {r.reason}
                                                            </li>
                                                        ))}
                                                    </ul>
                                                </details>
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
                                <p className="text-sm text-gray-500 mt-1">XLSX, XLS ou CSV — máximo 20 MB</p>
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
                            <LoadingSpinner size="md" label="Lendo planilha..." />
                        </div>
                    )}

                    {preview && (
                        <>
                            {/* Info sobre o header detectado */}
                            {preview.header_line && preview.headers_detected && (
                                <div className="mb-4 bg-gray-50 border border-gray-200 rounded-lg p-3 text-xs">
                                    {preview.sheet_names && preview.sheet_names.length > 1 && (
                                        <div className="mb-2">
                                            <span className="font-medium text-gray-700">Abas do arquivo:</span>
                                            <div className="mt-1 flex flex-wrap gap-1">
                                                {preview.sheet_names.map((name, i) => (
                                                    <span key={i} className={`px-2 py-0.5 rounded font-mono ${name === preview.sheet_name ? 'bg-green-100 text-green-800 font-semibold' : 'bg-gray-200 text-gray-700'}`}>
                                                        {name === preview.sheet_name ? '✓ ' : ''}{name}
                                                    </span>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                    <div>
                                        <span className="font-medium text-gray-700">
                                            Header detectado
                                            {preview.sheet_name && preview.sheet_names?.length > 1 && ` na aba "${preview.sheet_name}"`}
                                            {' '}linha {preview.header_line}:
                                        </span>
                                        <div className="mt-1 font-mono text-gray-600 break-words">
                                            {preview.headers_detected.length > 0
                                                ? preview.headers_detected.join(' | ')
                                                : <em className="text-red-600">Nenhum header encontrado</em>}
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* Colunas faltando */}
                            {missingColumns.length > 0 && (
                                <div className="mb-4 bg-red-50 border border-red-200 rounded-lg p-4">
                                    <div className="flex items-start">
                                        <XCircleIcon className="h-5 w-5 text-red-600 mr-2 flex-shrink-0 mt-0.5" />
                                        <div className="flex-1">
                                            <h3 className="text-sm font-medium text-red-900">Colunas obrigatórias ausentes</h3>
                                            <p className="text-xs text-red-700 mt-1">{missingColumns.join(', ')}</p>
                                            {preview.sheet_names && preview.sheet_names.length > 1 ? (
                                                <p className="text-xs text-red-600 mt-2">
                                                    O arquivo tem <strong>{preview.sheet_names.length} abas</strong>. O sistema escolheu <code className="bg-red-100 px-1 rounded">{preview.sheet_name}</code>. Talvez a aba visível quando abriu o Excel seja uma tabela dinâmica/resumo.
                                                </p>
                                            ) : (
                                                <p className="text-xs text-red-600 mt-2">Verifique o header da planilha.</p>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* Marcas desconhecidas */}
                            {unknownBrands.length > 0 && (
                                <div className="mb-4 bg-red-50 border border-red-200 rounded-lg p-4">
                                    <div className="flex items-start">
                                        <TagIcon className="h-5 w-5 text-red-600 mr-2 flex-shrink-0 mt-0.5" />
                                        <div className="flex-1">
                                            <div className="flex justify-between items-start">
                                                <h3 className="text-sm font-medium text-red-900">
                                                    {unknownBrands.length} marca(s) não cadastrada(s) e sem alias
                                                </h3>
                                                <Link href={route('purchase-orders.brand-aliases.index')}
                                                    className="text-xs text-red-700 hover:text-red-900 underline">
                                                    Configurar aliases →
                                                </Link>
                                            </div>
                                            <div className="mt-2 flex flex-wrap gap-1">
                                                {unknownBrands.map((b, i) => (
                                                    <span key={i} className="inline-flex items-center bg-red-100 text-red-800 text-xs px-2 py-0.5 rounded">{b.name}</span>
                                                ))}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* Tamanhos pendentes */}
                            {pendingSizes.length > 0 && (
                                <div className="mb-4 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                    <div className="flex items-start">
                                        <ScaleIcon className="h-5 w-5 text-yellow-600 mr-2 flex-shrink-0 mt-0.5" />
                                        <div className="flex-1">
                                            <div className="flex justify-between items-start">
                                                <h3 className="text-sm font-medium text-yellow-900">{pendingSizes.length} tamanho(s) sem mapeamento</h3>
                                                <Link href={route('purchase-orders.size-mappings.index')}
                                                    className="text-xs text-yellow-700 hover:text-yellow-900 underline">
                                                    Configurar →
                                                </Link>
                                            </div>
                                            <div className="mt-2 flex flex-wrap gap-1">
                                                {pendingSizes.map((s, i) => (
                                                    <span key={i} className="inline-flex items-center bg-yellow-100 text-yellow-800 text-xs px-2 py-0.5 rounded font-mono">{s}</span>
                                                ))}
                                            </div>
                                            <p className="text-xs text-yellow-700 mt-2">
                                                Itens com tamanho pendente serão rejeitados individualmente — o resto da importação prossegue.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* Marcas via alias */}
                            {aliasedBrands.length > 0 && (
                                <div className="mb-4 bg-blue-50 border border-blue-200 rounded-lg p-4">
                                    <div className="flex items-start">
                                        <TagIcon className="h-5 w-5 text-blue-600 mr-2 flex-shrink-0 mt-0.5" />
                                        <div className="flex-1">
                                            <h3 className="text-sm font-medium text-blue-900">{aliasedBrands.length} marca(s) resolvida(s) via alias</h3>
                                            <div className="mt-2 space-y-1 text-xs">
                                                {aliasedBrands.map((b, i) => (
                                                    <div key={i} className="flex items-center text-blue-800">
                                                        <span className="font-mono bg-blue-100 px-2 py-0.5 rounded">{b.name}</span>
                                                        <span className="mx-2">→</span>
                                                        <span className="font-medium">{b.resolved_to_name || b.product_brand_name}</span>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* Marcas reconhecidas */}
                            {knownBrands.length > 0 && unknownBrands.length === 0 && (
                                <div className="mb-4 bg-green-50 border border-green-200 rounded-lg p-4 text-sm text-green-900">
                                    <div className="flex items-center">
                                        <CheckCircleIcon className="h-4 w-4 mr-2" />
                                        <span className="font-medium">{knownBrands.length} marca(s) reconhecida(s) diretamente</span>
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
                                            Preview ({preview.total?.toLocaleString('pt-BR')} linha{preview.total !== 1 ? 's' : ''})
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
                                        Confirmar Importação
                                    </Button>
                                </div>

                                {!canImport && (
                                    <div className="mb-4 text-xs text-gray-500 italic">
                                        Resolva as pendências acima antes de prosseguir.
                                    </div>
                                )}

                                {preview.rows?.length === 0 ? (
                                    <EmptyState icon={DocumentArrowUpIcon} title="Planilha vazia" description="Nenhuma linha encontrada." compact />
                                ) : (
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full text-xs">
                                            <thead className="bg-gray-50">
                                                <tr>
                                                    {Object.keys(preview.rows[0] || {}).map((col) => (
                                                        <th key={col} className="px-2 py-2 text-left font-medium text-gray-500 uppercase whitespace-nowrap">{col}</th>
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
                                                Mostrando {preview.rows.length} de {preview.total?.toLocaleString('pt-BR')} linhas
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
                            Múltiplas linhas com o mesmo <code className="bg-blue-100 px-1 rounded">Nr Pedido</code> são agrupadas.
                            Campos de cabeçalho (Nr Pedido, Marca, Status, Destino...) são propagados automaticamente quando vazios (merged cells).
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
                                <li><strong>Marca:</strong> deve existir em product_brands (CIGAM) ou ter alias configurado</li>
                                <li><strong>Destino:</strong> código (Z424) ou nome (CD - Meia Sola)</li>
                                <li><strong>Status:</strong> PENDENTE, FATURADO, FATURADO PARCIAL, CANCELADO, ENTREGUE</li>
                                <li><strong>Datas:</strong> dd/mm/yyyy ou serial Excel</li>
                                <li><strong>Valores:</strong> aceita "172,90" (BR) ou "172.90"</li>
                                <li><strong>Múltiplas abas:</strong> sistema auto-detecta a aba com os dados brutos</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
