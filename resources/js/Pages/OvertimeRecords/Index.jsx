import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import StatusBadge from '@/Components/Shared/StatusBadge';
import DeleteConfirmModal from '@/Components/Shared/DeleteConfirmModal';
import StandardModal from '@/Components/StandardModal';
import FormSection from '@/Components/Shared/FormSection';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import { formatDateTime } from '@/Utils/dateHelpers';
import {
    PlusIcon, MagnifyingGlassIcon, XMarkIcon, PencilSquareIcon, ClockIcon,
} from '@heroicons/react/24/outline';

const STATUS_VARIANT = {
    pending: 'warning', approved: 'success', rejected: 'danger', closed: 'gray',
};

export default function Index({ records, employees = [], filters = {}, typeOptions = [], statusOptions = [] }) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission(PERMISSIONS.CREATE_OVERTIME);
    const canEdit = hasPermission(PERMISSIONS.EDIT_OVERTIME);
    const canDelete = hasPermission(PERMISSIONS.DELETE_OVERTIME);

    const { modals, selected, openModal, closeModal } = useModalManager(['create', 'edit', 'view']);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleting, setDeletingState] = useState(false);

    const [search, setSearch] = useState(filters.search || '');
    const [employeeFilter, setEmployeeFilter] = useState(filters.employee_id || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [typeFilter, setTypeFilter] = useState(filters.type || '');

    const applyFilters = () => {
        router.get(route('overtime-records.index'), {
            search: search || undefined, employee_id: employeeFilter || undefined,
            status: statusFilter || undefined, type: typeFilter || undefined,
        }, { preserveState: true });
    };

    const clearFilters = () => {
        setSearch(''); setEmployeeFilter(''); setStatusFilter(''); setTypeFilter('');
        router.get(route('overtime-records.index'), {}, { preserveState: true });
    };

    const hasActiveFilters = search || employeeFilter || statusFilter || typeFilter;

    const openEdit = (item) => {
        fetch(route('overtime-records.show', item.id)).then(r => r.json()).then(data => openModal('edit', data));
    };

    const openView = (item) => {
        fetch(route('overtime-records.show', item.id)).then(r => r.json()).then(data => openModal('view', data));
    };

    const handleConfirmDelete = () => {
        if (!deleteTarget) return;
        setDeletingState(true);
        router.delete(route('overtime-records.destroy', deleteTarget.id), {
            onSuccess: () => { setDeleteTarget(null); setDeletingState(false); },
            onError: () => setDeletingState(false),
        });
    };

    return (
        <>
            <Head title="Controle de Horas Extras" />
            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="mb-6 flex justify-between items-center">
                        <div>
                            <h1 className="text-3xl font-bold text-gray-900">Controle de Horas Extras</h1>
                            <p className="mt-1 text-sm text-gray-600">Registre e aprove horas extras dos funcionários</p>
                        </div>
                        {canCreate && (
                            <Button variant="primary" onClick={() => openModal('create')} icon={PlusIcon}>
                                Registrar HE
                            </Button>
                        )}
                    </div>

                    {/* Filtros */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-7 gap-4 items-end">
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
                                <label className="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                <select value={statusFilter} onChange={e => setStatusFilter(e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Todos</option>
                                    {statusOptions.map(s => <option key={s.value} value={s.value}>{s.label}</option>)}
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
                            <Button variant="primary" size="sm" onClick={applyFilters} icon={MagnifyingGlassIcon}>Filtrar</Button>
                            <Button variant="outline" size="sm" onClick={clearFilters} disabled={!hasActiveFilters} icon={XMarkIcon}>Limpar</Button>
                        </div>
                    </div>

                    {/* Tabela */}
                    <div className="bg-white shadow-sm rounded-lg overflow-hidden">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    {['Funcionário', 'Data', 'Horário', 'Horas', 'Tipo', 'Status', 'Ações'].map(h => (
                                        <th key={h} className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200">
                                {records.data?.length > 0 ? records.data.map(r => (
                                    <tr key={r.id} className="hover:bg-gray-50">
                                        <td className="px-4 py-3 text-sm font-medium text-gray-900">{r.employee_name}</td>
                                        <td className="px-4 py-3 text-sm text-gray-500">{r.date}</td>
                                        <td className="px-4 py-3 text-sm text-gray-500 font-mono">{r.start_time} - {r.end_time}</td>
                                        <td className="px-4 py-3 text-sm font-medium text-gray-900">{r.hours}h</td>
                                        <td className="px-4 py-3 text-sm text-gray-500">{r.type_label}</td>
                                        <td className="px-4 py-3">
                                            <StatusBadge variant={STATUS_VARIANT[r.status] || 'gray'}>{r.status_label}</StatusBadge>
                                        </td>
                                        <td className="px-4 py-3">
                                            <ActionButtons
                                                onView={() => openView(r)}
                                                onEdit={canEdit ? () => openEdit(r) : null}
                                                onDelete={canDelete ? () => setDeleteTarget(r) : null}
                                            />
                                        </td>
                                    </tr>
                                )) : (
                                    <tr><td colSpan="7" className="px-4 py-12 text-center text-gray-500">Nenhuma hora extra registrada.</td></tr>
                                )}
                            </tbody>
                        </table>
                        {records.last_page > 1 && (
                            <div className="px-4 py-3 border-t flex justify-between items-center">
                                <span className="text-sm text-gray-700">{records.from} a {records.to} de {records.total}</span>
                                <div className="flex space-x-1">
                                    {records.links.map((link, i) => (
                                        <button key={i} onClick={() => link.url && router.get(link.url)} disabled={!link.url}
                                            className={`px-3 py-1 text-sm rounded ${link.active ? 'bg-indigo-600 text-white' : link.url ? 'bg-white text-gray-700 hover:bg-gray-50 border' : 'bg-gray-100 text-gray-400'}`}
                                            dangerouslySetInnerHTML={{ __html: link.label }} />
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Modais */}
                    <OvertimeFormModal show={modals.create} employees={employees} typeOptions={typeOptions}
                        statusOptions={statusOptions} onClose={() => closeModal('create')} />
                    <OvertimeFormModal show={modals.edit && selected !== null} record={selected}
                        employees={employees} typeOptions={typeOptions} statusOptions={statusOptions}
                        onClose={() => closeModal('edit')} />

                    {modals.view && selected && (
                        <OvertimeViewModal record={selected} onClose={() => closeModal('view')} />
                    )}

                    <DeleteConfirmModal
                        show={deleteTarget !== null}
                        onClose={() => setDeleteTarget(null)}
                        onConfirm={handleConfirmDelete}
                        itemType="hora extra"
                        itemName={deleteTarget?.employee_name}
                        details={[
                            { label: 'Data', value: deleteTarget?.date },
                            { label: 'Horas', value: deleteTarget?.hours ? `${deleteTarget.hours}h` : null },
                        ]}
                        processing={deleting}
                    />
                </div>
            </div>
        </>
    );
}

function OvertimeFormModal({ show, record = null, employees, typeOptions, statusOptions, onClose }) {
    const isEdit = !!record;
    const form = useForm({
        employee_id: record?.employee_id || '', date: record?.date || record?.date_raw || '',
        start_time: record?.start_time || '', end_time: record?.end_time || '',
        hours: record?.hours || '', type: record?.type || 'regular',
        status: record?.status || 'pending', reason: record?.reason || '', notes: record?.notes || '',
    });

    const calcHours = (start, end) => {
        if (!start || !end) return;
        const [sh, sm] = start.split(':').map(Number);
        const [eh, em] = end.split(':').map(Number);
        const diff = (eh * 60 + em - sh * 60 - sm) / 60;
        if (diff > 0) form.setData('hours', diff.toFixed(2));
    };

    const handleSubmit = () => {
        if (isEdit) form.put(route('overtime-records.update', record.id), { onSuccess: onClose });
        else form.post(route('overtime-records.store'), { onSuccess: onClose });
    };

    return (
        <StandardModal show={show} onClose={onClose}
            title={isEdit ? 'Editar Hora Extra' : 'Registrar Hora Extra'}
            headerColor={isEdit ? 'bg-yellow-600' : 'bg-indigo-600'}
            headerIcon={isEdit ? <PencilSquareIcon className="h-5 w-5" /> : <ClockIcon className="h-5 w-5" />}
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
                    <TextInput type="date" className="mt-1 w-full" value={form.data.date}
                        onChange={e => form.setData('date', e.target.value)} required />
                    <InputError message={form.errors.date} className="mt-1" />
                </div>
            </FormSection>

            <FormSection title="Horário" cols={3}>
                <div>
                    <InputLabel value="Hora Início *" />
                    <input type="time" value={form.data.start_time}
                        onChange={e => { form.setData('start_time', e.target.value); calcHours(e.target.value, form.data.end_time); }} required
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                    <InputError message={form.errors.start_time} className="mt-1" />
                </div>
                <div>
                    <InputLabel value="Hora Fim *" />
                    <input type="time" value={form.data.end_time}
                        onChange={e => { form.setData('end_time', e.target.value); calcHours(form.data.start_time, e.target.value); }} required
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                    <InputError message={form.errors.end_time} className="mt-1" />
                </div>
                <div>
                    <InputLabel value="Total Horas *" />
                    <TextInput type="number" step="0.25" min="0.25" max="24" className="mt-1 w-full"
                        value={form.data.hours} onChange={e => form.setData('hours', e.target.value)} required />
                    <InputError message={form.errors.hours} className="mt-1" />
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
                {isEdit && (
                    <div>
                        <InputLabel value="Status" />
                        <select value={form.data.status} onChange={e => form.setData('status', e.target.value)}
                            className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            {statusOptions.map(s => <option key={s.value} value={s.value}>{s.label}</option>)}
                        </select>
                    </div>
                )}
                <div className="col-span-full">
                    <InputLabel value="Motivo" />
                    <TextInput className="mt-1 w-full" value={form.data.reason}
                        onChange={e => form.setData('reason', e.target.value)} placeholder="Motivo da hora extra" />
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

function OvertimeViewModal({ record, onClose }) {
    const headerBadges = [
        { text: record.status_label, className: 'bg-white/20 text-white' },
    ];

    return (
        <StandardModal show={true} onClose={onClose} title="Detalhes da Hora Extra"
            subtitle={record.employee_name}
            headerColor="bg-gray-700" headerIcon={<ClockIcon className="h-5 w-5" />}
            headerBadges={headerBadges}
            footer={<StandardModal.Footer onCancel={onClose} cancelLabel="Fechar" />}>

            <StandardModal.Section title="Informações da Hora Extra">
                <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <StandardModal.Field label="Funcionário" value={record.employee_name} />
                    <StandardModal.Field label="Data" value={record.date_formatted} />
                    <StandardModal.Field label="Horário" value={`${record.start_time} - ${record.end_time}`} mono />
                    <StandardModal.Field label="Total Horas" value={`${record.hours}h`} />
                    <StandardModal.Field label="Tipo" value={record.type_label} />
                    <div>
                        <p className="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Status</p>
                        <div className="mt-0.5">
                            <StatusBadge variant={STATUS_VARIANT[record.status] || 'gray'}>{record.status_label}</StatusBadge>
                        </div>
                    </div>
                    <StandardModal.Field label="Motivo" value={record.reason} />
                    <StandardModal.Field label="Aprovado por" value={record.approved_by} />
                </div>
            </StandardModal.Section>

            {record.notes && (
                <StandardModal.Section title="Observações">
                    <p className="text-sm text-gray-900 whitespace-pre-line">{record.notes}</p>
                </StandardModal.Section>
            )}

            <div className="flex justify-between text-xs text-gray-400 pt-2">
                <span>Registrado por {record.created_by || '-'} em {formatDateTime(record.created_at)}</span>
            </div>
        </StandardModal>
    );
}
