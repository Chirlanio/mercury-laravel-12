import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import { maskCpfCnpj, maskPhone } from '@/Hooks/useMasks';
import Button from '@/Components/Button';
import { formatDateTime } from '@/Utils/dateHelpers';

export default function Index({ auth, suppliers, filters = {} }) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission(PERMISSIONS.CREATE_SUPPLIERS);
    const canEdit = hasPermission(PERMISSIONS.EDIT_SUPPLIERS);
    const canDelete = hasPermission(PERMISSIONS.DELETE_SUPPLIERS);

    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [editingSupplier, setEditingSupplier] = useState(null);
    const [viewingSupplier, setViewingSupplier] = useState(null);
    const [deletingSupplier, setDeletingSupplier] = useState(null);

    const applyFilters = () => {
        router.get(route('suppliers.index'), {
            search: search || undefined, status: statusFilter || undefined,
        }, { preserveState: true });
    };

    const openEdit = (supplier) => {
        fetch(route('suppliers.show', supplier.id))
            .then(r => r.json())
            .then(data => setEditingSupplier(data));
    };

    const openView = (supplier) => {
        fetch(route('suppliers.show', supplier.id))
            .then(r => r.json())
            .then(data => setViewingSupplier(data));
    };

    const handleDelete = () => {
        if (!deletingSupplier) return;
        router.delete(route('suppliers.destroy', deletingSupplier.id), {
            onSuccess: () => setDeletingSupplier(null),
        });
    };

    return (
        <>
            <Head title="Fornecedores" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6">
                        <div className="flex justify-between items-center">
                            <div>
                                <h1 className="text-3xl font-bold text-gray-900">Fornecedores</h1>
                                <p className="mt-1 text-sm text-gray-600">Gerencie e visualize informações dos fornecedores</p>
                            </div>
                            <div className="flex gap-3">
                                {canCreate && (
                                    <Button
                                        variant="primary"
                                        onClick={() => setShowCreateModal(true)}
                                        icon={({ className }) => (
                                            <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                            </svg>
                                        )}
                                    >
                                        Novo Fornecedor
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Filters */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
                                <input type="text" placeholder="Nome, CNPJ, e-mail..."
                                    value={search} onChange={e => setSearch(e.target.value)}
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
                            <div>
                                <Button variant="primary" onClick={applyFilters}
                                    icon={({ className }) => (
                                        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                    )}
                                >
                                    Filtrar
                                </Button>
                            </div>
                        </div>
                    </div>

                    {/* Table */}
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
                                            <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${s.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                                {s.is_active ? 'Ativo' : 'Inativo'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex space-x-2">
                                                <Button
                                                    onClick={() => openView(s)}
                                                    variant="secondary"
                                                    size="sm"
                                                    iconOnly={true}
                                                    icon={({ className }) => (
                                                        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                        </svg>
                                                    )}
                                                    title="Visualizar fornecedor"
                                                />
                                                {canEdit && (
                                                    <Button
                                                        onClick={() => openEdit(s)}
                                                        variant="warning"
                                                        size="sm"
                                                        iconOnly={true}
                                                        icon={({ className }) => (
                                                            <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                            </svg>
                                                        )}
                                                        title="Editar fornecedor"
                                                    />
                                                )}
                                                {canDelete && (
                                                    <Button
                                                        onClick={() => setDeletingSupplier(s)}
                                                        variant="danger"
                                                        size="sm"
                                                        iconOnly={true}
                                                        icon={({ className }) => (
                                                            <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                        )}
                                                        title="Excluir fornecedor"
                                                    />
                                                )}
                                            </div>
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

                    {/* Create Modal */}
                    {showCreateModal && <SupplierFormModal onClose={() => setShowCreateModal(false)} />}

                    {/* Edit Modal */}
                    {editingSupplier && <SupplierFormModal supplier={editingSupplier} onClose={() => setEditingSupplier(null)} />}

                    {/* View Modal */}
                    {viewingSupplier && <ViewModal supplier={viewingSupplier} onClose={() => setViewingSupplier(null)} />}

                    {/* Delete Confirm */}
                    {deletingSupplier && (
                        <div className="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
                            <div className="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4">
                                <h3 className="text-lg font-medium text-gray-900 mb-2">Confirmar Exclusão</h3>
                                <p className="text-sm text-gray-600 mb-4">
                                    Deseja excluir o fornecedor <strong>{deletingSupplier.nome_fantasia}</strong>?
                                    Esta ação não pode ser desfeita.
                                </p>
                                <div className="flex justify-end space-x-3">
                                    <Button variant="outline" onClick={() => setDeletingSupplier(null)}>Cancelar</Button>
                                    <Button variant="danger" onClick={handleDelete}>Excluir</Button>
                                </div>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

// ============================================================
// FORM MODAL (Create + Edit)
// ============================================================
function SupplierFormModal({ supplier = null, onClose }) {
    const isEdit = !!supplier;
    const form = useForm({
        razao_social: supplier?.razao_social || '',
        nome_fantasia: supplier?.nome_fantasia || '',
        cnpj: supplier ? maskCpfCnpj(supplier.cnpj || '') : '',
        contact: supplier ? maskPhone(supplier.contact || '') : '',
        email: supplier?.email || '',
        is_active: supplier?.is_active ?? true,
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        if (isEdit) {
            form.put(route('suppliers.update', supplier.id), { onSuccess: () => onClose() });
        } else {
            form.post(route('suppliers.store'), { onSuccess: () => onClose() });
        }
    };

    return (
        <div className="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
            <div className="bg-white rounded-lg shadow-xl w-full max-w-4xl mx-4">
                <div className={`${isEdit ? 'bg-indigo-600' : 'bg-green-600'} text-white px-6 py-4 rounded-t-lg flex justify-between items-center`}>
                    <h3 className="text-lg font-semibold">{isEdit ? 'Editar Fornecedor' : 'Novo Fornecedor'}</h3>
                    <button onClick={onClose} className="text-white hover:opacity-80 text-2xl leading-none">&times;</button>
                </div>

                <form onSubmit={handleSubmit} className="p-6 space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Razão Social *</label>
                            <input type="text" value={form.data.razao_social}
                                onChange={e => form.setData('razao_social', e.target.value)} required
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            {form.errors.razao_social && <p className="mt-1 text-xs text-red-600">{form.errors.razao_social}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Nome Fantasia *</label>
                            <input type="text" value={form.data.nome_fantasia}
                                onChange={e => form.setData('nome_fantasia', e.target.value)} required
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            {form.errors.nome_fantasia && <p className="mt-1 text-xs text-red-600">{form.errors.nome_fantasia}</p>}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">CNPJ/CPF *</label>
                            <input type="text" value={form.data.cnpj}
                                onChange={e => form.setData('cnpj', maskCpfCnpj(e.target.value))} required
                                placeholder="00.000.000/0000-00"
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            {form.errors.cnpj && <p className="mt-1 text-xs text-red-600">{form.errors.cnpj}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Contato *</label>
                            <input type="text" value={form.data.contact}
                                onChange={e => form.setData('contact', maskPhone(e.target.value))} required
                                placeholder="(00) 00000-0000"
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            {form.errors.contact && <p className="mt-1 text-xs text-red-600">{form.errors.contact}</p>}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">E-mail *</label>
                            <input type="email" value={form.data.email}
                                onChange={e => form.setData('email', e.target.value)} required
                                placeholder="email@fornecedor.com"
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            {form.errors.email && <p className="mt-1 text-xs text-red-600">{form.errors.email}</p>}
                        </div>
                        {isEdit && (
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Situação</label>
                                <select value={form.data.is_active ? 'true' : 'false'}
                                    onChange={e => form.setData('is_active', e.target.value === 'true')}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="true">Ativo</option>
                                    <option value="false">Inativo</option>
                                </select>
                            </div>
                        )}
                    </div>

                    <div className="flex justify-end space-x-3 pt-4 border-t">
                        <Button variant="outline" onClick={onClose}>Cancelar</Button>
                        <Button
                            type="submit"
                            variant={isEdit ? 'primary' : 'success'}
                            loading={form.processing}
                        >
                            {isEdit ? 'Salvar Alterações' : 'Cadastrar Fornecedor'}
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ============================================================
// VIEW MODAL
// ============================================================
function ViewModal({ supplier, onClose }) {
    return (
        <div className="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
            <div className="bg-white rounded-lg shadow-xl w-full max-w-4xl mx-4">
                <div className="bg-blue-600 text-white px-6 py-4 rounded-t-lg flex justify-between items-center">
                    <h3 className="text-lg font-semibold">Detalhes do Fornecedor</h3>
                    <button onClick={onClose} className="text-white hover:opacity-80 text-2xl leading-none">&times;</button>
                </div>
                <div className="p-6 space-y-6">
                    {/* Identificação */}
                    <div>
                        <h4 className="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-3 flex items-center">
                            <svg className="w-4 h-4 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            Identificação
                        </h4>
                        <div className="bg-gray-50 rounded-lg p-4">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <Detail label="Razão Social" value={supplier.razao_social} />
                                <Detail label="Nome Fantasia" value={supplier.nome_fantasia} />
                                <Detail label="CNPJ/CPF" value={supplier.cnpj_formatted} mono />
                                <Detail label="Situação" value={
                                    <span className={`inline-flex px-2.5 py-1 rounded-full text-xs font-semibold ${supplier.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                        {supplier.is_active ? 'Ativo' : 'Inativo'}
                                    </span>
                                } />
                            </div>
                        </div>
                    </div>

                    {/* Contato */}
                    <div>
                        <h4 className="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-3 flex items-center">
                            <svg className="w-4 h-4 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            Contato
                        </h4>
                        <div className="bg-gray-50 rounded-lg p-4">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <Detail label="Telefone" value={supplier.contact_formatted} />
                                <Detail label="E-mail" value={supplier.email} />
                            </div>
                        </div>
                    </div>

                    {/* Registro */}
                    <div>
                        <h4 className="text-sm font-semibold text-gray-700 uppercase tracking-wider mb-3 flex items-center">
                            <svg className="w-4 h-4 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Registro
                        </h4>
                        <div className="bg-gray-50 rounded-lg p-4">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <Detail label="Cadastrado em" value={formatDateTime(supplier.created_at)} />
                                <Detail label="Última atualização" value={formatDateTime(supplier.updated_at)} />
                            </div>
                        </div>
                    </div>

                    <div className="flex justify-end pt-4 border-t">
                        <Button variant="outline" onClick={onClose}>Fechar</Button>
                    </div>
                </div>
            </div>
        </div>
    );
}

function Detail({ label, value, mono }) {
    return (
        <div>
            <p className="text-xs font-medium text-gray-500 uppercase mb-1">{label}</p>
            <p className={`text-sm text-gray-900 ${mono ? 'font-mono' : ''}`}>{value || '-'}</p>
        </div>
    );
}
