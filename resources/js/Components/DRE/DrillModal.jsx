import { useEffect, useState } from 'react';
import axios from 'axios';
import { ArrowTopRightOnSquareIcon, MagnifyingGlassIcon } from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import LoadingSpinner from '@/Components/Shared/LoadingSpinner';
import EmptyState from '@/Components/Shared/EmptyState';
import { formatCurrency, monthLabel, splitYearMonth } from '@/lib/dre';

/**
 * Drill-through de uma célula da matriz DRE.
 *
 * Abre um modal com a lista de contas contábeis que contribuíram para o
 * valor da linha em determinado período. Chama o endpoint
 * `GET /dre/matrix/drill/{line}` carregando o filter atual da tela.
 *
 * Props:
 *   - show, onClose: controle padrão do modal.
 *   - line: objeto line da matriz ({id, code, level_1, nature}).
 *   - yearMonth: 'YYYY-MM' do mês clicado (null quando é drill do total).
 *   - filter: filtro atual da matriz (scope, store_ids, etc).
 */
export default function DrillModal({ show, onClose, line, yearMonth, filter }) {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [contributors, setContributors] = useState([]);

    useEffect(() => {
        if (!show || !line) return;

        const controller = new AbortController();
        setLoading(true);
        setError(null);
        setContributors([]);

        const params = buildParams(filter, yearMonth);
        axios
            .get(route('dre.matrix.drill', line.id), {
                params,
                signal: controller.signal,
            })
            .then((res) => {
                setContributors(res.data.contributors || []);
            })
            .catch((err) => {
                if (axios.isCancel(err)) return;
                setError(err.response?.data?.message || 'Não foi possível carregar o detalhamento.');
            })
            .finally(() => setLoading(false));

        return () => controller.abort();
    }, [show, line?.id, yearMonth, filter]);

    const periodLabel = yearMonth
        ? (() => {
              const s = splitYearMonth(yearMonth);
              return s ? `${monthLabel(s.month)}/${s.year}` : yearMonth;
          })()
        : `${filter?.start_date ?? ''} → ${filter?.end_date ?? ''}`;

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title={line?.level_1 ?? 'Detalhamento'}
            subtitle={`${line?.code ?? ''} — ${periodLabel}`}
            headerColor="bg-indigo-600"
            headerIcon={<MagnifyingGlassIcon className="h-5 w-5 text-white" />}
            maxWidth="4xl"
            footer={
                <StandardModal.Footer
                    onCancel={onClose}
                    cancelLabel="Fechar"
                />
            }
        >
            <StandardModal.Section title="Contas contribuintes">
                {loading && (
                    <div className="py-8 flex justify-center">
                        <LoadingSpinner />
                    </div>
                )}

                {!loading && error && (
                    <div className="rounded-md bg-red-50 border border-red-200 p-4 text-sm text-red-700">
                        {error}
                    </div>
                )}

                {!loading && !error && contributors.length === 0 && (
                    <EmptyState
                        title="Nenhuma conta contribuinte"
                        description="Esta linha não teve lançamentos no período selecionado."
                        compact
                    />
                )}

                {!loading && !error && contributors.length > 0 && (
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-3 py-2 text-left text-xs font-semibold text-gray-600">Conta</th>
                                    <th className="px-3 py-2 text-left text-xs font-semibold text-gray-600">Centro de Custo</th>
                                    <th className="px-3 py-2 text-right text-xs font-semibold text-gray-600">Realizado</th>
                                    <th className="px-3 py-2 text-right text-xs font-semibold text-gray-600">Orçado</th>
                                    {filter?.compare_previous_year && (
                                        <th className="px-3 py-2 text-right text-xs font-semibold text-gray-600">Ano Anterior</th>
                                    )}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {contributors.map((c, idx) => (
                                    <tr key={`${c.chart_of_account?.id ?? '?'}-${c.cost_center?.id ?? 'null'}-${idx}`}>
                                        <td className="px-3 py-2">
                                            <div className="flex items-center gap-2">
                                                <span className="font-mono text-xs text-gray-500">
                                                    {c.chart_of_account?.code ?? '—'}
                                                </span>
                                                <span className="text-gray-900">
                                                    {c.chart_of_account?.name ?? '—'}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="px-3 py-2 text-gray-600">
                                            {c.cost_center
                                                ? `${c.cost_center.code} — ${c.cost_center.name}`
                                                : <span className="text-gray-400">Qualquer CC</span>}
                                        </td>
                                        <td className={`px-3 py-2 text-right tabular-nums ${
                                            Number(c.actual) < 0 ? 'text-red-700' : 'text-gray-900'
                                        }`}>
                                            {formatCurrency(c.actual)}
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums text-gray-600">
                                            {formatCurrency(c.budget)}
                                        </td>
                                        {filter?.compare_previous_year && (
                                            <td className="px-3 py-2 text-right tabular-nums text-gray-600">
                                                {formatCurrency(c.previous_year)}
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </StandardModal.Section>

            <StandardModal.Section title="Origens">
                <p className="text-xs text-gray-600">
                    Lançamentos vêm de <strong>OrderPayment</strong> (despesas com status &quot;pago&quot;),
                    <strong> Sale</strong> (receita de venda) e import manual.
                    Clique em <ArrowTopRightOnSquareIcon className="h-3 w-3 inline align-baseline" /> ao
                    lado da conta (em breve) para ver os lançamentos individuais.
                </p>
            </StandardModal.Section>
        </StandardModal>
    );
}

function buildParams(filter, yearMonth) {
    if (!filter) return {};

    const params = { ...filter };

    // Se temos mês específico, restringe start/end ao mês. Senão mantém o período inteiro.
    if (yearMonth) {
        const s = splitYearMonth(yearMonth);
        if (s) {
            const monthStart = `${String(s.year).padStart(4, '0')}-${String(s.month).padStart(2, '0')}-01`;
            // Último dia do mês.
            const nextMonth = s.month === 12 ? { y: s.year + 1, m: 1 } : { y: s.year, m: s.month + 1 };
            const lastDay = new Date(nextMonth.y, nextMonth.m - 1, 0).getDate();
            const monthEnd = `${String(s.year).padStart(4, '0')}-${String(s.month).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`;
            params.start_date = monthStart;
            params.end_date = monthEnd;
        }
    }

    return params;
}
