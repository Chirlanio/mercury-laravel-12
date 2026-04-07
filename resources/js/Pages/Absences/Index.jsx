import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import Button from '@/Components/Button';
import { formatDateTime } from '@/Utils/dateHelpers';

export default function Index({ auth, absences, employees = [], filters = {}, typeOptions = [] }) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission(PERMISSIONS.CREATE_ABSENCES);
    const canEdit = hasPermission(PERMISSIONS.EDIT_ABSENCES);
    const canDelete = hasPermission(PERMISSIONS.DELETE_ABSENCES);

    const [search, setSearch] = useState(filters.search || '');
    const [employeeFilter, setEmployeeFilter] = useState(filters.employee_id || '');
    const [typeFilter, setTypeFilter] = useState(filters.type || '');
    const [justifiedFilter, setJustifiedFilter] = useState(filters.justified || '');
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [editing, setEditing] = useState(null);
    const [viewing, setViewing] = useState(null);
    const [deleting, setDeleting] = useState(null);

    const applyFilters = () => {
        router.get(route('absences.index'), {
            search: search || undefined,
            employee_id: employeeFilter || undefined,
            type: typeFilter || undefined,
            justified: justifiedFilter || undefined,
        }, { preserveState: true });
    };

    const openEdit = (item) => {
        fetch(route('absences.show', item.id))
            .then(r => r.json())
            .then(data => setEditing(data));
    };

    const openView = (item) => {
        fetch(route('absences.show', item.id))
            .then(r => r.json())
            .then(data => setViewing(data));
    };

    const handleDelete = () => {
        if (!deleting) return;
        router.delete(route('absences.destroy', deleting.id), { onSuccess: () => setDeleting(null) });
    };

    const justifiedBadge = (val) => val
        ? <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Justificada</span>
        : <span className="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Injustificada</span>;

    return (
        <>
            <Head title="Controle de Faltas" />
            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="mb-6 flex justify-between items-center">
                        <div>
                            <h1 className="text-3xl font-bold text-gray-900">Controle de Faltas</h1>
                            <p className="mt-1 text-sm text-gray-600">Registre e acompanhe faltas dos funcionarios</p>
                        </div>
                        {canCreate && (
                            <Button variant="primary" onClick={() => setShowCreateModal(true)}
                                icon={({ className }) => (
                                    <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                )}>Registrar Falta</Button>
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
                                    {['Funcionario', 'Data', 'Tipo', 'Justificativa', 'Motivo', 'Acoes'].map(h => (
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
                                        <td className="px-4 py-3">{justifiedBadge(a.is_justified)}</td>
                                        <td className="px-4 py-3 text-sm text-gray-500 max-w-xs truncate">{a.reason || '-'}</td>
                                        <td className="px-4 py-3">
                                            <div className="flex space-x-2">
                                                <Button onClick={() => openView(a)} variant="secondary" size="sm" iconOnly
                                                    icon={({ className }) => <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>}
                                                    title="Visualizar" />
                                                {canEdit && <Button onClick={() => openEdit(a)} variant="warning" size="sm" iconOnly
                                                    icon={({ className }) => <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>}
                                                    title="Editar" />}
                                                {canDelete && <Button onClick={() => setDeleting(a)} variant="danger" size="sm" iconOnly
                                                    icon={({ className }) => <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>}
                                                    title="Excluir" />}
                                            </div>
                                        </td>
                                    </tr>
                                )) : (
                                    <tr><td colSpan="6" className="px-4 py-12 text-center text-gray-500">Nenhuma falta registrada.</td></tr>
                                )}
                            </tbody>
                        </table>
                        {absences.last_page > 1 && <Pagination links={absences.links} from={absences.from} to={absences.to} total={absences.total} />}
                    </div>

                    {showCreateModal && <FormModal employees={employees} typeOptions={typeOptions} onClose={() => setShowCreateModal(false)} />}
                    {editing && <FormModal absence={editing} employees={employees} typeOptions={typeOptions} onClose={() => setEditing(null)} />}
                    {viewing && <ViewModal absence={viewing} onClose={() => setViewing(null)} />}
                    {deleting && <DeleteConfirm label={`falta de ${deleting.employee_name} em ${deleting.absence_date}`} onConfirm={handleDelete} onCancel={() => setDeleting(null)} />}
                </div>
            </div>
        </>
    );
}

function FormModal({ absence = null, employees, typeOptions, onClose }) {
    const isEdit = !!absence;
    const form = useForm({
        employee_id: absence?.employee_id || '',
        absence_date: absence?.absence_date || absence?.absence_date_raw || '',
        type: absence?.type || 'unjustified',
        is_justified: absence?.is_justified ?? false,
        reason: absence?.reason || '',
        notes: absence?.notes || '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        if (isEdit) {
            form.put(route('absences.update', absence.id), { onSuccess: onClose });
        } else {
            form.post(route('absences.store'), { onSuccess: onClose });
        }
    };

    return (
        <div className="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
            <div className="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4">
                <div className={`${isEdit ? 'bg-indigo-600' : 'bg-green-600'} text-white px-6 py-4 rounded-t-lg flex justify-between items-center`}>
                    <h3 className="text-lg font-semibold">{isEdit ? 'Editar Falta' : 'Registrar Falta'}</h3>
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
                            <input type="date" value={form.data.absence_date} onChange={e => form.setData('absence_date', e.target.value)} required
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            {form.errors.absence_date && <p className="mt-1 text-xs text-red-600">{form.errors.absence_date}</p>}
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
                        <div className="flex items-end pb-2">
                            <label className="flex items-center">
                                <input type="checkbox" checked={form.data.is_justified} onChange={e => form.setData('is_justified', e.target.checked)}
                                    className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                <span className="ml-2 text-sm text-gray-700">Falta justificada</span>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Motivo</label>
                        <input type="text" value={form.data.reason} onChange={e => form.setData('reason', e.target.value)} placeholder="Motivo da falta"
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

function ViewModal({ absence, onClose }) {
    return (
        <div className="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
            <div className="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4">
                <div className="bg-blue-600 text-white px-6 py-4 rounded-t-lg flex justify-between items-center">
                    <h3 className="text-lg font-semibold">Detalhes da Falta</h3>
                    <button onClick={onClose} className="text-white hover:opacity-80 text-2xl leading-none">&times;</button>
                </div>
                <div className="p-6 space-y-4">
                    <div className="bg-gray-50 rounded-lg p-4 grid grid-cols-2 gap-4">
                        <Detail label="Funcionario" value={absence.employee_name} />
                        <Detail label="Data" value={absence.absence_date_formatted} />
                        <Detail label="Tipo" value={absence.type_label} />
                        <Detail label="Justificativa" value={absence.is_justified ? 'Justificada' : 'Injustificada'} />
                        <Detail label="Motivo" value={absence.reason} />
                        <Detail label="Atestado vinculado" value={absence.medical_certificate_id ? `#${absence.medical_certificate_id}` : '-'} />
                    </div>
                    {absence.notes && <div className="bg-gray-50 rounded-lg p-4"><Detail label="Observacoes" value={absence.notes} /></div>}
                    <div className="bg-gray-50 rounded-lg p-4 grid grid-cols-2 gap-4">
                        <Detail label="Registrado por" value={absence.created_by} />
                        <Detail label="Registrado em" value={formatDateTime(absence.created_at)} />
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
