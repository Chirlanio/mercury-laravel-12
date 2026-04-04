import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { BanknotesIcon, PlusIcon, MagnifyingGlassIcon, Squares2X2Icon, TableCellsIcon } from '@heroicons/react/24/outline';
import { useState } from 'react';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';

const STATUS_COLORS = {
    backlog: 'bg-gray-100 text-gray-800 border-gray-300',
    doing: 'bg-blue-100 text-blue-800 border-blue-300',
    waiting: 'bg-yellow-100 text-yellow-800 border-yellow-300',
    done: 'bg-green-100 text-green-800 border-green-300',
};

const KANBAN_HEADER_COLORS = {
    backlog: 'bg-gray-500',
    doing: 'bg-blue-500',
    waiting: 'bg-yellow-500',
    done: 'bg-green-500',
};

export default function Index({ payments, stores = [], filters = {}, statusOptions = {}, kanbanData = {} }) {
    const { hasPermission } = usePermissions();
    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [storeFilter, setStoreFilter] = useState(filters.store_id || '');
    const [viewMode, setViewMode] = useState('table');
    const [showCreateModal, setShowCreateModal] = useState(false);

    const createForm = useForm({
        store_id: '',
        supplier_name: '',
        description: '',
        total_value: '',
        payment_type: '',
        due_date: '',
        installments: 1,
    });

    const applyFilters = () => {
        router.get(route('order-payments.index'), {
            search: search || undefined,
            status: statusFilter || undefined,
            store_id: storeFilter || undefined,
        }, { preserveState: true });
    };

    const handleCreate = (e) => {
        e.preventDefault();
        createForm.post(route('order-payments.store'), {
            onSuccess: () => {
                setShowCreateModal(false);
                createForm.reset();
            },
        });
    };

    const formatCurrency = (value) => {
        return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value || 0);
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex justify-between items-center">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Ordens de Pagamento
                    </h2>
                    <div className="flex items-center space-x-3">
                        <div className="flex bg-gray-100 rounded-md p-0.5">
                            <button
                                onClick={() => setViewMode('table')}
                                className={`p-1.5 rounded ${viewMode === 'table' ? 'bg-white shadow-sm' : ''}`}
                                title="Tabela"
                            >
                                <TableCellsIcon className="h-5 w-5 text-gray-600" />
                            </button>
                            <button
                                onClick={() => setViewMode('kanban')}
                                className={`p-1.5 rounded ${viewMode === 'kanban' ? 'bg-white shadow-sm' : ''}`}
                                title="Kanban"
                            >
                                <Squares2X2Icon className="h-5 w-5 text-gray-600" />
                            </button>
                        </div>
                        {hasPermission(PERMISSIONS.CREATE_ORDER_PAYMENTS) && (
                            <button
                                onClick={() => setShowCreateModal(true)}
                                className="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700"
                            >
                                <PlusIcon className="h-4 w-4 mr-2" />
                                Nova Ordem
                            </button>
                        )}
                    </div>
                </div>
            }
        >
            <Head title="Ordens de Pagamento" />

            <div className="py-6">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    {/* Filtros */}
                    <div className="bg-white shadow rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 sm:grid-cols-4 gap-4">
                            <div className="relative">
                                <MagnifyingGlassIcon className="absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
                                <input
                                    type="text"
                                    placeholder="Buscar fornecedor, NF..."
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

                    {/* Kanban View */}
                    {viewMode === 'kanban' && (
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                            {Object.entries(statusOptions).map(([status, label]) => (
                                <div key={status} className="bg-white rounded-lg shadow overflow-hidden">
                                    <div className={`${KANBAN_HEADER_COLORS[status]} px-4 py-2`}>
                                        <div className="flex justify-between items-center">
                                            <h3 className="text-sm font-medium text-white">{label}</h3>
                                            <span className="bg-white bg-opacity-30 text-white text-xs font-bold px-2 py-0.5 rounded-full">
                                                {kanbanData[status]?.count ?? 0}
                                            </span>
                                        </div>
                                    </div>
                                    <div className="p-3 space-y-2 min-h-[200px]">
                                        {payments.data
                                            ?.filter(p => p.status === status)
                                            .map((payment) => (
                                                <div
                                                    key={payment.id}
                                                    className={`border rounded-lg p-3 ${payment.is_overdue ? 'border-red-300 bg-red-50' : 'border-gray-200 bg-white'}`}
                                                >
                                                    <div className="text-sm font-medium text-gray-900 truncate">
                                                        {payment.supplier_name}
                                                    </div>
                                                    <div className="text-xs text-gray-500 mt-1 truncate">
                                                        {payment.description || 'Sem descrição'}
                                                    </div>
                                                    <div className="flex justify-between items-center mt-2">
                                                        <span className="text-sm font-semibold text-gray-900">
                                                            {payment.formatted_total}
                                                        </span>
                                                        {payment.due_date && (
                                                            <span className={`text-xs ${payment.is_overdue ? 'text-red-600 font-medium' : 'text-gray-500'}`}>
                                                                {payment.is_overdue ? 'Vencido: ' : ''}{payment.due_date}
                                                            </span>
                                                        )}
                                                    </div>
                                                </div>
                                            ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    {/* Table View */}
                    {viewMode === 'table' && (
                        <div className="bg-white shadow rounded-lg overflow-hidden">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Fornecedor</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Loja</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Valor</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vencimento</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">NF</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Solicitante</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200">
                                    {payments.data && payments.data.length > 0 ? (
                                        payments.data.map((payment) => (
                                            <tr key={payment.id} className={`hover:bg-gray-50 ${payment.is_overdue ? 'bg-red-50' : ''}`}>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    #{payment.id}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    {payment.supplier_name}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {payment.store?.name || '-'}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    {payment.formatted_total}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <span className={payment.is_overdue ? 'text-red-600 font-medium' : ''}>
                                                        {payment.due_date || '-'}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 font-mono">
                                                    {payment.number_nf || '-'}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <span className={`inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium ${STATUS_COLORS[payment.status] || 'bg-gray-100 text-gray-800'}`}>
                                                        {payment.status_label}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    {payment.requested_by || '-'}
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan="8" className="px-6 py-12 text-center text-gray-500">
                                                Nenhuma ordem de pagamento encontrada.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>

                            {payments.links && payments.last_page > 1 && (
                                <div className="px-6 py-3 border-t border-gray-200 flex justify-between items-center">
                                    <span className="text-sm text-gray-700">
                                        Mostrando {payments.from} a {payments.to} de {payments.total} registros
                                    </span>
                                    <div className="flex space-x-1">
                                        {payments.links.map((link, i) => (
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
                    )}

                    {/* Modal Criar */}
                    {showCreateModal && (
                        <div className="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
                            <div className="bg-white rounded-lg shadow-xl p-6 w-full max-w-lg mx-4">
                                <h3 className="text-lg font-medium text-gray-900 mb-4">Nova Ordem de Pagamento</h3>
                                <form onSubmit={handleCreate} className="space-y-4">
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
                                        <label className="block text-sm font-medium text-gray-700">Fornecedor *</label>
                                        <input
                                            type="text"
                                            value={createForm.data.supplier_name}
                                            onChange={(e) => createForm.setData('supplier_name', e.target.value)}
                                            className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            required
                                        />
                                        {createForm.errors.supplier_name && <p className="mt-1 text-sm text-red-600">{createForm.errors.supplier_name}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700">Descrição</label>
                                        <textarea
                                            value={createForm.data.description}
                                            onChange={(e) => createForm.setData('description', e.target.value)}
                                            rows={2}
                                            className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        />
                                    </div>
                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Valor Total *</label>
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                value={createForm.data.total_value}
                                                onChange={(e) => createForm.setData('total_value', e.target.value)}
                                                className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                required
                                            />
                                            {createForm.errors.total_value && <p className="mt-1 text-sm text-red-600">{createForm.errors.total_value}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Tipo Pagamento</label>
                                            <select
                                                value={createForm.data.payment_type}
                                                onChange={(e) => createForm.setData('payment_type', e.target.value)}
                                                className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            >
                                                <option value="">Selecione</option>
                                                <option value="PIX">PIX</option>
                                                <option value="Transferência">Transferência Bancária</option>
                                                <option value="Boleto">Boleto</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Vencimento</label>
                                            <input
                                                type="date"
                                                value={createForm.data.due_date}
                                                onChange={(e) => createForm.setData('due_date', e.target.value)}
                                                className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700">Parcelas</label>
                                            <input
                                                type="number"
                                                min="1"
                                                value={createForm.data.installments}
                                                onChange={(e) => createForm.setData('installments', e.target.value)}
                                                className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                            />
                                        </div>
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
                                            {createForm.processing ? 'Salvando...' : 'Criar Ordem'}
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
