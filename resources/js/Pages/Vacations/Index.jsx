import { Head, router, useForm } from '@inertiajs/react';
import {
    PlusIcon, XMarkIcon, ArrowRightIcon,
    CheckCircleIcon, XCircleIcon, ExclamationTriangleIcon, ClockIcon,
    ArrowPathIcon, PaperAirplaneIcon, PlayIcon, FlagIcon,
} from '@heroicons/react/24/outline';
import { useState, useEffect, useMemo } from 'react';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import DataTable from '@/Components/DataTable';
import StandardModal from '@/Components/StandardModal';

const STATUS_STYLES = {
    draft:            { bg: 'bg-gray-100',   text: 'text-gray-800',   dot: 'bg-gray-400' },
    pending_manager:  { bg: 'bg-yellow-100', text: 'text-yellow-800', dot: 'bg-yellow-500' },
    approved_manager: { bg: 'bg-blue-100',   text: 'text-blue-800',   dot: 'bg-blue-500' },
    approved_rh:      { bg: 'bg-indigo-100', text: 'text-indigo-800', dot: 'bg-indigo-500' },
    in_progress:      { bg: 'bg-green-100',  text: 'text-green-800',  dot: 'bg-green-500' },
    completed:        { bg: 'bg-emerald-100',text: 'text-emerald-800',dot: 'bg-emerald-500' },
    cancelled:        { bg: 'bg-red-100',    text: 'text-red-800',    dot: 'bg-red-500' },
    rejected_manager: { bg: 'bg-orange-100', text: 'text-orange-800', dot: 'bg-orange-500' },
    rejected_rh:      { bg: 'bg-red-100',    text: 'text-red-800',    dot: 'bg-red-500' },
};

export default function Index({
    vacations, selects = {}, filters = {}, statusOptions = {}, statusCounts = {},
}) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission(PERMISSIONS.CREATE_VACATIONS);
    const canEdit = hasPermission(PERMISSIONS.EDIT_VACATIONS);
    const canApproveManager = hasPermission(PERMISSIONS.APPROVE_VACATIONS_MANAGER);
    const canApproveRH = hasPermission(PERMISSIONS.APPROVE_VACATIONS_RH);

    const [showCreateModal, setShowCreateModal] = useState(false);
    const [showDetailModal, setShowDetailModal] = useState(false);
    const [detailId, setDetailId] = useState(null);
    const [showTransitionModal, setShowTransitionModal] = useState(false);
    const [transitionData, setTransitionData] = useState(null);

    const applyFilter = (key, value) => {
        const currentUrl = new URL(window.location);
        if (value) {
            currentUrl.searchParams.set(key, value);
        } else {
            currentUrl.searchParams.delete(key);
        }
        currentUrl.searchParams.delete('page');
        router.visit(currentUrl.toString(), { preserveState: true, preserveScroll: true });
    };

    const clearFilters = () => {
        router.visit(route('vacations.index'), { preserveState: true, preserveScroll: true });
    };

    const hasActiveFilters = filters.status || filters.store_id;

    const openDetail = (id) => { setDetailId(id); setShowDetailModal(true); };

    const columns = [
        {
            field: 'employee_name',
            label: 'Funcionário',
            sortable: true,
            render: (v) => (
                <div>
                    <div className="text-sm font-medium text-gray-900">{v.employee_short_name || v.employee_name}</div>
                    <div className="text-xs text-gray-500">{v.position}</div>
                </div>
            ),
        },
        {
            field: 'store',
            label: 'Loja',
            render: (v) => v.store?.name || '-',
        },
        {
            field: 'date_start',
            label: 'Período',
            sortable: true,
            render: (v) => (
                <div>
                    <div className="text-sm text-gray-900">{v.date_start} - {v.date_end}</div>
                    <div className="text-xs text-gray-500">Retorno: {v.date_return}</div>
                </div>
            ),
        },
        {
            field: 'days_quantity',
            label: 'Dias',
            sortable: true,
            render: (v) => (
                <>
                    <span className="text-sm font-semibold text-gray-900">{v.days_quantity}</span>
                    {v.sell_days > 0 && <span className="text-xs text-orange-600 ml-1">(+{v.sell_days} abono)</span>}
                </>
            ),
        },
        {
            field: 'installment',
            label: 'Parcela',
            render: (v) => (
                <span className="inline-flex items-center justify-center h-6 w-6 rounded-full bg-indigo-100 text-indigo-700 text-xs font-bold">
                    {v.installment}
                </span>
            ),
        },
        {
            field: 'status',
            label: 'Status',
            sortable: true,
            render: (v) => (
                <>
                    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${STATUS_STYLES[v.status]?.bg} ${STATUS_STYLES[v.status]?.text}`}>
                        <span className={`h-1.5 w-1.5 rounded-full mr-1.5 ${STATUS_STYLES[v.status]?.dot}`} />
                        {v.status_label}
                    </span>
                    {v.is_retroactive && <span className="ml-1 text-xs text-purple-600 font-medium">(Retroativa)</span>}
                </>
            ),
        },
        {
            field: 'payment_deadline',
            label: 'Prazo Pgto',
            sortable: true,
            render: (v) => v.payment_deadline || '-',
        },
        {
            field: 'actions',
            label: 'Ações',
            render: (v) => (
                <ActionButtons onView={() => openDetail(v.id)}>
                    {canEdit && v.status === 'draft' && (
                        <ActionButtons.Custom variant="info" icon={PaperAirplaneIcon} title="Enviar para Gestor"
                            onClick={() => { setTransitionData({ vacation: v, newStatus: 'pending_manager' }); setShowTransitionModal(true); }} />
                    )}
                    {canApproveManager && v.status === 'pending_manager' && (
                        <>
                            <ActionButtons.Custom variant="success" icon={CheckCircleIcon} title="Aprovar (Gestor)"
                                onClick={() => { setTransitionData({ vacation: v, newStatus: 'approved_manager' }); setShowTransitionModal(true); }} />
                            <ActionButtons.Custom variant="danger" icon={XCircleIcon} title="Rejeitar (Gestor)"
                                onClick={() => { setTransitionData({ vacation: v, newStatus: 'rejected_manager' }); setShowTransitionModal(true); }} />
                        </>
                    )}
                    {canApproveRH && v.status === 'approved_manager' && (
                        <>
                            <ActionButtons.Custom variant="success" icon={CheckCircleIcon} title="Aprovar (RH)"
                                onClick={() => { setTransitionData({ vacation: v, newStatus: 'approved_rh' }); setShowTransitionModal(true); }} />
                            <ActionButtons.Custom variant="danger" icon={XCircleIcon} title="Rejeitar (RH)"
                                onClick={() => { setTransitionData({ vacation: v, newStatus: 'rejected_rh' }); setShowTransitionModal(true); }} />
                        </>
                    )}
                    {canEdit && v.status === 'approved_rh' && (
                        <ActionButtons.Custom variant="success" icon={PlayIcon} title="Iniciar Gozo"
                            onClick={() => { setTransitionData({ vacation: v, newStatus: 'in_progress' }); setShowTransitionModal(true); }} />
                    )}
                    {canEdit && v.status === 'in_progress' && (
                        <ActionButtons.Custom variant="success" icon={FlagIcon} title="Finalizar"
                            onClick={() => { setTransitionData({ vacation: v, newStatus: 'completed' }); setShowTransitionModal(true); }} />
                    )}
                </ActionButtons>
            ),
        },
    ];

    return (
        <>
            <Head title="Férias" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6">
                        <div className="flex justify-between items-center">
                            <div>
                                <h1 className="text-3xl font-bold text-gray-900">
                                    Gestão de Férias
                                </h1>
                                <p className="mt-1 text-sm text-gray-600">
                                    Solicitação, aprovação e controle de férias
                                </p>
                            </div>
                            {canCreate && (
                                <Button
                                    variant="primary"
                                    onClick={() => setShowCreateModal(true)}
                                    icon={PlusIcon}
                                >
                                    Nova Solicitação
                                </Button>
                            )}
                        </div>
                    </div>

                    {/* Stats Cards */}
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        {['pending_manager', 'approved_rh', 'in_progress', 'completed'].map(status => {
                            const data = statusCounts[status] || { label: status, count: 0 };
                            const style = STATUS_STYLES[status] || {};
                            return (
                                <div key={status} className="bg-white shadow-sm rounded-lg p-4">
                                    <div className="text-sm font-medium text-gray-500">{data.label}</div>
                                    <div className={`text-2xl font-bold ${style.text || 'text-gray-900'}`}>{data.count}</div>
                                </div>
                            );
                        })}
                    </div>

                    {/* Filtros */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                <select
                                    value={filters.status || ''}
                                    onChange={(e) => applyFilter('status', e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">Todos os Status</option>
                                    {Object.entries(statusOptions).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Loja</label>
                                <select
                                    value={filters.store_id || ''}
                                    onChange={(e) => applyFilter('store_id', e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">Todas as Lojas</option>
                                    {(selects.stores || []).map(s => <option key={s.id} value={s.id}>{s.code} - {s.name}</option>)}
                                </select>
                            </div>
                            <div>
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    className="h-[42px] w-[150px]"
                                    onClick={clearFilters}
                                    disabled={!hasActiveFilters}
                                    icon={XMarkIcon}
                                >
                                    Limpar Filtros
                                </Button>
                            </div>
                        </div>
                    </div>

                    {/* DataTable */}
                    <DataTable
                        data={vacations}
                        columns={columns}
                        searchPlaceholder="Buscar funcionário..."
                        emptyMessage="Nenhuma solicitação de férias encontrada"
                        onRowClick={(v) => openDetail(v.id)}
                        perPageOptions={[15, 25, 50]}
                    />
                </div>
            </div>

            {/* Create Modal */}
            {showCreateModal && (
                <CreateModal selects={selects} onClose={() => setShowCreateModal(false)} />
            )}

            {/* Detail Modal */}
            {showDetailModal && detailId && (
                <DetailModal vacationId={detailId} canEdit={canEdit}
                    onClose={() => { setShowDetailModal(false); setDetailId(null); }}
                    onTransition={(v, ns) => { setShowDetailModal(false); setTransitionData({ vacation: v, newStatus: ns }); setShowTransitionModal(true); }} />
            )}

            {/* Transition Modal */}
            {showTransitionModal && transitionData && (
                <TransitionModal data={transitionData} statusOptions={statusOptions}
                    onClose={() => { setShowTransitionModal(false); setTransitionData(null); }} />
            )}
        </>
    );
}


// ============================================================
// CREATE MODAL — Fiel à implementação v1
// ============================================================
function CreateModal({ selects, onClose }) {
    const form = useForm({
        employee_id: '', store_filter: '', vacation_period_id: '', date_start: '', days_quantity: 30,
        installment: 1, sell_allowance: false, sell_days: 0, advance_13th: false,
        override_reason: '', notes: '', is_retroactive: false, retroactive_reason: '',
    });

    const [balance, setBalance] = useState(null);
    const [loadingBalance, setLoadingBalance] = useState(false);
    const [dateCheck, setDateCheck] = useState(null);
    const [daysFeedback, setDaysFeedback] = useState(null); // {type: 'error'|'warning'|'success', message: string}

    // Funcionários filtrados por loja
    const filteredEmployees = useMemo(() => {
        const emps = selects.employees || [];
        if (!form.data.store_filter) return emps;
        return emps.filter(e => e.store_id === form.data.store_filter);
    }, [selects.employees, form.data.store_filter]);

    // Carregar saldo ao selecionar funcionário
    useEffect(() => {
        if (!form.data.employee_id) { setBalance(null); return; }
        setLoadingBalance(true);
        fetch(route('vacations.balance', form.data.employee_id), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(r => r.json())
            .then(d => { setBalance(d); setLoadingBalance(false); form.setData('days_quantity', d.default_days || 30); })
            .catch(() => setLoadingBalance(false));
    }, [form.data.employee_id]);

    // Verificar data de início (blackout)
    useEffect(() => {
        if (!form.data.date_start || form.data.is_retroactive) { setDateCheck(null); return; }
        // Validação local de fim de semana
        const d = new Date(form.data.date_start + 'T12:00:00');
        const dow = d.getDay();
        if (dow === 0 || dow === 6) {
            setDateCheck({ valid: false, suggested_formatted: null, local_error: 'Não é permitido iniciar férias em fim de semana (Art. 134 §3 CLT).' });
            return;
        }
        // Sexta-feira (véspera de DSR)
        if (dow === 5) {
            setDateCheck({ valid: false, suggested_formatted: null, local_error: 'Não é permitido iniciar férias em véspera de descanso semanal (Art. 134 §3 CLT).' });
            return;
        }
        // Validação no servidor
        fetch(route('vacations.check-date') + '?date=' + form.data.date_start, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(r => r.json())
            .then(d => setDateCheck({ ...d, local_error: null }))
            .catch(() => {});
    }, [form.data.date_start, form.data.is_retroactive]);

    // Calcular datas de fim e retorno + validação de dias
    const calculatedDates = useMemo(() => {
        if (!form.data.date_start || !form.data.days_quantity) return null;
        const start = new Date(form.data.date_start + 'T12:00:00');
        const days = parseInt(form.data.days_quantity) || 0;
        if (days < 5 || isNaN(start.getTime())) return null;
        const end = new Date(start);
        end.setDate(end.getDate() + days - 1);
        const ret = new Date(end);
        ret.setDate(ret.getDate() + 1);
        // Pular fins de semana para retorno
        while (ret.getDay() === 0 || ret.getDay() === 6) ret.setDate(ret.getDate() + 1);
        const fmt = (d) => d.toLocaleDateString('pt-BR');
        const today = new Date(); today.setHours(0,0,0,0);
        return { date_end: fmt(end), date_return: fmt(ret), end_raw: end, start_raw: start, is_end_future: end >= today, is_start_future: start >= today };
    }, [form.data.date_start, form.data.days_quantity]);

    // Validação inline dos dias (regras CLT)
    useEffect(() => {
        const days = parseInt(form.data.days_quantity) || 0;
        if (!days || !balance) { setDaysFeedback(null); return; }
        const selectedPeriod = balance.periods.find(p => p.id == form.data.vacation_period_id);
        const periodBalance = selectedPeriod?.balance ?? 0;
        const defaultDays = balance.default_days || 30;
        const installment = parseInt(form.data.installment) || 1;

        if (days < 5) {
            setDaysFeedback({ type: 'error', message: 'Mínimo de 5 dias por parcela (Art. 134 §1 CLT).' });
        } else if (installment === 1 && days < 14 && periodBalance >= 14) {
            setDaysFeedback({ type: 'error', message: 'A primeira parcela deve ter no mínimo 14 dias consecutivos (Art. 134 §1 CLT).' });
        } else if (selectedPeriod && days > periodBalance) {
            setDaysFeedback({ type: 'error', message: `Saldo insuficiente. Disponível: ${periodBalance} dias.` });
        } else if (days !== defaultDays) {
            setDaysFeedback({ type: 'warning', message: `Padrão para este cargo: ${defaultDays} dias. Justificativa será obrigatória.` });
        } else {
            setDaysFeedback({ type: 'success', message: `${days} dias de férias.` });
        }
    }, [form.data.days_quantity, form.data.vacation_period_id, form.data.installment, balance]);

    // Parcelas disponíveis (dinâmico)
    const selectedPeriod = balance?.periods?.find(p => p.id == form.data.vacation_period_id);
    const needsOverride = balance && parseInt(form.data.days_quantity) !== (balance.default_days || 30) && parseInt(form.data.days_quantity) > 0;

    const handleSubmit = () => {
        form.post(route('vacations.store'), { onSuccess: () => onClose() });
    };

    return (
        <StandardModal
            show={true}
            onClose={onClose}
            title="Nova Solicitação de Férias"
            headerIcon={<CalendarDaysIcon className="h-5 w-5" />}
            headerColor="bg-indigo-600"
            onSubmit={handleSubmit}
            errorMessage={form.errors.vacation}
            footer={
                <StandardModal.Footer
                    onCancel={onClose}
                    onSubmit="submit"
                    submitLabel={form.data.is_retroactive ? 'Registrar Retroativa' : 'Criar Solicitação'}
                    processing={form.processing}
                />
            }
        >
                        {/* Card 1: Funcionário */}
                        <StandardModal.Section title="Funcionário" icon="👤">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Loja</label>
                                    <select value={form.data.store_filter} onChange={e => { form.setData('store_filter', e.target.value); form.setData('employee_id', ''); }}
                                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="">Todas as Lojas</option>
                                        {(selects.stores || []).map(s => <option key={s.id} value={s.code}>{s.code} - {s.name}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Funcionário *</label>
                                    <select value={form.data.employee_id} onChange={e => form.setData('employee_id', e.target.value)} required
                                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="">Selecione o funcionário</option>
                                        {filteredEmployees.map(e => <option key={e.id} value={e.id}>{e.name}</option>)}
                                    </select>
                                </div>
                            </div>

                            {/* Período aquisitivo */}
                            {loadingBalance && <div className="text-sm text-gray-500 flex items-center gap-2 mt-3"><ArrowPathIcon className="h-4 w-4 animate-spin" /> Carregando períodos...</div>}
                            {balance && balance.periods.length > 0 && (
                                <div className="mt-3">
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Período Aquisitivo *</label>
                                    <select value={form.data.vacation_period_id} onChange={e => form.setData('vacation_period_id', e.target.value)} required
                                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="">Selecione o período</option>
                                        {balance.periods.map(p => (
                                            <option key={p.id} value={p.id}>{p.label} — {p.status} (Disponível: {p.balance} dias)</option>
                                        ))}
                                    </select>
                                </div>
                            )}
                            {balance && balance.periods.length === 0 && (
                                <p className="text-sm text-red-600 mt-3">Nenhum período com saldo disponível para este funcionário.</p>
                            )}
                        </StandardModal.Section>

                        {/* Card 2: Saldo do Período (aparece após selecionar período) */}
                        {selectedPeriod && (
                            <StandardModal.Section title="Saldo do Período" icon="📊">
                                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <div className="bg-blue-50 rounded-lg p-3 text-center">
                                        <p className="text-[10px] font-semibold text-blue-500 uppercase">Dias de Direito</p>
                                        <p className="text-xl font-bold text-blue-700">{selectedPeriod.days_entitled}</p>
                                    </div>
                                    <div className="bg-orange-50 rounded-lg p-3 text-center">
                                        <p className="text-[10px] font-semibold text-orange-500 uppercase">Dias Gozados</p>
                                        <p className="text-xl font-bold text-orange-700">{selectedPeriod.days_taken}</p>
                                    </div>
                                    <div className="bg-green-50 rounded-lg p-3 text-center">
                                        <p className="text-[10px] font-semibold text-green-500 uppercase">Saldo Restante</p>
                                        <p className="text-xl font-bold text-green-700">{selectedPeriod.balance}</p>
                                    </div>
                                    <div className="bg-gray-50 rounded-lg p-3 text-center">
                                        <p className="text-[10px] font-semibold text-gray-500 uppercase">Limite Concessivo</p>
                                        <p className={`text-sm font-bold ${selectedPeriod.is_expired ? 'text-red-600' : 'text-gray-700'}`}>
                                            {selectedPeriod.date_limit}
                                            {selectedPeriod.is_expired && <span className="block text-[10px] text-red-500">VENCIDO</span>}
                                        </p>
                                    </div>
                                </div>
                                <p className="text-xs text-gray-500 mt-2">Dias padrão para este cargo: <strong>{balance.default_days}</strong> dias</p>
                            </StandardModal.Section>
                        )}

                        {/* Card 3: Datas */}
                        <StandardModal.Section title="Datas" icon="📅">
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Data Início *</label>
                                    <input type="date" value={form.data.date_start} onChange={e => form.setData('date_start', e.target.value)} required
                                        className={`w-full rounded-md shadow-sm sm:text-sm ${dateCheck && !dateCheck.valid ? 'border-red-400 focus:border-red-500 focus:ring-red-500' : dateCheck?.valid ? 'border-green-400 focus:border-green-500 focus:ring-green-500' : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500'}`} />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Qtd. Dias *</label>
                                    <input type="number" min="5" max="30" value={form.data.days_quantity}
                                        onChange={e => form.setData('days_quantity', parseInt(e.target.value) || 5)}
                                        className={`w-full rounded-md shadow-sm sm:text-sm ${daysFeedback?.type === 'error' ? 'border-red-400' : daysFeedback?.type === 'success' ? 'border-green-400' : 'border-gray-300'} focus:border-indigo-500 focus:ring-indigo-500`} />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Data Fim</label>
                                    <input type="text" readOnly value={calculatedDates?.date_end || ''} placeholder="—"
                                        className="w-full rounded-md border-gray-200 bg-gray-50 text-gray-600 shadow-sm sm:text-sm cursor-not-allowed" />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Data Retorno</label>
                                    <input type="text" readOnly value={calculatedDates?.date_return || ''} placeholder="—"
                                        className="w-full rounded-md border-gray-200 bg-gray-50 text-gray-600 shadow-sm sm:text-sm cursor-not-allowed" />
                                </div>
                            </div>

                            {/* Feedback de data */}
                            {dateCheck && !dateCheck.valid && (
                                <div className="mt-2 p-2 bg-red-50 border border-red-200 rounded text-xs text-red-700">
                                    <ExclamationTriangleIcon className="inline h-3.5 w-3.5 mr-1" />
                                    {dateCheck.local_error || 'Data inválida para início de férias (Art. 134 §3 CLT).'}
                                    {dateCheck.suggested_formatted && <span className="font-semibold"> Sugerida: {dateCheck.suggested_formatted}</span>}
                                </div>
                            )}
                            {dateCheck?.valid && (
                                <div className="mt-2 p-2 bg-green-50 border border-green-200 rounded text-xs text-green-700">
                                    <CheckCircleIcon className="inline h-3.5 w-3.5 mr-1" /> Data válida para início de férias.
                                </div>
                            )}

                            {/* Feedback de dias */}
                            {daysFeedback && (
                                <div className={`mt-2 p-2 rounded text-xs border ${daysFeedback.type === 'error' ? 'bg-red-50 border-red-200 text-red-700' : daysFeedback.type === 'warning' ? 'bg-yellow-50 border-yellow-200 text-yellow-700' : 'bg-green-50 border-green-200 text-green-700'}`}>
                                    {daysFeedback.type === 'error' ? <ExclamationTriangleIcon className="inline h-3.5 w-3.5 mr-1" /> : daysFeedback.type === 'warning' ? <ExclamationTriangleIcon className="inline h-3.5 w-3.5 mr-1" /> : <CheckCircleIcon className="inline h-3.5 w-3.5 mr-1" />}
                                    {daysFeedback.message}
                                </div>
                            )}

                            {/* Feedback de retroativa — datas devem estar no passado */}
                            {form.data.is_retroactive && calculatedDates && calculatedDates.is_start_future && (
                                <div className="mt-2 p-2 bg-red-50 border border-red-200 rounded text-xs text-red-700">
                                    <ExclamationTriangleIcon className="inline h-3.5 w-3.5 mr-1" />
                                    Férias retroativas: a data de início deve ser no passado.
                                </div>
                            )}
                            {form.data.is_retroactive && calculatedDates && !calculatedDates.is_start_future && calculatedDates.is_end_future && (
                                <div className="mt-2 p-2 bg-red-50 border border-red-200 rounded text-xs text-red-700">
                                    <ExclamationTriangleIcon className="inline h-3.5 w-3.5 mr-1" />
                                    Férias retroativas: o período inteiro deve estar no passado. A data de término ({calculatedDates.date_end}) ainda não passou.
                                </div>
                            )}
                            {form.data.is_retroactive && calculatedDates && !calculatedDates.is_start_future && !calculatedDates.is_end_future && (
                                <div className="mt-2 p-2 bg-green-50 border border-green-200 rounded text-xs text-green-700">
                                    <CheckCircleIcon className="inline h-3.5 w-3.5 mr-1" />
                                    Período retroativo válido: {calculatedDates.date_end} já passou.
                                </div>
                            )}
                        </StandardModal.Section>

                        {/* Card 4: Opções */}
                        <StandardModal.Section title="Opções" icon="⚙️">
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Parcela *</label>
                                    <select value={form.data.installment} onChange={e => form.setData('installment', parseInt(e.target.value))}
                                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        {[1, 2, 3].map(n => (
                                            <option key={n} value={n}>{n}ª Parcela</option>
                                        ))}
                                    </select>
                                </div>
                                <label className="flex items-center gap-2 p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                                    <input type="checkbox" checked={form.data.sell_allowance}
                                        onChange={e => form.setData('sell_allowance', e.target.checked)}
                                        className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                    <div>
                                        <span className="text-sm font-medium text-gray-900">Abono Pecuniário</span>
                                        <span className="text-xs text-gray-500 block">Vender até 1/3 dos dias</span>
                                    </div>
                                </label>
                                <label className="flex items-center gap-2 p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                                    <input type="checkbox" checked={form.data.advance_13th}
                                        onChange={e => form.setData('advance_13th', e.target.checked)}
                                        className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                    <div>
                                        <span className="text-sm font-medium text-gray-900">Adiantamento 13º</span>
                                        <span className="text-xs text-gray-500 block">Antecipar parcela do 13º</span>
                                    </div>
                                </label>
                            </div>

                            {form.data.sell_allowance && (
                                <div className="mt-3 bg-orange-50 border border-orange-200 rounded-lg p-3">
                                    <label className="block text-sm font-medium text-orange-700 mb-1">Dias para Venda (Abono Pecuniário)</label>
                                    <input type="number" min="0" max="10" value={form.data.sell_days}
                                        onChange={e => form.setData('sell_days', parseInt(e.target.value) || 0)}
                                        className="w-32 rounded-md border-orange-300 shadow-sm focus:border-orange-500 focus:ring-orange-500 sm:text-sm" />
                                    <p className="text-xs text-orange-600 mt-1">Máximo: 1/3 dos dias de direito (Art. 143 CLT)</p>
                                </div>
                            )}

                            {/* Justificativa de override (aparece se dias diferem do padrão) */}
                            {needsOverride && (
                                <div className="mt-3 bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                                    <label className="block text-sm font-medium text-yellow-700 mb-1">
                                        Justificativa para Alteração do Padrão *
                                    </label>
                                    <input type="text" value={form.data.override_reason} maxLength={255} required
                                        onChange={e => form.setData('override_reason', e.target.value)}
                                        placeholder="Informe o motivo da alteração do período padrão..."
                                        className="w-full rounded-md border-yellow-300 shadow-sm focus:border-yellow-500 focus:ring-yellow-500 sm:text-sm" />
                                    <p className="text-xs text-yellow-600 mt-1">
                                        Padrão: {balance?.default_days} dias. Solicitado: {form.data.days_quantity} dias.
                                    </p>
                                </div>
                            )}
                        </StandardModal.Section>

                        {/* Card 5: Retroativa */}
                        <StandardModal.Section title="Férias Retroativas" icon="🔄">
                            <label className="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" checked={form.data.is_retroactive}
                                    onChange={e => form.setData('is_retroactive', e.target.checked)}
                                    className="rounded border-gray-300 text-purple-600 focus:ring-purple-500" />
                                <div>
                                    <span className="text-sm font-medium text-gray-900">Registrar férias retroativas</span>
                                    <span className="text-xs text-gray-500 block">Férias já gozadas com data de início no passado</span>
                                </div>
                            </label>

                            {form.data.is_retroactive && (
                                <div className="mt-3 bg-purple-50 border border-purple-200 rounded-lg p-3">
                                    <label className="block text-sm font-medium text-purple-700 mb-1">Justificativa *</label>
                                    <textarea value={form.data.retroactive_reason}
                                        onChange={e => form.setData('retroactive_reason', e.target.value)}
                                        rows={2} required placeholder="Motivo do registro retroativo..."
                                        className="w-full rounded-md border-purple-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm" />
                                </div>
                            )}
                        </StandardModal.Section>

                        {/* Card 6: Observações */}
                        <StandardModal.Section title="Observações" icon="📝">
                            <textarea value={form.data.notes} onChange={e => form.setData('notes', e.target.value)}
                                rows={3} maxLength={1000} placeholder="Observações adicionais..."
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            <p className="text-xs text-gray-400 mt-1 text-right">{(form.data.notes || '').length}/1000</p>
                        </StandardModal.Section>
        </StandardModal>
    );
}


// ============================================================
// DETAIL MODAL
// ============================================================
function DetailModal({ vacationId, canEdit, onClose, onTransition }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        setLoading(true);
        fetch(route('vacations.show', vacationId), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(r => r.json())
            .then(d => { setData(d); setLoading(false); })
            .catch(() => setLoading(false));
    }, [vacationId]);

    const v = data?.vacation;
    const sc = v ? STATUS_STYLES[v.status] : {};
    const nextTransitions = {
        draft: ['pending_manager'],
        pending_manager: ['approved_manager', 'rejected_manager'],
        approved_manager: ['approved_rh', 'rejected_rh'],
        approved_rh: ['in_progress'],
        in_progress: ['completed'],
    };
    const available = v ? (nextTransitions[v.status] || []) : [];

    const transitionLabels = {
        pending_manager: { label: 'Enviar para Gestor', color: 'bg-yellow-500 hover:bg-yellow-600' },
        approved_manager: { label: 'Aprovar (Gestor)', color: 'bg-blue-600 hover:bg-blue-700' },
        approved_rh: { label: 'Aprovar (RH)', color: 'bg-indigo-600 hover:bg-indigo-700' },
        in_progress: { label: 'Iniciar Gozo', color: 'bg-green-600 hover:bg-green-700' },
        completed: { label: 'Finalizar', color: 'bg-emerald-600 hover:bg-emerald-700' },
        rejected_manager: { label: 'Rejeitar (Gestor)', color: 'bg-orange-500 hover:bg-orange-600' },
        rejected_rh: { label: 'Rejeitar (RH)', color: 'bg-red-500 hover:bg-red-600' },
        cancelled: { label: 'Cancelar', color: 'bg-red-600 hover:bg-red-700' },
    };

    const badges = [];
    if (v) {
        badges.push({ text: v.status_label });
        if (v.is_retroactive) badges.push({ text: 'Retroativa', className: 'bg-purple-400/50 text-white' });
    }

    return (
        <StandardModal
            show={true}
            onClose={onClose}
            title={v ? `Férias #${v.id} — ${v.employee_name}` : 'Férias'}
            headerColor="bg-indigo-600"
            headerBadges={badges}
            loading={loading}
            errorMessage={!loading && !v ? 'Erro ao carregar.' : undefined}
            footer={canEdit && v && available.length > 0 ? (
                <StandardModal.Footer>
                    {available.map(ns => {
                        const t = transitionLabels[ns] || {};
                        return (
                            <button key={ns} onClick={() => onTransition(v, ns)}
                                className={`px-4 py-2 text-sm font-medium text-white rounded-lg ${t.color}`}>
                                {t.label}
                            </button>
                        );
                    })}
                    {!['completed', 'cancelled', 'rejected_manager', 'rejected_rh'].includes(v.status) && (
                        <button onClick={() => onTransition(v, 'cancelled')}
                            className="ml-auto px-4 py-2 text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100">
                            Cancelar Férias
                        </button>
                    )}
                </StandardModal.Footer>
            ) : undefined}
        >
                                {/* Resumo */}
                                <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                                    <StandardModal.InfoCard label="Início" value={v.date_start} icon={<CalendarDaysIcon className="h-4 w-4" />} />
                                    <StandardModal.InfoCard label="Fim" value={v.date_end} />
                                    <StandardModal.InfoCard label="Dias" value={v.days_quantity} highlight />
                                    <StandardModal.InfoCard label="Retorno" value={v.date_return} />
                                </div>

                                {/* Detalhes */}
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="bg-gray-50 rounded-lg p-4 space-y-2">
                                        <h4 className="text-xs font-semibold text-gray-500 uppercase">Funcionário</h4>
                                        <p className="text-sm"><strong>{v.employee?.name}</strong></p>
                                        <p className="text-xs text-gray-500">{v.employee?.position} | {v.employee?.store}</p>
                                        <p className="text-xs text-gray-500">Admissão: {v.employee?.admission_date}</p>
                                    </div>
                                    <div className="bg-gray-50 rounded-lg p-4 space-y-2">
                                        <h4 className="text-xs font-semibold text-gray-500 uppercase">Período Aquisitivo</h4>
                                        <p className="text-sm">{data.period?.label}</p>
                                        <p className="text-xs text-gray-500">Direito: {data.period?.days_entitled}d | Gozados: {data.period?.days_taken}d | Saldo: <strong>{data.period?.balance}d</strong></p>
                                    </div>
                                </div>

                                <div className="grid grid-cols-4 gap-3">
                                    <StandardModal.MiniField label="Parcela" value={`${v.installment}ª`} />
                                    <StandardModal.MiniField label="Abono" value={v.sell_allowance ? `${v.sell_days} dias` : 'Não'} />
                                    <StandardModal.MiniField label="13º Antecipado" value={v.advance_13th ? 'Sim' : 'Não'} />
                                    <StandardModal.MiniField label="Prazo Pagamento" value={v.payment_deadline || '-'} />
                                </div>

                                {/* Aprovações */}
                                {(v.manager_approved_by || v.hr_approved_by || v.rejected_by || v.cancelled_by) && (
                                    <div className="border rounded-lg p-4 space-y-2">
                                        <h4 className="text-xs font-semibold text-gray-500 uppercase">Aprovações</h4>
                                        {v.manager_approved_by && <p className="text-sm text-blue-700"><CheckCircleIcon className="inline h-4 w-4 mr-1" />Gestor: {v.manager_approved_by} em {v.manager_approved_at}</p>}
                                        {v.hr_approved_by && <p className="text-sm text-indigo-700"><CheckCircleIcon className="inline h-4 w-4 mr-1" />RH: {v.hr_approved_by} em {v.hr_approved_at}</p>}
                                        {v.rejected_by && <p className="text-sm text-red-700"><XCircleIcon className="inline h-4 w-4 mr-1" />Rejeitado por {v.rejected_by} em {v.rejected_at}: {v.rejection_reason}</p>}
                                        {v.cancelled_by && <p className="text-sm text-red-700"><XCircleIcon className="inline h-4 w-4 mr-1" />Cancelado por {v.cancelled_by} em {v.cancelled_at}: {v.cancellation_reason}</p>}
                                    </div>
                                )}

                                {/* Timeline */}
                                {data.logs?.length > 0 && (
                                    <div className="border rounded-lg p-4">
                                        <h4 className="text-xs font-semibold text-gray-500 uppercase mb-3">Histórico</h4>
                                        <div className="relative">
                                            <div className="absolute left-[7px] top-2 bottom-2 w-0.5 bg-gray-200" />
                                            <div className="space-y-3">
                                                {data.logs.map(l => (
                                                    <div key={l.id} className="flex items-start gap-3 relative">
                                                        <div className="mt-0.5 h-4 w-4 rounded-full bg-indigo-500 ring-4 ring-white shrink-0 z-10" />
                                                        <div className="flex-1 bg-gray-50 rounded p-2">
                                                            <div className="text-sm"><strong>{l.new_status}</strong>{l.old_status && <span className="text-gray-400 text-xs ml-2">(de {l.old_status})</span>}</div>
                                                            <div className="text-xs text-gray-500">{l.changed_by} — {l.created_at}{l.notes && <span className="italic ml-1">"{l.notes}"</span>}</div>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    </div>
                                )}

        </StandardModal>
    );
}


// ============================================================
// TRANSITION MODAL
// ============================================================
function TransitionModal({ data, statusOptions, onClose }) {
    const { vacation, newStatus } = data;
    const needsReason = ['rejected_manager', 'rejected_rh', 'cancelled'].includes(newStatus);
    const [notes, setNotes] = useState('');
    const [error, setError] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const labels = {
        pending_manager: 'Enviar para Aprovação do Gestor',
        approved_manager: 'Aprovar como Gestor',
        approved_rh: 'Aprovar como RH',
        in_progress: 'Iniciar Gozo de Férias',
        completed: 'Finalizar Férias',
        rejected_manager: 'Rejeitar (Gestor)',
        rejected_rh: 'Rejeitar (RH)',
        cancelled: 'Cancelar Férias',
        draft: 'Voltar para Rascunho',
    };

    const headerColors = {
        approved_manager: 'bg-blue-600', approved_rh: 'bg-indigo-600',
        in_progress: 'bg-green-600', completed: 'bg-emerald-600',
        rejected_manager: 'bg-orange-500', rejected_rh: 'bg-red-500',
        cancelled: 'bg-red-600', pending_manager: 'bg-yellow-500', draft: 'bg-gray-500',
    };

    const handleSubmit = () => {
        if (needsReason && !notes.trim()) { setError('Motivo é obrigatório.'); return; }
        setSubmitting(true);
        setError('');

        fetch(route('vacations.transition', vacation.id), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ new_status: newStatus, notes: notes || null, cancellation_reason: newStatus === 'cancelled' ? notes : null }),
        })
        .then(r => r.json().then(d => ({ ok: r.ok, d })))
        .then(({ ok, d }) => {
            setSubmitting(false);
            if (!ok || d.error) setError(d.message || 'Erro na transição.');
            else { onClose(); router.reload(); }
        })
        .catch(() => { setSubmitting(false); setError('Erro de conexão.'); });
    };

    return (
        <StandardModal
            show={true}
            onClose={onClose}
            title={labels[newStatus] || newStatus}
            subtitle={`${vacation.employee_name || vacation.employee_short_name} — #${vacation.id}`}
            headerColor={headerColors[newStatus] || 'bg-gray-600'}
            maxWidth="md"
            onSubmit={handleSubmit}
            errorMessage={error}
            footer={
                <StandardModal.Footer
                    onCancel={onClose}
                    onSubmit="submit"
                    submitLabel="Confirmar"
                    submitColor={headerColors[newStatus] || 'bg-indigo-600 hover:bg-indigo-700'}
                    processing={submitting}
                />
            }
        >
                        <div className="text-sm text-gray-600">
                            <p><strong>Período:</strong> {vacation.date_start} a {vacation.date_end} ({vacation.days_quantity} dias)</p>
                            <p><strong>Status atual:</strong> {vacation.status_label}</p>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                {needsReason ? 'Motivo *' : 'Observações'}
                            </label>
                            <textarea value={notes} onChange={e => setNotes(e.target.value)}
                                rows={3} required={needsReason} placeholder={needsReason ? 'Informe o motivo...' : 'Observações opcionais...'}
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                        </div>
        </StandardModal>
    );
}
