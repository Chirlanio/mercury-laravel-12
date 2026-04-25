import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
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
    PlusIcon, MagnifyingGlassIcon, XMarkIcon, PencilSquareIcon, DocumentTextIcon,
} from '@heroicons/react/24/outline';

export default function Index({ certificates, employees = [], filters = {} }) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission(PERMISSIONS.CREATE_MEDICAL_CERTIFICATES);
    const canEdit = hasPermission(PERMISSIONS.EDIT_MEDICAL_CERTIFICATES);
    const canDelete = hasPermission(PERMISSIONS.DELETE_MEDICAL_CERTIFICATES);

    const { modals, selected, openModal, closeModal } = useModalManager(['create', 'edit', 'view']);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleting, setDeleting] = useState(false);

    const [search, setSearch] = useState(filters.search || '');
    const [employeeFilter, setEmployeeFilter] = useState(filters.employee_id || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');

    const applyFilters = () => {
        router.get(route('medical-certificates.index'), {
            search: search || undefined, employee_id: employeeFilter || undefined, status: statusFilter || undefined,
        }, { preserveState: true });
    };

    const clearFilters = () => {
        setSearch(''); setEmployeeFilter(''); setStatusFilter('');
        router.get(route('medical-certificates.index'), {}, { preserveState: true });
    };

    const hasActiveFilters = search || employeeFilter || statusFilter;

    const openEdit = (item) => {
        fetch(route('medical-certificates.show', item.id))
            .then(r => r.json())
            .then(data => openModal('edit', data));
    };

    const openView = (item) => {
        fetch(route('medical-certificates.show', item.id))
            .then(r => r.json())
            .then(data => openModal('view', data));
    };

    const handleConfirmDelete = () => {
        if (!deleteTarget) return;
        setDeleting(true);
        router.delete(route('medical-certificates.destroy', deleteTarget.id), {
            onSuccess: () => { setDeleteTarget(null); setDeleting(false); },
            onError: () => setDeleting(false),
        });
    };

    return (
        <>
            <Head title="Atestados Médicos" />
            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <PageHeader
                        title="Atestados Médicos"
                        subtitle="Gerencie atestados médicos dos funcionários"
                        actions={[
                            {
                                type: 'create',
                                label: 'Novo Atestado',
                                onClick: () => openModal('create'),
                                visible: canCreate,
                            },
                        ]}
                    />

                    {/* Filtros */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
                                <input type="text" placeholder="Nome, CID, médico..." value={search}
                                    onChange={e => setSearch(e.target.value)}
                                    onKeyDown={e => e.key === 'Enter' && applyFilters()}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Funcionário</label>
                                <select value={employeeFilter} onChange={e => setEmployeeFilter(e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Todos</option>
                                    {employees.map(e => <option key={e.id} value={e.id}>{e.name}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Situação</label>
                                <select value={statusFilter} onChange={e => setStatusFilter(e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Todos</option>
                                    <option value="active">Vigente</option>
                                    <option value="expired">Expirado</option>
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
                                    {['Funcionário', 'Período', 'Dias', 'CID', 'Médico', 'Status', 'Ações'].map(h => (
                                        <th key={h} className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200">
                                {certificates.data?.length > 0 ? certificates.data.map(c => (
                                    <tr key={c.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3 text-sm font-medium text-gray-900">{c.employee_name}</td>
                                        <td className="px-4 py-3 text-sm text-gray-500">{c.start_date} - {c.end_date}</td>
                                        <td className="px-4 py-3 text-sm text-gray-500">{c.days}</td>
                                        <td className="px-4 py-3 text-sm text-gray-500 font-mono">{c.cid_code || '-'}</td>
                                        <td className="px-4 py-3 text-sm text-gray-500">{c.doctor_name || '-'}</td>
                                        <td className="px-4 py-3">
                                            <StatusBadge variant={c.is_active ? 'success' : 'gray'}>
                                                {c.is_active ? 'Vigente' : 'Expirado'}
                                            </StatusBadge>
                                        </td>
                                        <td className="px-4 py-3">
                                            <ActionButtons
                                                onView={() => openView(c)}
                                                onEdit={canEdit ? () => openEdit(c) : null}
                                                onDelete={canDelete ? () => setDeleteTarget(c) : null}
                                            />
                                        </td>
                                    </tr>
                                )) : (
                                    <tr><td colSpan="7" className="px-4 py-12 text-center text-gray-500">Nenhum atestado encontrado.</td></tr>
                                )}
                            </tbody>
                        </table>
                        {certificates.last_page > 1 && (
                            <div className="px-4 py-3 border-t flex justify-between items-center">
                                <span className="text-sm text-gray-700">{certificates.from} a {certificates.to} de {certificates.total}</span>
                                <div className="flex space-x-1">
                                    {certificates.links.map((link, i) => (
                                        <button key={i} onClick={() => link.url && router.get(link.url)} disabled={!link.url}
                                            className={`px-3 py-1 text-sm rounded ${link.active ? 'bg-indigo-600 text-white' : link.url ? 'bg-white text-gray-700 hover:bg-gray-50 border' : 'bg-gray-100 text-gray-400'}`}
                                            dangerouslySetInnerHTML={{ __html: link.label }} />
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Modal Criar/Editar */}
                    <CertificateFormModal
                        show={modals.create}
                        employees={employees}
                        onClose={() => closeModal('create')}
                    />
                    <CertificateFormModal
                        show={modals.edit && selected !== null}
                        certificate={selected}
                        employees={employees}
                        onClose={() => closeModal('edit')}
                    />

                    {/* Modal Visualizar */}
                    {modals.view && selected && (
                        <CertificateViewModal certificate={selected} onClose={() => closeModal('view')} />
                    )}

                    {/* Delete Confirm */}
                    <DeleteConfirmModal
                        show={deleteTarget !== null}
                        onClose={() => setDeleteTarget(null)}
                        onConfirm={handleConfirmDelete}
                        itemType="atestado"
                        itemName={deleteTarget?.employee_name}
                        details={[
                            { label: 'Período', value: deleteTarget ? `${deleteTarget.start_date} - ${deleteTarget.end_date}` : null },
                            { label: 'CID', value: deleteTarget?.cid_code },
                        ]}
                        processing={deleting}
                    />
                </div>
            </div>
        </>
    );
}

function CertificateFormModal({ show, certificate = null, employees, onClose }) {
    const isEdit = !!certificate;
    const form = useForm({
        employee_id: certificate?.employee_id || '',
        start_date: certificate?.start_date || '',
        end_date: certificate?.end_date || '',
        cid_code: certificate?.cid_code || '',
        cid_description: certificate?.cid_description || '',
        doctor_name: certificate?.doctor_name || '',
        doctor_crm: certificate?.doctor_crm || '',
        notes: certificate?.notes || '',
    });

    const handleSubmit = () => {
        if (isEdit) form.put(route('medical-certificates.update', certificate.id), { onSuccess: onClose });
        else form.post(route('medical-certificates.store'), { onSuccess: onClose });
    };

    return (
        <StandardModal show={show} onClose={onClose}
            title={isEdit ? 'Editar Atestado' : 'Novo Atestado Médico'}
            headerColor={isEdit ? 'bg-yellow-600' : 'bg-indigo-600'}
            headerIcon={isEdit ? <PencilSquareIcon className="h-5 w-5" /> : <PlusIcon className="h-5 w-5" />}
            onSubmit={handleSubmit}
            footer={
                <StandardModal.Footer onCancel={onClose} onSubmit="submit"
                    submitLabel={isEdit ? 'Salvar Alterações' : 'Cadastrar Atestado'}
                    submitColor={isEdit ? 'bg-yellow-600 hover:bg-yellow-700' : undefined}
                    processing={form.processing} />
            }>

            <FormSection title="Funcionário e Período" cols={2}>
                <div className="col-span-full sm:col-span-1">
                    <InputLabel value="Funcionário *" />
                    <select value={form.data.employee_id} onChange={e => form.setData('employee_id', e.target.value)} required
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="">Selecione...</option>
                        {employees.map(e => <option key={e.id} value={e.id}>{e.name}</option>)}
                    </select>
                    <InputError message={form.errors.employee_id} className="mt-1" />
                </div>
                <div />
                <div>
                    <InputLabel value="Data Início *" />
                    <TextInput type="date" className="mt-1 w-full" value={form.data.start_date}
                        onChange={e => form.setData('start_date', e.target.value)} required />
                    <InputError message={form.errors.start_date} className="mt-1" />
                </div>
                <div>
                    <InputLabel value="Data Fim *" />
                    <TextInput type="date" className="mt-1 w-full" value={form.data.end_date}
                        onChange={e => form.setData('end_date', e.target.value)} required />
                    <InputError message={form.errors.end_date} className="mt-1" />
                </div>
            </FormSection>

            <FormSection title="Diagnóstico" cols={2}>
                <div>
                    <InputLabel value="CID" />
                    <TextInput className="mt-1 w-full" value={form.data.cid_code}
                        onChange={e => form.setData('cid_code', e.target.value)} placeholder="Ex: J11" />
                </div>
                <div>
                    <InputLabel value="Descrição CID" />
                    <TextInput className="mt-1 w-full" value={form.data.cid_description}
                        onChange={e => form.setData('cid_description', e.target.value)} placeholder="Descrição do CID" />
                </div>
            </FormSection>

            <FormSection title="Médico" cols={2}>
                <div>
                    <InputLabel value="Nome do Médico" />
                    <TextInput className="mt-1 w-full" value={form.data.doctor_name}
                        onChange={e => form.setData('doctor_name', e.target.value)} placeholder="Nome do médico" />
                </div>
                <div>
                    <InputLabel value="CRM" />
                    <TextInput className="mt-1 w-full" value={form.data.doctor_crm}
                        onChange={e => form.setData('doctor_crm', e.target.value)} placeholder="CRM do médico" />
                </div>
            </FormSection>

            <FormSection title="Observações" cols={1}>
                <div>
                    <textarea value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} rows="3"
                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                </div>
            </FormSection>
        </StandardModal>
    );
}

function CertificateViewModal({ certificate, onClose }) {
    const headerBadges = [
        { text: certificate.is_active ? 'Vigente' : 'Expirado', className: certificate.is_active ? 'bg-emerald-500/20 text-white' : 'bg-white/20 text-white' },
    ];

    return (
        <StandardModal show={true} onClose={onClose} title="Detalhes do Atestado"
            subtitle={certificate.employee_name}
            headerColor="bg-gray-700" headerIcon={<DocumentTextIcon className="h-5 w-5" />}
            headerBadges={headerBadges}
            footer={<StandardModal.Footer onCancel={onClose} cancelLabel="Fechar" />}>

            <StandardModal.Section title="Informações do Atestado">
                <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <StandardModal.Field label="Funcionário" value={certificate.employee_name} />
                    <div>
                        <p className="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Status</p>
                        <div className="mt-0.5">
                            <StatusBadge variant={certificate.is_active ? 'success' : 'gray'}>
                                {certificate.is_active ? 'Vigente' : 'Expirado'}
                            </StatusBadge>
                        </div>
                    </div>
                    <StandardModal.Field label="Dias" value={certificate.days} />
                    <StandardModal.Field label="Início" value={certificate.start_date_formatted} />
                    <StandardModal.Field label="Fim" value={certificate.end_date_formatted} />
                    <StandardModal.Field label="CID" value={certificate.cid_code ? `${certificate.cid_code} - ${certificate.cid_description || ''}` : null} mono />
                    <StandardModal.Field label="Médico" value={certificate.doctor_name} />
                    <StandardModal.Field label="CRM" value={certificate.doctor_crm} mono />
                </div>
            </StandardModal.Section>

            {certificate.notes && (
                <StandardModal.Section title="Observações">
                    <p className="text-sm text-gray-900 whitespace-pre-line">{certificate.notes}</p>
                </StandardModal.Section>
            )}

            <div className="flex justify-between text-xs text-gray-400 pt-2">
                <span>Cadastrado por {certificate.created_by || '-'} em {formatDateTime(certificate.created_at)}</span>
            </div>
        </StandardModal>
    );
}
