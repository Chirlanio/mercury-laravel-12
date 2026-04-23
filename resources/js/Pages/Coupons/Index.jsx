import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import {
    PlusIcon,
    XMarkIcon,
    TicketIcon,
    ClockIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    SparklesIcon,
    NoSymbolIcon,
    ArrowUturnLeftIcon,
    ArchiveBoxXMarkIcon,
    DocumentArrowDownIcon,
    DocumentArrowUpIcon,
    PrinterIcon,
    ChartBarIcon,
} from '@heroicons/react/24/outline';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import useCoupons from '@/Hooks/useCoupons';
import { maskCpf } from '@/Hooks/useMasks';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import DataTable from '@/Components/DataTable';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';

const COLOR_MAP = {
    success: 'success',
    warning: 'warning',
    info: 'info',
    danger: 'danger',
    purple: 'purple',
    gray: 'gray',
    teal: 'teal',
};

const emptyCreate = {
    type: '',
    cpf: '',
    store_code: '',
    employee_id: '',
    influencer_name: '',
    city: '',
    social_media_id: '',
    social_media_link: '',
    suggested_coupon: '',
    campaign_name: '',
    valid_from: '',
    valid_until: '',
    max_uses: '',
    notes: '',
    auto_request: true,
};

export default function Index({
    coupons,
    filters = {},
    statistics = {},
    statusOptions = {},
    statusColors = {},
    statusTransitions = {},
    typeOptions = {},
    isStoreScoped = false,
    scopedStoreCode = null,
    selects = {},
    can = {},
}) {
    const { hasPermission } = usePermissions();
    const canCreate = can.create ?? hasPermission(PERMISSIONS.CREATE_COUPONS);
    const canEdit = hasPermission(PERMISSIONS.EDIT_COUPONS);
    const canDelete = hasPermission(PERMISSIONS.DELETE_COUPONS);
    const canIssue = can.issue ?? hasPermission(PERMISSIONS.ISSUE_COUPON_CODE);
    const canCancel = canEdit || hasPermission(PERMISSIONS.MANAGE_COUPONS) || canDelete;
    const canExport = can.export ?? hasPermission(PERMISSIONS.EXPORT_COUPONS);
    const canImport = hasPermission(PERMISSIONS.IMPORT_COUPONS);

    const { modals, selected, openModal, closeModal } = useModalManager([
        'create', 'detail', 'edit', 'issue', 'cancel', 'import',
    ]);

    const lookup = useCoupons();

    // ------------------------------------------------------------------
    // Create form
    // ------------------------------------------------------------------
    const [createForm, setCreateForm] = useState(emptyCreate);
    const [createErrors, setCreateErrors] = useState({});
    const [createProcessing, setCreateProcessing] = useState(false);
    const createTypeInfo = useMemo(() => ({
        requiresStore: ['consultor', 'ms_indica'].includes(createForm.type),
        requiresInfluencerFields: createForm.type === 'influencer',
        isMsIndica: createForm.type === 'ms_indica',
    }), [createForm.type]);

    // Rede social selecionada — usado pra validação contextual do link
    const selectedSocialMedia = useMemo(() => {
        if (!createForm.social_media_id) return null;
        return (selects.socialMedia || []).find(
            (sm) => String(sm.id) === String(createForm.social_media_id)
        );
    }, [createForm.social_media_id, selects.socialMedia]);

    // Lookup de colaboradores por loja (modal create)
    useEffect(() => {
        if (createForm.store_code && createTypeInfo.requiresStore) {
            lookup.lookupEmployees(createForm.store_code);
        }
    }, [createForm.store_code, createTypeInfo.requiresStore]);

    // Lookup warning de cupons existentes (ao completar CPF)
    const handleCpfBlur = async () => {
        const digits = (createForm.cpf || '').replace(/\D/g, '');
        if (digits.length !== 11) {
            lookup.clearExisting();
            return;
        }
        await lookup.lookupExisting(createForm.cpf, { type: createForm.type || null });
    };

    // Ao selecionar colaborador, puxa CPF/detalhes e avisa se network não é administrativa
    const handleEmployeeSelect = async (employeeId) => {
        setCreateForm((f) => ({ ...f, employee_id: employeeId, cpf: '' }));
        if (!employeeId) return;
        const details = await lookup.fetchEmployeeDetails(employeeId);
        if (details) {
            setCreateForm((f) => ({
                ...f,
                cpf: details.cpf ? maskCpf(details.cpf.replace(/\D/g, '')) : '',
                store_code: details.store_code || f.store_code,
            }));
        }
    };

    // Sugestão de código ao digitar nome
    const handleSuggestCode = async () => {
        const name = createTypeInfo.requiresInfluencerFields
            ? createForm.influencer_name
            : lookup.employees.data.find((e) => e.id == createForm.employee_id)?.name;
        if (!name) return;
        const code = await lookup.suggestCode(name);
        if (code) {
            setCreateForm((f) => ({ ...f, suggested_coupon: code }));
        }
    };

    const submitCreate = (e) => {
        e.preventDefault();
        setCreateProcessing(true);
        setCreateErrors({});
        router.post(route('coupons.store'), createForm, {
            preserveScroll: true,
            onSuccess: () => {
                setCreateForm(emptyCreate);
                lookup.clearExisting();
                closeModal('create');
            },
            onError: (errors) => setCreateErrors(errors),
            onFinish: () => setCreateProcessing(false),
        });
    };

    const openCreate = () => {
        setCreateForm(emptyCreate);
        setCreateErrors({});
        lookup.clearExisting();
        openModal('create');
    };

    // ------------------------------------------------------------------
    // Detail (view)
    // ------------------------------------------------------------------
    const openDetail = async (row) => {
        const resp = await fetch(route('coupons.show', row.id), {
            headers: { Accept: 'application/json' },
        });
        const json = await resp.json();
        openModal('detail', json.coupon);
    };

    // ------------------------------------------------------------------
    // Edit
    // ------------------------------------------------------------------
    const [editForm, setEditForm] = useState({});
    const [editErrors, setEditErrors] = useState({});
    const [editProcessing, setEditProcessing] = useState(false);

    const openEdit = async (row) => {
        const resp = await fetch(route('coupons.show', row.id), {
            headers: { Accept: 'application/json' },
        });
        const json = await resp.json();
        const c = json.coupon;
        setEditForm({
            id: c.id,
            social_media_link: c.social_media_link || '',
            campaign_name: c.campaign_name || '',
            valid_from: c.valid_from || '',
            valid_until: c.valid_until || '',
            max_uses: c.max_uses ?? '',
            notes: c.notes || '',
        });
        setEditErrors({});
        openModal('edit', c);
    };

    const submitEdit = (e) => {
        e.preventDefault();
        if (!editForm.id) return;
        setEditProcessing(true);
        router.put(route('coupons.update', editForm.id), editForm, {
            preserveScroll: true,
            onSuccess: () => closeModal('edit'),
            onError: (errors) => setEditErrors(errors),
            onFinish: () => setEditProcessing(false),
        });
    };

    // ------------------------------------------------------------------
    // Issue code
    // ------------------------------------------------------------------
    const [issueForm, setIssueForm] = useState({ coupon_site: '', note: '' });
    const [issueErrors, setIssueErrors] = useState({});
    const [issueProcessing, setIssueProcessing] = useState(false);

    const openIssue = (row) => {
        setIssueForm({ coupon_site: row.suggested_coupon || '', note: '' });
        setIssueErrors({});
        openModal('issue', row);
    };

    const submitIssue = (e) => {
        e.preventDefault();
        if (!selected?.id) return;
        setIssueProcessing(true);
        router.post(route('coupons.transition', selected.id), {
            to_status: 'issued',
            coupon_site: issueForm.coupon_site,
            note: issueForm.note,
        }, {
            preserveScroll: true,
            onSuccess: () => closeModal('issue'),
            onError: (errors) => setIssueErrors(errors),
            onFinish: () => setIssueProcessing(false),
        });
    };

    // ------------------------------------------------------------------
    // Cancel
    // ------------------------------------------------------------------
    const [cancelReason, setCancelReason] = useState('');
    const [cancelErrors, setCancelErrors] = useState({});
    const [cancelProcessing, setCancelProcessing] = useState(false);

    const openCancel = (row) => {
        setCancelReason('');
        setCancelErrors({});
        openModal('cancel', row);
    };

    const submitCancel = (e) => {
        e.preventDefault();
        if (!selected?.id) return;
        setCancelProcessing(true);
        router.post(route('coupons.transition', selected.id), {
            to_status: 'cancelled',
            note: cancelReason,
        }, {
            preserveScroll: true,
            onSuccess: () => closeModal('cancel'),
            onError: (errors) => setCancelErrors(errors),
            onFinish: () => setCancelProcessing(false),
        });
    };

    // ------------------------------------------------------------------
    // Import (preview + confirm)
    // ------------------------------------------------------------------
    const [importFile, setImportFile] = useState(null);
    const [importPreview, setImportPreview] = useState(null);
    const [importProcessing, setImportProcessing] = useState(false);

    const openImport = () => {
        setImportFile(null);
        setImportPreview(null);
        openModal('import');
    };

    const handleImportPreview = async () => {
        if (!importFile) return;
        setImportProcessing(true);
        const fd = new FormData();
        fd.append('file', importFile);
        try {
            const resp = await fetch(route('coupons.import.preview'), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'Accept': 'application/json',
                },
                body: fd,
            });
            if (!resp.ok) throw new Error('Preview falhou');
            const data = await resp.json();
            setImportPreview(data);
        } catch (err) {
            setImportPreview({ error: err.message || 'Erro no preview' });
        } finally {
            setImportProcessing(false);
        }
    };

    const submitImport = () => {
        if (!importFile) return;
        setImportProcessing(true);
        const fd = new FormData();
        fd.append('file', importFile);
        router.post(route('coupons.import.store'), fd, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setImportFile(null);
                setImportPreview(null);
                closeModal('import');
            },
            onFinish: () => setImportProcessing(false),
        });
    };

    // ------------------------------------------------------------------
    // Delete (soft)
    // ------------------------------------------------------------------
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleteReason, setDeleteReason] = useState('');
    const [deleteProcessing, setDeleteProcessing] = useState(false);

    const submitDelete = () => {
        if (!deleteTarget?.id) return;
        setDeleteProcessing(true);
        router.delete(route('coupons.destroy', deleteTarget.id), {
            data: { deleted_reason: deleteReason },
            preserveScroll: true,
            onSuccess: () => {
                setDeleteTarget(null);
                setDeleteReason('');
            },
            onFinish: () => setDeleteProcessing(false),
        });
    };

    // ------------------------------------------------------------------
    // Filtros
    // ------------------------------------------------------------------
    const applyFilter = (key, value) => {
        const url = new URL(window.location);
        if (value) url.searchParams.set(key, value);
        else url.searchParams.delete(key);
        url.searchParams.delete('page');
        router.visit(url.toString(), { preserveState: true, preserveScroll: true });
    };

    const clearFilters = () => router.visit(route('coupons.index'));

    const hasActiveFilters = !!(
        filters.search || filters.type || filters.status ||
        filters.store_code || filters.date_from || filters.date_to ||
        filters.include_cancelled
    );

    // ------------------------------------------------------------------
    // Cards de estatísticas
    // ------------------------------------------------------------------
    const statsCards = useMemo(() => ([
        {
            label: 'Total de cupons',
            value: statistics.total || 0,
            format: 'number',
            icon: TicketIcon,
            color: 'gray',
        },
        {
            label: 'Aguardando emissão',
            value: statistics.requested || 0,
            format: 'number',
            icon: ClockIcon,
            color: 'amber',
            onClick: () => applyFilter('status', 'requested'),
            active: filters.status === 'requested',
        },
        {
            label: 'Emitidos',
            value: statistics.issued || 0,
            format: 'number',
            icon: SparklesIcon,
            color: 'blue',
            onClick: () => applyFilter('status', 'issued'),
            active: filters.status === 'issued',
        },
        {
            label: 'Ativos',
            value: statistics.active || 0,
            format: 'number',
            icon: CheckCircleIcon,
            color: 'green',
            onClick: () => applyFilter('status', 'active'),
            active: filters.status === 'active',
        },
        {
            label: 'Cancelados',
            value: statistics.cancelled || 0,
            format: 'number',
            icon: NoSymbolIcon,
            color: 'red',
        },
        {
            label: 'Expirados',
            value: statistics.expired || 0,
            format: 'number',
            icon: ArchiveBoxXMarkIcon,
            color: 'gray',
        },
    ]), [statistics, filters.status]);

    // ------------------------------------------------------------------
    // Colunas da tabela
    // ------------------------------------------------------------------
    const columns = [
        {
            key: 'type',
            label: 'Tipo',
            render: (row) => (
                <StatusBadge color={COLOR_MAP[row.type_color] || 'info'}>
                    {row.type_label}
                </StatusBadge>
            ),
        },
        {
            key: 'beneficiary_name',
            label: 'Beneficiário',
            render: (row) => (
                <div>
                    <div className="font-medium text-gray-900">{row.beneficiary_name || '—'}</div>
                    <div className="text-xs text-gray-500">{row.masked_cpf}</div>
                </div>
            ),
        },
        {
            key: 'store',
            label: 'Loja',
            render: (row) => row.store_code ? (
                <div>
                    <div className="font-medium">{row.store_code}</div>
                    <div className="text-xs text-gray-500 truncate max-w-[160px]">{row.store_name}</div>
                </div>
            ) : (
                <span className="text-gray-400">—</span>
            ),
        },
        {
            key: 'coupon',
            label: 'Cupom',
            render: (row) => row.coupon_site ? (
                <code className="px-2 py-0.5 bg-green-50 text-green-800 rounded font-mono text-sm">
                    {row.coupon_site}
                </code>
            ) : row.suggested_coupon ? (
                <code className="px-2 py-0.5 bg-yellow-50 text-yellow-800 rounded font-mono text-xs">
                    {row.suggested_coupon} <span className="text-[10px] font-sans">(sugerido)</span>
                </code>
            ) : (
                <span className="text-gray-400">—</span>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            render: (row) => (
                <StatusBadge color={COLOR_MAP[row.status_color] || 'gray'}>
                    {row.status_label}
                </StatusBadge>
            ),
        },
        {
            key: 'created_at',
            label: 'Criado em',
            render: (row) => (
                <div>
                    <div className="text-sm">{row.created_at || '—'}</div>
                    {row.created_by_name && (
                        <div className="text-xs text-gray-500">por {row.created_by_name}</div>
                    )}
                </div>
            ),
        },
        {
            key: 'actions',
            label: 'Ações',
            render: (row) => (
                <ActionButtons
                    onView={() => openDetail(row)}
                    onEdit={canEdit && ['draft', 'requested'].includes(row.status) ? () => openEdit(row) : null}
                    onDelete={canDelete && ['draft', 'requested'].includes(row.status) ? () => setDeleteTarget(row) : null}
                >
                    {canIssue && row.status === 'requested' && (
                        <ActionButtons.Custom
                            icon={SparklesIcon}
                            variant="info"
                            title="Emitir código"
                            onClick={() => openIssue(row)}
                        />
                    )}
                    {canCancel && !['cancelled', 'expired'].includes(row.status) && (
                        <ActionButtons.Custom
                            icon={NoSymbolIcon}
                            variant="danger"
                            title="Cancelar"
                            onClick={() => openCancel(row)}
                        />
                    )}
                </ActionButtons>
            ),
        },
    ];

    return (
        <>
            <Head title="Cupons" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    {/* Header — mobile: stack vertical; sm+: linha única */}
                    <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-center">
                        <div>
                            <h1 className="text-3xl font-bold text-gray-900">Cupons</h1>
                            <p className="mt-1 text-sm text-gray-600">
                                Solicitação e emissão de cupons de desconto para Consultores, Influencers e MS Indica
                                {isStoreScoped && scopedStoreCode && (
                                    <span className="ml-2 text-xs font-medium text-indigo-600">
                                        (escopo: loja {scopedStoreCode})
                                    </span>
                                )}
                            </p>
                        </div>
                        <div className="flex gap-2 shrink-0">
                            <Link href={route('coupons.dashboard')}>
                                <Button variant="secondary" title="Dashboard" aria-label="Dashboard">
                                    <ChartBarIcon className="w-4 h-4 xl:mr-2" />
                                    <span className="hidden xl:inline">Dashboard</span>
                                </Button>
                            </Link>
                            {canImport && (
                                <Button variant="secondary" onClick={openImport} title="Importar" aria-label="Importar">
                                    <DocumentArrowUpIcon className="w-4 h-4 xl:mr-2" />
                                    <span className="hidden xl:inline">Importar</span>
                                </Button>
                            )}
                            {canExport && (
                                <a
                                    href={route('coupons.export', filters)}
                                    title="Exportar"
                                    aria-label="Exportar"
                                    className="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-gray-300 text-sm font-medium text-gray-700 rounded-md hover:bg-gray-50"
                                >
                                    <DocumentArrowDownIcon className="h-4 w-4" />
                                    <span className="hidden xl:inline">Exportar</span>
                                </a>
                            )}
                            {canCreate && (
                                <Button variant="primary" onClick={openCreate} title="Novo Cupom" aria-label="Novo Cupom">
                                    <PlusIcon className="w-4 h-4 xl:mr-2" />
                                    <span className="hidden xl:inline">Novo Cupom</span>
                                </Button>
                            )}
                        </div>
                    </div>

                    {/* Statistics — mobile: 2 cols; md: 3 cols; lg+: 6 cols (grid responsivo interno) */}
                    <StatisticsGrid cards={statsCards} cols={6} />

                    {/* Filtros — mobile: 1 col; md+: 5 cols com col-span-2 na busca */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                            <div className="md:col-span-2">
                                <InputLabel htmlFor="search" value="Buscar" />
                                <TextInput
                                    id="search"
                                    type="text"
                                    value={filters.search || ''}
                                    onChange={(e) => applyFilter('search', e.target.value)}
                                    placeholder="Nome, cupom, campanha..."
                                    className="mt-1 block w-full"
                                />
                            </div>
                            <div>
                                <InputLabel value="Tipo" />
                                <select
                                    value={filters.type || ''}
                                    onChange={(e) => applyFilter('type', e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">Todos</option>
                                    {Object.entries(typeOptions).map(([v, lbl]) => (
                                        <option key={v} value={v}>{lbl}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <InputLabel value="Status" />
                                <select
                                    value={filters.status || ''}
                                    onChange={(e) => applyFilter('status', e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">Ativos</option>
                                    {Object.entries(statusOptions).map(([v, lbl]) => (
                                        <option key={v} value={v}>{lbl}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="flex items-center gap-2 text-sm text-gray-700 h-[38px]">
                                    <input
                                        type="checkbox"
                                        checked={!!filters.include_cancelled}
                                        onChange={(e) => applyFilter('include_cancelled', e.target.checked ? '1' : '')}
                                        className="rounded border-gray-300"
                                    />
                                    <span>Incluir cancelados/expirados</span>
                                </label>
                            </div>
                        </div>
                        {hasActiveFilters && (
                            <div className="mt-3 flex justify-end">
                                <Button variant="outline" size="sm" icon={XMarkIcon} onClick={clearFilters}>
                                    Limpar filtros
                                </Button>
                            </div>
                        )}
                    </div>

                    {/* Tabela */}
                    <DataTable
                        data={coupons}
                        columns={columns}
                        searchable={false}
                        emptyMessage={
                            hasActiveFilters
                                ? 'Nenhum cupom encontrado com os filtros atuais.'
                                : 'Nenhum cupom cadastrado ainda.'
                        }
                    />
                </div>
            </div>

            {/* ============================================================== */}
            {/* Modal CREATE */}
            {/* ============================================================== */}
            <StandardModal
                show={modals.create}
                onClose={() => closeModal('create')}
                title="Novo Cupom"
                subtitle="Preencha os dados e escolha o tipo do cupom"
                headerColor="bg-indigo-600"
                headerIcon={<PlusIcon className="h-5 w-5" />}
                maxWidth="5xl"
                onSubmit={submitCreate}
                footer={(
                    <StandardModal.Footer
                        onCancel={() => closeModal('create')}
                        onSubmit="submit"
                        submitLabel="Solicitar cupom"
                        processing={createProcessing}
                    />
                )}
            >
                <StandardModal.Section title="Tipo de cupom">
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        {Object.entries(typeOptions).map(([v, lbl]) => (
                            <button
                                key={v}
                                type="button"
                                onClick={() => {
                                    // Ao trocar o tipo, reseta TODOS os campos type-específicos
                                    // (consultor/ms_indica usam store+employee; influencer usa city+social_media)
                                    // e limpa o banner de warning, que é contextual ao tipo.
                                    setCreateForm((f) => ({
                                        ...f,
                                        type: v,
                                        // Campos de Consultor/MsIndica
                                        store_code: '',
                                        employee_id: '',
                                        // Campos de Influencer
                                        influencer_name: '',
                                        city: '',
                                        social_media_id: '',
                                        social_media_link: '',
                                        // CPF também reseta — CPF pode ser puxado do Employee em
                                        // Consultor/MsIndica, enquanto Influencer digita manual
                                        cpf: '',
                                        // Código sugerido é derivado do nome do beneficiário
                                        // (via botão Sugerir / suggestCode) — perde contexto ao trocar tipo
                                        suggested_coupon: '',
                                    }));
                                    setCreateErrors({});
                                    lookup.clearExisting();
                                }}
                                className={`p-3 rounded-lg border-2 text-center transition ${
                                    createForm.type === v
                                        ? 'border-indigo-600 bg-indigo-50 text-indigo-900'
                                        : 'border-gray-200 bg-white hover:border-indigo-300'
                                }`}
                            >
                                <div className="font-medium">{lbl}</div>
                            </button>
                        ))}
                    </div>
                    <InputError message={createErrors.type} className="mt-2" />
                </StandardModal.Section>

                {createTypeInfo.requiresStore && (
                    <StandardModal.Section title={createTypeInfo.isMsIndica ? 'Colaborador (loja administrativa)' : 'Colaborador'}>
                        {createTypeInfo.isMsIndica && (
                            <div className="bg-teal-50 border border-teal-200 rounded p-3 text-sm text-teal-900 mb-3">
                                MS Indica é restrito a lojas administrativas (E-Commerce, Qualidade, CD, Escritório).
                            </div>
                        )}
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <InputLabel value="Loja" />
                                <select
                                    value={createForm.store_code}
                                    onChange={(e) => setCreateForm((f) => ({ ...f, store_code: e.target.value, employee_id: '' }))}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                >
                                    <option value="">Selecione...</option>
                                    {(selects.stores || [])
                                        .filter((s) => !createTypeInfo.isMsIndica || [6, 7].includes(Number(s.network_id)))
                                        .map((s) => (
                                            <option key={s.code} value={s.code}>{s.code} — {s.name}</option>
                                        ))}
                                </select>
                                <InputError message={createErrors.store_code} className="mt-1" />
                            </div>
                            <div>
                                <InputLabel value="Colaborador" />
                                <select
                                    value={createForm.employee_id}
                                    onChange={(e) => handleEmployeeSelect(e.target.value)}
                                    disabled={!createForm.store_code || lookup.employees.loading}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm disabled:bg-gray-100"
                                >
                                    <option value="">
                                        {lookup.employees.loading ? 'Carregando...' : 'Selecione a loja primeiro'}
                                    </option>
                                    {lookup.employees.data.map((e) => (
                                        <option key={e.id} value={e.id}>{e.name}</option>
                                    ))}
                                </select>
                                <InputError message={createErrors.employee_id} className="mt-1" />
                            </div>
                        </div>
                    </StandardModal.Section>
                )}

                {createTypeInfo.requiresInfluencerFields && (
                    <StandardModal.Section title="Dados do influencer">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div className="sm:col-span-2">
                                <InputLabel value="Nome do influencer" />
                                <TextInput
                                    value={createForm.influencer_name}
                                    onChange={(e) => setCreateForm((f) => ({ ...f, influencer_name: e.target.value }))}
                                    className="mt-1 block w-full"
                                    placeholder="Ex: Maria Silva"
                                />
                                <InputError message={createErrors.influencer_name} className="mt-1" />
                            </div>
                            <div>
                                <InputLabel value="Cidade" />
                                <TextInput
                                    value={createForm.city}
                                    onChange={(e) => setCreateForm((f) => ({ ...f, city: e.target.value }))}
                                    className="mt-1 block w-full"
                                    placeholder="Fortaleza"
                                />
                                <InputError message={createErrors.city} className="mt-1" />
                            </div>
                            <div>
                                <InputLabel value="Rede social" />
                                <select
                                    value={createForm.social_media_id}
                                    onChange={(e) => setCreateForm((f) => ({ ...f, social_media_id: e.target.value }))}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">Selecione...</option>
                                    {(selects.socialMedia || []).map((sm) => (
                                        <option key={sm.id} value={sm.id}>{sm.name}</option>
                                    ))}
                                </select>
                                <InputError message={createErrors.social_media_id} className="mt-1" />
                            </div>
                            <div className="sm:col-span-2">
                                <InputLabel value={
                                    selectedSocialMedia?.link_type === 'username'
                                        ? `Perfil no ${selectedSocialMedia.name} (opcional)`
                                        : selectedSocialMedia
                                            ? `Link do ${selectedSocialMedia.name} (opcional)`
                                            : 'Link do perfil (opcional)'
                                } />
                                <TextInput
                                    value={createForm.social_media_link}
                                    onChange={(e) => setCreateForm((f) => ({ ...f, social_media_link: e.target.value }))}
                                    className="mt-1 block w-full"
                                    placeholder={selectedSocialMedia?.link_placeholder || 'https://...'}
                                    disabled={!createForm.social_media_id}
                                />
                                <InputError message={createErrors.social_media_link} className="mt-1" />
                                {selectedSocialMedia && (
                                    <p className="mt-1 text-xs text-gray-500">
                                        {selectedSocialMedia.link_type === 'username'
                                            ? 'Aceita @usuário ou URL completa do perfil.'
                                            : 'Deve ser uma URL completa (começando com https://).'}
                                    </p>
                                )}
                            </div>
                        </div>
                    </StandardModal.Section>
                )}

                {createForm.type && (
                    <StandardModal.Section title="Identificação">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <InputLabel value="CPF" />
                                <TextInput
                                    value={createForm.cpf}
                                    onChange={(e) => setCreateForm((f) => ({ ...f, cpf: maskCpf(e.target.value) }))}
                                    onBlur={handleCpfBlur}
                                    className="mt-1 block w-full"
                                    placeholder="000.000.000-00"
                                />
                                <InputError message={createErrors.cpf} className="mt-1" />
                            </div>
                            <div>
                                <InputLabel value="Cupom sugerido" />
                                <div className="flex gap-2 mt-1">
                                    <TextInput
                                        value={createForm.suggested_coupon}
                                        onChange={(e) => setCreateForm((f) => ({ ...f, suggested_coupon: e.target.value.toUpperCase() }))}
                                        className="block w-full"
                                        placeholder="MARIA26"
                                    />
                                    <Button type="button" variant="secondary" size="sm" onClick={handleSuggestCode} icon={SparklesIcon}>
                                        Sugerir
                                    </Button>
                                </div>
                                <InputError message={createErrors.suggested_coupon} className="mt-1" />
                            </div>
                        </div>

                        {/* Banner warning — cupons existentes do CPF */}
                        {lookup.existing.data.length > 0 && (
                            <div className="mt-3 bg-amber-50 border border-amber-200 rounded p-3">
                                <div className="flex items-start gap-2">
                                    <ExclamationTriangleIcon className="h-5 w-5 text-amber-600 flex-shrink-0 mt-0.5" />
                                    <div className="text-sm text-amber-900 flex-1">
                                        <div className="font-medium mb-1">
                                            Este CPF já possui {lookup.existing.data.length} cupom{lookup.existing.data.length > 1 ? 's' : ''} ativo{lookup.existing.data.length > 1 ? 's' : ''}:
                                        </div>
                                        <ul className="list-disc ml-4 space-y-0.5">
                                            {lookup.existing.data.slice(0, 3).map((c) => (
                                                <li key={c.id}>
                                                    <strong>{c.type_label}</strong>
                                                    {c.store_code && ` · ${c.store_code} ${c.store_name}`}
                                                    {c.coupon_site && ` · código: ${c.coupon_site}`}
                                                    <span className="text-amber-700"> ({c.status_label})</span>
                                                </li>
                                            ))}
                                        </ul>
                                        <div className="text-xs mt-1 text-amber-800">
                                            Cadastrar novo é permitido caso o colaborador tenha trocado de loja ou rede.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}
                    </StandardModal.Section>
                )}

                {createForm.type && (
                    <StandardModal.Section title="Validade e campanha (opcional)">
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <InputLabel value="Campanha" />
                                <TextInput
                                    value={createForm.campaign_name}
                                    onChange={(e) => setCreateForm((f) => ({ ...f, campaign_name: e.target.value }))}
                                    className="mt-1 block w-full"
                                    placeholder="Black Friday 2026"
                                />
                                <InputError message={createErrors.campaign_name} className="mt-1" />
                            </div>
                            <div>
                                <InputLabel value="Válido de" />
                                <TextInput
                                    type="date"
                                    value={createForm.valid_from}
                                    onChange={(e) => setCreateForm((f) => ({ ...f, valid_from: e.target.value }))}
                                    className="mt-1 block w-full"
                                />
                            </div>
                            <div>
                                <InputLabel value="Válido até" />
                                <TextInput
                                    type="date"
                                    value={createForm.valid_until}
                                    onChange={(e) => setCreateForm((f) => ({ ...f, valid_until: e.target.value }))}
                                    className="mt-1 block w-full"
                                />
                                <InputError message={createErrors.valid_until} className="mt-1" />
                            </div>
                            <div className="sm:col-span-3">
                                <InputLabel value="Observações" />
                                <textarea
                                    value={createForm.notes}
                                    onChange={(e) => setCreateForm((f) => ({ ...f, notes: e.target.value }))}
                                    rows={2}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                />
                            </div>
                            <div className="sm:col-span-3">
                                <label className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        checked={createForm.auto_request}
                                        onChange={(e) => setCreateForm((f) => ({ ...f, auto_request: e.target.checked }))}
                                        className="rounded border-gray-300"
                                    />
                                    <span>Enviar ao e-commerce imediatamente (se desmarcado, fica como rascunho)</span>
                                </label>
                            </div>
                        </div>
                    </StandardModal.Section>
                )}
            </StandardModal>

            {/* ============================================================== */}
            {/* Modal DETAIL (view) */}
            {/* ============================================================== */}
            <StandardModal
                show={modals.detail}
                onClose={() => closeModal('detail')}
                title="Detalhes do cupom"
                subtitle={selected ? `${selected.type_label} · ${selected.beneficiary_name}` : ''}
                headerColor="bg-gray-700"
                maxWidth="5xl"
                headerActions={selected && canExport ? (
                    <a
                        href={route('coupons.pdf', selected.id)}
                        className="inline-flex items-center gap-1 px-3 py-1 text-xs bg-white/10 hover:bg-white/20 text-white rounded"
                    >
                        <PrinterIcon className="h-3.5 w-3.5" />
                        PDF
                    </a>
                ) : null}
            >
                {selected && (
                    <>
                        <StandardModal.Section title="Resumo">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <StandardModal.Field label="Tipo" value={selected.type_label} />
                                <div>
                                    <p className="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Status</p>
                                    <div className="mt-0.5">
                                        <StatusBadge color={COLOR_MAP[selected.status_color] || 'gray'}>
                                            {selected.status_label}
                                        </StatusBadge>
                                    </div>
                                </div>
                                <StandardModal.Field label="Beneficiário" value={selected.beneficiary_name} />
                                <StandardModal.Field label="CPF" value={selected.masked_cpf} mono />
                                {selected.store_code && (
                                    <StandardModal.Field label="Loja" value={`${selected.store_code} — ${selected.store_name}`} />
                                )}
                                {selected.city && (
                                    <StandardModal.Field label="Cidade" value={selected.city} />
                                )}
                                {selected.social_media_name && (
                                    <StandardModal.Field label="Rede social" value={selected.social_media_name} />
                                )}
                                {selected.social_media_link && (
                                    <div>
                                        <p className="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Link</p>
                                        <a href={selected.social_media_link} target="_blank" rel="noreferrer" className="text-sm text-indigo-600 hover:underline break-all">
                                            {selected.social_media_link}
                                        </a>
                                    </div>
                                )}
                            </div>
                        </StandardModal.Section>

                        <StandardModal.Section title="Código">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <StandardModal.Field label="Sugerido" value={selected.suggested_coupon || '—'} mono />
                                <div>
                                    <p className="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Emitido</p>
                                    {selected.coupon_site ? (
                                        <code className="px-2 py-0.5 bg-green-50 text-green-800 rounded font-mono text-sm">
                                            {selected.coupon_site}
                                        </code>
                                    ) : (
                                        <p className="text-sm mt-0.5 text-gray-900">—</p>
                                    )}
                                </div>
                                {selected.campaign_name && (
                                    <StandardModal.Field label="Campanha" value={selected.campaign_name} />
                                )}
                                {selected.valid_until && (
                                    <StandardModal.Field
                                        label="Validade"
                                        value={`${selected.valid_from_display || '—'} → ${selected.valid_until_display || '—'}`}
                                    />
                                )}
                                {selected.usage_count > 0 && (
                                    <StandardModal.Field
                                        label="Usos"
                                        value={`${selected.usage_count}${selected.max_uses ? ' / ' + selected.max_uses : ''}`}
                                    />
                                )}
                            </div>
                        </StandardModal.Section>

                        {selected.notes && (
                            <StandardModal.Section title="Observações">
                                <p className="text-sm text-gray-700 whitespace-pre-line">{selected.notes}</p>
                            </StandardModal.Section>
                        )}

                        {selected.cancelled_reason && (
                            <StandardModal.Section title="Cancelamento">
                                <p className="text-sm text-red-800">{selected.cancelled_reason}</p>
                            </StandardModal.Section>
                        )}

                        {selected.history && selected.history.length > 0 && (
                            <StandardModal.Section title="Histórico">
                                <StandardModal.Timeline
                                    items={selected.history.map((h) => ({
                                        id: h.id,
                                        title: h.to_status_label,
                                        subtitle: [h.created_at, h.changed_by_name ? `por ${h.changed_by_name}` : null].filter(Boolean).join(' · '),
                                        notes: h.note,
                                        dotColor: ({
                                            success: 'bg-green-500',
                                            warning: 'bg-amber-500',
                                            info: 'bg-blue-500',
                                            danger: 'bg-red-500',
                                            purple: 'bg-purple-500',
                                            gray: 'bg-gray-400',
                                            teal: 'bg-teal-500',
                                        })[h.to_status_color] || 'bg-indigo-500',
                                    }))}
                                />
                            </StandardModal.Section>
                        )}
                    </>
                )}
            </StandardModal>

            {/* ============================================================== */}
            {/* Modal EDIT */}
            {/* ============================================================== */}
            <StandardModal
                show={modals.edit}
                onClose={() => closeModal('edit')}
                title="Editar cupom"
                subtitle={selected ? `${selected.type_label} · ${selected.beneficiary_name}` : ''}
                headerColor="bg-indigo-600"
                maxWidth="xl"
                onSubmit={submitEdit}
                footer={(
                    <StandardModal.Footer
                        onCancel={() => closeModal('edit')}
                        onSubmit="submit"
                        submitLabel="Salvar alterações"
                        processing={editProcessing}
                    />
                )}
            >
                <StandardModal.Section title="Dados editáveis">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <InputLabel value="Campanha" />
                            <TextInput
                                value={editForm.campaign_name || ''}
                                onChange={(e) => setEditForm((f) => ({ ...f, campaign_name: e.target.value }))}
                                className="mt-1 block w-full"
                            />
                            <InputError message={editErrors.campaign_name} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Link da rede social" />
                            <TextInput
                                value={editForm.social_media_link || ''}
                                onChange={(e) => setEditForm((f) => ({ ...f, social_media_link: e.target.value }))}
                                className="mt-1 block w-full"
                            />
                            <InputError message={editErrors.social_media_link} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Válido de" />
                            <TextInput
                                type="date"
                                value={editForm.valid_from || ''}
                                onChange={(e) => setEditForm((f) => ({ ...f, valid_from: e.target.value }))}
                                className="mt-1 block w-full"
                            />
                        </div>
                        <div>
                            <InputLabel value="Válido até" />
                            <TextInput
                                type="date"
                                value={editForm.valid_until || ''}
                                onChange={(e) => setEditForm((f) => ({ ...f, valid_until: e.target.value }))}
                                className="mt-1 block w-full"
                            />
                            <InputError message={editErrors.valid_until} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Máximo de usos (opcional)" />
                            <TextInput
                                type="number"
                                min="1"
                                value={editForm.max_uses || ''}
                                onChange={(e) => setEditForm((f) => ({ ...f, max_uses: e.target.value }))}
                                className="mt-1 block w-full"
                            />
                            <InputError message={editErrors.max_uses} className="mt-1" />
                        </div>
                        <div className="sm:col-span-2">
                            <InputLabel value="Observações" />
                            <textarea
                                value={editForm.notes || ''}
                                onChange={(e) => setEditForm((f) => ({ ...f, notes: e.target.value }))}
                                rows={3}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            />
                        </div>
                    </div>
                </StandardModal.Section>
            </StandardModal>

            {/* ============================================================== */}
            {/* Modal ISSUE CODE */}
            {/* ============================================================== */}
            <StandardModal
                show={modals.issue}
                onClose={() => closeModal('issue')}
                title="Emitir código do cupom"
                subtitle={selected ? `${selected.type_label} · ${selected.beneficiary_name}` : ''}
                headerColor="bg-blue-600"
                headerIcon={<SparklesIcon className="h-5 w-5" />}
                maxWidth="lg"
                onSubmit={submitIssue}
                footer={(
                    <StandardModal.Footer
                        onCancel={() => closeModal('issue')}
                        onSubmit="submit"
                        submitLabel="Emitir código"
                        processing={issueProcessing}
                    />
                )}
            >
                <StandardModal.Section title="Código emitido na plataforma">
                    <p className="text-sm text-gray-600 mb-3">
                        Preencha abaixo o código de cupom gerado na plataforma de e-commerce (Shopify/Tray/etc).
                        Ao confirmar, o cupom passa para <strong>Emitido</strong> e o solicitante é notificado.
                    </p>
                    <div className="space-y-3">
                        <div>
                            <InputLabel value="Código do cupom" />
                            <TextInput
                                value={issueForm.coupon_site}
                                onChange={(e) => setIssueForm((f) => ({ ...f, coupon_site: e.target.value.toUpperCase() }))}
                                className="mt-1 block w-full font-mono"
                                placeholder="MARIA26"
                                autoFocus
                            />
                            <InputError message={issueErrors.coupon_site} className="mt-1" />
                            {selected?.suggested_coupon && (
                                <p className="text-xs text-gray-500 mt-1">Sugerido pelo solicitante: <code>{selected.suggested_coupon}</code></p>
                            )}
                        </div>
                        <div>
                            <InputLabel value="Observação (opcional)" />
                            <textarea
                                value={issueForm.note}
                                onChange={(e) => setIssueForm((f) => ({ ...f, note: e.target.value }))}
                                rows={2}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                placeholder="Ex: código ativo até 31/12/2026"
                            />
                            <InputError message={issueErrors.note} className="mt-1" />
                        </div>
                    </div>
                </StandardModal.Section>
            </StandardModal>

            {/* ============================================================== */}
            {/* Modal CANCEL */}
            {/* ============================================================== */}
            <StandardModal
                show={modals.cancel}
                onClose={() => closeModal('cancel')}
                title="Cancelar cupom"
                subtitle={selected ? `${selected.type_label} · ${selected.beneficiary_name}` : ''}
                headerColor="bg-red-600"
                headerIcon={<NoSymbolIcon className="h-5 w-5" />}
                maxWidth="lg"
                onSubmit={submitCancel}
                footer={(
                    <StandardModal.Footer
                        onCancel={() => closeModal('cancel')}
                        onSubmit="submit"
                        submitLabel="Confirmar cancelamento"
                        submitColor="bg-red-600 hover:bg-red-700"
                        processing={cancelProcessing}
                    />
                )}
            >
                <StandardModal.Section title="Motivo do cancelamento">
                    <p className="text-sm text-gray-600 mb-3">
                        Esta ação não pode ser desfeita. O cupom ficará marcado como <strong>Cancelado</strong> e não pode ser reutilizado.
                    </p>
                    <InputLabel value="Motivo (obrigatório)" />
                    <textarea
                        value={cancelReason}
                        onChange={(e) => setCancelReason(e.target.value)}
                        rows={3}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 text-sm"
                        placeholder="Ex: colaborador desligado, cliente desistiu..."
                        required
                    />
                    <InputError message={cancelErrors.note} className="mt-1" />
                </StandardModal.Section>
            </StandardModal>

            {/* ============================================================== */}
            {/* Modal DELETE (soft delete com motivo obrigatório) */}
            {/* ============================================================== */}
            <StandardModal
                show={deleteTarget !== null}
                onClose={() => { setDeleteTarget(null); setDeleteReason(''); }}
                title="Excluir cupom"
                subtitle={deleteTarget ? `${deleteTarget.type_label} · ${deleteTarget.beneficiary_name}` : ''}
                headerColor="bg-red-600"
                headerIcon={<ArchiveBoxXMarkIcon className="h-5 w-5" />}
                maxWidth="md"
                onSubmit={(e) => { e.preventDefault(); submitDelete(); }}
                footer={(
                    <StandardModal.Footer
                        onCancel={() => { setDeleteTarget(null); setDeleteReason(''); }}
                        onSubmit="submit"
                        submitLabel="Confirmar exclusão"
                        submitColor="bg-red-600 hover:bg-red-700"
                        processing={deleteProcessing}
                    />
                )}
            >
                <StandardModal.Section title="Confirmação">
                    <div className="bg-amber-50 border border-amber-200 rounded p-3 text-sm text-amber-900 mb-3">
                        <strong>Atenção:</strong> cupons já emitidos não podem ser excluídos — use Cancelar.
                        A exclusão é um soft delete (preservado para auditoria).
                    </div>
                    <InputLabel value="Motivo da exclusão (obrigatório)" />
                    <textarea
                        value={deleteReason}
                        onChange={(e) => setDeleteReason(e.target.value)}
                        rows={3}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 text-sm"
                        placeholder="Ex: cadastro duplicado, dados incorretos..."
                        required
                    />
                </StandardModal.Section>
            </StandardModal>

            {/* ============================================================== */}
            {/* Modal IMPORT (preview + confirm) */}
            {/* ============================================================== */}
            <StandardModal
                show={modals.import}
                onClose={() => { closeModal('import'); setImportFile(null); setImportPreview(null); }}
                title="Importar cupons"
                subtitle="Upload de planilha XLSX/CSV com cupons históricos"
                headerColor="bg-emerald-600"
                headerIcon={<DocumentArrowUpIcon className="h-5 w-5" />}
                maxWidth="3xl"
            >
                <StandardModal.Section title="Arquivo">
                    <div className="bg-blue-50 border border-blue-200 rounded p-3 text-xs text-blue-900 mb-3 space-y-1">
                        <div><strong>Colunas aceitas:</strong> tipo, cpf, loja, colaborador (ou matricula), cupom_sugerido, cupom_emitido, campanha, valido_de, valido_ate, max_uses, status, obs.</div>
                        <div><strong>Influencer:</strong> usar colunas <code>influencer</code>, <code>cidade</code>, <code>rede_social</code>, <code>link</code> (no lugar de loja/colaborador).</div>
                        <div><strong>Upsert:</strong> existente por (cpf + tipo + loja) é atualizado — reimports são idempotentes.</div>
                    </div>

                    <input
                        type="file"
                        accept=".xlsx,.xls,.csv"
                        onChange={(e) => { setImportFile(e.target.files?.[0] || null); setImportPreview(null); }}
                        className="block w-full text-sm text-gray-700 file:mr-3 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100"
                    />

                    {importFile && !importPreview && (
                        <div className="mt-3 flex justify-end">
                            <Button
                                variant="secondary"
                                onClick={handleImportPreview}
                                loading={importProcessing}
                            >
                                Pré-visualizar
                            </Button>
                        </div>
                    )}
                </StandardModal.Section>

                {importPreview && importPreview.error && (
                    <StandardModal.Section title="Erro">
                        <div className="bg-red-50 border border-red-200 rounded p-3 text-sm text-red-800">
                            {importPreview.error}
                        </div>
                    </StandardModal.Section>
                )}

                {importPreview && !importPreview.error && (
                    <>
                        <StandardModal.Section title="Resumo">
                            <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                <div className="bg-green-50 border border-green-200 rounded p-3">
                                    <div className="text-xs text-green-700 uppercase">Válidas</div>
                                    <div className="text-2xl font-bold text-green-800">{importPreview.valid_count}</div>
                                </div>
                                <div className="bg-red-50 border border-red-200 rounded p-3">
                                    <div className="text-xs text-red-700 uppercase">Inválidas</div>
                                    <div className="text-2xl font-bold text-red-800">{importPreview.invalid_count}</div>
                                </div>
                                <div className="bg-gray-50 border border-gray-200 rounded p-3 col-span-2 sm:col-span-1">
                                    <div className="text-xs text-gray-600 uppercase">Total</div>
                                    <div className="text-2xl font-bold text-gray-800">
                                        {importPreview.valid_count + importPreview.invalid_count}
                                    </div>
                                </div>
                            </div>
                        </StandardModal.Section>

                        {importPreview.errors && importPreview.errors.length > 0 && (
                            <StandardModal.Section title="Primeiros erros">
                                <div className="max-h-48 overflow-y-auto text-sm space-y-1">
                                    {importPreview.errors.slice(0, 20).map((err, i) => (
                                        <div key={i} className="bg-red-50 border border-red-100 rounded px-2 py-1 text-red-800">
                                            <strong>Linha {err.row}:</strong> {err.messages.join(' · ')}
                                        </div>
                                    ))}
                                    {importPreview.errors.length > 20 && (
                                        <div className="text-xs text-gray-500 pt-2">
                                            ... e mais {importPreview.errors.length - 20} erro(s).
                                        </div>
                                    )}
                                </div>
                            </StandardModal.Section>
                        )}

                        <StandardModal.Section>
                            <div className="flex justify-end gap-2">
                                <Button
                                    variant="secondary"
                                    onClick={() => { setImportFile(null); setImportPreview(null); }}
                                >
                                    Cancelar
                                </Button>
                                <Button
                                    variant="primary"
                                    onClick={submitImport}
                                    loading={importProcessing}
                                    disabled={importPreview.valid_count === 0}
                                >
                                    Confirmar importação ({importPreview.valid_count})
                                </Button>
                            </div>
                        </StandardModal.Section>
                    </>
                )}
            </StandardModal>
        </>
    );
}
