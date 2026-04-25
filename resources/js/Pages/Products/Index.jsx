import { Head, router } from "@inertiajs/react";
import { useState, useEffect, useRef } from "react";
import axios from "axios";
import { usePermissions } from "@/Hooks/usePermissions";
import useModalManager from "@/Hooks/useModalManager";
import Button from "@/Components/Button";
import PageHeader from "@/Components/Shared/PageHeader";
import StatusBadge from "@/Components/Shared/StatusBadge";
import StatisticsGrid from "@/Components/Shared/StatisticsGrid";
import ProductFilterBar from "@/Components/ProductFilterBar";
import ProductDetailModal from "@/Components/ProductDetailModal";
import ProductEditModal from "@/Components/ProductEditModal";
import ProductSyncModal from "@/Components/ProductSyncModal";
import ProductSyncLogsModal from "@/Components/ProductSyncLogsModal";
import ProductPriceImportModal from "@/Components/ProductPriceImportModal";
import PrintLabelsModal from "@/Components/PrintLabelsModal";
import {
    LockClosedIcon, CubeIcon, CheckCircleIcon, ArrowPathIcon,
    TagIcon, ArrowDownTrayIcon,
} from "@heroicons/react/24/outline";

export default function Index({ products, filters, stats, cigamAvailable, activeSyncLog }) {
    const { canEditProducts, canSyncProducts } = usePermissions();
    const { modals, openModal, closeModal } = useModalManager(['sync', 'syncLogs', 'priceImport', 'printLabels']);

    const [detailProductId, setDetailProductId] = useState(null);
    const [editProductId, setEditProductId] = useState(null);

    // Live sync progress polling
    const [liveSync, setLiveSync] = useState(activeSyncLog);
    const pollingRef = useRef(null);
    const pollCountRef = useRef(0);

    useEffect(() => {
        if (liveSync?.status === 'running' && liveSync?.id) {
            pollCountRef.current = 0;
            pollingRef.current = setInterval(async () => {
                try {
                    const { data } = await axios.get(`/products/sync/status/${liveSync.id}`);
                    setLiveSync(data);
                    pollCountRef.current++;
                    if (pollCountRef.current % 5 === 0) {
                        router.reload({ only: ['stats'], preserveScroll: true });
                    }
                    if (data.status !== 'running') {
                        clearInterval(pollingRef.current);
                        pollingRef.current = null;
                        router.reload({ only: ['products', 'stats', 'activeSyncLog'], preserveScroll: true });
                    }
                } catch {}
            }, 3000);
        }
        return () => { if (pollingRef.current) clearInterval(pollingRef.current); };
    }, [liveSync?.id, liveSync?.status]);

    useEffect(() => {
        if (activeSyncLog) setLiveSync(activeSyncLog);
    }, [activeSyncLog]);

    const navigateWithFilters = (params) => {
        delete params.page;
        router.get('/products', params, { preserveState: true, preserveScroll: true });
    };

    const handleFilterChange = (key, value) => {
        const params = { ...filters };
        if (value !== '' && value !== undefined && value !== null) {
            params[key] = value;
        } else {
            delete params[key];
        }
        navigateWithFilters(params);
    };

    const handleClearFilters = () => {
        const params = {};
        if (filters.sort) params.sort = filters.sort;
        if (filters.direction) params.direction = filters.direction;
        navigateWithFilters(params);
    };

    const handleSort = (field) => {
        const direction = filters.sort === field && filters.direction === 'asc' ? 'desc' : 'asc';
        navigateWithFilters({ ...filters, sort: field, direction });
    };

    const handlePageChange = (url) => {
        if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    const reload = () => {
        router.reload({ preserveScroll: true });
        if (liveSync?.status === 'running') {
            setLiveSync(prev => prev ? { ...prev, status: 'completed' } : null);
        }
    };

    const statisticsCards = [
        { label: 'Total de Produtos', value: stats.total, format: 'number', icon: CubeIcon, color: 'indigo' },
        { label: 'Produtos Ativos', value: stats.active, format: 'number', icon: CheckCircleIcon, color: 'green' },
        { label: 'Sync Bloqueado', value: stats.sync_locked, format: 'number', icon: LockClosedIcon, color: 'yellow' },
        { label: 'Última Sync', value: stats.last_sync ? new Date(stats.last_sync).toLocaleString('pt-BR') : 'Nunca', icon: ArrowPathIcon, color: 'blue' },
    ];

    return (
        <>
            <Head title="Produtos" />

            <div className="py-4 px-3 sm:py-6 sm:px-6 lg:px-8">
                {/* Header */}
                <PageHeader
                    title="Produtos"
                    subtitle="Catálogo de produtos sincronizado do CIGAM"
                    actions={[
                        {
                            label: 'Etiquetas',
                            icon: TagIcon,
                            variant: 'outline',
                            onClick: () => openModal('printLabels'),
                        },
                        {
                            label: 'Importar Preços',
                            icon: ArrowDownTrayIcon,
                            variant: 'outline',
                            onClick: () => openModal('priceImport'),
                            visible: canEditProducts(),
                        },
                        {
                            type: 'history',
                            onClick: () => openModal('syncLogs'),
                            visible: canSyncProducts(),
                        },
                        {
                            type: 'sync',
                            onClick: () => openModal('sync'),
                            disabled: !cigamAvailable,
                            visible: canSyncProducts(),
                        },
                    ]}
                />

                {/* Estatísticas */}
                <StatisticsGrid cards={statisticsCards} cols={4} />

                {/* CIGAM unavailable warning */}
                {!cigamAvailable && canSyncProducts() && (
                    <div className="mb-4 bg-amber-50 border border-amber-200 rounded-lg p-3">
                        <p className="text-sm text-amber-800">
                            Conexão com o CIGAM indisponível. A sincronização está desabilitada.
                        </p>
                    </div>
                )}

                {/* Active sync banner */}
                {liveSync && liveSync.status === 'running' && (
                    <div className="mb-3 bg-blue-50 border border-blue-200 rounded-lg p-3 flex items-center justify-between gap-3 sm:mb-4">
                        <div className="flex items-center gap-2 min-w-0 sm:gap-3">
                            <div className="animate-spin rounded-full h-4 w-4 border-2 border-blue-600 border-t-transparent flex-shrink-0" />
                            <div className="min-w-0">
                                <p className="text-xs text-blue-800 sm:text-sm">
                                    {liveSync.current_phase === 'lookups' && liveSync.lookup_current
                                        ? `Tabelas Auxiliares: ${liveSync.lookup_current} (${liveSync.lookup_processed || 0}/${liveSync.lookup_total || 0})`
                                        : liveSync.current_phase === 'prices' && liveSync.price_total > 0
                                            ? `Preços: ${(liveSync.price_processed || 0).toLocaleString('pt-BR')} / ${(liveSync.price_total || 0).toLocaleString('pt-BR')}`
                                            : `Sync: ${(liveSync.processed_records || 0).toLocaleString('pt-BR')} / ${(liveSync.total_records || 0).toLocaleString('pt-BR')}`
                                    }
                                </p>
                                {liveSync.current_phase && liveSync.current_phase !== 'lookups' && (
                                    <p className="text-xs text-blue-600 mt-0.5">
                                        {liveSync.current_phase === 'products' ? 'Produtos' : liveSync.current_phase === 'prices' ? 'Preços' : liveSync.current_phase}
                                        {liveSync.total_records > 0 && ` (${Math.round((liveSync.processed_records / liveSync.total_records) * 100)}%)`}
                                    </p>
                                )}
                            </div>
                        </div>
                        <Button variant="info" size="xs" onClick={() => openModal('sync')}>Ver</Button>
                    </div>
                )}

                {liveSync && liveSync.status === 'completed' && liveSync.id !== activeSyncLog?.id && (
                    <div className="mb-3 bg-emerald-50 border border-emerald-200 rounded-lg p-3 flex items-center justify-between sm:mb-4">
                        <p className="text-xs text-emerald-800 sm:text-sm">
                            Concluída: {(liveSync.inserted_records || 0).toLocaleString('pt-BR')} inseridos, {(liveSync.updated_records || 0).toLocaleString('pt-BR')} atualizados
                        </p>
                        <button onClick={() => setLiveSync(null)} className="text-emerald-600 hover:text-emerald-800 text-xs ml-2">Fechar</button>
                    </div>
                )}

                {liveSync && (liveSync.status === 'cancelled' || liveSync.status === 'failed') && liveSync.id !== activeSyncLog?.id && (
                    <div className="mb-3 bg-gray-50 border border-gray-200 rounded-lg p-3 flex items-center justify-between sm:mb-4">
                        <p className="text-xs text-gray-700 sm:text-sm">
                            {liveSync.status === 'cancelled' ? 'Sincronização cancelada.' : 'Sincronização falhou.'}
                        </p>
                        <button onClick={() => setLiveSync(null)} className="text-gray-500 hover:text-gray-700 text-xs ml-2">Fechar</button>
                    </div>
                )}

                {/* Filters */}
                <ProductFilterBar filters={filters} onFilterChange={handleFilterChange} onClearFilters={handleClearFilters} />

                {/* Mobile Cards / Desktop Table */}
                <div className="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    {products.data.length === 0 ? (
                        <div className="px-4 py-12 text-center text-sm text-gray-500">
                            Nenhum produto encontrado.
                        </div>
                    ) : (
                        <>
                            {/* Mobile: Card layout */}
                            <div className="divide-y divide-gray-200 sm:hidden">
                                {products.data.map(product => (
                                    <div key={product.id}
                                        onClick={() => setDetailProductId(product.id)}
                                        className="p-3 cursor-pointer active:bg-gray-50">
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="min-w-0 flex-1">
                                                <p className="text-sm font-medium text-gray-900">{product.reference}</p>
                                                <p className="text-xs text-gray-500 truncate mt-0.5">{product.description}</p>
                                            </div>
                                            <div className="flex items-center gap-1 flex-shrink-0">
                                                {product.sync_locked && <LockClosedIcon className="h-3.5 w-3.5 text-amber-500" />}
                                                <StatusBadge variant={product.is_active ? 'success' : 'danger'} size="sm">
                                                    {product.is_active ? 'Ativo' : 'Inativo'}
                                                </StatusBadge>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-3 mt-2 text-xs text-gray-500">
                                            {product.brand?.name && <span>{product.brand.name}</span>}
                                            {product.category?.name && <span>{product.category.name}</span>}
                                            <span className="ml-auto font-medium text-gray-900">
                                                {product.sale_price ? `R$ ${Number(product.sale_price).toFixed(2).replace('.', ',')}` : '-'}
                                            </span>
                                        </div>
                                    </div>
                                ))}
                            </div>

                            {/* Desktop: Table layout */}
                            <div className="hidden sm:block overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <Th field="reference" label="Referência" sort={filters.sort} direction={filters.direction} onSort={handleSort} />
                                            <Th field="description" label="Descrição" sort={filters.sort} direction={filters.direction} onSort={handleSort} />
                                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Marca</th>
                                            <th className="hidden lg:table-cell px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estação</th>
                                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                            <Th field="sale_price" label="Preço" sort={filters.sort} direction={filters.direction} onSort={handleSort} />
                                            <th className="hidden md:table-cell px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Variantes</th>
                                            <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {products.data.map(product => (
                                            <tr key={product.id}
                                                onClick={() => setDetailProductId(product.id)}
                                                className="cursor-pointer hover:bg-gray-50 transition-colors">
                                                <td className="px-4 py-3 text-sm font-medium text-gray-900 whitespace-nowrap">{product.reference}</td>
                                                <td className="px-4 py-3 text-sm text-gray-600 max-w-xs truncate">{product.description}</td>
                                                <td className="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">{product.brand?.name || '-'}</td>
                                                <td className="hidden lg:table-cell px-4 py-3 text-sm text-gray-600 whitespace-nowrap">{product.collection?.name || '-'}</td>
                                                <td className="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">{product.category?.name || '-'}</td>
                                                <td className="px-4 py-3 text-sm text-gray-900 whitespace-nowrap">
                                                    {product.sale_price ? `R$ ${Number(product.sale_price).toFixed(2).replace('.', ',')}` : '-'}
                                                </td>
                                                <td className="hidden md:table-cell px-4 py-3 text-sm text-gray-600 text-center">{product.variants_count ?? 0}</td>
                                                <td className="px-4 py-3 text-center whitespace-nowrap">
                                                    <div className="flex items-center justify-center gap-1">
                                                        <StatusBadge variant={product.is_active ? 'success' : 'danger'}>
                                                            {product.is_active ? 'Ativo' : 'Inativo'}
                                                        </StatusBadge>
                                                        {product.sync_locked && (
                                                            <LockClosedIcon className="h-4 w-4 text-amber-500" title="Sync Bloqueado" />
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </>
                    )}

                    {/* Pagination */}
                    {products.last_page > 1 && (
                        <div className="px-3 py-3 border-t border-gray-200 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:px-4">
                            <div className="text-xs text-gray-500 sm:text-sm">
                                {products.from}-{products.to} de {products.total}
                            </div>
                            <div className="flex flex-wrap gap-1">
                                {products.links.map((link, i) => (
                                    <button key={i} onClick={() => handlePageChange(link.url)} disabled={!link.url}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                        className={`px-2.5 py-1 text-xs rounded border sm:px-3 sm:text-sm ${
                                            link.active ? 'bg-indigo-600 text-white border-indigo-600'
                                            : link.url ? 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
                                            : 'bg-gray-100 text-gray-400 border-gray-200 cursor-not-allowed'
                                        }`}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Modals */}
            <ProductDetailModal
                show={!!detailProductId}
                onClose={() => setDetailProductId(null)}
                productId={detailProductId}
                canEdit={canEditProducts()}
                onEdit={(product) => { setDetailProductId(null); setEditProductId(product.id); }}
            />

            <ProductEditModal
                show={!!editProductId}
                onClose={() => setEditProductId(null)}
                productId={editProductId}
                onSaved={reload}
            />

            <ProductSyncModal
                show={modals.sync}
                onClose={() => closeModal('sync')}
                onCompleted={reload}
                onStarted={(log) => setLiveSync(log)}
                activeSyncLog={liveSync || activeSyncLog}
            />

            <ProductSyncLogsModal
                show={modals.syncLogs}
                onClose={() => closeModal('syncLogs')}
            />

            <ProductPriceImportModal
                show={modals.priceImport}
                onClose={() => closeModal('priceImport')}
                onCompleted={reload}
            />

            <PrintLabelsModal
                show={modals.printLabels}
                onClose={() => closeModal('printLabels')}
            />
        </>
    );
}

function Th({ field, label, sort, direction, onSort }) {
    return (
        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:text-gray-700"
            onClick={() => onSort(field)}>
            {label}
            {sort === field && <span className="ml-1 text-indigo-500">{direction === 'asc' ? '↑' : '↓'}</span>}
        </th>
    );
}
