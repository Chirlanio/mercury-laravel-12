import PageHeader from '@/Components/PageHeader';
import { Head, router, useForm } from '@inertiajs/react';
import {
    PlusIcon, MagnifyingGlassIcon, Squares2X2Icon, TableCellsIcon,
    ArrowRightIcon, ArrowLeftIcon, BookmarkIcon, DocumentCheckIcon,
    ExclamationTriangleIcon, ChartBarIcon, ArrowDownTrayIcon,
} from '@heroicons/react/24/outline';
import { useState, useMemo } from 'react';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import { maskMoney, parseMoney, maskCpfCnpj, maskPhone, handleMasked } from '@/Hooks/useMasks';
import ActionButtons from '@/Components/ActionButtons';
import DashboardCharts from '@/Components/OrderPayments/DashboardCharts';
import DetailModal from '@/Components/OrderPayments/DetailModal';

const STATUS_COLORS = {
    backlog: { bg: 'bg-gray-100', text: 'text-gray-800', border: 'border-gray-300', header: 'bg-gray-600' },
    doing:   { bg: 'bg-blue-100', text: 'text-blue-800', border: 'border-blue-300', header: 'bg-blue-600' },
    waiting: { bg: 'bg-yellow-100', text: 'text-yellow-800', border: 'border-yellow-300', header: 'bg-yellow-500' },
    done:    { bg: 'bg-green-100', text: 'text-green-800', border: 'border-green-300', header: 'bg-green-600' },
};

const fmtCurrency = (v) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0);
const nextStatus = (s) => ({ backlog: 'doing', doing: 'waiting', waiting: 'done' }[s]);
const prevStatus = (s) => ({ doing: 'backlog', waiting: 'doing', done: 'waiting' }[s]);
const isForward = (from, to) => {
    const o = ['backlog', 'doing', 'waiting', 'done'];
    return o.indexOf(to) > o.indexOf(from);
};

export default function Index({
    payments, selects = {}, filters = {}, statusOptions = {},
    kanbanData = {}, kanbanCards = {},
}) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission(PERMISSIONS.CREATE_ORDER_PAYMENTS);
    const canEdit = hasPermission(PERMISSIONS.EDIT_ORDER_PAYMENTS);

    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [storeFilter, setStoreFilter] = useState(filters.store_id || '');
    const [viewMode, setViewMode] = useState('kanban');
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [showTransitionModal, setShowTransitionModal] = useState(false);
    const [transitionData, setTransitionData] = useState(null);
    const [transitionError, setTransitionError] = useState('');
    const [showDashboard, setShowDashboard] = useState(false);
    const [detailOrderId, setDetailOrderId] = useState(null);

    const applyFilters = () => {
        router.get(route('order-payments.index'), {
            search: search || undefined, status: statusFilter || undefined, store_id: storeFilter || undefined,
        }, { preserveState: true });
    };

    const openTransitionModal = (order, newSt) => {
        setTransitionData({ order, newStatus: newSt });
        setTransitionError('');
        setShowTransitionModal(true);
    };

    // ======== RENDER ========
    return (
        <>
            <Head title="Ordens de Pagamento" />
            <PageHeader>
                <div className="flex justify-between items-center">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">Ordens de Pagamento</h2>
                    <div className="flex items-center space-x-3">
                        <ViewToggle viewMode={viewMode} setViewMode={setViewMode} />
                        <button onClick={() => setShowDashboard(true)}
                            className="inline-flex items-center px-3 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-50"
                            title="Dashboard">
                            <ChartBarIcon className="h-4 w-4" />
                        </button>
                        <a href={route('order-payments.export', { search: search || undefined, status: statusFilter || undefined, store_id: storeFilter || undefined })}
                            className="inline-flex items-center px-3 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-50"
                            title="Exportar Excel">
                            <ArrowDownTrayIcon className="h-4 w-4" />
                        </a>
                        {canCreate && (
                            <button onClick={() => setShowCreateModal(true)}
                                className="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">
                                <PlusIcon className="h-4 w-4 mr-2" />Nova Ordem
                            </button>
                        )}
                    </div>
                </div>
            </PageHeader>
            <div className="py-6">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    {/* KPI */}
                    <KpiCards kanbanData={kanbanData} />

                    {/* Filters */}
                    <Filters search={search} setSearch={setSearch} statusFilter={statusFilter}
                        setStatusFilter={setStatusFilter} storeFilter={storeFilter} setStoreFilter={setStoreFilter}
                        statusOptions={statusOptions} stores={selects.stores || []} onApply={applyFilters} />

                    {/* Kanban */}
                    {viewMode === 'kanban' && (
                        <KanbanBoard statusOptions={statusOptions} kanbanData={kanbanData}
                            kanbanCards={kanbanCards} canEdit={canEdit} onTransition={openTransitionModal}
                            onDetail={(id) => setDetailOrderId(id)} />
                    )}

                    {/* Table */}
                    {viewMode === 'table' && (
                        <TableView payments={payments} canEdit={canEdit} statusOptions={statusOptions}
                            onTransition={openTransitionModal} onDetail={(id) => setDetailOrderId(id)} />
                    )}

                    {/* Create Modal */}
                    {showCreateModal && (
                        <CreateModal selects={selects} onClose={() => setShowCreateModal(false)} />
                    )}

                    {/* Transition Modal */}
                    {showTransitionModal && transitionData && (
                        <TransitionModal data={transitionData} statusOptions={statusOptions}
                            selects={selects} error={transitionError} setError={setTransitionError}
                            onClose={() => setShowTransitionModal(false)} />
                    )}

                    {/* Dashboard Charts */}
                    <DashboardCharts show={showDashboard} onClose={() => setShowDashboard(false)}
                        statisticsUrl={route('order-payments.statistics')}
                        dashboardUrl={route('order-payments.dashboard')} />

                    {/* Detail Modal */}
                    <DetailModal orderId={detailOrderId}
                        onClose={() => setDetailOrderId(null)}
                        onTransition={(order, newSt) => { setDetailOrderId(null); openTransitionModal(order, newSt); }}
                        canEdit={canEdit} />
                </div>
            </div>
        </>
    );
}

// ============================================================
// CREATE MODAL — 6 Cards matching legacy v1
// ============================================================
function CreateModal({ selects, onClose }) {
    const form = useForm({
        // Card 1: Informações Básicas
        area_id: '', cost_center_id: '', brand_id: '', date_payment: '', manager_id: '', management_reason_id: '',
        // Card 2: Fornecedor e Valores
        supplier_id: '', total_value: '', number_nf: '', launch_number: '', description: '',
        // Card 3: Pagamento e Adiantamento
        payment_type_id: '', advance: '2', advance_amount: '', advance_paid: '2', proof: '2',
        installments: 0, installment_items: [],
        // Card 4: Dados Bancários
        bank_id: '', agency: '', type_account: '', checking_account: '',
        name_supplier: '', document_number_supplier: '', pix_key_type_id: '', pix_key: '',
        // Card 5: Rateio
        has_allocation: false, allocations: [],
        // Card 6: Observações
        observations: '',
    });

    const selectedPaymentType = useMemo(() => {
        const pt = (selects.paymentTypes || []).find(t => t.id == form.data.payment_type_id);
        return pt?.name || '';
    }, [form.data.payment_type_id, selects.paymentTypes]);

    const isPix = selectedPaymentType === 'PIX';
    const isBoleto = selectedPaymentType === 'Boleto';
    const isBankTransfer = selectedPaymentType === 'Transferência Bancária';

    const handleInstallmentCountChange = (count) => {
        const n = Math.min(12, Math.max(0, parseInt(count) || 0));
        form.setData('installments', n);
        const items = [...form.data.installment_items];
        while (items.length < n) items.push({ value: '', date: '' });
        form.setData('installment_items', items.slice(0, n));
    };

    const updateInstallmentItem = (idx, field, val) => {
        const items = [...form.data.installment_items];
        items[idx] = { ...items[idx], [field]: val };
        form.setData('installment_items', items);
    };

    // Allocation handlers
    const addAllocationRow = () => {
        form.setData('allocations', [...form.data.allocations, { cost_center_id: '', percentage: '', value: '' }]);
    };
    const removeAllocationRow = (idx) => {
        form.setData('allocations', form.data.allocations.filter((_, i) => i !== idx));
    };
    const updateAllocation = (idx, field, val) => {
        const allocs = [...form.data.allocations];
        allocs[idx] = { ...allocs[idx], [field]: val };
        // Auto-calculate value from percentage
        if (field === 'percentage' && form.data.total_value) {
            const total = parseMoney(form.data.total_value);
            allocs[idx].value = maskMoney(String(Math.round((parseFloat(val) || 0) / 100 * total * 100)));
        }
        form.setData('allocations', allocs);
    };
    const divideEqually = () => {
        const n = form.data.allocations.length;
        if (!n || !form.data.total_value) return;
        const total = parseMoney(form.data.total_value);
        const pct = (100 / n).toFixed(2);
        const val = maskMoney(String(Math.round(total / n * 100)));
        form.setData('allocations', form.data.allocations.map(a => ({ ...a, percentage: pct, value: val })));
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const pt = (selects.paymentTypes || []).find(t => t.id == form.data.payment_type_id);
        const submitData = {
            ...form.data,
            payment_type: pt?.name || '',
            total_value: parseMoney(form.data.total_value),
            advance_amount: parseMoney(form.data.advance_amount),
            advance: form.data.advance === '1',
            advance_paid: form.data.advance_paid === '1',
            proof: form.data.proof === '1',
            installment_items: form.data.installment_items.map(item => ({
                ...item,
                value: parseMoney(item.value),
            })),
            allocations: form.data.allocations.map(a => ({
                ...a,
                value: parseMoney(a.value),
                percentage: parseFloat(a.percentage) || 0,
            })),
        };
        form.transform(() => submitData).post(route('order-payments.store'), {
            onSuccess: () => onClose(),
        });
    };

    return (
        <div className="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
            <div className="bg-white rounded-lg shadow-xl w-full max-w-5xl mx-4 max-h-[95vh] flex flex-col">
                <div className="bg-green-600 text-white px-6 py-4 rounded-t-lg flex justify-between items-center shrink-0">
                    <h3 className="text-lg font-semibold">Nova Ordem de Pagamento</h3>
                    <button onClick={onClose} className="text-white hover:text-green-200 text-2xl leading-none">&times;</button>
                </div>

                <form onSubmit={handleSubmit} className="flex flex-col flex-1 min-h-0">
                <div className="p-6 space-y-6 overflow-y-auto flex-1">
                    {/* Card 1: Informações Básicas */}
                    <Card title="Informações Básicas" icon="📋">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <Select label="Área *" value={form.data.area_id} onChange={v => form.setData('area_id', v)}
                                options={(selects.areas || []).map(a => ({ value: a.id, label: a.name }))} error={form.errors.area_id} required />
                            <Select label="Centro de Custo *" value={form.data.cost_center_id} onChange={v => form.setData('cost_center_id', v)}
                                options={(selects.costCenters || []).map(c => ({ value: c.id, label: `${c.name} - ${c.code}` }))} error={form.errors.cost_center_id} required />
                            <Select label="Marca *" value={form.data.brand_id} onChange={v => form.setData('brand_id', v)}
                                options={(selects.brands || []).map(b => ({ value: b.id, label: b.name }))} error={form.errors.brand_id} required />
                            <Input label="Data Pagamento *" type="date" value={form.data.date_payment}
                                onChange={v => form.setData('date_payment', v)} error={form.errors.date_payment} required />
                            <Select label="Aprovador *" value={form.data.manager_id} onChange={v => form.setData('manager_id', v)}
                                options={(selects.managers || []).map(m => ({ value: m.id, label: m.name }))} error={form.errors.manager_id} required />
                            <Select label="Motivo Gerencial" value={form.data.management_reason_id} onChange={v => form.setData('management_reason_id', v)}
                                options={(selects.managementReasons || []).map(r => ({ value: r.id, label: r.name }))} />
                        </div>
                    </Card>

                    {/* Card 2: Fornecedor e Valores */}
                    <Card title="Fornecedor e Valores" icon="💰">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <Select label="Fornecedor *" value={form.data.supplier_id} onChange={v => form.setData('supplier_id', v)}
                                options={(selects.suppliers || []).map(s => ({ value: s.id, label: `${s.nome_fantasia}${s.cnpj ? ` (${s.cnpj})` : ''}` }))}
                                error={form.errors.supplier_id} required />
                            <MoneyInput label="Valor Total *" value={form.data.total_value}
                                onChange={v => form.setData('total_value', v)} error={form.errors.total_value} required />
                            <Input label="Nota Fiscal" type="text" value={form.data.number_nf}
                                onChange={v => form.setData('number_nf', v)} />
                            <Input label="Lançamento" type="text" value={form.data.launch_number}
                                onChange={v => form.setData('launch_number', v)} />
                        </div>
                        <div className="mt-4">
                            <Input label="Descrição *" value={form.data.description}
                                onChange={v => form.setData('description', v)} error={form.errors.description} required />
                        </div>
                    </Card>

                    {/* Card 3: Pagamento e Adiantamento */}
                    <Card title="Pagamento e Adiantamento" icon="💳">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <Select label="Forma de Pagamento *" value={form.data.payment_type_id}
                                onChange={v => form.setData('payment_type_id', v)}
                                options={(selects.paymentTypes || []).map(t => ({ value: t.id, label: t.name }))} required />
                            <Select label="Adiantamento *" value={form.data.advance} onChange={v => form.setData('advance', v)}
                                options={[{ value: '1', label: 'Sim' }, { value: '2', label: 'Não' }]} required />
                            <Select label="Comprovante *" value={form.data.proof} onChange={v => form.setData('proof', v)}
                                options={[{ value: '1', label: 'Sim' }, { value: '2', label: 'Não' }]} required />
                        </div>

                        {form.data.advance === '1' && (
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4 p-3 bg-yellow-50 rounded-lg border border-yellow-200">
                                <MoneyInput label="Valor Adiantamento" value={form.data.advance_amount}
                                    onChange={v => form.setData('advance_amount', v)} />
                                <Select label="Adiant. Pago?" value={form.data.advance_paid}
                                    onChange={v => form.setData('advance_paid', v)}
                                    options={[{ value: '1', label: 'Sim' }, { value: '2', label: 'Não' }]} />
                            </div>
                        )}

                        {isBoleto && (
                            <div className="mt-4 p-3 bg-orange-50 rounded-lg border border-orange-200">
                                <div className="flex items-center gap-4 mb-3">
                                    <label className="text-sm font-medium text-gray-700">Parcelas:</label>
                                    <input type="number" min="0" max="12" value={form.data.installments}
                                        onChange={e => handleInstallmentCountChange(e.target.value)}
                                        className="w-20 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                </div>
                                {form.data.installments > 0 && (
                                    <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                                        {form.data.installment_items.map((item, i) => (
                                            <div key={i} className="bg-white p-2 rounded border">
                                                <p className="text-xs font-medium text-gray-500 mb-1">Parcela {i + 1}</p>
                                                <input type="text" placeholder="0,00" value={item.value}
                                                    onChange={e => updateInstallmentItem(i, 'value', maskMoney(e.target.value))}
                                                    className="w-full mb-1 rounded-md border-gray-300 text-sm" />
                                                <input type="date" value={item.date}
                                                    onChange={e => updateInstallmentItem(i, 'date', e.target.value)}
                                                    className="w-full rounded-md border-gray-300 text-sm" />
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        )}
                    </Card>

                    {/* Card 4: Dados Bancários */}
                    <Card title="Dados Bancários" icon="🏦">
                        {isPix ? (
                            <div className="p-3 bg-purple-50 rounded-lg border border-purple-200">
                                <p className="text-xs font-semibold text-purple-700 mb-3 uppercase">Pagamento via PIX</p>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <Select label="Tipo Chave PIX *" value={form.data.pix_key_type_id}
                                        onChange={v => form.setData('pix_key_type_id', v)}
                                        options={(selects.pixKeyTypes || []).map(t => ({ value: t.id, label: t.name }))} required />
                                    <PixKeyInput
                                        keyTypeId={form.data.pix_key_type_id}
                                        pixKeyTypes={selects.pixKeyTypes || []}
                                        value={form.data.pix_key}
                                        onChange={v => form.setData('pix_key', v)} />
                                    <Input label="Titular *" value={form.data.name_supplier}
                                        onChange={v => form.setData('name_supplier', v)} required />
                                    <MaskedInput label="CPF / CNPJ" value={form.data.document_number_supplier}
                                        mask={maskCpfCnpj} onChange={v => form.setData('document_number_supplier', v)} />
                                </div>
                            </div>
                        ) : (
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <Select label="Banco" value={form.data.bank_id} onChange={v => form.setData('bank_id', v)}
                                    options={(selects.banks || []).map(b => ({ value: b.id, label: b.bank_name }))} />
                                <Input label="Agência" value={form.data.agency} onChange={v => form.setData('agency', v)} />
                                <Select label="Tipo Conta" value={form.data.type_account} onChange={v => form.setData('type_account', v)}
                                    options={[{ value: '1', label: 'Conta Corrente' }, { value: '2', label: 'Poupança' }]} />
                                <Input label="Conta Corrente" value={form.data.checking_account}
                                    onChange={v => form.setData('checking_account', v)} />
                                <Input label="Titular" value={form.data.name_supplier}
                                    onChange={v => form.setData('name_supplier', v)} />
                                <Input label="CPF / CNPJ" value={form.data.document_number_supplier}
                                    onChange={v => form.setData('document_number_supplier', v)} />
                            </div>
                        )}
                    </Card>

                    {/* Card 5: Rateio (Allocation) */}
                    <Card title="Rateio" icon="📊">
                        <div className="flex items-center space-x-2 mb-4">
                            <input type="checkbox" checked={form.data.has_allocation}
                                onChange={e => {
                                    form.setData('has_allocation', e.target.checked);
                                    if (e.target.checked && form.data.allocations.length === 0) addAllocationRow();
                                }}
                                className="rounded border-gray-300 text-indigo-600" />
                            <span className="text-sm text-gray-700">Habilitar rateio por centro de custo</span>
                        </div>

                        {form.data.has_allocation && (
                            <div className="border rounded-lg p-4 bg-gray-50">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="text-left text-gray-500 border-b">
                                            <th className="pb-2 w-2/5">Centro de Custo</th>
                                            <th className="pb-2 w-1/5">% Rateio</th>
                                            <th className="pb-2 w-1/4">Valor (R$)</th>
                                            <th className="pb-2 w-[15%] text-center">Ação</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {form.data.allocations.map((alloc, i) => (
                                            <tr key={i} className="border-b last:border-0">
                                                <td className="py-2 pr-2">
                                                    <select value={alloc.cost_center_id} onChange={e => updateAllocation(i, 'cost_center_id', e.target.value)}
                                                        className="w-full rounded-md border-gray-300 text-sm">
                                                        <option value="">Selecione</option>
                                                        {(selects.costCenters || []).map(c => (
                                                            <option key={c.id} value={c.id}>{c.name} - {c.code}</option>
                                                        ))}
                                                    </select>
                                                </td>
                                                <td className="py-2 pr-2">
                                                    <input type="number" step="0.01" min="0" max="100" value={alloc.percentage}
                                                        onChange={e => updateAllocation(i, 'percentage', e.target.value)}
                                                        className="w-full rounded-md border-gray-300 text-sm" placeholder="0,00" />
                                                </td>
                                                <td className="py-2 pr-2">
                                                    <input type="text" value={alloc.value}
                                                        onChange={e => updateAllocation(i, 'value', maskMoney(e.target.value))}
                                                        className="w-full rounded-md border-gray-300 text-sm" placeholder="0,00" />
                                                </td>
                                                <td className="py-2 text-center">
                                                    <button type="button" onClick={() => removeAllocationRow(i)}
                                                        className="text-red-500 hover:text-red-700 text-lg">&times;</button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                    <tfoot>
                                        <tr className="border-t font-medium">
                                            <td className="pt-2 text-right pr-2">Total:</td>
                                            <td className="pt-2 pr-2">
                                                <span className={`${Math.abs(form.data.allocations.reduce((s, a) => s + (parseFloat(a.percentage) || 0), 0) - 100) < 0.02 ? 'text-green-600' : 'text-red-600'}`}>
                                                    {form.data.allocations.reduce((s, a) => s + (parseFloat(a.percentage) || 0), 0).toFixed(2)}%
                                                </span>
                                            </td>
                                            <td className="pt-2 pr-2">
                                                <span className={`${Math.abs(form.data.allocations.reduce((s, a) => s + parseMoney(a.value), 0) - parseMoney(form.data.total_value)) < 0.02 ? 'text-green-600' : 'text-red-600'}`}>
                                                    {fmtCurrency(form.data.allocations.reduce((s, a) => s + parseMoney(a.value), 0))}
                                                </span>
                                            </td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                                <div className="flex gap-2 mt-3">
                                    <button type="button" onClick={addAllocationRow}
                                        className="px-3 py-1 text-xs bg-indigo-100 text-indigo-700 rounded hover:bg-indigo-200">
                                        + Adicionar Linha
                                    </button>
                                    <button type="button" onClick={divideEqually}
                                        className="px-3 py-1 text-xs bg-green-100 text-green-700 rounded hover:bg-green-200">
                                        Dividir Igualmente
                                    </button>
                                </div>
                            </div>
                        )}
                    </Card>

                    {/* Card 6: Observações */}
                    <Card title="Observações" icon="📝">
                        <textarea value={form.data.observations} onChange={e => form.setData('observations', e.target.value)}
                            rows={3} placeholder="Observações adicionais..."
                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                    </Card>

                    </div>
                    {/* Actions - footer fixo */}
                    <div className="flex justify-end space-x-3 px-6 py-4 border-t bg-gray-50 rounded-b-lg shrink-0">
                        <button type="button" onClick={onClose}
                            className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border rounded-md hover:bg-gray-50">
                            Cancelar
                        </button>
                        <button type="submit" disabled={form.processing}
                            className="px-6 py-2 text-sm font-medium text-white bg-green-600 rounded-md hover:bg-green-700 disabled:opacity-50">
                            {form.processing ? 'Salvando...' : 'Salvar Ordem de Pagamento'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// ============================================================
// TRANSITION MODAL
// ============================================================
function TransitionModal({ data, statusOptions, selects, error, setError, onClose }) {
    const { order, newStatus } = data;
    const forward = isForward(order.status, newStatus);
    const [formData, setFormData] = useState({
        new_status: newStatus, notes: '', number_nf: order.number_nf || '',
        launch_number: order.launch_number || '', date_paid: '',
        bank_name: '', agency: '', checking_account: '', pix_key_type: '', pix_key: '',
    });

    const showNf = order.status === 'backlog' && newStatus === 'doing';
    const showLaunch = (order.status === 'backlog' && newStatus === 'doing') || (order.status === 'doing' && newStatus === 'waiting');
    const showDatePaid = newStatus === 'done';
    const showBank = order.status === 'doing' && newStatus === 'waiting' && order.payment_type !== 'PIX' && order.payment_type !== 'Boleto';
    const showPix = order.status === 'doing' && newStatus === 'waiting' && order.payment_type === 'PIX';
    const showNotes = !forward;

    const handleSubmit = (e) => {
        e.preventDefault();
        setError('');
        fetch(route('order-payments.transition', order.id), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(formData),
        })
        .then(r => r.json().then(d => ({ ok: r.ok, d })))
        .then(({ ok, d }) => {
            if (!ok || d.error) setError(d.message || 'Erro na transição.');
            else { onClose(); router.reload(); }
        })
        .catch(() => setError('Erro de conexão.'));
    };

    const set = (k, v) => setFormData(prev => ({ ...prev, [k]: v }));

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            <div className="flex min-h-full items-start justify-center p-4 pt-20">
                <div className="fixed inset-0 bg-gray-500/75 transition-opacity" onClick={onClose} />
                <div className="relative w-full max-w-lg bg-white rounded-xl shadow-2xl">
                    {/* Header */}
                    <div className={`${forward ? 'bg-green-600' : 'bg-yellow-500'} rounded-t-xl px-6 py-4 flex items-center justify-between`}>
                        <h3 className="text-lg font-semibold text-white">
                            {forward ? 'Avançar' : 'Retornar'} Ordem #{order.id}
                        </h3>
                        <button onClick={onClose} className="text-white/70 hover:text-white">
                            <span className="text-2xl leading-none">&times;</span>
                        </button>
                    </div>

                    {/* Status Transition Visual */}
                    <div className="px-6 py-4 bg-gray-50 border-b">
                        <div className="flex items-center justify-center space-x-4">
                            <div className="text-center">
                                <span className={`inline-flex px-3 py-1.5 rounded-full text-sm font-medium ${STATUS_COLORS[order.status]?.bg} ${STATUS_COLORS[order.status]?.text}`}>
                                    {statusOptions[order.status]}
                                </span>
                                <p className="text-[10px] text-gray-400 mt-1 uppercase">Atual</p>
                            </div>
                            <ArrowRightIcon className="h-6 w-6 text-gray-300 shrink-0" />
                            <div className="text-center">
                                <span className={`inline-flex px-3 py-1.5 rounded-full text-sm font-medium ring-2 ${STATUS_COLORS[newStatus]?.bg} ${STATUS_COLORS[newStatus]?.text} ring-offset-1`}
                                    style={{ ringColor: STATUS_COLORS[newStatus]?.border }}>
                                    {statusOptions[newStatus]}
                                </span>
                                <p className="text-[10px] text-gray-400 mt-1 uppercase">Novo</p>
                            </div>
                        </div>
                    </div>

                    <div className="p-6">
                        {error && (
                            <div className="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700 mb-4 flex items-start gap-2">
                                <ExclamationTriangleIcon className="h-5 w-5 text-red-500 shrink-0 mt-0.5" />
                                <span>{error}</span>
                            </div>
                        )}

                        <form onSubmit={handleSubmit} className="space-y-4">
                            {showNf && <Input label="Número NF *" value={formData.number_nf} onChange={v => set('number_nf', v)} required />}
                            {showLaunch && <Input label="Número Lançamento *" value={formData.launch_number} onChange={v => set('launch_number', v)} required />}
                            {showDatePaid && <Input label="Data de Pagamento *" type="date" value={formData.date_paid} onChange={v => set('date_paid', v)} required />}
                            {showBank && (
                                <div className="grid grid-cols-3 gap-3">
                                    <Input label="Banco *" value={formData.bank_name} onChange={v => set('bank_name', v)} />
                                    <Input label="Agência *" value={formData.agency} onChange={v => set('agency', v)} />
                                    <Input label="Conta *" value={formData.checking_account} onChange={v => set('checking_account', v)} />
                                </div>
                            )}
                            {showPix && (
                                <div className="grid grid-cols-2 gap-3">
                                    <Select label="Tipo Chave PIX *" value={formData.pix_key_type} onChange={v => set('pix_key_type', v)}
                                        options={(selects.pixKeyTypes || []).map(t => ({ value: t.name, label: t.name }))} />
                                    <Input label="Chave PIX *" value={formData.pix_key} onChange={v => set('pix_key', v)} />
                                </div>
                            )}
                            {showNotes && <Textarea label="Motivo do retorno *" value={formData.notes} onChange={v => set('notes', v)} required rows={3} />}

                            {/* Se não tem campos específicos, mostra confirmação */}
                            {!showNf && !showLaunch && !showDatePaid && !showBank && !showPix && !showNotes && (
                                <p className="text-sm text-gray-500 text-center py-2">
                                    Confirma o {forward ? 'avançamento' : 'retorno'} desta ordem?
                                </p>
                            )}

                            <div className="flex justify-end space-x-3 pt-4 border-t">
                                <button type="button" onClick={onClose}
                                    className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                    Cancelar
                                </button>
                                <button type="submit"
                                    className={`px-5 py-2 text-sm font-medium text-white rounded-lg ${forward ? 'bg-green-600 hover:bg-green-700' : 'bg-yellow-500 hover:bg-yellow-600'}`}>
                                    {forward ? 'Avançar' : 'Retornar'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    );
}

// ============================================================
// KANBAN BOARD
// ============================================================
function KanbanBoard({ statusOptions, kanbanData, kanbanCards, canEdit, onTransition, onDetail }) {
    return (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            {Object.entries(statusOptions).map(([status, label]) => (
                <div key={status} className="bg-white rounded-lg shadow overflow-hidden flex flex-col">
                    <div className={`${STATUS_COLORS[status]?.header} px-4 py-3`}>
                        <div className="flex justify-between items-center">
                            <h3 className="text-sm font-semibold text-white">{label}</h3>
                            <span className="bg-white bg-opacity-30 text-white text-xs font-bold px-2 py-0.5 rounded-full">
                                {kanbanData[status]?.count ?? 0}
                            </span>
                        </div>
                    </div>
                    <div className="p-2 space-y-2 flex-1 overflow-y-auto max-h-[600px]">
                        {(kanbanCards[status] || []).map((p) => (
                            <div key={p.id} className={`border rounded-lg p-3 cursor-pointer ${p.is_overdue ? 'border-red-300 bg-red-50' : 'border-gray-200 bg-white'} hover:shadow-md transition-shadow`}
                                onClick={() => onDetail(p.id)}>
                                <div className="flex justify-between items-start mb-1">
                                    <span className="text-xs font-bold text-gray-500">#{p.id}</span>
                                    <div className="flex space-x-1">
                                        {p.advance && <BookmarkIcon className="h-3.5 w-3.5 text-yellow-500" />}
                                        {p.payment_prepared && <DocumentCheckIcon className="h-3.5 w-3.5 text-green-500" />}
                                        {p.installments > 1 && <span className="text-[10px] bg-blue-100 text-blue-700 px-1 rounded">{p.installments}x</span>}
                                    </div>
                                </div>
                                <p className="text-sm font-medium text-gray-900 truncate">{p.supplier_name}</p>
                                <div className="mt-1 space-y-0.5 text-[11px] text-gray-500">
                                    <p><strong>Pagar:</strong> <span className={p.is_overdue ? 'text-red-600 font-medium' : ''}>{p.is_overdue && '⚠ '}{p.date_payment}</span></p>
                                    <p><strong>Valor:</strong> <span className="text-gray-900 font-medium">{p.formatted_total}</span></p>
                                </div>
                                {canEdit && (
                                    <div className="flex justify-end mt-2 pt-2 border-t border-gray-100">
                                        <ActionButtons size="xs">
                                            {status !== 'done' && (
                                                <ActionButtons.Custom variant="success" icon={ArrowRightIcon} title="Avançar" size="xs"
                                                    onClick={() => onTransition(p, nextStatus(status))} />
                                            )}
                                            {status !== 'backlog' && (
                                                <ActionButtons.Custom variant="warning" icon={ArrowLeftIcon} title="Retornar" size="xs"
                                                    onClick={() => onTransition(p, prevStatus(status))} />
                                            )}
                                        </ActionButtons>
                                    </div>
                                )}
                            </div>
                        ))}
                        {(kanbanCards[status] || []).length === 0 && <p className="text-center text-gray-400 text-sm py-8">Nenhuma ordem</p>}
                    </div>
                </div>
            ))}
        </div>
    );
}

// ============================================================
// TABLE VIEW
// ============================================================
function TableView({ payments, canEdit, statusOptions, onTransition, onDetail }) {
    return (
        <div className="bg-white shadow rounded-lg overflow-hidden">
            <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                    <tr>
                        {['#', 'Fornecedor', 'Loja', 'Valor', 'Pagamento', 'NF', 'Status', 'Ações'].map(h => (
                            <th key={h} className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{h}</th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                    {payments.data?.length > 0 ? payments.data.map((p) => (
                        <tr key={p.id} className={`hover:bg-gray-50 cursor-pointer ${p.is_overdue ? 'bg-red-50' : ''}`} onClick={() => onDetail(p.id)}>
                            <td className="px-4 py-3 text-sm font-medium text-gray-900">
                                #{p.id}
                                {p.advance && <BookmarkIcon className="inline h-3.5 w-3.5 ml-1 text-yellow-500" />}
                            </td>
                            <td className="px-4 py-3 text-sm text-gray-900">{p.supplier_name}</td>
                            <td className="px-4 py-3 text-sm text-gray-500">{p.store?.name || '-'}</td>
                            <td className="px-4 py-3 text-sm font-medium text-gray-900">{p.formatted_total}</td>
                            <td className="px-4 py-3 text-sm"><span className={p.is_overdue ? 'text-red-600 font-medium' : 'text-gray-500'}>{p.date_payment || '-'}</span></td>
                            <td className="px-4 py-3 text-sm text-gray-500 font-mono">{p.number_nf || '-'}</td>
                            <td className="px-4 py-3"><span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_COLORS[p.status]?.bg} ${STATUS_COLORS[p.status]?.text}`}>{p.status_label}</span></td>
                            <td className="px-4 py-3" onClick={(e) => e.stopPropagation()}>
                                <ActionButtons
                                    onView={() => onDetail(p.id)}
                                >
                                    {canEdit && p.status !== 'done' && (
                                        <ActionButtons.Custom variant="success" icon={ArrowRightIcon} title="Avançar"
                                            onClick={() => onTransition(p, nextStatus(p.status))} />
                                    )}
                                    {canEdit && p.status !== 'backlog' && (
                                        <ActionButtons.Custom variant="warning" icon={ArrowLeftIcon} title="Retornar"
                                            onClick={() => onTransition(p, prevStatus(p.status))} />
                                    )}
                                </ActionButtons>
                            </td>
                        </tr>
                    )) : (
                        <tr><td colSpan="8" className="px-4 py-12 text-center text-gray-500">Nenhuma ordem encontrada.</td></tr>
                    )}
                </tbody>
            </table>
            {payments.last_page > 1 && (
                <div className="px-4 py-3 border-t flex justify-between items-center">
                    <span className="text-sm text-gray-700">{payments.from} a {payments.to} de {payments.total}</span>
                    <div className="flex space-x-1">
                        {payments.links.map((link, i) => (
                            <button key={i} onClick={() => link.url && router.get(link.url)} disabled={!link.url}
                                className={`px-3 py-1 text-sm rounded ${link.active ? 'bg-indigo-600 text-white' : link.url ? 'bg-white text-gray-700 hover:bg-gray-50 border' : 'bg-gray-100 text-gray-400'}`}
                                dangerouslySetInnerHTML={{ __html: link.label }} />
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

// ============================================================
// SHARED COMPONENTS
// ============================================================
function KpiCards({ kanbanData }) {
    return (
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            {Object.entries(kanbanData).map(([status, data]) => (
                <div key={status} className={`rounded-lg p-4 ${STATUS_COLORS[status]?.bg} border ${STATUS_COLORS[status]?.border}`}>
                    <div className="flex justify-between items-start">
                        <div>
                            <p className={`text-xs font-medium uppercase ${STATUS_COLORS[status]?.text}`}>{data.label}</p>
                            <p className={`text-2xl font-bold mt-1 ${STATUS_COLORS[status]?.text}`}>{data.count}</p>
                        </div>
                        <p className={`text-sm font-semibold ${STATUS_COLORS[status]?.text}`}>{fmtCurrency(data.total)}</p>
                    </div>
                </div>
            ))}
        </div>
    );
}

function Filters({ search, setSearch, statusFilter, setStatusFilter, storeFilter, setStoreFilter, statusOptions, stores, onApply }) {
    return (
        <div className="bg-white shadow rounded-lg p-4 mb-6">
            <div className="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div className="relative">
                    <MagnifyingGlassIcon className="absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
                    <input type="text" placeholder="Buscar fornecedor, NF..." value={search}
                        onChange={e => setSearch(e.target.value)} onKeyDown={e => e.key === 'Enter' && onApply()}
                        className="pl-10 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                </div>
                <select value={statusFilter} onChange={e => setStatusFilter(e.target.value)}
                    className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <option value="">Todos os Status</option>
                    {Object.entries(statusOptions).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                </select>
                <select value={storeFilter} onChange={e => setStoreFilter(e.target.value)}
                    className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    <option value="">Todas as Lojas</option>
                    {stores.map(s => <option key={s.id} value={s.id}>{s.code} - {s.name}</option>)}
                </select>
                <button onClick={onApply} className="inline-flex justify-center items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700">Filtrar</button>
            </div>
        </div>
    );
}

function ViewToggle({ viewMode, setViewMode }) {
    return (
        <div className="flex bg-gray-100 rounded-md p-0.5">
            <button onClick={() => setViewMode('kanban')} className={`p-1.5 rounded ${viewMode === 'kanban' ? 'bg-white shadow-sm' : ''}`}>
                <Squares2X2Icon className="h-5 w-5 text-gray-600" />
            </button>
            <button onClick={() => setViewMode('table')} className={`p-1.5 rounded ${viewMode === 'table' ? 'bg-white shadow-sm' : ''}`}>
                <TableCellsIcon className="h-5 w-5 text-gray-600" />
            </button>
        </div>
    );
}

function Card({ title, icon, children }) {
    return (
        <div className="bg-white border border-gray-200 rounded-lg">
            <div className="bg-gray-50 px-4 py-3 border-b border-gray-200 rounded-t-lg">
                <h4 className="text-sm font-semibold text-gray-700">{icon} {title}</h4>
            </div>
            <div className="p-4">{children}</div>
        </div>
    );
}

function Input({ label, error, onChange, ...props }) {
    return (
        <div>
            {label && <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>}
            <input {...props} onChange={e => onChange(e.target.value)}
                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}

function Select({ label, options = [], error, onChange, value, required }) {
    return (
        <div>
            {label && <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>}
            <select value={value} onChange={e => onChange(e.target.value)} required={required}
                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                <option value="">Selecione</option>
                {options.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
            </select>
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}

function Textarea({ label, error, onChange, ...props }) {
    return (
        <div>
            {label && <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>}
            <textarea {...props} onChange={e => onChange(e.target.value)}
                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}

function MoneyInput({ label, value, onChange, error, required }) {
    return (
        <div>
            {label && <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>}
            <div className="relative">
                <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm">R$</span>
                <input type="text" value={value} placeholder="0,00" required={required}
                    onChange={e => onChange(maskMoney(e.target.value))}
                    className="w-full pl-10 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
            </div>
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}

function MaskedInput({ label, value, mask, onChange, error, required }) {
    return (
        <div>
            {label && <label className="block text-sm font-medium text-gray-700 mb-1">{label}</label>}
            <input type="text" value={value} required={required}
                onChange={e => onChange(mask(e.target.value))}
                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}

function PixKeyInput({ keyTypeId, pixKeyTypes, value, onChange }) {
    const selectedType = pixKeyTypes.find(t => t.id == keyTypeId);
    const typeName = selectedType?.name || '';

    const getMask = () => {
        if (typeName === 'CPF/CNPJ') return maskCpfCnpj;
        if (typeName === 'Telefone') return maskPhone;
        return null;
    };

    const mask = getMask();
    const placeholder = typeName === 'CPF/CNPJ' ? '000.000.000-00'
        : typeName === 'E-mail' ? 'email@exemplo.com'
        : typeName === 'Telefone' ? '(00) 00000-0000'
        : 'Chave aleatória';

    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Chave PIX *</label>
            <input type="text" value={value} placeholder={placeholder} required
                onChange={e => onChange(mask ? mask(e.target.value) : e.target.value)}
                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
        </div>
    );
}
