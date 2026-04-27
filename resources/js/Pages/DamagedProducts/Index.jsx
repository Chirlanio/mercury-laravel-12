import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'react-toastify';
import {
    ExclamationTriangleIcon,
    LinkIcon,
    CheckCircleIcon,
    ClockIcon,
    TrashIcon,
    XMarkIcon,
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
import DamagedProductFormModal from './components/DamagedProductFormModal';
import DamagedProductDetailModal from './components/DamagedProductDetailModal';
import MatchesModal from './components/MatchesModal';

const STATUS_VARIANT = {
    open: 'gray',
    matched: 'info',
    transfer_requested: 'warning',
    resolved: 'success',
    cancelled: 'danger',
};

const fmtDate = (iso) => (iso ? new Date(iso).toLocaleDateString('pt-BR') : '—');

export default function Index({
    items,
    filters = {},
    statistics = {},
    statusOptions = {},
    isStoreScoped = false,
    scopedStoreId = null,
    scopedStoreCode = null,
    permissions: pagePermissions = {},
    selects = {},
}) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission(PERMISSIONS.CREATE_DAMAGED_PRODUCTS);
    const canEdit = hasPermission(PERMISSIONS.EDIT_DAMAGED_PRODUCTS);
    const canDelete = hasPermission(PERMISSIONS.DELETE_DAMAGED_PRODUCTS);
    const canRunMatching = pagePermissions.run_matching || hasPermission(PERMISSIONS.RUN_DAMAGED_PRODUCT_MATCHING);
    const canApproveMatches = pagePermissions.approve_matches || hasPermission(PERMISSIONS.APPROVE_DAMAGED_PRODUCT_MATCHES);
    const canExport = pagePermissions.export || hasPermission(PERMISSIONS.EXPORT_DAMAGED_PRODUCTS);
    const canManage = pagePermissions.manage || hasPermission(PERMISSIONS.MANAGE_DAMAGED_PRODUCTS);

    const { modals, selected, openModal, closeModal } = useModalManager([
        'create', 'edit', 'detail', 'matches', 'delete',
    ]);

    // ------------------------------------------------------------------
    // Filtros (debounced para search; immediate para selects)
    // ------------------------------------------------------------------
    const [filterState, setFilterState] = useState({
        store_id: filters.store_id ?? '',
        status: filters.status ?? '',
        issue_type: filters.issue_type ?? '',
        damage_type_id: filters.damage_type_id ?? '',
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
        router.get(route('damaged-products.index'), params, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
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

    const handleSelectChange = (key, value) => {
        const next = { ...filterState, [key]: value };
        setFilterState(next);
        applyFilters(next);
    };

    // ------------------------------------------------------------------
    // Reverb realtime — subscribe no canal privado da loja escopada.
    // Vendedor/gerente (sem MANAGE) ouve só a própria loja. Admin com
    // visão global não subscribe (usaria refresh manual). Padrão paridade
    // Helpdesk: try/catch silencioso se Echo offline + debounce 500ms pra
    // coalescer bursts de eventos.
    // ------------------------------------------------------------------
    const reloadTimerRef = useRef(null);
    useEffect(() => {
        if (!scopedStoreId || typeof window === 'undefined' || !window.Echo) {
            return;
        }

        const scheduleReload = () => {
            if (reloadTimerRef.current) clearTimeout(reloadTimerRef.current);
            reloadTimerRef.current = setTimeout(() => {
                router.reload({ only: ['items', 'statistics'] });
            }, 500);
        };

        const channelName = `damaged-products.store.${scopedStoreId}`;
        let channel;

        try {
            channel = window.Echo.private(channelName);

            channel
                .listen('.damaged-match.found', (payload) => {
                    const ref = payload?.product_reference ?? '';
                    const dest = payload?.destination_store_name ?? payload?.destination_store_code ?? '';
                    toast.info(
                        `Novo match sugerido${ref ? ` para ${ref}` : ''}${dest ? ` (destino: ${dest})` : ''}`,
                        { autoClose: 4000 }
                    );
                    scheduleReload();
                })
                .listen('.damaged-match.accepted', (payload) => {
                    toast.success(
                        `Match aceito${payload?.actor_name ? ` por ${payload.actor_name}` : ''} — transferência criada.`,
                        { autoClose: 4000 }
                    );
                    scheduleReload();
                })
                .listen('.damaged-match.rejected', (payload) => {
                    toast.warn(
                        `Match rejeitado${payload?.actor_name ? ` por ${payload.actor_name}` : ''}.`,
                        { autoClose: 4000 }
                    );
                    scheduleReload();
                });
        } catch (e) {
            // Echo não configurado ou auth falhou — silent no-op.
        }

        return () => {
            if (reloadTimerRef.current) clearTimeout(reloadTimerRef.current);
            try {
                if (channel) {
                    channel.stopListening('.damaged-match.found');
                    channel.stopListening('.damaged-match.accepted');
                    channel.stopListening('.damaged-match.rejected');
                }
                window.Echo.leave(`private-${channelName}`);
            } catch (e) {
                // noop
            }
        };
    }, [scopedStoreId]);

    // ------------------------------------------------------------------
    // Run matching manual (POST + reload)
    // ------------------------------------------------------------------
    const [runningMatching, setRunningMatching] = useState(false);
    const runMatching = async () => {
        setRunningMatching(true);
        try {
            const res = await window.axios.post(route('damaged-products.run-matching'));
            alert(res.data?.message || 'Matching concluído.');
            router.reload({ only: ['items', 'statistics'] });
        } catch {
            alert('Erro ao rodar matching.');
        } finally {
            setRunningMatching(false);
        }
    };

    // ------------------------------------------------------------------
    // Detecta se há filtros ativos (controla "Limpar filtros" + emptyMessage)
    // ------------------------------------------------------------------
    const hasActiveFilters = useMemo(() => {
        return Object.entries(filters).some(([k, v]) => {
            if (k === 'include_terminal') return v === true;
            return v !== '' && v !== null && v !== undefined;
        });
    }, [filters]);

    const clearFilters = () => {
        const reset = {
            store_id: '',
            status: '',
            issue_type: '',
            damage_type_id: '',
            search: '',
            date_from: '',
            date_to: '',
            include_terminal: false,
        };
        setFilterState(reset);
        router.get(route('damaged-products.index'), {}, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    // ------------------------------------------------------------------
    // Statistics cards (clicáveis — filtram por status quando aplicável)
    // ------------------------------------------------------------------
    const setStatusFilter = (status) => {
        const next = { ...filterState, status, include_terminal: status ? false : filterState.include_terminal };
        setFilterState(next);
        applyFilters(next);
    };

    const statisticsCards = [
        {
            label: 'Total',
            value: statistics.total ?? 0,
            format: 'number',
            icon: ExclamationTriangleIcon,
            color: 'gray',
            onClick: () => setStatusFilter(''),
            active: !filterState.status,
        },
        {
            label: 'Em aberto',
            value: statistics.open ?? 0,
            format: 'number',
            icon: ClockIcon,
            color: 'gray',
            onClick: () => setStatusFilter('open'),
            active: filterState.status === 'open',
        },
        {
            label: 'Match encontrado',
            value: statistics.matched ?? 0,
            format: 'number',
            icon: LinkIcon,
            color: 'blue',
            onClick: () => setStatusFilter('matched'),
            active: filterState.status === 'matched',
        },
        {
            label: 'Aguardando transferência',
            value: statistics.transfer_requested ?? 0,
            format: 'number',
            icon: ClockIcon,
            color: 'amber',
            onClick: () => setStatusFilter('transfer_requested'),
            active: filterState.status === 'transfer_requested',
        },
        {
            label: 'Resolvidos',
            value: statistics.resolved ?? 0,
            format: 'number',
            sub: `Taxa: ${statistics.resolution_rate ?? 0}%`,
            icon: CheckCircleIcon,
            color: 'green',
            onClick: () => {
                const next = { ...filterState, status: 'resolved', include_terminal: true };
                setFilterState(next);
                applyFilters(next);
            },
            active: filterState.status === 'resolved',
        },
    ];

    // ------------------------------------------------------------------
    // Table columns
    // ------------------------------------------------------------------
    const columns = [
        {
            key: 'store',
            label: 'Loja',
            render: (row) => row.store ? (
                <div>
                    <div className="font-mono text-xs">{row.store.code}</div>
                    <div className="text-xs text-gray-500">{row.store.name}</div>
                </div>
            ) : '—',
        },
        {
            key: 'product_reference',
            label: 'Produto',
            render: (row) => (
                <div>
                    <div className="font-mono font-semibold text-sm">{row.product_reference}</div>
                    {row.product_name && <div className="text-xs text-gray-600 truncate max-w-xs">{row.product_name}</div>}
                </div>
            ),
        },
        {
            key: 'issue_type',
            label: 'Tipo',
            render: (row) => (
                <div className="flex flex-wrap gap-1">
                    {row.is_mismatched && <StatusBadge variant="warning">Par trocado</StatusBadge>}
                    {row.is_damaged && <StatusBadge variant="danger">Avariado</StatusBadge>}
                </div>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            render: (row) => (
                <StatusBadge variant={STATUS_VARIANT[row.status] ?? 'gray'}>
                    {row.status_label}
                </StatusBadge>
            ),
        },
        {
            key: 'pending_matches_count',
            label: 'Matches',
            render: (row) =>
                row.pending_matches_count > 0
                    ? <StatusBadge variant="purple">{row.pending_matches_count}</StatusBadge>
                    : <span className="text-gray-400">—</span>,
        },
        {
            key: 'created_at',
            label: 'Cadastrado em',
            render: (row) => (
                <div className="text-xs">
                    <div>{fmtDate(row.created_at)}</div>
                    {row.created_by && <div className="text-gray-500">{row.created_by}</div>}
                </div>
            ),
        },
        {
            key: 'actions',
            label: 'Ações',
            render: (row) => (
                <ActionButtons
                    onView={() => openModal('detail', row)}
                    onEdit={canEdit && !['resolved', 'cancelled'].includes(row.status) ? () => openModal('edit', row) : null}
                    onDelete={canDelete && !['resolved', 'cancelled'].includes(row.status) ? () => openModal('delete', row) : null}
                >
                    {row.pending_matches_count > 0 && (
                        <ActionButtons.Custom
                            icon={LinkIcon}
                            label="Matches"
                            onClick={() => openModal('matches', row)}
                            color="purple"
                        />
                    )}
                </ActionButtons>
            ),
        },
    ];

    // ------------------------------------------------------------------
    // Delete (cancel) handler
    // ------------------------------------------------------------------
    const [cancelReason, setCancelReason] = useState('');
    const [deleting, setDeleting] = useState(false);

    const submitDelete = () => {
        if (!selected || cancelReason.trim().length < 5) return;
        setDeleting(true);
        router.delete(route('damaged-products.destroy', selected.ulid), {
            data: { reason: cancelReason },
            preserveScroll: true,
            onSuccess: () => {
                closeModal('delete');
                setCancelReason('');
            },
            onFinish: () => setDeleting(false),
        });
    };

    return (
        <>
            <Head title="Produtos Avariados" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <PageHeader
                        title="Produtos Avariados"
                        subtitle="Pares trocados e avarias entre lojas com matching automático"
                        icon={ExclamationTriangleIcon}
                        scopeBadge={isStoreScoped && scopedStoreCode ? `escopo: loja ${scopedStoreCode}` : null}
                        actions={[
                            {
                                type: 'dashboard',
                                href: route('damaged-products.dashboard'),
                            },
                            {
                                type: 'sync',
                                label: 'Rodar matching',
                                onClick: runMatching,
                                visible: canRunMatching,
                                loading: runningMatching,
                            },
                            {
                                type: 'download',
                                download: route('damaged-products.export', filters),
                                visible: canExport,
                            },
                            {
                                type: 'create',
                                onClick: () => openModal('create'),
                                visible: canCreate,
                            },
                        ]}
                    />

                    <StatisticsGrid cards={statisticsCards} cols={5} className="mb-6" />

                    {/* Filtros */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
                            <div className="lg:col-span-2">
                                <label className="block text-xs font-medium text-gray-700 mb-1">Buscar</label>
                                <input
                                    type="text"
                                    value={filterState.search}
                                    onChange={(e) => setFilterState({ ...filterState, search: e.target.value })}
                                    placeholder="Referência, descrição ou cor..."
                                    className="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"
                                />
                            </div>

                            {!isStoreScoped && (
                                <div>
                                    <label className="block text-xs font-medium text-gray-700 mb-1">Loja</label>
                                    <select
                                        value={filterState.store_id}
                                        onChange={(e) => handleSelectChange('store_id', e.target.value)}
                                        className="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"
                                    >
                                        <option value="">Todas</option>
                                        {selects.stores?.map((s) => (
                                            <option key={s.id} value={s.id}>{s.code} — {s.name}</option>
                                        ))}
                                    </select>
                                </div>
                            )}

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
                                <label className="block text-xs font-medium text-gray-700 mb-1">Tipo de problema</label>
                                <select
                                    value={filterState.issue_type}
                                    onChange={(e) => handleSelectChange('issue_type', e.target.value)}
                                    className="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"
                                >
                                    <option value="">Todos</option>
                                    <option value="mismatched">Par trocado</option>
                                    <option value="damaged">Avariado</option>
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">Tipo de dano</label>
                                <select
                                    value={filterState.damage_type_id}
                                    onChange={(e) => handleSelectChange('damage_type_id', e.target.value)}
                                    className="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"
                                >
                                    <option value="">Todos</option>
                                    {selects.damageTypes?.map((dt) => (
                                        <option key={dt.id} value={dt.id}>{dt.name}</option>
                                    ))}
                                </select>
                            </div>

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
                                    Incluir resolvidos/cancelados
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

                    {/* Tabela */}
                    <DataTable
                        data={items}
                        columns={columns}
                        searchable={false}
                        emptyMessage={
                            hasActiveFilters
                                ? 'Nenhum produto avariado encontrado com os filtros atuais.'
                                : 'Nenhum produto avariado cadastrado ainda.'
                        }
                    />
                </div>
            </div>

            {/* Modais */}
            <DamagedProductFormModal
                show={modals.create}
                onClose={() => closeModal('create')}
                onSuccess={() => closeModal('create')}
                mode="create"
                selects={selects}
                isStoreScoped={isStoreScoped}
                scopedStoreId={scopedStoreId}
                canManage={canManage}
            />

            <DamagedProductFormModal
                show={modals.edit}
                onClose={() => closeModal('edit')}
                onSuccess={() => closeModal('edit')}
                mode="edit"
                initial={selected}
                selects={selects}
                isStoreScoped={isStoreScoped}
                scopedStoreId={scopedStoreId}
                canManage={canManage}
            />

            <DamagedProductDetailModal
                show={modals.detail}
                onClose={() => closeModal('detail')}
                ulid={selected?.ulid}
            />

            <MatchesModal
                show={modals.matches}
                onClose={() => closeModal('matches')}
                item={selected}
                canApprove={canApproveMatches}
            />

            <StandardModal
                show={modals.delete}
                onClose={() => closeModal('delete')}
                title="Cancelar produto avariado"
                subtitle={selected?.product_reference}
                headerColor="bg-red-600"
                headerIcon={<TrashIcon className="h-5 w-5" />}
                maxWidth="md"
                footer={
                    <StandardModal.Footer
                        onCancel={() => closeModal('delete')}
                        onSubmit={submitDelete}
                        submitLabel="Cancelar registro"
                        submitVariant="danger"
                        processing={deleting}
                        submitDisabled={cancelReason.trim().length < 5}
                    />
                }
            >
                {selected && (
                    <>
                        <StandardModal.Section title="Detalhes do registro">
                            <div className="grid grid-cols-2 gap-3">
                                <StandardModal.Field label="Loja" value={selected.store?.code} />
                                <StandardModal.Field label="Status atual" value={selected.status_label} />
                            </div>
                        </StandardModal.Section>
                        <StandardModal.Highlight>
                            O cancelamento é definitivo e expira automaticamente todos os matches pendentes vinculados.
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
        </>
    );
}
