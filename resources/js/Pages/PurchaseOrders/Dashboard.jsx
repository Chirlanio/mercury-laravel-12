import { Head, Link } from '@inertiajs/react';
import {
    BarChart, Bar, PieChart, Pie, Cell, LineChart, Line,
    XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer,
} from 'recharts';
import {
    ArrowLeftIcon, ShoppingCartIcon, ExclamationTriangleIcon,
    BuildingStorefrontIcon, TagIcon, CalendarDaysIcon,
} from '@heroicons/react/24/outline';
import Button from '@/Components/Button';
import EmptyState from '@/Components/Shared/EmptyState';

const STATUS_HEX = {
    warning: '#eab308',
    info: '#3b82f6',
    purple: '#a855f7',
    danger: '#ef4444',
    success: '#22c55e',
};

const formatCurrency = (value) => new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
}).format(Number(value || 0));

const formatMonth = (yyyymm) => {
    const [y, m] = yyyymm.split('-');
    const date = new Date(parseInt(y, 10), parseInt(m, 10) - 1, 1);
    return date.toLocaleDateString('pt-BR', { month: 'short', year: '2-digit' });
};

export default function Dashboard({
    isStoreScoped = false,
    scopedStoreCode,
    statusDistribution = {},
    statusLabels = {},
    statusColors = {},
    byMonth = [],
    topSuppliers = [],
    topBrands = [],
    overdueCount = 0,
}) {
    const statusData = Object.entries(statusDistribution).map(([key, count]) => ({
        name: statusLabels[key] || key,
        value: count,
        fill: STATUS_HEX[statusColors[key]] || '#9ca3af',
    }));

    const monthData = byMonth.map((m) => ({
        month: formatMonth(m.month),
        ordens: m.count,
        custo: m.total_cost,
    }));

    const totalOrders = Object.values(statusDistribution).reduce((a, b) => a + b, 0);

    return (
        <>
            <Head title="Dashboard — Ordens de Compra" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6 flex items-center gap-3">
                        <Link href={route('purchase-orders.index')}>
                            <Button variant="outline" size="sm" icon={ArrowLeftIcon}>Voltar</Button>
                        </Link>
                        <div className="flex-1">
                            <h1 className="text-2xl font-bold text-gray-900">
                                Dashboard — Ordens de Compra
                                {isStoreScoped && <span className="ml-2 text-base text-indigo-600 font-medium">(Loja {scopedStoreCode})</span>}
                            </h1>
                            <p className="text-sm text-gray-600">Visão consolidada do módulo</p>
                        </div>
                    </div>

                    {totalOrders === 0 ? (
                        <EmptyState
                            icon={ShoppingCartIcon}
                            title="Sem dados ainda"
                            description="Crie ou importe ordens de compra para ver o dashboard."
                        />
                    ) : (
                        <>
                            {/* KPIs */}
                            <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                                <KpiCard
                                    label="Total de Ordens"
                                    value={totalOrders}
                                    icon={ShoppingCartIcon}
                                    color="indigo"
                                />
                                <KpiCard
                                    label="Atrasadas"
                                    value={overdueCount}
                                    icon={ExclamationTriangleIcon}
                                    color={overdueCount > 0 ? 'red' : 'gray'}
                                />
                                <KpiCard
                                    label="Fornecedores"
                                    value={topSuppliers.length}
                                    icon={BuildingStorefrontIcon}
                                    color="blue"
                                    sub={topSuppliers.length === 5 ? '+ outros' : null}
                                />
                                <KpiCard
                                    label="Marcas"
                                    value={topBrands.length}
                                    icon={TagIcon}
                                    color="purple"
                                    sub={topBrands.length === 5 ? '+ outras' : null}
                                />
                            </div>

                            {/* Status pie + monthly trend */}
                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                                <div className="bg-white shadow-sm rounded-lg p-4">
                                    <h3 className="text-sm font-medium text-gray-700 mb-3">Distribuição por Status</h3>
                                    <ResponsiveContainer width="100%" height={250}>
                                        <PieChart>
                                            <Pie
                                                data={statusData}
                                                cx="50%"
                                                cy="50%"
                                                outerRadius={85}
                                                dataKey="value"
                                                label={(entry) => entry.name}
                                            >
                                                {statusData.map((entry, idx) => (
                                                    <Cell key={`cell-${idx}`} fill={entry.fill} />
                                                ))}
                                            </Pie>
                                            <Tooltip />
                                        </PieChart>
                                    </ResponsiveContainer>
                                </div>

                                <div className="bg-white shadow-sm rounded-lg p-4">
                                    <h3 className="text-sm font-medium text-gray-700 mb-3">
                                        <CalendarDaysIcon className="inline h-4 w-4 mr-1" />
                                        Ordens criadas nos últimos 6 meses
                                    </h3>
                                    {monthData.length > 0 ? (
                                        <ResponsiveContainer width="100%" height={250}>
                                            <LineChart data={monthData}>
                                                <CartesianGrid strokeDasharray="3 3" />
                                                <XAxis dataKey="month" />
                                                <YAxis />
                                                <Tooltip
                                                    formatter={(value, name) => name === 'custo' ? formatCurrency(value) : value}
                                                />
                                                <Legend />
                                                <Line type="monotone" dataKey="ordens" stroke="#4338ca" strokeWidth={2} />
                                            </LineChart>
                                        </ResponsiveContainer>
                                    ) : (
                                        <p className="text-sm text-gray-500 text-center py-12">Sem dados nos últimos 6 meses</p>
                                    )}
                                </div>
                            </div>

                            {/* Top suppliers + brands */}
                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <div className="bg-white shadow-sm rounded-lg p-4">
                                    <h3 className="text-sm font-medium text-gray-700 mb-3">
                                        <BuildingStorefrontIcon className="inline h-4 w-4 mr-1" />
                                        Top 5 Fornecedores
                                    </h3>
                                    {topSuppliers.length > 0 ? (
                                        <ResponsiveContainer width="100%" height={220}>
                                            <BarChart data={topSuppliers} layout="vertical" margin={{ left: 80 }}>
                                                <CartesianGrid strokeDasharray="3 3" />
                                                <XAxis type="number" />
                                                <YAxis type="category" dataKey="supplier_name" tick={{ fontSize: 11 }} width={75} />
                                                <Tooltip />
                                                <Bar dataKey="count" fill="#3b82f6" />
                                            </BarChart>
                                        </ResponsiveContainer>
                                    ) : (
                                        <p className="text-sm text-gray-500 text-center py-12">Sem fornecedores</p>
                                    )}
                                </div>

                                <div className="bg-white shadow-sm rounded-lg p-4">
                                    <h3 className="text-sm font-medium text-gray-700 mb-3">
                                        <TagIcon className="inline h-4 w-4 mr-1" />
                                        Top 5 Marcas
                                    </h3>
                                    {topBrands.length > 0 ? (
                                        <ResponsiveContainer width="100%" height={220}>
                                            <BarChart data={topBrands} layout="vertical" margin={{ left: 80 }}>
                                                <CartesianGrid strokeDasharray="3 3" />
                                                <XAxis type="number" />
                                                <YAxis type="category" dataKey="brand_name" tick={{ fontSize: 11 }} width={75} />
                                                <Tooltip />
                                                <Bar dataKey="count" fill="#a855f7" />
                                            </BarChart>
                                        </ResponsiveContainer>
                                    ) : (
                                        <p className="text-sm text-gray-500 text-center py-12">Sem marcas associadas</p>
                                    )}
                                </div>
                            </div>
                        </>
                    )}
                </div>
            </div>
        </>
    );
}

const COLOR_CLASSES = {
    indigo: 'bg-indigo-50 text-indigo-700 border-indigo-200',
    red: 'bg-red-50 text-red-700 border-red-200',
    gray: 'bg-gray-50 text-gray-700 border-gray-200',
    blue: 'bg-blue-50 text-blue-700 border-blue-200',
    purple: 'bg-purple-50 text-purple-700 border-purple-200',
};

function KpiCard({ label, value, icon: Icon, color, sub }) {
    return (
        <div className={`rounded-lg border p-4 ${COLOR_CLASSES[color] || COLOR_CLASSES.gray}`}>
            <div className="flex items-center justify-between mb-1">
                <span className="text-xs font-medium uppercase tracking-wider opacity-80">{label}</span>
                {Icon && <Icon className="h-4 w-4 opacity-60" />}
            </div>
            <div className="text-3xl font-bold">{value}</div>
            {sub && <div className="text-xs opacity-70 mt-1">{sub}</div>}
        </div>
    );
}
