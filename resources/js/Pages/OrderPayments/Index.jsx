import { Head, router, useForm } from '@inertiajs/react';
import {
    PlusIcon, XMarkIcon, MagnifyingGlassIcon, Squares2X2Icon, TableCellsIcon,
    ArrowRightIcon, ArrowLeftIcon, BookmarkIcon, DocumentCheckIcon,
    ExclamationTriangleIcon, ChartBarIcon, ArrowDownTrayIcon,
    ClipboardDocumentListIcon, ArrowPathIcon, ClockIcon, CheckCircleIcon,
} from '@heroicons/react/24/outline';
import { useState, useMemo, useEffect } from 'react';
import axios from 'axios';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import { maskMoney, parseMoney, maskCpfCnpj, maskPhone, handleMasked } from '@/Hooks/useMasks';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import DashboardCharts from '@/Components/OrderPayments/DashboardCharts';
import DetailModal from '@/Components/OrderPayments/DetailModal';
import StandardModal from '@/Components/StandardModal';

const STATUS_COLORS = {
    backlog: { bg: 'bg-gray-100', text: 'text-gray-800', border: 'border-gray-300', header: 'bg-gray-600' },
    doing:   { bg: 'bg-blue-100', text: 'text-blue-800', border: 'border-blue-300', header: 'bg-blue-600' },
    waiting: { bg: 'bg-yellow-100', text: 'text-yellow-800', border: 'border-yellow-300', header: 'bg-yellow-500' },
    done:    { bg: 'bg-green-100', text: 'text-green-800', border: 'border-green-300', header: 'bg-green-600' },
};

const STATUS_VARIANT_MAP = {
    backlog: 'gray',
    doing: 'info',
    waiting: 'warning',
    done: 'success',
};

const STATUS_ICON_MAP = {
    backlog: ClipboardDocumentListIcon,
    doing: ArrowPathIcon,
    waiting: ClockIcon,
    done: CheckCircleIcon,
};

const STATUS_COLOR_MAP = {
    backlog: 'gray',
    doing: 'blue',
    waiting: 'yellow',
    done: 'green',
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

    const { modals, selected, openModal, closeModal } = useModalManager(['create', 'transition', 'dashboard']);
    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [storeFilter, setStoreFilter] = useState(filters.store_id || '');
    const [viewMode, setViewMode] = useState('kanban');
    const [transitionError, setTransitionError] = useState('');
    const [detailOrderId, setDetailOrderId] = useState(null);

    const applyFilters = () => {
        router.get(route('order-payments.index'), {
            search: search || undefined, status: statusFilter || undefined, store_id: storeFilter || undefined,
        }, { preserveState: true });
    };

    const openTransitionModal = (order, newSt) => {
        setTransitionError('');
        openModal('transition', { order, newStatus: newSt });
    };

    const statisticsCards = Object.entries(kanbanData).map(([status, data]) => ({
        label: data.label,
        value: data.count,
        format: 'number',
        icon: STATUS_ICON_MAP[status],
        color: STATUS_COLOR_MAP[status] || 'gray',
        sub: fmtCurrency(data.total),
    }));

    // ======== RENDER ========
    return (
        <>
            <Head title="Ordens de Pagamento" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6">
                        <div className="flex justify-between items-center">
                            <div>
                                <h1 className="text-3xl font-bold text-gray-900">
                                    Ordens de Pagamento
                                </h1>
                                <p className="mt-1 text-sm text-gray-600">
                                    Solicitações e controle de pagamentos
                                </p>
                            </div>
                            <div className="flex items-center space-x-3">
                                <ViewToggle viewMode={viewMode} setViewMode={setViewMode} />
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => openModal('dashboard')}
                                    icon={ChartBarIcon}
                                    iconOnly
                                    title="Dashboard"
                                />
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => {
                                        window.location.href = route('order-payments.export', {
                                            search: search || undefined,
                                            status: statusFilter || undefined,
                                            store_id: storeFilter || undefined,
                                        });
                                    }}
                                    icon={ArrowDownTrayIcon}
                                    iconOnly
                                    title="Exportar Excel"
                                />
                                {canCreate && (
                                    <Button
                                        variant="primary"
                                        onClick={() => openModal('create')}
                                        icon={PlusIcon}
                                    >
                                        Nova Ordem
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Estatísticas */}
                    <StatisticsGrid cards={statisticsCards} cols={4} />

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
                </div>
            </div>

            {/* Create Modal */}
            {modals.create && (
                <CreateModal selects={selects} onClose={() => closeModal('create')} />
            )}

            {/* Transition Modal */}
            {modals.transition && selected && (
                <TransitionModal data={selected} statusOptions={statusOptions}
                    selects={selects} error={transitionError} setError={setTransitionError}
                    onClose={() => closeModal('transition')} />
            )}

            {/* Dashboard Charts */}
            <DashboardCharts show={modals.dashboard} onClose={() => closeModal('dashboard')}
                statisticsUrl={route('order-payments.statistics')}
                dashboardUrl={route('order-payments.dashboard')} />

            {/* Detail Modal */}
            <DetailModal orderId={detailOrderId}
                onClose={() => setDetailOrderId(null)}
                onTransition={(order, newSt) => { setDetailOrderId(null); openTransitionModal(order, newSt); }}
                canEdit={canEdit} />
        </>
    );
}

// ============================================================
// CREATE MODAL — 6 Cards matching legacy v1
// ============================================================
function CreateModal({ selects, onClose }) {
    const form = useForm({
        // Card 1: Informações Básicas
        area_id: '',  // legado — não usado na nova cascata
        management_class_id: '',  // fonte autoritária (carrega CC + filtra ACs)
        cost_center_id: '',  // derivado da management_class, preenchido automaticamente
        accounting_class_id: '',
        brand_id: '',
        date_payment: '', competence_date: '', manager_id: '', management_reason_id: '',
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

    // Cascata Área (departamento gerencial) → Classe Gerencial → CC → AC.
    // Fetch com filtro por `year` — só retorna áreas/classes que têm budget
    // ativo no ano. Evita mostrar ao user opções sem orçamento cadastrado.
    const [departments, setDepartments] = useState([]);
    const [selectedDepartmentId, setSelectedDepartmentId] = useState('');

    const managementClassesForDept = useMemo(() => {
        if (!selectedDepartmentId) return [];
        const dept = departments.find(d => d.id == selectedDepartmentId);
        return dept?.classes || [];
    }, [departments, selectedDepartmentId]);

    const selectedMgmtClass = useMemo(() => {
        return managementClassesForDept.find(m => m.id == form.data.management_class_id) || null;
    }, [managementClassesForDept, form.data.management_class_id]);

    // Quando MC muda, preenche cost_center_id automaticamente (CC derivado)
    useEffect(() => {
        if (selectedMgmtClass?.cost_center?.id) {
            if (form.data.cost_center_id != selectedMgmtClass.cost_center.id) {
                form.setData('cost_center_id', selectedMgmtClass.cost_center.id);
            }
        }
    }, [selectedMgmtClass]);

    // Reset de cascata quando muda o departamento
    const handleDepartmentChange = (newDeptId) => {
        setSelectedDepartmentId(newDeptId);
        form.setData('management_class_id', '');
        form.setData('cost_center_id', '');
        form.setData('accounting_class_id', '');
    };

    // Lista de contas contábeis com budget ativo no CC+ano atual.
    // Recarrega sempre que CC ou o ano (de competência ou pagamento) mudam.
    const [accountingClasses, setAccountingClasses] = useState([]);
    const [loadingAcs, setLoadingAcs] = useState(false);
    const cc = form.data.cost_center_id;
    const yearSource = form.data.competence_date || form.data.date_payment;
    const year = yearSource ? new Date(yearSource).getFullYear() : new Date().getFullYear();

    // Carrega departments filtrado pelo ano — recarrega ao mudar o ano da
    // OP (competência/pagamento). Reseta seleções se departamento sumir.
    useEffect(() => {
        axios.get(route('management-classes.departments'), { params: { year } })
            .then(r => {
                const depts = r.data.departments || [];
                setDepartments(depts);
                // Se o departamento selecionado não está mais na lista, limpa
                if (selectedDepartmentId && !depts.some(d => d.id == selectedDepartmentId)) {
                    setSelectedDepartmentId('');
                    form.setData('management_class_id', '');
                    form.setData('cost_center_id', '');
                    form.setData('accounting_class_id', '');
                }
            })
            .catch(() => setDepartments([]));
    }, [year]);

    useEffect(() => {
        if (!cc) {
            setAccountingClasses([]);
            return;
        }
        setLoadingAcs(true);
        axios.get(route('budgets.accounting-classes-for-cost-center', cc), { params: { year } })
            .then(r => setAccountingClasses(r.data.accounting_classes || []))
            .catch(() => setAccountingClasses([]))
            .finally(() => setLoadingAcs(false));
    }, [cc, year]);

    // Se o AC selecionado saiu da lista (mudou CC/ano), limpa
    useEffect(() => {
        if (form.data.accounting_class_id && accountingClasses.length > 0) {
            const stillValid = accountingClasses.some(a => a.id == form.data.accounting_class_id);
            if (!stillValid) form.setData('accounting_class_id', '');
        }
    }, [accountingClasses]);

    const selectedPaymentType = useMemo(() => {
        const pt = (selects.paymentTypes || []).find(t => t.id == form.data.payment_type_id);
        return pt?.name || '';
    }, [form.data.payment_type_id, selects.paymentTypes]);

    const isPix = selectedPaymentType === 'PIX';
    const isBoleto = selectedPaymentType === 'Boleto';
    const isBankTransfer = selectedPaymentType === 'Transferência Bancária';

    // Boleto começa sempre com 1 parcela (quando o tipo é selecionado e ainda
    // não há parcelas). Não força para 1 quando o user aumenta manualmente.
    useEffect(() => {
        if (isBoleto && form.data.installments === 0) {
            handleInstallmentCountChange(1);
        }
    }, [isBoleto]);

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

    const handleSubmit = () => {
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
        // transform() em algumas versões do Inertia retorna void em vez de
        // encadear o form — separar em duas chamadas é resiliente.
        form.transform(() => submitData);
        form.post(route('order-payments.store'), {
            onSuccess: () => onClose(),
        });
    };

    return (
        <StandardModal
            show={true}
            onClose={onClose}
            title="Nova Ordem de Pagamento"
            headerColor="bg-green-600"
            maxWidth="7xl"
            onSubmit={handleSubmit}
            footer={
                <StandardModal.Footer
                    onCancel={onClose}
                    onSubmit="submit"
                    submitLabel={form.processing ? 'Salvando...' : 'Salvar Ordem de Pagamento'}
                    submitColor="bg-green-600 hover:bg-green-700"
                    processing={form.processing}
                />
            }
        >
                    {/* Card 1: Informações Básicas — cascata Área → Gerencial → CC → AC */}
                    <StandardModal.Section title="Informações Básicas" icon="📋">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <Select label="Área *" value={selectedDepartmentId} onChange={handleDepartmentChange}
                                    options={departments.map(d => ({ value: d.id, label: d.name }))}
                                    error={form.errors.area_id} required />
                                {departments.length === 0 && (
                                    <p className="mt-1 text-xs text-amber-700">
                                        Nenhuma área com orçamento ativo para {year}. Cadastre o orçamento em Orçamentos antes.
                                    </p>
                                )}
                            </div>
                            <ManagementClassCascadeSelect
                                value={form.data.management_class_id}
                                onChange={v => form.setData('management_class_id', v)}
                                options={managementClassesForDept}
                                hasDepartment={!!selectedDepartmentId}
                                error={form.errors.management_class_id}
                            />
                            <CostCenterReadonly
                                costCenter={selectedMgmtClass?.cost_center || null}
                                error={form.errors.cost_center_id}
                            />
                            <AccountingClassSelect
                                value={form.data.accounting_class_id}
                                onChange={v => form.setData('accounting_class_id', v)}
                                options={accountingClasses}
                                loading={loadingAcs}
                                hasCostCenter={!!cc}
                                error={form.errors.accounting_class_id}
                            />
                            <Select label="Marca *" value={form.data.brand_id} onChange={v => form.setData('brand_id', v)}
                                options={(selects.brands || []).map(b => ({ value: b.id, label: b.name }))} error={form.errors.brand_id} required />
                            <Input label="Data Pagamento *" type="date" value={form.data.date_payment}
                                onChange={v => form.setData('date_payment', v)} error={form.errors.date_payment} required />
                            <Input label="Data de Competência" type="date" value={form.data.competence_date}
                                onChange={v => form.setData('competence_date', v)} error={form.errors.competence_date} />
                            <Select label="Aprovador *" value={form.data.manager_id} onChange={v => form.setData('manager_id', v)}
                                options={(selects.managers || []).map(m => ({ value: m.id, label: m.name }))} error={form.errors.manager_id} required />
                            <Select label="Motivo Gerencial" value={form.data.management_reason_id} onChange={v => form.setData('management_reason_id', v)}
                                options={(selects.managementReasons || []).map(r => ({ value: r.id, label: r.name }))} />
                        </div>
                    </StandardModal.Section>

                    {/* Card 2: Fornecedor e Valores */}
                    <StandardModal.Section title="Fornecedor e Valores" icon="💰">
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
                    </StandardModal.Section>

                    {/* Card 3: Pagamento e Adiantamento */}
                    <StandardModal.Section title="Pagamento e Adiantamento" icon="💳">
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
                                    <label className="text-sm font-medium text-gray-700">Parcelas: *</label>
                                    <input type="number" min="1" max="12" value={form.data.installments}
                                        onChange={e => handleInstallmentCountChange(e.target.value)}
                                        required
                                        className="w-20 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                </div>
                                {form.data.installments > 0 && (
                                    <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                                        {form.data.installment_items.map((item, i) => (
                                            <div key={i} className="bg-white p-2 rounded border">
                                                <p className="text-xs font-medium text-gray-500 mb-1">Parcela {i + 1} *</p>
                                                <input type="text" placeholder="Valor *" value={item.value}
                                                    onChange={e => updateInstallmentItem(i, 'value', maskMoney(e.target.value))}
                                                    required
                                                    className="w-full mb-1 rounded-md border-gray-300 text-sm" />
                                                <input type="date" value={item.date}
                                                    onChange={e => updateInstallmentItem(i, 'date', e.target.value)}
                                                    required
                                                    className="w-full rounded-md border-gray-300 text-sm" />
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        )}
                    </StandardModal.Section>

                    {/* Card 4: Dados Bancários */}
                    <StandardModal.Section title="Dados Bancários" icon="🏦">
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
                    </StandardModal.Section>

                    {/* Card 5: Rateio (Allocation) */}
                    <StandardModal.Section title="Rateio" icon="📊">
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
                    </StandardModal.Section>

                    {/* Card 6: Observações */}
                    <StandardModal.Section title="Observações" icon="📝">
                        <textarea value={form.data.observations} onChange={e => form.setData('observations', e.target.value)}
                            rows={3} placeholder="Observações adicionais..."
                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                    </StandardModal.Section>
        </StandardModal>
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

    const handleSubmit = () => {
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
        <StandardModal
            show={true}
            onClose={onClose}
            title={`${forward ? 'Avançar' : 'Retornar'} Ordem #${order.id}`}
            headerColor={forward ? 'bg-green-600' : 'bg-yellow-500'}
            maxWidth="lg"
            onSubmit={handleSubmit}
            errorMessage={error}
            footer={
                <StandardModal.Footer
                    onCancel={onClose}
                    onSubmit="submit"
                    submitLabel={forward ? 'Avançar' : 'Retornar'}
                    submitColor={forward ? 'bg-green-600 hover:bg-green-700' : 'bg-yellow-500 hover:bg-yellow-600'}
                />
            }
        >
                    {/* Status Transition Visual */}
                    <div className="flex items-center justify-center space-x-4 bg-gray-50 -mx-6 -mt-5 mb-5 px-6 py-4 border-b">
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
        </StandardModal>
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
                            <td className="px-4 py-3"><StatusBadge variant={STATUS_VARIANT_MAP[p.status] || 'gray'}>{p.status_label}</StatusBadge></td>
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
                <Button variant="primary" onClick={onApply} icon={MagnifyingGlassIcon}>Filtrar</Button>
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

/**
 * Select dependente da Área (departamento gerencial). Lista as classes
 * analíticas do departamento selecionado com o CC que cada uma representa
 * exibido inline no label.
 */
function ManagementClassCascadeSelect({ value, onChange, options, hasDepartment, error }) {
    const helper = !hasDepartment
        ? 'Selecione uma Área primeiro.'
        : options.length === 0
        ? 'Nenhuma classe gerencial cadastrada para esta Área.'
        : null;

    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Classe Gerencial *</label>
            <select
                value={value}
                onChange={e => onChange(e.target.value)}
                disabled={!hasDepartment || options.length === 0}
                required
                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-50 disabled:text-gray-500"
            >
                <option value="">Selecione</option>
                {options.map(m => (
                    <option key={m.id} value={m.id}>{m.name}</option>
                ))}
            </select>
            {helper && <p className="mt-1 text-xs text-gray-500">{helper}</p>}
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}

/**
 * Mostra o CC derivado da Classe Gerencial como read-only. Não tem select —
 * o CC é fixado pela cascata. Se não houver CC vinculado, indica hint.
 */
function CostCenterReadonly({ costCenter, error }) {
    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Centro de Custo</label>
            <div className="w-full rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-700 min-h-[38px] flex items-center">
                {costCenter
                    ? <span>{costCenter.name}</span>
                    : <span className="text-xs text-gray-400">Derivado da classe gerencial</span>}
            </div>
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}

/**
 * Select especializado para Conta Contábil — dependente do CC selecionado.
 * Carrega via `budgets.accounting-classes-for-cost-center` e mostra o consumo
 * inline no dropdown (previsto × realizado × % utilização).
 *
 * Abaixo do select, renderiza um mini-indicador visual com barra de utilização
 * da opção selecionada quando há dados.
 */
function AccountingClassSelect({ value, onChange, options, loading, hasCostCenter, error }) {
    const BRL = (v) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(v || 0));
    const selected = options.find(o => o.id == value);

    const statusColor = (status) => {
        if (status === 'exceeded') return 'bg-red-500';
        if (status === 'warning') return 'bg-amber-500';
        return 'bg-green-500';
    };

    const helper = !hasCostCenter
        ? 'Selecione um Centro de Custo para ver as contas contábeis com orçamento.'
        : loading
        ? 'Carregando contas contábeis…'
        : options.length === 0
        ? 'Nenhuma conta contábil com orçamento ativo neste CC para o ano.'
        : null;

    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Conta Contábil</label>
            <select
                value={value}
                onChange={e => onChange(e.target.value)}
                disabled={!hasCostCenter || loading}
                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-50 disabled:text-gray-500"
            >
                <option value="">Selecione</option>
                {options.map(o => (
                    <option key={o.id} value={o.id}>
                        {o.name} — previsto {BRL(o.forecast_total)} · realizado {BRL(o.realized_total)} ({o.utilization_pct.toFixed(1)}%)
                    </option>
                ))}
            </select>
            {helper && <p className="mt-1 text-xs text-gray-500">{helper}</p>}
            {selected && (
                <div className="mt-1.5">
                    <div className="flex items-center gap-2">
                        <div className="flex-1 bg-gray-200 rounded-full h-1.5 overflow-hidden">
                            <div
                                className={`h-1.5 rounded-full ${statusColor(selected.status)}`}
                                style={{ width: `${Math.min(selected.utilization_pct, 100)}%` }}
                            />
                        </div>
                        <span className="text-xs font-medium text-gray-700">
                            {BRL(selected.available)} disponível
                        </span>
                    </div>
                </div>
            )}
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
