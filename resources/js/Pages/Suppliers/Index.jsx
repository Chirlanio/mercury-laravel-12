import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import { maskCpfCnpj, maskPhone, maskCep } from '@/Hooks/useMasks';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import PageHeader from '@/Components/Shared/PageHeader';
import StatusBadge from '@/Components/Shared/StatusBadge';
import DeleteConfirmModal from '@/Components/Shared/DeleteConfirmModal';
import StandardModal from '@/Components/StandardModal';
import FormSection from '@/Components/Shared/FormSection';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import { formatDateTime } from '@/Utils/dateHelpers';
import {
    PlusIcon, MagnifyingGlassIcon, XMarkIcon, PencilSquareIcon,
    BuildingOfficeIcon, EnvelopeIcon, ClockIcon, MapPinIcon,
    CurrencyDollarIcon, DocumentTextIcon,
} from '@heroicons/react/24/outline';

export default function Index({ suppliers, filters = {} }) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission(PERMISSIONS.CREATE_SUPPLIERS);
    const canEdit = hasPermission(PERMISSIONS.EDIT_SUPPLIERS);
    const canDelete = hasPermission(PERMISSIONS.DELETE_SUPPLIERS);

    const { modals, selected, openModal, closeModal } = useModalManager(['create', 'edit', 'view']);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleting, setDeleting] = useState(false);
    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');

    const applyFilters = () => {
        router.get(route('suppliers.index'), {
            search: search || undefined, status: statusFilter || undefined,
        }, { preserveState: true });
    };

    const clearFilters = () => {
        setSearch(''); setStatusFilter('');
        router.get(route('suppliers.index'), {}, { preserveState: true });
    };

    const hasActiveFilters = search || statusFilter;

    const openEdit = (s) => {
        fetch(route('suppliers.show', s.id)).then(r => r.json()).then(data => openModal('edit', data));
    };

    const openView = (s) => {
        fetch(route('suppliers.show', s.id)).then(r => r.json()).then(data => openModal('view', data));
    };

    const handleConfirmDelete = () => {
        if (!deleteTarget) return;
        setDeleting(true);
        router.delete(route('suppliers.destroy', deleteTarget.id), {
            onSuccess: () => { setDeleteTarget(null); setDeleting(false); },
            onError: () => setDeleting(false),
        });
    };

    return (
        <>
            <Head title="Fornecedores" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <PageHeader
                        title="Fornecedores"
                        subtitle="Gerencie e visualize informações dos fornecedores"
                        actions={[
                            {
                                type: 'create',
                                label: 'Novo Fornecedor',
                                onClick: () => openModal('create'),
                                visible: canCreate,
                            },
                        ]}
                    />

                    {/* Filtros */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
                                <input type="text" placeholder="Nome, CNPJ, e-mail..." value={search}
                                    onChange={e => setSearch(e.target.value)}
                                    onKeyDown={e => e.key === 'Enter' && applyFilters()}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Situação</label>
                                <select value={statusFilter} onChange={e => setStatusFilter(e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Todos</option>
                                    <option value="active">Ativos</option>
                                    <option value="inactive">Inativos</option>
                                </select>
                            </div>
                            <Button variant="primary" size="sm" onClick={applyFilters} icon={MagnifyingGlassIcon}>Filtrar</Button>
                            <Button variant="outline" size="sm" onClick={clearFilters} disabled={!hasActiveFilters} icon={XMarkIcon}>Limpar</Button>
                        </div>
                    </div>

                    {/* Tabela */}
                    <div className="bg-white shadow-sm rounded-lg overflow-hidden">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    {['Nome Fantasia', 'Razão Social', 'CNPJ/CPF', 'Status', 'Ações'].map(h => (
                                        <th key={h} className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200">
                                {suppliers.data?.length > 0 ? suppliers.data.map(s => (
                                    <tr key={s.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3 text-sm font-medium text-gray-900">{s.nome_fantasia}</td>
                                        <td className="px-4 py-3 text-sm text-gray-500">{s.razao_social}</td>
                                        <td className="px-4 py-3 text-sm text-gray-500 font-mono">{s.cnpj_formatted}</td>
                                        <td className="px-4 py-3">
                                            <StatusBadge variant={s.is_active ? 'success' : 'danger'}>
                                                {s.is_active ? 'Ativo' : 'Inativo'}
                                            </StatusBadge>
                                        </td>
                                        <td className="px-4 py-3">
                                            <ActionButtons
                                                onView={() => openView(s)}
                                                onEdit={canEdit ? () => openEdit(s) : null}
                                                onDelete={canDelete ? () => setDeleteTarget(s) : null}
                                            />
                                        </td>
                                    </tr>
                                )) : (
                                    <tr><td colSpan="5" className="px-4 py-12 text-center text-gray-500">Nenhum fornecedor encontrado.</td></tr>
                                )}
                            </tbody>
                        </table>
                        {suppliers.last_page > 1 && (
                            <div className="px-4 py-3 border-t flex justify-between items-center">
                                <span className="text-sm text-gray-700">{suppliers.from} a {suppliers.to} de {suppliers.total}</span>
                                <div className="flex space-x-1">
                                    {suppliers.links.map((link, i) => (
                                        <button key={i} onClick={() => link.url && router.get(link.url)} disabled={!link.url}
                                            className={`px-3 py-1 text-sm rounded ${link.active ? 'bg-indigo-600 text-white' : link.url ? 'bg-white text-gray-700 hover:bg-gray-50 border' : 'bg-gray-100 text-gray-400'}`}
                                            dangerouslySetInnerHTML={{ __html: link.label }} />
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Modais */}
                    <SupplierFormModal show={modals.create} onClose={() => closeModal('create')} />
                    <SupplierFormModal show={modals.edit && selected !== null} supplier={selected}
                        onClose={() => closeModal('edit')} />

                    {modals.view && selected && (
                        <SupplierViewModal supplier={selected} onClose={() => closeModal('view')} />
                    )}

                    <DeleteConfirmModal
                        show={deleteTarget !== null}
                        onClose={() => setDeleteTarget(null)}
                        onConfirm={handleConfirmDelete}
                        itemType="fornecedor"
                        itemName={deleteTarget?.nome_fantasia}
                        details={[
                            { label: 'CNPJ/CPF', value: deleteTarget?.cnpj_formatted },
                            { label: 'Razão Social', value: deleteTarget?.razao_social },
                        ]}
                        processing={deleting}
                    />
                </div>
            </div>
        </>
    );
}

function SupplierFormModal({ show, supplier = null, onClose }) {
    const isEdit = !!supplier;
    const form = useForm({
        razao_social: supplier?.razao_social || '',
        nome_fantasia: supplier?.nome_fantasia || '',
        cnpj: supplier ? maskCpfCnpj(supplier.cnpj || '') : '',
        contact: supplier ? maskPhone(supplier.contact || '') : '',
        email: supplier?.email || '',
        payment_terms_default: supplier?.payment_terms_default || '',
        address: supplier?.address || '',
        city: supplier?.city || '',
        state: supplier?.state || '',
        zip: supplier ? maskCep(supplier.zip || '') : '',
        notes: supplier?.notes || '',
        is_active: supplier?.is_active ?? true,
    });

    const handleSubmit = () => {
        if (isEdit) form.put(route('suppliers.update', supplier.id), { onSuccess: onClose });
        else form.post(route('suppliers.store'), { onSuccess: onClose });
    };

    return (
        <StandardModal show={show} onClose={onClose}
            title={isEdit ? 'Editar Fornecedor' : 'Novo Fornecedor'}
            headerColor={isEdit ? 'bg-yellow-600' : 'bg-indigo-600'}
            headerIcon={isEdit ? <PencilSquareIcon className="h-5 w-5" /> : <PlusIcon className="h-5 w-5" />}
            onSubmit={handleSubmit}
            footer={
                <StandardModal.Footer onCancel={onClose} onSubmit="submit"
                    submitLabel={isEdit ? 'Salvar Alterações' : 'Cadastrar Fornecedor'}
                    submitColor={isEdit ? 'bg-yellow-600 hover:bg-yellow-700' : undefined}
                    processing={form.processing} />
            }>

            <FormSection title="Identificação" cols={2}>
                <div>
                    <InputLabel value="Razão Social *" />
                    <TextInput className="mt-1 w-full" value={form.data.razao_social}
                        onChange={e => form.setData('razao_social', e.target.value)} required />
                    <InputError message={form.errors.razao_social} className="mt-1" />
                </div>
                <div>
                    <InputLabel value="Nome Fantasia *" />
                    <TextInput className="mt-1 w-full" value={form.data.nome_fantasia}
                        onChange={e => form.setData('nome_fantasia', e.target.value)} required />
                    <InputError message={form.errors.nome_fantasia} className="mt-1" />
                </div>
                <div>
                    <InputLabel value="CNPJ/CPF *" />
                    <TextInput className="mt-1 w-full" value={form.data.cnpj}
                        onChange={e => form.setData('cnpj', maskCpfCnpj(e.target.value))}
                        placeholder="00.000.000/0000-00" required />
                    <InputError message={form.errors.cnpj} className="mt-1" />
                </div>
                {isEdit && (
                    <div>
                        <InputLabel value="Situação" />
                        <select value={form.data.is_active ? 'true' : 'false'}
                            onChange={e => form.setData('is_active', e.target.value === 'true')}
                            className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="true">Ativo</option>
                            <option value="false">Inativo</option>
                        </select>
                    </div>
                )}
            </FormSection>

            <FormSection title="Contato" cols={2}>
                <div>
                    <InputLabel value="Telefone *" />
                    <TextInput className="mt-1 w-full" value={form.data.contact}
                        onChange={e => form.setData('contact', maskPhone(e.target.value))}
                        placeholder="(00) 00000-0000" required />
                    <InputError message={form.errors.contact} className="mt-1" />
                </div>
                <div>
                    <InputLabel value="E-mail *" />
                    <TextInput type="email" className="mt-1 w-full" value={form.data.email}
                        onChange={e => form.setData('email', e.target.value)}
                        placeholder="email@fornecedor.com" required />
                    <InputError message={form.errors.email} className="mt-1" />
                </div>
            </FormSection>

            <FormSection title="Endereço" cols={4}>
                <div className="col-span-4 sm:col-span-1">
                    <InputLabel value="CEP" />
                    <TextInput className="mt-1 w-full" value={form.data.zip}
                        onChange={e => form.setData('zip', maskCep(e.target.value))}
                        placeholder="00000-000" />
                    <InputError message={form.errors.zip} className="mt-1" />
                </div>
                <div className="col-span-4 sm:col-span-3">
                    <InputLabel value="Logradouro" />
                    <TextInput className="mt-1 w-full" value={form.data.address}
                        onChange={e => form.setData('address', e.target.value)}
                        placeholder="Rua, número, bairro..." />
                    <InputError message={form.errors.address} className="mt-1" />
                </div>
                <div className="col-span-4 sm:col-span-3">
                    <InputLabel value="Cidade" />
                    <TextInput className="mt-1 w-full" value={form.data.city}
                        onChange={e => form.setData('city', e.target.value)} />
                    <InputError message={form.errors.city} className="mt-1" />
                </div>
                <div className="col-span-4 sm:col-span-1">
                    <InputLabel value="UF" />
                    <TextInput className="mt-1 w-full uppercase" value={form.data.state}
                        onChange={e => form.setData('state', e.target.value.toUpperCase().slice(0, 2))}
                        maxLength={2} placeholder="SP" />
                    <InputError message={form.errors.state} className="mt-1" />
                </div>
            </FormSection>

            <FormSection title="Condições Comerciais" cols={1}>
                <div>
                    <InputLabel value="Prazos de pagamento padrão" />
                    <TextInput className="mt-1 w-full" value={form.data.payment_terms_default}
                        onChange={e => form.setData('payment_terms_default', e.target.value)}
                        placeholder="Ex: 30/60/90" />
                    <p className="mt-1 text-xs text-gray-500">
                        Prazos em dias separados por barra. Usado como padrão ao criar ordens de compra.
                    </p>
                    <InputError message={form.errors.payment_terms_default} className="mt-1" />
                </div>
            </FormSection>

            <FormSection title="Observações" cols={1}>
                <div>
                    <InputLabel value="Notas" />
                    <textarea
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        rows={3}
                        value={form.data.notes}
                        onChange={e => form.setData('notes', e.target.value)}
                        placeholder="Informações adicionais sobre o fornecedor..."
                    />
                    <InputError message={form.errors.notes} className="mt-1" />
                </div>
            </FormSection>
        </StandardModal>
    );
}

function SupplierViewModal({ supplier, onClose }) {
    const headerBadges = [
        { text: supplier.is_active ? 'Ativo' : 'Inativo', className: supplier.is_active ? 'bg-emerald-500/20 text-white' : 'bg-red-500/20 text-white' },
    ];

    return (
        <StandardModal show={true} onClose={onClose} title="Detalhes do Fornecedor"
            subtitle={supplier.nome_fantasia}
            headerColor="bg-gray-700" headerIcon={<BuildingOfficeIcon className="h-5 w-5" />}
            headerBadges={headerBadges}
            footer={<StandardModal.Footer onCancel={onClose} cancelLabel="Fechar" />}>

            <StandardModal.Section title="Identificação" icon={<BuildingOfficeIcon className="h-4 w-4" />}>
                <div className="grid grid-cols-2 gap-4">
                    <StandardModal.Field label="Razão Social" value={supplier.razao_social} />
                    <StandardModal.Field label="Nome Fantasia" value={supplier.nome_fantasia} />
                    <StandardModal.Field label="CNPJ/CPF" value={supplier.cnpj_formatted} mono />
                    <div>
                        <p className="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Situação</p>
                        <div className="mt-0.5">
                            <StatusBadge variant={supplier.is_active ? 'success' : 'danger'}>
                                {supplier.is_active ? 'Ativo' : 'Inativo'}
                            </StatusBadge>
                        </div>
                    </div>
                </div>
            </StandardModal.Section>

            <StandardModal.Section title="Contato" icon={<EnvelopeIcon className="h-4 w-4" />}>
                <div className="grid grid-cols-2 gap-4">
                    <StandardModal.Field label="Telefone" value={supplier.contact_formatted} />
                    <StandardModal.Field label="E-mail" value={supplier.email} />
                </div>
            </StandardModal.Section>

            {(supplier.address || supplier.city || supplier.state || supplier.zip) && (
                <StandardModal.Section title="Endereço" icon={<MapPinIcon className="h-4 w-4" />}>
                    <div className="grid grid-cols-4 gap-4">
                        <StandardModal.Field label="CEP" value={supplier.zip ? maskCep(supplier.zip) : null} />
                        <div className="col-span-3">
                            <StandardModal.Field label="Logradouro" value={supplier.address} />
                        </div>
                        <div className="col-span-3">
                            <StandardModal.Field label="Cidade" value={supplier.city} />
                        </div>
                        <StandardModal.Field label="UF" value={supplier.state} />
                    </div>
                </StandardModal.Section>
            )}

            {supplier.payment_terms_default && (
                <StandardModal.Section title="Condições Comerciais" icon={<CurrencyDollarIcon className="h-4 w-4" />}>
                    <StandardModal.Field label="Prazos de pagamento padrão" value={supplier.payment_terms_default} />
                </StandardModal.Section>
            )}

            {supplier.notes && (
                <StandardModal.Section title="Observações" icon={<DocumentTextIcon className="h-4 w-4" />}>
                    <p className="text-sm text-gray-700 whitespace-pre-wrap">{supplier.notes}</p>
                </StandardModal.Section>
            )}

            <StandardModal.Section title="Registro" icon={<ClockIcon className="h-4 w-4" />}>
                <div className="grid grid-cols-2 gap-4">
                    <StandardModal.Field label="Cadastrado em" value={formatDateTime(supplier.created_at)} />
                    <StandardModal.Field label="Última atualização" value={formatDateTime(supplier.updated_at)} />
                </div>
            </StandardModal.Section>
        </StandardModal>
    );
}
