import { useState, useEffect } from 'react';
import axios from 'axios';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';
import { formatDateTime } from '@/Utils/dateHelpers';

const TIER_VARIANT = {
    hiper: 'warning', super: 'info', goal: 'emerald', below: 'danger',
};
const TIER_LABEL = {
    hiper: 'Hiper Meta', super: 'Super Meta', goal: 'Meta', below: 'Abaixo',
};

export default function ViewModal({ show, onClose, goalId }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (show && goalId) {
            setLoading(true);
            axios.get(`/store-goals/${goalId}`)
                .then(res => setData(res.data))
                .catch(() => setData(null))
                .finally(() => setLoading(false));
        } else { setData(null); }
    }, [show, goalId]);

    const fmt = (v) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0);
    const fmtPct = (v) => `${v || 0}%`;

    const totals = data?.consultants?.reduce((acc, c) => ({
        individual_goal: acc.individual_goal + (c.individual_goal || 0),
        super_goal: acc.super_goal + (c.super_goal || 0),
        actual_sales: acc.actual_sales + (c.actual_sales || 0),
        award_amount: acc.award_amount + (c.award_amount || 0),
    }), { individual_goal: 0, super_goal: 0, actual_sales: 0, award_amount: 0 });

    const headerBadges = data ? [
        { text: data.period_label, className: 'bg-white/20 text-white' },
        {
            text: `${data.achievement_pct}%`,
            className: data.achievement_pct >= 100 ? 'bg-emerald-500/30 text-white' : 'bg-amber-500/30 text-white',
        },
    ] : [];

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title={data?.store_name || 'Detalhes da Meta'}
            subtitle={data?.manager_name ? `Gerente: ${data.manager_name}` : undefined}
            headerColor="bg-indigo-700"
            headerBadges={headerBadges}
            loading={loading}
            errorMessage={!loading && !data && show ? 'Erro ao carregar dados.' : null}
            footer={data && <StandardModal.Footer onCancel={onClose} cancelLabel="Fechar" />}
        >
            {data && (
                <>
                    {/* Atingimento + Indicadores */}
                    <div className="grid grid-cols-3 md:grid-cols-6 gap-3">
                        <StandardModal.InfoCard label="Atingimento" value={`${data.achievement_pct}%`} highlight />
                        <StandardModal.InfoCard label="Consultoras" value={data.consultants?.length || 0} />
                        <StandardModal.InfoCard label="Dias Úteis" value={data.business_days} />
                        <StandardModal.InfoCard label="Feriados" value={data.non_working_days} />
                        <StandardModal.InfoCard label="Meta" value={fmt(data.goal_amount)} colorClass="bg-emerald-50" />
                        <StandardModal.InfoCard label="Super Meta" value={fmt(data.super_goal)} colorClass="bg-blue-50" />
                    </div>

                    {/* Faixas de Meta */}
                    <div className="grid grid-cols-3 gap-3">
                        <div className="bg-emerald-50 border border-emerald-200 rounded-lg p-3 text-center">
                            <p className="text-xs text-emerald-600 font-medium">Meta</p>
                            <p className="text-sm font-bold text-emerald-700">{fmt(data.goal_amount)}</p>
                        </div>
                        <div className="bg-blue-50 border border-blue-200 rounded-lg p-3 text-center">
                            <p className="text-xs text-blue-600 font-medium">Super Meta</p>
                            <p className="text-sm font-bold text-blue-700">{fmt(data.super_goal)}</p>
                        </div>
                        <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-center">
                            <p className="text-xs text-yellow-600 font-medium">Hiper Meta</p>
                            <p className="text-sm font-bold text-yellow-700">{fmt(data.hiper_goal)}</p>
                        </div>
                    </div>

                    {/* Tabela de Consultores */}
                    {data.consultants?.length > 0 ? (
                        <StandardModal.Section title="Distribuição por Consultora">
                            <div className="overflow-x-auto -mx-4 -mb-4">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Consultora</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nível</th>
                                            <th className="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Dias</th>
                                            <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Meta</th>
                                            <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Super</th>
                                            <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Vendas</th>
                                            <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">%</th>
                                            <th className="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Faixa</th>
                                            <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Premiação</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {data.consultants.map((c) => (
                                            <tr key={c.id} className="hover:bg-gray-50">
                                                <td className="px-3 py-2 text-sm text-gray-900 max-w-[200px] truncate" title={c.employee_name}>{c.employee_name}</td>
                                                <td className="px-3 py-2 text-sm text-gray-500">{c.level_snapshot}</td>
                                                <td className="px-3 py-2 text-sm text-gray-500 text-center">{c.working_days - (c.deducted_days || 0)}</td>
                                                <td className="px-3 py-2 text-sm text-gray-900 text-right">{fmt(c.individual_goal)}</td>
                                                <td className="px-3 py-2 text-sm text-gray-500 text-right">{fmt(c.super_goal)}</td>
                                                <td className="px-3 py-2 text-sm font-medium text-gray-900 text-right">{c.actual_sales > 0 ? fmt(c.actual_sales) : '-'}</td>
                                                <td className="px-3 py-2 text-sm font-medium text-right">
                                                    <span className={c.achievement_pct >= 100 ? 'text-emerald-600' : c.achievement_pct > 0 ? 'text-amber-600' : 'text-gray-400'}>
                                                        {c.achievement_pct > 0 ? fmtPct(c.achievement_pct) : '-'}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-2 text-center">
                                                    <StatusBadge variant={TIER_VARIANT[c.tier] || 'danger'} size="sm">
                                                        {TIER_LABEL[c.tier] || 'Abaixo'}
                                                    </StatusBadge>
                                                </td>
                                                <td className="px-3 py-2 text-sm font-medium text-right text-emerald-700">{fmt(c.award_amount)}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                    <tfoot className="bg-gray-100 font-semibold">
                                        <tr>
                                            <td className="px-3 py-2 text-sm text-gray-900" colSpan={3}>Total</td>
                                            <td className="px-3 py-2 text-sm text-gray-900 text-right">{fmt(totals?.individual_goal)}</td>
                                            <td className="px-3 py-2 text-sm text-gray-900 text-right">{fmt(totals?.super_goal)}</td>
                                            <td className="px-3 py-2 text-sm text-gray-900 text-right">{fmt(totals?.actual_sales)}</td>
                                            <td className="px-3 py-2 text-sm text-right">
                                                <span className={data.achievement_pct >= 100 ? 'text-emerald-600' : 'text-amber-600'}>{fmtPct(data.achievement_pct)}</span>
                                            </td>
                                            <td className="px-3 py-2" />
                                            <td className="px-3 py-2 text-sm text-emerald-700 text-right">{fmt(totals?.award_amount)}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </StandardModal.Section>
                    ) : (
                        <div className="text-center py-8 text-gray-500 bg-gray-50 rounded-lg">
                            <p className="text-sm">Nenhuma meta individual distribuída.</p>
                            <p className="text-xs mt-1">Execute a redistribuição para gerar as metas dos consultores.</p>
                        </div>
                    )}

                    {/* Percentuais de Premiação */}
                    {data.awards_config?.length > 0 && (
                        <StandardModal.Section title="Percentuais de Premiação">
                            <div className="overflow-x-auto -mx-4 -mb-4">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nível</th>
                                            <th className="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Não Meta</th>
                                            <th className="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Meta</th>
                                            <th className="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Super Meta</th>
                                            <th className="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Hiper Meta</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {data.awards_config.map((a) => (
                                            <tr key={a.level}>
                                                <td className="px-3 py-2 text-sm font-medium text-gray-900">{a.level}</td>
                                                <td className="px-3 py-2 text-sm text-center text-red-600">{a.no_goal_pct}%</td>
                                                <td className="px-3 py-2 text-sm text-center text-emerald-600">{a.goal_pct}%</td>
                                                <td className="px-3 py-2 text-sm text-center text-blue-600">{a.super_goal_pct}%</td>
                                                <td className="px-3 py-2 text-sm text-center text-yellow-600">{a.hiper_goal_pct}%</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </StandardModal.Section>
                    )}

                    {/* Resumo */}
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <StandardModal.InfoCard label="Total Vendas" value={fmt(data.total_sales)} colorClass="bg-emerald-50" />
                        <StandardModal.InfoCard label="Total Premiações" value={fmt(data.total_awards)} colorClass="bg-blue-50" />
                        {data.achievement_pct < 100
                            ? <StandardModal.InfoCard label="Falta p/ Meta" value={fmt(data.missing_for_goal)} colorClass="bg-red-50" />
                            : <StandardModal.InfoCard label="Acima da Meta" value={fmt(data.total_sales - data.goal_amount)} colorClass="bg-emerald-50" />
                        }
                        <StandardModal.InfoCard label="Atingimento" value={fmtPct(data.achievement_pct)} highlight />
                    </div>

                    {/* Timestamps */}
                    <div className="flex justify-between text-xs text-gray-400 pt-2">
                        <span>Criado por {data.created_by || 'N/A'} em {formatDateTime(data.created_at)}</span>
                        {data.updated_by && <span>Atualizado por {data.updated_by} em {formatDateTime(data.updated_at)}</span>}
                    </div>
                </>
            )}
        </StandardModal>
    );
}
