import { useState, useEffect } from 'react';
import Modal from '@/Components/Modal';
import Button from '@/Components/Button';
import axios from 'axios';
import { formatDateTime } from '@/Utils/dateHelpers';

const TIER_CONFIG = {
    hiper: { label: 'Hiper Meta', color: 'bg-yellow-100 text-yellow-800', ring: 'ring-yellow-400' },
    super: { label: 'Super Meta', color: 'bg-blue-100 text-blue-800', ring: 'ring-blue-400' },
    goal: { label: 'Meta', color: 'bg-emerald-100 text-emerald-800', ring: 'ring-emerald-400' },
    below: { label: 'Abaixo', color: 'bg-red-100 text-red-800', ring: 'ring-red-400' },
};

export default function ViewModal({ isOpen, onClose, goalId }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (isOpen && goalId) {
            setLoading(true);
            axios.get(`/store-goals/${goalId}`)
                .then(res => setData(res.data))
                .catch(() => setData(null))
                .finally(() => setLoading(false));
        } else {
            setData(null);
        }
    }, [isOpen, goalId]);

    const fmt = (value) =>
        new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value || 0);

    const fmtPct = (value) => `${value || 0}%`;

    // Totals from consultants
    const totals = data?.consultants?.reduce((acc, c) => ({
        individual_goal: acc.individual_goal + (c.individual_goal || 0),
        super_goal: acc.super_goal + (c.super_goal || 0),
        actual_sales: acc.actual_sales + (c.actual_sales || 0),
        award_amount: acc.award_amount + (c.award_amount || 0),
    }), { individual_goal: 0, super_goal: 0, actual_sales: 0, award_amount: 0 });

    return (
        <Modal show={isOpen} onClose={onClose} maxWidth="5xl">
            <div className="p-6">
                {loading ? (
                    <div className="flex items-center justify-center py-12">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                    </div>
                ) : data ? (
                    <>
                        {/* Header */}
                        <div className="flex justify-between items-start mb-6">
                            <div>
                                <h2 className="text-lg font-semibold text-gray-900">{data.store_name}</h2>
                                {data.manager_name && (
                                    <p className="text-sm text-gray-500">Gerente: <span className="font-medium text-gray-700">{data.manager_name}</span></p>
                                )}
                                <p className="text-sm text-gray-500">{data.period_label}</p>
                            </div>
                            <div className="text-right">
                                <p className="text-xs text-gray-500 uppercase tracking-wide">Atingimento</p>
                                <p className={`text-3xl font-bold ${data.achievement_pct >= 115 ? 'text-yellow-600' : data.achievement_pct >= 100 ? 'text-emerald-600' : 'text-amber-600'}`}>
                                    {data.achievement_pct}%
                                </p>
                                <p className="text-xs text-gray-400 mt-0.5">
                                    {fmt(data.total_sales)} / {fmt(data.goal_amount)}
                                </p>
                            </div>
                        </div>

                        {/* Info Cards - Row 1 */}
                        <div className="grid grid-cols-3 md:grid-cols-6 gap-3 mb-4">
                            <InfoCard label="Consultoras" value={data.consultants?.length || 0} />
                            <InfoCard label="Dias no Mês" value={data.days_in_month} />
                            <InfoCard label="Dias Úteis" value={data.business_days} />
                            <InfoCard label="Feriados" value={data.non_working_days} />
                            <InfoCard label="Meta" value={fmt(data.goal_amount)} highlight="blue" />
                            <InfoCard label="Super Meta" value={fmt(data.super_goal)} highlight="indigo" />
                        </div>

                        {/* Metas Row */}
                        <div className="grid grid-cols-3 gap-3 mb-6">
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

                        {/* Consultants Table */}
                        {data.consultants && data.consultants.length > 0 ? (
                            <div>
                                <h3 className="text-sm font-medium text-gray-900 mb-3">
                                    Distribuição por Consultora
                                </h3>
                                <div className="overflow-x-auto border rounded-lg">
                                    <table className="min-w-full divide-y divide-gray-200">
                                        <thead className="bg-gray-50">
                                            <tr>
                                                <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Consultora</th>
                                                <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nível</th>
                                                <th className="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Dias Trab.</th>
                                                <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Meta</th>
                                                <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Super</th>
                                                <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Vendas</th>
                                                {data.has_confirmed_sales && (
                                                    <th className="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Confirmado</th>
                                                )}
                                                <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">%</th>
                                                <th className="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Faixa</th>
                                                <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Premiação</th>
                                            </tr>
                                        </thead>
                                        <tbody className="bg-white divide-y divide-gray-200">
                                            {data.consultants.map((c) => {
                                                const tier = TIER_CONFIG[c.tier] || TIER_CONFIG.below;
                                                return (
                                                    <tr key={c.id} className="hover:bg-gray-50">
                                                        <td className="px-3 py-2 text-sm text-gray-900 max-w-[200px] truncate" title={c.employee_name}>{c.employee_name}</td>
                                                        <td className="px-3 py-2 text-sm text-gray-500">{c.level_snapshot}</td>
                                                        <td className="px-3 py-2 text-sm text-gray-500 text-center">{c.working_days - (c.deducted_days || 0)}</td>
                                                        <td className="px-3 py-2 text-sm text-gray-900 text-right">{fmt(c.individual_goal)}</td>
                                                        <td className="px-3 py-2 text-sm text-gray-500 text-right">{fmt(c.super_goal)}</td>
                                                        <td className="px-3 py-2 text-sm font-medium text-gray-900 text-right">
                                                            {c.actual_sales > 0 ? fmt(c.actual_sales) : '-'}
                                                        </td>
                                                        {data.has_confirmed_sales && (
                                                            <td className="px-3 py-2 text-center">
                                                                {c.confirmed_sales !== null ? (
                                                                    <span className="inline-flex items-center text-xs text-emerald-600" title={`Confirmado: ${fmt(c.confirmed_sales)}`}>
                                                                        <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                                            <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                                                        </svg>
                                                                    </span>
                                                                ) : (
                                                                    <span className="text-xs text-gray-300">-</span>
                                                                )}
                                                            </td>
                                                        )}
                                                        <td className="px-3 py-2 text-sm font-medium text-right">
                                                            <span className={c.achievement_pct >= 100 ? 'text-emerald-600' : c.achievement_pct > 0 ? 'text-amber-600' : 'text-gray-400'}>
                                                                {c.achievement_pct > 0 ? fmtPct(c.achievement_pct) : '-'}
                                                            </span>
                                                        </td>
                                                        <td className="px-3 py-2 text-center">
                                                            <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${tier.color}`}>
                                                                {tier.label}
                                                            </span>
                                                        </td>
                                                        <td className="px-3 py-2 text-sm font-medium text-right text-emerald-700">
                                                            {fmt(c.award_amount)}
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                        {/* Totals Row */}
                                        <tfoot className="bg-gray-100 font-semibold">
                                            <tr>
                                                <td className="px-3 py-2 text-sm text-gray-900" colSpan={3}>Total</td>
                                                <td className="px-3 py-2 text-sm text-gray-900 text-right">{fmt(totals?.individual_goal)}</td>
                                                <td className="px-3 py-2 text-sm text-gray-900 text-right">{fmt(totals?.super_goal)}</td>
                                                <td className="px-3 py-2 text-sm text-gray-900 text-right">{fmt(totals?.actual_sales)}</td>
                                                {data.has_confirmed_sales && <td className="px-3 py-2"></td>}
                                                <td className="px-3 py-2 text-sm text-right">
                                                    <span className={data.achievement_pct >= 100 ? 'text-emerald-600' : 'text-amber-600'}>
                                                        {fmtPct(data.achievement_pct)}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-2"></td>
                                                <td className="px-3 py-2 text-sm text-emerald-700 text-right">{fmt(totals?.award_amount)}</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        ) : (
                            <div className="text-center py-8 text-gray-500 bg-gray-50 rounded-lg">
                                <p className="text-sm">Nenhuma meta individual distribuída.</p>
                                <p className="text-xs mt-1">Execute a redistribuição para gerar as metas dos consultores.</p>
                            </div>
                        )}

                        {/* Percentage Awards Config */}
                        {data.awards_config && data.awards_config.length > 0 && (
                            <div className="mt-6">
                                <h3 className="text-sm font-medium text-gray-900 mb-3">Percentuais de Premiação</h3>
                                <div className="overflow-x-auto border rounded-lg">
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
                            </div>
                        )}

                        {/* Summary */}
                        <div className="mt-6 grid grid-cols-2 md:grid-cols-4 gap-3">
                            <SummaryCard label="Total Vendas" value={fmt(data.total_sales)} color="emerald" />
                            <SummaryCard label="Total Premiações" value={fmt(data.total_awards)} color="blue" />
                            {data.achievement_pct < 100 ? (
                                <SummaryCard label="Falta p/ Meta" value={fmt(data.missing_for_goal)} color="red" />
                            ) : (
                                <SummaryCard label="Acima da Meta" value={fmt(data.total_sales - data.goal_amount)} color="emerald" />
                            )}
                            <SummaryCard label="Atingimento" value={fmtPct(data.achievement_pct)} color={data.achievement_pct >= 100 ? 'emerald' : 'amber'} />
                        </div>

                        {/* Footer info */}
                        <div className="mt-4 flex justify-between text-xs text-gray-400">
                            <span>Criado por {data.created_by || 'N/A'} em {formatDateTime(data.created_at)}</span>
                            {data.updated_by && <span>Atualizado por {data.updated_by} em {formatDateTime(data.updated_at)}</span>}
                        </div>
                    </>
                ) : (
                    <p className="text-center py-8 text-gray-500">Erro ao carregar dados.</p>
                )}

                <div className="mt-6 flex justify-end">
                    <Button variant="secondary" onClick={onClose}>Fechar</Button>
                </div>
            </div>
        </Modal>
    );
}

function InfoCard({ label, value, highlight }) {
    const bgClass = highlight === 'blue' ? 'bg-blue-50' : highlight === 'indigo' ? 'bg-indigo-50' : 'bg-gray-50';
    const textClass = highlight === 'blue' ? 'text-blue-700' : highlight === 'indigo' ? 'text-indigo-700' : 'text-gray-900';
    return (
        <div className={`${bgClass} rounded-lg p-3 text-center`}>
            <p className="text-xs text-gray-500">{label}</p>
            <p className={`text-sm font-semibold ${textClass}`}>{value}</p>
        </div>
    );
}

function SummaryCard({ label, value, color = 'gray' }) {
    const colors = {
        emerald: 'bg-emerald-50 border-emerald-200 text-emerald-700',
        blue: 'bg-blue-50 border-blue-200 text-blue-700',
        red: 'bg-red-50 border-red-200 text-red-700',
        amber: 'bg-amber-50 border-amber-200 text-amber-700',
        gray: 'bg-gray-50 border-gray-200 text-gray-700',
    };
    return (
        <div className={`border rounded-lg p-3 text-center ${colors[color] || colors.gray}`}>
            <p className="text-xs opacity-75">{label}</p>
            <p className="text-sm font-bold">{value}</p>
        </div>
    );
}
