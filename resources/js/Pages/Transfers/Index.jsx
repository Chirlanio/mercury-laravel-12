import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { ArrowsRightLeftIcon, PlusIcon, MagnifyingGlassIcon } from '@heroicons/react/24/outline';
import { useState } from 'react';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';

const STATUS_COLORS = {
    pending: 'bg-yellow-100 text-yellow-800',
    in_transit: 'bg-blue-100 text-blue-800',
    delivered: 'bg-indigo-100 text-indigo-800',
    confirmed: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800',
};

export default function Index({ transfers, stores = [], filters = {}, statusOptions = {}, typeOptions = {} }) {
    const { hasPermission } = usePermissions();
    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [storeFilter, setStoreFilter] = useState(filters.store_id || '');
    const [showCreateModal, setShowCreateModal] = useState(false);

    const createForm = useForm({
        origin_store_id: '',
        destination_store_id: '',
        invoice_number: '',
        volumes_qty: '',
        products_qty: '',
        transfer_type: 'transfer',
        observations: '',
    });

    const applyFilters = () => {
        router.get(route('transfers.index'), {
            search: search || undefined,
            status: statusFilter || undefined,
            store_id: storeFilter || undefined,
        }, { preserveState: true });
    };

    const handleCreate = (e) => {
        e.preventDefault();
        createForm.post(route('transfers.store'), {
            onSuccess: () => {
                setShowCreateModal(false);
                createForm.reset();
            },
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex justify-between items-center">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Transferências
                    </h2>
                    {hasPermission(PERMISSIONS.CREATE_TRANSFERS) && (
                        <button
                            onClick={() => setShowCreateModal(true)}
                            className="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700"
                        >
                            <PlusIcon className="h-4 w-4 mr-2" />
                            Nova Transferência
                        </button>
                    )}
                </div>
            }
        >
            <Head title="Transferências" />

            <div className="py-6">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    {/* Filtros */}
                    <div className="bg-white shadow rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 sm:grid-cols-4 gap-4">
                            <div className="relative">
                                <MagnifyingGlassIcon className="absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
                                <input
                                    type="text"
                                    placeholder="Buscar NF, observações..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                                    className="pl-10 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                />
                            </div>
                            <select
                                value={statusFilter}
                                onChange={(e) => setStatusFilter(e.target.value)}
                                className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            >
                                <option value="">Todos os Status</option>
                                {Object.entries(statusOptions).map(([key, label]) => (
                                    <option key={key} value={key}>{label}</option>
                                ))}
                            </select>
                            <select
                                value={storeFilter}
                                onChange={(e) => setStoreFilter(e.target.value)}
                                className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            >
                                <option value="">Todas as Lojas</option>
                                {stores.map((store) => (
                                    <option key={store.id} value={store.id}>
                                        {store.code} - {store.name}
                                    </option>
                                ))}
                            </select>
                            <button
                                onClick={applyFilters}
                                className="inline-flex justify-center items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700"
                            >
                                Filtrar
                            </button>
                        </div>
                    </div>

                    {/* Tabela */}
                    <div className="bg-white shadow rounded-lg overflow-hidden">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Origem</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Destino</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">NF</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vol/Prod</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {transfers.data && transfers.data.length > 0 ? (
                                    transfers.data.map((transfer) => (
                                        <tr key={transfer.id} className="hover:bg-gray-50">
                                            <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                #{transfer.id}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {transfer.origin_store?.name || '-'}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {transfer.destination_store?.name || '-'}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {transfer.type_label}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">
                                                {transfer.invoice_number || '-'}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {transfer.volumes_qty ?? '-'} / {transfer.products_qty ?? '-'}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <span className={`inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium ${STATUS_COLORS[transfer.status] || 'bg-gray-100 text-gray-800'}`}>
                                                    {transfer.status_label}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {transfer.created_at}
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan="8" className="px-6 py-12 text-center text-gray-500">
                                            Nenhuma transferência encontrada.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>

                        {/* Paginação */}
                        {transfers.links && transfers.last_page > 1 && (
                            <div className="px-6 py-3 border-t border-gray-200 flex justify-between items-center">
                                <span className="text-sm text-gray-700">
                                    Mostrando {transfers.from} a {transfers.to} de {transfers.total} registros
                                </span>
                                <div className="flex space-x-1">
                                    {transfers.links.map((link, i) => (
                                        <button
                                            key={i}
                                            onClick={() => link.url && router.get(link.url)}
                                            disabled={!link.url}
                                            className={`px-3 py-1 text-sm rounded ${
                                                link.active
                                                    ? 'bg-indigo-600 text-white'
                                                    : link.url
                                                    ? 'bg-white text-gray-700 hover:bg-gray-50 border'
                                                    : 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                            }`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Modal Criar */}
                    {showCreateModal && (
                        <div className="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
                            <div className="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg mx-4">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Nova Transferência</h3>
                                <form onSubmit={handleCreate} className="space-y-4">
                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Loja Origem *</label>
                                            <select
                                                value={createForm.data.origin_store_id}
                                                onChange={(e) => createForm.setData('origin_store_id', e.target.value)}
                                                className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                required
                                            >
                                                <option value="">Selecione</option>
                                                {stores.map((s) => (
                                                    <option key={s.id} value={s.id}>{s.code} - {s.name}</option>
                                                ))}
                                            </select>
                                            {createForm.errors.origin_store_id && <p className="mt-1 text-sm text-red-600">{createForm.errors.origin_store_id}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Loja Destino *</label>
                                            <select
                                                value={createForm.data.destination_store_id}
                                                onChange={(e) => createForm.setData('destination_store_id', e.target.value)}
                                                className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                required
                                            >
                                                <option value="">Selecione</option>
                                                {stores.map((s) => (
                                                    <option key={s.id} value={s.id}>{s.code} - {s.name}</option>
                                                ))}
                                            </select>
                                            {createForm.errors.destination_store_id && <p className="mt-1 text-sm text-red-600">{createForm.errors.destination_store_id}</p>}
                                        </div>
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Tipo *</label>
                                        <select
                                            value={createForm.data.transfer_type}
                                            onChange={(e) => createForm.setData('transfer_type', e.target.value)}
                                            className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        >
                                            {Object.entries(typeOptions).map(([key, label]) => (
                                                <option key={key} value={key}>{label}</option>
                                            ))}
                                        </select>
                                    </div>
                                    <div className="grid grid-cols-3 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Nº NF</label>
                                            <input
                                                type="text"
                                                value={createForm.data.invoice_number}
                                                onChange={(e) => createForm.setData('invoice_number', e.target.value)}
                                                className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Volumes</label>
                                            <input
                                                type="number"
                                                min="0"
                                                value={createForm.data.volumes_qty}
                                                onChange={(e) => createForm.setData('volumes_qty', e.target.value)}
                                                className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Produtos</label>
                                            <input
                                                type="number"
                                                min="0"
                                                value={createForm.data.products_qty}
                                                onChange={(e) => createForm.setData('products_qty', e.target.value)}
                                                className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            />
                                        </div>
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Observações</label>
                                        <textarea
                                            value={createForm.data.observations}
                                            onChange={(e) => createForm.setData('observations', e.target.value)}
                                            rows={3}
                                            className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        />
                                    </div>
                                    <div className="flex justify-end space-x-3 pt-4">
                                        <button
                                            type="button"
                                            onClick={() => setShowCreateModal(false)}
                                            className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                                        >
                                            Cancelar
                                        </button>
                                        <button
                                            type="submit"
                                            disabled={createForm.processing}
                                            className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50"
                                        >
                                            {createForm.processing ? 'Salvando...' : 'Criar Transferência'}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
