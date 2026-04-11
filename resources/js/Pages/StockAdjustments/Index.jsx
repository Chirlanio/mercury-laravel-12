import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import Button from '@/Components/Button';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StandardModal from '@/Components/StandardModal';
import FormSection from '@/Components/Shared/FormSection';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { formatDateTime } from '@/Utils/dateHelpers';
import {
    PlusIcon, MagnifyingGlassIcon, XMarkIcon, TrashIcon,
} from '@heroicons/react/24/outline';

const STATUS_VARIANT = {
    pending: 'warning',
    under_analysis: 'info',
    awaiting_response: 'orange',
    balance_transfer: 'purple',
    adjusted: 'success',
    no_adjustment: 'gray',
    cancelled: 'danger',
};

export default function Index({ adjustments, stores = [], filters = {}, statusOptions = {} }) {
    const { hasPermission } = usePermissions();
    const { modals, openModal, closeModal } = useModalManager(['create']);
    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [storeFilter, setStoreFilter] = useState(filters.store_id || '');

    const applyFilters = () => {
        router.get(route('stock-adjustments.index'), {
            search: search || undefined,
            status: statusFilter || undefined,
            store_id: storeFilter || undefined,
        }, { preserveState: true });
    };

    const clearFilters = () => {
        setSearch(''); setStatusFilter(''); setStoreFilter('');
        router.get(route('stock-adjustments.index'), {}, { preserveState: true });
    };

    const hasActiveFilters = search || statusFilter || storeFilter;

    return (
        <>
            <Head title="Ajustes de Estoque" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6">
                        <div className="flex justify-between items-center">
                            <div>
                                <h1 className="text-3xl font-bold text-gray-900">Ajustes de Estoque</h1>
                                <p className="mt-1 text-sm text-gray-600">
                                    Gerencie solicitações de ajuste de estoque das lojas
                                </p>
                            </div>
                            {hasPermission(PERMISSIONS.CREATE_ADJUSTMENTS) && (
                                <Button variant="primary" onClick={() => openModal('create')} icon={PlusIcon}>
                                    Novo Ajuste
                                </Button>
                            )}
                        </div>
                    </div>

                    {/* Filtros */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 sm:grid-cols-4 gap-4 items-end">
                            <div className="relative">
                                <MagnifyingGlassIcon className="absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
                                <input type="text" placeholder="Buscar..." value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                                    className="pl-10 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            </div>
                            <select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)}
                                className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">Todos os Status</option>
                                {Object.entries(statusOptions).map(([key, label]) => (
                                    <option key={key} value={key}>{label}</option>
                                ))}
                            </select>
                            <select value={storeFilter} onChange={(e) => setStoreFilter(e.target.value)}
                                className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">Todas as Lojas</option>
                                {stores.map((s) => (
                                    <option key={s.id} value={s.id}>{s.code} - {s.name}</option>
                                ))}
                            </select>
                            <div className="flex gap-2">
                                <Button variant="primary" size="sm" onClick={applyFilters} icon={MagnifyingGlassIcon}>
                                    Filtrar
                                </Button>
                                <Button variant="outline" size="sm" onClick={clearFilters} disabled={!hasActiveFilters} icon={XMarkIcon}>
                                    Limpar
                                </Button>
                            </div>
                        </div>
                    </div>

                    {/* Tabela */}
                    <div className="bg-white shadow-sm rounded-lg overflow-hidden">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Loja</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Colaborador</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Itens</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Criado por</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {adjustments.data?.length > 0 ? (
                                    adjustments.data.map((adj) => (
                                        <tr key={adj.id} className="hover:bg-gray-50">
                                            <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">#{adj.id}</td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{adj.store?.name || '-'}</td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{adj.employee || '-'}</td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{adj.items_count} item(s)</td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <StatusBadge variant={STATUS_VARIANT[adj.status] || 'gray'}>
                                                    {adj.status_label}
                                                </StatusBadge>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{adj.created_by || '-'}</td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{formatDateTime(adj.created_at)}</td>
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
                                        <button key={i} onClick={() => link.url && router.get(link.url)} disabled={!link.url}
                                            className={`px-3 py-1 text-sm rounded ${
                                                link.active ? 'bg-indigo-600 text-white'
                                                : link.url ? 'bg-white text-gray-700 hover:bg-gray-50 border'
                                                : 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                            }`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Modal Criar */}
            {modals.create && (
                <CreateModal
                    stores={stores}
                    onClose={() => closeModal('create')}
                />
            )}
        </>
    );
}

function CreateModal({ stores, onClose }) {
    const form = useForm({
        store_id: '',
        observation: '',
        items: [{ reference: '', size: '', is_adjustment: true }],
    });

    const addItem = () => {
        form.setData('items', [...form.data.items, { reference: '', size: '', is_adjustment: true }]);
    };

    const removeItem = (index) => {
        if (form.data.items.length <= 1) return;
        form.setData('items', form.data.items.filter((_, i) => i !== index));
    };

    const updateItem = (index, field, value) => {
        const items = [...form.data.items];
        items[index] = { ...items[index], [field]: value };
        form.setData('items', items);
    };

    const handleSubmit = () => {
        form.post(route('stock-adjustments.store'), {
            onSuccess: () => { onClose(); form.reset(); },
        });
    };

    return (
        <StandardModal
            show={true}
            onClose={onClose}
            title="Novo Ajuste de Estoque"
            headerColor="bg-indigo-600"
            headerIcon={<PlusIcon className="h-5 w-5" />}
            maxWidth="2xl"
            onSubmit={handleSubmit}
            footer={
                <StandardModal.Footer
                    onCancel={onClose}
                    onSubmit="submit"
                    submitLabel="Criar Ajuste"
                    processing={form.processing}
                />
            }
        >
            <FormSection title="Informações Gerais" cols={1}>
                <div>
                    <InputLabel value="Loja *" />
                    <select value={form.data.store_id} onChange={(e) => form.setData('store_id', e.target.value)}
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
                        <option value="">Selecione</option>
                        {stores.map((s) => <option key={s.id} value={s.id}>{s.code} - {s.name}</option>)}
                    </select>
                </div>
                <div>
                    <InputLabel value="Observação" />
                    <textarea value={form.data.observation} onChange={(e) => form.setData('observation', e.target.value)}
                        rows={2} className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                </div>
            </FormSection>

            <StandardModal.Section title="Itens">
                <div className="space-y-2">
                    {form.data.items.map((item, index) => (
                        <div key={index} className="flex gap-2 items-start">
                            <TextInput placeholder="Referência *" value={item.reference} className="flex-1"
                                onChange={(e) => updateItem(index, 'reference', e.target.value)} required />
                            <TextInput placeholder="Tamanho" value={item.size} className="w-24"
                                onChange={(e) => updateItem(index, 'size', e.target.value)} />
                            {form.data.items.length > 1 && (
                                <button type="button" onClick={() => removeItem(index)}
                                    className="text-red-500 hover:text-red-700 p-2">
                                    <TrashIcon className="h-4 w-4" />
                                </button>
                            )}
                        </div>
                    ))}
                </div>
                <div className="mt-3">
                    <Button variant="outline" size="xs" onClick={addItem} icon={PlusIcon}>
                        Adicionar Item
                    </Button>
                </div>
            </StandardModal.Section>
        </StandardModal>
    );
}
