import { Head, router, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import {
    ArrowPathRoundedSquareIcon,
    PlusIcon,
    ArrowPathIcon,
    ClockIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    ChartBarIcon,
    TrashIcon,
    TruckIcon,
    DocumentArrowDownIcon,
} from '@heroicons/react/24/outline';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import DataTable from '@/Components/DataTable';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import InvoiceLookupSection from './components/InvoiceLookupSection';
import ItemSelectionWithQuantityTable from './components/ItemSelectionWithQuantityTable';
import ReasonCategorySelector from './components/ReasonCategorySelector';

const BRL = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

const COLOR_MAP = {
    info: 'info',
    warning: 'warning',
    success: 'success',
    danger: 'danger',
    purple: 'purple',
    orange: 'orange',
    indigo: 'indigo',
    gray: 'gray',
};

const requiresRefund = (type) => type === 'estorno' || type === 'credito';

export default function Index({
    returns,
    filters = {},
    statistics = {},
    statusOptions = {},
    statusColors = {},
    statusTransitions = {},
    typeOptions = {},
    reasonCategoryOptions = {},
    reasonCategoryColors = {},
    isStoreScoped = false,
    scopedStoreCode = null,
    selects = {},
}) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission(PERMISSIONS.CREATE_RETURNS);
    const canEdit = hasPermission(PERMISSIONS.EDIT_RETURNS);
    const canApprove = hasPermission(PERMISSIONS.APPROVE_RETURNS);
    const canProcess = hasPermission(PERMISSIONS.PROCESS_RETURNS);
    const canCancel = hasPermission(PERMISSIONS.CANCEL_RETURNS);
    const canDelete = hasPermission(PERMISSIONS.DELETE_RETURNS);
    const canExport = hasPermission(PERMISSIONS.EXPORT_RETURNS);

    const { modals, selected, openModal, closeModal } = useModalManager([
        'create',
        'detail',
        'edit',
        'transition',
    ]);

    // ------------------------------------------------------------------
    // Create form
    // ------------------------------------------------------------------
    const emptyCreate = {
        invoice_number: '',
        movement_date: '',
        customer_name: '',
        employee_id: '',
        type: 'troca',
        reason_category: '',
        return_reason_id: '',
        refund_amount: '',
        reverse_tracking_code: '',
        items: [],
        notes: '',
    };
    const [createForm, setCreateForm] = useState(emptyCreate);
    const [createErrors, setCreateErrors] = useState({});
    const [createProcessing, setCreateProcessing] = useState(false);
    const [createLookup, setCreateLookup] = useState(null);

    // ------------------------------------------------------------------
    // Edit / Transition / Delete
    // ------------------------------------------------------------------
    const [editForm, setEditForm] = useState({});
    const [editErrors, setEditErrors] = useState({});
    const [editProcessing, setEditProcessing] = useState(false);

    const [transitionForm, setTransitionForm] = useState({ to_status: '', note: '' });
    const [transitionErrors, setTransitionErrors] = useState({});
    const [transitionProcessing, setTransitionProcessing] = useState(false);

    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleteReason, setDeleteReason] = useState('');
    const [deleteProcessing, setDeleteProcessing] = useState(false);

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------
    const applyFilter = (key, value) => {
        const url = new URL(window.location);
        if (value) url.searchParams.set(key, value);
        else url.searchParams.delete(key);
        url.searchParams.delete('page');
        router.visit(url.toString(), { preserveState: true, preserveScroll: true });
    };

    const clearFilters = () => router.visit(route('returns.index'));

    const openDetail = (row) => {
        fetch(route('returns.show', row.id), { headers: { Accept: 'application/json' } })
            .then((r) => r.json())
            .then((d) => openModal('detail', d.return));
    };

    const openEdit = (row) => {
        fetch(route('returns.show', row.id), { headers: { Accept: 'application/json' } })
            .then((r) => r.json())
            .then((d) => {
                const r = d.return;
                setEditForm({
                    id: r.id,
                    customer_name: r.customer_name || '',
                    employee_id: r.employee_id || '',
                    reason_category: r.reason_category || '',
                    return_reason_id: r.return_reason_id || '',
                    refund_amount: r.refund_amount || '',
                    reverse_tracking_code: r.reverse_tracking_code || '',
                    notes: r.notes || '',
                    type: r.type,
                });
                setEditErrors({});
                openModal('edit', r);
            });
    };

    const openTransition = (row) => {
        fetch(route('returns.show', row.id), { headers: { Accept: 'application/json' } })
            .then((r) => r.json())
            .then((d) => {
                setTransitionForm({ to_status: '', note: '' });
                setTransitionErrors({});
                openModal('transition', d.return);
            });
    };

    const allowedTargetStatuses = useMemo(() => {
        if (!selected?.status) return [];
        return (statusTransitions[selected.status] || []).map((s) => ({
            value: s,
            label: statusOptions[s] || s,
        }));
    }, [selected?.status, statusTransitions, statusOptions]);

    const handleCreateSubmit = () => {
        setCreateProcessing(true);

        // FormData para suportar upload de arquivos no futuro (Fase 5)
        const fd = new FormData();
        const plain = { ...createForm };
        const items = plain.items || [];
        delete plain.items;

        Object.entries(plain).forEach(([k, v]) => {
            if (v === null || v === undefined || v === '') return;
            fd.append(k, v);
        });

        items.forEach((item, idx) => {
            fd.append(`items[${idx}][movement_id]`, item.movement_id);
            if (item.quantity !== undefined && item.quantity !== '') {
                fd.append(`items[${idx}][quantity]`, item.quantity);
            }
        });

        router.post(route('returns.store'), fd, {
            forceFormData: true,
            onError: (errs) => setCreateErrors(errs),
            onSuccess: () => {
                setCreateForm(emptyCreate);
                setCreateErrors({});
                setCreateLookup(null);
                closeModal('create');
            },
            onFinish: () => setCreateProcessing(false),
        });
    };

    const handleEditSubmit = () => {
        setEditProcessing(true);
        router.put(route('returns.update', editForm.id), editForm, {
            onError: (errs) => setEditErrors(errs),
            onSuccess: () => {
                closeModal('edit');
                setEditErrors({});
            },
            onFinish: () => setEditProcessing(false),
        });
    };

    const handleTransitionSubmit = () => {
        setTransitionProcessing(true);
        router.post(route('returns.transition', selected?.id), transitionForm, {
            onError: (errs) => setTransitionErrors(errs),
            onSuccess: () => {
                closeModal('transition');
                setTransitionErrors({});
            },
            onFinish: () => setTransitionProcessing(false),
        });
    };

    const handleDeleteConfirm = () => {
        if (!deleteTarget) return;
        setDeleteProcessing(true);
        router.delete(route('returns.destroy', deleteTarget.id), {
            data: { deleted_reason: deleteReason },
            onSuccess: () => {
                setDeleteTarget(null);
                setDeleteReason('');
            },
            onFinish: () => setDeleteProcessing(false),
        });
    };

    // ------------------------------------------------------------------
    // Columns
    // ------------------------------------------------------------------
    const columns = [
        {
            field: 'invoice_number',
            label: 'NF/Cupom',
            render: (row) => <span className="font-mono text-xs">{row.invoice_number}</span>,
        },
        {
            field: 'customer_name',
            label: 'Cliente',
            render: (row) => (
                <div>
                    <div className="text-sm font-medium text-gray-900">{row.customer_name}</div>
                    {row.cpf_customer && (
                        <div className="text-xs text-gray-500 font-mono">{row.cpf_customer}</div>
                    )}
                </div>
            ),
        },
        {
            field: 'type_label',
            label: 'Tipo',
            render: (row) => (
                <StatusBadge variant={COLOR_MAP[row.type_color] || 'gray'}>
                    {row.type_label}
                </StatusBadge>
            ),
        },
        {
            field: 'reason_category_label',
            label: 'Motivo',
            render: (row) => (
                <div>
                    <StatusBadge variant={COLOR_MAP[row.reason_category_color] || 'gray'} size="sm">
                        {row.reason_category_label}
                    </StatusBadge>
                    {row.reason_name && (
                        <div className="text-xs text-gray-500 mt-0.5">{row.reason_name}</div>
                    )}
                </div>
            ),
        },
        {
            field: 'amount_items',
            label: 'Valor',
            render: (row) => (
                <div>
                    <div className="text-sm font-semibold text-gray-900">
                        {BRL.format(row.amount_items)}
                    </div>
                    {row.refund_amount !== null && row.refund_amount !== row.amount_items && (
                        <div className="text-xs text-gray-400">
                            reembolso: {BRL.format(row.refund_amount)}
                        </div>
                    )}
                </div>
            ),
        },
        {
            field: 'status_label',
            label: 'Status',
            render: (row) => (
                <StatusBadge variant={COLOR_MAP[row.status_color] || 'gray'}>
                    {row.status_label}
                </StatusBadge>
            ),
        },
        {
            field: 'created_at',
            label: 'Criada',
            render: (row) =>
                row.created_at
                    ? new Date(row.created_at).toLocaleDateString('pt-BR')
                    : '—',
        },
        {
            field: 'actions',
            label: 'Ações',
            render: (row) => (
                <ActionButtons
                    onView={() => openDetail(row)}
                    onEdit={canEdit && !row.is_terminal ? () => openEdit(row) : null}
                    onDelete={
                        canDelete && !row.is_terminal
                            ? () => {
                                  setDeleteTarget(row);
                                  setDeleteReason('');
                              }
                            : null
                    }
                >
                    {(canApprove || canProcess || canCancel) && !row.is_terminal && (
                        <ActionButtons.Custom
                            variant="info"
                            icon={ArrowPathIcon}
                            title="Alterar Status"
                            onClick={() => openTransition(row)}
                        />
                    )}
                </ActionButtons>
            ),
        },
    ];

    const hasActiveFilters =
        filters.status ||
        filters.type ||
        filters.reason_category ||
        filters.date_from ||
        filters.date_to ||
        filters.store_code ||
        filters.search;

    const statisticsCards = [
        {
            label: 'Total (não excluídas)',
            value: statistics.total || 0,
            format: 'number',
            icon: ArrowPathRoundedSquareIcon,
            color: 'indigo',
            active: !filters.status,
            onClick: () => applyFilter('status', ''),
        },
        {
            label: 'Aguardando aprovação',
            value: statistics.pending_approval || 0,
            format: 'number',
            icon: ClockIcon,
            color: 'yellow',
            active: filters.status === 'pending',
            onClick: () =>
                applyFilter('status', filters.status === 'pending' ? '' : 'pending'),
        },
        {
            label: 'Aguardando produto',
            value: statistics.awaiting_product || 0,
            format: 'number',
            icon: TruckIcon,
            color: 'orange',
            active: filters.status === 'awaiting_product',
            onClick: () =>
                applyFilter(
                    'status',
                    filters.status === 'awaiting_product' ? '' : 'awaiting_product'
                ),
        },
        {
            label: 'Em processamento',
            value: statistics.processing || 0,
            format: 'number',
            color: 'purple',
            active: filters.status === 'processing',
            onClick: () =>
                applyFilter('status', filters.status === 'processing' ? '' : 'processing'),
        },
        {
            label: 'Concluídas no mês',
            value: statistics.completed_this_month_amount || 0,
            format: 'currency',
            icon: CheckCircleIcon,
            color: 'green',
        },
        {
            label: 'Canceladas',
            value: statistics.cancelled || 0,
            format: 'number',
            icon: ExclamationTriangleIcon,
            color: 'red',
        },
    ];

    return (
        <>
            <Head title="Devoluções" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="mb-6 flex justify-between items-center">
                        <div>
                            <h1 className="text-3xl font-bold text-gray-900">Devoluções</h1>
                            <p className="mt-1 text-sm text-gray-600">
                                Solicitações de troca, estorno e crédito do e-commerce
                                {isStoreScoped && scopedStoreCode && (
                                    <span className="ml-2 text-xs font-medium text-indigo-600">
                                        (escopo: loja {scopedStoreCode})
                                    </span>
                                )}
                            </p>
                        </div>
                        <div className="flex gap-2">
                            <Link href={route('returns.dashboard')}>
                                <Button variant="secondary" icon={ChartBarIcon}>
                                    Dashboard
                                </Button>
                            </Link>
                            {canCreate && (
                                <Button
                                    variant="primary"
                                    onClick={() => {
                                        setCreateForm(emptyCreate);
                                        setCreateErrors({});
                                        setCreateLookup(null);
                                        openModal('create');
                                    }}
                                    icon={PlusIcon}
                                >
                                    Nova Devolução
                                </Button>
                            )}
                        </div>
                    </div>

                    <StatisticsGrid cards={statisticsCards} cols={6} />

                    {/* Filtros */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                            <div className="md:col-span-2">
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Buscar
                                </label>
                                <input
                                    type="text"
                                    value={filters.search || ''}
                                    onChange={(e) => applyFilter('search', e.target.value)}
                                    placeholder="NF, cliente ou CPF..."
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Tipo
                                </label>
                                <select
                                    value={filters.type || ''}
                                    onChange={(e) => applyFilter('type', e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm"
                                >
                                    <option value="">Todos</option>
                                    {Object.entries(typeOptions).map(([k, v]) => (
                                        <option key={k} value={k}>
                                            {v}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Categoria do motivo
                                </label>
                                <select
                                    value={filters.reason_category || ''}
                                    onChange={(e) => applyFilter('reason_category', e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm"
                                >
                                    <option value="">Todas</option>
                                    {Object.entries(reasonCategoryOptions).map(([k, v]) => (
                                        <option key={k} value={k}>
                                            {v}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Status
                                </label>
                                <select
                                    value={filters.status || ''}
                                    onChange={(e) => applyFilter('status', e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm"
                                >
                                    <option value="">Todos</option>
                                    {Object.entries(statusOptions).map(([k, v]) => (
                                        <option key={k} value={k}>
                                            {v}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                        {hasActiveFilters && (
                            <div className="mt-3 text-right">
                                <button
                                    onClick={clearFilters}
                                    className="text-sm text-indigo-600 hover:underline"
                                >
                                    Limpar filtros
                                </button>
                            </div>
                        )}
                    </div>

                    <DataTable
                        data={returns}
                        columns={columns}
                        emptyMessage="Nenhuma devolução encontrada."
                    />
                </div>
            </div>

            {/* ========================================================== */}
            {/* Modal: Create                                              */}
            {/* ========================================================== */}
            <StandardModal
                show={modals.create}
                onClose={() => closeModal('create')}
                title="Nova Devolução"
                subtitle="Registre uma solicitação de troca, estorno ou crédito"
                headerColor="bg-indigo-600"
                headerIcon={<ArrowPathRoundedSquareIcon className="h-6 w-6" />}
                maxWidth="5xl"
                onSubmit={handleCreateSubmit}
                footer={
                    <StandardModal.Footer
                        onCancel={() => closeModal('create')}
                        onSubmit="submit"
                        submitLabel="Criar Devolução"
                        processing={createProcessing}
                        disabled={!createLookup || createForm.items.length === 0}
                    />
                }
            >
                <StandardModal.Section title="Venda de origem">
                    <InvoiceLookupSection
                        value={createForm.invoice_number}
                        onChange={(v) =>
                            setCreateForm({
                                ...createForm,
                                invoice_number: v,
                                movement_date: '',
                                items: [],
                            })
                        }
                        movementDate={createForm.movement_date}
                        onResolved={(payload) => {
                            setCreateLookup(payload);
                            if (payload?.suggested_employee_id) {
                                setCreateForm((f) => ({
                                    ...f,
                                    employee_id: payload.suggested_employee_id,
                                    movement_date: f.movement_date || payload.movement_date || '',
                                }));
                            } else if (payload?.movement_date) {
                                setCreateForm((f) => ({
                                    ...f,
                                    movement_date: f.movement_date || payload.movement_date,
                                }));
                            }
                        }}
                        error={createErrors.invoice_number}
                    />

                    {createLookup && (createLookup.available_dates?.length || 0) > 1 && (
                        <div className="mt-4 p-3 bg-amber-50 border border-amber-300 rounded-lg">
                            <p className="text-xs font-semibold text-amber-800 uppercase mb-2">
                                Foram encontradas {createLookup.available_dates.length} vendas com este número
                            </p>
                            <p className="text-xs text-amber-700 mb-2">
                                A numeração de cupons pode se repetir entre anos. Confirme a data:
                            </p>
                            <div className="flex items-center gap-2">
                                <label className="text-sm font-medium text-amber-900">
                                    Data da venda:
                                </label>
                                <select
                                    value={createForm.movement_date || ''}
                                    onChange={(e) =>
                                        setCreateForm({
                                            ...createForm,
                                            movement_date: e.target.value,
                                            items: [],
                                        })
                                    }
                                    className="rounded-md border-amber-300 shadow-sm text-sm"
                                >
                                    {createLookup.available_dates.map((d) => (
                                        <option key={d} value={d}>
                                            {new Date(d).toLocaleDateString('pt-BR')}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                    )}

                    {createLookup && (
                        <div className="mt-4 p-4 bg-indigo-50 border border-indigo-200 rounded-lg">
                            <div className="flex items-center justify-between mb-2">
                                <p className="text-xs font-semibold text-indigo-700 uppercase">
                                    Venda localizada
                                </p>
                                <span className="text-xs text-indigo-600">
                                    {createLookup.items_count} itens
                                </span>
                            </div>
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                                <div>
                                    <p className="text-xs text-indigo-500 uppercase">Loja</p>
                                    <p className="font-mono font-semibold text-indigo-900">
                                        {createLookup.store_code}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-indigo-500 uppercase">Data</p>
                                    <p className="font-semibold text-indigo-900">
                                        {createLookup.movement_date
                                            ? new Date(createLookup.movement_date).toLocaleDateString('pt-BR')
                                            : '—'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-indigo-500 uppercase">CPF Cliente</p>
                                    <p className="font-mono text-sm text-indigo-900">
                                        {createLookup.cpf_customer || '—'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-indigo-500 uppercase">Total da NF</p>
                                    <p className="font-bold text-indigo-900">
                                        {BRL.format(createLookup.sale_total)}
                                    </p>
                                </div>
                            </div>
                        </div>
                    )}

                    <div className="mt-4">
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Nome do cliente *
                        </label>
                        <input
                            type="text"
                            value={createForm.customer_name}
                            onChange={(e) =>
                                setCreateForm({ ...createForm, customer_name: e.target.value })
                            }
                            placeholder="Nome completo do solicitante"
                            className="w-full rounded-md border-gray-300 shadow-sm"
                        />
                        {createErrors.customer_name && (
                            <p className="mt-1 text-xs text-red-600">
                                {createErrors.customer_name}
                            </p>
                        )}
                    </div>
                </StandardModal.Section>

                <StandardModal.Section title="Tipo e motivo">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Tipo *
                            </label>
                            <select
                                value={createForm.type}
                                onChange={(e) =>
                                    setCreateForm({
                                        ...createForm,
                                        type: e.target.value,
                                        refund_amount:
                                            requiresRefund(e.target.value) ? createForm.refund_amount : '',
                                    })
                                }
                                className="w-full rounded-md border-gray-300 shadow-sm"
                            >
                                {Object.entries(typeOptions).map(([k, v]) => (
                                    <option key={k} value={k}>
                                        {v}
                                    </option>
                                ))}
                            </select>
                        </div>

                        {requiresRefund(createForm.type) && (
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Valor do reembolso *
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={createForm.refund_amount}
                                    onChange={(e) =>
                                        setCreateForm({ ...createForm, refund_amount: e.target.value })
                                    }
                                    placeholder="0,00"
                                    className="w-full rounded-md border-gray-300 shadow-sm"
                                />
                                {createErrors.refund_amount && (
                                    <p className="mt-1 text-xs text-red-600">
                                        {createErrors.refund_amount}
                                    </p>
                                )}
                            </div>
                        )}
                    </div>

                    <ReasonCategorySelector
                        category={createForm.reason_category}
                        reasonId={createForm.return_reason_id}
                        onChange={({ category, return_reason_id }) =>
                            setCreateForm({
                                ...createForm,
                                reason_category: category,
                                return_reason_id,
                            })
                        }
                        categoryOptions={reasonCategoryOptions}
                        reasons={selects.reasons || []}
                        errors={createErrors}
                    />
                </StandardModal.Section>

                <StandardModal.Section title="Itens a devolver">
                    <ItemSelectionWithQuantityTable
                        items={createLookup?.items || []}
                        selectedItems={createForm.items}
                        onChange={(items) => setCreateForm({ ...createForm, items })}
                        error={createErrors.items}
                    />
                </StandardModal.Section>

                <StandardModal.Section title="Logística e observações">
                    <div className="grid grid-cols-1 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Código de rastreio (logística reversa)
                            </label>
                            <input
                                type="text"
                                value={createForm.reverse_tracking_code}
                                onChange={(e) =>
                                    setCreateForm({
                                        ...createForm,
                                        reverse_tracking_code: e.target.value,
                                    })
                                }
                                placeholder="Ex: Correios, Loggi, Jadlog..."
                                className="w-full rounded-md border-gray-300 shadow-sm"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Observações
                            </label>
                            <textarea
                                rows={3}
                                value={createForm.notes}
                                onChange={(e) =>
                                    setCreateForm({ ...createForm, notes: e.target.value })
                                }
                                placeholder="Anotações internas do atendimento..."
                                className="w-full rounded-md border-gray-300 shadow-sm"
                            />
                        </div>
                    </div>
                </StandardModal.Section>
            </StandardModal>

            {/* ========================================================== */}
            {/* Modal: Detail                                              */}
            {/* ========================================================== */}
            <StandardModal
                show={modals.detail}
                onClose={() => closeModal('detail')}
                title={selected ? `Devolução #${selected.id}` : 'Detalhes'}
                subtitle={
                    selected
                        ? `NF ${selected.invoice_number} · ${BRL.format(selected.amount_items)}`
                        : ''
                }
                headerColor="bg-gray-700"
                headerIcon={<ArrowPathRoundedSquareIcon className="h-6 w-6" />}
                headerBadges={
                    selected
                        ? [
                              { text: selected.status_label, className: 'bg-white/20 text-white' },
                              { text: selected.type_label, className: 'bg-white/20 text-white' },
                              {
                                  text: selected.reason_category_label,
                                  className: 'bg-white/20 text-white',
                              },
                          ]
                        : []
                }
                maxWidth="5xl"
                footer={
                    <StandardModal.Footer>
                        <div className="flex items-center justify-between w-full">
                            <div>
                                {canExport && selected && (
                                    <a
                                        href={route('returns.pdf', selected.id)}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center gap-2 rounded-md border border-indigo-600 bg-white px-4 py-2 text-sm font-medium text-indigo-600 shadow-sm hover:bg-indigo-50"
                                    >
                                        <DocumentArrowDownIcon className="h-4 w-4" />
                                        Baixar comprovante
                                    </a>
                                )}
                            </div>
                            <button
                                type="button"
                                onClick={() => closeModal('detail')}
                                className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                            >
                                Fechar
                            </button>
                        </div>
                    </StandardModal.Footer>
                }
            >
                {selected && (
                    <>
                        <StandardModal.Section title="Venda">
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                                <StandardModal.Field label="NF/Cupom" value={selected.invoice_number} mono />
                                <StandardModal.Field
                                    label="Loja"
                                    value={`${selected.store_code} — ${selected.store_name || '—'}`}
                                />
                                <StandardModal.Field
                                    label="Data da Venda"
                                    value={
                                        selected.movement_date
                                            ? new Date(selected.movement_date).toLocaleDateString('pt-BR')
                                            : '—'
                                    }
                                />
                                <StandardModal.Field
                                    label="Total da NF"
                                    value={BRL.format(selected.sale_total)}
                                />
                                <StandardModal.Field label="Cliente" value={selected.customer_name} />
                                <StandardModal.Field label="CPF Cliente" value={selected.cpf_customer} mono />
                                <StandardModal.Field
                                    label="Consultor"
                                    value={selected.employee_name || selected.cpf_consultant}
                                />
                                <StandardModal.Field
                                    label="Motivo"
                                    value={selected.reason_name || selected.reason_category_label}
                                />
                            </div>
                        </StandardModal.Section>

                        <StandardModal.Section title="Valores">
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                                <StandardModal.InfoCard label="Tipo" value={selected.type_label} />
                                <StandardModal.InfoCard
                                    label="Valor dos itens"
                                    value={BRL.format(selected.amount_items)}
                                />
                                {selected.refund_amount !== null && (
                                    <StandardModal.InfoCard
                                        label="Reembolso"
                                        value={BRL.format(selected.refund_amount)}
                                        highlight
                                    />
                                )}
                                {selected.reverse_tracking_code && (
                                    <StandardModal.Field
                                        label="Rastreio reverso"
                                        value={selected.reverse_tracking_code}
                                        mono
                                    />
                                )}
                            </div>
                        </StandardModal.Section>

                        {selected.items && selected.items.length > 0 && (
                            <StandardModal.Section title="Itens devolvidos">
                                <table className="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">
                                                Código
                                            </th>
                                            <th className="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">
                                                Ref/Tam
                                            </th>
                                            <th className="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase">
                                                Qtd
                                            </th>
                                            <th className="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase">
                                                Unitário
                                            </th>
                                            <th className="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase">
                                                Subtotal
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200 bg-white">
                                        {selected.items.map((i) => (
                                            <tr key={i.id}>
                                                <td className="px-3 py-2 font-mono text-xs">
                                                    {i.barcode || '—'}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {[i.reference, i.size].filter(Boolean).join(' · ') || '—'}
                                                </td>
                                                <td className="px-3 py-2 text-right">
                                                    {Number(i.quantity).toLocaleString('pt-BR')}
                                                </td>
                                                <td className="px-3 py-2 text-right">
                                                    {BRL.format(i.unit_price)}
                                                </td>
                                                <td className="px-3 py-2 text-right font-semibold">
                                                    {BRL.format(i.subtotal)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </StandardModal.Section>
                        )}

                        {selected.notes && (
                            <StandardModal.Section title="Observações">
                                <p className="text-sm text-gray-700 whitespace-pre-wrap">
                                    {selected.notes}
                                </p>
                            </StandardModal.Section>
                        )}

                        {selected.cancelled_reason && (
                            <StandardModal.Section title="Motivo do cancelamento">
                                <div className="bg-red-50 border border-red-200 rounded-lg p-3">
                                    <p className="text-sm text-red-900 whitespace-pre-wrap">
                                        {selected.cancelled_reason}
                                    </p>
                                </div>
                            </StandardModal.Section>
                        )}

                        {selected.status_history && selected.status_history.length > 0 && (
                            <StandardModal.Section title="Histórico de status">
                                <StandardModal.Timeline
                                    items={selected.status_history.map((h) => ({
                                        id: h.id,
                                        title: `${
                                            h.from_status_label
                                                ? h.from_status_label + ' → '
                                                : ''
                                        }${h.to_status_label}`,
                                        subtitle: `${h.changed_by_name || 'Sistema'} — ${new Date(h.created_at).toLocaleString('pt-BR')}`,
                                        notes: h.note,
                                    }))}
                                />
                            </StandardModal.Section>
                        )}
                    </>
                )}
            </StandardModal>

            {/* ========================================================== */}
            {/* Modal: Edit                                                */}
            {/* ========================================================== */}
            <StandardModal
                show={modals.edit}
                onClose={() => closeModal('edit')}
                title="Editar Devolução"
                subtitle={selected ? `NF ${selected.invoice_number}` : ''}
                headerColor="bg-amber-600"
                headerIcon={<ArrowPathRoundedSquareIcon className="h-6 w-6" />}
                maxWidth="4xl"
                onSubmit={handleEditSubmit}
                footer={
                    <StandardModal.Footer
                        onCancel={() => closeModal('edit')}
                        onSubmit="submit"
                        submitLabel="Salvar"
                        submitColor="bg-amber-600 hover:bg-amber-700"
                        processing={editProcessing}
                    />
                }
            >
                <StandardModal.Section title="Dados da devolução">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Nome do cliente
                            </label>
                            <input
                                type="text"
                                value={editForm.customer_name || ''}
                                onChange={(e) =>
                                    setEditForm({ ...editForm, customer_name: e.target.value })
                                }
                                className="w-full rounded-md border-gray-300 shadow-sm"
                            />
                        </div>
                        {requiresRefund(editForm.type) && (
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Valor do reembolso
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={editForm.refund_amount || ''}
                                    onChange={(e) =>
                                        setEditForm({ ...editForm, refund_amount: e.target.value })
                                    }
                                    className="w-full rounded-md border-gray-300 shadow-sm"
                                />
                            </div>
                        )}
                        <div className="md:col-span-2">
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Código de rastreio (logística reversa)
                            </label>
                            <input
                                type="text"
                                value={editForm.reverse_tracking_code || ''}
                                onChange={(e) =>
                                    setEditForm({
                                        ...editForm,
                                        reverse_tracking_code: e.target.value,
                                    })
                                }
                                className="w-full rounded-md border-gray-300 shadow-sm"
                            />
                        </div>
                    </div>

                    <ReasonCategorySelector
                        category={editForm.reason_category}
                        reasonId={editForm.return_reason_id}
                        onChange={({ category, return_reason_id }) =>
                            setEditForm({
                                ...editForm,
                                reason_category: category,
                                return_reason_id,
                            })
                        }
                        categoryOptions={reasonCategoryOptions}
                        reasons={selects.reasons || []}
                        errors={editErrors}
                    />

                    <div className="mt-4">
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Observações
                        </label>
                        <textarea
                            rows={3}
                            value={editForm.notes || ''}
                            onChange={(e) => setEditForm({ ...editForm, notes: e.target.value })}
                            className="w-full rounded-md border-gray-300 shadow-sm"
                        />
                    </div>
                </StandardModal.Section>
            </StandardModal>

            {/* ========================================================== */}
            {/* Modal: Transition                                          */}
            {/* ========================================================== */}
            <StandardModal
                show={modals.transition}
                onClose={() => closeModal('transition')}
                title="Alterar Status da Devolução"
                subtitle={selected ? `NF ${selected.invoice_number}` : ''}
                headerColor="bg-blue-600"
                headerIcon={<ArrowPathIcon className="h-6 w-6" />}
                maxWidth="2xl"
                onSubmit={handleTransitionSubmit}
                footer={
                    <StandardModal.Footer
                        onCancel={() => closeModal('transition')}
                        onSubmit="submit"
                        submitLabel="Aplicar"
                        processing={transitionProcessing}
                        disabled={!transitionForm.to_status}
                    />
                }
            >
                {selected && (
                    <StandardModal.Section title="Nova transição">
                        <div className="mb-4">
                            <div className="flex items-center gap-2 text-sm text-gray-600 mb-2">
                                Atual:
                                <StatusBadge variant={COLOR_MAP[selected.status_color] || 'gray'}>
                                    {selected.status_label}
                                </StatusBadge>
                            </div>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Novo status *
                            </label>
                            <select
                                value={transitionForm.to_status}
                                onChange={(e) =>
                                    setTransitionForm({
                                        ...transitionForm,
                                        to_status: e.target.value,
                                    })
                                }
                                className="w-full rounded-md border-gray-300 shadow-sm"
                            >
                                <option value="">Selecione...</option>
                                {allowedTargetStatuses.map((o) => (
                                    <option key={o.value} value={o.value}>
                                        {o.label}
                                    </option>
                                ))}
                            </select>
                            {transitionErrors.to_status && (
                                <p className="mt-1 text-xs text-red-600">
                                    {transitionErrors.to_status}
                                </p>
                            )}
                            {transitionErrors.status && (
                                <p className="mt-1 text-xs text-red-600">
                                    {transitionErrors.status}
                                </p>
                            )}
                        </div>

                        <div className="mt-4">
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Observação{' '}
                                {transitionForm.to_status === 'cancelled' && (
                                    <span className="text-red-500">*</span>
                                )}
                            </label>
                            <textarea
                                rows={3}
                                value={transitionForm.note}
                                onChange={(e) =>
                                    setTransitionForm({ ...transitionForm, note: e.target.value })
                                }
                                placeholder={
                                    transitionForm.to_status === 'cancelled'
                                        ? 'Motivo do cancelamento...'
                                        : 'Observação opcional...'
                                }
                                className="w-full rounded-md border-gray-300 shadow-sm"
                            />
                            {transitionErrors.note && (
                                <p className="mt-1 text-xs text-red-600">
                                    {transitionErrors.note}
                                </p>
                            )}
                        </div>
                    </StandardModal.Section>
                )}
            </StandardModal>

            {/* ========================================================== */}
            {/* Modal: Delete                                              */}
            {/* ========================================================== */}
            <StandardModal
                show={deleteTarget !== null}
                onClose={() => {
                    setDeleteTarget(null);
                    setDeleteReason('');
                }}
                title="Excluir Devolução"
                subtitle={
                    deleteTarget
                        ? `#${deleteTarget.id} — NF ${deleteTarget.invoice_number}`
                        : ''
                }
                headerColor="bg-red-600"
                headerIcon={<TrashIcon className="h-6 w-6" />}
                maxWidth="lg"
                onSubmit={handleDeleteConfirm}
                footer={
                    <StandardModal.Footer
                        onCancel={() => {
                            setDeleteTarget(null);
                            setDeleteReason('');
                        }}
                        onSubmit="submit"
                        submitLabel="Confirmar Exclusão"
                        submitColor="bg-red-600 hover:bg-red-700"
                        processing={deleteProcessing}
                        disabled={!deleteReason || deleteReason.trim().length < 3}
                    />
                }
            >
                {deleteTarget && (
                    <StandardModal.Section title="Dados da devolução">
                        <div className="grid grid-cols-2 gap-3 mb-4">
                            <StandardModal.Field
                                label="NF/Cupom"
                                value={deleteTarget.invoice_number}
                                mono
                            />
                            <StandardModal.Field
                                label="Tipo"
                                value={deleteTarget.type_label}
                            />
                            <StandardModal.Field
                                label="Cliente"
                                value={deleteTarget.customer_name}
                            />
                            <StandardModal.Field
                                label="Valor"
                                value={BRL.format(deleteTarget.amount_items)}
                            />
                        </div>
                        <div className="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4">
                            <p className="text-sm text-amber-900">
                                A devolução será marcada como excluída mas o histórico permanece auditável.
                                Não é possível excluir devoluções já concluídas.
                            </p>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Motivo da exclusão *
                            </label>
                            <textarea
                                rows={3}
                                value={deleteReason}
                                onChange={(e) => setDeleteReason(e.target.value)}
                                placeholder="Descreva o motivo (mínimo 3 caracteres)..."
                                className="w-full rounded-md border-gray-300 shadow-sm"
                                required
                            />
                        </div>
                    </StandardModal.Section>
                )}
            </StandardModal>
        </>
    );
}
