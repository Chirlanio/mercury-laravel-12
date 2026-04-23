import { Head, Link } from '@inertiajs/react';
import { ArrowLeftIcon, ChartBarIcon } from '@heroicons/react/24/outline';
import {
    ResponsiveContainer,
    LineChart, Line,
    BarChart, Bar,
    PieChart, Pie, Cell,
    XAxis, YAxis,
    CartesianGrid,
    Tooltip,
    Legend,
} from 'recharts';
import Button from '@/Components/Button';

const STATUS_COLORS = {
    draft: '#9ca3af',
    requested: '#f59e0b',
    issued: '#3b82f6',
    active: '#10b981',
    expired: '#6b7280',
    cancelled: '#ef4444',
};

export default function Dashboard({ analytics = {}, isStoreScoped, scopedStoreCode }) {
    const byMonth = analytics.by_month || [];
    const byStore = analytics.by_store || [];
    const byInfluencer = analytics.by_influencer || [];
    const byStatus = analytics.by_status || [];

    const pieData = byStatus.map((s) => ({
        name: s.label,
        value: s.total,
        fill: STATUS_COLORS[s.status] || '#9ca3af',
    }));

    return (
        <>
            <Head title="Dashboard de Cupons" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-center">
                        <div>
                            <h1 className="text-3xl font-bold text-gray-900 flex items-center gap-2">
                                <ChartBarIcon className="h-7 w-7 text-indigo-600" />
                                Dashboard de Cupons
                            </h1>
                            <p className="mt-1 text-sm text-gray-600">
                                Visão analítica de emissões, top lojas e influencers
                                {isStoreScoped && scopedStoreCode && (
                                    <span className="ml-2 text-xs font-medium text-indigo-600">
                                        (escopo: loja {scopedStoreCode})
                                    </span>
                                )}
                            </p>
                        </div>
                        <Link href={route('coupons.index')}>
                            <Button variant="secondary" icon={ArrowLeftIcon}>
                                Voltar para listagem
                            </Button>
                        </Link>
                    </div>

                    {/* Grid 2x2 de gráficos — mobile: 1 col; lg+: 2 cols */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {/* 1. Emissões por mês */}
                        <div className="bg-white shadow-sm rounded-lg p-4">
                            <h2 className="text-sm font-semibold text-gray-700 uppercase mb-3">
                                Emissões por mês (últimos 12)
                            </h2>
                            {byMonth.length === 0 ? (
                                <p className="text-sm text-gray-400 text-center py-12">Sem dados</p>
                            ) : (
                                <ResponsiveContainer width="100%" height={280}>
                                    <LineChart data={byMonth}>
                                        <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                        <XAxis dataKey="month" fontSize={11} />
                                        <YAxis fontSize={11} allowDecimals={false} />
                                        <Tooltip />
                                        <Legend />
                                        <Line
                                            type="monotone"
                                            dataKey="total"
                                            name="Cupons criados"
                                            stroke="#4f46e5"
                                            strokeWidth={2}
                                            dot={{ r: 4 }}
                                        />
                                    </LineChart>
                                </ResponsiveContainer>
                            )}
                        </div>

                        {/* 2. Distribuição por status */}
                        <div className="bg-white shadow-sm rounded-lg p-4">
                            <h2 className="text-sm font-semibold text-gray-700 uppercase mb-3">
                                Distribuição por status
                            </h2>
                            {pieData.length === 0 ? (
                                <p className="text-sm text-gray-400 text-center py-12">Sem dados</p>
                            ) : (
                                <ResponsiveContainer width="100%" height={280}>
                                    <PieChart>
                                        <Pie
                                            data={pieData}
                                            cx="50%"
                                            cy="50%"
                                            outerRadius={90}
                                            label={(e) => `${e.name} (${e.value})`}
                                            labelLine={false}
                                            dataKey="value"
                                        >
                                            {pieData.map((entry, idx) => (
                                                <Cell key={idx} fill={entry.fill} />
                                            ))}
                                        </Pie>
                                        <Tooltip />
                                    </PieChart>
                                </ResponsiveContainer>
                            )}
                        </div>

                        {/* 3. Top 10 lojas solicitantes */}
                        <div className="bg-white shadow-sm rounded-lg p-4">
                            <h2 className="text-sm font-semibold text-gray-700 uppercase mb-3">
                                Top 10 lojas solicitantes
                            </h2>
                            {byStore.length === 0 ? (
                                <p className="text-sm text-gray-400 text-center py-12">Sem dados</p>
                            ) : (
                                <ResponsiveContainer width="100%" height={320}>
                                    <BarChart data={byStore} layout="vertical" margin={{ left: 8, right: 16 }}>
                                        <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                        <XAxis type="number" fontSize={11} allowDecimals={false} />
                                        <YAxis
                                            type="category"
                                            dataKey="store_name"
                                            fontSize={11}
                                            width={160}
                                            interval={0}
                                        />
                                        <Tooltip
                                            formatter={(value) => [value, 'Cupons']}
                                            labelFormatter={(label, payload) => {
                                                const row = payload?.[0]?.payload;
                                                return row ? `${row.store_code} — ${row.store_name}` : label;
                                            }}
                                        />
                                        <Bar dataKey="total" name="Cupons" fill="#6366f1" />
                                    </BarChart>
                                </ResponsiveContainer>
                            )}
                        </div>

                        {/* 4. Top 10 influencers */}
                        <div className="bg-white shadow-sm rounded-lg p-4">
                            <h2 className="text-sm font-semibold text-gray-700 uppercase mb-3">
                                Top 10 influencers por volume
                            </h2>
                            {byInfluencer.length === 0 ? (
                                <p className="text-sm text-gray-400 text-center py-12">Sem dados</p>
                            ) : (
                                <ResponsiveContainer width="100%" height={280}>
                                    <BarChart data={byInfluencer} layout="vertical">
                                        <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                        <XAxis type="number" fontSize={11} allowDecimals={false} />
                                        <YAxis type="category" dataKey="name" fontSize={11} width={140} />
                                        <Tooltip />
                                        <Bar dataKey="total" name="Cupons" fill="#a855f7" />
                                    </BarChart>
                                </ResponsiveContainer>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
