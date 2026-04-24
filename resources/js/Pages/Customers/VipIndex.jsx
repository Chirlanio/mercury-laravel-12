import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import {
    SparklesIcon, TrophyIcon, UsersIcon, CurrencyDollarIcon,
    ArrowPathIcon, ArrowTopRightOnSquareIcon, ChartBarIcon,
    GiftIcon, CalendarDaysIcon, PencilSquareIcon, TrashIcon,
    ArrowLeftIcon, AdjustmentsHorizontalIcon, PlusIcon,
} from '@heroicons/react/24/outline';
import {
    ResponsiveContainer, LineChart, Line, CartesianGrid,
    XAxis, YAxis, Tooltip, Legend,
} from 'recharts';
import { useConfirm } from '@/Hooks/useConfirm';
import useModalManager from '@/Hooks/useModalManager';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import StandardModal from '@/Components/StandardModal';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import StatusBadge from '@/Components/Shared/StatusBadge';
import EmptyState from '@/Components/Shared/EmptyState';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';

const MONTHS_PT = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

const TIER_VARIANTS = {
    black: { color: 'dark', label: 'Black' },
    gold: { color: 'warning', label: 'Gold' },
};

const ACTIVITY_TYPE_LABEL = {
    gift: 'Brinde',
    event: 'Evento',
    contact: 'Contato',
    note: 'Nota',
    other: 'Outro',
};

const ACTIVITY_TYPE_ICON = {
    gift: GiftIcon,
    event: CalendarDaysIcon,
    contact: UsersIcon,
    note: PencilSquareIcon,
    other: SparklesIcon,
};

const fmtCurrency = (v) => new Intl.NumberFormat('pt-BR', {
    style: 'currency', currency: 'BRL',
}).format(Number(v) || 0);

const fmtDate = (iso) => iso ? new Date(iso).toLocaleDateString('pt-BR') : '—';

const fmtDateTime = (iso) => iso ? new Date(iso).toLocaleString('pt-BR', {
    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit',
}) : '—';

export default function VipIndex({ tiers, year, availableYears, filters, statistics, can }) {
    const { confirm, ConfirmDialogComponent } = useConfirm();
    const { modals, selected, openModal, closeModal } = useModalManager(['curate', 'report', 'activities']);

    const [searchInput, setSearchInput] = useState(filters.search || '');

    // Debounce search
    useEffect(() => {
        if (searchInput === (filters.search || '')) return;
        const h = setTimeout(() => {
            router.get(route('customers.vip.index'), {
                ...filters,
                search: searchInput || undefined,
            }, { preserveState: true, preserveScroll: true, replace: true });
        }, 400);
        return () => clearTimeout(h);
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchInput]);

    const applyFilter = (key, value) => {
        router.get(route('customers.vip.index'), {
            ...filters,
            [key]: value || undefined,
        }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const statsCards = useMemo(() => [
        {
            label: 'VIPs Black',
            value: statistics.total_black,
            format: 'number',
            icon: TrophyIcon,
            color: 'dark',
            onClick: () => applyFilter('final_tier', filters.final_tier === 'black' ? null : 'black'),
            active: filters.final_tier === 'black',
        },
        {
            label: 'VIPs Gold',
            value: statistics.total_gold,
            format: 'number',
            icon: SparklesIcon,
            color: 'warning',
            onClick: () => applyFilter('final_tier', filters.final_tier === 'gold' ? null : 'gold'),
            active: filters.final_tier === 'gold',
        },
        {
            label: 'Pendentes de curadoria',
            value: statistics.total_pending,
            format: 'number',
            icon: UsersIcon,
            color: 'info',
            onClick: () => applyFilter('final_tier', filters.final_tier === 'pending' ? null : 'pending'),
            active: filters.final_tier === 'pending',
        },
        {
            label: 'Faturamento VIP',
            value: statistics.total_revenue,
            format: 'currency',
            icon: CurrencyDollarIcon,
            color: 'success',
        },
    // eslint-disable-next-line react-hooks/exhaustive-deps
    ], [statistics, filters.final_tier]);

    const handleSuggestionsRun = async () => {
        const ok = await confirm({
            title: 'Gerar sugestões automáticas',
            message: `Isso vai recalcular o faturamento de ${year} sobre as movimentações e sugerir tiers para quem bate os thresholds. Curadorias já feitas NÃO serão sobrescritas. Continuar?`,
            confirmText: 'Gerar sugestões',
            type: 'info',
        });
        if (!ok) return;
        router.post(route('customers.vip.suggestions'), { year }, { preserveScroll: true });
    };

    const handleRemove = async (tier) => {
        const ok = await confirm({
            title: 'Remover cliente da lista VIP?',
            message: `${tier.customer.name} será removido da lista VIP de ${tier.year}. O histórico (faturamento calculado) permanece para consulta. Continuar?`,
            confirmText: 'Sim, remover',
            type: 'danger',
        });
        if (!ok) return;
        router.delete(route('customers.vip.destroy', tier.id), { preserveScroll: true });
    };

    return (
        <>
            <Head title="MS Life" />

            <div className="py-6 sm:py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    {/* Header — vertical até lg pra não estourar em iPad mini (768) */}
                    <div className="mb-6 flex flex-col gap-3 lg:flex-row lg:justify-between lg:items-center">
                        <div className="min-w-0">
                            <h1 className="text-2xl sm:text-3xl font-bold text-gray-900 flex items-center gap-2">
                                <TrophyIcon className="h-7 w-7 text-indigo-600 shrink-0" />
                                MS Life {year}
                            </h1>
                            <p className="mt-1 text-sm text-gray-600">
                                Programa <strong>MS Life</strong> — curadoria Black/Gold baseada no faturamento
                                de <strong>{year - 1}</strong> apenas nas lojas da rede <strong>Meia Sola</strong>.
                                A lista de cada ano usa o faturamento do ano anterior.
                            </p>
                        </div>
                        <div className="flex flex-wrap gap-2 lg:shrink-0">
                            <Link href={route('customers.index')} className="flex-1 sm:flex-none">
                                <Button variant="outline" icon={ArrowLeftIcon} className="w-full sm:w-auto whitespace-nowrap">
                                    Voltar
                                </Button>
                            </Link>
                            {can.manage_config && (
                                <Link href={route('customers.vip.config.index')} className="flex-1 sm:flex-none">
                                    <Button variant="secondary" icon={AdjustmentsHorizontalIcon} className="w-full sm:w-auto whitespace-nowrap">
                                        Limites
                                    </Button>
                                </Link>
                            )}
                            {can.manage && (
                                <Button
                                    variant="primary"
                                    onClick={handleSuggestionsRun}
                                    icon={ArrowPathIcon}
                                    className="flex-1 sm:flex-none w-full sm:w-auto whitespace-nowrap"
                                >
                                    Gerar sugestões {year}
                                </Button>
                            )}
                        </div>
                    </div>

                    <StatisticsGrid cards={statsCards} cols={4} />

                    {/* Filtros */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                            <div>
                                <InputLabel value="Ano" />
                                <select
                                    value={year}
                                    onChange={(e) => applyFilter('year', e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    {availableYears.map((y) => (
                                        <option key={y} value={y}>{y}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <InputLabel value="Tier" />
                                <select
                                    value={filters.final_tier || ''}
                                    onChange={(e) => applyFilter('final_tier', e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">Todos</option>
                                    <option value="black">Black</option>
                                    <option value="gold">Gold</option>
                                    <option value="pending">Pendentes curadoria</option>
                                </select>
                            </div>
                            <div className="md:col-span-2">
                                <InputLabel value="Buscar cliente" />
                                <TextInput
                                    type="text"
                                    value={searchInput}
                                    onChange={(e) => setSearchInput(e.target.value)}
                                    placeholder="Nome, CPF, e-mail…"
                                    className="mt-1 block w-full"
                                />
                            </div>
                        </div>
                    </div>

                    {/* Tabela */}
                    <div className="bg-white rounded-lg shadow-sm overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cliente</th>
                                        <th className="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden md:table-cell">Sugerido</th>
                                        <th className="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tier final</th>
                                        <th className="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Faturamento {year - 1}</th>
                                        <th className="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase hidden lg:table-cell">NFs</th>
                                        <th className="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden lg:table-cell">Loja preferida</th>
                                        <th className="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase hidden xl:table-cell">Curadoria</th>
                                        <th className="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200">
                                    {tiers.data?.length > 0 ? tiers.data.map((t) => (
                                        <tr key={t.id} className="hover:bg-gray-50">
                                            <td className="px-3 py-3">
                                                <div className="font-medium text-gray-900">{t.customer.name}</div>
                                                <div className="text-xs text-gray-500">
                                                    {t.customer.formatted_cpf || '—'}
                                                    {t.customer.city && ` · ${t.customer.city}${t.customer.state ? `/${t.customer.state}` : ''}`}
                                                </div>
                                            </td>
                                            <td className="px-3 py-3 hidden md:table-cell">
                                                {t.suggested_tier ? (
                                                    <StatusBadge color={TIER_VARIANTS[t.suggested_tier].color} size="sm">
                                                        {TIER_VARIANTS[t.suggested_tier].label}
                                                    </StatusBadge>
                                                ) : (
                                                    <span className="text-xs text-gray-400">—</span>
                                                )}
                                            </td>
                                            <td className="px-3 py-3">
                                                {t.final_tier ? (
                                                    <StatusBadge color={TIER_VARIANTS[t.final_tier].color}>
                                                        {TIER_VARIANTS[t.final_tier].label}
                                                    </StatusBadge>
                                                ) : (
                                                    <StatusBadge color="gray" size="sm">Sem tier</StatusBadge>
                                                )}
                                            </td>
                                            <td className="px-3 py-3 text-right font-mono text-sm">
                                                {fmtCurrency(t.total_revenue)}
                                            </td>
                                            <td className="px-3 py-3 text-right text-sm text-gray-600 hidden lg:table-cell">
                                                {t.total_orders}
                                            </td>
                                            <td className="px-3 py-3 text-sm hidden lg:table-cell">
                                                {t.preferred_store ? (
                                                    <div>
                                                        <div className="font-medium text-gray-900">{t.preferred_store.code}</div>
                                                        <div className="text-xs text-gray-500 truncate max-w-[180px]" title={t.preferred_store.name}>
                                                            {t.preferred_store.name}
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-gray-400">—</span>
                                                )}
                                            </td>
                                            <td className="px-3 py-3 text-xs text-gray-500 hidden xl:table-cell">
                                                {t.curated_at ? (
                                                    <>
                                                        <div>{fmtDateTime(t.curated_at)}</div>
                                                        {t.curated_by && <div className="text-gray-400">por {t.curated_by}</div>}
                                                    </>
                                                ) : (
                                                    <span className="text-amber-600">Pendente</span>
                                                )}
                                            </td>
                                            <td className="px-3 py-3 text-right">
                                                <ActionButtons
                                                    onEdit={can.curate ? () => openModal('curate', t) : null}
                                                    onDelete={can.curate && t.final_tier ? () => handleRemove(t) : null}
                                                >
                                                    {can.view_reports && (
                                                        <ActionButtons.Custom
                                                            variant="info"
                                                            icon={ChartBarIcon}
                                                            title="Relatório YoY (faturamento)"
                                                            onClick={() => openModal('report', t)}
                                                        />
                                                    )}
                                                    {can.manage_activities && (
                                                        <ActionButtons.Custom
                                                            variant="secondary"
                                                            icon={GiftIcon}
                                                            title="Atividades de marketing"
                                                            onClick={() => openModal('activities', t)}
                                                        />
                                                    )}
                                                </ActionButtons>
                                            </td>
                                        </tr>
                                    )) : (
                                        <tr>
                                            <td colSpan={8} className="p-8">
                                                <EmptyState
                                                    title="Nenhum VIP classificado para este ano"
                                                    description={can.manage
                                                        ? 'Configure os thresholds e gere as sugestões para começar.'
                                                        : 'Aguarde a geração das sugestões pelo time de Marketing.'
                                                    }
                                                    icon={TrophyIcon}
                                                />
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Paginação */}
                        {tiers.last_page > 1 && (
                            <div className="px-4 py-3 bg-gray-50 border-t flex items-center justify-between">
                                <p className="text-sm text-gray-500">
                                    Mostrando {tiers.from}-{tiers.to} de {new Intl.NumberFormat('pt-BR').format(tiers.total)}
                                </p>
                                <div className="flex gap-1">
                                    {tiers.links.filter((l) => l.url).map((link, i) => (
                                        <button
                                            key={i}
                                            onClick={() => router.get(link.url, {}, { preserveState: true, preserveScroll: true })}
                                            className={`px-3 py-1 text-sm rounded ${
                                                link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100 border'
                                            }`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            <CurateModal
                show={modals.curate}
                onClose={() => closeModal('curate')}
                tier={selected}
            />
            <ReportModal
                show={modals.report}
                onClose={() => closeModal('report')}
                tier={selected}
                year={year}
            />
            <ActivitiesModal
                show={modals.activities}
                onClose={() => closeModal('activities')}
                tier={selected}
            />

            <ConfirmDialogComponent />
        </>
    );
}

// ----------------------------------------------------------------------
// Curate Modal
// ----------------------------------------------------------------------
function CurateModal({ show, onClose, tier }) {
    const { data, setData, patch, processing, reset, errors } = useForm({
        final_tier: '',
        notes: '',
    });

    useEffect(() => {
        if (show && tier) {
            setData({
                final_tier: tier.final_tier || '',
                notes: tier.notes || '',
            });
        }
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [show, tier?.id]);

    const handleSubmit = () => {
        patch(route('customers.vip.curate', tier.id), {
            preserveScroll: true,
            onSuccess: () => { reset(); onClose(); },
        });
    };

    if (!tier) return null;

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title={`Curar — ${tier.customer.name}`}
            subtitle={`Ano ${tier.year} · Faturamento ${fmtCurrency(tier.total_revenue)}`}
            headerColor="bg-indigo-600"
            headerIcon={<TrophyIcon className="w-6 h-6" />}
            maxWidth="2xl"
            onSubmit={handleSubmit}
            footer={
                <StandardModal.Footer
                    onCancel={onClose}
                    onSubmit="submit"
                    submitLabel="Salvar curadoria"
                    processing={processing}
                />
            }
        >
            <StandardModal.Section title={`Snapshot — Lista ${tier.year} (faturamento ${tier.revenue_year ?? tier.year - 1})`}>
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <StandardModal.Field
                        label={`Faturamento ${tier.revenue_year ?? tier.year - 1}`}
                        value={fmtCurrency(tier.total_revenue)}
                    />
                    <StandardModal.Field label="NFs" value={tier.total_orders} />
                    <StandardModal.Field
                        label="Loja preferida"
                        value={tier.preferred_store
                            ? `${tier.preferred_store.code} · ${tier.preferred_store.name}`
                            : '—'}
                    />
                    <StandardModal.Field label="Sugerido" value={tier.suggested_tier ? TIER_VARIANTS[tier.suggested_tier].label : '—'} />
                </div>
                <p className="mt-3 text-xs text-gray-500">
                    A lista de {tier.year} é construída com o faturamento de {tier.revenue_year ?? tier.year - 1},
                    apenas em lojas da rede Meia Sola. Loja preferida é a de maior faturamento; em empate,
                    maior número de NFs; em empate, maior quantidade de itens.
                </p>
            </StandardModal.Section>

            <StandardModal.Section title="Decisão de curadoria">
                <div className="space-y-4">
                    <div>
                        <InputLabel value="Tier final" />
                        <div className="mt-2 flex gap-2 flex-wrap">
                            {['black', 'gold', ''].map((opt) => (
                                <button
                                    key={opt || 'none'}
                                    type="button"
                                    onClick={() => setData('final_tier', opt)}
                                    className={`px-4 py-2 rounded-lg border text-sm font-medium transition-colors ${
                                        data.final_tier === opt
                                            ? opt === 'black' ? 'bg-gray-900 text-white border-gray-900'
                                                : opt === 'gold' ? 'bg-amber-500 text-white border-amber-500'
                                                : 'bg-gray-200 text-gray-700 border-gray-300'
                                            : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'
                                    }`}
                                >
                                    {opt === '' ? 'Sem tier (remover)' : TIER_VARIANTS[opt].label}
                                </button>
                            ))}
                        </div>
                        {errors.final_tier && <p className="text-red-600 text-xs mt-1">{errors.final_tier}</p>}
                    </div>

                    <div>
                        <InputLabel value="Notas da curadoria" />
                        <textarea
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                            rows={3}
                            maxLength={1000}
                            placeholder="Ex: Cliente frequente em eventos, consome coleções premium…"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        />
                        {errors.notes && <p className="text-red-600 text-xs mt-1">{errors.notes}</p>}
                    </div>
                </div>
            </StandardModal.Section>
        </StandardModal>
    );
}

// ----------------------------------------------------------------------
// Report Modal (YoY)
// ----------------------------------------------------------------------
function ReportModal({ show, onClose, tier, year }) {
    const [loading, setLoading] = useState(false);
    const [mode, setMode] = useState('ytd');
    const [report, setReport] = useState(null);

    useEffect(() => {
        if (!show || !tier) return;
        setLoading(true);
        fetch(route('customers.vip.report.yoy', tier.customer.id) + `?year=${year}&mode=${mode}`, {
            headers: { Accept: 'application/json' },
        })
            .then((r) => r.json())
            .then((data) => setReport(data.report))
            .catch(() => setReport(null))
            .finally(() => setLoading(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [show, tier?.id, year, mode]);

    const chartData = useMemo(() => {
        if (!report) return [];
        return MONTHS_PT.map((label, idx) => {
            const m = idx + 1;
            return {
                month: label,
                [report.current.year]: report.current.monthly[m] || 0,
                [report.previous.year]: report.previous.monthly[m] || 0,
            };
        });
    }, [report]);

    if (!tier) return null;

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title={`Comparativo YoY — ${tier.customer.name}`}
            subtitle={`CPF ${tier.customer.formatted_cpf || '—'}`}
            headerColor="bg-purple-600"
            headerIcon={<ChartBarIcon className="w-6 h-6" />}
            maxWidth="5xl"
            loading={loading}
            footer={<StandardModal.Footer onCancel={onClose} cancelLabel="Fechar" />}
        >
            <StandardModal.Section title="Período">
                <div className="flex gap-2">
                    <button
                        type="button"
                        onClick={() => setMode('ytd')}
                        className={`px-3 py-1.5 rounded text-sm ${mode === 'ytd' ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-700'}`}
                    >
                        YTD (até hoje)
                    </button>
                    <button
                        type="button"
                        onClick={() => setMode('full_year')}
                        className={`px-3 py-1.5 rounded text-sm ${mode === 'full_year' ? 'bg-purple-600 text-white' : 'bg-gray-100 text-gray-700'}`}
                    >
                        Ano inteiro
                    </button>
                </div>
            </StandardModal.Section>

            {report && (
                <>
                    <StandardModal.Section title="Resumo">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <StandardModal.InfoCard
                                label={`${report.current.year} (atual)`}
                                value={fmtCurrency(report.current.total)}
                                sub={`${report.current.orders} NFs`}
                            />
                            <StandardModal.InfoCard
                                label={`${report.previous.year} (anterior)`}
                                value={fmtCurrency(report.previous.total)}
                                sub={`${report.previous.orders} NFs`}
                            />
                            <StandardModal.InfoCard
                                label="Delta"
                                value={
                                    report.delta.pct === null
                                        ? '—'
                                        : `${report.delta.pct > 0 ? '+' : ''}${report.delta.pct.toFixed(1)}%`
                                }
                                sub={fmtCurrency(report.delta.absolute)}
                                color={
                                    report.delta.absolute > 0 ? 'success'
                                        : report.delta.absolute < 0 ? 'danger' : 'gray'
                                }
                            />
                        </div>
                    </StandardModal.Section>

                    <StandardModal.Section title="Evolução mensal">
                        <div className="h-72">
                            <ResponsiveContainer width="100%" height="100%">
                                <LineChart data={chartData}>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis dataKey="month" />
                                    <YAxis tickFormatter={(v) => new Intl.NumberFormat('pt-BR', { notation: 'compact' }).format(v)} />
                                    <Tooltip formatter={(v) => fmtCurrency(v)} />
                                    <Legend />
                                    <Line
                                        type="monotone"
                                        dataKey={report.previous.year}
                                        stroke="#9ca3af"
                                        strokeDasharray="4 4"
                                        strokeWidth={2}
                                        dot={false}
                                    />
                                    <Line
                                        type="monotone"
                                        dataKey={report.current.year}
                                        stroke="#6366f1"
                                        strokeWidth={2.5}
                                        dot={{ r: 3 }}
                                    />
                                </LineChart>
                            </ResponsiveContainer>
                        </div>
                    </StandardModal.Section>
                </>
            )}
        </StandardModal>
    );
}

// ----------------------------------------------------------------------
// Activities Modal (feed CRM-light)
// ----------------------------------------------------------------------
function ActivitiesModal({ show, onClose, tier }) {
    const [activities, setActivities] = useState([]);
    const [loading, setLoading] = useState(false);
    const [showForm, setShowForm] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        type: 'gift',
        title: '',
        description: '',
        occurred_at: new Date().toISOString().split('T')[0],
    });

    const load = async () => {
        if (!tier) return;
        setLoading(true);
        try {
            const res = await fetch(route('customers.vip.activities.index', tier.customer.id), {
                headers: { Accept: 'application/json' },
            });
            const json = await res.json();
            setActivities(json.activities || []);
        } catch {
            setActivities([]);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (show && tier) { load(); setShowForm(false); reset(); }
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [show, tier?.id]);

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('customers.vip.activities.store', tier.customer.id), {
            preserveScroll: true,
            onSuccess: () => { reset(); setShowForm(false); load(); },
        });
    };

    const handleDelete = async (id) => {
        router.delete(route('customers.vip.activities.destroy', id), {
            preserveScroll: true,
            onSuccess: () => load(),
        });
    };

    if (!tier) return null;

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title={`Atividades — ${tier.customer.name}`}
            subtitle="Feed de relacionamento (brindes, eventos, contatos, notas)"
            headerColor="bg-purple-600"
            headerIcon={<GiftIcon className="w-6 h-6" />}
            maxWidth="3xl"
            headerActions={
                !showForm && (
                    <Button size="sm" variant="light" onClick={() => setShowForm(true)} icon={PlusIcon}>
                        Nova
                    </Button>
                )
            }
            footer={<StandardModal.Footer onCancel={onClose} cancelLabel="Fechar" />}
        >
            {showForm && (
                <StandardModal.Section title="Nova atividade">
                    <form onSubmit={handleSubmit} className="space-y-3">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <InputLabel value="Tipo" />
                                <select
                                    value={data.type}
                                    onChange={(e) => setData('type', e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                >
                                    {Object.entries(ACTIVITY_TYPE_LABEL).map(([k, v]) => (
                                        <option key={k} value={k}>{v}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <InputLabel value="Data" />
                                <TextInput
                                    type="date"
                                    value={data.occurred_at}
                                    onChange={(e) => setData('occurred_at', e.target.value)}
                                    className="mt-1 block w-full"
                                />
                            </div>
                        </div>
                        <div>
                            <InputLabel value="Título" />
                            <TextInput
                                type="text"
                                value={data.title}
                                onChange={(e) => setData('title', e.target.value)}
                                placeholder="Ex: Envio de brinde — aniversário"
                                className="mt-1 block w-full"
                                required
                            />
                        </div>
                        <div>
                            <InputLabel value="Descrição" />
                            <textarea
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                rows={2}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            />
                        </div>
                        <div className="flex gap-2 justify-end">
                            <Button type="button" variant="outline" size="sm" onClick={() => { setShowForm(false); reset(); }}>
                                Cancelar
                            </Button>
                            <Button type="submit" variant="primary" size="sm" loading={processing}>
                                Registrar
                            </Button>
                        </div>
                    </form>
                </StandardModal.Section>
            )}

            <StandardModal.Section title={`Histórico (${activities.length})`}>
                {loading ? (
                    <p className="text-sm text-gray-500">Carregando…</p>
                ) : activities.length === 0 ? (
                    <p className="text-sm text-gray-500 italic">Nenhuma atividade registrada ainda.</p>
                ) : (
                    <ul className="divide-y divide-gray-200">
                        {activities.map((a) => {
                            const Icon = ACTIVITY_TYPE_ICON[a.type] || SparklesIcon;
                            return (
                                <li key={a.id} className="py-3 flex items-start gap-3">
                                    <div className="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center shrink-0">
                                        <Icon className="w-4 h-4 text-purple-700" />
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-center gap-2 flex-wrap">
                                            <StatusBadge color="purple" size="xs">{ACTIVITY_TYPE_LABEL[a.type]}</StatusBadge>
                                            <span className="font-medium text-sm text-gray-900">{a.title}</span>
                                            <span className="text-xs text-gray-500">{fmtDate(a.occurred_at)}</span>
                                        </div>
                                        {a.description && (
                                            <p className="mt-1 text-sm text-gray-600 whitespace-pre-wrap">{a.description}</p>
                                        )}
                                        <p className="mt-1 text-xs text-gray-400">
                                            Registrado por {a.created_by || 'sistema'} · {fmtDateTime(a.created_at)}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => handleDelete(a.id)}
                                        className="p-1 text-red-600 hover:text-red-800"
                                        title="Excluir"
                                    >
                                        <TrashIcon className="w-4 h-4" />
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </StandardModal.Section>
        </StandardModal>
    );
}
