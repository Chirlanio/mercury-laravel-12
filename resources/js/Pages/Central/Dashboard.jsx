import { Head } from '@inertiajs/react';
import CentralLayout from '@/Layouts/CentralLayout';
import { formatDateTime } from '@/Utils/dateHelpers';
import {
    BuildingOffice2Icon,
    UserGroupIcon,
    CurrencyDollarIcon,
    ExclamationTriangleIcon,
    DocumentArrowDownIcon,
} from '@heroicons/react/24/outline';

const statCards = [
    { key: 'active_tenants', label: 'Tenants Ativos', icon: BuildingOffice2Icon, color: 'bg-green-500' },
    { key: 'trialing_tenants', label: 'Em Trial', icon: UserGroupIcon, color: 'bg-blue-500' },
    { key: 'inactive_tenants', label: 'Inativos', icon: ExclamationTriangleIcon, color: 'bg-red-500' },
    { key: 'pending_invoices', label: 'Faturas Pendentes', icon: CurrencyDollarIcon, color: 'bg-yellow-500' },
];

export default function Dashboard({ stats, recentTenants, planDistribution }) {
    return (
        <CentralLayout title="Dashboard">
            <Head title="Dashboard - Mercury SaaS" />

            {/* Stats Grid */}
            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
                {statCards.map((card) => (
                    <div key={card.key} className="overflow-hidden rounded-lg bg-white shadow">
                        <div className="p-5">
                            <div className="flex items-center">
                                <div className={`flex-shrink-0 rounded-md ${card.color} p-3`}>
                                    <card.icon className="h-6 w-6 text-white" />
                                </div>
                                <div className="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt className="truncate text-sm font-medium text-gray-500">{card.label}</dt>
                                        <dd className="text-2xl font-semibold text-gray-900">{stats[card.key] ?? 0}</dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                {/* Recent Tenants */}
                <div className="bg-white shadow rounded-lg">
                    <div className="px-4 py-5 sm:px-6 border-b border-gray-200">
                        <h3 className="text-base font-semibold text-gray-900">Tenants Recentes</h3>
                    </div>
                    <div className="divide-y divide-gray-200">
                        {recentTenants?.length === 0 ? (
                            <p className="p-6 text-sm text-gray-500 text-center">Nenhum tenant cadastrado.</p>
                        ) : (
                            recentTenants?.map((tenant) => (
                                <div key={tenant.id} className="px-4 py-4 sm:px-6 flex items-center justify-between">
                                    <div>
                                        <p className="text-sm font-medium text-indigo-600">{tenant.name}</p>
                                        <p className="text-xs text-gray-500">
                                            {tenant.domain} &middot; {tenant.plan}
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                                            tenant.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                                        }`}>
                                            {tenant.is_active ? 'Ativo' : 'Inativo'}
                                        </span>
                                        <span className="text-xs text-gray-400">{formatDateTime(tenant.created_at)}</span>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </div>

                {/* Plan Distribution */}
                <div className="bg-white shadow rounded-lg">
                    <div className="px-4 py-5 sm:px-6 border-b border-gray-200">
                        <h3 className="text-base font-semibold text-gray-900">Distribuição por Plano</h3>
                    </div>
                    <div className="p-6">
                        {planDistribution?.length === 0 ? (
                            <p className="text-sm text-gray-500 text-center">Nenhum plano configurado.</p>
                        ) : (
                            <div className="space-y-4">
                                {planDistribution?.map((plan) => (
                                    <div key={plan.name}>
                                        <div className="flex justify-between text-sm mb-1">
                                            <span className="font-medium text-gray-700">{plan.name}</span>
                                            <span className="text-gray-500">{plan.count} tenants</span>
                                        </div>
                                        <div className="w-full bg-gray-200 rounded-full h-2">
                                            <div
                                                className="bg-indigo-600 h-2 rounded-full transition-all"
                                                style={{
                                                    width: `${Math.max(5, (plan.count / Math.max(stats.total_tenants, 1)) * 100)}%`,
                                                }}
                                            />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Quick Links */}
            <div className="mt-6 bg-white shadow rounded-lg p-6">
                <h3 className="text-base font-semibold text-gray-900 mb-3">Recursos</h3>
                <a
                    href="/admin/manual"
                    className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-indigo-700 bg-indigo-100 rounded-md hover:bg-indigo-200 transition-colors"
                >
                    <DocumentArrowDownIcon className="h-5 w-5" />
                    Baixar Manual de Administração (PDF)
                </a>
            </div>

            {/* Revenue */}
            {stats.monthly_revenue > 0 && (
                <div className="mt-6 bg-white shadow rounded-lg p-6">
                    <h3 className="text-base font-semibold text-gray-900 mb-2">Receita do Mês</h3>
                    <p className="text-3xl font-bold text-green-600">
                        R$ {Number(stats.monthly_revenue).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                    </p>
                </div>
            )}
        </CentralLayout>
    );
}
