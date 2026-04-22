import { useMemo } from 'react';
import {
    BarChart,
    Bar,
    LineChart,
    Line,
    PieChart,
    Pie,
    Cell,
    Tooltip,
    Legend,
    XAxis,
    YAxis,
    CartesianGrid,
    ResponsiveContainer,
} from 'recharts';
import EmptyState from '@/Components/Shared/EmptyState';
import { formatCurrency, monthLabel, splitYearMonth } from '@/lib/dre';

/**
 * Painel de gráficos da DRE (playbook prompt 13).
 *
 * 3 visualizações montadas a partir da mesma `matrix` já carregada na tela:
 *   - BarChart empilhado: Receita × Despesa × EBITDA por mês.
 *   - LineChart: evolução de margens (realizado × orçado × ano anterior).
 *   - PieChart: distribuição de despesas por linha no período.
 *
 * Lê da prop `matrix` sem novo fetch — espelha exatamente a matriz mensal.
 * Zero custo extra de backend; adicionar charts não invalida nada do cache.
 *
 * Heurística de classificação (por nature + code):
 *   - Receitas: nature='revenue'.
 *   - Despesas: nature='expense'.
 *   - EBITDA: linha subtotal com code contendo 'EBITDA' (case-insensitive).
 *   - Margem líquida: linha subtotal com code contendo 'MARGEM' ou 'LUCRO_LIQUIDO'.
 */
export default function ChartsPanel({ matrix, filter }) {
    const lines = matrix?.lines || [];

    const yearMonths = useMemo(() => {
        const set = new Set();
        lines.forEach((l) => {
            Object.keys(l.months || {}).forEach((ym) => set.add(ym));
        });
        return Array.from(set).sort();
    }, [lines]);

    const barData = useMemo(() => buildBarData(lines, yearMonths), [lines, yearMonths]);
    const lineData = useMemo(() => buildLineData(lines, yearMonths, filter), [lines, yearMonths, filter]);
    const pieData = useMemo(() => buildPieData(lines), [lines]);

    if (lines.length === 0) {
        return (
            <div className="bg-white shadow-sm rounded-lg p-6">
                <EmptyState
                    title="Sem dados para gráficos"
                    description="Ajuste os filtros ou o período para ver a matriz."
                />
            </div>
        );
    }

    return (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <ChartCard
                title="Receita × Despesa × EBITDA por mês"
                subtitle="Barras empilhadas — realizado"
            >
                {barData.length === 0 ? (
                    <MiniEmpty label="Sem receitas/despesas classificadas no período." />
                ) : (
                    <ResponsiveContainer width="100%" height={260}>
                        <BarChart data={barData}>
                            <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                            <XAxis dataKey="ym" tickFormatter={formatYmShort} />
                            <YAxis tickFormatter={formatShort} />
                            <Tooltip formatter={(v) => formatCurrency(v)} />
                            <Legend />
                            <Bar dataKey="receita" stackId="a" fill="#10b981" name="Receita" />
                            <Bar dataKey="despesa" stackId="b" fill="#ef4444" name="Despesa" />
                            <Bar dataKey="ebitda" fill="#6366f1" name="EBITDA" />
                        </BarChart>
                    </ResponsiveContainer>
                )}
            </ChartCard>

            <ChartCard
                title="Margem líquida ao longo do período"
                subtitle="Realizado × orçado × ano anterior"
            >
                {lineData.length === 0 ? (
                    <MiniEmpty label="Sem linha de margem encontrada (esperado subtotal com code MARGEM ou LUCRO_LIQUIDO)." />
                ) : (
                    <ResponsiveContainer width="100%" height={260}>
                        <LineChart data={lineData}>
                            <CartesianGrid strokeDasharray="3 3" stroke="#e5e7eb" />
                            <XAxis dataKey="ym" tickFormatter={formatYmShort} />
                            <YAxis tickFormatter={formatShort} />
                            <Tooltip formatter={(v) => formatCurrency(v)} />
                            <Legend />
                            <Line
                                dataKey="actual"
                                stroke="#4f46e5"
                                strokeWidth={2}
                                dot={false}
                                name="Realizado"
                            />
                            <Line
                                dataKey="budget"
                                stroke="#059669"
                                strokeDasharray="4 4"
                                strokeWidth={2}
                                dot={false}
                                name="Orçado"
                            />
                            {filter?.compare_previous_year && (
                                <Line
                                    dataKey="py"
                                    stroke="#94a3b8"
                                    strokeWidth={2}
                                    dot={false}
                                    name="Ano anterior"
                                />
                            )}
                        </LineChart>
                    </ResponsiveContainer>
                )}
            </ChartCard>

            <ChartCard
                title="Distribuição de despesas"
                subtitle="Participação no período filtrado"
                className="lg:col-span-2"
            >
                {pieData.length === 0 ? (
                    <MiniEmpty label="Sem despesas no período." />
                ) : (
                    <ResponsiveContainer width="100%" height={320}>
                        <PieChart>
                            <Pie
                                data={pieData}
                                dataKey="value"
                                nameKey="name"
                                cx="50%"
                                cy="50%"
                                outerRadius={110}
                                label={(p) => `${p.name} — ${p.pct}%`}
                            >
                                {pieData.map((entry, i) => (
                                    <Cell key={`c-${i}`} fill={PIE_COLORS[i % PIE_COLORS.length]} />
                                ))}
                            </Pie>
                            <Tooltip formatter={(v) => formatCurrency(v)} />
                            <Legend />
                        </PieChart>
                    </ResponsiveContainer>
                )}
            </ChartCard>
        </div>
    );
}

// ---------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------

const PIE_COLORS = ['#4f46e5', '#ef4444', '#f59e0b', '#10b981', '#3b82f6', '#8b5cf6', '#ec4899', '#64748b'];

function ChartCard({ title, subtitle, className = '', children }) {
    return (
        <div className={`bg-white shadow-sm rounded-lg p-4 ${className}`}>
            <div className="mb-3">
                <h3 className="text-sm font-semibold text-gray-900">{title}</h3>
                {subtitle && <p className="text-xs text-gray-500">{subtitle}</p>}
            </div>
            {children}
        </div>
    );
}

function MiniEmpty({ label }) {
    return (
        <div className="text-center py-10 text-sm text-gray-400">{label}</div>
    );
}

function formatYmShort(ym) {
    const s = splitYearMonth(ym);
    return s ? `${monthLabel(s.month)}/${String(s.year).slice(2)}` : ym;
}

function formatShort(v) {
    const n = Number(v || 0);
    if (Math.abs(n) >= 1_000_000) return `${(n / 1_000_000).toFixed(1)}M`;
    if (Math.abs(n) >= 1_000) return `${(n / 1_000).toFixed(0)}k`;
    return String(n);
}

function buildBarData(lines, yearMonths) {
    const sums = {};
    yearMonths.forEach((ym) => {
        sums[ym] = { ym, receita: 0, despesa: 0, ebitda: 0 };
    });

    lines.forEach((line) => {
        const nature = (line.nature || '').toLowerCase();
        const code = (line.code || '').toUpperCase();
        const isEbitda = line.is_subtotal && code.includes('EBITDA');

        Object.entries(line.months || {}).forEach(([ym, cell]) => {
            if (!sums[ym]) return;
            const value = Number(cell?.actual || 0);

            if (nature === 'revenue' && !line.is_subtotal) {
                sums[ym].receita += value;
            } else if (nature === 'expense' && !line.is_subtotal) {
                sums[ym].despesa += Math.abs(value);
            }

            if (isEbitda) {
                sums[ym].ebitda = value;
            }
        });
    });

    return yearMonths
        .map((ym) => sums[ym])
        .filter((d) => d.receita !== 0 || d.despesa !== 0 || d.ebitda !== 0);
}

function buildLineData(lines, yearMonths, filter) {
    const marginLine = lines.find((l) => {
        const code = (l.code || '').toUpperCase();
        return l.is_subtotal && (code.includes('MARGEM') || code.includes('LUCRO_LIQUIDO') || code.includes('EBITDA'));
    });

    if (!marginLine) return [];

    return yearMonths.map((ym) => {
        const cell = marginLine.months?.[ym] || {};
        return {
            ym,
            actual: Number(cell.actual || 0),
            budget: Number(cell.budget || 0),
            py: filter?.compare_previous_year ? Number(cell.previous_year || 0) : null,
        };
    });
}

function buildPieData(lines) {
    const expenses = lines
        .filter((l) => !l.is_subtotal && (l.nature || '').toLowerCase() === 'expense')
        .map((l) => ({
            name: l.level_1 || l.code || '?',
            value: Math.abs(Number(l.totals?.actual || 0)),
        }))
        .filter((d) => d.value > 0.01)
        .sort((a, b) => b.value - a.value)
        .slice(0, 8);

    const total = expenses.reduce((acc, d) => acc + d.value, 0);
    return expenses.map((d) => ({
        ...d,
        pct: total > 0 ? ((d.value / total) * 100).toFixed(1) : '0.0',
    }));
}
