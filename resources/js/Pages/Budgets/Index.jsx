import { Head, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import axios from 'axios';
import {
    BanknotesIcon,
    CheckCircleIcon,
    ArchiveBoxIcon,
    CalendarDaysIcon,
    TagIcon,
    ArrowDownTrayIcon,
    DocumentTextIcon,
    ExclamationTriangleIcon,
    ClockIcon,
    CloudArrowUpIcon,
    ChartBarIcon,
} from '@heroicons/react/24/outline';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import DataTable from '@/Components/DataTable';
import StandardModal from '@/Components/StandardModal';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import StatusBadge from '@/Components/Shared/StatusBadge';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import BudgetUploadWizard from './components/BudgetUploadWizard';

const BRL = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const MONTH_LABELS = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

export default function Index({ budgets, filters = {}, statistics = {}, enums = {}, selects = {} }) {
    const { hasPermission } = usePermissions();
    const canDownload = hasPermission(PERMISSIONS.DOWNLOAD_BUDGETS);
    const canDelete = hasPermission(PERMISSIONS.DELETE_BUDGETS);
    const canUpload = hasPermission(PERMISSIONS.UPLOAD_BUDGETS);
    const canViewConsumption = hasPermission(PERMISSIONS.VIEW_BUDGET_CONSUMPTION);

    const { modals, selected, openModal, closeModal } = useModalManager(['detail', 'upload']);

    // ------------------------------------------------------------------
    // Detail
    // ------------------------------------------------------------------
    const [detail, setDetail] = useState(null);
    const [loadingDetail, setLoadingDetail] = useState(false);

    const handleDetailOpen = async (b) => {
        openModal('detail', b);
        setLoadingDetail(true);
        try {
            const { data } = await axios.get(route('budgets.show', b.id));
            setDetail(data.budget);
        } catch (_) {
            setDetail(null);
        } finally {
            setLoadingDetail(false);
        }
    };

    // ------------------------------------------------------------------
    // Delete
    // ------------------------------------------------------------------
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleteReason, setDeleteReason] = useState('');
    const [deleteProcessing, setDeleteProcessing] = useState(false);

    const handleDelete = () => {
        if (!deleteTarget) return;
        setDeleteProcessing(true);
        router.delete(route('budgets.destroy', deleteTarget.id), {
            data: { deleted_reason: deleteReason },
            preserveScroll: true,
            onSuccess: () => {
                setDeleteTarget(null);
                setDeleteReason('');
            },
            onFinish: () => setDeleteProcessing(false),
        });
    };

    // ------------------------------------------------------------------
    // Filters
    // ------------------------------------------------------------------
    const applyFilter = (key, value) => {
        router.get(route('budgets.index'), {
            ...filters,
            [key]: value || undefined,
        }, { preserveState: true, preserveScroll: true, replace: true });
    };

    // ------------------------------------------------------------------
    // Cards
    // ------------------------------------------------------------------
    const statisticsCards = useMemo(() => [
        {
            label: 'Orçamentos ativos',
            value: statistics.active || 0,
            format: 'number',
            icon: CheckCircleIcon,
            color: 'green',
        },
        {
            label: 'Total previsto (ativas)',
            value: statistics.total_amount_active || 0,
            format: 'currency',
            icon: BanknotesIcon,
            color: 'indigo',
        },
        {
            label: 'Versões inativas',
            value: statistics.inactive || 0,
            format: 'number',
            icon: ArchiveBoxIcon,
            color: 'gray',
            active: filters.include_inactive === '1' || filters.include_inactive === 1,
            onClick: () => applyFilter('include_inactive', filters.include_inactive ? '' : '1'),
        },
        {
            label: 'Escopos distintos',
            value: statistics.distinct_scopes || 0,
            format: 'number',
            icon: TagIcon,
            color: 'purple',
        },
        {
            label: 'Anos cobertos',
            value: statistics.distinct_years || 0,
            format: 'number',
            icon: CalendarDaysIcon,
            color: 'orange',
        },
    ], [statistics, filters]);

    const columns = [
        { key: 'year', label: 'Ano', sortable: true, className: 'font-mono' },
        {
            key: 'scope_label',
            label: 'Escopo',
            render: (b) => (
                <span className="font-medium text-gray-900">{b.scope_label}</span>
            ),
        },
        {
            key: 'version_label',
            label: 'Versão',
            render: (b) => (
                <div className="flex items-center gap-2">
                    <span className="font-mono font-semibold text-indigo-700">
                        v{b.version_label}
                    </span>
                    <span className="text-xs text-gray-500">({b.upload_type_label})</span>
                </div>
            ),
        },
        {
            key: 'items_count',
            label: 'Linhas',
            render: (b) => (
                <span className="text-sm text-gray-700">{b.items_count}</span>
            ),
        },
        {
            key: 'total_year',
            label: 'Total anual',
            render: (b) => (
                <span className="font-mono text-sm text-gray-900">
                    {BRL.format(b.total_year || 0)}
                </span>
            ),
        },
        {
            key: 'is_active',
            label: 'Status',
            render: (b) => (
                <StatusBadge variant={b.is_active ? 'success' : 'gray'}>
                    {b.is_active ? 'Ativo' : 'Inativo'}
                </StatusBadge>
            ),
        },
        {
            key: 'created_at',
            label: 'Enviado em',
            render: (b) => (
                <span className="text-xs text-gray-500">
                    {b.created_at ? new Date(b.created_at).toLocaleDateString('pt-BR') : '—'}
                </span>
            ),
        },
        {
            key: 'actions',
            label: 'Ações',
            render: (b) => (
                <div className="flex gap-1 items-center">
                    <ActionButtons
                        onView={() => handleDetailOpen(b)}
                        onDelete={(canDelete && !b.is_active) ? () => setDeleteTarget(b) : undefined}
                    />
                    {canViewConsumption && (
                        <ActionButtons.Custom
                            variant="info"
                            icon={ChartBarIcon}
                            title="Dashboard de consumo"
                            onClick={() => router.visit(route('budgets.dashboard', b.id))}
                        />
                    )}
                    {canDownload && (
                        <ActionButtons.Custom
                            variant="success"
                            icon={ArrowDownTrayIcon}
                            title="Baixar planilha original"
                            onClick={() => window.location.href = route('budgets.download', b.id)}
                        />
                    )}
                </div>
            ),
        },
    ];

    return (
        <>
            <Head title="Orçamentos" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="mb-6 flex justify-between items-center">
                        <div>
                            <h1 className="text-3xl font-bold text-gray-900">Orçamentos</h1>
                            <p className="mt-1 text-sm text-gray-600">
                                Orçamento anual por escopo com versionamento e consumo previsto × realizado.
                            </p>
                        </div>
                        {canUpload && (
                            <Button
                                variant="primary"
                                icon={CloudArrowUpIcon}
                                onClick={() => openModal('upload')}
                            >
                                Novo Upload
                            </Button>
                        )}
                    </div>

                    <StatisticsGrid cards={statisticsCards} cols={5} />

                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                            <div>
                                <InputLabel value="Buscar" className="mb-2" />
                                <TextInput
                                    className="w-full text-sm"
                                    value={filters.search || ''}
                                    onChange={(e) => applyFilter('search', e.target.value)}
                                    placeholder="Escopo, versão ou arquivo..."
                                />
                            </div>
                            <div>
                                <InputLabel value="Ano" className="mb-2" />
                                <select
                                    value={filters.year || ''}
                                    onChange={(e) => applyFilter('year', e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                >
                                    <option value="">Todos</option>
                                    {(selects.years || []).map((y) => (
                                        <option key={y} value={y}>{y}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <InputLabel value="Escopo" className="mb-2" />
                                <select
                                    value={filters.scope_label || ''}
                                    onChange={(e) => applyFilter('scope_label', e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                >
                                    <option value="">Todos</option>
                                    {(selects.scopes || []).map((s) => (
                                        <option key={s} value={s}>{s}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <InputLabel value="Tipo" className="mb-2" />
                                <select
                                    value={filters.upload_type || ''}
                                    onChange={(e) => applyFilter('upload_type', e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                >
                                    <option value="">Todos</option>
                                    {Object.entries(enums.uploadTypes || {}).map(([k, v]) => (
                                        <option key={k} value={k}>{v}</option>
                                    ))}
                                </select>
                            </div>
                        </div>
                    </div>

                    <DataTable
                        columns={columns}
                        data={budgets}
                        emptyMessage={filters.include_inactive
                            ? 'Nenhum orçamento cadastrado.'
                            : 'Nenhum orçamento ativo. Clique no card "Versões inativas" para ver o histórico.'}
                    />
                </div>
            </div>

            {/* -------- Detail Modal -------- */}
            <StandardModal
                show={modals.detail}
                onClose={() => {
                    closeModal('detail');
                    setDetail(null);
                }}
                title={detail
                    ? `${detail.scope_label} — ${detail.year} · v${detail.version_label}`
                    : selected
                        ? `${selected.scope_label} — ${selected.year} · v${selected.version_label}`
                        : 'Detalhes'}
                subtitle={detail?.upload_type_label || selected?.upload_type_label}
                headerColor="bg-indigo-700"
                headerIcon={BanknotesIcon}
                maxWidth="5xl"
                loading={loadingDetail}
                headerBadges={detail ? [
                    {
                        text: detail.is_active ? 'Ativo' : 'Inativo',
                        className: detail.is_active
                            ? 'bg-white/20 text-white'
                            : 'bg-white/10 text-white/70',
                    },
                    {
                        text: `${detail.items_count} linhas`,
                        className: 'bg-white/20 text-white',
                    },
                    {
                        text: BRL.format(detail.total_year || 0),
                        className: 'bg-white/20 text-white font-mono',
                    },
                ] : []}
                headerActions={detail && canDownload ? (
                    <button
                        onClick={() => window.location.href = route('budgets.download', detail.id)}
                        className="text-white text-sm font-medium hover:bg-white/10 px-3 py-1.5 rounded flex items-center gap-1"
                    >
                        <ArrowDownTrayIcon className="w-4 h-4" /> Baixar xlsx
                    </button>
                ) : null}
            >
                {detail && (
                    <>
                        <StandardModal.Section title="Informações">
                            <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                                <StandardModal.Field label="Ano" value={detail.year} />
                                <StandardModal.Field label="Escopo" value={detail.scope_label} />
                                <StandardModal.Field label="Versão" value={`v${detail.version_label}`} />
                                <StandardModal.Field label="Tipo" value={detail.upload_type_label} />
                                <StandardModal.Field label="Linhas" value={detail.items_count} />
                                <StandardModal.Field
                                    label="Total previsto"
                                    value={BRL.format(detail.total_year || 0)}
                                />
                                <StandardModal.Field
                                    label="Arquivo original"
                                    value={detail.original_filename}
                                />
                                <StandardModal.Field
                                    label="Criado por"
                                    value={detail.created_by || '—'}
                                />
                                <StandardModal.Field
                                    label="Enviado em"
                                    value={detail.created_at ? new Date(detail.created_at).toLocaleString('pt-BR') : '—'}
                                />
                            </div>
                            {detail.notes && (
                                <div className="mt-4 bg-gray-50 rounded p-3">
                                    <p className="text-xs font-medium text-gray-500 uppercase mb-1">
                                        Observações
                                    </p>
                                    <p className="text-sm text-gray-800 whitespace-pre-wrap">
                                        {detail.notes}
                                    </p>
                                </div>
                            )}
                        </StandardModal.Section>

                        {detail.items && detail.items.length > 0 && (
                            <StandardModal.Section title={`Linhas do orçamento (${detail.items.length})`}>
                                <div className="overflow-x-auto border border-gray-200 rounded-lg">
                                    <table className="min-w-full text-xs">
                                        <thead className="bg-gray-100">
                                            <tr>
                                                <th className="px-2 py-1.5 text-left font-medium sticky left-0 bg-gray-100">
                                                    Conta contábil
                                                </th>
                                                <th className="px-2 py-1.5 text-left font-medium">
                                                    Conta gerencial
                                                </th>
                                                <th className="px-2 py-1.5 text-left font-medium">
                                                    CC
                                                </th>
                                                <th className="px-2 py-1.5 text-left font-medium">
                                                    Loja
                                                </th>
                                                <th className="px-2 py-1.5 text-left font-medium">
                                                    Fornecedor
                                                </th>
                                                {MONTH_LABELS.map((m) => (
                                                    <th key={m} className="px-2 py-1.5 text-right font-medium">
                                                        {m}
                                                    </th>
                                                ))}
                                                <th className="px-2 py-1.5 text-right font-medium bg-indigo-100">
                                                    Total
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-200">
                                            {detail.items.map((item) => (
                                                <tr key={item.id} className="hover:bg-gray-50">
                                                    <td className="px-2 py-1.5 sticky left-0 bg-white">
                                                        <span className="font-mono text-gray-700">
                                                            {item.accounting_class?.code}
                                                        </span>
                                                        <span className="block text-gray-500 text-[10px]">
                                                            {item.accounting_class?.name}
                                                        </span>
                                                    </td>
                                                    <td className="px-2 py-1.5">
                                                        <span className="font-mono text-gray-700">
                                                            {item.management_class?.code}
                                                        </span>
                                                        <span className="block text-gray-500 text-[10px]">
                                                            {item.management_class?.name}
                                                        </span>
                                                    </td>
                                                    <td className="px-2 py-1.5">
                                                        <span className="font-mono text-gray-700">
                                                            {item.cost_center?.code}
                                                        </span>
                                                    </td>
                                                    <td className="px-2 py-1.5 text-gray-500">
                                                        {item.store?.code || '—'}
                                                    </td>
                                                    <td className="px-2 py-1.5 text-gray-500">
                                                        {item.supplier || '—'}
                                                    </td>
                                                    {[1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12].map((m) => (
                                                        <td key={m} className="px-2 py-1.5 text-right font-mono">
                                                            {(item.months?.[m] || 0) > 0
                                                                ? BRL.format(item.months[m])
                                                                : <span className="text-gray-300">—</span>}
                                                        </td>
                                                    ))}
                                                    <td className="px-2 py-1.5 text-right font-mono font-semibold bg-indigo-50">
                                                        {BRL.format(item.year_total || 0)}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </StandardModal.Section>
                        )}

                        {detail.status_history && detail.status_history.length > 0 && (
                            <StandardModal.Section title="Histórico">
                                <ul className="space-y-2">
                                    {detail.status_history.map((h) => (
                                        <li key={h.id} className="flex items-start gap-3 bg-gray-50 rounded p-3 text-sm">
                                            <ClockIcon className="w-4 h-4 text-gray-400 shrink-0 mt-0.5" />
                                            <div className="flex-1">
                                                <div className="flex items-center justify-between">
                                                    <span className="font-medium text-gray-900">
                                                        {eventLabel(h.event)}
                                                    </span>
                                                    <span className="text-xs text-gray-500">
                                                        {h.created_at ? new Date(h.created_at).toLocaleString('pt-BR') : '—'}
                                                    </span>
                                                </div>
                                                {h.note && (
                                                    <p className="text-xs text-gray-700 mt-1">{h.note}</p>
                                                )}
                                                <p className="text-xs text-gray-500 mt-0.5">
                                                    {h.changed_by || 'Sistema'}
                                                </p>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </StandardModal.Section>
                        )}
                    </>
                )}
            </StandardModal>

            {/* -------- Delete Modal -------- */}
            <StandardModal
                show={deleteTarget !== null}
                onClose={() => { setDeleteTarget(null); setDeleteReason(''); }}
                title="Excluir versão de orçamento"
                subtitle={deleteTarget
                    ? `${deleteTarget.scope_label} ${deleteTarget.year} · v${deleteTarget.version_label}`
                    : ''}
                headerColor="bg-red-600"
                headerIcon={ExclamationTriangleIcon}
                maxWidth="md"
                footer={
                    <StandardModal.Footer
                        onCancel={() => { setDeleteTarget(null); setDeleteReason(''); }}
                        onSubmit={handleDelete}
                        submitLabel="Excluir"
                        submitColor="bg-red-600 hover:bg-red-700"
                        processing={deleteProcessing}
                        disabled={deleteReason.trim().length < 3}
                    />
                }
            >
                <div className="space-y-3">
                    <div className="flex items-start gap-2 bg-red-50 border border-red-100 rounded-lg p-3">
                        <ExclamationTriangleIcon className="h-5 w-5 text-red-500 shrink-0 mt-0.5" />
                        <p className="text-sm text-red-700">
                            Soft delete. Apenas versões <strong>não-ativas</strong> podem ser excluídas.
                            A versão ativa nunca é excluída diretamente — faça upload de uma nova versão
                            para substituí-la.
                        </p>
                    </div>
                    <div>
                        <InputLabel value="Motivo da exclusão *" className="mb-1 text-xs" />
                        <TextInput
                            className="w-full text-sm"
                            value={deleteReason}
                            onChange={(e) => setDeleteReason(e.target.value)}
                            placeholder="Mínimo 3 caracteres"
                        />
                    </div>
                </div>
            </StandardModal>

            {/* -------- Upload Wizard -------- */}
            <BudgetUploadWizard
                show={modals.upload}
                onClose={() => closeModal('upload')}
                enums={enums}
                selects={selects}
            />
        </>
    );
}

function eventLabel(event) {
    return ({
        created: 'Criado',
        activated: 'Ativado',
        deactivated: 'Desativado',
        deleted: 'Excluído',
    })[event] || event;
}
