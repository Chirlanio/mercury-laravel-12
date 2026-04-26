import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import {
    PaperAirplaneIcon,
    ClockIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    DocumentCheckIcon,
    BanknotesIcon,
    EyeIcon,
    PencilSquareIcon,
    TrashIcon,
    PaperClipIcon,
    PaperAirplaneIcon as SendIcon,
    HandThumbUpIcon,
    HandThumbDownIcon,
    XCircleIcon,
} from '@heroicons/react/24/outline';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import { useConfirm } from '@/Hooks/useConfirm';
import ActionButtons from '@/Components/ActionButtons';
import Button from '@/Components/Button';
import DataTable from '@/Components/DataTable';
import StandardModal from '@/Components/StandardModal';
import PageHeader from '@/Components/Shared/PageHeader';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import TravelExpenseFormModal from './Partials/TravelExpenseFormModal';
import TravelExpenseDetailModal from './Partials/TravelExpenseDetailModal';
import AccountabilityModal from './Partials/AccountabilityModal';
import TransitionModal from './Partials/TransitionModal';
import DeleteWithReasonModal from './Partials/DeleteWithReasonModal';

const COLOR_MAP = {
    success: 'success',
    warning: 'warning',
    info: 'info',
    danger: 'danger',
    purple: 'purple',
    gray: 'gray',
    orange: 'orange',
    teal: 'teal',
};

export default function Index({
    expenses,
    filters = {},
    statistics = {},
    statusOptions = {},
    statusColors = {},
    statusTransitions = {},
    accountabilityStatusOptions = {},
    accountabilityStatusColors = {},
    accountabilityTransitions = {},
    isStoreScoped = false,
    scopedStoreCode = null,
    selects = {},
    permissions: serverPerms = {},
    defaultDailyRate = 100,
}) {
    const { hasPermission } = usePermissions();
    const canCreate = serverPerms.create ?? hasPermission(PERMISSIONS.CREATE_TRAVEL_EXPENSES);
    const canEdit = serverPerms.edit ?? hasPermission(PERMISSIONS.EDIT_TRAVEL_EXPENSES);
    const canDelete = serverPerms.delete ?? hasPermission(PERMISSIONS.DELETE_TRAVEL_EXPENSES);
    const canApprove = serverPerms.approve ?? hasPermission(PERMISSIONS.APPROVE_TRAVEL_EXPENSES);
    const canManage = serverPerms.manage ?? hasPermission(PERMISSIONS.MANAGE_TRAVEL_EXPENSES);
    const canManageAccountability = serverPerms.manageAccountability ?? hasPermission(PERMISSIONS.MANAGE_ACCOUNTABILITY);
    const canExport = serverPerms.export ?? hasPermission(PERMISSIONS.EXPORT_TRAVEL_EXPENSES);

    const { modals, selected, openModal, closeModal, switchModal } = useModalManager([
        'create', 'edit', 'detail', 'accountability', 'transition', 'delete',
    ]);
    const { confirm, ConfirmDialogComponent } = useConfirm();

    // ------------------------------------------------------------------
    // Filtros
    // ------------------------------------------------------------------
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [accountabilityStatus, setAccountabilityStatus] = useState(filters.accountability_status ?? '');
    const [storeCode, setStoreCode] = useState(filters.store_code ?? '');
    const [employeeId, setEmployeeId] = useState(filters.employee_id ?? '');
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [includeTerminal, setIncludeTerminal] = useState(filters.include_terminal === '1' || filters.include_terminal === true);

    const handleApplyFilters = (e) => {
        e?.preventDefault?.();
        router.get(route('travel-expenses.index'), {
            search: search || undefined,
            status: status || undefined,
            accountability_status: accountabilityStatus || undefined,
            store_code: storeCode || undefined,
            employee_id: employeeId || undefined,
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
            include_terminal: includeTerminal ? 1 : undefined,
        }, { preserveState: true, preserveScroll: true });
    };

    const handleClearFilters = () => {
        setSearch(''); setStatus(''); setAccountabilityStatus('');
        setStoreCode(''); setEmployeeId(''); setDateFrom(''); setDateTo('');
        setIncludeTerminal(false);
        router.get(route('travel-expenses.index'), {}, { preserveScroll: true });
    };

    // ------------------------------------------------------------------
    // Detalhes — fetch lazy ao abrir modal
    // ------------------------------------------------------------------
    const [detailExpense, setDetailExpense] = useState(null);
    const [detailLoading, setDetailLoading] = useState(false);

    const loadDetail = async (expense) => {
        setDetailLoading(true);
        try {
            const res = await fetch(route('travel-expenses.show', expense.ulid), {
                headers: { 'Accept': 'application/json' },
            });
            const json = await res.json();
            setDetailExpense(json.expense);
        } catch (err) {
            console.error(err);
            alert('Erro ao carregar detalhes.');
        } finally {
            setDetailLoading(false);
        }
    };

    const openDetail = (expense) => {
        setDetailExpense(null);
        openModal('detail', expense);
        loadDetail(expense);
    };

    const openAccountability = (expense) => {
        setDetailExpense(null);
        openModal('accountability', expense);
        loadDetail(expense);
    };

    const openEdit = (expense) => {
        // Edição precisa do payload detalhado (description, dados bancários,
        // pix decriptado, internal_notes) — formatExpense da listagem traz só
        // campos básicos. Faz fetch antes de mostrar o form pra preencher tudo.
        setDetailExpense(null);
        openModal('edit', expense);
        loadDetail(expense);
    };

    // ------------------------------------------------------------------
    // Transições (aprovar/rejeitar/cancelar)
    // ------------------------------------------------------------------
    const [transitionConfig, setTransitionConfig] = useState(null);

    const startTransition = (expense, kind, toStatus, label, requiresNote = false) => {
        setTransitionConfig({ expense, kind, toStatus, label, requiresNote });
        openModal('transition', expense);
    };

    // ------------------------------------------------------------------
    // Stats cards
    // ------------------------------------------------------------------
    const statsCards = useMemo(() => ([
        {
            label: 'Total de Verbas',
            value: statistics.total ?? 0,
            format: 'number',
            icon: PaperAirplaneIcon,
            color: 'indigo',
        },
        {
            label: 'Aguardando Aprovação',
            value: statistics.submitted ?? 0,
            format: 'number',
            icon: ClockIcon,
            color: 'warning',
            onClick: () => { setStatus('submitted'); handleApplyFilters(); },
            active: status === 'submitted',
        },
        {
            label: 'Aprovadas',
            value: statistics.approved ?? 0,
            format: 'number',
            icon: CheckCircleIcon,
            color: 'info',
            onClick: () => { setStatus('approved'); handleApplyFilters(); },
            active: status === 'approved',
        },
        {
            label: 'Prestações Atrasadas',
            value: statistics.accountability_overdue ?? 0,
            format: 'number',
            icon: ExclamationTriangleIcon,
            color: 'danger',
            sub: '≥ 3 dias após retorno',
        },
        {
            label: 'Finalizadas',
            value: statistics.finalized ?? 0,
            format: 'number',
            icon: DocumentCheckIcon,
            color: 'success',
        },
        {
            label: 'Valor Total',
            value: statistics.total_value ?? 0,
            format: 'currency',
            icon: BanknotesIcon,
            color: 'teal',
            sub: 'Aprovadas + Finalizadas',
        },
    ]), [statistics, status]);

    // ------------------------------------------------------------------
    // Tabela
    // ------------------------------------------------------------------
    const columns = useMemo(() => ([
        {
            key: 'employee',
            label: 'Beneficiado',
            sortable: false,
            render: (row) => (
                <div className="font-medium text-gray-900">
                    {row.employee?.name ?? '—'}
                    <div className="text-xs text-gray-500 font-normal">
                        {row.store?.code ?? row.store_code ?? '—'} · solicitado por {row.created_by?.name ?? '—'}
                    </div>
                </div>
            ),
        },
        {
            key: 'route',
            label: 'Trecho',
            sortable: false,
            render: (row) => (
                <div className="text-sm">
                    <div className="font-medium">{row.origin} → {row.destination}</div>
                    <div className="text-xs text-gray-500">
                        {formatDate(row.initial_date)} a {formatDate(row.end_date)} ({row.days_count} {row.days_count === 1 ? 'dia' : 'dias'})
                    </div>
                </div>
            ),
        },
        {
            key: 'value',
            label: 'Valor',
            sortable: false,
            render: (row) => (
                <div className="text-right tabular-nums font-medium text-gray-900 whitespace-nowrap">
                    {formatCurrency(row.value)}
                </div>
            ),
        },
        {
            key: 'status',
            label: 'Solicitação',
            sortable: false,
            render: (row) => (
                <StatusBadge
                    label={row.status_label}
                    variant={COLOR_MAP[row.status_color] ?? 'gray'}
                />
            ),
        },
        {
            key: 'accountability_status',
            label: 'Prestação',
            sortable: false,
            render: (row) => (
                <div className="flex items-center gap-2">
                    <StatusBadge
                        label={row.accountability_status_label}
                        variant={COLOR_MAP[row.accountability_status_color] ?? 'gray'}
                    />
                    {row.is_overdue && (
                        <span title="Prestação atrasada (≥ 3 dias após retorno)">
                            <ExclamationTriangleIcon className="h-5 w-5 text-red-500" />
                        </span>
                    )}
                </div>
            ),
        },
        {
            key: 'actions',
            label: 'Ações',
            sortable: false,
            render: (row) => (
                <ActionButtons
                    onView={() => openDetail(row)}
                    onEdit={canEditRow(row) ? () => openEdit(row) : undefined}
                    onDelete={canDeleteRow(row) ? () => openModal('delete', row) : undefined}
                >
                    {canManageAccountabilityRow(row) && (
                        <ActionButtons.Custom
                            label="Prestação de contas"
                            icon={PaperClipIcon}
                            onClick={() => openAccountability(row)}
                            variant="info-soft"
                        />
                    )}
                    {canSubmit(row) && (
                        <ActionButtons.Custom
                            label="Enviar para aprovação"
                            icon={SendIcon}
                            onClick={() => startTransition(row, 'expense', 'submitted', 'Enviar para aprovação')}
                            variant="primary-soft"
                        />
                    )}
                    {canApproveRow(row) && (
                        <>
                            <ActionButtons.Custom
                                label="Aprovar"
                                icon={HandThumbUpIcon}
                                onClick={() => startTransition(row, 'expense', 'approved', 'Aprovar verba')}
                                variant="success-soft"
                            />
                            <ActionButtons.Custom
                                label="Rejeitar"
                                icon={HandThumbDownIcon}
                                onClick={() => startTransition(row, 'expense', 'rejected', 'Rejeitar verba', true)}
                                variant="danger-soft"
                            />
                        </>
                    )}
                    {canCancelRow(row) && (
                        <ActionButtons.Custom
                            label="Cancelar"
                            icon={XCircleIcon}
                            onClick={() => startTransition(row, 'expense', 'cancelled', 'Cancelar verba', true)}
                            variant="danger-soft"
                        />
                    )}
                </ActionButtons>
            ),
        },
    // eslint-disable-next-line react-hooks/exhaustive-deps
    ]), [canEdit, canDelete, canApprove, canManage, canManageAccountability]);

    // ------------------------------------------------------------------
    // Action helpers
    // ------------------------------------------------------------------
    function canEditRow(row) {
        if (canManage) return true;
        if (!canEdit) return false;
        return ['draft', 'submitted'].includes(row.status);
    }

    function canDeleteRow(row) {
        if (!canDelete) return false;
        return ['draft', 'submitted', 'rejected'].includes(row.status);
    }

    function canManageAccountabilityRow(row) {
        if (!canManageAccountability && !canManage) return false;
        return row.status === 'approved';
    }

    function canSubmit(row) {
        return row.status === 'draft' && canEdit;
    }

    function canApproveRow(row) {
        return canApprove && row.status === 'submitted';
    }

    function canCancelRow(row) {
        if (!canApprove && !canManage) return false;
        return ['draft', 'submitted', 'approved'].includes(row.status);
    }

    // ------------------------------------------------------------------
    // Delete handler
    // ------------------------------------------------------------------
    const [deleteProcessing, setDeleteProcessing] = useState(false);
    const handleDelete = (reason) => {
        if (!selected) return;
        setDeleteProcessing(true);
        router.delete(route('travel-expenses.destroy', selected.ulid), {
            data: { deleted_reason: reason },
            preserveScroll: true,
            onFinish: () => setDeleteProcessing(false),
            onSuccess: () => closeModal('delete'),
        });
    };

    return (
        <>
            <Head title="Verbas de Viagem" />

            <div className="py-6 sm:py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <PageHeader
                        title="Verbas de Viagem"
                        subtitle="Solicitação de adiantamento e prestação de contas"
                        icon={PaperAirplaneIcon}
                        scopeBadge={isStoreScoped ? `Escopo: ${scopedStoreCode}` : null}
                        actions={[
                            {
                                type: 'dashboard',
                                href: route('travel-expenses.dashboard'),
                            },
                            {
                                type: 'download',
                                download: route('travel-expenses.export', {
                                    search: search || undefined,
                                    status: status || undefined,
                                    accountability_status: accountabilityStatus || undefined,
                                    store_code: storeCode || undefined,
                                    employee_id: employeeId || undefined,
                                    date_from: dateFrom || undefined,
                                    date_to: dateTo || undefined,
                                    include_terminal: includeTerminal ? 1 : undefined,
                                }),
                                visible: canExport,
                            },
                            {
                                type: 'create',
                                label: 'Nova Solicitação',
                                onClick: () => openModal('create'),
                                visible: canCreate,
                            },
                        ]}
                    />

                    <div className="mb-6">
                        <StatisticsGrid cards={statsCards} cols={6} />
                    </div>

                    {/* Filtros */}
                    <form
                        onSubmit={handleApplyFilters}
                        className="bg-white shadow-sm rounded-lg p-4 mb-6 space-y-4"
                    >
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">Buscar</label>
                                <input
                                    type="text"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Origem, destino, beneficiado..."
                                    className="w-full text-sm rounded-md border-gray-300"
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">Status</label>
                                <select
                                    value={status}
                                    onChange={(e) => setStatus(e.target.value)}
                                    className="w-full text-sm rounded-md border-gray-300"
                                >
                                    <option value="">Todos</option>
                                    {Object.entries(statusOptions).map(([k, v]) => (
                                        <option key={k} value={k}>{v}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">Prestação</label>
                                <select
                                    value={accountabilityStatus}
                                    onChange={(e) => setAccountabilityStatus(e.target.value)}
                                    className="w-full text-sm rounded-md border-gray-300"
                                >
                                    <option value="">Todas</option>
                                    {Object.entries(accountabilityStatusOptions).map(([k, v]) => (
                                        <option key={k} value={k}>{v}</option>
                                    ))}
                                </select>
                            </div>
                            {!isStoreScoped && (
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">Loja</label>
                                    <select
                                        value={storeCode}
                                        onChange={(e) => setStoreCode(e.target.value)}
                                        className="w-full text-sm rounded-md border-gray-300"
                                    >
                                        <option value="">Todas</option>
                                        {(selects.stores || []).map((s) => (
                                            <option key={s.id} value={s.code}>{s.code} — {s.name}</option>
                                        ))}
                                    </select>
                                </div>
                            )}
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">Beneficiado</label>
                                <select
                                    value={employeeId}
                                    onChange={(e) => setEmployeeId(e.target.value)}
                                    className="w-full text-sm rounded-md border-gray-300"
                                >
                                    <option value="">Todos</option>
                                    {(selects.employees || []).map((e) => (
                                        <option key={e.id} value={e.id}>{e.name}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">Período (saída) — de</label>
                                <input
                                    type="date"
                                    value={dateFrom}
                                    onChange={(e) => setDateFrom(e.target.value)}
                                    className="w-full text-sm rounded-md border-gray-300"
                                />
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">até (retorno)</label>
                                <input
                                    type="date"
                                    value={dateTo}
                                    onChange={(e) => setDateTo(e.target.value)}
                                    className="w-full text-sm rounded-md border-gray-300"
                                />
                            </div>
                            <div className="flex items-end">
                                <label className="inline-flex items-center text-sm text-gray-700 gap-2">
                                    <input
                                        type="checkbox"
                                        checked={includeTerminal}
                                        onChange={(e) => setIncludeTerminal(e.target.checked)}
                                        className="rounded border-gray-300"
                                    />
                                    Mostrar finalizadas/canceladas
                                </label>
                            </div>
                        </div>
                        <div className="flex justify-end gap-2">
                            <Button type="button" variant="outline" size="sm" onClick={handleClearFilters}>
                                Limpar
                            </Button>
                            <Button type="submit" variant="primary" size="sm">
                                Aplicar filtros
                            </Button>
                        </div>
                    </form>

                    <div className="bg-white shadow-sm rounded-lg overflow-hidden">
                        <DataTable
                            data={expenses}
                            columns={columns}
                            emptyMessage="Nenhuma verba encontrada."
                        />
                    </div>
                </div>
            </div>

            {/* === Modal: Criar === */}
            <TravelExpenseFormModal
                show={modals.create}
                onClose={() => closeModal('create')}
                mode="create"
                selects={selects}
                isStoreScoped={isStoreScoped}
                scopedStoreCode={scopedStoreCode}
                defaultDailyRate={defaultDailyRate}
                canManage={canManage}
            />

            {/* === Modal: Editar === */}
            <TravelExpenseFormModal
                show={modals.edit}
                onClose={() => closeModal('edit')}
                mode="edit"
                expense={detailExpense}
                loading={detailLoading}
                selects={selects}
                isStoreScoped={isStoreScoped}
                scopedStoreCode={scopedStoreCode}
                defaultDailyRate={defaultDailyRate}
                canManage={canManage}
            />

            {/* === Modal: Detalhes === */}
            <TravelExpenseDetailModal
                show={modals.detail}
                onClose={() => closeModal('detail')}
                expense={detailExpense}
                loading={detailLoading}
                onOpenAccountability={() => switchModal('detail', 'accountability')}
                canExport={canExport}
            />

            {/* === Modal: Prestação de Contas === */}
            <AccountabilityModal
                show={modals.accountability}
                onClose={() => closeModal('accountability')}
                expense={detailExpense}
                loading={detailLoading}
                typeExpenses={selects.typeExpenses ?? []}
                onReload={() => detailExpense && loadDetail({ ulid: detailExpense.ulid })}
                canManageAccountability={canManageAccountability || canManage}
                canApprove={canApprove}
            />

            {/* === Modal: Transição (aprovar/rejeitar/cancelar) === */}
            <TransitionModal
                show={modals.transition}
                onClose={() => { closeModal('transition'); setTransitionConfig(null); }}
                config={transitionConfig}
            />

            {/* === Modal: Excluir === */}
            <DeleteWithReasonModal
                show={modals.delete}
                onClose={() => closeModal('delete')}
                onConfirm={handleDelete}
                itemName={selected ? `${selected.origin} → ${selected.destination}` : ''}
                details={selected ? [
                    { label: 'Beneficiado', value: selected.employee?.name ?? '—' },
                    { label: 'Período', value: `${formatDate(selected.initial_date)} a ${formatDate(selected.end_date)}` },
                    { label: 'Valor', value: formatCurrency(selected.value) },
                    { label: 'Status', value: selected.status_label },
                ] : []}
                processing={deleteProcessing}
            />

            <ConfirmDialogComponent />
        </>
    );
}

// ------------------------------------------------------------------
// Helpers locais
// ------------------------------------------------------------------
function formatDate(iso) {
    if (!iso) return '—';
    const d = new Date(`${iso}T00:00:00`);
    return d.toLocaleDateString('pt-BR');
}

function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value || 0);
}
