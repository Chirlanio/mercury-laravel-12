import { useState, useEffect } from 'react';
import {
    BarChart, Bar, LineChart, Line, PieChart, Pie, Cell,
    XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer,
} from 'recharts';
import { XMarkIcon, ChartBarIcon } from '@heroicons/react/24/outline';

const COLORS = ['#6B7280', '#3B82F6', '#EAB308', '#22C55E'];
const fmtCurrency = (v) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0);

export default function DashboardCharts({ show, onClose, statisticsUrl, dashboardUrl }) {
    const [stats, setStats] = useState(null);
    const [dashboard, setDashboard] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        if (!show) return;
        setLoading(true);

        Promise.all([
            fetch(statisticsUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json()),
            fetch(dashboardUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json()),
        ]).then(([statsData, dashData]) => {
            setStats(statsData);
            setDashboard(dashData);
            setLoading(false);
        }).catch(() => setLoading(false));
    }, [show]);

    if (!show) return null;

    const statusData = stats ? Object.entries(stats.by_status).map(([key, val]) => ({
        name: val.label, count: val.count, total: val.total,
    })) : [];

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            <div className="flex min-h-full items-start justify-center p-4 pt-16">
                <div className="fixed inset-0 bg-gray-500/75 transition-opacity" onClick={onClose} />
                <div className="relative w-full max-w-7xl bg-white rounded-xl shadow-2xl">
                    {/* Header */}
                    <div className="flex items-center justify-between px-6 py-4 border-b bg-indigo-600 rounded-t-xl">
                        <h3 className="text-lg font-semibold text-white flex items-center gap-2">
                            <ChartBarIcon className="h-5 w-5" /> Dashboard - Ordens de Pagamento
                        </h3>
                        <button onClick={onClose} className="text-white hover:text-indigo-200">
                            <XMarkIcon className="h-6 w-6" />
                        </button>
                    </div>

                    <div className="p-6">
                        {loading ? (
                            <div className="flex justify-center py-20">
                                <div className="animate-spin h-8 w-8 border-4 border-indigo-600 border-t-transparent rounded-full" />
                            </div>
                        ) : (
                            <div className="space-y-8">
                                {/* KPI Summary */}
                                {stats && (
                                    <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                                        <SummaryCard label="Vencidas" value={stats.overdue.count} total={fmtCurrency(stats.overdue.total)} color="red" />
                                        <SummaryCard label="Parcelas Vencidas" value={stats.installments.overdue} color="orange" />
                                        <SummaryCard label="Parcelas a Vencer (30d)" value={stats.installments.upcoming} color="yellow" />
                                        <SummaryCard label="Parcelas Pagas" value={stats.installments.paid} color="green" />
                                    </div>
                                )}

                                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                    {/* Status Distribution (Pie) */}
                                    <ChartCard title="Distribuição por Status">
                                        <ResponsiveContainer width="100%" height={250}>
                                            <PieChart>
                                                <Pie data={statusData} dataKey="count" nameKey="name" cx="50%" cy="50%"
                                                    outerRadius={90} label={({ name, count }) => `${name}: ${count}`}>
                                                    {statusData.map((_, i) => <Cell key={i} fill={COLORS[i % COLORS.length]} />)}
                                                </Pie>
                                                <Tooltip formatter={(v) => [v, 'Quantidade']} />
                                            </PieChart>
                                        </ResponsiveContainer>
                                    </ChartCard>

                                    {/* Value by Status (Bar) */}
                                    <ChartCard title="Valor por Status">
                                        <ResponsiveContainer width="100%" height={250}>
                                            <BarChart data={statusData} layout="vertical">
                                                <CartesianGrid strokeDasharray="3 3" />
                                                <XAxis type="number" tickFormatter={(v) => fmtCurrency(v)} />
                                                <YAxis type="category" dataKey="name" width={100} />
                                                <Tooltip formatter={(v) => fmtCurrency(v)} />
                                                <Bar dataKey="total" fill="#6366F1" radius={[0, 4, 4, 0]} />
                                            </BarChart>
                                        </ResponsiveContainer>
                                    </ChartCard>

                                    {/* Monthly Flow (Line) */}
                                    {dashboard?.monthly_detailed && (
                                        <ChartCard title="Fluxo Mensal (12 meses)" className="lg:col-span-2">
                                            <ResponsiveContainer width="100%" height={280}>
                                                <LineChart data={dashboard.monthly_detailed}>
                                                    <CartesianGrid strokeDasharray="3 3" />
                                                    <XAxis dataKey="month" />
                                                    <YAxis tickFormatter={(v) => `R$ ${(v / 1000).toFixed(0)}k`} />
                                                    <Tooltip formatter={(v) => fmtCurrency(v)} />
                                                    <Legend />
                                                    <Line type="monotone" dataKey="total" name="Criadas" stroke="#6366F1" strokeWidth={2} />
                                                    <Line type="monotone" dataKey="paid" name="Pagas" stroke="#22C55E" strokeWidth={2} />
                                                </LineChart>
                                            </ResponsiveContainer>
                                        </ChartCard>
                                    )}

                                    {/* Top Suppliers (Bar) */}
                                    {dashboard?.by_supplier?.length > 0 && (
                                        <ChartCard title="Top 10 Fornecedores" className="lg:col-span-2">
                                            <ResponsiveContainer width="100%" height={300}>
                                                <BarChart data={dashboard.by_supplier}>
                                                    <CartesianGrid strokeDasharray="3 3" />
                                                    <XAxis dataKey="supplier" angle={-35} textAnchor="end" height={80} interval={0} tick={{ fontSize: 11 }} />
                                                    <YAxis tickFormatter={(v) => fmtCurrency(v)} />
                                                    <Tooltip formatter={(v) => fmtCurrency(v)} />
                                                    <Bar dataKey="total" name="Valor Total" fill="#8B5CF6" radius={[4, 4, 0, 0]} />
                                                </BarChart>
                                            </ResponsiveContainer>
                                        </ChartCard>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}

function SummaryCard({ label, value, total, color }) {
    const colorMap = {
        red: 'bg-red-50 border-red-200 text-red-800',
        orange: 'bg-orange-50 border-orange-200 text-orange-800',
        yellow: 'bg-yellow-50 border-yellow-200 text-yellow-800',
        green: 'bg-green-50 border-green-200 text-green-800',
    };
    return (
        <div className={`rounded-lg p-4 border ${colorMap[color]}`}>
            <p className="text-xs font-medium uppercase">{label}</p>
            <p className="text-2xl font-bold mt-1">{value}</p>
            {total && <p className="text-sm font-medium mt-0.5">{total}</p>}
        </div>
    );
}

function ChartCard({ title, children, className = '' }) {
    return (
        <div className={`bg-white border rounded-lg p-4 ${className}`}>
            <h4 className="text-sm font-semibold text-gray-700 mb-3">{title}</h4>
            {children}
        </div>
    );
}
