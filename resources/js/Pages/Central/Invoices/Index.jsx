import { Head, useForm, router } from '@inertiajs/react';
import CentralLayout from '@/Layouts/CentralLayout';
import { useState } from 'react';
import {
    PlusIcon, PencilIcon, EyeIcon, XMarkIcon, CheckIcon,
    MagnifyingGlassIcon, BanknotesIcon, ArrowPathIcon,
    CurrencyDollarIcon, ClockIcon, ExclamationTriangleIcon,
} from '@heroicons/react/24/outline';

// Currency helpers (same pattern as Plans)
function centsToDisplay(cents) {
    if (!cents) return '0,00';
    const str = String(cents).padStart(3, '0');
    const intPart = str.slice(0, -2).replace(/^0+(?=\d)/, '') || '0';
    const decPart = str.slice(-2);
    return `${intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.')},${decPart}`;
}
function centsToNumber(cents) { return cents / 100; }

const STATUS_LABELS = {
    pending: 'Pendente',
    paid: 'Pago',
    overdue: 'Vencido',
    cancelled: 'Cancelado',
};
const STATUS_COLORS = {
    pending: 'bg-yellow-100 text-yellow-800',
    paid: 'bg-green-100 text-green-800',
    overdue: 'bg-red-100 text-red-800',
    cancelled: 'bg-gray-100 text-gray-600',
};
const CYCLE_LABELS = { monthly: 'Mensal', yearly: 'Anual' };
const CYCLE_COLORS = { monthly: 'bg-blue-50 text-blue-700', yearly: 'bg-purple-50 text-purple-700' };
const PAYMENT_METHODS = [
    { value: 'pix', label: 'PIX' },
    { value: 'boleto', label: 'Boleto' },
    { value: 'cartao', label: 'Cartão de Crédito' },
    { value: 'transferencia', label: 'Transferência' },
    { value: 'dinheiro', label: 'Dinheiro' },
    { value: 'outro', label: 'Outro' },
];

function formatBRL(value) {
    return `R$ ${Number(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`;
}

export default function Index({ invoices, stats, tenants, plans, filters, asaasConfigured }) {
    const [showCreate, setShowCreate] = useState(false);
    const [editing, setEditing] = useState(null);
    const [viewing, setViewing] = useState(null);
    const [markingPaid, setMarkingPaid] = useState(null);
    const [showBulk, setShowBulk] = useState(false);
    const [chargingAsaas, setChargingAsaas] = useState(null);
    const [search, setSearch] = useState(filters.search || '');

    const handleSearch = (e) => {
        e.preventDefault();
        router.get('/admin/invoices', { ...filters, search }, { preserveState: true });
    };

    const handleFilter = (key, value) => {
        router.get('/admin/invoices', { ...filters, [key]: value }, { preserveState: true });
    };

    return (
        <CentralLayout title="Faturas">
            <Head title="Faturas - Mercury SaaS" />

            {/* Statistics */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
                <StatCard icon={CurrencyDollarIcon} label="MRR" subtitle="Receita recorrente mensal" value={formatBRL(stats.mrr)} color="bg-indigo-500" />
                <StatCard icon={ClockIcon} label="Pendentes" subtitle="Aguardando pagamento" value={formatBRL(stats.total_pending)} color="bg-yellow-500" />
                <StatCard icon={ExclamationTriangleIcon} label="Vencidas" subtitle="Pagamento em atraso" value={formatBRL(stats.total_overdue)} color="bg-red-500" />
                <StatCard icon={CheckIcon} label="Pagas este mês" subtitle="Recebido no período" value={formatBRL(stats.paid_this_month)} color="bg-green-500" />
            </div>

            {/* Filters */}
            <div className="flex flex-wrap items-center gap-3 mb-4">
                <form onSubmit={handleSearch} className="relative w-64">
                    <MagnifyingGlassIcon className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                    <input type="text" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Buscar tenant..." className="pl-9 w-full rounded-md border-gray-300 shadow-sm text-sm" />
                </form>
                <select value={filters.status || ''} onChange={(e) => handleFilter('status', e.target.value)} className="rounded-md border-gray-300 shadow-sm text-sm">
                    <option value="">Todos os status</option>
                    {Object.entries(STATUS_LABELS).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                </select>
                <select value={filters.billing_cycle || ''} onChange={(e) => handleFilter('billing_cycle', e.target.value)} className="rounded-md border-gray-300 shadow-sm text-sm">
                    <option value="">Todos os ciclos</option>
                    <option value="monthly">Mensal</option>
                    <option value="yearly">Anual</option>
                </select>
                <div className="ml-auto flex gap-2">
                    <button onClick={() => setShowBulk(true)} className="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-indigo-700 bg-indigo-100 rounded-md hover:bg-indigo-200 transition-colors">
                        <ArrowPathIcon className="h-4 w-4" /> Gerar em Lote
                    </button>
                    <button onClick={() => setShowCreate(true)} className="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-md hover:bg-indigo-700 shadow-sm">
                        <PlusIcon className="h-4 w-4" /> Nova Fatura
                    </button>
                </div>
            </div>

            {/* Table */}
            <div className="bg-white shadow rounded-lg overflow-hidden">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tenant</th>
                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Período</th>
                            <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Valor</th>
                            <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Ciclo</th>
                            <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vencimento</th>
                            <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                        {invoices.data?.map((inv) => (
                            <tr key={inv.id} className="hover:bg-gray-50">
                                <td className="px-4 py-3">
                                    <div className="text-sm font-medium text-gray-900">{inv.tenant_name}</div>
                                    <div className="text-xs text-gray-400">{inv.plan_name}</div>
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-500">{inv.billing_period_start} - {inv.billing_period_end}</td>
                                <td className="px-4 py-3 text-sm font-semibold text-gray-900 text-right">{formatBRL(inv.amount)}</td>
                                <td className="px-4 py-3 text-center">
                                    <span className={`inline-flex rounded px-2 py-0.5 text-xs font-medium ${CYCLE_COLORS[inv.billing_cycle] || ''}`}>
                                        {CYCLE_LABELS[inv.billing_cycle] || inv.billing_cycle}
                                    </span>
                                </td>
                                <td className="px-4 py-3 text-center">
                                    <span className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ${STATUS_COLORS[inv.status] || ''}`}>
                                        {STATUS_LABELS[inv.status] || inv.status}
                                    </span>
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-500">
                                    {inv.due_at}
                                    {inv.paid_at && <div className="text-xs text-green-600">Pago {inv.paid_at}</div>}
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <div className="flex justify-end gap-1">
                                        <button onClick={() => setViewing(inv)} title="Ver detalhes" className="inline-flex items-center justify-center w-8 h-8 rounded-md bg-indigo-100 text-indigo-700 hover:bg-indigo-200 transition-colors">
                                            <EyeIcon className="h-4 w-4" />
                                        </button>
                                        {(inv.status === 'pending' || inv.status === 'overdue') && (
                                            <>
                                                <button onClick={() => setEditing(inv)} title="Editar" className="inline-flex items-center justify-center w-8 h-8 rounded-md bg-amber-100 text-amber-700 hover:bg-amber-200 transition-colors">
                                                    <PencilIcon className="h-4 w-4" />
                                                </button>
                                                {asaasConfigured && !inv.gateway_id && (
                                                    <button onClick={() => setChargingAsaas(inv)} title="Cobrar via Asaas" className="inline-flex items-center justify-center w-8 h-8 rounded-md bg-purple-100 text-purple-700 hover:bg-purple-200 transition-colors">
                                                        <BanknotesIcon className="h-4 w-4" />
                                                    </button>
                                                )}
                                                {inv.gateway_id && (
                                                    <button onClick={() => router.post(`/admin/invoices/${inv.id}/sync-asaas`)} title="Sincronizar com Asaas" className="inline-flex items-center justify-center w-8 h-8 rounded-md bg-cyan-100 text-cyan-700 hover:bg-cyan-200 transition-colors">
                                                        <ArrowPathIcon className="h-4 w-4" />
                                                    </button>
                                                )}
                                                <button onClick={() => setMarkingPaid(inv)} title="Marcar como pago" className="inline-flex items-center justify-center w-8 h-8 rounded-md bg-green-100 text-green-700 hover:bg-green-200 transition-colors">
                                                    <CheckIcon className="h-4 w-4" />
                                                </button>
                                                <button onClick={() => { if (confirm('Cancelar esta fatura?')) router.post(`/admin/invoices/${inv.id}/cancel`); }} title="Cancelar" className="inline-flex items-center justify-center w-8 h-8 rounded-md bg-red-100 text-red-700 hover:bg-red-200 transition-colors">
                                                    <XMarkIcon className="h-4 w-4" />
                                                </button>
                                            </>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {invoices.data?.length === 0 && (
                            <tr><td colSpan={7} className="px-4 py-12 text-center text-sm text-gray-500">Nenhuma fatura encontrada.</td></tr>
                        )}
                    </tbody>
                </table>
            </div>

            {/* Pagination */}
            {invoices.last_page > 1 && (
                <div className="flex justify-center gap-2 mt-4">
                    {invoices.links?.map((link, i) => (
                        <button key={i} disabled={!link.url} onClick={() => link.url && router.get(link.url)} className={`px-3 py-1.5 text-sm rounded ${link.active ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 border hover:bg-gray-50'} ${!link.url ? 'opacity-50 cursor-not-allowed' : ''}`} dangerouslySetInnerHTML={{ __html: link.label }} />
                    ))}
                </div>
            )}

            {/* Modals */}
            {showCreate && <CreateInvoiceModal tenants={tenants} plans={plans} onClose={() => setShowCreate(false)} />}
            {editing && <EditInvoiceModal invoice={editing} onClose={() => setEditing(null)} />}
            {viewing && <ViewInvoiceModal invoice={viewing} onClose={() => setViewing(null)} />}
            {markingPaid && <MarkAsPaidModal invoice={markingPaid} onClose={() => setMarkingPaid(null)} />}
            {showBulk && <BulkGenerateModal onClose={() => setShowBulk(false)} />}
            {chargingAsaas && <ChargeAsaasModal invoice={chargingAsaas} onClose={() => setChargingAsaas(null)} />}
        </CentralLayout>
    );
}

function StatCard({ icon: Icon, label, subtitle, value, color }) {
    return (
        <div className="bg-white shadow rounded-lg p-5">
            <div className="flex items-center">
                <div className={`flex-shrink-0 rounded-md ${color} p-3`}>
                    <Icon className="h-5 w-5 text-white" />
                </div>
                <div className="ml-4">
                    <p className="text-sm font-medium text-gray-500">{label}</p>
                    <p className="text-xl font-bold text-gray-900">{value}</p>
                    {subtitle && <p className="text-xs text-gray-400">{subtitle}</p>}
                </div>
            </div>
        </div>
    );
}

// =================== CREATE INVOICE MODAL ===================

function CreateInvoiceModal({ tenants, plans, onClose }) {
    const { data, setData, post, processing, errors } = useForm({
        tenant_id: '',
        plan_id: '',
        amount: 0,
        billing_cycle: 'monthly',
        billing_period_start: new Date().toISOString().slice(0, 10),
        billing_period_end: '',
        due_at: '',
        notes: '',
        payment_url: '',
    });
    const [cents, setCents] = useState(0);
    const [fromPlan, setFromPlan] = useState(false);

    const handleCurrencyKeyDown = (e) => {
        if (e.key === 'Backspace') {
            e.preventDefault();
            const nc = Math.floor(cents / 10);
            setCents(nc);
            setData('amount', centsToNumber(nc));
        } else if (/^\d$/.test(e.key)) {
            e.preventDefault();
            const nc = cents * 10 + parseInt(e.key, 10);
            setCents(nc);
            setData('amount', centsToNumber(nc));
        }
    };

    const handleTenantChange = (tenantId) => {
        setData('tenant_id', tenantId);
        if (fromPlan && tenantId) {
            // We don't have plan price in tenants list, so user must set it manually
            // This could be enhanced later
        }
    };

    const submit = (e) => {
        e.preventDefault();
        post('/admin/invoices', { onSuccess: onClose });
    };

    return (
        <Modal title="Nova Fatura" subtitle="Crie uma fatura manualmente para um tenant." onClose={onClose}>
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                    <Field label="Tenant *" help="Empresa que receberá a cobrança.">
                        <select value={data.tenant_id} onChange={(e) => handleTenantChange(e.target.value)} className="block w-full rounded-md border-gray-300 shadow-sm text-sm" required>
                            <option value="">Selecione...</option>
                            {tenants.map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
                        </select>
                        {errors.tenant_id && <p className="mt-1 text-xs text-red-600">{errors.tenant_id}</p>}
                    </Field>
                    <Field label="Ciclo *" help="Mensal ou anual.">
                        <select value={data.billing_cycle} onChange={(e) => setData('billing_cycle', e.target.value)} className="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="monthly">Mensal</option>
                            <option value="yearly">Anual</option>
                        </select>
                    </Field>
                </div>
                <Field label="Valor *" help="Valor em reais (R$). Digite como calculadora.">
                    <div className="relative">
                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-500">R$</span>
                        <input type="text" value={centsToDisplay(cents)} onKeyDown={handleCurrencyKeyDown} onChange={() => {}} className="block w-full pl-9 pr-3 py-2 rounded-md border-gray-300 shadow-sm text-sm text-right" inputMode="numeric" />
                    </div>
                    {errors.amount && <p className="mt-1 text-xs text-red-600">{errors.amount}</p>}
                </Field>
                <div className="grid grid-cols-2 gap-4">
                    <Field label="Início do Período *" help="Data de início da cobertura.">
                        <input type="date" value={data.billing_period_start} onChange={(e) => setData('billing_period_start', e.target.value)} className="block w-full rounded-md border-gray-300 shadow-sm text-sm" required />
                    </Field>
                    <Field label="Fim do Período *" help="Data de fim da cobertura.">
                        <input type="date" value={data.billing_period_end} onChange={(e) => setData('billing_period_end', e.target.value)} className="block w-full rounded-md border-gray-300 shadow-sm text-sm" required />
                    </Field>
                </div>
                <Field label="Vencimento *" help="Data limite para pagamento.">
                    <input type="date" value={data.due_at} onChange={(e) => setData('due_at', e.target.value)} className="block w-full rounded-md border-gray-300 shadow-sm text-sm" required />
                </Field>
                <Field label="Notas" help="Observações internas (não visíveis ao tenant).">
                    <textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} className="block w-full rounded-md border-gray-300 shadow-sm text-sm" rows="2" />
                </Field>
                <ModalActions onClose={onClose} processing={processing} label="Criar Fatura" />
            </form>
        </Modal>
    );
}

// =================== EDIT INVOICE MODAL ===================

function EditInvoiceModal({ invoice, onClose }) {
    const { data, setData, put, processing, errors } = useForm({
        amount: invoice.amount,
        billing_cycle: invoice.billing_cycle,
        due_at: invoice.due_at ? invoice.due_at.split('/').reverse().join('-') : '',
        notes: invoice.notes || '',
        payment_url: invoice.payment_url || '',
    });
    const [cents, setCents] = useState(Math.round((invoice.amount || 0) * 100));

    const handleCurrencyKeyDown = (e) => {
        if (e.key === 'Backspace') {
            e.preventDefault();
            const nc = Math.floor(cents / 10);
            setCents(nc);
            setData('amount', centsToNumber(nc));
        } else if (/^\d$/.test(e.key)) {
            e.preventDefault();
            const nc = cents * 10 + parseInt(e.key, 10);
            setCents(nc);
            setData('amount', centsToNumber(nc));
        }
    };

    const submit = (e) => {
        e.preventDefault();
        put(`/admin/invoices/${invoice.id}`, { onSuccess: onClose });
    };

    return (
        <Modal title={`Editar Fatura #${invoice.id}`} subtitle={`Tenant: ${invoice.tenant_name}`} onClose={onClose}>
            <form onSubmit={submit} className="space-y-4">
                <Field label="Valor" help="Valor em reais (R$).">
                    <div className="relative">
                        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-500">R$</span>
                        <input type="text" value={centsToDisplay(cents)} onKeyDown={handleCurrencyKeyDown} onChange={() => {}} className="block w-full pl-9 pr-3 py-2 rounded-md border-gray-300 shadow-sm text-sm text-right" inputMode="numeric" />
                    </div>
                </Field>
                <Field label="Vencimento">
                    <input type="date" value={data.due_at} onChange={(e) => setData('due_at', e.target.value)} className="block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                </Field>
                <Field label="Link de Pagamento" help="URL onde o tenant pode pagar (futuro Asaas).">
                    <input type="text" value={data.payment_url} onChange={(e) => setData('payment_url', e.target.value)} className="block w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="https://..." />
                </Field>
                <Field label="Notas">
                    <textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} className="block w-full rounded-md border-gray-300 shadow-sm text-sm" rows="2" />
                </Field>
                <ModalActions onClose={onClose} processing={processing} label="Salvar" />
            </form>
        </Modal>
    );
}

// =================== MARK AS PAID MODAL ===================

function MarkAsPaidModal({ invoice, onClose }) {
    const { data, setData, post, processing } = useForm({
        payment_method: 'pix',
        paid_at: new Date().toISOString().slice(0, 10),
        transaction_id: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(`/admin/invoices/${invoice.id}/mark-paid`, { onSuccess: onClose });
    };

    return (
        <Modal title="Confirmar Pagamento" subtitle={`Fatura #${invoice.id} — ${invoice.tenant_name} — ${formatBRL(invoice.amount)}`} onClose={onClose}>
            <form onSubmit={submit} className="space-y-4">
                <Field label="Método de Pagamento *" help="Como o tenant efetuou o pagamento.">
                    <select value={data.payment_method} onChange={(e) => setData('payment_method', e.target.value)} className="block w-full rounded-md border-gray-300 shadow-sm text-sm" required>
                        {PAYMENT_METHODS.map((m) => <option key={m.value} value={m.value}>{m.label}</option>)}
                    </select>
                </Field>
                <Field label="Data do Pagamento" help="Data em que o pagamento foi recebido.">
                    <input type="date" value={data.paid_at} onChange={(e) => setData('paid_at', e.target.value)} className="block w-full rounded-md border-gray-300 shadow-sm text-sm" />
                </Field>
                <Field label="ID da Transação" help="Identificador do pagamento no banco ou gateway (opcional).">
                    <input type="text" value={data.transaction_id} onChange={(e) => setData('transaction_id', e.target.value)} className="block w-full rounded-md border-gray-300 shadow-sm text-sm" placeholder="Ex: E12345678..." />
                </Field>
                <ModalActions onClose={onClose} processing={processing} label="Confirmar Pagamento" color="bg-green-600 hover:bg-green-700" />
            </form>
        </Modal>
    );
}

// =================== VIEW INVOICE MODAL ===================

function ViewInvoiceModal({ invoice, onClose }) {
    const [pixData, setPixData] = useState(null);
    const [loadingPix, setLoadingPix] = useState(false);

    const loadPixQrCode = async () => {
        setLoadingPix(true);
        try {
            const response = await fetch(`/admin/invoices/${invoice.id}/pix-qrcode`);
            const data = await response.json();
            if (data.encodedImage) {
                setPixData(data);
            }
        } catch (e) {
            // Silently fail
        }
        setLoadingPix(false);
    };

    return (
        <Modal title={`Fatura #${invoice.id}`} subtitle={invoice.tenant_name} onClose={onClose}>
            <dl className="grid grid-cols-2 gap-4 text-sm">
                <DL label="Tenant" value={invoice.tenant_name} />
                <DL label="Plano" value={invoice.plan_name} />
                <DL label="Valor" value={formatBRL(invoice.amount)} />
                <DL label="Ciclo" value={CYCLE_LABELS[invoice.billing_cycle]} />
                <DL label="Período" value={`${invoice.billing_period_start} - ${invoice.billing_period_end}`} />
                <DL label="Vencimento" value={invoice.due_at} />
                <DL label="Status" value={
                    <span className={`inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium ${STATUS_COLORS[invoice.status]}`}>
                        {STATUS_LABELS[invoice.status]}
                    </span>
                } />
                <DL label="Pago em" value={invoice.paid_at || '—'} />
                <DL label="Método" value={invoice.payment_method || '—'} />
                <DL label="ID Transação" value={invoice.transaction_id || '—'} />
                <DL label="Criada em" value={invoice.created_at} />
                <DL label="Geração" value={invoice.auto_generated ? 'Automática' : 'Manual'} />
            </dl>

            {/* Payment Link */}
            {invoice.payment_url && (
                <div className="mt-4 p-3 bg-indigo-50 rounded-lg">
                    <p className="text-xs font-medium text-indigo-700 mb-1">Link de Pagamento</p>
                    <a href={invoice.payment_url} target="_blank" rel="noopener" className="text-sm text-indigo-600 underline break-all">{invoice.payment_url}</a>
                </div>
            )}

            {/* PIX QR Code */}
            {invoice.gateway_id && invoice.status !== 'paid' && invoice.status !== 'cancelled' && (
                <div className="mt-4 p-3 bg-gray-50 rounded-lg">
                    <div className="flex items-center justify-between mb-2">
                        <p className="text-xs font-medium text-gray-700">PIX QR Code</p>
                        {!pixData && (
                            <button onClick={loadPixQrCode} disabled={loadingPix} className="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                {loadingPix ? 'Carregando...' : 'Carregar QR Code'}
                            </button>
                        )}
                    </div>
                    {pixData && (
                        <div className="text-center space-y-2">
                            <img src={`data:image/png;base64,${pixData.encodedImage}`} alt="PIX QR Code" className="mx-auto w-48 h-48" />
                            <div className="bg-white border rounded p-2">
                                <p className="text-xs text-gray-500 mb-1">Copia e Cola:</p>
                                <p className="text-xs font-mono break-all text-gray-700 select-all">{pixData.payload}</p>
                            </div>
                            {pixData.expirationDate && (
                                <p className="text-xs text-gray-400">Expira em: {pixData.expirationDate}</p>
                            )}
                        </div>
                    )}
                </div>
            )}

            {invoice.notes && (
                <div className="mt-4 p-3 bg-yellow-50 rounded-lg">
                    <p className="text-xs font-medium text-yellow-700 mb-1">Notas</p>
                    <p className="text-sm text-gray-700">{invoice.notes}</p>
                </div>
            )}

            <div className="flex justify-end pt-4 mt-4 border-t">
                <button onClick={onClose} className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200">Fechar</button>
            </div>
        </Modal>
    );
}

// =================== CHARGE ASAAS MODAL ===================

function ChargeAsaasModal({ invoice, onClose }) {
    const { data, setData, post, processing } = useForm({
        billing_type: 'PIX',
    });

    const submit = (e) => {
        e.preventDefault();
        post(`/admin/invoices/${invoice.id}/charge-asaas`, { onSuccess: onClose });
    };

    return (
        <Modal title="Cobrar via Asaas" subtitle={`Fatura #${invoice.id} — ${invoice.tenant_name} — ${formatBRL(invoice.amount)}`} onClose={onClose}>
            <form onSubmit={submit} className="space-y-4">
                <Field label="Tipo de Cobrança *" help="Como o tenant poderá pagar esta fatura.">
                    <div className="grid grid-cols-3 gap-3 mt-1">
                        {[
                            { value: 'PIX', label: 'PIX', desc: 'QR Code + Copia e Cola', color: 'border-green-300 bg-green-50' },
                            { value: 'BOLETO', label: 'Boleto', desc: 'Boleto bancário', color: 'border-blue-300 bg-blue-50' },
                            { value: 'UNDEFINED', label: 'Todos', desc: 'Tenant escolhe', color: 'border-purple-300 bg-purple-50' },
                        ].map((opt) => (
                            <label key={opt.value} className={`relative flex flex-col items-center p-3 rounded-lg border-2 cursor-pointer transition-colors ${
                                data.billing_type === opt.value ? opt.color + ' ring-2 ring-indigo-500' : 'border-gray-200 hover:border-gray-300'
                            }`}>
                                <input type="radio" name="billing_type" value={opt.value} checked={data.billing_type === opt.value} onChange={(e) => setData('billing_type', e.target.value)} className="sr-only" />
                                <span className="text-sm font-semibold text-gray-900">{opt.label}</span>
                                <span className="text-xs text-gray-500 text-center">{opt.desc}</span>
                            </label>
                        ))}
                    </div>
                </Field>

                <div className="rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-700">
                    <strong>O que acontece:</strong>
                    <ul className="mt-1 ml-4 list-disc text-xs space-y-1">
                        <li>Um cliente é criado/atualizado no Asaas para este tenant</li>
                        <li>A cobrança é gerada e o tenant recebe o link de pagamento por email</li>
                        <li>Quando o pagamento for confirmado, o webhook atualiza a fatura automaticamente</li>
                        <li>O link de pagamento fica disponível nos detalhes da fatura</li>
                    </ul>
                </div>

                <ModalActions onClose={onClose} processing={processing} label="Criar Cobrança" color="bg-purple-600 hover:bg-purple-700" />
            </form>
        </Modal>
    );
}

function DL({ label, value }) {
    return (
        <div>
            <dt className="text-xs font-medium text-gray-500">{label}</dt>
            <dd className="mt-1 text-gray-900">{value}</dd>
        </div>
    );
}

// =================== BULK GENERATE MODAL ===================

function BulkGenerateModal({ onClose }) {
    const { data, setData, post, processing } = useForm({
        billing_cycle: 'monthly',
    });

    const submit = (e) => {
        e.preventDefault();
        post('/admin/invoices/generate-bulk', { onSuccess: onClose });
    };

    return (
        <Modal title="Gerar Faturas em Lote" subtitle="Gera faturas para todos os tenants ativos com plano que não possuem fatura para o período atual." onClose={onClose}>
            <form onSubmit={submit} className="space-y-4">
                <Field label="Ciclo de Cobrança *" help="Mensal: gera para o mês atual. Anual: gera para o ano atual.">
                    <select value={data.billing_cycle} onChange={(e) => setData('billing_cycle', e.target.value)} className="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="monthly">Mensal</option>
                        <option value="yearly">Anual</option>
                    </select>
                </Field>
                <div className="rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-700">
                    <strong>O que será gerado:</strong>
                    <ul className="mt-1 ml-4 list-disc text-xs space-y-1">
                        <li>Uma fatura para cada tenant ativo que possua plano com preço definido</li>
                        <li>Tenants que já possuem fatura para o período serão ignorados</li>
                        <li>O valor será o preço {data.billing_cycle === 'monthly' ? 'mensal' : 'anual'} do plano do tenant</li>
                        <li>Vencimento: 10 dias após o início do período</li>
                    </ul>
                </div>
                <ModalActions onClose={onClose} processing={processing} label="Gerar Faturas" />
            </form>
        </Modal>
    );
}

// =================== SHARED ===================

function Modal({ title, subtitle, onClose, children }) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center">
            <div className="fixed inset-0 bg-gray-600/75" onClick={onClose} />
            <div className="relative bg-white rounded-lg shadow-xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div className="px-6 py-4 border-b">
                    <h3 className="text-lg font-semibold text-gray-900">{title}</h3>
                    {subtitle && <p className="mt-1 text-sm text-gray-500">{subtitle}</p>}
                </div>
                <div className="p-6">{children}</div>
            </div>
        </div>
    );
}

function Field({ label, help, children }) {
    return (
        <div>
            <label className="block text-sm font-medium text-gray-700">{label}</label>
            {help && <p className="text-xs text-gray-400 mb-1">{help}</p>}
            {children}
        </div>
    );
}

function ModalActions({ onClose, processing, label, color = 'bg-indigo-600 hover:bg-indigo-700' }) {
    return (
        <div className="flex justify-end gap-3 pt-4 border-t">
            <button type="button" onClick={onClose} className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200">Cancelar</button>
            <button type="submit" disabled={processing} className={`px-4 py-2 text-sm font-medium text-white rounded-md disabled:opacity-50 ${color}`}>
                {processing ? 'Salvando...' : label}
            </button>
        </div>
    );
}
