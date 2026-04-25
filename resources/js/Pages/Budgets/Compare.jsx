import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import {
    ArrowLeftIcon,
    ArrowTrendingUpIcon,
    ArrowTrendingDownIcon,
    PlusCircleIcon,
    MinusCircleIcon,
    PencilSquareIcon,
} from '@heroicons/react/24/outline';
import EmptyState from '@/Components/Shared/EmptyState';
import PageHeader from '@/Components/Shared/PageHeader';

const BRL = (v) =>
    new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(v || 0));

const MONTH_LABELS = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

const deltaClass = (delta) => {
    if (Math.abs(delta) < 0.005) return 'text-gray-500';
    return delta > 0 ? 'text-red-600' : 'text-green-600';
};

const deltaSign = (delta) => {
    if (Math.abs(delta) < 0.005) return '±';
    return delta > 0 ? '+' : '';
};

export default function Compare({ diff, error }) {
    const [tab, setTab] = useState('changed');

    if (error || !diff) {
        return (
            <>
                <Head title="Comparar versões" />
                <div className="py-12 max-w-4xl mx-auto px-4">
                    <Link href={route('budgets.index')}
                        className="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1 mb-4">
                        <ArrowLeftIcon className="w-4 h-4" /> Voltar para orçamentos
                    </Link>
                    <EmptyState
                        title="Não foi possível comparar"
                        description={error || 'Selecione duas versões para comparar.'}
                    />
                </div>
            </>
        );
    }

    const { v1, v2, added, removed, changed, unchanged_count, totals, by_month } = diff;

    const currentRows = useMemo(() => {
        if (tab === 'added') return added;
        if (tab === 'removed') return removed;
        if (tab === 'changed') return changed;
        return [];
    }, [tab, added, removed, changed]);

    return (
        <>
            <Head title={`Comparar ${v1.version_label} × ${v2.version_label}`} />

            <div className="py-8">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <PageHeader
                        title={`Comparativo: ${v1.scope_label} ${v1.year}`}
                        subtitle={(
                            <>
                                <strong>v{v1.version_label}</strong> ({v1.created_at}) × <strong>v{v2.version_label}</strong> ({v2.created_at})
                            </>
                        )}
                        actions={[
                            { type: 'back', href: route('budgets.index') },
                        ]}
                    />

                    {/* Resumo de totais */}
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <div className="bg-white shadow rounded-lg p-5">
                            <p className="text-xs uppercase font-semibold text-gray-500">Total v{v1.version_label}</p>
                            <p className="text-2xl font-bold text-gray-900 mt-1 font-mono">{BRL(totals.v1)}</p>
                            <p className="text-xs text-gray-500 mt-1">{totals.items_v1} itens</p>
                        </div>
                        <div className="bg-white shadow rounded-lg p-5">
                            <p className="text-xs uppercase font-semibold text-gray-500">Total v{v2.version_label}</p>
                            <p className="text-2xl font-bold text-gray-900 mt-1 font-mono">{BRL(totals.v2)}</p>
                            <p className="text-xs text-gray-500 mt-1">{totals.items_v2} itens</p>
                        </div>
                        <div className={`shadow rounded-lg p-5 ${totals.delta > 0 ? 'bg-red-50' : totals.delta < 0 ? 'bg-green-50' : 'bg-gray-50'}`}>
                            <p className="text-xs uppercase font-semibold text-gray-500">Delta</p>
                            <p className={`text-2xl font-bold mt-1 font-mono flex items-center gap-1 ${deltaClass(totals.delta)}`}>
                                {totals.delta > 0 && <ArrowTrendingUpIcon className="h-5 w-5" />}
                                {totals.delta < 0 && <ArrowTrendingDownIcon className="h-5 w-5" />}
                                {deltaSign(totals.delta)}{BRL(totals.delta)}
                            </p>
                            <p className={`text-xs mt-1 ${deltaClass(totals.delta)}`}>
                                {deltaSign(totals.delta_pct)}{totals.delta_pct.toFixed(2)}%
                            </p>
                        </div>
                        <div className="bg-white shadow rounded-lg p-5">
                            <p className="text-xs uppercase font-semibold text-gray-500">Mudanças</p>
                            <div className="flex gap-2 mt-2 text-xs">
                                <span className="inline-flex items-center gap-1 px-2 py-0.5 bg-green-100 text-green-800 rounded">
                                    <PlusCircleIcon className="h-3 w-3" /> {totals.added_count}
                                </span>
                                <span className="inline-flex items-center gap-1 px-2 py-0.5 bg-red-100 text-red-800 rounded">
                                    <MinusCircleIcon className="h-3 w-3" /> {totals.removed_count}
                                </span>
                                <span className="inline-flex items-center gap-1 px-2 py-0.5 bg-amber-100 text-amber-800 rounded">
                                    <PencilSquareIcon className="h-3 w-3" /> {totals.changed_count}
                                </span>
                            </div>
                            <p className="text-xs text-gray-500 mt-2">{unchanged_count} inalteradas</p>
                        </div>
                    </div>

                    {/* Delta por mês */}
                    <div className="mt-6 bg-white shadow rounded-lg p-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-4">Delta por mês</h2>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead>
                                    <tr className="border-b border-gray-200 text-xs uppercase text-gray-500">
                                        <th className="px-2 py-2 text-left">Mês</th>
                                        <th className="px-2 py-2 text-right">v{v1.version_label}</th>
                                        <th className="px-2 py-2 text-right">v{v2.version_label}</th>
                                        <th className="px-2 py-2 text-right">Delta</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200">
                                    {by_month.map((m) => (
                                        <tr key={m.month}>
                                            <td className="px-2 py-1.5 text-gray-700">{MONTH_LABELS[m.month - 1]}</td>
                                            <td className="px-2 py-1.5 text-right font-mono text-gray-900">{BRL(m.v1)}</td>
                                            <td className="px-2 py-1.5 text-right font-mono text-gray-900">{BRL(m.v2)}</td>
                                            <td className={`px-2 py-1.5 text-right font-mono ${deltaClass(m.delta)}`}>
                                                {deltaSign(m.delta)}{BRL(m.delta)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* Tabs: added / removed / changed */}
                    <div className="mt-6 bg-white shadow rounded-lg">
                        <div className="border-b border-gray-200">
                            <nav className="flex -mb-px" aria-label="Tabs">
                                <TabButton active={tab === 'changed'} onClick={() => setTab('changed')} label="Alteradas" count={totals.changed_count} color="amber" />
                                <TabButton active={tab === 'added'} onClick={() => setTab('added')} label="Adicionadas" count={totals.added_count} color="green" />
                                <TabButton active={tab === 'removed'} onClick={() => setTab('removed')} label="Removidas" count={totals.removed_count} color="red" />
                            </nav>
                        </div>
                        <div className="p-4">
                            {currentRows.length === 0 ? (
                                <EmptyState
                                    title="Sem itens nesta categoria"
                                    description={
                                        tab === 'changed' ? 'Nenhuma linha foi alterada entre as versões.'
                                            : tab === 'added' ? 'Nenhuma linha foi adicionada em v2.'
                                            : 'Nenhuma linha foi removida em v2.'
                                    }
                                    compact
                                />
                            ) : tab === 'changed' ? (
                                <ChangedTable rows={currentRows} v1={v1} v2={v2} />
                            ) : (
                                <SimpleTable rows={currentRows} mode={tab} />
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

function TabButton({ active, onClick, label, count, color }) {
    const colorMap = {
        amber: 'border-amber-600 text-amber-700',
        green: 'border-green-600 text-green-700',
        red: 'border-red-600 text-red-700',
    };
    return (
        <button onClick={onClick}
            className={`px-6 py-3 text-sm font-medium border-b-2 transition-colors ${
                active ? colorMap[color] : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}>
            {label} <span className="ml-1 text-xs text-gray-400">({count})</span>
        </button>
    );
}

function SimpleTable({ rows, mode }) {
    return (
        <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
                <thead>
                    <tr className="border-b border-gray-200 text-xs uppercase text-gray-500">
                        <th className="px-2 py-2 text-left">Conta Contábil</th>
                        <th className="px-2 py-2 text-left">Gerencial</th>
                        <th className="px-2 py-2 text-left">CC</th>
                        <th className="px-2 py-2 text-left">Loja</th>
                        <th className="px-2 py-2 text-left">Fornecedor</th>
                        <th className="px-2 py-2 text-right">Total Anual</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                    {rows.map((r) => (
                        <tr key={r.id} className={mode === 'added' ? 'bg-green-50/30' : 'bg-red-50/30'}>
                            <td className="px-2 py-1.5 text-gray-700">{r.accounting_class?.name || '—'}</td>
                            <td className="px-2 py-1.5 text-gray-700">{r.management_class?.name || '—'}</td>
                            <td className="px-2 py-1.5 text-gray-700">{r.cost_center?.name || '—'}</td>
                            <td className="px-2 py-1.5 text-gray-600">{r.store?.name || '—'}</td>
                            <td className="px-2 py-1.5 text-gray-600 max-w-xs truncate" title={r.supplier}>{r.supplier || '—'}</td>
                            <td className={`px-2 py-1.5 text-right font-mono font-medium ${mode === 'added' ? 'text-green-700' : 'text-red-700'}`}>
                                {mode === 'added' ? '+' : '−'}{BRL(r.year_total)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function ChangedTable({ rows, v1, v2 }) {
    const [expandedIdx, setExpandedIdx] = useState(null);

    return (
        <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
                <thead>
                    <tr className="border-b border-gray-200 text-xs uppercase text-gray-500">
                        <th className="px-2 py-2 w-8"></th>
                        <th className="px-2 py-2 text-left">Conta Contábil</th>
                        <th className="px-2 py-2 text-left">CC</th>
                        <th className="px-2 py-2 text-left">Loja</th>
                        <th className="px-2 py-2 text-right">v{v1.version_label}</th>
                        <th className="px-2 py-2 text-right">v{v2.version_label}</th>
                        <th className="px-2 py-2 text-right">Delta</th>
                        <th className="px-2 py-2 text-right">%</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                    {rows.map((r, idx) => {
                        const expanded = expandedIdx === idx;
                        return (
                            <>
                                <tr key={`row-${idx}`} className={expanded ? 'bg-amber-50' : 'hover:bg-gray-50'}>
                                    <td className="px-2 py-1.5 text-center">
                                        <button
                                            onClick={() => setExpandedIdx(expanded ? null : idx)}
                                            className="text-amber-600 hover:text-amber-800"
                                            title={expanded ? 'Recolher' : 'Ver mês a mês'}
                                        >
                                            {expanded ? '▼' : '▶'}
                                        </button>
                                    </td>
                                    <td className="px-2 py-1.5 text-gray-700">{r.accounting_class?.name || '—'}</td>
                                    <td className="px-2 py-1.5 text-gray-700">{r.cost_center?.name || '—'}</td>
                                    <td className="px-2 py-1.5 text-gray-600">{r.store?.name || '—'}</td>
                                    <td className="px-2 py-1.5 text-right font-mono text-gray-900">{BRL(r.year_total_v1)}</td>
                                    <td className="px-2 py-1.5 text-right font-mono text-gray-900">{BRL(r.year_total_v2)}</td>
                                    <td className={`px-2 py-1.5 text-right font-mono ${deltaClass(r.year_total_delta)}`}>
                                        {deltaSign(r.year_total_delta)}{BRL(r.year_total_delta)}
                                    </td>
                                    <td className={`px-2 py-1.5 text-right font-mono ${deltaClass(r.year_total_delta)}`}>
                                        {deltaSign(r.year_total_delta_pct)}{r.year_total_delta_pct.toFixed(2)}%
                                    </td>
                                </tr>
                                {expanded && (
                                    <tr key={`expanded-${idx}`} className="bg-amber-50/30">
                                        <td colSpan={8} className="px-6 py-3">
                                            {r.supplier_changed && (
                                                <div className="mb-3 text-xs">
                                                    <strong>Fornecedor:</strong> {r.supplier_v1 || '—'} → {r.supplier_v2 || '—'}
                                                </div>
                                            )}
                                            <div className="grid grid-cols-6 gap-2 text-xs">
                                                {r.months.map((m) => (
                                                    <div key={m.month} className={`rounded p-2 ${Math.abs(m.delta) > 0.005 ? 'bg-white border border-amber-200' : 'bg-gray-50'}`}>
                                                        <p className="font-medium text-gray-700">{MONTH_LABELS[m.month - 1]}</p>
                                                        <p className="font-mono text-gray-900">{BRL(m.v2)}</p>
                                                        {Math.abs(m.delta) > 0.005 && (
                                                            <p className={`font-mono text-[10px] ${deltaClass(m.delta)}`}>
                                                                {deltaSign(m.delta)}{BRL(m.delta)}
                                                            </p>
                                                        )}
                                                    </div>
                                                ))}
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
