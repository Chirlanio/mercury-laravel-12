import { Head, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import axios from 'axios';
import {
    BanknotesIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    FireIcon,
    ChartBarIcon,
    CalendarIcon,
    TagIcon,
    PencilSquareIcon,
} from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import { maskMoney, parseMoney } from '@/Hooks/useMasks';
import {
    ResponsiveContainer,
    BarChart,
    Bar,
    XAxis,
    YAxis,
    Tooltip,
    Legend,
    CartesianGrid,
    Cell,
} from 'recharts';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import StatusBadge from '@/Components/Shared/StatusBadge';
import EmptyState from '@/Components/Shared/EmptyState';
import PageHeader from '@/Components/Shared/PageHeader';

const BRL = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const MONTH_LABELS = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

const STATUS_COLORS = {
    ok: { bar: '#10b981', text: 'text-green-700', bg: 'bg-green-50', border: 'border-green-200' },
    warning: { bar: '#f59e0b', text: 'text-amber-700', bg: 'bg-amber-50', border: 'border-amber-200' },
    exceeded: { bar: '#ef4444', text: 'text-red-700', bg: 'bg-red-50', border: 'border-red-200' },
};

const TABS = [
    { key: 'by_cost_center', label: 'Por centro de custo' },
    { key: 'by_accounting_class', label: 'Por conta contábil' },
    { key: 'by_item', label: 'Detalhe por linha' },
];

export default function Dashboard({ budget, consumption }) {
    const [activeTab, setActiveTab] = useState('by_cost_center');
    const [editingItem, setEditingItem] = useState(null);
    const { hasPermission } = usePermissions();
    const canEdit = hasPermission(PERMISSIONS.UPLOAD_BUDGETS);

    const statusCounts = useMemo(() => {
        const counts = { ok: 0, warning: 0, exceeded: 0 };
        (consumption?.by_item || []).forEach((item) => {
            counts[item.status] = (counts[item.status] || 0) + 1;
        });
        return counts;
    }, [consumption]);

    const chartData = useMemo(() => {
        return (consumption?.by_month || []).map((m) => ({
            name: MONTH_LABELS[m.month - 1],
            previsto: m.forecast,
            comprometido: m.committed ?? 0,
            realizado: m.realized,
        }));
    }, [consumption]);

    const statisticsCards = useMemo(() => [
        {
            label: 'Previsto total',
            value: consumption?.totals?.forecast || 0,
            format: 'currency',
            icon: BanknotesIcon,
            color: 'indigo',
        },
        {
            label: 'Comprometido',
            value: consumption?.totals?.committed || 0,
            format: 'currency',
            icon: FireIcon,
            color: 'orange',
            sub: 'Em fluxo + pago',
        },
        {
            label: 'Realizado (Pago)',
            value: consumption?.totals?.realized || 0,
            format: 'currency',
            icon: CheckCircleIcon,
            color: 'green',
            sub: 'Efetivamente saído do caixa',
        },
        {
            label: 'Disponível',
            value: consumption?.totals?.available || 0,
            format: 'currency',
            icon: BanknotesIcon,
            color: (consumption?.totals?.available || 0) < 0 ? 'red' : 'teal',
            sub: 'Previsto − Comprometido',
        },
        {
            label: 'Utilização',
            value: consumption?.totals?.utilization_pct || 0,
            format: 'percentage',
            icon: ChartBarIcon,
            color: overallColor(consumption?.totals?.utilization_pct || 0),
        },
        {
            label: 'Linhas em alerta',
            value: statusCounts.warning + statusCounts.exceeded,
            format: 'number',
            icon: ExclamationTriangleIcon,
            color: statusCounts.exceeded > 0 ? 'red' : statusCounts.warning > 0 ? 'orange' : 'gray',
            sub: `${statusCounts.exceeded} excedido${statusCounts.exceeded !== 1 ? 's' : ''} · ${statusCounts.warning} warning`,
        },
    ], [consumption, statusCounts]);

    return (
        <>
            <Head title={`Dashboard — ${budget?.scope_label || 'Orçamento'}`} />

            <div className="py-8">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <PageHeader
                        title="Dashboard de Consumo"
                        subtitle={(
                            <span className="inline-flex flex-wrap items-center gap-2 align-middle">
                                <StatusBadge variant="indigo" icon={TagIcon}>
                                    {budget?.scope_label}
                                </StatusBadge>
                                <StatusBadge variant="gray" icon={CalendarIcon}>
                                    {budget?.year}
                                </StatusBadge>
                                <StatusBadge variant="gray" className="font-mono">
                                    v{budget?.version_label}
                                </StatusBadge>
                                {budget?.is_active ? (
                                    <StatusBadge variant="success">Ativo</StatusBadge>
                                ) : (
                                    <StatusBadge variant="gray">Inativo</StatusBadge>
                                )}
                            </span>
                        )}
                        actions={[
                            { type: 'back', href: route('budgets.index') },
                            { type: 'download', label: 'Exportar xlsx', download: route('budgets.export', budget.id) },
                        ]}
                    />

                    <StatisticsGrid cards={statisticsCards} cols={6} />

                    {/* Gráfico previsto × realizado por mês */}
                    <div className="mt-6 bg-white shadow-sm rounded-lg p-6">
                        <div className="flex items-center gap-2 mb-4">
                            <ChartBarIcon className="h-5 w-5 text-indigo-600" />
                            <h2 className="text-lg font-semibold text-gray-900">
                                Previsto × Realizado por mês
                            </h2>
                        </div>
                        {chartData.length === 0 ? (
                            <EmptyState
                                title="Sem dados para exibir"
                                description="Este orçamento ainda não tem valores mensais registrados."
                                compact
                            />
                        ) : (
                            <div className="h-72">
                                <ResponsiveContainer width="100%" height="100%">
                                    <BarChart data={chartData} margin={{ top: 10, right: 24, left: 0, bottom: 0 }}>
                                        <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                                        <XAxis dataKey="name" tick={{ fontSize: 12 }} />
                                        <YAxis
                                            tick={{ fontSize: 11 }}
                                            tickFormatter={(v) => v >= 1000 ? `${(v / 1000).toFixed(0)}k` : v}
                                        />
                                        <Tooltip
                                            formatter={(value) => BRL.format(value)}
                                            contentStyle={{ fontSize: 12, borderRadius: 4 }}
                                        />
                                        <Legend wrapperStyle={{ fontSize: 12, paddingTop: 10 }} />
                                        <Bar dataKey="previsto" name="Previsto" fill="#6366f1" radius={[3, 3, 0, 0]} />
                                        <Bar dataKey="comprometido" name="Comprometido" fill="#f59e0b" radius={[3, 3, 0, 0]} />
                                        <Bar dataKey="realizado" name="Realizado" fill="#10b981" radius={[3, 3, 0, 0]} />
                                    </BarChart>
                                </ResponsiveContainer>
                            </div>
                        )}
                    </div>

                    {/* Tabs */}
                    <div className="mt-6 bg-white shadow-sm rounded-lg">
                        <div className="border-b border-gray-200">
                            <nav className="flex -mb-px" aria-label="Tabs">
                                {TABS.map((tab) => (
                                    <button
                                        key={tab.key}
                                        onClick={() => setActiveTab(tab.key)}
                                        className={`px-6 py-3 text-sm font-medium border-b-2 transition-colors ${
                                            activeTab === tab.key
                                                ? 'border-indigo-600 text-indigo-700'
                                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                        }`}
                                    >
                                        {tab.label}
                                    </button>
                                ))}
                            </nav>
                        </div>

                        <div className="p-6">
                            {activeTab === 'by_cost_center' && (
                                <AggregationTable
                                    rows={consumption?.by_cost_center || []}
                                    dimensionLabel="Centro de custo"
                                    showItemsCount
                                />
                            )}
                            {activeTab === 'by_accounting_class' && (
                                <AggregationTable
                                    rows={consumption?.by_accounting_class || []}
                                    dimensionLabel="Conta contábil"
                                    showItemsCount
                                />
                            )}
                            {activeTab === 'by_item' && (
                                <ItemsTable
                                    rows={consumption?.by_item || []}
                                    canEdit={canEdit}
                                    onEdit={setEditingItem}
                                />
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {editingItem && (
                <EditItemModal
                    item={editingItem}
                    onClose={() => setEditingItem(null)}
                    onSaved={() => {
                        setEditingItem(null);
                        router.reload({ only: ['consumption'] });
                    }}
                />
            )}
        </>
    );
}

function AggregationTable({ rows, dimensionLabel, showItemsCount }) {
    if (rows.length === 0) {
        return (
            <EmptyState
                title="Sem dados para exibir"
                description="Nenhuma agregação disponível neste orçamento."
                compact
            />
        );
    }

    return (
        <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
                <thead>
                    <tr className="border-b border-gray-200">
                        <th className="px-3 py-2 text-left font-medium text-gray-700">
                            {dimensionLabel}
                        </th>
                        {showItemsCount && (
                            <th className="px-3 py-2 text-right font-medium text-gray-700">
                                Linhas
                            </th>
                        )}
                        <th className="px-3 py-2 text-right font-medium text-gray-700">
                            Previsto
                        </th>
                        <th className="px-3 py-2 text-right font-medium text-gray-700">
                            Comprometido
                        </th>
                        <th className="px-3 py-2 text-right font-medium text-gray-700">
                            Realizado
                        </th>
                        <th className="px-3 py-2 text-right font-medium text-gray-700">
                            Disponível
                        </th>
                        <th className="px-3 py-2 text-right font-medium text-gray-700 w-48">
                            Utilização
                        </th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                    {rows.map((row) => (
                        <tr key={row.id} className="hover:bg-gray-50">
                            <td className="px-3 py-2">
                                <span className="block text-gray-900">{row.name}</span>
                            </td>
                            {showItemsCount && (
                                <td className="px-3 py-2 text-right text-gray-600 text-xs">
                                    {row.items_count}
                                </td>
                            )}
                            <td className="px-3 py-2 text-right font-mono text-gray-900">
                                {BRL.format(row.forecast)}
                            </td>
                            <td className="px-3 py-2 text-right font-mono text-gray-900">
                                {BRL.format(row.committed ?? 0)}
                            </td>
                            <td className="px-3 py-2 text-right font-mono text-green-700">
                                {BRL.format(row.realized)}
                            </td>
                            <td className={`px-3 py-2 text-right font-mono ${row.available < 0 ? 'text-red-600 font-semibold' : 'text-gray-900'}`}>
                                {BRL.format(row.available)}
                            </td>
                            <td className="px-3 py-2">
                                <UtilizationBar pct={row.utilization_pct} status={row.status} />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function ItemsTable({ rows, canEdit, onEdit }) {
    if (rows.length === 0) {
        return (
            <EmptyState
                title="Sem linhas cadastradas"
                description="Este orçamento ainda não tem itens."
                compact
            />
        );
    }

    return (
        <div className="overflow-x-auto">
            <table className="min-w-full text-xs">
                <thead>
                    <tr className="border-b border-gray-200">
                        <th className="px-2 py-2 text-left font-medium">Contábil</th>
                        <th className="px-2 py-2 text-left font-medium">Gerencial</th>
                        <th className="px-2 py-2 text-left font-medium">Centro de Custo</th>
                        <th className="px-2 py-2 text-left font-medium">Loja</th>
                        <th className="px-2 py-2 text-left font-medium">Fornecedor</th>
                        <th className="px-2 py-2 text-right font-medium">Previsto</th>
                        <th className="px-2 py-2 text-right font-medium">Comprometido</th>
                        <th className="px-2 py-2 text-right font-medium">Realizado</th>
                        <th className="px-2 py-2 text-right font-medium">Disponível</th>
                        <th className="px-2 py-2 text-right font-medium w-44">Utilização</th>
                        {canEdit && <th className="px-2 py-2 w-10"></th>}
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                    {rows.map((row) => (
                        <tr key={row.id} className="hover:bg-gray-50">
                            <td className="px-2 py-1.5 text-gray-700">
                                {row.accounting_class?.name || '—'}
                            </td>
                            <td className="px-2 py-1.5 text-gray-700">
                                {row.management_class?.name || '—'}
                            </td>
                            <td className="px-2 py-1.5 text-gray-700">
                                {row.cost_center?.name || '—'}
                            </td>
                            <td className="px-2 py-1.5 text-gray-600">
                                {row.store?.name || '—'}
                            </td>
                            <td className="px-2 py-1.5 text-gray-600 max-w-xs truncate" title={row.supplier}>
                                {row.supplier || '—'}
                            </td>
                            <td className="px-2 py-1.5 text-right font-mono text-gray-900">
                                {BRL.format(row.forecast)}
                            </td>
                            <td className="px-2 py-1.5 text-right font-mono text-gray-900">
                                {BRL.format(row.committed ?? 0)}
                            </td>
                            <td className="px-2 py-1.5 text-right font-mono text-green-700">
                                {BRL.format(row.realized)}
                            </td>
                            <td className={`px-2 py-1.5 text-right font-mono ${row.available < 0 ? 'text-red-600 font-semibold' : 'text-gray-900'}`}>
                                {BRL.format(row.available)}
                            </td>
                            <td className="px-2 py-1.5">
                                <UtilizationBar pct={row.utilization_pct} status={row.status} />
                            </td>
                            {canEdit && (
                                <td className="px-2 py-1.5 text-center">
                                    <button
                                        type="button"
                                        onClick={() => onEdit(row)}
                                        className="text-indigo-600 hover:text-indigo-800"
                                        title="Editar linha"
                                    >
                                        <PencilSquareIcon className="h-4 w-4" />
                                    </button>
                                </td>
                            )}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

/**
 * Modal de edição inline de BudgetItem. Edita valores mensais + campos
 * de texto (supplier, descrições). FKs (CC/AC/MC/loja) ficam read-only —
 * mudanças estruturais exigem novo upload.
 */
function EditItemModal({ item, onClose, onSaved }) {
    const [form, setForm] = useState(() => ({
        supplier: item.supplier || '',
        account_description: item.account_description || '',
        class_description: item.class_description || '',
        justification: item.justification || '',
        months: (() => {
            const out = {};
            for (let m = 1; m <= 12; m++) {
                const val = item[`month_${String(m).padStart(2, '0')}_value`] ?? (item.months?.[m]);
                out[m] = val != null ? maskMoney(String(Math.round(parseFloat(val) * 100))) : '';
            }
            return out;
        })(),
    }));
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});

    const yearTotal = useMemo(() => {
        return Object.values(form.months).reduce((s, v) => s + parseMoney(v || '0'), 0);
    }, [form.months]);

    const handleSubmit = () => {
        setProcessing(true);
        setErrors({});
        const payload = {
            supplier: form.supplier,
            account_description: form.account_description,
            class_description: form.class_description,
            justification: form.justification,
        };
        for (let m = 1; m <= 12; m++) {
            payload[`month_${String(m).padStart(2, '0')}_value`] = parseMoney(form.months[m] || '0');
        }
        axios.patch(route('budget-items.update', item.id), payload)
            .then(() => onSaved())
            .catch((err) => {
                setErrors(err.response?.data?.errors || {});
                setProcessing(false);
            });
    };

    const MONTH_LABELS_FULL = [
        'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
        'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
    ];

    return (
        <StandardModal
            show={true}
            onClose={onClose}
            title="Editar linha do orçamento"
            headerColor="bg-amber-600"
            maxWidth="5xl"
            onSubmit={handleSubmit}
            footer={
                <StandardModal.Footer
                    onCancel={onClose}
                    onSubmit="submit"
                    submitLabel={processing ? 'Salvando…' : 'Salvar alterações'}
                    submitColor="bg-amber-600 hover:bg-amber-700"
                    processing={processing}
                />
            }
        >
            <StandardModal.Section title="Contexto (somente leitura)" icon="🔒">
                <div className="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                    <StandardModal.MiniField label="Conta Contábil" value={item.accounting_class?.name || '—'} />
                    <StandardModal.MiniField label="Conta Gerencial" value={item.management_class?.name || '—'} />
                    <StandardModal.MiniField label="Centro de Custo" value={item.cost_center?.name || '—'} />
                    <StandardModal.MiniField label="Loja" value={item.store?.name || '—'} />
                </div>
                <p className="mt-2 text-xs text-gray-500">
                    Para alterar conta/CC/MC/loja, faça novo upload do orçamento.
                </p>
            </StandardModal.Section>

            <StandardModal.Section title="Dados do fornecedor / descrição" icon="🏷">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Fornecedor</label>
                        <input type="text" value={form.supplier}
                            onChange={(e) => setForm({ ...form, supplier: e.target.value })}
                            maxLength={255}
                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                        {errors.supplier && <p className="mt-1 text-xs text-red-600">{errors.supplier[0]}</p>}
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Descrição da Conta</label>
                        <input type="text" value={form.account_description}
                            onChange={(e) => setForm({ ...form, account_description: e.target.value })}
                            maxLength={255}
                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Descrição da Classe</label>
                        <input type="text" value={form.class_description}
                            onChange={(e) => setForm({ ...form, class_description: e.target.value })}
                            maxLength={255}
                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Justificativa</label>
                        <textarea value={form.justification}
                            onChange={(e) => setForm({ ...form, justification: e.target.value })}
                            rows={2} maxLength={2000}
                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    </div>
                </div>
            </StandardModal.Section>

            <StandardModal.Section title="Valores mensais" icon="💰">
                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    {MONTH_LABELS_FULL.map((name, idx) => {
                        const m = idx + 1;
                        return (
                            <div key={m}>
                                <label className="block text-xs font-medium text-gray-600 mb-1">{name}</label>
                                <div className="relative">
                                    <span className="absolute left-2 top-1/2 -translate-y-1/2 text-gray-500 text-xs">R$</span>
                                    <input type="text" value={form.months[m]}
                                        onChange={(e) => setForm({
                                            ...form,
                                            months: { ...form.months, [m]: maskMoney(e.target.value) },
                                        })}
                                        placeholder="0,00"
                                        className="w-full pl-8 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                                </div>
                            </div>
                        );
                    })}
                </div>
                <div className="mt-4 bg-indigo-50 border border-indigo-200 rounded p-3 flex justify-between items-center">
                    <span className="text-sm font-medium text-indigo-900">Total Anual</span>
                    <span className="text-xl font-bold text-indigo-700 font-mono">
                        {new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(yearTotal)}
                    </span>
                </div>
            </StandardModal.Section>
        </StandardModal>
    );
}

function UtilizationBar({ pct, status }) {
    const color = STATUS_COLORS[status] || STATUS_COLORS.ok;
    const displayPct = Math.min(pct, 100);

    return (
        <div className="flex items-center gap-2">
            <div className="flex-1 bg-gray-200 rounded-full h-2 overflow-hidden">
                <div
                    className="h-2 rounded-full"
                    style={{
                        width: `${displayPct}%`,
                        backgroundColor: color.bar,
                    }}
                />
            </div>
            <span className={`text-xs font-semibold ${color.text} w-14 text-right`}>
                {pct.toFixed(1)}%
            </span>
        </div>
    );
}

function overallColor(pct) {
    if (pct >= 100) return 'red';
    if (pct >= 70) return 'orange';
    return 'green';
}
