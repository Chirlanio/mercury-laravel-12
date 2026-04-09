import PageHeader from '@/Components/PageHeader';
import { Head, router, useForm } from '@inertiajs/react';
import { ClipboardDocumentCheckIcon, PlusIcon, MagnifyingGlassIcon } from '@heroicons/react/24/outline';
import { useState } from 'react';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import { formatDateTime } from '@/Utils/dateHelpers';

const STATUS_COLORS = {
    pending: 'bg-yellow-100 text-yellow-800',
    under_analysis: 'bg-blue-100 text-blue-800',
    awaiting_response: 'bg-orange-100 text-orange-800',
    balance_transfer: 'bg-purple-100 text-purple-800',
    adjusted: 'bg-green-100 text-green-800',
    no_adjustment: 'bg-gray-100 text-gray-800',
    cancelled: 'bg-red-100 text-red-800',
};

export default function Index({ adjustments, stores = [], filters = {}, statusOptions = {} }) {
    const { hasPermission } = usePermissions();
    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [storeFilter, setStoreFilter] = useState(filters.store_id || '');
    const [showCreateModal, setShowCreateModal] = useState(false);

    const createForm = useForm({
        store_id: '',
        observation: '',
        items: [{ reference: '', size: '', is_adjustment: true }],
    });

    const applyFilters = () => {
        router.get(route('stock-adjustments.index'), {
            search: search || undefined,
            status: statusFilter || undefined,
            store_id: storeFilter || undefined,
        }, { preserveState: true });
    };

    const addItem = () => {
        createForm.setData('items', [
            ...createForm.data.items,
            { reference: '', size: '', is_adjustment: true },
        ]);
    };

    const removeItem = (index) => {
        if (createForm.data.items.length <= 1) return;
        createForm.setData('items', createForm.data.items.filter((_, i) => i !== index));
    };

    const updateItem = (index, field, value) => {
        const items = [...createForm.data.items];
        items[index] = { ...items[index], [field]: value };
        createForm.setData('items', items);
    };

    const handleCreate = (e) => {
        e.preventDefault();
        createForm.post(route('stock-adjustments.store'), {
            onSuccess: () => {
                setShowCreateModal(false);
                createForm.reset();
            },
        });
    };

    return (
        <>
            <Head title="Ajustes de Estoque" />
            <PageHeader>
                <div className="flex justify-between items-center">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Ajustes de Estoque
                    </h2>
                    {hasPermission(PERMISSIONS.CREATE_ADJUSTMENTS) && (
                        <button
                            onClick={() => setShowCreateModal(true)}
                            className="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700"
                        >
                            <PlusIcon className="h-4 w-4 mr-2" />
                            Novo Ajuste
                        </button>
                    )}
                </div>
            </PageHeader>

            <div className="py-6">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    {/* Filtros */}
                    <div className="bg-white shadow rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 sm:grid-cols-4 gap-4">
                            <div className="relative">
                                <MagnifyingGlassIcon className="absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
                                <input
                                    type="text"
                                    placeholder="Buscar..."
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
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Loja</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Colaborador</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Items</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Criado por</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {adjustments.data && adjustments.data.length > 0 ? (
                                    adjustments.data.map((adj) => (
                                        <tr key={adj.id} className="hover:bg-gray-50">
                                            <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                #{adj.id}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {adj.store?.name || '-'}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {adj.employee || '-'}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {adj.items_count} item(s)
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <span className={`inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium ${STATUS_COLORS[adj.status] || 'bg-gray-100 text-gray-800'}`}>
                                                    {adj.status_label}
                                                </span>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {adj.created_by || '-'}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {formatDateTime(adj.created_at)}
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan="7" className="px-6 py-12 text-center text-gray-500">
                                            Nenhum ajuste de estoque encontrado.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>

                        {adjustments.links && adjustments.last_page > 1 && (
                            <div className="px-6 py-3 border-t border-gray-200 flex justify-between items-center">
                                <span className="text-sm text-gray-700">
                                    Mostrando {adjustments.from} a {adjustments.to} de {adjustments.total} registros
                                </span>
                                <div className="flex space-x-1">
                                    {adjustments.links.map((link, i) => (
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
                            <div className="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4 max-h-[90vh] flex flex-col">
                                <div className="px-6 py-4 border-b shrink-0">
                                    <h3 className="text-lg font-medium text-gray-900">Novo Ajuste de Estoque</h3>
                                </div>
                                <form onSubmit={handleCreate} className="flex flex-col flex-1 min-h-0">
                                <div className="p-6 space-y-4 overflow-y-auto flex-1">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Loja *</label>
                                        <select
                                            value={createForm.data.store_id}
                                            onChange={(e) => createForm.setData('store_id', e.target.value)}
                                            className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            required
                                        >
                                            <option value="">Selecione</option>
                                            {stores.map((s) => (
                                                <option key={s.id} value={s.id}>{s.code} - {s.name}</option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Observação</label>
                                        <textarea
                                            value={createForm.data.observation}
                                            onChange={(e) => createForm.setData('observation', e.target.value)}
                                            rows={2}
                                            className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        />
                                    </div>

                                    {/* Items */}
                                    <div>
                                        <div className="flex justify-between items-center mb-2">
                                            <label className="block text-sm font-medium text-gray-700">Itens *</label>
                                            <button
                                                type="button"
                                                onClick={addItem}
                                                className="text-sm text-indigo-600 hover:text-indigo-800"
                                            >
                                                + Adicionar Item
                                            </button>
                                        </div>
                                        <div className="space-y-2">
                                            {createForm.data.items.map((item, index) => (
                                                <div key={index} className="flex gap-2 items-start">
                                                    <input
                                                        type="text"
                                                        placeholder="Referência *"
                                                        value={item.reference}
                                                        onChange={(e) => updateItem(index, 'reference', e.target.value)}
                                                        className="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                        required
                                                    />
                                                    <input
                                                        type="text"
                                                        placeholder="Tamanho"
                                                        value={item.size}
                                                        onChange={(e) => updateItem(index, 'size', e.target.value)}
                                                        className="w-24 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                    />
                                                    {createForm.data.items.length > 1 && (
                                                        <button
                                                            type="button"
                                                            onClick={() => removeItem(index)}
                                                            className="text-red-500 hover:text-red-700 px-2 py-2"
                                                        >
                                                            &times;
                                                        </button>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    </div>

                                    </div>
                                    <div className="flex justify-end space-x-3 px-6 py-4 border-t bg-gray-50 rounded-b-lg shrink-0">
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
                                            {createForm.processing ? 'Salvando...' : 'Criar Ajuste'}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
