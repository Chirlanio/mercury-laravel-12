import { Head, router, useForm } from '@inertiajs/react';
import CentralLayout from '@/Layouts/CentralLayout';
import { useState } from 'react';
import { ArrowLeftIcon, PencilIcon } from '@heroicons/react/24/outline';
import { formatDateTime } from '@/Utils/dateHelpers';

export default function Show({ tenant, usage, plans, recentInvoices, allRoles }) {
    const planLimits = tenant.plan ? plans.find(p => p.id === tenant.plan?.id) : null;

    return (
        <CentralLayout title={`Tenant: ${tenant.name}`}>
            <Head title={`${tenant.name} - Mercury SaaS`} />

            <button
                onClick={() => router.get('/admin/tenants')}
                className="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800 mb-6"
            >
                <ArrowLeftIcon className="h-4 w-4" />
                Voltar para lista
            </button>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Main Info */}
                <div className="lg:col-span-2 space-y-6">
                    <TenantInfoCard tenant={tenant} />

                    {/* Modules */}
                    <div className="bg-white shadow rounded-lg p-6">
                        <h3 className="text-base font-semibold text-gray-900 mb-3">Módulos Habilitados</h3>
                        <div className="flex flex-wrap gap-2">
                            {tenant.modules?.map((mod) => (
                                <span key={mod} className="inline-flex items-center rounded-full bg-indigo-50 px-3 py-1 text-xs font-medium text-indigo-700">
                                    {mod}
                                </span>
                            ))}
                            {tenant.modules?.length === 0 && (
                                <p className="text-sm text-gray-500">Nenhum módulo habilitado.</p>
                            )}
                        </div>
                    </div>

                    {/* Actions */}
                    <div className="bg-white shadow rounded-lg p-6">
                        <h3 className="text-base font-semibold text-gray-900 mb-4">Ações</h3>
                        <div className="flex flex-wrap gap-3">
                            {tenant.is_active ? (
                                <button
                                    onClick={() => {
                                        if (confirm(`Suspender ${tenant.name}?`)) {
                                            router.post(`/admin/tenants/${tenant.id}/suspend`);
                                        }
                                    }}
                                    className="px-4 py-2 text-sm font-medium text-amber-800 bg-amber-100 rounded-md hover:bg-amber-200 transition-colors"
                                >
                                    Suspender
                                </button>
                            ) : (
                                <button
                                    onClick={() => router.post(`/admin/tenants/${tenant.id}/reactivate`)}
                                    className="px-4 py-2 text-sm font-medium text-green-800 bg-green-100 rounded-md hover:bg-green-200 transition-colors"
                                >
                                    Reativar
                                </button>
                            )}
                            <button
                                onClick={() => {
                                    if (confirm(`EXCLUIR ${tenant.name} e TODOS os dados? Esta ação não pode ser desfeita!`)) {
                                        router.delete(`/admin/tenants/${tenant.id}`);
                                    }
                                }}
                                className="px-4 py-2 text-sm font-medium text-red-800 bg-red-100 rounded-md hover:bg-red-200 transition-colors"
                            >
                                Excluir Permanentemente
                            </button>
                        </div>
                    </div>
                </div>

                {/* Sidebar Stats */}
                <div className="space-y-6">
                    <div className="bg-white shadow rounded-lg p-6">
                        <h3 className="text-base font-semibold text-gray-900 mb-4">Uso</h3>
                        <dl className="space-y-3">
                            <UsageItem label="Usuários" value={usage.users} max={planLimits?.max_users} />
                            <UsageItem label="Lojas" value={usage.stores} max={planLimits?.max_stores} />
                            <UsageItem label="Funcionários" value={usage.employees} />
                        </dl>
                    </div>

                    {/* Allowed Roles */}
                    <AllowedRolesForm tenantId={tenant.id} allowedRoles={tenant.allowed_roles} allRoles={allRoles} />

                    {/* Change Plan */}
                    <ChangePlanForm tenantId={tenant.id} currentPlanId={tenant.plan?.id} plans={plans} />
                </div>
            </div>
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

function TenantInfoCard({ tenant }) {
    const [editing, setEditing] = useState(false);
    const { data, setData, put, processing, errors } = useForm({
        name: tenant.name || '',
        cnpj: tenant.cnpj || '',
        owner_name: tenant.owner_name || '',
        owner_email: tenant.owner_email || '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(`/admin/tenants/${tenant.id}`, {
            onSuccess: () => setEditing(false),
        });
    };

    if (!editing) {
        return (
            <div className="bg-white shadow rounded-lg p-6">
                <div className="flex items-center justify-between mb-4">
                    <h2 className="text-lg font-semibold text-gray-900">{tenant.name}</h2>
                    <div className="flex items-center gap-2">
                        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                            tenant.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                        }`}>
                            {tenant.is_active ? 'Ativo' : 'Suspenso'}
                        </span>
                        {tenant.is_trialing && (
                            <span className="inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-800">
                                Trial ate {tenant.trial_ends_at}
                            </span>
                        )}
                        <button
                            onClick={() => setEditing(true)}
                            title="Editar dados"
                            className="inline-flex items-center justify-center w-8 h-8 rounded-md bg-amber-100 text-amber-700 hover:bg-amber-200 transition-colors"
                        >
                            <PencilIcon className="h-4 w-4" />
                        </button>
                    </div>
                </div>
                <dl className="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt className="font-medium text-gray-500">Slug / ID</dt>
                        <dd className="mt-1 text-gray-900">{tenant.slug}</dd>
                    </div>
                    <div>
                        <dt className="font-medium text-gray-500">Domínio</dt>
                        <dd className="mt-1 text-gray-900">{tenant.domain || '-'}</dd>
                    </div>
                    <div>
                        <dt className="font-medium text-gray-500">CNPJ</dt>
                        <dd className="mt-1 text-gray-900">{tenant.cnpj || '-'}</dd>
                    </div>
                    <div>
                        <dt className="font-medium text-gray-500">Plano</dt>
                        <dd className="mt-1 text-gray-900">{tenant.plan?.name || 'Nenhum'}</dd>
                    </div>
                    <div>
                        <dt className="font-medium text-gray-500">Responsável</dt>
                        <dd className="mt-1 text-gray-900">{tenant.owner_name}</dd>
                    </div>
                    <div>
                        <dt className="font-medium text-gray-500">E-mail</dt>
                        <dd className="mt-1 text-gray-900">{tenant.owner_email}</dd>
                    </div>
                    <div>
                        <dt className="font-medium text-gray-500">Criado em</dt>
                        <dd className="mt-1 text-gray-900">{formatDateTime(tenant.created_at)}</dd>
                    </div>
                </dl>
            </div>
        );
    }

    return (
        <div className="bg-white shadow rounded-lg p-6">
            <div className="flex items-center justify-between mb-4">
                <h2 className="text-lg font-semibold text-gray-900">Editar Dados</h2>
                <span className="text-xs text-gray-400">Slug: {tenant.slug}</span>
            </div>
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700">Nome da Empresa</label>
                        <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" required />
                        {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700">CNPJ</label>
                        <input type="text" value={data.cnpj} onChange={(e) => setData('cnpj', formatCnpj(e.target.value))} placeholder="00.000.000/0000-00" maxLength={18} className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700">Responsável</label>
                        <input type="text" value={data.owner_name} onChange={(e) => setData('owner_name', e.target.value)} className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" required />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700">E-mail</label>
                        <input type="email" value={data.owner_email} onChange={(e) => setData('owner_email', e.target.value)} className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" required />
                    </div>
                </div>
                <div className="flex justify-end gap-3 pt-3 border-t">
                    <button type="button" onClick={() => setEditing(false)} className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200">
                        Cancelar
                    </button>
                    <button type="submit" disabled={processing} className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50">
                        {processing ? 'Salvando...' : 'Salvar'}
                    </button>
                </div>
            </form>
        </div>
    );
}

function UsageItem({ label, value, max }) {
    const percentage = max && max > 0 ? Math.min((value / max) * 100, 100) : 0;
    const isWarning = max && max > 0 && percentage > 80;

    return (
        <div>
            <div className="flex justify-between text-sm mb-1">
                <span className="text-gray-500">{label}</span>
                <span className={`font-medium ${isWarning ? 'text-red-600' : 'text-gray-900'}`}>
                    {value}{max && max > 0 ? ` / ${max}` : ''}
                </span>
            </div>
            {max && max > 0 && (
                <div className="w-full bg-gray-200 rounded-full h-1.5">
                    <div
                        className={`h-1.5 rounded-full ${isWarning ? 'bg-red-500' : 'bg-indigo-500'}`}
                        style={{ width: `${percentage}%` }}
                    />
                </div>
            )}
        </div>
    );
}

function AllowedRolesForm({ tenantId, allowedRoles, allRoles = {} }) {
    const { data, setData, put, processing } = useForm({
        allowed_roles: allowedRoles || Object.keys(allRoles),
    });

    const toggleRole = (roleValue) => {
        const current = data.allowed_roles;
        if (current.includes(roleValue)) {
            if (current.length <= 1) return; // Must keep at least one
            setData('allowed_roles', current.filter(r => r !== roleValue));
        } else {
            setData('allowed_roles', [...current, roleValue]);
        }
    };

    const submit = (e) => {
        e.preventDefault();
        put(`/admin/tenants/${tenantId}/allowed-roles`);
    };

    return (
        <div className="bg-white shadow rounded-lg p-6">
            <h3 className="text-base font-semibold text-gray-900 mb-3">Roles Permitidas</h3>
            <p className="text-xs text-gray-500 mb-3">
                Define quais tipos de usuario o admin deste tenant pode criar.
            </p>
            <form onSubmit={submit} className="space-y-3">
                <div className="space-y-2">
                    {Object.entries(allRoles).map(([value, label]) => (
                        <label key={value} className="flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={data.allowed_roles.includes(value)}
                                onChange={() => toggleRole(value)}
                                className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                            />
                            <span className="text-sm text-gray-700">{label}</span>
                            <span className="text-xs text-gray-400">({value})</span>
                        </label>
                    ))}
                </div>
                <button
                    type="submit"
                    disabled={processing}
                    className="w-full px-3 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50"
                >
                    Salvar
                </button>
            </form>
        </div>
    );
}

function ChangePlanForm({ tenantId, currentPlanId, plans }) {
    const { data, setData, put, processing } = useForm({
        plan_id: currentPlanId || '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(`/admin/tenants/${tenantId}`);
    };

    return (
        <div className="bg-white shadow rounded-lg p-6">
            <h3 className="text-base font-semibold text-gray-900 mb-3">Alterar Plano</h3>
            <form onSubmit={submit} className="space-y-3">
                <select
                    value={data.plan_id}
                    onChange={(e) => setData('plan_id', e.target.value)}
                    className="block w-full rounded-md border-gray-300 shadow-sm text-sm"
                >
                    <option value="">Nenhum</option>
                    {plans.map((plan) => (
                        <option key={plan.id} value={plan.id}>
                            {plan.name} ({plan.max_users} users, {plan.max_stores} stores)
                        </option>
                    ))}
                </select>
                <button
                    type="submit"
                    disabled={processing}
                    className="w-full px-3 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50"
                >
                    Salvar
                </button>
            </form>
        </div>
    );
}
