import { Head, router, Link } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import {
    ArrowUturnLeftIcon,
    PlusIcon,
    ArrowPathIcon,
    ClockIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    ChartBarIcon,
    TrashIcon,
    CurrencyDollarIcon,
    DocumentArrowDownIcon,
} from '@heroicons/react/24/outline';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import { maskMoney, parseMoney } from '@/Hooks/useMasks';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import DataTable from '@/Components/DataTable';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import InvoiceLookupSection from './components/InvoiceLookupSection';
import ItemSelectionTable from './components/ItemSelectionTable';
import PixFieldsSection from './components/PixFieldsSection';
import ReversalFilesUpload from './components/ReversalFilesUpload';

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

// Nomes que indicam PIX no cadastro de payment_types (fallback case-insensitive)
const PIX_TOKENS = ['pix'];

const isPixPayment = (paymentTypes, id) => {
    const pt = (paymentTypes || []).find((p) => String(p.id) === String(id));
    if (!pt?.name) return false;
    const lower = pt.name.toLowerCase();
    return PIX_TOKENS.some((token) => lower.includes(token));
};

export default function Index({
    reversals,
    filters = {},
    statistics = {},
    statusOptions = {},
    statusColors = {},
    statusTransitions = {},
    typeOptions = {},
    isStoreScoped = false,
    scopedStoreCode = null,
    selects = {},
}) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission(PERMISSIONS.CREATE_REVERSALS);
    const canEdit = hasPermission(PERMISSIONS.EDIT_REVERSALS);
    const canApprove = hasPermission(PERMISSIONS.APPROVE_REVERSALS);
    const canProcess = hasPermission(PERMISSIONS.PROCESS_REVERSALS);
    const canDelete = hasPermission(PERMISSIONS.DELETE_REVERSALS);
    const canExport = hasPermission(PERMISSIONS.EXPORT_REVERSALS);

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
        store_code: scopedStoreCode || '',
        invoice_number: '',
        movement_date: '',
        customer_name: '',
        employee_id: '',
        type: 'total',
        partial_mode: '',
        amount_correct: '',
        items: [],
        reversal_reason_id: '',
        expected_refund_date: '',
        payment_type_id: '',
        payment_brand: '',
        installments_count: 1,
        nsu: '',
        authorization_code: '',
        pix_key_type: '',
        pix_key: '',
        pix_beneficiary: '',
        pix_bank_id: '',
        notes: '',
    };
    const [createForm, setCreateForm] = useState(emptyCreate);
    const [createErrors, setCreateErrors] = useState({});
    const [createProcessing, setCreateProcessing] = useState(false);
    const [createLookup, setCreateLookup] = useState(null);
    const [createFiles, setCreateFiles] = useState([]);

    // ------------------------------------------------------------------
    // Edit form
    // ------------------------------------------------------------------
    const [editForm, setEditForm] = useState({});
    const [editErrors, setEditErrors] = useState({});
    const [editProcessing, setEditProcessing] = useState(false);

    // ------------------------------------------------------------------
    // Transition form
    // ------------------------------------------------------------------
    const [transitionForm, setTransitionForm] = useState({ to_status: '', note: '' });
    const [transitionErrors, setTransitionErrors] = useState({});
    const [transitionProcessing, setTransitionProcessing] = useState(false);

    // ------------------------------------------------------------------
    // Delete form
    // ------------------------------------------------------------------
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

    const clearFilters = () => router.visit(route('reversals.index'));

    const openDetail = (row) => {
        fetch(route('reversals.show', row.id), {
            headers: { Accept: 'application/json' },
        })
            .then((r) => r.json())
            .then((d) => openModal('detail', d.reversal));
    };

    const openEdit = (row) => {
        fetch(route('reversals.show', row.id), {
            headers: { Accept: 'application/json' },
        })
            .then((r) => r.json())
            .then((d) => {
                const r = d.reversal;
                setEditForm({
                    id: r.id,
                    customer_name: r.customer_name || '',
                    employee_id: r.employee_id || '',
                    reversal_reason_id: r.reversal_reason_id || '',
                    expected_refund_date: r.expected_refund_date || '',
                    payment_type_id: r.payment_type_id || '',
                    payment_brand: r.payment_brand || '',
                    installments_count: r.installments_count || '',
                    nsu: r.nsu || '',
                    authorization_code: r.authorization_code || '',
                    pix_key_type: r.pix_key_type || '',
                    pix_key: r.pix_key || '',
                    pix_beneficiary: r.pix_beneficiary || '',
                    pix_bank_id: r.pix_bank_id || '',
                    notes: r.notes || '',
                });
                setEditErrors({});
                openModal('edit', r);
            });
    };

    const openTransition = (row) => {
        fetch(route('reversals.show', row.id), {
            headers: { Accept: 'application/json' },
        })
            .then((r) => r.json())
            .then((d) => {
                setTransitionForm({ to_status: '', note: '' });
                setTransitionErrors({});
                openModal('transition', d.reversal);
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

        // Monta payload. FormData eh necessario por causa dos arquivos.
        const fd = new FormData();

        const plain = { ...createForm };
        // Limpa arrays/objetos para FormData
        const items = plain.items || [];
        delete plain.items;

        // Converte máscara BR "1.234,56" para float antes de enviar
        if (plain.amount_correct) {
            plain.amount_correct = parseMoney(plain.amount_correct);
        }

        Object.entries(plain).forEach(([k, v]) => {
            if (v === null || v === undefined || v === '') return;
            fd.append(k, v);
        });

        items.forEach((item, idx) => {
            fd.append(`items[${idx}][movement_id]`, item.movement_id);
            if (item.product_name) fd.append(`items[${idx}][product_name]`, item.product_name);
            if (item.quantity !== undefined && item.quantity !== '') {
                fd.append(`items[${idx}][quantity]`, item.quantity);
            }
        });

        createFiles.forEach((file) => fd.append('files[]', file));

        router.post(route('reversals.store'), fd, {
            forceFormData: true,
            onError: (errs) => setCreateErrors(errs),
            onSuccess: () => {
                setCreateForm(emptyCreate);
                setCreateErrors({});
                setCreateLookup(null);
                setCreateFiles([]);
                closeModal('create');
            },
            onFinish: () => setCreateProcessing(false),
        });
    };

    const handleEditSubmit = () => {
        setEditProcessing(true);
        router.put(route('reversals.update', editForm.id), editForm, {
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
        router.post(route('reversals.transition', selected?.id), transitionForm, {
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
        router.delete(route('reversals.destroy', deleteTarget.id), {
            data: { deleted_reason: deleteReason },
            onSuccess: () => {
                setDeleteTarget(null);
                setDeleteReason('');
            },
            onFinish: () => setDeleteProcessing(false),
        });
    };

    // ------------------------------------------------------------------
    // Table columns
    // ------------------------------------------------------------------
    const columns = [
        {
            field: 'invoice_number',
            label: 'NF/Cupom',
            render: (row) => (
                <span className="font-mono text-xs">{row.invoice_number}</span>
            ),
        },
        {
            field: 'store_code',
            label: 'Loja',
            render: (row) => (
                <span className="font-mono text-xs">{row.store_code}</span>
            ),
        },
        {
            field: 'customer_name',
            label: 'Cliente',
            render: (row) => (
                <div>
                    <div className="text-sm font-medium text-gray-900">
                        {row.customer_name}
                    </div>
                    {row.cpf_customer && (
                        <div className="text-xs text-gray-500 font-mono">
                            {row.cpf_customer}
                        </div>
                    )}
                </div>
            ),
        },
        {
            field: 'type_label',
            label: 'Tipo',
            render: (row) => (
                <StatusBadge variant={row.type === 'total' ? 'danger' : 'warning'}>
                    {row.type_label}
                </StatusBadge>
            ),
        },
        {
            field: 'amount_reversal',
            label: 'Valor Estorno',
            render: (row) => (
                <div>
                    <div className="text-sm font-semibold text-gray-900">
                        {BRL.format(row.amount_reversal)}
                    </div>
                    {row.amount_original !== row.amount_reversal && (
                        <div className="text-xs text-gray-400">
                            de {BRL.format(row.amount_original)}
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
            label: 'Criado',
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
                    {(canApprove || canProcess) && !row.is_terminal && (
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
        filters.reversal_reason_id ||
        filters.date_from ||
        filters.date_to ||
        filters.store_code ||
        filters.search;

    const statisticsCards = [
        {
            label: 'Total (não excluídos)',
            value: statistics.total || 0,
            format: 'number',
            icon: ArrowUturnLeftIcon,
            color: 'indigo',
            sub: `Valor: ${BRL.format(statistics.total_amount || 0)}`,
            active: !filters.status,
            onClick: () => applyFilter('status', ''),
        },
        {
            label: 'Aguardando aprovação',
            value: statistics.pending_approval || 0,
            format: 'number',
            icon: ClockIcon,
            color: 'yellow',
            active: filters.status === 'pending_authorization',
            onClick: () =>
                applyFilter(
                    'status',
                    filters.status === 'pending_authorization' ? '' : 'pending_authorization'
                ),
        },
        {
            label: 'Aguardando financeira',
            value: statistics.pending_finance || 0,
            format: 'number',
            icon: CurrencyDollarIcon,
            color: 'orange',
            active: filters.status === 'pending_finance',
            onClick: () =>
                applyFilter(
                    'status',
                    filters.status === 'pending_finance' ? '' : 'pending_finance'
                ),
        },
        {
            label: 'Estornado este mês',
            value: statistics.reversed_this_month_amount || 0,
            format: 'currency',
            icon: CheckCircleIcon,
            color: 'green',
        },
        {
            label: 'Cancelados',
            value: statistics.cancelled || 0,
            format: 'number',
            icon: ExclamationTriangleIcon,
            color: 'red',
        },
    ];

    const paymentTypeIsPix = isPixPayment(selects.paymentTypes, createForm.payment_type_id);
    const editPaymentIsPix = isPixPayment(selects.paymentTypes, editForm.payment_type_id);

    return (
        <>
            <Head title="Estornos" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6 flex justify-between items-center">
                        <div>
                            <h1 className="text-3xl font-bold text-gray-900">
                                Estornos
                            </h1>
                            <p className="mt-1 text-sm text-gray-600">
                                Solicitações de estorno de vendas com workflow de aprovação
                                {isStoreScoped && scopedStoreCode && (
                                    <span className="ml-2 text-xs font-medium text-indigo-600">
                                        (escopo: loja {scopedStoreCode})
                                    </span>
                                )}
                            </p>
                        </div>
                        <div className="flex gap-2">
                            <Link href={route('reversals.dashboard')}>
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
                                        setCreateFiles([]);
                                        openModal('create');
                                    }}
                                    icon={PlusIcon}
                                >
                                    Novo Estorno
                                </Button>
                            )}
                        </div>
                    </div>

                    <StatisticsGrid cards={statisticsCards} />

                    {/* Filtros */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
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
                            {!isStoreScoped && (
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                        Loja
                                    </label>
                                    <select
                                        value={filters.store_code || ''}
                                        onChange={(e) =>
                                            applyFilter('store_code', e.target.value)
                                        }
                                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">Todas</option>
                                        {(selects.stores || []).map((s) => (
                                            <option key={s.code} value={s.code}>
                                                {s.code} — {s.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Tipo
                                </label>
                                <select
                                    value={filters.type || ''}
                                    onChange={(e) => applyFilter('type', e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
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
                                    Status
                                </label>
                                <select
                                    value={filters.status || ''}
                                    onChange={(e) => applyFilter('status', e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">Todos</option>
                                    {Object.entries(statusOptions).map(([k, v]) => (
                                        <option key={k} value={k}>
                                            {v}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Criado de
                                </label>
                                <input
                                    type="date"
                                    value={filters.date_from || ''}
                                    onChange={(e) =>
                                        applyFilter('date_from', e.target.value)
                                    }
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
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
                        data={reversals}
                        columns={columns}
                        emptyMessage="Nenhum estorno encontrado."
                    />
                </div>
            </div>

            {/* ========================================================== */}
            {/* Modal: Create                                              */}
            {/* ========================================================== */}
            <StandardModal
                show={modals.create}
                onClose={() => closeModal('create')}
                title="Novo Estorno"
                subtitle="Informe a NF/cupom e os dados do estorno"

                headerColor="bg-indigo-600"
                headerIcon={<ArrowUturnLeftIcon className="h-6 w-6" />}
                maxWidth="5xl"
                onSubmit={handleCreateSubmit}
                footer={
                    <StandardModal.Footer
                        onCancel={() => closeModal('create')}
                        onSubmit="submit"
                        submitLabel="Criar Estorno"
                        processing={createProcessing}
                        disabled={!createLookup}
                    />
                }
            >
                <StandardModal.Section title="Venda">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Loja da Venda *
                            </label>
                            <select
                                value={createForm.store_code}
                                onChange={(e) => {
                                    // Muda a loja → limpa lookup e NF para evitar mistura
                                    setCreateForm({
                                        ...createForm,
                                        store_code: e.target.value,
                                        invoice_number: '',
                                    });
                                    setCreateLookup(null);
                                }}
                                disabled={isStoreScoped}
                                className="w-full rounded-md border-gray-300 shadow-sm disabled:bg-gray-100"
                            >
                                <option value="">Selecione a loja...</option>
                                {(selects.stores || []).map((s) => (
                                    <option key={s.code} value={s.code}>
                                        {s.code} — {s.name}
                                    </option>
                                ))}
                            </select>
                            {createErrors.store_code && (
                                <p className="mt-1 text-xs text-red-600">
                                    {createErrors.store_code}
                                </p>
                            )}
                            {isStoreScoped && (
                                <p className="mt-1 text-xs text-gray-500">
                                    Você só pode criar estornos para a sua própria loja.
                                </p>
                            )}
                        </div>

                        <InvoiceLookupSection
                            value={createForm.invoice_number}
                            onChange={(v) =>
                                setCreateForm({
                                    ...createForm,
                                    invoice_number: v,
                                    movement_date: '',
                                })
                            }
                            storeCode={createForm.store_code}
                            movementDate={createForm.movement_date}
                            onResolved={(payload) => {
                                setCreateLookup(payload);
                                if (payload?.suggested_employee_id) {
                                    setCreateForm((f) => ({
                                        ...f,
                                        employee_id: payload.suggested_employee_id,
                                        // Sincroniza a data escolhida pelo backend
                                        movement_date:
                                            f.movement_date || payload.movement_date || '',
                                    }));
                                } else if (payload?.movement_date) {
                                    setCreateForm((f) => ({
                                        ...f,
                                        movement_date:
                                            f.movement_date || payload.movement_date,
                                    }));
                                }
                            }}
                            error={createErrors.invoice_number}
                        />
                    </div>

                    {createLookup && (createLookup.available_dates?.length || 0) > 1 && (
                        <div className="mt-4 p-3 bg-amber-50 border border-amber-300 rounded-lg">
                            <p className="text-xs font-semibold text-amber-800 uppercase mb-2">
                                Foram encontradas {createLookup.available_dates.length}{' '}
                                vendas com este número nesta loja
                            </p>
                            <p className="text-xs text-amber-700 mb-2">
                                A numeração de cupons pode se repetir entre anos. Confirme
                                abaixo a data da venda que você quer estornar:
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
                                    {createLookup.items_count}{' '}
                                    {createLookup.items_count === 1 ? 'item' : 'itens'}
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
                                            ? new Date(
                                                  createLookup.movement_date
                                              ).toLocaleDateString('pt-BR')
                                            : '—'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-indigo-500 uppercase">
                                        Consultor
                                    </p>
                                    <p className="font-mono text-sm text-indigo-900">
                                        {createLookup.cpf_consultant || '—'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs text-indigo-500 uppercase">
                                        Total da NF
                                    </p>
                                    <p className="font-bold text-indigo-900">
                                        {BRL.format(createLookup.sale_total)}
                                    </p>
                                </div>
                            </div>
                        </div>
                    )}

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Nome do Cliente *
                            </label>
                            <input
                                type="text"
                                value={createForm.customer_name}
                                onChange={(e) =>
                                    setCreateForm({
                                        ...createForm,
                                        customer_name: e.target.value,
                                    })
                                }
                                className="w-full rounded-md border-gray-300 shadow-sm"
                            />
                            {createErrors.customer_name && (
                                <p className="mt-1 text-xs text-red-600">
                                    {createErrors.customer_name}
                                </p>
                            )}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Motivo do Estorno *
                            </label>
                            <select
                                value={createForm.reversal_reason_id}
                                onChange={(e) =>
                                    setCreateForm({
                                        ...createForm,
                                        reversal_reason_id: e.target.value,
                                    })
                                }
                                className="w-full rounded-md border-gray-300 shadow-sm"
                            >
                                <option value="">Selecione...</option>
                                {(selects.reasons || []).map((r) => (
                                    <option key={r.id} value={r.id}>
                                        {r.name}
                                    </option>
                                ))}
                            </select>
                            {createErrors.reversal_reason_id && (
                                <p className="mt-1 text-xs text-red-600">
                                    {createErrors.reversal_reason_id}
                                </p>
                            )}
                        </div>
                    </div>
                </StandardModal.Section>

                <StandardModal.Section title="Tipo de Estorno">
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
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
                                        partial_mode: '',
                                        amount_correct: '',
                                        items: [],
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
                        {createForm.type === 'partial' && (
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Modo *
                                </label>
                                <select
                                    value={createForm.partial_mode}
                                    onChange={(e) =>
                                        setCreateForm({
                                            ...createForm,
                                            partial_mode: e.target.value,
                                            items: [],
                                            amount_correct: '',
                                        })
                                    }
                                    className="w-full rounded-md border-gray-300 shadow-sm"
                                >
                                    <option value="">Selecione...</option>
                                    <option value="by_value">Por Valor</option>
                                    <option value="by_item">Por Produto</option>
                                </select>
                                {createErrors.partial_mode && (
                                    <p className="mt-1 text-xs text-red-600">
                                        {createErrors.partial_mode}
                                    </p>
                                )}
                            </div>
                        )}
                        {createForm.type === 'partial' &&
                            createForm.partial_mode === 'by_value' && (
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Valor correto *
                                    </label>
                                    <div className="relative">
                                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-500">
                                            R$
                                        </span>
                                        <input
                                            type="text"
                                            inputMode="numeric"
                                            value={createForm.amount_correct}
                                            onChange={(e) =>
                                                setCreateForm({
                                                    ...createForm,
                                                    amount_correct: maskMoney(e.target.value),
                                                })
                                            }
                                            placeholder="0,00"
                                            className="w-full rounded-md border-gray-300 shadow-sm pl-9"
                                        />
                                    </div>
                                    {createErrors.amount_correct && (
                                        <p className="mt-1 text-xs text-red-600">
                                            {createErrors.amount_correct}
                                        </p>
                                    )}
                                </div>
                            )}
                    </div>

                    {createForm.type === 'partial' &&
                        createForm.partial_mode === 'by_item' && (
                            <div className="mt-4">
                                <ItemSelectionTable
                                    items={createLookup?.items || []}
                                    selectedIds={(createForm.items || []).map(
                                        (i) => i.movement_id
                                    )}
                                    onToggle={(movementId) => {
                                        const exists = (createForm.items || []).some(
                                            (i) => i.movement_id === movementId
                                        );
                                        const next = exists
                                            ? createForm.items.filter(
                                                  (i) => i.movement_id !== movementId
                                              )
                                            : [...createForm.items, { movement_id: movementId }];
                                        setCreateForm({ ...createForm, items: next });
                                    }}
                                    error={createErrors.items}
                                />
                            </div>
                        )}
                </StandardModal.Section>

                <StandardModal.Section title="Pagamento Original">
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Forma de Pagamento
                            </label>
                            <select
                                value={createForm.payment_type_id}
                                onChange={(e) =>
                                    setCreateForm({
                                        ...createForm,
                                        payment_type_id: e.target.value,
                                    })
                                }
                                className="w-full rounded-md border-gray-300 shadow-sm"
                            >
                                <option value="">—</option>
                                {(selects.paymentTypes || []).map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Bandeira
                            </label>
                            <input
                                type="text"
                                value={createForm.payment_brand}
                                onChange={(e) =>
                                    setCreateForm({
                                        ...createForm,
                                        payment_brand: e.target.value,
                                    })
                                }
                                placeholder="Ex: VISA, MASTER"
                                className="w-full rounded-md border-gray-300 shadow-sm"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Parcelas
                            </label>
                            <input
                                type="number"
                                min="1"
                                max="99"
                                value={createForm.installments_count}
                                onChange={(e) => {
                                    const raw = parseInt(e.target.value, 10);
                                    const clamped = Number.isFinite(raw) && raw >= 1 ? raw : 1;
                                    setCreateForm({
                                        ...createForm,
                                        installments_count: clamped,
                                    });
                                }}
                                className="w-full rounded-md border-gray-300 shadow-sm"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                NSU
                            </label>
                            <input
                                type="text"
                                value={createForm.nsu}
                                onChange={(e) =>
                                    setCreateForm({ ...createForm, nsu: e.target.value })
                                }
                                className="w-full rounded-md border-gray-300 shadow-sm"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Código de Autorização
                            </label>
                            <input
                                type="text"
                                value={createForm.authorization_code}
                                onChange={(e) =>
                                    setCreateForm({
                                        ...createForm,
                                        authorization_code: e.target.value,
                                    })
                                }
                                className="w-full rounded-md border-gray-300 shadow-sm"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Data prevista da devolução
                            </label>
                            <input
                                type="date"
                                value={createForm.expected_refund_date}
                                onChange={(e) =>
                                    setCreateForm({
                                        ...createForm,
                                        expected_refund_date: e.target.value,
                                    })
                                }
                                className="w-full rounded-md border-gray-300 shadow-sm"
                            />
                        </div>
                    </div>

                    {paymentTypeIsPix && (
                        <div className="mt-4">
                            <PixFieldsSection
                                value={{
                                    pix_key_type: createForm.pix_key_type,
                                    pix_key: createForm.pix_key,
                                    pix_beneficiary: createForm.pix_beneficiary,
                                    pix_bank_id: createForm.pix_bank_id,
                                }}
                                onChange={(patch) =>
                                    setCreateForm({ ...createForm, ...patch })
                                }
                                errors={createErrors}
                                banks={selects.banks || []}
                            />
                        </div>
                    )}
                </StandardModal.Section>

                <StandardModal.Section title="Anexos e Observações">
                    <ReversalFilesUpload files={createFiles} onChange={setCreateFiles} />
                    <div className="mt-4">
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Observações
                        </label>
                        <textarea
                            rows={3}
                            value={createForm.notes}
                            onChange={(e) =>
                                setCreateForm({ ...createForm, notes: e.target.value })
                            }
                            placeholder="Informações adicionais..."
                            className="w-full rounded-md border-gray-300 shadow-sm"
                        />
                    </div>
                </StandardModal.Section>
            </StandardModal>

            {/* ========================================================== */}
            {/* Modal: Detail                                              */}
            {/* ========================================================== */}
            <StandardModal
                show={modals.detail}
                onClose={() => closeModal('detail')}
                title={selected ? `Estorno #${selected.id}` : 'Detalhes'}
                subtitle={
                    selected
                        ? `NF ${selected.invoice_number} · ${BRL.format(selected.amount_reversal)}`
                        : ''
                }
                headerColor="bg-gray-700"
                headerIcon={<ArrowUturnLeftIcon className="h-6 w-6" />}
                headerBadges={
                    selected
                        ? [
                              { text: selected.status_label, className: 'bg-white/20 text-white' },
                              { text: selected.type_label, className: 'bg-white/20 text-white' },
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
                                        href={route('reversals.pdf', selected.id)}
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
                                <StandardModal.Field
                                    label="NF/Cupom"
                                    value={selected.invoice_number}
                                    mono
                                />
                                <StandardModal.Field
                                    label="Loja"
                                    value={`${selected.store_code} — ${selected.store_name || '—'}`}
                                />
                                <StandardModal.Field
                                    label="Data da Venda"
                                    value={
                                        selected.movement_date
                                            ? new Date(selected.movement_date).toLocaleDateString(
                                                  'pt-BR'
                                              )
                                            : '—'
                                    }
                                />
                                <StandardModal.Field
                                    label="Total da NF"
                                    value={BRL.format(selected.sale_total)}
                                />
                                <StandardModal.Field
                                    label="Cliente"
                                    value={selected.customer_name}
                                />
                                <StandardModal.Field
                                    label="CPF Cliente"
                                    value={selected.cpf_customer}
                                    mono
                                />
                                <StandardModal.Field
                                    label="Consultor"
                                    value={selected.employee_name || selected.cpf_consultant}
                                />
                                <StandardModal.Field
                                    label="Motivo"
                                    value={selected.reason_name}
                                />
                            </div>
                        </StandardModal.Section>

                        <StandardModal.Section title="Valores e Pagamento">
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                                <StandardModal.InfoCard
                                    label="Tipo"
                                    value={selected.type_label}
                                />
                                {selected.partial_mode_label && (
                                    <StandardModal.InfoCard
                                        label="Modo"
                                        value={selected.partial_mode_label}
                                    />
                                )}
                                <StandardModal.InfoCard
                                    label="Valor Original"
                                    value={BRL.format(selected.amount_original)}
                                />
                                {selected.amount_correct !== null && (
                                    <StandardModal.InfoCard
                                        label="Valor Correto"
                                        value={BRL.format(selected.amount_correct)}
                                    />
                                )}
                                <StandardModal.InfoCard
                                    label="Valor do Estorno"
                                    value={BRL.format(selected.amount_reversal)}
                                    highlight
                                />
                                {selected.payment_type_name && (
                                    <StandardModal.Field
                                        label="Forma de Pagamento"
                                        value={`${selected.payment_type_name}${selected.payment_brand ? ` (${selected.payment_brand})` : ''}`}
                                    />
                                )}
                                {selected.installments_count && (
                                    <StandardModal.Field
                                        label="Parcelas"
                                        value={`${selected.installments_count}x`}
                                    />
                                )}
                                {selected.nsu && (
                                    <StandardModal.Field label="NSU" value={selected.nsu} mono />
                                )}
                                {selected.authorization_code && (
                                    <StandardModal.Field
                                        label="Autorização"
                                        value={selected.authorization_code}
                                        mono
                                    />
                                )}
                                {selected.expected_refund_date && (
                                    <StandardModal.Field
                                        label="Previsão devolução"
                                        value={new Date(
                                            selected.expected_refund_date
                                        ).toLocaleDateString('pt-BR')}
                                    />
                                )}
                            </div>
                        </StandardModal.Section>

                        {selected.pix_key && (
                            <StandardModal.Section title="Dados PIX">
                                <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                                    <StandardModal.Field
                                        label="Tipo"
                                        value={selected.pix_key_type}
                                    />
                                    <StandardModal.Field
                                        label="Chave"
                                        value={selected.pix_key}
                                        mono
                                    />
                                    <StandardModal.Field
                                        label="Beneficiário"
                                        value={selected.pix_beneficiary}
                                    />
                                    <StandardModal.Field
                                        label="Banco"
                                        value={selected.pix_bank_name}
                                    />
                                </div>
                            </StandardModal.Section>
                        )}

                        {selected.items && selected.items.length > 0 && (
                            <StandardModal.Section title="Itens Estornados">
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
                                                Qtde
                                            </th>
                                            <th className="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase">
                                                Total
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200 bg-white">
                                        {selected.items.map((i) => (
                                            <tr key={i.id}>
                                                <td className="px-3 py-2 font-mono text-xs">
                                                    {i.barcode || '—'}
                                                </td>
                                                <td className="px-3 py-2">{i.ref_size || '—'}</td>
                                                <td className="px-3 py-2 text-right">
                                                    {Number(i.quantity).toLocaleString('pt-BR')}
                                                </td>
                                                <td className="px-3 py-2 text-right font-semibold">
                                                    {BRL.format(i.amount)}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </StandardModal.Section>
                        )}

                        {selected.files && selected.files.length > 0 && (
                            <StandardModal.Section title="Anexos">
                                <ul className="space-y-2">
                                    {selected.files.map((f) => (
                                        <li
                                            key={f.id}
                                            className="flex items-center justify-between bg-gray-50 border rounded px-3 py-2"
                                        >
                                            <a
                                                href={`/storage/${f.file_path}`}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="text-sm text-indigo-600 hover:underline truncate"
                                            >
                                                {f.file_name}
                                            </a>
                                            <span className="text-xs text-gray-400">
                                                {f.uploaded_by_name}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
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
                            <StandardModal.Section title="Motivo do Cancelamento">
                                <div className="bg-red-50 border border-red-200 rounded-lg p-3">
                                    <p className="text-sm text-red-900 whitespace-pre-wrap">
                                        {selected.cancelled_reason}
                                    </p>
                                </div>
                            </StandardModal.Section>
                        )}

                        {selected.status_history && selected.status_history.length > 0 && (
                            <StandardModal.Section title="Histórico de Status">
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
                title="Editar Estorno"
                subtitle={selected ? `NF ${selected.invoice_number}` : ''}
                headerColor="bg-amber-600"
                headerIcon={<ArrowUturnLeftIcon className="h-6 w-6" />}
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
                <StandardModal.Section title="Dados do Estorno">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Nome do Cliente
                            </label>
                            <input
                                type="text"
                                value={editForm.customer_name || ''}
                                onChange={(e) =>
                                    setEditForm({ ...editForm, customer_name: e.target.value })
                                }
                                className="w-full rounded-md border-gray-300 shadow-sm"
                            />
                            {editErrors.customer_name && (
                                <p className="mt-1 text-xs text-red-600">
                                    {editErrors.customer_name}
                                </p>
                            )}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Motivo do Estorno
                            </label>
                            <select
                                value={editForm.reversal_reason_id || ''}
                                onChange={(e) =>
                                    setEditForm({
                                        ...editForm,
                                        reversal_reason_id: e.target.value,
                                    })
                                }
                                className="w-full rounded-md border-gray-300 shadow-sm"
                            >
                                <option value="">Selecione...</option>
                                {(selects.reasons || []).map((r) => (
                                    <option key={r.id} value={r.id}>
                                        {r.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Data prevista devolução
                            </label>
                            <input
                                type="date"
                                value={editForm.expected_refund_date || ''}
                                onChange={(e) =>
                                    setEditForm({
                                        ...editForm,
                                        expected_refund_date: e.target.value,
                                    })
                                }
                                className="w-full rounded-md border-gray-300 shadow-sm"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Forma de Pagamento
                            </label>
                            <select
                                value={editForm.payment_type_id || ''}
                                onChange={(e) =>
                                    setEditForm({
                                        ...editForm,
                                        payment_type_id: e.target.value,
                                    })
                                }
                                className="w-full rounded-md border-gray-300 shadow-sm"
                            >
                                <option value="">—</option>
                                {(selects.paymentTypes || []).map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>

                    {editPaymentIsPix && (
                        <div className="mt-4">
                            <PixFieldsSection
                                value={{
                                    pix_key_type: editForm.pix_key_type,
                                    pix_key: editForm.pix_key,
                                    pix_beneficiary: editForm.pix_beneficiary,
                                    pix_bank_id: editForm.pix_bank_id,
                                }}
                                onChange={(patch) => setEditForm({ ...editForm, ...patch })}
                                errors={editErrors}
                                banks={selects.banks || []}
                            />
                        </div>
                    )}

                    <div className="mt-4">
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Observações
                        </label>
                        <textarea
                            rows={3}
                            value={editForm.notes || ''}
                            onChange={(e) =>
                                setEditForm({ ...editForm, notes: e.target.value })
                            }
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
                title="Alterar Status do Estorno"
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
                    <StandardModal.Section title="Nova Transicao">
                        <div className="mb-4">
                            <div className="flex items-center gap-2 text-sm text-gray-600 mb-2">
                                Atual:
                                <StatusBadge
                                    variant={COLOR_MAP[selected.status_color] || 'gray'}
                                >
                                    {selected.status_label}
                                </StatusBadge>
                            </div>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Novo Status *
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
                                    setTransitionForm({
                                        ...transitionForm,
                                        note: e.target.value,
                                    })
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
                title="Excluir Estorno"
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
                    <StandardModal.Section title="Dados do Estorno">
                        <div className="grid grid-cols-2 gap-3 mb-4">
                            <StandardModal.Field
                                label="NF/Cupom"
                                value={deleteTarget.invoice_number}
                                mono
                            />
                            <StandardModal.Field
                                label="Loja"
                                value={deleteTarget.store_code}
                                mono
                            />
                            <StandardModal.Field
                                label="Cliente"
                                value={deleteTarget.customer_name}
                            />
                            <StandardModal.Field
                                label="Valor"
                                value={BRL.format(deleteTarget.amount_reversal)}
                            />
                        </div>
                        <div className="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4">
                            <p className="text-sm text-amber-900">
                                O estorno será marcado como excluído mas o histórico permanece auditável.
                                Não é possível excluir estornos já executados.
                            </p>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Motivo da Exclusão *
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
