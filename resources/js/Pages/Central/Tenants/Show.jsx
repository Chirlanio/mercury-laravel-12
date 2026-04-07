import { Head, router, useForm } from '@inertiajs/react';
import CentralLayout from '@/Layouts/CentralLayout';
import { ArrowLeftIcon } from '@heroicons/react/24/outline';

export default function Show({ tenant, usage, plans, recentInvoices }) {
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
                    <div className="bg-white shadow rounded-lg p-6">
                        <div className="flex items-center justify-between mb-4">
                            <h2 className="text-lg font-semibold text-gray-900">{tenant.name}</h2>
                            <div className="flex gap-2">
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
                                <dd className="mt-1 text-gray-900">{tenant.created_at}</dd>
                            </div>
                        </dl>
                    </div>

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
                                    className="px-4 py-2 text-sm font-medium text-yellow-700 bg-yellow-100 rounded-md hover:bg-yellow-200"
                                >
                                    Suspender
                                </button>
                            ) : (
                                <button
                                    onClick={() => router.post(`/admin/tenants/${tenant.id}/reactivate`)}
                                    className="px-4 py-2 text-sm font-medium text-green-700 bg-green-100 rounded-md hover:bg-green-200"
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
                                className="px-4 py-2 text-sm font-medium text-red-700 bg-red-100 rounded-md hover:bg-red-200"
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

                    {/* Change Plan */}
                    <ChangePlanForm tenantId={tenant.id} currentPlanId={tenant.plan?.id} plans={plans} />
                </div>
            </div>
        </CentralLayout>
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
