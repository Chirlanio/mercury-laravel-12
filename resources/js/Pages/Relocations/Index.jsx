import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'react-toastify';
import {
    RectangleStackIcon,
    ClockIcon,
    CheckCircleIcon,
    TruckIcon,
    PaperAirplaneIcon,
    ExclamationTriangleIcon,
    XMarkIcon,
    PlayIcon,
    HandThumbUpIcon,
    HandThumbDownIcon,
    InboxArrowDownIcon,
    TrashIcon,
} from '@heroicons/react/24/outline';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import PageHeader from '@/Components/Shared/PageHeader';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StandardModal from '@/Components/StandardModal';
import DataTable from '@/Components/DataTable';
import ActionButtons from '@/Components/ActionButtons';
import Button from '@/Components/Button';
import CreateModal from './Components/CreateModal';
import DetailModal from './Components/DetailModal';
import ReceiveModal from './Components/ReceiveModal';
import ImportModal from './Components/ImportModal';

const STATUS_VARIANT = {
    draft: 'gray',
    requested: 'warning',
    approved: 'info',
    in_separation: 'purple',
    in_transit: 'indigo',
    completed: 'success',
    partial: 'warning',
    rejected: 'danger',
    cancelled: 'danger',
};

const PRIORITY_VARIANT = {
    low: 'gray',
    normal: 'info',
    high: 'warning',
    urgent: 'danger',
};

const fmtDate = (iso) => (iso ? new Date(iso).toLocaleDateString('pt-BR') : '—');

export default function Index({
    relocations,
    filters = {},
    statistics = {},
    statusOptions = {},
    priorityOptions = {},
    priorityDeadlines = {},
    reasonOptions = {},
    isStoreScoped = false,
    scopedStoreId = null,
    permissions: pagePerms = {},
    selects = {},
}) {
    const { hasPermission } = usePermissions();
    const canCreate = pagePerms.create ?? hasPermission(PERMISSIONS.CREATE_RELOCATIONS);
    const canEdit = pagePerms.edit ?? hasPermission(PERMISSIONS.EDIT_RELOCATIONS);
    const canDelete = pagePerms.delete ?? hasPermission(PERMISSIONS.DELETE_RELOCATIONS);
    const canApprove = pagePerms.approve ?? hasPermission(PERMISSIONS.APPROVE_RELOCATIONS);
    const canSeparate = pagePerms.separate ?? hasPermission(PERMISSIONS.SEPARATE_RELOCATIONS);
    const canReceive = pagePerms.receive ?? hasPermission(PERMISSIONS.RECEIVE_RELOCATIONS);

    const canExport = pagePerms.export ?? hasPermission(PERMISSIONS.EXPORT_RELOCATIONS);
    const canImport = pagePerms.import ?? hasPermission(PERMISSIONS.IMPORT_RELOCATIONS);

    const { modals, selected, openModal, closeModal } = useModalManager([
        'create', 'detail', 'receive', 'cancel', 'reject', 'import',
    ]);

    // ------------------------------------------------------------------
    // Bulk approve — seleção de remanejos REQUESTED pra aprovação em lote.
    // Só ativo pra users com APPROVE_RELOCATIONS. Checkbox aparece só na
    // linhas com status='requested'.
    // ------------------------------------------------------------------
    const [selectedUlids, setSelectedUlids] = useState([]);
    const [bulkApproving, setBulkApproving] = useState(false);

    const toggleSelect = (ulid) => {
        setSelectedUlids((prev) => prev.includes(ulid)
            ? prev.filter((u) => u !== ulid)
            : [...prev, ulid]);
    };

    const clearSelection = () => setSelectedUlids([]);

    const handleBulkApprove = async () => {
        if (selectedUlids.length === 0) return;
        if (!confirm(`Aprovar ${selectedUlids.length} remanejo(s) selecionado(s)?`)) return;

        setBulkApproving(true);
        try {
            const { data } = await window.axios.post(
                route('relocations.bulk-approve'),
                { ulids: selectedUlids },
            );
            const { approved_count, failed = [], missing = [] } = data;

            if (approved_count > 0 && failed.length === 0 && missing.length === 0) {
                toast.success(`${approved_count} remanejo(s) aprovado(s).`);
            } else if (approved_count > 0) {
                toast.warning(
                    `${approved_count} aprovado(s), ${failed.length + missing.length} pulado(s).`,
                    { autoClose: 6000 },
                );
            } else {
                toast.error('Nenhum remanejo foi aprovado. Verifique os filtros e tente novamente.');
            }

            setSelectedUlids([]);
            router.reload({ only: ['relocations', 'statistics'] });
        } catch (e) {
            toast.error(e.response?.data?.message ?? 'Falha ao aprovar em lote.');
        } finally {
            setBulkApproving(false);
        }
    };

    // ------------------------------------------------------------------
    // Filtros (search debounced; selects/dates immediate)
    // ------------------------------------------------------------------
    const [filterState, setFilterState] = useState({
        origin_store_id: filters.origin_store_id ?? '',
        destination_store_id: filters.destination_store_id ?? '',
        status: filters.status ?? '',
        relocation_type_id: filters.relocation_type_id ?? '',
        priority: filters.priority ?? '',
        search: filters.search ?? '',
        date_from: filters.date_from ?? '',
        date_to: filters.date_to ?? '',
        include_terminal: filters.include_terminal ?? false,
    });

    const searchDebounceRef = useRef(null);
    const isFirstRender = useRef(true);

    const applyFilters = (overrides = {}) => {
        const merged = { ...filterState, ...overrides };
        const params = Object.fromEntries(
            Object.entries(merged).filter(([, v]) => v !== '' && v !== null && v !== undefined && v !== false)
        );
        // partial reload: re-renderiza só a tabela + cards + filtros, preserva
        // statusOptions, permissions, selects e demais props estáveis (evita
        // o flash visual de re-mount completo da página).
        router.get(route('relocations.index'), params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['relocations', 'statistics', 'filters'],
        });
    };

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }
        if (searchDebounceRef.current) clearTimeout(searchDebounceRef.current);
        searchDebounceRef.current = setTimeout(() => applyFilters(), 400);
        return () => clearTimeout(searchDebounceRef.current);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [filterState.search]);

    // ------------------------------------------------------------------
    // Reverb realtime — subscribe no canal privado da loja escopada.
    // Vendedor/gerente (sem MANAGE/APPROVE) ouve só a própria loja.
    // Admins/planejamento têm visão global e fazem refresh manual (não
    // subscribe pra evitar enxurrada de toasts cross-tenant).
    // Mesmo padrão de DamagedProducts: try/catch silencioso se Echo
    // offline + debounce 500ms pra coalescer bursts de eventos.
    // ------------------------------------------------------------------
    const reloadTimerRef = useRef(null);
    useEffect(() => {
        if (!scopedStoreId || typeof window === 'undefined' || !window.Echo) {
            return;
        }

        const scheduleReload = () => {
            if (reloadTimerRef.current) clearTimeout(reloadTimerRef.current);
            reloadTimerRef.current = setTimeout(() => {
                router.reload({ only: ['relocations', 'statistics'] });
            }, 500);
        };

        const channelName = `relocations.store.${scopedStoreId}`;
        let channel;

        try {
            channel = window.Echo.private(channelName);

            channel.listen('.relocation.status-changed', (payload) => {
                const isOrigin = payload.origin_store_id === scopedStoreId;
                const isDestination = payload.destination_store_id === scopedStoreId;
                const otherCode = isOrigin
                    ? (payload.destination_store_code ?? '—')
                    : (payload.origin_store_code ?? '—');
                const arrow = isOrigin ? '→' : '←';
                const label = payload.to_status_label ?? payload.to_status;

                // Toasts contextualizados pelas transições mais relevantes.
                switch (payload.to_status) {
                    case 'requested':
                        if (isOrigin) toast.info(`Novo remanejo ${arrow} ${otherCode} solicitado.`);
                        break;
                    case 'approved':
                        if (isOrigin) toast.success(`Remanejo ${arrow} ${otherCode} aprovado pelo planejamento.`);
                        break;
                    case 'in_separation':
                        if (isOrigin) toast.info(`Separação iniciada — remanejo ${arrow} ${otherCode}.`);
                        break;
                    case 'in_transit':
                        if (isDestination) {
                            toast.success(
                                `Remanejo despachado de ${otherCode} ${payload.invoice_number ? `(NF ${payload.invoice_number})` : ''} — aguardando recebimento.`,
                                { autoClose: 6000 },
                            );
                        }
                        break;
                    case 'completed':
                    case 'partial':
                        if (isOrigin) {
                            toast.success(`Remanejo ${arrow} ${otherCode} ${payload.to_status === 'partial' ? 'recebido parcialmente' : 'recebido'}.`);
                        }
                        break;
                    case 'rejected':
                        toast.warn(`Remanejo ${arrow} ${otherCode} rejeitado.`);
                        break;
                    case 'cancelled':
                        toast.warn(`Remanejo ${arrow} ${otherCode} cancelado.`);
                        break;
                    default:
                        toast.info(`Remanejo ${arrow} ${otherCode}: ${label}`);
                }

                scheduleReload();
            });
        } catch (e) {
            // Echo não configurado ou auth falhou — silent no-op.
        }

        return () => {
            if (reloadTimerRef.current) clearTimeout(reloadTimerRef.current);
            try {
                if (channel) channel.stopListening('.relocation.status-changed');
                window.Echo.leave(`private-${channelName}`);
            } catch (e) {
                // noop
            }
        };
    }, [scopedStoreId]);

    const handleSelectChange = (key, value) => {
        const next = { ...filterState, [key]: value };
        setFilterState(next);
        applyFilters(next);
    };

    const hasActiveFilters = useMemo(() => {
        return Object.entries(filters).some(([k, v]) => {
            if (k === 'include_terminal') return v === true;
            return v !== '' && v !== null && v !== undefined;
        });
    }, [filters]);

    const clearFilters = () => {
        const reset = {
            origin_store_id: '',
            destination_store_id: '',
            status: '',
            relocation_type_id: '',
            priority: '',
            search: '',
            date_from: '',
            date_to: '',
            include_terminal: false,
        };
        setFilterState(reset);
        router.get(route('relocations.index'), {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['relocations', 'statistics', 'filters'],
        });
    };

    const setStatusFilter = (status) => {
        const next = {
            ...filterState,
            status,
            include_terminal: ['completed', 'partial', 'rejected', 'cancelled'].includes(status)
                ? true
                : filterState.include_terminal,
        };
        setFilterState(next);
        applyFilters(next);
    };

    // ------------------------------------------------------------------
    // Statistics cards (clicáveis)
    // ------------------------------------------------------------------
    const statisticsCards = [
        {
            label: 'Total',
            value: statistics.total ?? 0,
            format: 'number',
            icon: RectangleStackIcon,
            color: 'gray',
            onClick: () => setStatusFilter(''),
            active: !filterState.status,
        },
        {
            label: 'Solicitados',
            value: statistics.requested ?? 0,
            format: 'number',
            icon: ClockIcon,
            color: 'amber',
            onClick: () => setStatusFilter('requested'),
            active: filterState.status === 'requested',
        },
        {
            label: 'Em separação',
            value: statistics.in_separation ?? 0,
            format: 'number',
            icon: PlayIcon,
            color: 'purple',
            onClick: () => setStatusFilter('in_separation'),
            active: filterState.status === 'in_separation',
        },
        {
            label: 'Em trânsito',
            value: statistics.in_transit ?? 0,
            format: 'number',
            icon: TruckIcon,
            color: 'indigo',
            onClick: () => setStatusFilter('in_transit'),
            active: filterState.status === 'in_transit',
        },
        {
            label: 'Concluídos',
            value: (statistics.completed ?? 0) + (statistics.partial ?? 0),
            format: 'number',
            sub: (statistics.partial ?? 0) > 0 ? `${statistics.partial} parcial(is)` : null,
            icon: CheckCircleIcon,
            color: 'green',
            onClick: () => setStatusFilter('completed'),
            active: filterState.status === 'completed',
        },
        {
            label: 'Atrasados',
            value: statistics.overdue ?? 0,
            format: 'number',
            icon: ExclamationTriangleIcon,
            color: 'red',
        },
    ];

    // ------------------------------------------------------------------
    // Quick transitions inline na linha (ActionButtons.Custom)
    // ------------------------------------------------------------------
    const transitionTo = (relocation, toStatus, payload = {}, note = null) => {
        router.post(route('relocations.transition', relocation.ulid), {
            to_status: toStatus,
            note,
            ...payload,
        }, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    // ------------------------------------------------------------------
    // Cancel modal (exige motivo)
    // ------------------------------------------------------------------
    const [cancelReason, setCancelReason] = useState('');
    const [rejectReason, setRejectReason] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const submitCancel = () => {
        if (!selected || cancelReason.trim().length < 5) return;
        setSubmitting(true);
        router.post(route('relocations.transition', selected.ulid), {
            to_status: 'cancelled',
            note: cancelReason,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                closeModal('cancel');
                setCancelReason('');
            },
            onFinish: () => setSubmitting(false),
        });
    };

    const submitReject = () => {
        if (!selected || rejectReason.trim().length < 5) return;
        setSubmitting(true);
        router.post(route('relocations.transition', selected.ulid), {
            to_status: 'rejected',
            note: rejectReason,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                closeModal('reject');
                setRejectReason('');
            },
            onFinish: () => setSubmitting(false),
        });
    };

    // ------------------------------------------------------------------
    // Table columns
    // ------------------------------------------------------------------
    const columns = [
        ...(canApprove ? [{
            key: 'select',
            label: '',
            render: (row) => row.status === 'requested' ? (
                <input
                    type="checkbox"
                    checked={selectedUlids.includes(row.ulid)}
                    onChange={(e) => { e.stopPropagation(); toggleSelect(row.ulid); }}
                    onClick={(e) => e.stopPropagation()}
                    className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                />
            ) : null,
        }] : []),
        {
            key: 'title',
            label: 'Identificação',
            render: (row) => (
                <div className="min-w-0">
                    <div className="font-semibold text-sm truncate max-w-xs">
                        {row.title || `Remanejo #${row.id}`}
                    </div>
                    <div className="text-xs text-gray-500">
                        {row.type_name} · {row.items_count} {row.items_count === 1 ? 'item' : 'itens'}
                    </div>
                </div>
            ),
        },
        {
            key: 'origin',
            label: 'Origem',
            render: (row) => row.origin_store ? (
                <div>
                    <div className="font-mono text-xs">{row.origin_store.code}</div>
                    <div className="text-xs text-gray-600 truncate max-w-[160px]">{row.origin_store.name}</div>
                </div>
            ) : '—',
        },
        {
            key: 'destination',
            label: 'Destino',
            render: (row) => row.destination_store ? (
                <div>
                    <div className="font-mono text-xs">{row.destination_store.code}</div>
                    <div className="text-xs text-gray-600 truncate max-w-[160px]">{row.destination_store.name}</div>
                </div>
            ) : '—',
        },
        {
            key: 'priority',
            label: 'Prioridade',
            render: (row) => (
                <StatusBadge variant={PRIORITY_VARIANT[row.priority] ?? 'gray'}>
                    {row.priority_label}
                </StatusBadge>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            render: (row) => (
                <div className="space-y-1">
                    <StatusBadge variant={STATUS_VARIANT[row.status] ?? 'gray'}>
                        {row.status_label}
                    </StatusBadge>
                    {row.invoice_number && (
                        <div className="text-xs text-gray-500 font-mono">NF {row.invoice_number}</div>
                    )}
                    {(row.cigam_dispatched_at || row.cigam_received_at) && (
                        <div className="text-xs flex gap-1.5">
                            <span className={row.cigam_dispatched_at ? 'text-green-600' : 'text-gray-400'} title="Saída CIGAM (origem)">
                                ↗ {row.cigam_dispatched_at ? '✓' : '–'}
                            </span>
                            <span className={row.cigam_received_at ? 'text-green-600' : 'text-gray-400'} title="Entrada CIGAM (destino)">
                                ↘ {row.cigam_received_at ? '✓' : '–'}
                            </span>
                        </div>
                    )}
                </div>
            ),
        },
        {
            key: 'created_at',
            label: 'Criado em',
            render: (row) => (
                <div className="text-xs">
                    <div>{fmtDate(row.created_at)}</div>
                    {row.created_by_name && <div className="text-gray-500 truncate max-w-[140px]">{row.created_by_name}</div>}
                </div>
            ),
        },
        {
            key: 'actions',
            label: 'Ações',
            render: (row) => (
                <ActionButtons
                    onView={() => openModal('detail', row)}
                    onDelete={
                        canDelete && row.is_pre_transit
                            ? () => openModal('cancel', row)
                            : null
                    }
                >
                    {/* Aprovar / Rejeitar (a partir de requested) */}
                    {row.status === 'requested' && canApprove && (
                        <>
                            <ActionButtons.Custom
                                icon={HandThumbUpIcon}
                                title="Aprovar"
                                variant="success"
                                onClick={() => transitionTo(row, 'approved')}
                            />
                            <ActionButtons.Custom
                                icon={HandThumbDownIcon}
                                title="Rejeitar"
                                variant="danger"
                                onClick={() => openModal('reject', row)}
                            />
                        </>
                    )}

                    {/* Solicitar (draft → requested) */}
                    {row.status === 'draft' && canEdit && (
                        <ActionButtons.Custom
                            icon={PaperAirplaneIcon}
                            title="Solicitar"
                            variant="warning"
                            onClick={() => transitionTo(row, 'requested')}
                        />
                    )}

                    {/* Iniciar separação (approved → in_separation) */}
                    {row.status === 'approved' && canSeparate && (
                        <ActionButtons.Custom
                            icon={PlayIcon}
                            title="Iniciar separação"
                            variant="info"
                            onClick={() => transitionTo(row, 'in_separation')}
                        />
                    )}

                    {/* Receber (in_transit → completed/partial) */}
                    {row.status === 'in_transit' && canReceive && (
                        <ActionButtons.Custom
                            icon={InboxArrowDownIcon}
                            title="Receber"
                            variant="success"
                            onClick={() => openModal('receive', row)}
                        />
                    )}
                </ActionButtons>
            ),
        },
    ];

    return (
        <>
            <Head title="Remanejos" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <PageHeader
                        title="Remanejos"
                        subtitle="Solicitações de transferência de produtos entre lojas"
                        icon={RectangleStackIcon}
                        scopeBadge={isStoreScoped ? 'escopo: sua loja (origem ou destino)' : null}
                        actions={[
                            {
                                type: 'dashboard',
                                href: route('relocations.dashboard'),
                            },
                            {
                                type: 'download',
                                download: route('relocations.export', filters),
                                visible: canExport,
                            },
                            {
                                type: 'import',
                                onClick: () => openModal('import'),
                                visible: canImport,
                            },
                            {
                                type: 'create',
                                label: 'Novo remanejo',
                                onClick: () => openModal('create'),
                                visible: canCreate,
                            },
                        ]}
                    />

                    <StatisticsGrid cards={statisticsCards} cols={6} className="mb-6" />

                    {/* Filtros */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
                            <div className="lg:col-span-2">
                                <label className="block text-xs font-medium text-gray-700 mb-1">Buscar</label>
                                <input
                                    type="text"
                                    value={filterState.search}
                                    onChange={(e) => setFilterState({ ...filterState, search: e.target.value })}
                                    placeholder="Título, NF ou observação..."
                                    className="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">Status</label>
                                <select
                                    value={filterState.status}
                                    onChange={(e) => handleSelectChange('status', e.target.value)}
                                    className="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"
                                >
                                    <option value="">Todos</option>
                                    {Object.entries(statusOptions).map(([k, v]) => (
                                        <option key={k} value={k}>{v}</option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">Prioridade</label>
                                <select
                                    value={filterState.priority}
                                    onChange={(e) => handleSelectChange('priority', e.target.value)}
                                    className="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"
                                >
                                    <option value="">Todas</option>
                                    {Object.entries(priorityOptions).map(([k, v]) => (
                                        <option key={k} value={k}>{v}</option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">Tipo</label>
                                <select
                                    value={filterState.relocation_type_id}
                                    onChange={(e) => handleSelectChange('relocation_type_id', e.target.value)}
                                    className="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"
                                >
                                    <option value="">Todos</option>
                                    {selects.types?.map((t) => (
                                        <option key={t.id} value={t.id}>{t.name}</option>
                                    ))}
                                </select>
                            </div>

                            {!isStoreScoped && (
                                <>
                                    <div>
                                        <label className="block text-xs font-medium text-gray-700 mb-1">Loja origem</label>
                                        <select
                                            value={filterState.origin_store_id}
                                            onChange={(e) => handleSelectChange('origin_store_id', e.target.value)}
                                            className="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"
                                        >
                                            <option value="">Todas</option>
                                            {selects.stores?.map((s) => (
                                                <option key={s.id} value={s.id}>{s.code} — {s.name}</option>
                                            ))}
                                        </select>
                                    </div>

                                    <div>
                                        <label className="block text-xs font-medium text-gray-700 mb-1">Loja destino</label>
                                        <select
                                            value={filterState.destination_store_id}
                                            onChange={(e) => handleSelectChange('destination_store_id', e.target.value)}
                                            className="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"
                                        >
                                            <option value="">Todas</option>
                                            {selects.stores?.map((s) => (
                                                <option key={s.id} value={s.id}>{s.code} — {s.name}</option>
                                            ))}
                                        </select>
                                    </div>
                                </>
                            )}

                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">De</label>
                                <input
                                    type="date"
                                    value={filterState.date_from}
                                    onChange={(e) => handleSelectChange('date_from', e.target.value)}
                                    className="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"
                                />
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">Até</label>
                                <input
                                    type="date"
                                    value={filterState.date_to}
                                    onChange={(e) => handleSelectChange('date_to', e.target.value)}
                                    className="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"
                                />
                            </div>

                            <div className="flex items-end">
                                <label className="inline-flex items-center gap-2 text-xs text-gray-700">
                                    <input
                                        type="checkbox"
                                        checked={filterState.include_terminal}
                                        onChange={(e) => handleSelectChange('include_terminal', e.target.checked)}
                                        className="rounded border-gray-300"
                                    />
                                    Incluir concluídos / cancelados
                                </label>
                            </div>
                        </div>

                        {hasActiveFilters && (
                            <div className="mt-3 flex justify-end">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    icon={XMarkIcon}
                                    onClick={clearFilters}
                                >
                                    Limpar filtros
                                </Button>
                            </div>
                        )}
                    </div>

                    {canApprove && selectedUlids.length > 0 && (
                        <div className="mb-3 bg-indigo-50 border border-indigo-200 rounded-lg p-3 flex items-center justify-between gap-3">
                            <div className="text-sm text-indigo-900">
                                <strong>{selectedUlids.length}</strong> remanejo(s) selecionado(s) pra aprovação.
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={clearSelection}
                                    disabled={bulkApproving}
                                >
                                    Limpar seleção
                                </Button>
                                <Button
                                    variant="success"
                                    size="sm"
                                    icon={HandThumbUpIcon}
                                    onClick={handleBulkApprove}
                                    disabled={bulkApproving}
                                    loading={bulkApproving}
                                >
                                    Aprovar selecionados ({selectedUlids.length})
                                </Button>
                            </div>
                        </div>
                    )}

                    <DataTable
                        data={relocations}
                        columns={columns}
                        searchable={false}
                        emptyMessage={
                            hasActiveFilters
                                ? 'Nenhum remanejo encontrado com os filtros atuais.'
                                : 'Nenhum remanejo cadastrado ainda. Clique em "Novo remanejo" para começar.'
                        }
                        onNavigate={(url) => router.visit(url, {
                            preserveState: true,
                            preserveScroll: true,
                            only: ['relocations', 'statistics', 'filters'],
                        })}
                    />
                </div>
            </div>

            {/* Modais */}
            <CreateModal
                show={modals.create}
                onClose={() => closeModal('create')}
                selects={selects}
                isStoreScoped={isStoreScoped}
                scopedStoreId={scopedStoreId}
                priorityDeadlines={priorityDeadlines}
            />

            <DetailModal
                show={modals.detail}
                onClose={() => closeModal('detail')}
                ulid={selected?.ulid}
                permissions={pagePerms}
                onTransition={transitionTo}
            />

            <ReceiveModal
                show={modals.receive}
                onClose={() => closeModal('receive')}
                ulid={selected?.ulid}
                reasonOptions={reasonOptions}
            />

            <ImportModal
                show={modals.import}
                onClose={() => closeModal('import')}
            />

            {/* Cancel modal — motivo obrigatório */}
            <StandardModal
                show={modals.cancel}
                onClose={() => closeModal('cancel')}
                title="Cancelar remanejo"
                subtitle={selected?.title || `Remanejo #${selected?.id}`}
                headerColor="bg-red-600"
                headerIcon={<TrashIcon className="h-5 w-5" />}
                maxWidth="md"
                footer={
                    <StandardModal.Footer
                        onCancel={() => closeModal('cancel')}
                        onSubmit={submitCancel}
                        submitLabel="Cancelar remanejo"
                        submitVariant="danger"
                        processing={submitting}
                        submitDisabled={cancelReason.trim().length < 5}
                    />
                }
            >
                {selected && (
                    <>
                        <StandardModal.Highlight>
                            O cancelamento é definitivo. Só é permitido antes do remanejo entrar em trânsito.
                        </StandardModal.Highlight>
                        <StandardModal.Section title="Motivo do cancelamento">
                            <textarea
                                rows={3}
                                value={cancelReason}
                                onChange={(e) => setCancelReason(e.target.value)}
                                className="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"
                                placeholder="Mínimo 5 caracteres..."
                            />
                        </StandardModal.Section>
                    </>
                )}
            </StandardModal>

            {/* Reject modal — motivo obrigatório */}
            <StandardModal
                show={modals.reject}
                onClose={() => closeModal('reject')}
                title="Rejeitar remanejo"
                subtitle={selected?.title || `Remanejo #${selected?.id}`}
                headerColor="bg-red-600"
                headerIcon={<HandThumbDownIcon className="h-5 w-5" />}
                maxWidth="md"
                footer={
                    <StandardModal.Footer
                        onCancel={() => closeModal('reject')}
                        onSubmit={submitReject}
                        submitLabel="Rejeitar"
                        submitVariant="danger"
                        processing={submitting}
                        submitDisabled={rejectReason.trim().length < 5}
                    />
                }
            >
                <StandardModal.Section title="Motivo da rejeição">
                    <p className="text-sm text-gray-600 mb-2">
                        Informe ao solicitante por que o remanejo não foi aprovado.
                    </p>
                    <textarea
                        rows={3}
                        value={rejectReason}
                        onChange={(e) => setRejectReason(e.target.value)}
                        className="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"
                        placeholder="Mínimo 5 caracteres..."
                    />
                </StandardModal.Section>
            </StandardModal>
        </>
    );
}
