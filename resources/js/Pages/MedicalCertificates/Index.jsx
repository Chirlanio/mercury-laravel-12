import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import Button from '@/Components/Button';

export default function Index({ auth, certificates, employees = [], filters = {} }) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission(PERMISSIONS.CREATE_MEDICAL_CERTIFICATES);
    const canEdit = hasPermission(PERMISSIONS.EDIT_MEDICAL_CERTIFICATES);
    const canDelete = hasPermission(PERMISSIONS.DELETE_MEDICAL_CERTIFICATES);

    const [search, setSearch] = useState(filters.search || '');
    const [employeeFilter, setEmployeeFilter] = useState(filters.employee_id || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [editing, setEditing] = useState(null);
    const [viewing, setViewing] = useState(null);
    const [deleting, setDeleting] = useState(null);

    const applyFilters = () => {
        router.get(route('medical-certificates.index'), {
            search: search || undefined,
            employee_id: employeeFilter || undefined,
            status: statusFilter || undefined,
        }, { preserveState: true });
    };

    const openEdit = (item) => {
        fetch(route('medical-certificates.show', item.id))
            .then(r => r.json())
            .then(data => setEditing(data));
    };

    const openView = (item) => {
        fetch(route('medical-certificates.show', item.id))
            .then(r => r.json())
            .then(data => setViewing(data));
    };

    const handleDelete = () => {
        if (!deleting) return;
        router.delete(route('medical-certificates.destroy', deleting.id), {
            onSuccess: () => setDeleting(null),
        });
    };

    return (
        <AuthenticatedLayout user={auth?.user}>
            <Head title="Atestados Medicos" />
            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6 flex justify-between items-center">
                        <div>
                            <h1 className="text-3xl font-bold text-gray-900">Atestados Medicos</h1>
                            <p className="mt-1 text-sm text-gray-600">Gerencie atestados medicos dos funcionarios</p>
                        </div>
                        {canCreate && (
                            <Button variant="primary" onClick={() => setShowCreateModal(true)}
                                icon={({ className }) => (
                                    <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                )}>Novo Atestado</Button>
                        )}
                    </div>

                    {/* Filters */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
                                <input type="text" placeholder="Nome, CID, medico..." value={search}
                                    onChange={e => setSearch(e.target.value)}
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
                                <label className="block text-sm font-medium text-gray-700 mb-1">Situacao</label>
                                <select value={statusFilter} onChange={e => setStatusFilter(e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Todos</option>
                                    <option value="active">Vigente</option>
                                    <option value="expired">Expirado</option>
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
                                    {['Funcionario', 'Periodo', 'Dias', 'CID', 'Medico', 'Status', 'Acoes'].map(h => (
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
                                            <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${c.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'}`}>
                                                {c.is_active ? 'Vigente' : 'Expirado'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex space-x-2">
                                                <Button onClick={() => openView(c)} variant="secondary" size="sm" iconOnly
                                                    icon={({ className }) => <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>}
                                                    title="Visualizar" />
                                                {canEdit && <Button onClick={() => openEdit(c)} variant="warning" size="sm" iconOnly
                                                    icon={({ className }) => <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>}
                                                    title="Editar" />}
                                                {canDelete && <Button onClick={() => setDeleting(c)} variant="danger" size="sm" iconOnly
                                                    icon={({ className }) => <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>}
                                                    title="Excluir" />}
                                            </div>
                                        </td>
                                    </tr>
                                )) : (
                                    <tr><td colSpan="7" className="px-4 py-12 text-center text-gray-500">Nenhum atestado encontrado.</td></tr>
                                )}
                            </tbody>
                        </table>
                        {certificates.last_page > 1 && <Pagination links={certificates.links} from={certificates.from} to={certificates.to} total={certificates.total} />}
                    </div>

                    {showCreateModal && <FormModal employees={employees} onClose={() => setShowCreateModal(false)} />}
                    {editing && <FormModal certificate={editing} employees={employees} onClose={() => setEditing(null)} />}
                    {viewing && <ViewModal certificate={viewing} onClose={() => setViewing(null)} />}
                    {deleting && <DeleteConfirm item={deleting} label={`atestado de ${deleting.employee_name}`} onConfirm={handleDelete} onCancel={() => setDeleting(null)} />}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function FormModal({ certificate = null, employees, onClose }) {
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

    const handleSubmit = (e) => {
        e.preventDefault();
        if (isEdit) {
            form.put(route('medical-certificates.update', certificate.id), { onSuccess: onClose });
        } else {
            form.post(route('medical-certificates.store'), { onSuccess: onClose });
        }
    };

    return (
        <div className="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
            <div className="bg-white rounded-lg shadow-xl w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto">
                <div className={`${isEdit ? 'bg-indigo-600' : 'bg-green-600'} text-white px-6 py-4 rounded-t-lg flex justify-between items-center`}>
                    <h3 className="text-lg font-semibold">{isEdit ? 'Editar Atestado' : 'Novo Atestado Medico'}</h3>
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
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Data Inicio *</label>
                            <input type="date" value={form.data.start_date} onChange={e => form.setData('start_date', e.target.value)} required
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            {form.errors.start_date && <p className="mt-1 text-xs text-red-600">{form.errors.start_date}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Data Fim *</label>
                            <input type="date" value={form.data.end_date} onChange={e => form.setData('end_date', e.target.value)} required
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            {form.errors.end_date && <p className="mt-1 text-xs text-red-600">{form.errors.end_date}</p>}
                        </div>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">CID</label>
                            <input type="text" value={form.data.cid_code} onChange={e => form.setData('cid_code', e.target.value)} placeholder="Ex: J11"
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Descricao CID</label>
                            <input type="text" value={form.data.cid_description} onChange={e => form.setData('cid_description', e.target.value)} placeholder="Descricao do CID"
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                        </div>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Medico</label>
                            <input type="text" value={form.data.doctor_name} onChange={e => form.setData('doctor_name', e.target.value)} placeholder="Nome do medico"
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">CRM</label>
                            <input type="text" value={form.data.doctor_crm} onChange={e => form.setData('doctor_crm', e.target.value)} placeholder="CRM do medico"
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                        </div>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Observacoes</label>
                        <textarea value={form.data.notes} onChange={e => form.setData('notes', e.target.value)} rows="3"
                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                    </div>
                    <div className="flex justify-end space-x-3 pt-4 border-t">
                        <Button variant="outline" onClick={onClose}>Cancelar</Button>
                        <Button type="submit" variant={isEdit ? 'primary' : 'success'} loading={form.processing}>
                            {isEdit ? 'Salvar Alteracoes' : 'Cadastrar Atestado'}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

function ViewModal({ certificate, onClose }) {
    return (
        <div className="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
            <div className="bg-white rounded-lg shadow-xl w-full max-w-4xl mx-4">
                <div className="bg-blue-600 text-white px-6 py-4 rounded-t-lg flex justify-between items-center">
                    <h3 className="text-lg font-semibold">Detalhes do Atestado</h3>
                    <button onClick={onClose} className="text-white hover:opacity-80 text-2xl leading-none">&times;</button>
                </div>
                <div className="p-6 space-y-4">
                    <div className="bg-gray-50 rounded-lg p-4 grid grid-cols-2 gap-4">
                        <Detail label="Funcionario" value={certificate.employee_name} />
                        <Detail label="Status" value={
                            <span className={`inline-flex px-2.5 py-1 rounded-full text-xs font-semibold ${certificate.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'}`}>
                                {certificate.is_active ? 'Vigente' : 'Expirado'}
                            </span>
                        } />
                        <Detail label="Inicio" value={certificate.start_date_formatted} />
                        <Detail label="Fim" value={certificate.end_date_formatted} />
                        <Detail label="Dias" value={certificate.days} />
                        <Detail label="CID" value={certificate.cid_code ? `${certificate.cid_code} - ${certificate.cid_description || ''}` : '-'} />
                        <Detail label="Medico" value={certificate.doctor_name} />
                        <Detail label="CRM" value={certificate.doctor_crm} />
                    </div>
                    {certificate.notes && (
                        <div className="bg-gray-50 rounded-lg p-4">
                            <Detail label="Observacoes" value={certificate.notes} />
                        </div>
                    )}
                    <div className="bg-gray-50 rounded-lg p-4 grid grid-cols-2 gap-4">
                        <Detail label="Cadastrado por" value={certificate.created_by} />
                        <Detail label="Cadastrado em" value={certificate.created_at} />
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
    return (
        <div>
            <p className="text-xs font-medium text-gray-500 uppercase mb-1">{label}</p>
            <p className="text-sm text-gray-900">{value || '-'}</p>
        </div>
    );
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

function DeleteConfirm({ item, label, onConfirm, onCancel }) {
    return (
        <div className="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
            <div className="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4">
                <h3 className="text-lg font-medium text-gray-900 mb-2">Confirmar Exclusao</h3>
                <p className="text-sm text-gray-600 mb-4">Deseja excluir o {label}? Esta acao nao pode ser desfeita.</p>
                <div className="flex justify-end space-x-3">
                    <Button variant="outline" onClick={onCancel}>Cancelar</Button>
                    <Button variant="danger" onClick={onConfirm}>Excluir</Button>
                </div>
            </div>
        </div>
    );
}
