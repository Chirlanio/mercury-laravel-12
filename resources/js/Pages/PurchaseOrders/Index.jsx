import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import {
    PlusIcon, MagnifyingGlassIcon, XMarkIcon, PencilSquareIcon,
    ShoppingCartIcon, TruckIcon, CurrencyDollarIcon, DocumentTextIcon,
    ClipboardDocumentListIcon, ClockIcon, BuildingStorefrontIcon,
    ArrowPathIcon, CheckCircleIcon, XCircleIcon, ExclamationTriangleIcon,
    InboxArrowDownIcon, BoltIcon, QrCodeIcon,
} from '@heroicons/react/24/outline';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import { maskMoney, parseMoney } from '@/Hooks/useMasks';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import StandardModal from '@/Components/StandardModal';
import PageHeader from '@/Components/Shared/PageHeader';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import FormSection from '@/Components/Shared/FormSection';
import DeleteConfirmModal from '@/Components/Shared/DeleteConfirmModal';
import EmptyState from '@/Components/Shared/EmptyState';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import { formatDate, formatDateTime } from '@/Utils/dateHelpers';

const STATUS_VARIANT_MAP = {
    warning: 'warning',
    info: 'info',
    purple: 'purple',
    danger: 'danger',
    success: 'success',
};

const formatCurrency = (value) => new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
}).format(Number(value || 0));

export default function Index({
    orders,
    filters = {},
    statistics = {},
    statusOptions = {},
    statusColors = {},
    statusTransitions = {},
    isStoreScoped = false,
    scopedStoreCode,
    selects = {},
}) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission(PERMISSIONS.CREATE_PURCHASE_ORDERS);
    const canEdit = hasPermission(PERMISSIONS.EDIT_PURCHASE_ORDERS);
    const canDelete = hasPermission(PERMISSIONS.DELETE_PURCHASE_ORDERS);
    const canApprove = hasPermission(PERMISSIONS.APPROVE_PURCHASE_ORDERS);
    const canReceive = hasPermission(PERMISSIONS.RECEIVE_PURCHASE_ORDERS);
    const canImport = hasPermission(PERMISSIONS.IMPORT_PURCHASE_ORDERS);
    const canExport = hasPermission(PERMISSIONS.EXPORT_PURCHASE_ORDERS);

    const { modals, selected, openModal, closeModal } = useModalManager([
        'create', 'edit', 'detail', 'transition', 'addItems', 'receipt',
    ]);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleting, setDeleting] = useState(false);

    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [supplierFilter, setSupplierFilter] = useState(filters.supplier_id || '');
    const [brandFilter, setBrandFilter] = useState(filters.brand_id || '');
    const [dateFrom, setDateFrom] = useState(filters.date_from || '');
    const [dateTo, setDateTo] = useState(filters.date_to || '');

    // Aplica filtros automaticamente — chamada por qualquer mudança de select/date.
    // Pra busca de texto, aplica no Enter (pra não disparar a cada tecla).
    const applyFilters = (overrides = {}) => {
        const params = {
            search: search || undefined,
            status: statusFilter || undefined,
            supplier_id: supplierFilter || undefined,
            brand_id: brandFilter || undefined,
            date_from: dateFrom || undefined,
            date_to: dateTo || undefined,
            ...overrides,
        };
        // Remove undefined pra não poluir a URL
        Object.keys(params).forEach((k) => params[k] === undefined && delete params[k]);
        router.get(route('purchase-orders.index'), params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    // Helpers pra filtros automáticos — seta estado + aplica imediatamente
    const setAndApply = (setter, key) => (e) => {
        const value = e.target.value;
        setter(value);
        applyFilters({ [key]: value || undefined });
    };

    const clearFilters = () => {
        setSearch(''); setStatusFilter(''); setSupplierFilter('');
        setBrandFilter(''); setDateFrom(''); setDateTo('');
        router.get(route('purchase-orders.index'), {}, { preserveState: true, preserveScroll: true });
    };

    const hasActiveFilters = search || statusFilter || supplierFilter || brandFilter || dateFrom || dateTo;

    const openDetail = (order) => {
        fetch(route('purchase-orders.show', order.id))
            .then((r) => r.json())
            .then((data) => openModal('detail', data.order));
    };

    const openEdit = (order) => {
        fetch(route('purchase-orders.show', order.id))
            .then((r) => r.json())
            .then((data) => openModal('edit', data.order));
    };

    const openTransition = (order) => {
        openModal('transition', order);
    };

    const openAddItems = (order) => {
        openModal('addItems', order);
    };

    const openReceipt = (order) => {
        // Recarrega detalhe pra ter os items mais recentes (com quantity_received atualizado)
        fetch(route('purchase-orders.show', order.id))
            .then((r) => r.json())
            .then((data) => openModal('receipt', data.order));
    };

    const handleMatchCigam = (order) => {
        router.post(route('purchase-orders.match-cigam', order.id), {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleGenerateBarcodes = async (orderId) => {
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            await fetch(route('purchase-orders.generate-barcodes', orderId), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            // Recarrega o detalhe da ordem pra mostrar os barcodes gerados
            const res = await fetch(route('purchase-orders.show', orderId), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (res.ok) {
                const data = await res.json();
                openModal('detail', data.order);
            }
        } catch (e) {
            // Fallback: recarrega a página
            router.reload();
        }
    };

    const handleConfirmDelete = (reason) => {
        if (!deleteTarget) return;
        setDeleting(true);
        router.delete(route('purchase-orders.destroy', deleteTarget.id), {
            data: { deleted_reason: reason },
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => { setDeleteTarget(null); setDeleting(false); },
            onError: () => setDeleting(false),
        });
    };

    const statsCards = useMemo(() => ([
        {
            label: 'Total de Ordens',
            value: statistics.total || 0,
            format: 'number',
            icon: ShoppingCartIcon,
            color: 'indigo',
        },
        {
            label: 'Pendentes',
            value: statistics.pending || 0,
            format: 'number',
            icon: ClockIcon,
            color: 'yellow',
            onClick: () => { setStatusFilter('pending'); applyFilters(); },
        },
        {
            label: 'Faturadas',
            value: (statistics.invoiced || 0) + (statistics.partial_invoiced || 0),
            format: 'number',
            icon: DocumentTextIcon,
            color: 'blue',
            sub: statistics.partial_invoiced ? `${statistics.partial_invoiced} parcial${statistics.partial_invoiced > 1 ? 'is' : ''}` : null,
        },
        {
            label: 'Entregues',
            value: statistics.delivered || 0,
            format: 'number',
            icon: CheckCircleIcon,
            color: 'green',
        },
        {
            label: 'Atrasadas',
            value: statistics.overdue || 0,
            format: 'number',
            icon: ExclamationTriangleIcon,
            color: 'red',
            active: (statistics.overdue || 0) > 0,
        },
    ]), [statistics]);

    return (
        <>
            <Head title="Ordens de Compra" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <PageHeader
                        title="Ordens de Compra"
                        subtitle="Gestão de pedidos de coleções com fornecedores"
                        scopeBadge={isStoreScoped ? `Loja ${scopedStoreCode}` : null}
                        actions={[
                            {
                                type: 'dashboard',
                                href: route('purchase-orders.dashboard'),
                            },
                            {
                                type: 'download',
                                download: route('purchase-orders.export', {
                                    format: 'excel',
                                    search: search || undefined,
                                    status: statusFilter || undefined,
                                    supplier_id: supplierFilter || undefined,
                                    brand_id: brandFilter || undefined,
                                    date_from: dateFrom || undefined,
                                    date_to: dateTo || undefined,
                                }),
                                visible: canExport,
                            },
                            {
                                type: 'import',
                                href: route('purchase-orders.import.page'),
                                visible: canImport,
                            },
                            {
                                type: 'create',
                                label: 'Nova Ordem',
                                onClick: () => openModal('create'),
                                visible: canCreate,
                            },
                        ]}
                    />

                    {/* Stats */}
                    <div className="mb-6">
                        <StatisticsGrid cards={statsCards} />
                    </div>

                    {/* Filtros — aplicados automaticamente ao mudar qualquer select/date.
                        Busca de texto aplica com Enter (pra não disparar a cada tecla). */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-7 gap-3 items-end">
                            <div className="md:col-span-2">
                                <label className="block text-xs font-medium text-gray-700 mb-1">Buscar</label>
                                <input type="text" placeholder="Nº ordem, descrição, estação..."
                                    value={search} onChange={(e) => setSearch(e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                                    onBlur={() => { if (search !== (filters.search || '')) applyFilters(); }}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">Status</label>
                                <select value={statusFilter} onChange={setAndApply(setStatusFilter, 'status')}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Todos</option>
                                    {Object.entries(statusOptions).map(([value, label]) => (
                                        <option key={value} value={value}>{label}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">Fornecedor</label>
                                <select value={supplierFilter} onChange={setAndApply(setSupplierFilter, 'supplier_id')}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Todos</option>
                                    {selects.suppliers?.map((s) => (
                                        <option key={s.id} value={s.id}>{s.nome_fantasia}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">Data de</label>
                                <input type="date" value={dateFrom} onChange={(e) => { setDateFrom(e.target.value); applyFilters({ date_from: e.target.value || undefined }); }}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">Data até</label>
                                <input type="date" value={dateTo} onChange={(e) => { setDateTo(e.target.value); applyFilters({ date_to: e.target.value || undefined }); }}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            </div>
                            <div>
                                <Button variant="outline" size="sm" onClick={clearFilters} disabled={!hasActiveFilters} icon={XMarkIcon} className="w-full">
                                    Limpar
                                </Button>
                            </div>
                        </div>
                    </div>

                    {/* Tabela */}
                    <div className="bg-white shadow-sm rounded-lg overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        {['Nº Ordem', 'Descrição / Estação', 'Fornecedor', 'Loja', 'Itens', 'Data', 'Previsão', 'Status', 'Ações'].map((h) => (
                                            <th key={h} className="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200">
                                    {orders.data?.length > 0 ? orders.data.map((o) => (
                                        <tr key={o.id} className={`hover:bg-gray-50 ${o.is_overdue ? 'bg-red-50/50' : ''}`}>
                                            <td className="px-3 py-3 text-sm font-mono font-medium text-gray-900">{o.order_number}</td>
                                            <td className="px-3 py-3 text-sm">
                                                <div className="font-medium text-gray-900">{o.short_description || '—'}</div>
                                                <div className="text-xs text-gray-500">{o.season} · {o.collection}</div>
                                            </td>
                                            <td className="px-3 py-3 text-sm text-gray-600">{o.supplier_name || '—'}</td>
                                            <td className="px-3 py-3 text-sm text-gray-600">{o.store_name || o.store_id}</td>
                                            <td className="px-3 py-3 text-sm text-center text-gray-700">{o.items_count || 0}</td>
                                            <td className="px-3 py-3 text-sm text-gray-500">{formatDate(o.order_date)}</td>
                                            <td className="px-3 py-3 text-sm">
                                                {o.predict_date ? (
                                                    <span className={o.is_overdue ? 'text-red-600 font-medium' : 'text-gray-500'}>
                                                        {formatDate(o.predict_date)}
                                                        {o.is_overdue && <ExclamationTriangleIcon className="inline h-3 w-3 ml-1" />}
                                                    </span>
                                                ) : '—'}
                                            </td>
                                            <td className="px-3 py-3">
                                                <StatusBadge variant={STATUS_VARIANT_MAP[o.status_color] || 'gray'}>
                                                    {o.status_label}
                                                </StatusBadge>
                                            </td>
                                            <td className="px-3 py-3">
                                                <ActionButtons
                                                    onView={() => openDetail(o)}
                                                    onEdit={canEdit && o.status === 'pending' ? () => openEdit(o) : null}
                                                    onDelete={canDelete && o.status === 'pending' ? () => setDeleteTarget(o) : null}
                                                >
                                                    {canEdit && !o.is_terminal && o.status !== 'cancelled' && (
                                                        <ActionButtons.Custom
                                                            icon={ArrowPathIcon}
                                                            title="Transicionar status"
                                                            onClick={() => openTransition(o)}
                                                            className="text-blue-600 hover:text-blue-800"
                                                        />
                                                    )}
                                                    {canEdit && o.status === 'pending' && (
                                                        <ActionButtons.Custom
                                                            icon={ClipboardDocumentListIcon}
                                                            title="Adicionar itens"
                                                            onClick={() => openAddItems(o)}
                                                            className="text-green-600 hover:text-green-800"
                                                        />
                                                    )}
                                                    {canReceive && !o.is_terminal && o.status !== 'cancelled' && o.status !== 'pending' && (
                                                        <ActionButtons.Custom
                                                            icon={InboxArrowDownIcon}
                                                            title="Registrar recebimento"
                                                            onClick={() => openReceipt(o)}
                                                            className="text-teal-600 hover:text-teal-800"
                                                        />
                                                    )}
                                                </ActionButtons>
                                            </td>
                                        </tr>
                                    )) : (
                                        <tr>
                                            <td colSpan="9" className="px-3 py-12">
                                                <EmptyState
                                                    icon={ShoppingCartIcon}
                                                    title="Nenhuma ordem encontrada"
                                                    description="Ajuste os filtros ou crie uma nova ordem de compra."
                                                    compact
                                                />
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                        {orders.last_page > 1 && (
                            <div className="px-4 py-3 border-t flex justify-between items-center">
                                <span className="text-sm text-gray-700">{orders.from} a {orders.to} de {orders.total}</span>
                                <div className="flex space-x-1">
                                    {orders.links.map((link, i) => (
                                        <button key={i} onClick={() => link.url && router.get(link.url)} disabled={!link.url}
                                            className={`px-3 py-1 text-sm rounded ${link.active ? 'bg-indigo-600 text-white' : link.url ? 'bg-white text-gray-700 hover:bg-gray-50 border' : 'bg-gray-100 text-gray-400'}`}
                                            dangerouslySetInnerHTML={{ __html: link.label }} />
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Modais */}
                    <PurchaseOrderFormModal
                        show={modals.create}
                        onClose={() => closeModal('create')}
                        selects={selects}
                        isStoreScoped={isStoreScoped}
                        scopedStoreCode={scopedStoreCode}
                    />
                    {modals.edit && selected && (
                        <PurchaseOrderFormModal
                            show={true}
                            order={selected}
                            onClose={() => closeModal('edit')}
                            selects={selects}
                            isStoreScoped={isStoreScoped}
                            scopedStoreCode={scopedStoreCode}
                        />
                    )}
                    {modals.detail && selected && (
                        <PurchaseOrderDetailModal
                            order={selected}
                            onClose={() => closeModal('detail')}
                            onGenerateBarcodes={() => handleGenerateBarcodes(selected.id)}
                            canEdit={canEdit}
                        />
                    )}
                    {modals.transition && selected && (
                        <PurchaseOrderTransitionModal
                            order={selected}
                            statusTransitions={statusTransitions}
                            statusOptions={statusOptions}
                            onClose={() => closeModal('transition')}
                        />
                    )}
                    {modals.addItems && selected && (
                        <AddItemsModal
                            order={selected}
                            onClose={() => closeModal('addItems')}
                        />
                    )}
                    {modals.receipt && selected && (
                        <RegisterReceiptModal
                            order={selected}
                            onClose={() => closeModal('receipt')}
                            onMatchCigam={() => handleMatchCigam(selected)}
                        />
                    )}

                    {deleteTarget && (
                        <DeleteWithReasonModal
                            show={true}
                            onClose={() => setDeleteTarget(null)}
                            onConfirm={handleConfirmDelete}
                            orderNumber={deleteTarget.order_number}
                            processing={deleting}
                        />
                    )}
                </div>
            </div>
        </>
    );
}

// ========================================================================
// FORM (create / edit)
// ========================================================================

function PurchaseOrderFormModal({ show, order = null, onClose, selects, isStoreScoped, scopedStoreCode }) {
    const isEdit = !!order;
    const form = useForm({
        order_number: order?.order_number || '',
        short_description: order?.short_description || '',
        season: order?.season || '',
        collection: order?.collection || '',
        release_name: order?.release_name || '',
        supplier_id: order?.supplier_id || '',
        store_id: order?.store_id || (isStoreScoped ? scopedStoreCode : ''),
        brand_id: order?.brand_id || '',
        order_date: order?.order_date || new Date().toISOString().slice(0, 10),
        predict_date: order?.predict_date || '',
        payment_terms_raw: order?.payment_terms_raw || '',
        auto_generate_payments: order?.auto_generate_payments || false,
        notes: order?.notes || '',
    });

    const handleSupplierChange = (supplierId) => {
        form.setData('supplier_id', supplierId);
        // Auto-preencher payment_terms_raw com o default do fornecedor se vazio
        if (!form.data.payment_terms_raw && supplierId) {
            const supplier = selects.suppliers?.find((s) => String(s.id) === String(supplierId));
            if (supplier?.payment_terms_default) {
                form.setData('payment_terms_raw', supplier.payment_terms_default);
            }
        }
    };

    const handleSubmit = () => {
        if (isEdit) {
            form.put(route('purchase-orders.update', order.id), { preserveState: true, preserveScroll: true, onSuccess: onClose });
        } else {
            form.post(route('purchase-orders.store'), { preserveState: true, preserveScroll: true, onSuccess: onClose });
        }
    };

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title={isEdit ? 'Editar Ordem de Compra' : 'Nova Ordem de Compra'}
            headerColor={isEdit ? 'bg-yellow-600' : 'bg-indigo-600'}
            headerIcon={isEdit ? <PencilSquareIcon className="h-5 w-5" /> : <PlusIcon className="h-5 w-5" />}
            onSubmit={handleSubmit}
            maxWidth="3xl"
            footer={(
                <StandardModal.Footer
                    onCancel={onClose}
                    onSubmit="submit"
                    submitLabel={isEdit ? 'Salvar Alterações' : 'Criar Ordem'}
                    submitColor={isEdit ? 'bg-yellow-600 hover:bg-yellow-700' : undefined}
                    processing={form.processing}
                />
            )}
        >
            <FormSection title="Identificação" cols={2}>
                <div>
                    <InputLabel value="Nº Ordem *" />
                    <TextInput className="mt-1 w-full font-mono" value={form.data.order_number}
                        onChange={(e) => form.setData('order_number', e.target.value)} required />
                    <InputError message={form.errors.order_number} className="mt-1" />
                </div>
                <div>
                    <InputLabel value="Descrição breve" />
                    <TextInput className="mt-1 w-full" value={form.data.short_description}
                        onChange={(e) => form.setData('short_description', e.target.value)}
                        placeholder="Ex: Inverno 2026 - Anacapri" />
                    <InputError message={form.errors.short_description} className="mt-1" />
                </div>
                <div>
                    <InputLabel value="Estação *" />
                    <TextInput className="mt-1 w-full" value={form.data.season}
                        onChange={(e) => form.setData('season', e.target.value)}
                        placeholder="Ex: INVERNO 2026" required />
                    <InputError message={form.errors.season} className="mt-1" />
                </div>
                <div>
                    <InputLabel value="Coleção *" />
                    <TextInput className="mt-1 w-full" value={form.data.collection}
                        onChange={(e) => form.setData('collection', e.target.value)}
                        placeholder="Ex: INVERNO 1" required />
                    <InputError message={form.errors.collection} className="mt-1" />
                </div>
                <div className="col-span-2">
                    <InputLabel value="Lançamento *" />
                    <TextInput className="mt-1 w-full" value={form.data.release_name}
                        onChange={(e) => form.setData('release_name', e.target.value)}
                        placeholder="Ex: Lançamento 1" required />
                    <InputError message={form.errors.release_name} className="mt-1" />
                </div>
            </FormSection>

            <FormSection title="Fornecedor, Loja e Marca" cols={3}>
                <div>
                    <InputLabel value="Fornecedor *" />
                    <select value={form.data.supplier_id}
                        onChange={(e) => handleSupplierChange(e.target.value)}
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
                        <option value="">Selecione...</option>
                        {selects.suppliers?.map((s) => (
                            <option key={s.id} value={s.id}>{s.nome_fantasia}</option>
                        ))}
                    </select>
                    <InputError message={form.errors.supplier_id} className="mt-1" />
                </div>
                <div>
                    <InputLabel value="Loja destino *" />
                    <select value={form.data.store_id}
                        onChange={(e) => form.setData('store_id', e.target.value)}
                        disabled={isStoreScoped}
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-100" required>
                        <option value="">Selecione...</option>
                        {selects.stores?.map((s) => (
                            <option key={s.code} value={s.code}>{s.name}</option>
                        ))}
                    </select>
                    <InputError message={form.errors.store_id} className="mt-1" />
                </div>
                <div>
                    <InputLabel value="Marca" />
                    <select value={form.data.brand_id}
                        onChange={(e) => form.setData('brand_id', e.target.value)}
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="">Nenhuma</option>
                        {selects.brands?.map((b) => (
                            <option key={b.id} value={b.id}>{b.name}</option>
                        ))}
                    </select>
                    <InputError message={form.errors.brand_id} className="mt-1" />
                </div>
            </FormSection>

            <FormSection title="Datas e Condições" cols={3}>
                <div>
                    <InputLabel value="Data do pedido *" />
                    <TextInput type="date" className="mt-1 w-full" value={form.data.order_date}
                        onChange={(e) => form.setData('order_date', e.target.value)} required />
                    <InputError message={form.errors.order_date} className="mt-1" />
                </div>
                <div>
                    <InputLabel value="Previsão de entrega" />
                    <TextInput type="date" className="mt-1 w-full" value={form.data.predict_date}
                        onChange={(e) => form.setData('predict_date', e.target.value)} />
                    <InputError message={form.errors.predict_date} className="mt-1" />
                </div>
                <div>
                    <InputLabel value="Prazos de pagamento" />
                    <TextInput className="mt-1 w-full" value={form.data.payment_terms_raw}
                        onChange={(e) => form.setData('payment_terms_raw', e.target.value)}
                        placeholder="Ex: 30/60/90" />
                    <p className="mt-1 text-xs text-gray-500">Em dias, separados por barra</p>
                    <InputError message={form.errors.payment_terms_raw} className="mt-1" />
                </div>
            </FormSection>

            <FormSection title="Observações" cols={1}>
                <div>
                    <textarea rows={3}
                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        value={form.data.notes}
                        onChange={(e) => form.setData('notes', e.target.value)}
                        placeholder="Anotações internas sobre esta ordem..." />
                    <InputError message={form.errors.notes} className="mt-1" />
                </div>
            </FormSection>
        </StandardModal>
    );
}

// ========================================================================
// DETAIL
// ========================================================================

function PurchaseOrderDetailModal({ order, onClose, onGenerateBarcodes, canEdit }) {
    const headerBadges = [
        {
            text: order.status_label,
            className: 'bg-white/20 text-white',
        },
    ];

    const itemsWithoutBarcode = order.items?.filter((i) => !i.barcode).length || 0;

    return (
        <StandardModal
            show={true}
            onClose={onClose}
            title={`Ordem #${order.order_number}`}
            subtitle={order.short_description || `${order.season} · ${order.collection}`}
            headerColor="bg-gray-700"
            headerIcon={<ShoppingCartIcon className="h-5 w-5" />}
            headerBadges={headerBadges}
            headerActions={canEdit && itemsWithoutBarcode > 0 && order.items?.length > 0 ? (
                <button
                    type="button"
                    onClick={onGenerateBarcodes}
                    className="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md bg-white/20 text-white hover:bg-white/30 transition-colors"
                    title="Gerar códigos de barras EAN-13 internos para itens sem código"
                >
                    <QrCodeIcon className="h-4 w-4 mr-1" />
                    Gerar Cód. Barras ({itemsWithoutBarcode})
                </button>
            ) : null}
            maxWidth="7xl"
            footer={<StandardModal.Footer onCancel={onClose} cancelLabel="Fechar" />}
        >
            <StandardModal.Section title="Resumo" icon={<DocumentTextIcon className="h-4 w-4" />}>
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <StandardModal.InfoCard label="Unidades" value={order.total_units || 0} />
                    <StandardModal.InfoCard label="Custo total" value={formatCurrency(order.total_cost)} />
                    <StandardModal.InfoCard label="Venda total" value={formatCurrency(order.total_selling)} />
                    <StandardModal.InfoCard label="Itens" value={order.items?.length || 0} />
                </div>
            </StandardModal.Section>

            <StandardModal.Section title="Dados da Ordem">
                <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <StandardModal.Field label="Estação" value={order.season} />
                    <StandardModal.Field label="Coleção" value={order.collection} />
                    <StandardModal.Field label="Lançamento" value={order.release_name} />
                    <StandardModal.Field label="Data do pedido" value={formatDate(order.order_date)} />
                    <StandardModal.Field label="Previsão" value={formatDate(order.predict_date)} />
                    <StandardModal.Field label="Entregue em" value={order.delivered_at ? formatDateTime(order.delivered_at) : null} />
                    <StandardModal.Field label="Prazos pagamento" value={order.payment_terms_raw} />
                    <StandardModal.Field label="Loja destino" value={order.store_name || order.store_id} />
                    <StandardModal.Field label="Marca" value={order.brand_name} />
                </div>
            </StandardModal.Section>

            {order.supplier && (
                <StandardModal.Section title="Fornecedor" icon={<TruckIcon className="h-4 w-4" />}>
                    <div className="grid grid-cols-2 gap-4">
                        <StandardModal.Field label="Razão social" value={order.supplier.razao_social} />
                        <StandardModal.Field label="Nome fantasia" value={order.supplier.nome_fantasia} />
                        <StandardModal.Field label="CNPJ" value={order.supplier.cnpj} mono />
                        <StandardModal.Field label="Prazos padrão" value={order.supplier.payment_terms_default} />
                    </div>
                </StandardModal.Section>
            )}

            <StandardModal.Section title={`Itens (${order.items?.length || 0})`} icon={<ClipboardDocumentListIcon className="h-4 w-4" />}>
                {order.items?.length > 0 ? (
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-gray-50">
                                <tr>
                                    {['Ref.', 'Descrição', 'Tam.', 'Cód. Barras', 'NF', 'Emissão', 'Qtd', 'Recebido', 'Custo', 'Venda', 'Total'].map((h) => (
                                        <th key={h} className="px-2 py-2 text-left text-xs font-medium text-gray-500">{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {order.items.map((item) => (
                                    <tr key={item.id}>
                                        <td className="px-2 py-1 font-mono text-xs">{item.reference}</td>
                                        <td className="px-2 py-1">
                                            <div>{item.description}</div>
                                            {(item.material || item.color) && (
                                                <div className="text-xs text-gray-500">{[item.material, item.color].filter(Boolean).join(' · ')}</div>
                                            )}
                                        </td>
                                        <td className="px-2 py-1 text-center">{item.size}</td>
                                        <td className="px-2 py-1 font-mono text-xs">
                                            {item.barcode ? (
                                                <span title={item.barcode_source === 'catalog' ? 'Código do catálogo (CIGAM)' : 'EAN-13 interno'}>
                                                    {item.barcode}
                                                    {item.barcode_source === 'catalog' && (
                                                        <span className="ml-1 text-[9px] text-green-600" title="Catálogo">●</span>
                                                    )}
                                                </span>
                                            ) : (
                                                <span className="text-gray-300">—</span>
                                            )}
                                        </td>
                                        <td className="px-2 py-1 font-mono text-xs">
                                            {item.invoice_number || <span className="text-gray-300">—</span>}
                                        </td>
                                        <td className="px-2 py-1 text-xs text-gray-500">
                                            {formatDate(item.invoice_emission_date)}
                                        </td>
                                        <td className="px-2 py-1 text-center">{item.quantity_ordered}</td>
                                        <td className="px-2 py-1 text-center">
                                            <span className={item.is_fully_received ? 'text-green-600 font-medium' : 'text-gray-600'}>
                                                {item.quantity_received}
                                            </span>
                                        </td>
                                        <td className="px-2 py-1 text-right">{formatCurrency(item.unit_cost)}</td>
                                        <td className="px-2 py-1 text-right">{formatCurrency(item.selling_price)}</td>
                                        <td className="px-2 py-1 text-right font-medium">{formatCurrency(item.total_cost)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <p className="text-sm text-gray-500 italic text-center py-4">Nenhum item adicionado ainda.</p>
                )}
            </StandardModal.Section>

            {order.notes && (
                <StandardModal.Section title="Observações">
                    <p className="text-sm text-gray-700 whitespace-pre-wrap">{order.notes}</p>
                </StandardModal.Section>
            )}

            <StandardModal.Section
                title={`Recebimentos (${order.receipts?.length || 0})`}
                icon={<InboxArrowDownIcon className="h-4 w-4" />}
            >
                {order.receipts?.length > 0 ? (
                    <div className="space-y-3">
                        {order.receipts.map((r) => (
                            <div key={r.id} className="border rounded-lg p-3 bg-gray-50">
                                <div className="flex justify-between items-start mb-2">
                                    <div>
                                        <div className="text-sm font-medium text-gray-900">
                                            {r.invoice_number ? `NF ${r.invoice_number}` : 'Sem NF'}
                                            {r.is_from_cigam && (
                                                <span className="ml-2 inline-flex items-center bg-blue-100 text-blue-800 text-xs px-2 py-0.5 rounded-full">
                                                    <BoltIcon className="h-3 w-3 mr-1" />
                                                    CIGAM
                                                </span>
                                            )}
                                        </div>
                                        <div className="text-xs text-gray-500">
                                            {formatDateTime(r.received_at)}
                                            {r.created_by_name && ` · ${r.created_by_name}`}
                                        </div>
                                    </div>
                                    <div className="text-right text-sm font-medium text-gray-700">
                                        {r.total_quantity} un.
                                    </div>
                                </div>
                                {r.notes && <p className="text-xs text-gray-600 italic mb-2">{r.notes}</p>}
                                <div className="text-xs space-y-1">
                                    {r.items?.map((ri) => (
                                        <div key={ri.id} className="flex justify-between text-gray-600">
                                            <span>
                                                <span className="font-mono">{ri.reference}</span>/{ri.size} — {ri.description}
                                            </span>
                                            <span className="font-medium">
                                                {ri.quantity_received} un.
                                                {ri.unit_cost_cigam && ` · ${formatCurrency(ri.unit_cost_cigam)}`}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <p className="text-sm text-gray-500 italic text-center py-4">
                        Nenhum recebimento registrado ainda.
                    </p>
                )}
            </StandardModal.Section>

            <StandardModal.Section title="Histórico de Status" icon={<ClockIcon className="h-4 w-4" />}>
                <StandardModal.Timeline
                    items={order.status_history?.map((h) => ({
                        label: `${h.from_status || '—'} → ${h.to_status}`,
                        value: h.note || '',
                        meta: `${h.changed_by_name || 'Sistema'} em ${formatDateTime(h.created_at)}`,
                    })) || []}
                />
            </StandardModal.Section>
        </StandardModal>
    );
}

// ========================================================================
// TRANSITION
// ========================================================================

function PurchaseOrderTransitionModal({ order, statusTransitions, statusOptions, onClose }) {
    const available = statusTransitions[order.status] || [];
    const form = useForm({ to_status: available[0] || '', note: '' });

    const handleSubmit = () => {
        form.post(route('purchase-orders.transition', order.id), { preserveState: true, preserveScroll: true, onSuccess: onClose });
    };

    return (
        <StandardModal
            show={true}
            onClose={onClose}
            title={`Transição — Ordem #${order.order_number}`}
            subtitle={`Status atual: ${order.status_label}`}
            headerColor="bg-blue-600"
            headerIcon={<ArrowPathIcon className="h-5 w-5" />}
            onSubmit={handleSubmit}
            footer={(
                <StandardModal.Footer
                    onCancel={onClose}
                    onSubmit="submit"
                    submitLabel="Transicionar"
                    processing={form.processing}
                />
            )}
        >
            <FormSection title="Nova situação" cols={1}>
                <div>
                    <InputLabel value="Mover para *" />
                    <select value={form.data.to_status}
                        onChange={(e) => form.setData('to_status', e.target.value)}
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" required>
                        {available.length === 0 ? (
                            <option value="">Nenhuma transição disponível</option>
                        ) : available.map((s) => (
                            <option key={s} value={s}>{statusOptions[s]}</option>
                        ))}
                    </select>
                    <InputError message={form.errors.to_status} className="mt-1" />
                </div>
                <div>
                    <InputLabel value={form.data.to_status === 'cancelled' ? 'Motivo do cancelamento *' : 'Observação'} />
                    <textarea rows={3}
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        value={form.data.note}
                        onChange={(e) => form.setData('note', e.target.value)}
                        required={form.data.to_status === 'cancelled'} />
                    <InputError message={form.errors.note} className="mt-1" />
                </div>
            </FormSection>
        </StandardModal>
    );
}

// ========================================================================
// ADD ITEMS (size matrix)
// ========================================================================

function AddItemsModal({ order, onClose }) {
    const [item, setItem] = useState({
        reference: '',
        description: '',
        material: '',
        color: '',
        unit_cost: '',
        selling_price: '',
        sizes: {},
    });

    const [sizeInput, setSizeInput] = useState('');
    const [qtyInput, setQtyInput] = useState('');
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});

    const addSize = () => {
        if (!sizeInput || !qtyInput) return;
        setItem((prev) => ({
            ...prev,
            sizes: { ...prev.sizes, [sizeInput]: parseInt(qtyInput, 10) },
        }));
        setSizeInput('');
        setQtyInput('');
    };

    const removeSize = (size) => {
        setItem((prev) => {
            const next = { ...prev.sizes };
            delete next[size];
            return { ...prev, sizes: next };
        });
    };

    const handleSubmit = () => {
        setProcessing(true);
        setErrors({});
        router.post(route('purchase-orders.items.store', order.id), {
            items: [{
                reference: item.reference,
                description: item.description,
                material: item.material || null,
                color: item.color || null,
                unit_cost: parseMoney(item.unit_cost),
                selling_price: parseMoney(item.selling_price),
                sizes: item.sizes,
            }],
        }, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => { setProcessing(false); onClose(); },
            onError: (errs) => { setErrors(errs); setProcessing(false); },
        });
    };

    const canSubmit = item.reference && item.description && item.unit_cost && Object.keys(item.sizes).length > 0;

    return (
        <StandardModal
            show={true}
            onClose={onClose}
            title={`Adicionar Itens — Ordem #${order.order_number}`}
            headerColor="bg-green-600"
            headerIcon={<ClipboardDocumentListIcon className="h-5 w-5" />}
            maxWidth="3xl"
            footer={(
                <StandardModal.Footer
                    onCancel={onClose}
                    onSubmit={handleSubmit}
                    submitLabel="Adicionar Itens"
                    submitDisabled={!canSubmit}
                    processing={processing}
                />
            )}
        >
            <FormSection title="Produto" cols={2}>
                <div>
                    <InputLabel value="Referência *" />
                    <TextInput className="mt-1 w-full font-mono"
                        value={item.reference}
                        onChange={(e) => setItem({ ...item, reference: e.target.value })} />
                    <InputError message={errors['items.0.reference']} className="mt-1" />
                </div>
                <div>
                    <InputLabel value="Descrição *" />
                    <TextInput className="mt-1 w-full"
                        value={item.description}
                        onChange={(e) => setItem({ ...item, description: e.target.value })} />
                    <InputError message={errors['items.0.description']} className="mt-1" />
                </div>
                <div>
                    <InputLabel value="Material" />
                    <TextInput className="mt-1 w-full"
                        value={item.material}
                        onChange={(e) => setItem({ ...item, material: e.target.value })} />
                </div>
                <div>
                    <InputLabel value="Cor" />
                    <TextInput className="mt-1 w-full"
                        value={item.color}
                        onChange={(e) => setItem({ ...item, color: e.target.value })} />
                </div>
                <div>
                    <InputLabel value="Custo unitário *" />
                    <TextInput className="mt-1 w-full"
                        value={item.unit_cost}
                        onChange={(e) => setItem({ ...item, unit_cost: maskMoney(e.target.value) })}
                        placeholder="0,00" />
                    <InputError message={errors['items.0.unit_cost']} className="mt-1" />
                </div>
                <div>
                    <InputLabel value="Preço de venda" />
                    <TextInput className="mt-1 w-full"
                        value={item.selling_price}
                        onChange={(e) => setItem({ ...item, selling_price: maskMoney(e.target.value) })}
                        placeholder="0,00" />
                </div>
            </FormSection>

            <FormSection title="Matriz de Tamanhos" cols={1}>
                <div className="flex gap-2 items-end">
                    <div className="flex-1">
                        <InputLabel value="Tamanho" />
                        <TextInput className="mt-1 w-full uppercase"
                            value={sizeInput}
                            onChange={(e) => setSizeInput(e.target.value.toUpperCase())}
                            placeholder="Ex: 34, M" />
                    </div>
                    <div className="flex-1">
                        <InputLabel value="Quantidade" />
                        <TextInput type="number" className="mt-1 w-full"
                            value={qtyInput}
                            onChange={(e) => setQtyInput(e.target.value)}
                            min="1" />
                    </div>
                    <Button variant="success" size="sm" onClick={addSize} icon={PlusIcon}>Adicionar</Button>
                </div>
                {Object.keys(item.sizes).length > 0 && (
                    <div className="mt-3 flex flex-wrap gap-2">
                        {Object.entries(item.sizes).map(([size, qty]) => (
                            <span key={size} className="inline-flex items-center bg-indigo-100 text-indigo-800 rounded-full px-3 py-1 text-sm">
                                <strong className="mr-1">{size}:</strong> {qty}
                                <button type="button" onClick={() => removeSize(size)} className="ml-2 text-indigo-600 hover:text-indigo-900">
                                    <XMarkIcon className="h-3 w-3" />
                                </button>
                            </span>
                        ))}
                    </div>
                )}
            </FormSection>
        </StandardModal>
    );
}

// ========================================================================
// REGISTER RECEIPT (manual + matcher CIGAM)
// ========================================================================

function RegisterReceiptModal({ order, onClose, onMatchCigam }) {
    // Pré-preenche o campo NF com a NF mais frequente dos itens da ordem
    // (a planilha v1 importa invoice_number em cada item — aproveita esse dado)
    const defaultNf = useMemo(() => {
        const nfs = order.items?.map((i) => i.invoice_number).filter(Boolean) || [];
        if (nfs.length === 0) return '';
        const counts = {};
        nfs.forEach((nf) => { counts[nf] = (counts[nf] || 0) + 1; });
        return Object.entries(counts).sort((a, b) => b[1] - a[1])[0]?.[0] || '';
    }, [order]);

    const [invoice, setInvoice] = useState(defaultNf);
    const [notes, setNotes] = useState('');
    // Map: itemId → quantidade a receber
    const [quantities, setQuantities] = useState({});
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});

    const updateQty = (itemId, value) => {
        const num = parseInt(value, 10);
        setQuantities((prev) => ({
            ...prev,
            [itemId]: isNaN(num) || num <= 0 ? undefined : num,
        }));
    };

    const fillRemaining = (item) => {
        const remaining = item.quantity_ordered - item.quantity_received;
        updateQty(item.id, remaining);
    };

    const fillAllRemaining = () => {
        const next = {};
        order.items?.forEach((item) => {
            const rem = item.quantity_ordered - item.quantity_received;
            if (rem > 0) next[item.id] = rem;
        });
        setQuantities(next);
    };

    const itemsToSubmit = Object.entries(quantities)
        .filter(([, qty]) => qty > 0)
        .map(([id, qty]) => ({ purchase_order_item_id: parseInt(id, 10), quantity: qty }));

    const handleSubmit = () => {
        if (itemsToSubmit.length === 0) return;

        setProcessing(true);
        setErrors({});
        router.post(route('purchase-orders.receipts.store', order.id), {
            invoice_number: invoice || null,
            notes: notes || null,
            items: itemsToSubmit,
        }, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                setProcessing(false);
                onClose();
            },
            onError: (errs) => {
                setErrors(errs);
                setProcessing(false);
            },
        });
    };

    const itemsWithSaldo = order.items?.filter((i) => i.quantity_ordered > i.quantity_received) || [];

    return (
        <StandardModal
            show={true}
            onClose={onClose}
            title={`Registrar Recebimento — Ordem #${order.order_number}`}
            subtitle={order.short_description || `${order.season} · ${order.collection}`}
            headerColor="bg-teal-600"
            headerIcon={<InboxArrowDownIcon className="h-5 w-5" />}
            headerActions={(
                <button
                    type="button"
                    onClick={onMatchCigam}
                    className="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md bg-white/20 text-white hover:bg-white/30 transition-colors"
                    title="Buscar movimentos do CIGAM e criar recebimento automático"
                >
                    <BoltIcon className="h-4 w-4 mr-1" />
                    Buscar no CIGAM
                </button>
            )}
            maxWidth="4xl"
            footer={(
                <StandardModal.Footer
                    onCancel={onClose}
                    onSubmit={handleSubmit}
                    submitLabel={`Confirmar (${itemsToSubmit.length} item${itemsToSubmit.length !== 1 ? 's' : ''})`}
                    submitColor="bg-teal-600 hover:bg-teal-700"
                    submitDisabled={itemsToSubmit.length === 0}
                    processing={processing}
                />
            )}
        >
            <FormSection title="Dados da Nota Fiscal" cols={2}>
                <div>
                    <InputLabel value="Número da NF" />
                    <TextInput className="mt-1 w-full font-mono"
                        value={invoice}
                        onChange={(e) => setInvoice(e.target.value)}
                        placeholder="Ex: 12345" />
                    <InputError message={errors.invoice_number} className="mt-1" />
                </div>
                <div>
                    <InputLabel value="Observação" />
                    <TextInput className="mt-1 w-full"
                        value={notes}
                        onChange={(e) => setNotes(e.target.value)}
                        placeholder="Opcional" />
                </div>
            </FormSection>

            <FormSection title="Itens" cols={1}>
                {itemsWithSaldo.length === 0 ? (
                    <p className="text-sm text-gray-500 italic text-center py-4">
                        Todos os itens desta ordem já foram totalmente recebidos.
                    </p>
                ) : (
                    <>
                        <div className="flex justify-end mb-2">
                            <Button variant="outline" size="xs" onClick={fillAllRemaining}>
                                Preencher saldo total
                            </Button>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="bg-gray-50">
                                    <tr>
                                        {['Ref.', 'Descrição', 'Tam.', 'Pedido', 'Já recebido', 'Saldo', 'A receber'].map((h) => (
                                            <th key={h} className="px-2 py-2 text-left text-xs font-medium text-gray-500">{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {itemsWithSaldo.map((item) => {
                                        const remaining = item.quantity_ordered - item.quantity_received;
                                        return (
                                            <tr key={item.id}>
                                                <td className="px-2 py-1 font-mono text-xs">{item.reference}</td>
                                                <td className="px-2 py-1">{item.description}</td>
                                                <td className="px-2 py-1 text-center">{item.size}</td>
                                                <td className="px-2 py-1 text-center">{item.quantity_ordered}</td>
                                                <td className="px-2 py-1 text-center text-gray-500">{item.quantity_received}</td>
                                                <td className="px-2 py-1 text-center font-medium">{remaining}</td>
                                                <td className="px-2 py-1">
                                                    <div className="flex gap-1">
                                                        <input type="number" min="0" max={remaining}
                                                            value={quantities[item.id] ?? ''}
                                                            onChange={(e) => updateQty(item.id, e.target.value)}
                                                            className="w-20 rounded border-gray-300 text-sm" />
                                                        <button type="button" onClick={() => fillRemaining(item)}
                                                            className="text-xs text-teal-600 hover:text-teal-800"
                                                            title="Receber tudo">
                                                            ↑
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                        <InputError message={errors.items} className="mt-2" />
                    </>
                )}
            </FormSection>
        </StandardModal>
    );
}

// ========================================================================
// DELETE with reason
// ========================================================================

function DeleteWithReasonModal({ show, onClose, onConfirm, orderNumber, processing }) {
    const [reason, setReason] = useState('');

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title={`Excluir Ordem #${orderNumber}`}
            headerColor="bg-red-600"
            headerIcon={<XCircleIcon className="h-5 w-5" />}
            footer={(
                <StandardModal.Footer
                    onCancel={onClose}
                    onSubmit={() => onConfirm(reason)}
                    submitLabel="Confirmar Exclusão"
                    submitColor="bg-red-600 hover:bg-red-700"
                    submitDisabled={reason.trim().length < 3}
                    processing={processing}
                />
            )}
        >
            <p className="text-sm text-gray-700 mb-4">
                Esta ação marcará a ordem como excluída. O registro será preservado para auditoria.
            </p>
            <InputLabel value="Motivo da exclusão *" />
            <textarea rows={3}
                className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm"
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                placeholder="Descreva o motivo (mínimo 3 caracteres)..." />
        </StandardModal>
    );
}
