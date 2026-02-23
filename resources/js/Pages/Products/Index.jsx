import { Head, router } from "@inertiajs/react";
import { useState } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import ProductFilterBar from "@/Components/ProductFilterBar";
import ProductDetailModal from "@/Components/ProductDetailModal";
import ProductEditModal from "@/Components/ProductEditModal";
import ProductSyncModal from "@/Components/ProductSyncModal";
import ProductSyncLogsModal from "@/Components/ProductSyncLogsModal";
import { usePermissions } from "@/Hooks/usePermissions";
import { LockClosedIcon } from "@heroicons/react/24/outline";

export default function Index({ auth, products, filters, stats, cigamAvailable, activeSyncLog }) {
    const { canEditProducts, canSyncProducts } = usePermissions();

    const [detailProductId, setDetailProductId] = useState(null);
    const [editProductId, setEditProductId] = useState(null);
    const [isSyncOpen, setIsSyncOpen] = useState(false);
    const [isSyncLogsOpen, setIsSyncLogsOpen] = useState(false);

    const handleFilterChange = (key, value) => {
        const params = { ...filters };
        if (value !== '' && value !== undefined && value !== null) {
            params[key] = value;
        } else {
            delete params[key];
        }
        // Reset to page 1 on filter change
        delete params.page;

        router.get('/products', params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleSort = (field) => {
        const direction = filters.sort === field && filters.direction === 'asc' ? 'desc' : 'asc';
        handleFilterChange('sort', field);
        // Need to also set direction
        const params = { ...filters, sort: field, direction };
        delete params.page;
        router.get('/products', params, { preserveState: true, preserveScroll: true });
    };

    const handlePageChange = (url) => {
        if (url) router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    const reload = () => router.reload();

    const SortIcon = ({ field }) => {
        if (filters.sort !== field) return null;
        return (
            <span className="ml-1 text-indigo-500">
                {filters.direction === 'asc' ? '↑' : '↓'}
            </span>
        );
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="Produtos" />

            <div className="py-6 px-4 sm:px-6 lg:px-8">
                {/* Header */}
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">Produtos</h1>
                        <p className="mt-1 text-sm text-gray-500">Catálogo de produtos sincronizado do CIGAM</p>
                    </div>
                    <div className="flex items-center gap-3">
                        {canSyncProducts() && (
                            <>
                                <button
                                    onClick={() => setIsSyncLogsOpen(true)}
                                    className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                                >
                                    Histórico Sync
                                </button>
                                <button
                                    onClick={() => setIsSyncOpen(true)}
                                    disabled={!cigamAvailable}
                                    className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    Sincronizar
                                </button>
                            </>
                        )}
                    </div>
                </div>

                {/* Stats Cards */}
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <StatCard label="Total de Produtos" value={stats.total?.toLocaleString('pt-BR')} />
                    <StatCard label="Produtos Ativos" value={stats.active?.toLocaleString('pt-BR')} color="text-green-600" />
                    <StatCard label="Sync Bloqueado" value={stats.sync_locked?.toLocaleString('pt-BR')} color="text-amber-600" />
                    <StatCard label="Última Sync" value={stats.last_sync ? new Date(stats.last_sync).toLocaleString('pt-BR') : 'Nunca'} small />
                </div>

                {/* CIGAM unavailable warning */}
                {!cigamAvailable && canSyncProducts() && (
                    <div className="mb-4 bg-amber-50 border border-amber-200 rounded-lg p-3">
                        <p className="text-sm text-amber-800">
                            Conexão com o CIGAM indisponível. A sincronização está desabilitada.
                        </p>
                    </div>
                )}

                {/* Active sync banner */}
                {activeSyncLog && activeSyncLog.status === 'running' && (
                    <div className="mb-4 bg-blue-50 border border-blue-200 rounded-lg p-3 flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <div className="animate-spin rounded-full h-4 w-4 border-2 border-blue-600 border-t-transparent"></div>
                            <p className="text-sm text-blue-800">
                                Sincronização em andamento: {(activeSyncLog.processed_records || 0).toLocaleString()} / {(activeSyncLog.total_records || 0).toLocaleString()} produtos
                            </p>
                        </div>
                        <button
                            onClick={() => setIsSyncOpen(true)}
                            className="px-3 py-1 text-xs font-medium text-blue-700 bg-blue-100 rounded-lg hover:bg-blue-200"
                        >
                            Ver Progresso
                        </button>
                    </div>
                )}

                {/* Filters */}
                <ProductFilterBar filters={filters} onFilterChange={handleFilterChange} />

                {/* Table */}
                <div className="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <Th field="reference" label="Referência" sort={filters.sort} direction={filters.direction} onSort={handleSort} />
                                    <Th field="description" label="Descrição" sort={filters.sort} direction={filters.direction} onSort={handleSort} />
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Marca</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Coleção</th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Categoria</th>
                                    <Th field="sale_price" label="Preço" sort={filters.sort} direction={filters.direction} onSort={handleSort} />
                                    <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Variantes</th>
                                    <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {products.data.length === 0 ? (
                                    <tr>
                                        <td colSpan={8} className="px-4 py-12 text-center text-sm text-gray-500">
                                            Nenhum produto encontrado.
                                        </td>
                                    </tr>
                                ) : products.data.map(product => (
                                    <tr key={product.id}
                                        onClick={() => setDetailProductId(product.id)}
                                        className="cursor-pointer hover:bg-gray-50 transition-colors">
                                        <td className="px-4 py-3 text-sm font-medium text-gray-900 whitespace-nowrap">
                                            {product.reference}
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-600 max-w-xs truncate">
                                            {product.description}
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                                            {product.brand?.name || '-'}
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                                            {product.collection?.name || '-'}
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                                            {product.category?.name || '-'}
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-900 whitespace-nowrap">
                                            {product.sale_price ? `R$ ${Number(product.sale_price).toFixed(2).replace('.', ',')}` : '-'}
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-600 text-center">
                                            {product.variants_count ?? 0}
                                        </td>
                                        <td className="px-4 py-3 text-center whitespace-nowrap">
                                            <div className="flex items-center justify-center gap-1">
                                                <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${
                                                    product.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                                }`}>
                                                    {product.is_active ? 'Ativo' : 'Inativo'}
                                                </span>
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

                    {/* Pagination */}
                    {products.last_page > 1 && (
                        <div className="px-4 py-3 border-t border-gray-200 flex items-center justify-between">
                            <div className="text-sm text-gray-500">
                                Mostrando {products.from} a {products.to} de {products.total} produtos
                            </div>
                            <div className="flex gap-1">
                                {products.links.map((link, i) => (
                                    <button
                                        key={i}
                                        onClick={() => handlePageChange(link.url)}
                                        disabled={!link.url}
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                        className={`px-3 py-1 text-sm rounded border ${
                                            link.active
                                                ? 'bg-indigo-600 text-white border-indigo-600'
                                                : link.url
                                                    ? 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
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
                onEdit={(product) => {
                    setDetailProductId(null);
                    setEditProductId(product.id);
                }}
            />

            <ProductEditModal
                show={!!editProductId}
                onClose={() => setEditProductId(null)}
                productId={editProductId}
                onSaved={reload}
            />

            <ProductSyncModal
                show={isSyncOpen}
                onClose={() => setIsSyncOpen(false)}
                onCompleted={reload}
                activeSyncLog={activeSyncLog}
            />

            <ProductSyncLogsModal
                show={isSyncLogsOpen}
                onClose={() => setIsSyncLogsOpen(false)}
            />
        </AuthenticatedLayout>
    );
}

function StatCard({ label, value, color = 'text-gray-900', small = false }) {
    return (
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <div className="text-xs font-medium text-gray-500">{label}</div>
            <div className={`mt-1 ${small ? 'text-sm' : 'text-xl'} font-bold ${color}`}>{value}</div>
        </div>
    );
}

function Th({ field, label, sort, direction, onSort }) {
    return (
        <th
            className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:text-gray-700"
            onClick={() => onSort(field)}
        >
            {label}
            {sort === field && (
                <span className="ml-1 text-indigo-500">{direction === 'asc' ? '↑' : '↓'}</span>
            )}
        </th>
    );
}
