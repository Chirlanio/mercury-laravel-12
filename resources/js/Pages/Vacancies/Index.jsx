import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import {
    BriefcaseIcon,
    PlusIcon,
    ArrowPathIcon,
    ClockIcon,
    CheckCircleIcon,
    XCircleIcon,
    ExclamationTriangleIcon,
    UserPlusIcon,
} from '@heroicons/react/24/outline';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import DataTable from '@/Components/DataTable';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import { TrashIcon } from '@heroicons/react/24/outline';

// Mapeamento de cores do backend → variantes do StatusBadge
const COLOR_MAP = {
    info: 'info', warning: 'warning', success: 'success', danger: 'danger',
    purple: 'purple', orange: 'orange', indigo: 'indigo', gray: 'gray',
};

export default function Index({
    vacancies,
    filters = {},
    statistics = {},
    statusOptions = {},
    statusColors = {},
    statusTransitions = {},
    requestTypeOptions = {},
    isStoreScoped = false,
    scopedStoreCode = null,
    selects = {},
}) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission(PERMISSIONS.CREATE_VACANCIES);
    const canEdit = hasPermission(PERMISSIONS.EDIT_VACANCIES);
    const canManage = hasPermission(PERMISSIONS.MANAGE_VACANCIES);
    const canDelete = hasPermission(PERMISSIONS.DELETE_VACANCIES);

    const { modals, selected, openModal, closeModal } = useModalManager([
        'create', 'detail', 'edit', 'transition',
    ]);

    // Form states
    const emptyCreate = {
        store_id: scopedStoreCode || '',
        position_id: '',
        work_schedule_id: '',
        request_type: 'headcount_increase',
        replaced_employee_id: '',
        predicted_sla_days: 30,
        delivery_forecast: '',
        comments: '',
    };
    const [createForm, setCreateForm] = useState(emptyCreate);
    const [createErrors, setCreateErrors] = useState({});
    const [createProcessing, setCreateProcessing] = useState(false);
    const [eligibleEmployees, setEligibleEmployees] = useState([]);

    const [editForm, setEditForm] = useState({});
    const [editErrors, setEditErrors] = useState({});
    const [editProcessing, setEditProcessing] = useState(false);

    const [transitionForm, setTransitionForm] = useState({
        to_status: '', note: '', recruiter_id: '', name: '', cpf: '', date_admission: '',
    });
    const [transitionErrors, setTransitionErrors] = useState({});
    const [transitionProcessing, setTransitionProcessing] = useState(false);
    const [recruiters, setRecruiters] = useState([]);

    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleteReason, setDeleteReason] = useState('');
    const [deleteProcessing, setDeleteProcessing] = useState(false);

    // Carrega recrutadores uma vez (usado nos modais de transition e edit)
    useEffect(() => {
        if ((modals.transition || modals.edit) && recruiters.length === 0) {
            fetch(route('vacancies.recruiters'), { headers: { Accept: 'application/json' } })
                .then(r => r.json())
                .then(d => setRecruiters(d.recruiters || []));
        }
    }, [modals.transition, modals.edit]);

    // Carrega employees elegíveis quando muda tipo/loja no modal de criação
    useEffect(() => {
        if (!modals.create || createForm.request_type !== 'substitution' || !createForm.store_id) {
            setEligibleEmployees([]);
            return;
        }
        fetch(
            `${route('vacancies.eligible-employees')}?store_id=${encodeURIComponent(createForm.store_id)}`,
            { headers: { Accept: 'application/json' } }
        )
            .then(r => r.json())
            .then(d => setEligibleEmployees(d.employees || []));
    }, [modals.create, createForm.request_type, createForm.store_id]);

    const applyFilter = (key, value) => {
        const url = new URL(window.location);
        if (value) url.searchParams.set(key, value);
        else url.searchParams.delete(key);
        url.searchParams.delete('page');
        router.visit(url.toString(), { preserveState: true, preserveScroll: true });
    };

    const clearFilters = () => router.visit(route('vacancies.index'));

    const openDetail = (row) => {
        fetch(route('vacancies.show', row.id), { headers: { Accept: 'application/json' } })
            .then(r => r.json())
            .then(d => openModal('detail', d.vacancy));
    };

    const openEdit = (row) => {
        fetch(route('vacancies.show', row.id), { headers: { Accept: 'application/json' } })
            .then(r => r.json())
            .then(d => {
                setEditForm({
                    id: d.vacancy.id,
                    work_schedule_id: d.vacancy.work_schedule_id || '',
                    recruiter_id: d.vacancy.recruiter_id || '',
                    predicted_sla_days: d.vacancy.predicted_sla_days || 30,
                    delivery_forecast: d.vacancy.delivery_forecast || '',
                    interview_hr: d.vacancy.interview_hr || '',
                    evaluators_hr: d.vacancy.evaluators_hr || '',
                    interview_leader: d.vacancy.interview_leader || '',
                    evaluators_leader: d.vacancy.evaluators_leader || '',
                    comments: d.vacancy.comments || '',
                });
                setEditErrors({});
                openModal('edit', d.vacancy);
            });
    };

    const openTransition = (row) => {
        fetch(route('vacancies.show', row.id), { headers: { Accept: 'application/json' } })
            .then(r => r.json())
            .then(d => {
                setTransitionForm({
                    to_status: '', note: '', recruiter_id: d.vacancy.recruiter_id || '',
                    name: '', cpf: '', date_admission: new Date().toISOString().slice(0, 10),
                });
                setTransitionErrors({});
                openModal('transition', d.vacancy);
            });
    };

    const handleCreateSubmit = () => {
        setCreateProcessing(true);
        router.post(route('vacancies.store'), createForm, {
            onError: (errs) => setCreateErrors(errs),
            onSuccess: () => { setCreateForm(emptyCreate); setCreateErrors({}); closeModal('create'); },
            onFinish: () => setCreateProcessing(false),
        });
    };

    const handleEditSubmit = () => {
        setEditProcessing(true);
        router.put(route('vacancies.update', editForm.id), editForm, {
            onError: (errs) => setEditErrors(errs),
            onSuccess: () => { closeModal('edit'); setEditErrors({}); },
            onFinish: () => setEditProcessing(false),
        });
    };

    const handleTransitionSubmit = () => {
        setTransitionProcessing(true);
        router.post(route('vacancies.transition', selected?.id), transitionForm, {
            onError: (errs) => setTransitionErrors(errs),
            onSuccess: () => { closeModal('transition'); setTransitionErrors({}); },
            onFinish: () => setTransitionProcessing(false),
        });
    };

    const handleDeleteConfirm = () => {
        if (!deleteTarget) return;
        setDeleteProcessing(true);
        router.delete(route('vacancies.destroy', deleteTarget.id), {
            data: { deleted_reason: deleteReason },
            onSuccess: () => { setDeleteTarget(null); setDeleteReason(''); },
            onFinish: () => setDeleteProcessing(false),
        });
    };

    // Próximos status disponíveis para a vaga selecionada no modal de transition
    const availableNextStatuses = useMemo(() => {
        if (!selected?.status) return [];
        const next = statusTransitions[selected.status] || [];
        return next.map(s => ({ value: s, label: statusOptions[s] || s }));
    }, [selected?.status, statusTransitions, statusOptions]);

    const columns = [
        {
            field: 'store_id',
            label: 'Loja',
            render: (row) => <span className="font-mono text-xs">{row.store_id}</span>,
        },
        { field: 'position_name', label: 'Cargo' },
        {
            field: 'request_type_label',
            label: 'Tipo',
            render: (row) => (
                <StatusBadge variant={COLOR_MAP[row.request_type_color] || 'gray'}>
                    {row.request_type_label}
                </StatusBadge>
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
            field: 'recruiter_name',
            label: 'Recrutador',
            render: (row) => row.recruiter_name || <span className="text-gray-400">—</span>,
        },
        {
            field: 'delivery_forecast',
            label: 'Prazo',
            render: (row) => {
                if (!row.delivery_forecast) return <span className="text-gray-400">—</span>;
                const d = new Date(row.delivery_forecast).toLocaleDateString('pt-BR');
                if (row.is_overdue) {
                    return <StatusBadge variant="danger">{d} (atrasada)</StatusBadge>;
                }
                return <span className="text-sm">{d}</span>;
            },
        },
        {
            field: 'actions',
            label: 'Ações',
            render: (row) => (
                <ActionButtons
                    onView={() => openDetail(row)}
                    onEdit={canEdit && !row.is_terminal ? () => openEdit(row) : null}
                    onDelete={canDelete ? () => { setDeleteTarget(row); setDeleteReason(''); } : null}
                >
                    {canManage && !row.is_terminal && (
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

    const hasActiveFilters = filters.status || filters.request_type || filters.recruiter_id || filters.date_from || filters.date_to || filters.store_id;

    const statisticsCards = [
        {
            label: 'Ativas (Total)',
            value: statistics.total_active || 0,
            format: 'number',
            icon: BriefcaseIcon,
            color: 'indigo',
            sub: statistics.avg_effective_sla !== null && statistics.avg_effective_sla !== undefined
                ? `SLA médio: ${statistics.avg_effective_sla} dias`
                : null,
            active: !filters.status,
            onClick: () => applyFilter('status', ''),
        },
        {
            label: 'Abertas',
            value: statistics.open || 0,
            format: 'number',
            icon: ClockIcon,
            color: 'blue',
            active: filters.status === 'open',
            onClick: () => applyFilter('status', filters.status === 'open' ? '' : 'open'),
        },
        {
            label: 'Em Processamento',
            value: statistics.processing || 0,
            format: 'number',
            icon: ArrowPathIcon,
            color: 'yellow',
            active: filters.status === 'processing',
            onClick: () => applyFilter('status', filters.status === 'processing' ? '' : 'processing'),
        },
        {
            label: 'Em Admissão',
            value: statistics.in_admission || 0,
            format: 'number',
            icon: UserPlusIcon,
            color: 'purple',
            active: filters.status === 'in_admission',
            onClick: () => applyFilter('status', filters.status === 'in_admission' ? '' : 'in_admission'),
        },
        {
            label: 'Atrasadas',
            value: statistics.overdue || 0,
            format: 'number',
            icon: ExclamationTriangleIcon,
            color: 'red',
            sub: `Finalizadas 30d: ${statistics.finalized_last_30d || 0}`,
        },
    ];

    return (
        <>
            <Head title="Abertura de Vagas" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6 flex justify-between items-center">
                        <div>
                            <h1 className="text-3xl font-bold text-gray-900">Abertura de Vagas</h1>
                            <p className="mt-1 text-sm text-gray-600">
                                Substituição, aumento de quadro e volante — ciclo completo até o pré-cadastro do contratado
                                {isStoreScoped && scopedStoreCode && (
                                    <span className="ml-2 text-xs font-medium text-indigo-600">
                                        (escopo: loja {scopedStoreCode})
                                    </span>
                                )}
                            </p>
                        </div>
                        {canCreate && (
                            <Button
                                variant="primary"
                                onClick={() => {
                                    setCreateForm(emptyCreate);
                                    setCreateErrors({});
                                    openModal('create');
                                }}
                                icon={PlusIcon}
                            >
                                Nova Vaga
                            </Button>
                        )}
                    </div>

                    <StatisticsGrid cards={statisticsCards} />

                    {/* Filtros */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                            {!isStoreScoped && (
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">Loja</label>
                                    <select
                                        value={filters.store_id || ''}
                                        onChange={(e) => applyFilter('store_id', e.target.value)}
                                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="">Todas</option>
                                        {(selects.stores || []).map(s => (
                                            <option key={s.code} value={s.code}>{s.code} — {s.name}</option>
                                        ))}
                                    </select>
                                </div>
                            )}
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
                                <select
                                    value={filters.request_type || ''}
                                    onChange={(e) => applyFilter('request_type', e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">Todos</option>
                                    {Object.entries(requestTypeOptions).map(([k, v]) => (
                                        <option key={k} value={k}>{v}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                <select
                                    value={filters.status || ''}
                                    onChange={(e) => applyFilter('status', e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">Todos</option>
                                    {Object.entries(statusOptions).map(([k, v]) => (
                                        <option key={k} value={k}>{v}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Criada de</label>
                                <input
                                    type="date"
                                    value={filters.date_from || ''}
                                    onChange={(e) => applyFilter('date_from', e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">Criada até</label>
                                <input
                                    type="date"
                                    value={filters.date_to || ''}
                                    onChange={(e) => applyFilter('date_to', e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                        </div>
                        {hasActiveFilters && (
                            <div className="mt-3 text-right">
                                <button onClick={clearFilters} className="text-sm text-indigo-600 hover:underline">
                                    Limpar filtros
                                </button>
                            </div>
                        )}
                    </div>

                    <DataTable data={vacancies.data} columns={columns} pagination={vacancies} />
                </div>
            </div>

            {/* Modal: Create */}
            <StandardModal
                show={modals.create}
                onClose={() => closeModal('create')}
                title="Nova Vaga"
                subtitle="Abra uma solicitação de vaga para a loja"
                headerColor="bg-indigo-600"
                headerIcon={<BriefcaseIcon className="h-6 w-6" />}
                maxWidth="4xl"
                onSubmit={handleCreateSubmit}
                footer={
                    <StandardModal.Footer
                        onCancel={() => closeModal('create')}
                        onSubmit="submit"
                        submitLabel="Criar Vaga"
                        processing={createProcessing}
                    />
                }
            >
                <StandardModal.Section title="Dados da Vaga">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Loja *</label>
                            <select
                                value={createForm.store_id}
                                onChange={(e) => setCreateForm({ ...createForm, store_id: e.target.value })}
                                disabled={isStoreScoped}
                                className="w-full rounded-md border-gray-300 shadow-sm disabled:bg-gray-100"
                            >
                                <option value="">Selecione...</option>
                                {(selects.stores || []).map(s => (
                                    <option key={s.code} value={s.code}>{s.code} — {s.name}</option>
                                ))}
                            </select>
                            {createErrors.store_id && <p className="mt-1 text-xs text-red-600">{createErrors.store_id}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Tipo de Solicitação *</label>
                            <select
                                value={createForm.request_type}
                                onChange={(e) => setCreateForm({ ...createForm, request_type: e.target.value, replaced_employee_id: '' })}
                                className="w-full rounded-md border-gray-300 shadow-sm"
                            >
                                {Object.entries(requestTypeOptions).map(([k, v]) => (
                                    <option key={k} value={k}>{v}</option>
                                ))}
                            </select>
                            {createErrors.request_type && <p className="mt-1 text-xs text-red-600">{createErrors.request_type}</p>}
                        </div>

                        {createForm.request_type === 'substitution' && (
                            <div className="md:col-span-2">
                                <label className="block text-sm font-medium text-gray-700 mb-1">Colaborador a Substituir *</label>
                                <select
                                    value={createForm.replaced_employee_id}
                                    onChange={(e) => setCreateForm({ ...createForm, replaced_employee_id: e.target.value })}
                                    className="w-full rounded-md border-gray-300 shadow-sm"
                                >
                                    <option value="">
                                        {!createForm.store_id ? 'Selecione uma loja primeiro' : 'Selecione...'}
                                    </option>
                                    {eligibleEmployees.map(e => (
                                        <option key={e.id} value={e.id}>{e.name}</option>
                                    ))}
                                </select>
                                {createErrors.replaced_employee_id && <p className="mt-1 text-xs text-red-600">{createErrors.replaced_employee_id}</p>}
                            </div>
                        )}

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Cargo *</label>
                            <select
                                value={createForm.position_id}
                                onChange={(e) => setCreateForm({ ...createForm, position_id: e.target.value })}
                                className="w-full rounded-md border-gray-300 shadow-sm"
                            >
                                <option value="">Selecione...</option>
                                {(selects.positions || []).map(p => (
                                    <option key={p.id} value={p.id}>{p.name}</option>
                                ))}
                            </select>
                            {createErrors.position_id && <p className="mt-1 text-xs text-red-600">{createErrors.position_id}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Escala de Trabalho</label>
                            <select
                                value={createForm.work_schedule_id}
                                onChange={(e) => setCreateForm({ ...createForm, work_schedule_id: e.target.value })}
                                className="w-full rounded-md border-gray-300 shadow-sm"
                            >
                                <option value="">—</option>
                                {(selects.workSchedules || []).map(w => (
                                    <option key={w.id} value={w.id}>{w.name}</option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">SLA Previsto (dias) *</label>
                            <input
                                type="number"
                                min="1"
                                max="365"
                                value={createForm.predicted_sla_days}
                                onChange={(e) => setCreateForm({ ...createForm, predicted_sla_days: e.target.value })}
                                className="w-full rounded-md border-gray-300 shadow-sm"
                            />
                            {createErrors.predicted_sla_days && <p className="mt-1 text-xs text-red-600">{createErrors.predicted_sla_days}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Data Prevista (opcional)</label>
                            <input
                                type="date"
                                value={createForm.delivery_forecast}
                                onChange={(e) => setCreateForm({ ...createForm, delivery_forecast: e.target.value })}
                                className="w-full rounded-md border-gray-300 shadow-sm"
                            />
                            <p className="mt-1 text-xs text-gray-500">Se vazio, será calculado: hoje + SLA</p>
                        </div>
                    </div>
                </StandardModal.Section>

                <StandardModal.Section title="Observações">
                    <textarea
                        rows={4}
                        value={createForm.comments}
                        onChange={(e) => setCreateForm({ ...createForm, comments: e.target.value })}
                        placeholder="Informações adicionais para o recrutador..."
                        className="w-full rounded-md border-gray-300 shadow-sm"
                    />
                </StandardModal.Section>
            </StandardModal>

            {/* Modal: Detail */}
            <StandardModal
                show={modals.detail}
                onClose={() => closeModal('detail')}
                title={selected ? `Vaga #${selected.id}` : 'Detalhes'}
                subtitle={selected?.position_name}
                headerColor="bg-gray-700"
                headerIcon={<BriefcaseIcon className="h-6 w-6" />}
                headerBadges={selected ? [
                    { label: selected.status_label, variant: COLOR_MAP[selected.status_color] || 'gray' },
                    { label: selected.request_type_label, variant: COLOR_MAP[selected.request_type_color] || 'gray' },
                ] : []}
                maxWidth="5xl"
                footer={<StandardModal.Footer onCancel={() => closeModal('detail')} cancelLabel="Fechar" />}
            >
                {selected && (
                    <>
                        <StandardModal.Section title="Identificação">
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                                <StandardModal.Field label="Loja" value={selected.store_id} mono />
                                <StandardModal.Field label="Cargo" value={selected.position_name} />
                                <StandardModal.Field label="Escala" value={selected.work_schedule_name} />
                                <StandardModal.Field label="Recrutador" value={selected.recruiter_name} />
                            </div>
                        </StandardModal.Section>

                        {selected.replaced_employee && (
                            <StandardModal.Section title="Substituição">
                                <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
                                    <StandardModal.Field label="Colaborador" value={selected.replaced_employee.name} />
                                    <StandardModal.Field label="CPF" value={selected.replaced_employee.cpf} mono />
                                    {selected.origin_movement && (
                                        <StandardModal.Field
                                            label="Movimento Origem"
                                            value={`#${selected.origin_movement.id} (${selected.origin_movement.effective_date || '—'})`}
                                        />
                                    )}
                                </div>
                            </StandardModal.Section>
                        )}

                        <StandardModal.Section title="Prazos e SLA">
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                                <StandardModal.InfoCard label="SLA Previsto" value={`${selected.predicted_sla_days} dias`} />
                                <StandardModal.InfoCard
                                    label="Data Prevista"
                                    value={selected.delivery_forecast ? new Date(selected.delivery_forecast).toLocaleDateString('pt-BR') : '—'}
                                />
                                {selected.effective_sla_days !== null && selected.effective_sla_days !== undefined && (
                                    <StandardModal.InfoCard
                                        label="SLA Efetivo"
                                        value={`${selected.effective_sla_days} dias`}
                                        highlight
                                    />
                                )}
                                {selected.closing_date && (
                                    <StandardModal.InfoCard
                                        label="Fechamento"
                                        value={new Date(selected.closing_date).toLocaleDateString('pt-BR')}
                                    />
                                )}
                            </div>
                        </StandardModal.Section>

                        {(selected.interview_hr || selected.interview_leader) && (
                            <StandardModal.Section title="Entrevistas">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <p className="text-xs font-semibold text-gray-400 uppercase mb-1">RH</p>
                                        <p className="text-sm text-gray-900 whitespace-pre-wrap">
                                            {selected.interview_hr || '—'}
                                        </p>
                                        {selected.evaluators_hr && (
                                            <p className="text-xs text-gray-500 mt-1">Avaliadores: {selected.evaluators_hr}</p>
                                        )}
                                    </div>
                                    <div>
                                        <p className="text-xs font-semibold text-gray-400 uppercase mb-1">Líder</p>
                                        <p className="text-sm text-gray-900 whitespace-pre-wrap">
                                            {selected.interview_leader || '—'}
                                        </p>
                                        {selected.evaluators_leader && (
                                            <p className="text-xs text-gray-500 mt-1">Avaliadores: {selected.evaluators_leader}</p>
                                        )}
                                    </div>
                                </div>
                            </StandardModal.Section>
                        )}

                        {selected.hired_employee && (
                            <StandardModal.Section title="Contratação">
                                <StandardModal.Highlight>
                                    <p className="text-xs font-semibold text-indigo-500 uppercase">Pré-cadastro criado</p>
                                    <p className="text-xl font-bold text-indigo-700 mt-1">{selected.hired_employee.name}</p>
                                    <p className="text-sm text-indigo-600 mt-1">
                                        CPF: <span className="font-mono">{selected.hired_employee.cpf}</span>
                                        {selected.date_admission && (
                                            <> — Admissão: {new Date(selected.date_admission).toLocaleDateString('pt-BR')}</>
                                        )}
                                    </p>
                                    <p className="text-xs text-indigo-500 mt-2 italic">
                                        Complete os dados do funcionário em /employees/{selected.hired_employee.id}
                                    </p>
                                </StandardModal.Highlight>
                            </StandardModal.Section>
                        )}

                        {selected.status_history && selected.status_history.length > 0 && (
                            <StandardModal.Section title="Histórico de Status">
                                <StandardModal.Timeline
                                    items={selected.status_history.map((h) => ({
                                        id: h.id,
                                        title: `${h.from_status ? statusOptions[h.from_status] + ' → ' : ''}${statusOptions[h.to_status] || h.to_status}`,
                                        subtitle: `${h.changed_by_name || 'Sistema'} — ${new Date(h.created_at).toLocaleString('pt-BR')}`,
                                        notes: h.note,
                                    }))}
                                />
                            </StandardModal.Section>
                        )}

                        {selected.comments && (
                            <StandardModal.Section title="Observações">
                                <p className="text-sm text-gray-700 whitespace-pre-wrap">{selected.comments}</p>
                            </StandardModal.Section>
                        )}
                    </>
                )}
            </StandardModal>

            {/* Modal: Edit */}
            <StandardModal
                show={modals.edit}
                onClose={() => closeModal('edit')}
                title="Editar Vaga"
                headerColor="bg-amber-600"
                headerIcon={<BriefcaseIcon className="h-6 w-6" />}
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
                <StandardModal.Section title="Recrutamento">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Recrutador</label>
                            <select
                                value={editForm.recruiter_id || ''}
                                onChange={(e) => setEditForm({ ...editForm, recruiter_id: e.target.value })}
                                className="w-full rounded-md border-gray-300 shadow-sm"
                            >
                                <option value="">—</option>
                                {recruiters.map(r => (
                                    <option key={r.id} value={r.id}>{r.name}</option>
                                ))}
                            </select>
                            {editErrors.recruiter_id && <p className="mt-1 text-xs text-red-600">{editErrors.recruiter_id}</p>}
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Escala</label>
                            <select
                                value={editForm.work_schedule_id || ''}
                                onChange={(e) => setEditForm({ ...editForm, work_schedule_id: e.target.value })}
                                className="w-full rounded-md border-gray-300 shadow-sm"
                            >
                                <option value="">—</option>
                                {(selects.workSchedules || []).map(w => (
                                    <option key={w.id} value={w.id}>{w.name}</option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">SLA Previsto (dias)</label>
                            <input
                                type="number"
                                min="1"
                                max="365"
                                value={editForm.predicted_sla_days || ''}
                                onChange={(e) => setEditForm({ ...editForm, predicted_sla_days: e.target.value })}
                                className="w-full rounded-md border-gray-300 shadow-sm"
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Data Prevista</label>
                            <input
                                type="date"
                                value={editForm.delivery_forecast || ''}
                                onChange={(e) => setEditForm({ ...editForm, delivery_forecast: e.target.value })}
                                className="w-full rounded-md border-gray-300 shadow-sm"
                            />
                        </div>
                    </div>
                </StandardModal.Section>

                <StandardModal.Section title="Entrevistas">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Entrevista RH</label>
                            <textarea
                                rows={3}
                                value={editForm.interview_hr || ''}
                                onChange={(e) => setEditForm({ ...editForm, interview_hr: e.target.value })}
                                className="w-full rounded-md border-gray-300 shadow-sm"
                            />
                            <input
                                type="text"
                                placeholder="Avaliadores RH"
                                value={editForm.evaluators_hr || ''}
                                onChange={(e) => setEditForm({ ...editForm, evaluators_hr: e.target.value })}
                                className="mt-2 w-full rounded-md border-gray-300 shadow-sm text-sm"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Entrevista Líder</label>
                            <textarea
                                rows={3}
                                value={editForm.interview_leader || ''}
                                onChange={(e) => setEditForm({ ...editForm, interview_leader: e.target.value })}
                                className="w-full rounded-md border-gray-300 shadow-sm"
                            />
                            <input
                                type="text"
                                placeholder="Avaliadores Líder"
                                value={editForm.evaluators_leader || ''}
                                onChange={(e) => setEditForm({ ...editForm, evaluators_leader: e.target.value })}
                                className="mt-2 w-full rounded-md border-gray-300 shadow-sm text-sm"
                            />
                        </div>
                    </div>
                </StandardModal.Section>

                <StandardModal.Section title="Observações">
                    <textarea
                        rows={3}
                        value={editForm.comments || ''}
                        onChange={(e) => setEditForm({ ...editForm, comments: e.target.value })}
                        className="w-full rounded-md border-gray-300 shadow-sm"
                    />
                </StandardModal.Section>
            </StandardModal>

            {/* Modal: Transition */}
            <StandardModal
                show={modals.transition}
                onClose={() => closeModal('transition')}
                title="Alterar Status da Vaga"
                subtitle={selected ? `${selected.status_label} → ?` : ''}
                headerColor="bg-blue-600"
                headerIcon={<ArrowPathIcon className="h-6 w-6" />}
                maxWidth="3xl"
                onSubmit={handleTransitionSubmit}
                footer={
                    <StandardModal.Footer
                        onCancel={() => closeModal('transition')}
                        onSubmit="submit"
                        submitLabel={transitionForm.to_status === 'finalized' ? 'Finalizar e criar pré-cadastro' : 'Confirmar'}
                        submitColor="bg-blue-600 hover:bg-blue-700"
                        processing={transitionProcessing}
                        disabled={!transitionForm.to_status}
                    />
                }
            >
                {selected && (
                    <StandardModal.Section title="Transição">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Novo Status *</label>
                            <select
                                value={transitionForm.to_status}
                                onChange={(e) => setTransitionForm({ ...transitionForm, to_status: e.target.value })}
                                className="w-full rounded-md border-gray-300 shadow-sm"
                            >
                                <option value="">Selecione...</option>
                                {availableNextStatuses.map(s => (
                                    <option key={s.value} value={s.value}>{s.label}</option>
                                ))}
                            </select>
                            {transitionErrors.to_status && <p className="mt-1 text-xs text-red-600">{transitionErrors.to_status}</p>}
                        </div>

                        {(transitionForm.to_status === 'processing' || transitionForm.to_status === 'in_admission') && (
                            <div className="mt-4">
                                <label className="block text-sm font-medium text-gray-700 mb-1">Recrutador *</label>
                                <select
                                    value={transitionForm.recruiter_id}
                                    onChange={(e) => setTransitionForm({ ...transitionForm, recruiter_id: e.target.value })}
                                    className="w-full rounded-md border-gray-300 shadow-sm"
                                >
                                    <option value="">Selecione...</option>
                                    {recruiters.map(r => (
                                        <option key={r.id} value={r.id}>{r.name}</option>
                                    ))}
                                </select>
                                {transitionErrors.recruiter_id && <p className="mt-1 text-xs text-red-600">{transitionErrors.recruiter_id}</p>}
                            </div>
                        )}

                        {transitionForm.to_status === 'finalized' && (
                            <div className="mt-4 p-4 bg-indigo-50 border border-indigo-100 rounded-lg">
                                <p className="text-sm font-semibold text-indigo-900 mb-3">
                                    Pré-cadastro do Funcionário Contratado
                                </p>
                                <p className="text-xs text-indigo-700 mb-3">
                                    O funcionário será criado em estado <strong>Pendente</strong>. Complete os demais dados em /employees depois.
                                </p>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div className="md:col-span-2">
                                        <label className="block text-xs font-medium text-indigo-900 mb-1">Nome Completo *</label>
                                        <input
                                            type="text"
                                            value={transitionForm.name}
                                            onChange={(e) => setTransitionForm({ ...transitionForm, name: e.target.value })}
                                            className="w-full rounded-md border-gray-300 shadow-sm"
                                        />
                                        {transitionErrors.name && <p className="mt-1 text-xs text-red-600">{transitionErrors.name}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-xs font-medium text-indigo-900 mb-1">CPF *</label>
                                        <input
                                            type="text"
                                            value={transitionForm.cpf}
                                            onChange={(e) => setTransitionForm({ ...transitionForm, cpf: e.target.value })}
                                            placeholder="000.000.000-00"
                                            className="w-full rounded-md border-gray-300 shadow-sm font-mono"
                                        />
                                        {transitionErrors.cpf && <p className="mt-1 text-xs text-red-600">{transitionErrors.cpf}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-xs font-medium text-indigo-900 mb-1">Data de Admissão *</label>
                                        <input
                                            type="date"
                                            value={transitionForm.date_admission}
                                            onChange={(e) => setTransitionForm({ ...transitionForm, date_admission: e.target.value })}
                                            className="w-full rounded-md border-gray-300 shadow-sm"
                                        />
                                        {transitionErrors.date_admission && <p className="mt-1 text-xs text-red-600">{transitionErrors.date_admission}</p>}
                                    </div>
                                </div>
                                <div className="mt-3 text-xs text-indigo-600">
                                    Loja: <span className="font-mono font-bold">{selected.store_id}</span> — Cargo: <strong>{selected.position_name}</strong> (herdados da vaga)
                                </div>
                            </div>
                        )}

                        <div className="mt-4">
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Observação {transitionForm.to_status === 'cancelled' && <span className="text-red-500">*</span>}
                            </label>
                            <textarea
                                rows={3}
                                value={transitionForm.note}
                                onChange={(e) => setTransitionForm({ ...transitionForm, note: e.target.value })}
                                placeholder={transitionForm.to_status === 'cancelled' ? 'Motivo do cancelamento...' : 'Observação opcional...'}
                                className="w-full rounded-md border-gray-300 shadow-sm"
                            />
                            {transitionErrors.note && <p className="mt-1 text-xs text-red-600">{transitionErrors.note}</p>}
                        </div>
                    </StandardModal.Section>
                )}
            </StandardModal>

            {/* Modal: Delete (com motivo obrigatório) */}
            <StandardModal
                show={deleteTarget !== null}
                onClose={() => { setDeleteTarget(null); setDeleteReason(''); }}
                title="Excluir Vaga"
                subtitle={deleteTarget ? `#${deleteTarget.id} — ${deleteTarget.position_name}` : ''}
                headerColor="bg-red-600"
                headerIcon={<TrashIcon className="h-6 w-6" />}
                maxWidth="lg"
                onSubmit={handleDeleteConfirm}
                footer={
                    <StandardModal.Footer
                        onCancel={() => { setDeleteTarget(null); setDeleteReason(''); }}
                        onSubmit="submit"
                        submitLabel="Confirmar Exclusão"
                        submitColor="bg-red-600 hover:bg-red-700"
                        processing={deleteProcessing}
                        disabled={!deleteReason || deleteReason.trim().length < 3}
                    />
                }
            >
                {deleteTarget && (
                    <StandardModal.Section title="Dados da Vaga">
                        <div className="grid grid-cols-2 gap-3 mb-4">
                            <StandardModal.Field label="Loja" value={deleteTarget.store_id} mono />
                            <StandardModal.Field label="Tipo" value={deleteTarget.request_type_label} />
                            <StandardModal.Field label="Status" value={deleteTarget.status_label} />
                            <StandardModal.Field label="Cargo" value={deleteTarget.position_name} />
                        </div>
                        <div className="bg-amber-50 border border-amber-200 rounded-lg p-3 mb-4">
                            <p className="text-sm text-amber-900">
                                A vaga será marcada como excluída mas o histórico permanece auditável.
                            </p>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Motivo da Exclusão *</label>
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
