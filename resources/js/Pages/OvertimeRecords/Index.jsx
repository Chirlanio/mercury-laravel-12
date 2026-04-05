import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import Button from '@/Components/Button';

const STATUS_COLORS = {
    pending: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
    closed: 'bg-gray-100 text-gray-600',
};

export default function Index({ auth, records, employees = [], filters = {}, typeOptions = [], statusOptions = [] }) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission(PERMISSIONS.CREATE_OVERTIME);
    const canEdit = hasPermission(PERMISSIONS.EDIT_OVERTIME);
    const canDelete = hasPermission(PERMISSIONS.DELETE_OVERTIME);

    const [search, setSearch] = useState(filters.search || '');
    const [employeeFilter, setEmployeeFilter] = useState(filters.employee_id || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [typeFilter, setTypeFilter] = useState(filters.type || '');
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [editing, setEditing] = useState(null);
    const [viewing, setViewing] = useState(null);
    const [deleting, setDeleting] = useState(null);

    const applyFilters = () => {
        router.get(route('overtime-records.index'), {
            search: search || undefined,
            employee_id: employeeFilter || undefined,
            status: statusFilter || undefined,
            type: typeFilter || undefined,
        }, { preserveState: true });
    };

    const openEdit = (item) => {
        fetch(route('overtime-records.show', item.id))
            .then(r => r.json())
            .then(data => setEditing(data));
    };

    const openView = (item) => {
        fetch(route('overtime-records.show', item.id))
            .then(r => r.json())
            .then(data => setViewing(data));
    };

    const handleDelete = () => {
        if (!deleting) return;
        router.delete(route('overtime-records.destroy', deleting.id), { onSuccess: () => setDeleting(null) });
    };

    return (
        <AuthenticatedLayout user={auth?.user}>
            <Head title="Controle de Horas Extras" />
            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="mb-6 flex justify-between items-center">
                        <div>
                            <h1 className="text-3xl font-bold text-gray-900">Controle de Horas Extras</h1>
                            <p className="mt-1 text-sm text-gray-600">Registre e aprove horas extras dos funcionarios</p>
                        </div>
                        {canCreate && (
                            <Button variant="primary" onClick={() => setShowCreateModal(true)}
                                icon={({ className }) => (
                                    <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                )}>Registrar HE</Button>
                        )}
                    </div>

                    {/* Filters */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
                                <input type="text" placeholder="Nome..." value={search} onChange={e => setSearch(e.target.value)}
                                    onKeyDown={e => e.key === 'Enter' && applyFilters()}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Funcionario</label>
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
                            <div>
                                <Button variant="primary" onClick={applyFilters}>Filtrar</Button>
                            </div>
                        </div>
                    </div>

                    {/* Table */}
                    <div className="bg-white shadow-sm rounded-lg overflow-hidden">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    {['Funcionario', 'Data', 'Horario', 'Horas', 'Tipo', 'Status', 'Acoes'].map(h => (
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
                                            <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_COLORS[r.status] || 'bg-gray-100 text-gray-600'}`}>
                                                {r.status_label}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex space-x-2">
                                                <Button onClick={() => openView(r)} variant="secondary" size="sm" iconOnly
                                                    icon={({ className }) => <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>}
                                                    title="Visualizar" />
                                                {canEdit && <Button onClick={() => openEdit(r)} variant="warning" size="sm" iconOnly
                                                    icon={({ className }) => <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>}
                                                    title="Editar" />}
                                                {canDelete && <Button onClick={() => setDeleting(r)} variant="danger" size="sm" iconOnly
                                                    icon={({ className }) => <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>}
                                                    title="Excluir" />}
                                            </div>
                                        </td>
                                    </tr>
                                )) : (
                                    <tr><td colSpan="7" className="px-4 py-12 text-center text-gray-500">Nenhuma hora extra registrada.</td></tr>
                                )}
                            </tbody>
                        </table>
                        {records.last_page > 1 && <Pagination links={records.links} from={records.from} to={records.to} total={records.total} />}
                    </div>

                    {showCreateModal && <FormModal employees={employees} typeOptions={typeOptions} statusOptions={statusOptions} onClose={() => setShowCreateModal(false)} />}
                    {editing && <FormModal record={editing} employees={employees} typeOptions={typeOptions} statusOptions={statusOptions} onClose={() => setEditing(null)} />}
                    {viewing && <ViewModal record={viewing} onClose={() => setViewing(null)} />}
                    {deleting && <DeleteConfirm label={`hora extra de ${deleting.employee_name} em ${deleting.date}`} onConfirm={handleDelete} onCancel={() => setDeleting(null)} />}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function FormModal({ record = null, employees, typeOptions, statusOptions, onClose }) {
    const isEdit = !!record;
    const form = useForm({
        employee_id: record?.employee_id || '',
        date: record?.date || record?.date_raw || '',
        start_time: record?.start_time || '',
        end_time: record?.end_time || '',
        hours: record?.hours || '',
        type: record?.type || 'regular',
        status: record?.status || 'pending',
        reason: record?.reason || '',
        notes: record?.notes || '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        if (isEdit) {
            form.put(route('overtime-records.update', record.id), { onSuccess: onClose });
        } else {
            form.post(route('overtime-records.store'), { onSuccess: onClose });
        }
    };

    // Auto-calculate hours from time range
    const calcHours = (start, end) => {
        if (!start || !end) return;
        const [sh, sm] = start.split(':').map(Number);
        const [eh, em] = end.split(':').map(Number);
        const diff = (eh * 60 + em - sh * 60 - sm) / 60;
        if (diff > 0) form.setData('hours', diff.toFixed(2));
    };

    return (
        <div className="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
            <div className="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4">
                <div className={`${isEdit ? 'bg-indigo-600' : 'bg-green-600'} text-white px-6 py-4 rounded-t-lg flex justify-between items-center`}>
                    <h3 className="text-lg font-semibold">{isEdit ? 'Editar Hora Extra' : 'Registrar Hora Extra'}</h3>
                    <button onClick={onClose} className="text-white hover:opacity-80 text-2xl leading-none">&times;</button>
                </div>
                <form onSubmit={handleSubmit} className="p-6 space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Funcionario *</label>
                            <select value={form.data.employee_id} onChange={e => form.setData('employee_id', e.target.value)} required
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">Selecione...</option>
                                {employees.map(e => <option key={e.id} value={e.id}>{e.name}</option>)}
                            </select>
                            {form.errors.employee_id && <p className="mt-1 text-xs text-red-600">{form.errors.employee_id}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Data *</label>
                            <input type="date" value={form.data.date} onChange={e => form.setData('date', e.target.value)} required
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            {form.errors.date && <p className="mt-1 text-xs text-red-600">{form.errors.date}</p>}
                        </div>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Hora Inicio *</label>
                            <input type="time" value={form.data.start_time}
                                onChange={e => { form.setData('start_time', e.target.value); calcHours(e.target.value, form.data.end_time); }} required
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            {form.errors.start_time && <p className="mt-1 text-xs text-red-600">{form.errors.start_time}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Hora Fim *</label>
                            <input type="time" value={form.data.end_time}
                                onChange={e => { form.setData('end_time', e.target.value); calcHours(form.data.start_time, e.target.value); }} required
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            {form.errors.end_time && <p className="mt-1 text-xs text-red-600">{form.errors.end_time}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Total Horas *</label>
                            <input type="number" step="0.25" min="0.25" max="24" value={form.data.hours}
                                onChange={e => form.setData('hours', e.target.value)} required
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            {form.errors.hours && <p className="mt-1 text-xs text-red-600">{form.errors.hours}</p>}
                        </div>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Tipo *</label>
                            <select value={form.data.type} onChange={e => form.setData('type', e.target.value)} required
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                {typeOptions.map(t => <option key={t.value} value={t.value}>{t.label}</option>)}
                            </select>
                        </div>
                        {isEdit && (
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                <select value={form.data.status} onChange={e => form.setData('status', e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    {statusOptions.map(s => <option key={s.value} value={s.value}>{s.label}</option>)}
                                </select>
                            </div>
                        )}
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Motivo</label>
                        <input type="text" value={form.data.reason} onChange={e => form.setData('reason', e.target.value)} placeholder="Motivo da hora extra"
                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Observacoes</label>
                        <textarea value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} rows="2"
                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                    </div>
                    <div className="flex justify-end space-x-3 pt-4 border-t">
                        <Button variant="outline" onClick={onClose}>Cancelar</Button>
                        <Button type="submit" variant={isEdit ? 'primary' : 'success'} loading={form.processing}>
                            {isEdit ? 'Salvar' : 'Registrar'}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function ViewModal({ record, onClose }) {
    return (
        <div className="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
            <div className="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4">
                <div className="bg-blue-600 text-white px-6 py-4 rounded-t-lg flex justify-between items-center">
                    <h3 className="text-lg font-semibold">Detalhes da Hora Extra</h3>
                    <button onClick={onClose} className="text-white hover:opacity-80 text-2xl leading-none">&times;</button>
                </div>
                <div className="p-6 space-y-4">
                    <div className="bg-gray-50 rounded-lg p-4 grid grid-cols-2 gap-4">
                        <Detail label="Funcionario" value={record.employee_name} />
                        <Detail label="Data" value={record.date_formatted} />
                        <Detail label="Horario" value={`${record.start_time} - ${record.end_time}`} />
                        <Detail label="Total Horas" value={`${record.hours}h`} />
                        <Detail label="Tipo" value={record.type_label} />
                        <Detail label="Status" value={
                            <span className={`inline-flex px-2.5 py-1 rounded-full text-xs font-semibold ${STATUS_COLORS[record.status] || ''}`}>
                                {record.status_label}
                            </span>
                        } />
                        <Detail label="Motivo" value={record.reason} />
                        <Detail label="Aprovado por" value={record.approved_by} />
                    </div>
                    {record.notes && <div className="bg-gray-50 rounded-lg p-4"><Detail label="Observacoes" value={record.notes} /></div>}
                    <div className="bg-gray-50 rounded-lg p-4 grid grid-cols-2 gap-4">
                        <Detail label="Registrado por" value={record.created_by} />
                        <Detail label="Registrado em" value={record.created_at} />
                    </div>
                    <div className="flex justify-end pt-4 border-t">
                        <Button variant="outline" onClick={onClose}>Fechar</Button>
                    </div>
                </div>
            </div>
        </div>
    );
}

function Detail({ label, value }) {
    return (<div><p className="text-xs font-medium text-gray-500 uppercase mb-1">{label}</p><p className="text-sm text-gray-900">{value || '-'}</p></div>);
}

function Pagination({ links, from, to, total }) {
    return (
        <div className="px-4 py-3 border-t flex justify-between items-center">
            <span className="text-sm text-gray-700">{from} a {to} de {total}</span>
            <div className="flex space-x-1">
                {links.map((link, i) => (
                    <button key={i} onClick={() => link.url && router.get(link.url)} disabled={!link.url}
                        className={`px-3 py-1 text-sm rounded ${link.active ? 'bg-indigo-600 text-white' : link.url ? 'bg-white text-gray-700 hover:bg-gray-50 border' : 'bg-gray-100 text-gray-400'}`}
                        dangerouslySetInnerHTML={{ __html: link.label }} />
                ))}
            </div>
        </div>
    );
}

function DeleteConfirm({ label, onConfirm, onCancel }) {
    return (
        <div className="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
            <div className="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4">
                <h3 className="text-lg font-medium text-gray-900 mb-2">Confirmar Exclusao</h3>
                <p className="text-sm text-gray-600 mb-4">Deseja excluir a {label}? Esta acao nao pode ser desfeita.</p>
                <div className="flex justify-end space-x-3">
                    <Button variant="outline" onClick={onCancel}>Cancelar</Button>
                    <Button variant="danger" onClick={onConfirm}>Excluir</Button>
                </div>
            </div>
        </div>
    );
}
