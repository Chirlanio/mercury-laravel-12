import { useMemo } from 'react';
import { ArrowUpIcon, ArrowDownIcon } from '@heroicons/react/24/solid';
import {
    formatCurrency,
    formatPercentage,
    monthLabel,
    yearMonthsBetween,
    splitYearMonth,
    classifyVariance,
} from '@/lib/dre';

/**
 * Tabela da matriz DRE executiva.
 *
 * Props:
 *   - lines: array do payload do `DreMatrixService::matrix()` (campo `lines`).
 *     Cada item: { id, code, sort_order, is_subtotal, nature, level_1,
 *                  months: { 'YYYY-MM': {actual, budget, previous_year} },
 *                  totals: {actual, budget, previous_year} }
 *   - filters: filtro atual (start_date, end_date, compare_previous_year).
 *   - onCellClick: (line, yearMonth) => void — abre drill modal.
 *   - closedYearMonths: array 'YYYY-MM' dos meses que estão fechados — exibe
 *     cabeçalho com cadeado.
 *
 * Não virtualiza (só ~20 linhas). Sticky column + sticky header.
 */
export default function MatrixTable({
    lines = [],
    filters,
    onCellClick,
    closedYearMonths = [],
}) {
    const months = useMemo(() => {
        if (!filters?.start_date || !filters?.end_date) return [];
        return yearMonthsBetween(filters.start_date, filters.end_date);
    }, [filters?.start_date, filters?.end_date]);

    const closedSet = useMemo(() => new Set(closedYearMonths || []), [closedYearMonths]);
    const showPreviousYear = !!filters?.compare_previous_year;

    return (
        <div className="bg-white shadow-sm rounded-lg overflow-x-auto">
            <table className="min-w-full text-sm">
                <thead className="bg-gray-50 sticky top-0 z-10">
                    <tr>
                        <th
                            className="sticky left-0 z-20 bg-gray-50 px-3 py-2 text-left text-xs font-semibold text-gray-600 border-r border-gray-200 min-w-[280px]"
                        >
                            Linha
                        </th>
                        {months.map((ym) => (
                            <MonthHeader
                                key={ym}
                                ym={ym}
                                isClosed={closedSet.has(ym)}
                            />
                        ))}
                        <th className="px-3 py-2 text-right text-xs font-semibold text-gray-600 bg-gray-100 border-l">
                            Total
                        </th>
                        <th className="px-3 py-2 text-right text-xs font-semibold text-gray-600 bg-gray-100">
                            Orçado
                        </th>
                        <th className="px-3 py-2 text-right text-xs font-semibold text-gray-600 bg-gray-100">
                            % Ating.
                        </th>
                        {showPreviousYear && (
                            <>
                                <th className="px-3 py-2 text-right text-xs font-semibold text-gray-600 bg-gray-100 border-l">
                                    Ano Anterior
                                </th>
                                <th className="px-3 py-2 text-right text-xs font-semibold text-gray-600 bg-gray-100">
                                    Var. A.A.
                                </th>
                            </>
                        )}
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                    {lines.map((line) => (
                        <MatrixRow
                            key={line.id}
                            line={line}
                            months={months}
                            onCellClick={onCellClick}
                            showPreviousYear={showPreviousYear}
                        />
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function MonthHeader({ ym, isClosed }) {
    const split = splitYearMonth(ym);
    const label = split ? `${monthLabel(split.month)}/${String(split.year).slice(2)}` : ym;

    return (
        <th
            className={`px-3 py-2 text-right text-xs font-semibold text-gray-600 whitespace-nowrap ${
                isClosed ? 'bg-amber-50' : ''
            }`}
            title={isClosed ? 'Período fechado — valores imutáveis' : undefined}
        >
            <span>{label}</span>
            {isClosed && <span className="ml-1 text-amber-600" aria-hidden="true">🔒</span>}
        </th>
    );
}

function MatrixRow({ line, months, onCellClick, showPreviousYear }) {
    const rowClass = line.is_subtotal
        ? 'bg-gray-50 font-semibold border-t border-gray-300'
        : '';

    const isUnclassified = line.code === 'L99_UNCLASSIFIED';
    const hasValue = Math.abs(Number(line.totals?.actual ?? 0)) > 0.001;
    const highlightRed = isUnclassified && hasValue;

    return (
        <tr className={`${rowClass} ${highlightRed ? 'text-red-700' : ''}`}>
            <th
                className={`sticky left-0 z-10 px-3 py-2 text-left font-normal border-r border-gray-200 text-gray-900 ${
                    line.is_subtotal ? 'bg-gray-50 font-semibold' : 'bg-white'
                } ${highlightRed ? 'bg-red-50 text-red-700' : ''}`}
            >
                <div className="flex items-center gap-2">
                    <span className="font-mono text-xs text-gray-500">{line.code}</span>
                    <span>{line.level_1}</span>
                </div>
            </th>

            {months.map((ym) => {
                const cell = line.months?.[ym];
                const actual = Number(cell?.actual ?? 0);
                const hasEntry = Math.abs(actual) > 0.001;

                const clickable = !line.is_subtotal && hasEntry && typeof onCellClick === 'function';
                const Cell = clickable ? 'button' : 'td';

                // Para <button>, Tailwind precisa de display inline-block no td-like.
                // Tratamos com wrapper <td> quando for button.
                if (clickable) {
                    return (
                        <td
                            key={ym}
                            className="px-3 py-2 text-right whitespace-nowrap tabular-nums"
                        >
                            <button
                                type="button"
                                onClick={() => onCellClick(line, ym)}
                                className={`w-full text-right hover:underline focus:outline-none focus:ring-2 focus:ring-indigo-300 rounded ${
                                    actual < 0 ? 'text-red-700' : 'text-gray-900'
                                }`}
                                aria-label={`Detalhar ${line.level_1} em ${ym}`}
                            >
                                {formatCurrency(actual)}
                            </button>
                        </td>
                    );
                }

                return (
                    <td
                        key={ym}
                        className={`px-3 py-2 text-right whitespace-nowrap tabular-nums ${
                            actual < 0 ? 'text-red-700' : 'text-gray-900'
                        }`}
                    >
                        {hasEntry ? formatCurrency(actual) : '—'}
                    </td>
                );
            })}

            {/* Total / Orçado / %Ating. */}
            <td
                className={`px-3 py-2 text-right whitespace-nowrap tabular-nums bg-gray-50 border-l ${
                    Number(line.totals?.actual ?? 0) < 0 ? 'text-red-700' : ''
                }`}
            >
                {formatCurrency(line.totals?.actual)}
            </td>
            <td className="px-3 py-2 text-right whitespace-nowrap tabular-nums bg-gray-50 text-gray-600">
                {formatCurrency(line.totals?.budget)}
            </td>
            <td className="px-3 py-2 text-right whitespace-nowrap tabular-nums bg-gray-50">
                <AttainmentBadge
                    actual={Number(line.totals?.actual ?? 0)}
                    budget={Number(line.totals?.budget ?? 0)}
                    nature={line.nature}
                />
            </td>

            {showPreviousYear && (
                <>
                    <td className="px-3 py-2 text-right whitespace-nowrap tabular-nums bg-gray-50 border-l text-gray-600">
                        {formatCurrency(line.totals?.previous_year)}
                    </td>
                    <td className="px-3 py-2 text-right whitespace-nowrap tabular-nums bg-gray-50">
                        <PreviousYearVariation
                            actual={Number(line.totals?.actual ?? 0)}
                            previous={Number(line.totals?.previous_year ?? 0)}
                            nature={line.nature}
                        />
                    </td>
                </>
            )}
        </tr>
    );
}

function AttainmentBadge({ actual, budget, nature }) {
    if (Math.abs(budget) < 0.001) {
        return <span className="text-gray-400">—</span>;
    }

    const pct = (actual / budget) * 100;
    const variance = pct - 100;
    const klass = classifyVariance(variance, nature);
    const color = klass === 'favorable' ? 'text-green-700' : klass === 'unfavorable' ? 'text-red-700' : 'text-gray-600';
    const Icon = klass === 'favorable' ? ArrowUpIcon : klass === 'unfavorable' ? ArrowDownIcon : null;

    return (
        <span className={`inline-flex items-center gap-1 ${color}`}>
            {formatPercentage(pct)}
            {Icon && <Icon className="w-3 h-3" />}
        </span>
    );
}

function PreviousYearVariation({ actual, previous, nature }) {
    if (Math.abs(previous) < 0.001) {
        return <span className="text-gray-400">—</span>;
    }

    const variance = ((actual - previous) / Math.abs(previous)) * 100;
    const klass = classifyVariance(variance, nature);
    const color = klass === 'favorable' ? 'text-green-700' : klass === 'unfavorable' ? 'text-red-700' : 'text-gray-600';
    const Icon = klass === 'favorable' ? ArrowUpIcon : klass === 'unfavorable' ? ArrowDownIcon : null;

    return (
        <span className={`inline-flex items-center gap-1 ${color}`}>
            {formatPercentage(variance)}
            {Icon && <Icon className="w-3 h-3" />}
        </span>
    );
}
