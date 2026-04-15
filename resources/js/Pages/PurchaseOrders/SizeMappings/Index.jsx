import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import {
    ArrowLeftIcon, ArrowPathIcon, PlusIcon, PencilSquareIcon,
    MagnifyingGlassIcon, XMarkIcon, CheckCircleIcon, ExclamationTriangleIcon,
    ScaleIcon,
} from '@heroicons/react/24/outline';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import DeleteConfirmModal from '@/Components/Shared/DeleteConfirmModal';
import EmptyState from '@/Components/Shared/EmptyState';
import FormSection from '@/Components/Shared/FormSection';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';

export default function Index({ mappings, filters = {}, stats = {}, productSizes = [] }) {
    const [showForm, setShowForm] = useState(false);
    const [editing, setEditing] = useState(null);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleting, setDeleting] = useState(false);

    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');

    const applyFilters = () => {
        router.get(route('purchase-orders.size-mappings.index'), {
            search: search || undefined,
            status: status || undefined,
        }, { preserveState: true });
    };

    const clearFilters = () => {
        setSearch('');
        setStatus('');
        router.get(route('purchase-orders.size-mappings.index'));
    };

    const openCreate = () => {
        setEditing(null);
        setShowForm(true);
    };

    const openEdit = (mapping) => {
        setEditing(mapping);
        setShowForm(true);
    };

    const closeForm = () => {
        setShowForm(false);
        setEditing(null);
    };

    const handleAutoDetect = () => {
        router.post(route('purchase-orders.size-mappings.auto-detect'), {}, {
            preserveScroll: true,
        });
    };

    const handleConfirmDelete = () => {
        if (!deleteTarget) return;
        setDeleting(true);
        router.delete(route('purchase-orders.size-mappings.destroy', deleteTarget.id), {
            onSuccess: () => { setDeleteTarget(null); setDeleting(false); },
            onError: () => setDeleting(false),
        });
    };

    const statsCards = [
        { label: 'Total', value: stats.total || 0, format: 'number', icon: ScaleIcon, color: 'indigo' },
        { label: 'Resolvidos', value: stats.resolved || 0, format: 'number', icon: CheckCircleIcon, color: 'green' },
        { label: 'Pendentes', value: stats.pending || 0, format: 'number', icon: ExclamationTriangleIcon, color: 'yellow', active: (stats.pending || 0) > 0 },
        { label: 'Inativos', value: stats.inactive || 0, format: 'number', color: 'gray' },
    ];

    return (
        <>
            <Head title="Mapeamento de Tamanhos" />

            <div className="py-12">
                <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6 flex items-center gap-3">
                        <Link href={route('purchase-orders.index')}>
                            <Button variant="outline" size="sm" icon={ArrowLeftIcon}>Voltar</Button>
                        </Link>
                        <div className="flex-1">
                            <h1 className="text-2xl font-bold text-gray-900">Mapeamento de Tamanhos</h1>
                            <p className="text-sm text-gray-600">
                                De-para entre labels de tamanho da planilha de importação e tamanhos oficiais do catálogo (CIGAM)
                            </p>
                        </div>
                        <Button variant="outline" onClick={handleAutoDetect} icon={ArrowPathIcon}>
                            Auto-detectar
                        </Button>
                        <Button variant="primary" onClick={openCreate} icon={PlusIcon}>
                            Novo Mapeamento
                        </Button>
                    </div>

                    {/* KPIs */}
                    <div className="mb-6">
                        <StatisticsGrid cards={statsCards} />
                    </div>

                    {/* Filters */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                            <div className="md:col-span-2">
                                <label className="block text-xs font-medium text-gray-700 mb-1">Buscar label</label>
                                <input type="text" placeholder="Ex: 33/34, PP..."
                                    value={search} onChange={(e) => setSearch(e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">Status</label>
                                <select value={status} onChange={(e) => setStatus(e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Todos</option>
                                    <option value="resolved">Resolvidos</option>
                                    <option value="pending">Pendentes</option>
                                    <option value="inactive">Inativos</option>
                                </select>
                            </div>
                            <div className="flex gap-2">
                                <Button variant="primary" size="sm" onClick={applyFilters} icon={MagnifyingGlassIcon}>Filtrar</Button>
                                <Button variant="outline" size="sm" onClick={clearFilters} icon={XMarkIcon}>Limpar</Button>
                            </div>
                        </div>
                    </div>

                    {/* Table */}
                    <div className="bg-white shadow-sm rounded-lg overflow-hidden">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    {['Label da planilha', 'Tamanho oficial (CIGAM)', 'Origem', 'Status', 'Ações'].map((h) => (
                                        <th key={h} className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200">
                                {mappings.data?.length > 0 ? mappings.data.map((m) => (
                                    <tr key={m.id} className={`hover:bg-gray-50 ${!m.product_size_id ? 'bg-yellow-50/40' : ''}`}>
                                        <td className="px-4 py-3 text-sm font-mono font-medium text-gray-900">{m.source_label}</td>
                                        <td className="px-4 py-3 text-sm">
                                            {m.product_size_name ? (
                                                <span className="text-gray-900">{m.product_size_name}</span>
                                            ) : (
                                                <span className="text-yellow-700 italic">Pendente — configure</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            {m.auto_detected ? (
                                                <StatusBadge variant="info">Auto-detectado</StatusBadge>
                                            ) : (
                                                <StatusBadge variant="purple">Manual</StatusBadge>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <StatusBadge variant={m.is_active ? 'success' : 'gray'}>
                                                {m.is_active ? 'Ativo' : 'Inativo'}
                                            </StatusBadge>
                                        </td>
                                        <td className="px-4 py-3">
                                            <ActionButtons
                                                onEdit={() => openEdit(m)}
                                                onDelete={() => setDeleteTarget(m)}
                                            />
                                        </td>
                                    </tr>
                                )) : (
                                    <tr>
                                        <td colSpan="5" className="px-4 py-12">
                                            <EmptyState
                                                icon={ScaleIcon}
                                                title="Nenhum mapeamento"
                                                description="Clique em 'Auto-detectar' para popular mapeamentos óbvios a partir dos tamanhos do CIGAM."
                                                compact
                                            />
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                        {mappings.last_page > 1 && (
                            <div className="px-4 py-3 border-t flex justify-between items-center">
                                <span className="text-sm text-gray-700">{mappings.from} a {mappings.to} de {mappings.total}</span>
                                <div className="flex space-x-1">
                                    {mappings.links.map((link, i) => (
                                        <button key={i} onClick={() => link.url && router.get(link.url)} disabled={!link.url}
                                            className={`px-3 py-1 text-sm rounded ${link.active ? 'bg-indigo-600 text-white' : link.url ? 'bg-white text-gray-700 hover:bg-gray-50 border' : 'bg-gray-100 text-gray-400'}`}
                                            dangerouslySetInnerHTML={{ __html: link.label }} />
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Info box */}
                    <div className="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-900">
                        <h3 className="font-medium mb-2">Como funciona</h3>
                        <ul className="text-xs space-y-1 list-disc ml-4">
                            <li>A planilha v1 do Mercury traz tamanhos como <code className="bg-blue-100 px-1 rounded">PP</code>, <code className="bg-blue-100 px-1 rounded">33</code>, <code className="bg-blue-100 px-1 rounded">33/34</code></li>
                            <li>O catálogo oficial (CIGAM) tem <strong>{productSizes.length}</strong> tamanhos cadastrados em <code className="bg-blue-100 px-1 rounded">product_sizes</code></li>
                            <li>A maioria casa automaticamente por nome (ex: <code className="bg-blue-100 px-1 rounded">PP → PP</code>, <code className="bg-blue-100 px-1 rounded">33 → 33</code>)</li>
                            <li>Tamanhos duplos (<code className="bg-blue-100 px-1 rounded">33/34</code>) não têm equivalente direto — você precisa escolher qual tamanho oficial usar</li>
                            <li>Mapeamentos <strong>pendentes</strong> (sem tamanho oficial) fazem o import <strong>rejeitar</strong> linhas com aquele tamanho</li>
                        </ul>
                    </div>
                </div>
            </div>

            {/* Form modal */}
            {showForm && (
                <MappingFormModal
                    mapping={editing}
                    productSizes={productSizes}
                    onClose={closeForm}
                />
            )}

            <DeleteConfirmModal
                show={deleteTarget !== null}
                onClose={() => setDeleteTarget(null)}
                onConfirm={handleConfirmDelete}
                itemType="mapeamento"
                itemName={deleteTarget?.source_label}
                details={deleteTarget ? [
                    { label: 'Tamanho oficial', value: deleteTarget.product_size_name || '(pendente)' },
                ] : []}
                processing={deleting}
            />
        </>
    );
}

function MappingFormModal({ mapping, productSizes, onClose }) {
    const isEdit = !!mapping;
    const form = useForm({
        source_label: mapping?.source_label || '',
        product_size_id: mapping?.product_size_id || '',
        is_active: mapping?.is_active ?? true,
        notes: mapping?.notes || '',
    });

    const handleSubmit = () => {
        if (isEdit) {
            form.put(route('purchase-orders.size-mappings.update', mapping.id), { onSuccess: onClose });
        } else {
            form.post(route('purchase-orders.size-mappings.store'), { onSuccess: onClose });
        }
    };

    return (
        <StandardModal
            show={true}
            onClose={onClose}
            title={isEdit ? `Editar — ${mapping.source_label}` : 'Novo Mapeamento'}
            headerColor={isEdit ? 'bg-yellow-600' : 'bg-indigo-600'}
            headerIcon={isEdit ? <PencilSquareIcon className="h-5 w-5" /> : <PlusIcon className="h-5 w-5" />}
            onSubmit={handleSubmit}
            footer={(
                <StandardModal.Footer
                    onCancel={onClose}
                    onSubmit="submit"
                    submitLabel={isEdit ? 'Salvar' : 'Criar'}
                    submitColor={isEdit ? 'bg-yellow-600 hover:bg-yellow-700' : undefined}
                    processing={form.processing}
                />
            )}
        >
            <FormSection title="Mapeamento" cols={1}>
                {!isEdit && (
                    <div>
                        <InputLabel value="Label da planilha *" />
                        <TextInput className="mt-1 w-full font-mono uppercase" value={form.data.source_label}
                            onChange={(e) => form.setData('source_label', e.target.value.toUpperCase())}
                            placeholder="Ex: 33/34" required />
                        <p className="mt-1 text-xs text-gray-500">Como o tamanho aparece na coluna da planilha</p>
                        <InputError message={form.errors.source_label} className="mt-1" />
                    </div>
                )}
                <div>
                    <InputLabel value="Tamanho oficial (CIGAM)" />
                    <select value={form.data.product_size_id || ''}
                        onChange={(e) => form.setData('product_size_id', e.target.value || null)}
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="">— Deixar pendente —</option>
                        {productSizes.map((ps) => (
                            <option key={ps.id} value={ps.id}>
                                {ps.name} {ps.cigam_code && `(cigam: ${ps.cigam_code})`}
                            </option>
                        ))}
                    </select>
                    <p className="mt-1 text-xs text-gray-500">
                        Se deixar pendente, itens com esse tamanho serão rejeitados no import
                    </p>
                    <InputError message={form.errors.product_size_id} className="mt-1" />
                </div>
                <div>
                    <InputLabel value="Situação" />
                    <select value={form.data.is_active ? 'true' : 'false'}
                        onChange={(e) => form.setData('is_active', e.target.value === 'true')}
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="true">Ativo</option>
                        <option value="false">Inativo</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Notas" />
                    <textarea rows={2} value={form.data.notes}
                        onChange={(e) => form.setData('notes', e.target.value)}
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        placeholder="Opcional — decisão de mapeamento, convenção, etc." />
                    <InputError message={form.errors.notes} className="mt-1" />
                </div>
            </FormSection>
        </StandardModal>
    );
}
