import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import StatusBadge from '@/Components/Shared/StatusBadge';
import DeleteConfirmModal from '@/Components/Shared/DeleteConfirmModal';
import PageHeader from '@/Components/Shared/PageHeader';
import StandardModal from '@/Components/StandardModal';
import FormSection from '@/Components/Shared/FormSection';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import Checkbox from '@/Components/Checkbox';
import { formatDateTime } from '@/Utils/dateHelpers';
import {
    PlusIcon, MagnifyingGlassIcon, XMarkIcon, PencilSquareIcon, DocumentTextIcon,
} from '@heroicons/react/24/outline';

export default function Index({ absences, employees = [], filters = {}, typeOptions = [] }) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission(PERMISSIONS.CREATE_ABSENCES);
    const canEdit = hasPermission(PERMISSIONS.EDIT_ABSENCES);
    const canDelete = hasPermission(PERMISSIONS.DELETE_ABSENCES);

    const { modals, selected, openModal, closeModal } = useModalManager(['create', 'edit', 'view']);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleting, setDeleting] = useState(false);

    const [search, setSearch] = useState(filters.search || '');
    const [employeeFilter, setEmployeeFilter] = useState(filters.employee_id || '');
    const [typeFilter, setTypeFilter] = useState(filters.type || '');
    const [justifiedFilter, setJustifiedFilter] = useState(filters.justified || '');

    const applyFilters = () => {
        router.get(route('absences.index'), {
            search: search || undefined, employee_id: employeeFilter || undefined,
            type: typeFilter || undefined, justified: justifiedFilter || undefined,
        }, { preserveState: true });
    };

    const clearFilters = () => {
        setSearch(''); setEmployeeFilter(''); setTypeFilter(''); setJustifiedFilter('');
        router.get(route('absences.index'), {}, { preserveState: true });
    };

    const hasActiveFilters = search || employeeFilter || typeFilter || justifiedFilter;

    const openEdit = (item) => {
        fetch(route('absences.show', item.id)).then(r => r.json()).then(data => openModal('edit', data));
    };

    const openView = (item) => {
        fetch(route('absences.show', item.id)).then(r => r.json()).then(data => openModal('view', data));
    };

    const handleConfirmDelete = () => {
        if (!deleteTarget) return;
        setDeleting(true);
        router.delete(route('absences.destroy', deleteTarget.id), {
            onSuccess: () => { setDeleteTarget(null); setDeleting(false); },
            onError: () => setDeleting(false),
        });
    };

    return (
        <>
            <Head title="Controle de Faltas" />
            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <PageHeader
                        title="Controle de Faltas"
                        subtitle="Registre e acompanhe faltas dos funcionários"
                        actions={[
                            {
                                label: 'Registrar Falta',
                                icon: PlusIcon,
                                variant: 'primary',
                                onClick: () => openModal('create'),
                                visible: canCreate,
                            },
                        ]}
                    />


                    {/* Filtros */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
                                <input type="text" placeholder="Nome..." value={search}
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
                                <label className="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                                <select value={typeFilter} onChange={e => setTypeFilter(e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Todos</option>
                                    {typeOptions.map(t => <option key={t.value} value={t.value}>{t.label}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Justificativa</label>
                                <select value={justifiedFilter} onChange={e => setJustifiedFilter(e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Todas</option>
                                    <option value="yes">Justificadas</option>
                                    <option value="no">Injustificadas</option>
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
                                    {['Funcionário', 'Data', 'Tipo', 'Justificativa', 'Motivo', 'Ações'].map(h => (
                                        <th key={h} className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200">
                                {absences.data?.length > 0 ? absences.data.map(a => (
                                    <tr key={a.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3 text-sm font-medium text-gray-900">{a.employee_name}</td>
                                        <td className="px-4 py-3 text-sm text-gray-500">{a.absence_date}</td>
                                        <td className="px-4 py-3 text-sm text-gray-500">{a.type_label}</td>
                                        <td className="px-4 py-3">
                                            <StatusBadge variant={a.is_justified ? 'success' : 'danger'}>
                                                {a.is_justified ? 'Justificada' : 'Injustificada'}
                                            </StatusBadge>
                                        </td>
                                        <td className="px-4 py-3 text-sm text-gray-500 max-w-xs truncate">{a.reason || '-'}</td>
                                        <td className="px-4 py-3">
                                            <ActionButtons
                                                onView={() => openView(a)}
                                                onEdit={canEdit ? () => openEdit(a) : null}
                                                onDelete={canDelete ? () => setDeleteTarget(a) : null}
                                            />
                                        </td>
                                    </tr>
                                )) : (
                                    <tr><td colSpan="6" className="px-4 py-12 text-center text-gray-500">Nenhuma falta registrada.</td></tr>
                                )}
                            </tbody>
                        </table>
                        {absences.last_page > 1 && (
                            <div className="px-4 py-3 border-t flex justify-between items-center">
                                <span className="text-sm text-gray-700">{absences.from} a {absences.to} de {absences.total}</span>
                                <div className="flex space-x-1">
                                    {absences.links.map((link, i) => (
                                        <button key={i} onClick={() => link.url && router.get(link.url)} disabled={!link.url}
                                            className={`px-3 py-1 text-sm rounded ${link.active ? 'bg-indigo-600 text-white' : link.url ? 'bg-white text-gray-700 hover:bg-gray-50 border' : 'bg-gray-100 text-gray-400'}`}
                                            dangerouslySetInnerHTML={{ __html: link.label }} />
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Modal Criar/Editar */}
                    <AbsenceFormModal show={modals.create} employees={employees} typeOptions={typeOptions}
                        onClose={() => closeModal('create')} />
                    <AbsenceFormModal show={modals.edit && selected !== null} absence={selected}
                        employees={employees} typeOptions={typeOptions} onClose={() => closeModal('edit')} />

                    {/* Modal Visualizar */}
                    {modals.view && selected && (
                        <AbsenceViewModal absence={selected} onClose={() => closeModal('view')} />
                    )}

                    {/* Delete Confirm */}
                    <DeleteConfirmModal
                        show={deleteTarget !== null}
                        onClose={() => setDeleteTarget(null)}
                        onConfirm={handleConfirmDelete}
                        itemType="falta"
                        itemName={deleteTarget?.employee_name}
                        details={[
                            { label: 'Data', value: deleteTarget?.absence_date },
                            { label: 'Tipo', value: deleteTarget?.type_label },
                        ]}
                        processing={deleting}
                    />
                </div>
            </div>
        </>
    );
}

function AbsenceFormModal({ show, absence = null, employees, typeOptions, onClose }) {
    const isEdit = !!absence;
    const form = useForm({
        employee_id: absence?.employee_id || '',
        absence_date: absence?.absence_date || absence?.absence_date_raw || '',
        type: absence?.type || 'unjustified',
        is_justified: absence?.is_justified ?? false,
        reason: absence?.reason || '',
        notes: absence?.notes || '',
    });

    const handleSubmit = () => {
        if (isEdit) form.put(route('absences.update', absence.id), { onSuccess: onClose });
        else form.post(route('absences.store'), { onSuccess: onClose });
    };

    return (
        <StandardModal show={show} onClose={onClose}
            title={isEdit ? 'Editar Falta' : 'Registrar Falta'}
            headerColor={isEdit ? 'bg-yellow-600' : 'bg-indigo-600'}
            headerIcon={isEdit ? <PencilSquareIcon className="h-5 w-5" /> : <PlusIcon className="h-5 w-5" />}
            onSubmit={handleSubmit}
            footer={
                <StandardModal.Footer onCancel={onClose} onSubmit="submit"
                    submitLabel={isEdit ? 'Salvar' : 'Registrar'}
                    submitColor={isEdit ? 'bg-yellow-600 hover:bg-yellow-700' : undefined}
                    processing={form.processing} />
            }>

            <FormSection title="Funcionário e Data" cols={2}>
                <div>
                    <InputLabel value="Funcionário *" />
                    <select value={form.data.employee_id} onChange={e => form.setData('employee_id', e.target.value)} required
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="">Selecione...</option>
                        {employees.map(e => <option key={e.id} value={e.id}>{e.name}</option>)}
                    </select>
                    <InputError message={form.errors.employee_id} className="mt-1" />
                </div>
                <div>
                    <InputLabel value="Data *" />
                    <TextInput type="date" className="mt-1 w-full" value={form.data.absence_date}
                        onChange={e => form.setData('absence_date', e.target.value)} required />
                    <InputError message={form.errors.absence_date} className="mt-1" />
                </div>
            </FormSection>

            <FormSection title="Classificação" cols={2}>
                <div>
                    <InputLabel value="Tipo *" />
                    <select value={form.data.type} onChange={e => form.setData('type', e.target.value)} required
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        {typeOptions.map(t => <option key={t.value} value={t.value}>{t.label}</option>)}
                    </select>
                </div>
                <div className="flex items-end pb-1">
                    <div className="flex items-center gap-2">
                        <Checkbox checked={form.data.is_justified}
                            onChange={e => form.setData('is_justified', e.target.checked)} />
                        <span className="text-sm text-gray-700">Falta justificada</span>
                    </div>
                </div>
                <div className="col-span-full">
                    <InputLabel value="Motivo" />
                    <TextInput className="mt-1 w-full" value={form.data.reason}
                        onChange={e => form.setData('reason', e.target.value)} placeholder="Motivo da falta" />
                </div>
            </FormSection>

            <FormSection title="Observações" cols={1}>
                <div>
                    <textarea value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} rows="2"
                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                </div>
            </FormSection>
        </StandardModal>
    );
}

function AbsenceViewModal({ absence, onClose }) {
    const headerBadges = [
        {
            text: absence.is_justified ? 'Justificada' : 'Injustificada',
            className: absence.is_justified ? 'bg-emerald-500/20 text-white' : 'bg-red-500/20 text-white',
        },
    ];

    return (
        <StandardModal show={true} onClose={onClose} title="Detalhes da Falta"
            subtitle={absence.employee_name}
            headerColor="bg-gray-700" headerIcon={<DocumentTextIcon className="h-5 w-5" />}
            headerBadges={headerBadges}
            footer={<StandardModal.Footer onCancel={onClose} cancelLabel="Fechar" />}>

            <StandardModal.Section title="Informações da Falta">
                <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <StandardModal.Field label="Funcionário" value={absence.employee_name} />
                    <StandardModal.Field label="Data" value={absence.absence_date_formatted} />
                    <StandardModal.Field label="Tipo" value={absence.type_label} />
                    <div>
                        <p className="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Justificativa</p>
                        <div className="mt-0.5">
                            <StatusBadge variant={absence.is_justified ? 'success' : 'danger'}>
                                {absence.is_justified ? 'Justificada' : 'Injustificada'}
                            </StatusBadge>
                        </div>
                    </div>
                    <StandardModal.Field label="Motivo" value={absence.reason} />
                    <StandardModal.Field label="Atestado Vinculado"
                        value={absence.medical_certificate_id ? `#${absence.medical_certificate_id}` : null} mono />
                </div>
            </StandardModal.Section>

            {absence.notes && (
                <StandardModal.Section title="Observações">
                    <p className="text-sm text-gray-900 whitespace-pre-line">{absence.notes}</p>
                </StandardModal.Section>
            )}

            <div className="flex justify-between text-xs text-gray-400 pt-2">
                <span>Registrado por {absence.created_by || '-'} em {formatDateTime(absence.created_at)}</span>
            </div>
        </StandardModal>
    );
}
