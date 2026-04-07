import { Head, router, useForm } from '@inertiajs/react';
import CentralLayout from '@/Layouts/CentralLayout';
import { useState } from 'react';
import {
    PlusIcon,
    MagnifyingGlassIcon,
    EyeIcon,
    PauseIcon,
    PlayIcon,
    TrashIcon,
} from '@heroicons/react/24/outline';

export default function Index({ tenants, plans, filters }) {
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [search, setSearch] = useState(filters.search || '');

    const handleSearch = (e) => {
        e.preventDefault();
        router.get('/admin/tenants', { search }, { preserveState: true });
    };

    const handleFilter = (key, value) => {
        router.get('/admin/tenants', { ...filters, [key]: value }, { preserveState: true });
    };

    return (
        <CentralLayout title="Tenants">
            <Head title="Tenants - Mercury SaaS" />

            {/* Header */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                <form onSubmit={handleSearch} className="flex gap-2 flex-1 max-w-md">
                    <div className="relative flex-1">
                        <MagnifyingGlassIcon className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar por nome, email, CNPJ..."
                            className="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-indigo-500 focus:border-indigo-500"
                        />
                    </div>
                    <select
                        value={filters.status || ''}
                        onChange={(e) => handleFilter('status', e.target.value)}
                        className="border border-gray-300 rounded-md text-sm px-3 py-2"
                    >
                        <option value="">Todos</option>
                        <option value="active">Ativos</option>
                        <option value="inactive">Inativos</option>
                    </select>
                </form>

                <button
                    onClick={() => setShowCreateModal(true)}
                    className="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                >
                    <PlusIcon className="h-4 w-4" />
                    Novo Tenant
                </button>
            </div>

            {/* Table */}
            <div className="bg-white shadow rounded-lg overflow-hidden">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Empresa</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Domínio</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Plano</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Criado em</th>
                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                        {tenants.data?.map((tenant) => (
                            <tr key={tenant.id} className="hover:bg-gray-50">
                                <td className="px-6 py-4">
                                    <div>
                                        <p className="text-sm font-medium text-gray-900">{tenant.name}</p>
                                        <p className="text-xs text-gray-500">{tenant.owner_email}</p>
                                    </div>
                                </td>
                                <td className="px-6 py-4 text-sm text-gray-500">{tenant.domain || '-'}</td>
                                <td className="px-6 py-4">
                                    <span className="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-800">
                                        {tenant.plan?.name || 'Nenhum'}
                                    </span>
                                    {tenant.is_trialing && (
                                        <span className="ml-1 inline-flex items-center rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-800">
                                            Trial
                                        </span>
                                    )}
                                </td>
                                <td className="px-6 py-4">
                                    <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                        tenant.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                    }`}>
                                        {tenant.is_active ? 'Ativo' : 'Suspenso'}
                                    </span>
                                </td>
                                <td className="px-6 py-4 text-sm text-gray-500">{tenant.created_at}</td>
                                <td className="px-6 py-4 text-right">
                                    <div className="flex justify-end gap-2">
                                        <button
                                            onClick={() => router.get(`/admin/tenants/${tenant.id}`)}
                                            className="text-indigo-600 hover:text-indigo-800"
                                            title="Ver detalhes"
                                        >
                                            <EyeIcon className="h-4 w-4" />
                                        </button>
                                        {tenant.is_active ? (
                                            <button
                                                onClick={() => {
                                                    if (confirm(`Suspender ${tenant.name}?`)) {
                                                        router.post(`/admin/tenants/${tenant.id}/suspend`);
                                                    }
                                                }}
                                                className="text-yellow-600 hover:text-yellow-800"
                                                title="Suspender"
                                            >
                                                <PauseIcon className="h-4 w-4" />
                                            </button>
                                        ) : (
                                            <button
                                                onClick={() => router.post(`/admin/tenants/${tenant.id}/reactivate`)}
                                                className="text-green-600 hover:text-green-800"
                                                title="Reativar"
                                            >
                                                <PlayIcon className="h-4 w-4" />
                                            </button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {tenants.data?.length === 0 && (
                            <tr>
                                <td colSpan="6" className="px-6 py-12 text-center text-sm text-gray-500">
                                    Nenhum tenant encontrado.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {/* Pagination */}
            {tenants.last_page > 1 && (
                <div className="flex justify-center gap-2 mt-4">
                    {tenants.links?.map((link, i) => (
                        <button
                            key={i}
                            disabled={!link.url}
                            onClick={() => link.url && router.get(link.url)}
                            className={`px-3 py-1.5 text-sm rounded ${
                                link.active
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-white text-gray-700 border hover:bg-gray-50'
                            } ${!link.url ? 'opacity-50 cursor-not-allowed' : ''}`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}

            {/* Create Modal */}
            {showCreateModal && (
                <CreateTenantModal
                    plans={plans}
                    onClose={() => setShowCreateModal(false)}
                />
            )}
        </CentralLayout>
    );
}

function formatCnpj(value) {
    const digits = value.replace(/\D/g, '').slice(0, 14);
    if (digits.length <= 2) return digits;
    if (digits.length <= 5) return `${digits.slice(0, 2)}.${digits.slice(2)}`;
    if (digits.length <= 8) return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5)}`;
    if (digits.length <= 12) return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5, 8)}/${digits.slice(8)}`;
    return `${digits.slice(0, 2)}.${digits.slice(2, 5)}.${digits.slice(5, 8)}/${digits.slice(8, 12)}-${digits.slice(12)}`;
}

function CreateTenantModal({ plans, onClose }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        slug: '',
        cnpj: '',
        plan_id: '',
        owner_name: '',
        owner_email: '',
        admin_password: '',
        trial_days: 30,
    });

    const submit = (e) => {
        e.preventDefault();
        post('/admin/tenants', {
            onSuccess: () => onClose(),
        });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center">
            <div className="fixed inset-0 bg-gray-600/75" onClick={onClose} />
            <div className="relative bg-white rounded-lg shadow-xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div className="px-6 py-4 border-b">
                    <h3 className="text-lg font-semibold text-gray-900">Novo Tenant</h3>
                </div>
                <form onSubmit={submit} className="p-6 space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div className="col-span-2">
                            <label className="block text-sm font-medium text-gray-700">Nome da Empresa *</label>
                            <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:ring-indigo-500 focus:border-indigo-500" required />
                            {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Slug (URL)</label>
                            <input type="text" value={data.slug} onChange={(e) => setData('slug', e.target.value)}
                                placeholder="auto-gerado" className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">CNPJ</label>
                            <input type="text" value={data.cnpj}
                                onChange={(e) => setData('cnpj', formatCnpj(e.target.value))}
                                placeholder="00.000.000/0000-00" maxLength={18}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Nome do Responsável *</label>
                            <input type="text" value={data.owner_name} onChange={(e) => setData('owner_name', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" required />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">E-mail do Responsável *</label>
                            <input type="email" value={data.owner_email} onChange={(e) => setData('owner_email', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" required />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Plano</label>
                            <select value={data.plan_id} onChange={(e) => setData('plan_id', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="">Nenhum</option>
                                {plans.map((plan) => (
                                    <option key={plan.id} value={plan.id}>{plan.name}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Dias de Trial</label>
                            <input type="number" value={data.trial_days} onChange={(e) => setData('trial_days', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" min="0" />
                        </div>
                        <div className="col-span-2">
                            <label className="block text-sm font-medium text-gray-700">Senha do Admin</label>
                            <input type="password" value={data.admin_password} onChange={(e) => setData('admin_password', e.target.value)}
                                placeholder="auto-gerada se vazio" className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                        </div>
                    </div>

                    <div className="flex justify-end gap-3 pt-4 border-t">
                        <button type="button" onClick={onClose}
                            className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                            Cancelar
                        </button>
                        <button type="submit" disabled={processing}
                            className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50">
                            {processing ? 'Criando...' : 'Criar Tenant'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
