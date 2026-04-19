import { Head, router } from '@inertiajs/react';
import { useState, useMemo, useEffect } from 'react';
import axios from 'axios';
import {
    PlusIcon,
    FolderOpenIcon,
    CheckCircleIcon,
    XCircleIcon,
    LinkIcon,
    NoSymbolIcon,
    DocumentIcon,
    FolderIcon,
    DocumentArrowDownIcon,
    DocumentArrowUpIcon,
    ExclamationTriangleIcon,
    ListBulletIcon,
    Squares2X2Icon,
    ChevronRightIcon,
    ChevronDownIcon,
} from '@heroicons/react/24/outline';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import DataTable from '@/Components/DataTable';
import StandardModal from '@/Components/StandardModal';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import StatusBadge from '@/Components/Shared/StatusBadge';

export default function Index({ managementClasses, filters = {}, statistics = {}, selects = {} }) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission(PERMISSIONS.CREATE_MANAGEMENT_CLASSES);
    const canEdit = hasPermission(PERMISSIONS.EDIT_MANAGEMENT_CLASSES);
    const canDelete = hasPermission(PERMISSIONS.DELETE_MANAGEMENT_CLASSES);
    const canImport = hasPermission(PERMISSIONS.IMPORT_MANAGEMENT_CLASSES);
    const canExport = hasPermission(PERMISSIONS.EXPORT_MANAGEMENT_CLASSES);

    const { modals, selected, openModal, closeModal } = useModalManager([
        'create', 'detail', 'edit', 'import',
    ]);

    const [viewMode, setViewMode] = useState('list');
    const [tree, setTree] = useState(null);
    const [loadingTree, setLoadingTree] = useState(false);

    useEffect(() => {
        if (viewMode === 'tree' && !tree) loadTree();
    }, [viewMode]);

    const loadTree = async () => {
        setLoadingTree(true);
        try {
            const { data } = await axios.get(route('management-classes.tree'));
            setTree(data.tree);
        } catch (_) {
            setTree([]);
        } finally {
            setLoadingTree(false);
        }
    };

    const emptyForm = {
        code: '',
        name: '',
        description: '',
        parent_id: '',
        accounting_class_id: '',
        cost_center_id: '',
        accepts_entries: true,
        sort_order: 0,
        is_active: true,
    };

    const [createForm, setCreateForm] = useState(emptyForm);
    const [createErrors, setCreateErrors] = useState({});
    const [createProcessing, setCreateProcessing] = useState(false);

    const handleCreateSubmit = (e) => {
        e.preventDefault();
        setCreateProcessing(true);
        setCreateErrors({});

        router.post(route('management-classes.store'), createForm, {
            preserveScroll: true,
            onSuccess: () => {
                setCreateForm(emptyForm);
                closeModal('create');
                setTree(null);
            },
            onError: (errors) => setCreateErrors(errors),
            onFinish: () => setCreateProcessing(false),
        });
    };

    const [editForm, setEditForm] = useState({});
    const [editErrors, setEditErrors] = useState({});
    const [editProcessing, setEditProcessing] = useState(false);

    const handleEditOpen = (c) => {
        setEditForm({
            id: c.id,
            code: c.code,
            name: c.name,
            description: c.description || '',
            parent_id: c.parent_id || '',
            accounting_class_id: c.accounting_class_id || '',
            cost_center_id: c.cost_center_id || '',
            accepts_entries: c.accepts_entries,
            sort_order: c.sort_order || 0,
            is_active: c.is_active,
        });
        setEditErrors({});
        openModal('edit', c);
    };

    const handleEditSubmit = (e) => {
        e.preventDefault();
        setEditProcessing(true);
        setEditErrors({});

        const { id, ...payload } = editForm;
        router.put(route('management-classes.update', id), payload, {
            preserveScroll: true,
            onSuccess: () => {
                closeModal('edit');
                setTree(null);
            },
            onError: (errors) => setEditErrors(errors),
            onFinish: () => setEditProcessing(false),
        });
    };

    const [detail, setDetail] = useState(null);
    const [loadingDetail, setLoadingDetail] = useState(false);

    const handleDetailOpen = async (c) => {
        openModal('detail', c);
        setLoadingDetail(true);
        try {
            const { data } = await axios.get(route('management-classes.show', c.id));
            setDetail(data.managementClass);
        } catch (_) {
            setDetail(null);
        } finally {
            setLoadingDetail(false);
        }
    };

    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleteReason, setDeleteReason] = useState('');
    const [deleteProcessing, setDeleteProcessing] = useState(false);

    const handleDelete = () => {
        if (!deleteTarget) return;
        setDeleteProcessing(true);
        router.delete(route('management-classes.destroy', deleteTarget.id), {
            data: { deleted_reason: deleteReason },
            preserveScroll: true,
            onSuccess: () => {
                setDeleteTarget(null);
                setDeleteReason('');
                setTree(null);
            },
            onFinish: () => setDeleteProcessing(false),
        });
    };

    const [importFile, setImportFile] = useState(null);
    const [importPreview, setImportPreview] = useState(null);
    const [importProcessing, setImportProcessing] = useState(false);
    const [importError, setImportError] = useState(null);

    const handlePreview = async () => {
        if (!importFile) return;
        setImportProcessing(true);
        setImportError(null);
        try {
            const formData = new FormData();
            formData.append('file', importFile);
            const { data } = await axios.post(route('management-classes.import.preview'), formData);
            setImportPreview(data);
        } catch (e) {
            setImportError(e.response?.data?.message || 'Falha ao processar planilha.');
            setImportPreview(null);
        } finally {
            setImportProcessing(false);
        }
    };

    const handleImportConfirm = () => {
        if (!importFile) return;
        setImportProcessing(true);
        const formData = new FormData();
        formData.append('file', importFile);
        router.post(route('management-classes.import.store'), formData, {
            preserveScroll: true,
            onSuccess: () => {
                setImportFile(null);
                setImportPreview(null);
                setTree(null);
                closeModal('import');
            },
            onError: (errors) => setImportError(errors.file || 'Erro ao importar.'),
            onFinish: () => setImportProcessing(false),
        });
    };

    const applyFilter = (key, value) => {
        router.get(route('management-classes.index'), {
            ...filters,
            [key]: value || undefined,
        }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const statisticsCards = useMemo(() => [
        {
            label: 'Total',
            value: statistics.total || 0,
            format: 'number',
            icon: FolderOpenIcon,
            color: 'indigo',
        },
        {
            label: 'Ativas',
            value: statistics.active || 0,
            format: 'number',
            icon: CheckCircleIcon,
            color: 'green',
        },
        {
            label: 'Inativas',
            value: statistics.inactive || 0,
            format: 'number',
            icon: XCircleIcon,
            color: 'red',
        },
        {
            label: 'Com vínculo contábil',
            value: statistics.linked_to_accounting || 0,
            format: 'number',
            icon: LinkIcon,
            color: 'green',
            active: filters.accounting_link === 'linked',
            onClick: () => applyFilter('accounting_link', filters.accounting_link === 'linked' ? '' : 'linked'),
        },
        {
            label: 'Sem vínculo contábil',
            value: statistics.unlinked_from_accounting || 0,
            format: 'number',
            icon: NoSymbolIcon,
            color: 'orange',
            active: filters.accounting_link === 'unlinked',
            onClick: () => applyFilter('accounting_link', filters.accounting_link === 'unlinked' ? '' : 'unlinked'),
        },
    ], [statistics, filters]);

    const columns = [
        { key: 'code', label: 'Código', sortable: true, className: 'font-mono' },
        {
            key: 'name',
            label: 'Nome',
            render: (c) => (
                <div>
                    <span className={c.accepts_entries ? '' : 'font-semibold'}>
                        {c.name}
                    </span>
                    {!c.accepts_entries && (
                        <span className="ml-2 text-xs text-gray-500">(grupo)</span>
                    )}
                </div>
            ),
        },
        {
            key: 'accounting_class_label',
            label: 'Conta contábil',
            render: (c) => c.accounting_class_label ? (
                <span className="text-xs text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded">
                    {c.accounting_class_label}
                </span>
            ) : (
                <span className="text-xs text-amber-600" title="Sem vínculo contábil">
                    não vinculada
                </span>
            ),
        },
        {
            key: 'cost_center_label',
            label: 'CC default',
            render: (c) => c.cost_center_label
                ? <span className="text-xs text-gray-600">{c.cost_center_label}</span>
                : <span className="text-xs text-gray-400">—</span>,
        },
        {
            key: 'is_active',
            label: 'Status',
            render: (c) => (
                <StatusBadge
                    status={c.is_active ? 'success' : 'gray'}
                    text={c.is_active ? 'Ativa' : 'Inativa'}
                />
            ),
        },
        {
            key: 'actions',
            label: 'Ações',
            render: (c) => (
                <ActionButtons
                    onView={() => handleDetailOpen(c)}
                    onEdit={canEdit ? () => handleEditOpen(c) : undefined}
                    onDelete={canDelete ? () => setDeleteTarget(c) : undefined}
                />
            ),
        },
    ];

    return (
        <AuthenticatedLayout>
            <Head title="Plano Gerencial" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="mb-6 flex justify-between items-center">
                        <div>
                            <h1 className="text-3xl font-bold text-gray-900">Plano de Contas Gerencial</h1>
                            <p className="mt-1 text-sm text-gray-600">
                                Visão interna operacional, complementar ao plano contábil.
                            </p>
                        </div>
                        <div className="flex gap-2">
                            {canExport && (
                                <Button
                                    variant="secondary"
                                    icon={DocumentArrowDownIcon}
                                    onClick={() => window.location.href = route('management-classes.export', filters)}
                                >
                                    Exportar
                                </Button>
                            )}
                            {canImport && (
                                <Button
                                    variant="secondary"
                                    icon={DocumentArrowUpIcon}
                                    onClick={() => {
                                        setImportFile(null);
                                        setImportPreview(null);
                                        setImportError(null);
                                        openModal('import');
                                    }}
                                >
                                    Importar
                                </Button>
                            )}
                            {canCreate && (
                                <Button
                                    variant="primary"
                                    icon={PlusIcon}
                                    onClick={() => {
                                        setCreateForm(emptyForm);
                                        setCreateErrors({});
                                        openModal('create');
                                    }}
                                >
                                    Nova Conta
                                </Button>
                            )}
                        </div>
                    </div>

                    <StatisticsGrid cards={statisticsCards} cols={5} />

                    <div className="mt-6 mb-4 flex items-center justify-between bg-white shadow-sm rounded-lg p-4">
                        <div className="flex items-center gap-2">
                            <span className="text-sm text-gray-700 font-medium">Visualização:</span>
                            <button
                                onClick={() => setViewMode('list')}
                                className={`px-3 py-1.5 rounded text-sm font-medium flex items-center gap-1 ${viewMode === 'list' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700'}`}
                            >
                                <ListBulletIcon className="w-4 h-4" /> Lista
                            </button>
                            <button
                                onClick={() => setViewMode('tree')}
                                className={`px-3 py-1.5 rounded text-sm font-medium flex items-center gap-1 ${viewMode === 'tree' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700'}`}
                            >
                                <Squares2X2Icon className="w-4 h-4" /> Árvore
                            </button>
                        </div>

                        {viewMode === 'list' && (
                            <div className="flex items-center gap-2 flex-wrap">
                                <input
                                    type="text"
                                    value={filters.search || ''}
                                    onChange={(e) => applyFilter('search', e.target.value)}
                                    placeholder="Buscar..."
                                    className="rounded-md border-gray-300 shadow-sm text-sm w-56"
                                />
                                <select
                                    value={filters.accepts_entries ?? ''}
                                    onChange={(e) => applyFilter('accepts_entries', e.target.value)}
                                    className="rounded-md border-gray-300 shadow-sm text-sm"
                                >
                                    <option value="">Todos tipos</option>
                                    <option value="1">Folhas (analíticas)</option>
                                    <option value="0">Grupos (sintéticas)</option>
                                </select>
                                <select
                                    value={filters.is_active ?? ''}
                                    onChange={(e) => applyFilter('is_active', e.target.value)}
                                    className="rounded-md border-gray-300 shadow-sm text-sm"
                                >
                                    <option value="">Todos status</option>
                                    <option value="1">Ativas</option>
                                    <option value="0">Inativas</option>
                                </select>
                            </div>
                        )}
                    </div>

                    {viewMode === 'list' ? (
                        <DataTable
                            columns={columns}
                            data={managementClasses}
                            emptyMessage="Nenhuma conta gerencial cadastrada. Comece cadastrando manualmente ou importando uma planilha."
                        />
                    ) : (
                        <div className="bg-white shadow-sm rounded-lg p-4">
                            {loadingTree ? (
                                <p className="text-center text-sm text-gray-500 py-8">Carregando árvore...</p>
                            ) : tree && tree.length > 0 ? (
                                <TreeView
                                    nodes={tree}
                                    onView={handleDetailOpen}
                                    onEdit={canEdit ? handleEditOpen : null}
                                    onDelete={canDelete ? (c) => setDeleteTarget(c) : null}
                                />
                            ) : (
                                <p className="text-center text-sm text-gray-500 py-8">Árvore vazia.</p>
                            )}
                        </div>
                    )}
                </div>
            </div>

            {/* -------- Create Modal -------- */}
            <StandardModal
                show={modals.create}
                onClose={() => closeModal('create')}
                title="Nova Conta Gerencial"
                headerColor="bg-indigo-600"
                headerIcon={PlusIcon}
                maxWidth="3xl"
                onSubmit={handleCreateSubmit}
                footer={
                    <StandardModal.Footer
                        onCancel={() => closeModal('create')}
                        onSubmit="submit"
                        submitLabel="Cadastrar"
                        processing={createProcessing}
                    />
                }
            >
                <ManagementClassFormFields
                    form={createForm}
                    errors={createErrors}
                    onChange={(patch) => setCreateForm({ ...createForm, ...patch })}
                    parents={selects.parents || []}
                    accountingClasses={selects.accountingClasses || []}
                    costCenters={selects.costCenters || []}
                />
            </StandardModal>

            {/* -------- Edit Modal -------- */}
            <StandardModal
                show={modals.edit}
                onClose={() => closeModal('edit')}
                title={`Editar ${selected?.code || ''}`}
                headerColor="bg-amber-600"
                maxWidth="3xl"
                onSubmit={handleEditSubmit}
                footer={
                    <StandardModal.Footer
                        onCancel={() => closeModal('edit')}
                        onSubmit="submit"
                        submitLabel="Salvar"
                        processing={editProcessing}
                    />
                }
            >
                <ManagementClassFormFields
                    form={editForm}
                    errors={editErrors}
                    onChange={(patch) => setEditForm({ ...editForm, ...patch })}
                    parents={(selects.parents || []).filter((p) => p.id !== editForm.id)}
                    accountingClasses={selects.accountingClasses || []}
                    costCenters={selects.costCenters || []}
                />
            </StandardModal>

            {/* -------- Detail Modal -------- */}
            <StandardModal
                show={modals.detail}
                onClose={() => {
                    closeModal('detail');
                    setDetail(null);
                }}
                title={detail?.name || selected?.name || 'Detalhes'}
                subtitle={detail?.code ? `Código ${detail.code}` : selected?.code}
                headerColor="bg-gray-700"
                maxWidth="3xl"
                loading={loadingDetail}
                headerBadges={detail ? [
                    {
                        text: detail.has_accounting_link ? 'Vinculada' : 'Sem vínculo contábil',
                        className: detail.has_accounting_link
                            ? 'bg-white/20 text-white'
                            : 'bg-amber-500/40 text-white',
                    },
                    {
                        text: detail.is_active ? 'Ativa' : 'Inativa',
                        className: detail.is_active ? 'bg-white/20 text-white' : 'bg-white/10 text-white/70',
                    },
                ] : []}
            >
                {detail && (
                    <>
                        <StandardModal.Section title="Informações">
                            <div className="grid grid-cols-2 gap-4">
                                <StandardModal.Field label="Código" value={detail.code} />
                                <StandardModal.Field label="Nome" value={detail.name} />
                                <StandardModal.Field label="Pai" value={detail.parent_label || '—'} />
                                <StandardModal.Field
                                    label="Tipo"
                                    value={detail.accepts_entries ? 'Folha (analítica)' : 'Grupo (sintética)'}
                                />
                                <StandardModal.Field
                                    label="Conta contábil vinculada"
                                    value={detail.accounting_class_label || 'Não vinculada'}
                                />
                                <StandardModal.Field
                                    label="CC default"
                                    value={detail.cost_center_label || '—'}
                                />
                            </div>
                            {detail.description && (
                                <div className="mt-4">
                                    <p className="text-xs font-medium text-gray-500 uppercase mb-1">
                                        Descrição
                                    </p>
                                    <p className="text-sm text-gray-800 whitespace-pre-wrap">
                                        {detail.description}
                                    </p>
                                </div>
                            )}
                            {!detail.has_accounting_link && detail.accepts_entries && (
                                <div className="mt-4 bg-amber-50 border border-amber-200 rounded p-3 text-sm text-amber-800 flex items-start gap-2">
                                    <ExclamationTriangleIcon className="w-5 h-5 shrink-0 mt-0.5" />
                                    <div>
                                        <p className="font-medium">Sem vínculo contábil</p>
                                        <p className="text-xs mt-0.5">
                                            Esta conta analítica não está mapeada para nenhuma
                                            conta contábil. Lançamentos nela não entrarão no DRE
                                            automaticamente. Considere vincular a uma folha
                                            analítica do plano contábil.
                                        </p>
                                    </div>
                                </div>
                            )}
                        </StandardModal.Section>

                        {detail.children && detail.children.length > 0 && (
                            <StandardModal.Section title={`Contas filhas (${detail.children.length})`}>
                                <ul className="divide-y divide-gray-200 bg-white rounded border border-gray-200">
                                    {detail.children.map((c) => (
                                        <li key={c.id} className="p-3 flex justify-between items-center text-sm">
                                            <div>
                                                <span className="font-mono text-gray-700">{c.code}</span>
                                                <span className="text-gray-500 ml-2">{c.name}</span>
                                                {!c.accepts_entries && (
                                                    <span className="ml-2 text-xs text-gray-400">(grupo)</span>
                                                )}
                                            </div>
                                            <StatusBadge
                                                status={c.is_active ? 'success' : 'gray'}
                                                text={c.is_active ? 'Ativa' : 'Inativa'}
                                            />
                                        </li>
                                    ))}
                                </ul>
                            </StandardModal.Section>
                        )}
                    </>
                )}
            </StandardModal>

            {/* -------- Delete Modal -------- */}
            <StandardModal
                show={deleteTarget !== null}
                onClose={() => { setDeleteTarget(null); setDeleteReason(''); }}
                title="Excluir conta gerencial"
                subtitle={deleteTarget ? `${deleteTarget.code} · ${deleteTarget.name}` : ''}
                headerColor="bg-red-600"
                headerIcon={ExclamationTriangleIcon}
                maxWidth="md"
                footer={
                    <StandardModal.Footer
                        onCancel={() => { setDeleteTarget(null); setDeleteReason(''); }}
                        onSubmit={handleDelete}
                        submitLabel="Excluir"
                        submitColor="bg-red-600 hover:bg-red-700"
                        processing={deleteProcessing}
                        disabled={deleteReason.trim().length < 3}
                    />
                }
            >
                <div className="space-y-3">
                    <div className="flex items-start gap-2 bg-red-50 border border-red-100 rounded-lg p-3">
                        <ExclamationTriangleIcon className="h-5 w-5 text-red-500 shrink-0 mt-0.5" />
                        <p className="text-sm text-red-700">
                            Soft delete. Contas com filhas ativas não podem ser excluídas.
                        </p>
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-700 mb-1">
                            Motivo da exclusão *
                        </label>
                        <input
                            type="text"
                            value={deleteReason}
                            onChange={(e) => setDeleteReason(e.target.value)}
                            placeholder="Mínimo 3 caracteres"
                            className="w-full rounded-md border-gray-300 shadow-sm text-sm"
                        />
                    </div>
                </div>
            </StandardModal>

            {/* -------- Import Modal -------- */}
            <StandardModal
                show={modals.import}
                onClose={() => closeModal('import')}
                title="Importar Plano Gerencial"
                headerColor="bg-emerald-600"
                headerIcon={DocumentArrowUpIcon}
                maxWidth="4xl"
                footer={(
                    <div className="flex justify-between items-center w-full">
                        <p className="text-xs text-gray-500">
                            Cabeçalhos: <code>codigo</code>, <code>nome</code>,{' '}
                            <code>codigo_pai</code>, <code>codigo_contabil</code>,{' '}
                            <code>codigo_centro_custo</code>, <code>aceita_lancamento</code>
                        </p>
                        <div className="flex gap-2">
                            <Button variant="secondary" onClick={() => closeModal('import')}>
                                Cancelar
                            </Button>
                            {!importPreview ? (
                                <Button
                                    variant="primary"
                                    onClick={handlePreview}
                                    disabled={!importFile || importProcessing}
                                    loading={importProcessing}
                                >
                                    Analisar planilha
                                </Button>
                            ) : (
                                <Button
                                    variant="success"
                                    onClick={handleImportConfirm}
                                    disabled={importPreview.valid_count === 0 || importProcessing}
                                    loading={importProcessing}
                                >
                                    Importar {importPreview.valid_count} linhas
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            >
                <StandardModal.Section title="Arquivo">
                    <input
                        type="file"
                        accept=".xlsx,.xls,.csv"
                        onChange={(e) => {
                            setImportFile(e.target.files?.[0] || null);
                            setImportPreview(null);
                            setImportError(null);
                        }}
                        className="block w-full text-sm text-gray-900 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-medium file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100"
                    />
                    {importError && (
                        <p className="mt-2 text-sm text-red-600">
                            <ExclamationTriangleIcon className="inline w-4 h-4 mr-1" />
                            {importError}
                        </p>
                    )}
                </StandardModal.Section>

                {importPreview && (
                    <>
                        <StandardModal.Section title="Resumo">
                            <div className="grid grid-cols-2 gap-4">
                                <div className="bg-green-50 border border-green-200 rounded p-3">
                                    <p className="text-xs text-green-700 uppercase font-semibold">Válidas</p>
                                    <p className="text-2xl font-bold text-green-800">{importPreview.valid_count}</p>
                                </div>
                                <div className="bg-red-50 border border-red-200 rounded p-3">
                                    <p className="text-xs text-red-700 uppercase font-semibold">Com erro</p>
                                    <p className="text-2xl font-bold text-red-800">{importPreview.invalid_count}</p>
                                </div>
                            </div>
                        </StandardModal.Section>

                        {importPreview.rows.length > 0 && (
                            <StandardModal.Section title={`Prévia (${importPreview.rows.length} primeiras)`}>
                                <div className="overflow-x-auto">
                                    <table className="min-w-full text-sm">
                                        <thead className="bg-gray-100">
                                            <tr>
                                                <th className="px-2 py-1 text-left font-medium">Código</th>
                                                <th className="px-2 py-1 text-left font-medium">Nome</th>
                                                <th className="px-2 py-1 text-left font-medium">Pai</th>
                                                <th className="px-2 py-1 text-left font-medium">Contábil</th>
                                                <th className="px-2 py-1 text-left font-medium">CC</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-200">
                                            {importPreview.rows.map((r, i) => (
                                                <tr key={i}>
                                                    <td className="px-2 py-1 font-mono">{r.code}</td>
                                                    <td className="px-2 py-1">{r.name}</td>
                                                    <td className="px-2 py-1 text-xs text-gray-500">{r.parent_code || '—'}</td>
                                                    <td className="px-2 py-1 text-xs text-gray-500">{r.accounting_class_code || '—'}</td>
                                                    <td className="px-2 py-1 text-xs text-gray-500">{r.cost_center_code || '—'}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </StandardModal.Section>
                        )}

                        {importPreview.errors.length > 0 && (
                            <StandardModal.Section title={`Erros (${importPreview.errors.length})`}>
                                <ul className="space-y-1 max-h-48 overflow-y-auto text-sm">
                                    {importPreview.errors.map((e, i) => (
                                        <li key={i} className="bg-red-50 border border-red-200 rounded px-3 py-1.5">
                                            <span className="font-mono text-xs text-red-800">Linha {e.row}:</span>
                                            <span className="text-red-700 ml-2">{e.messages.join('; ')}</span>
                                        </li>
                                    ))}
                                </ul>
                            </StandardModal.Section>
                        )}
                    </>
                )}
            </StandardModal>
        </AuthenticatedLayout>
    );
}

// ------------------------------------------------------------------
// Tree view sub-component
// ------------------------------------------------------------------
function TreeView({ nodes, onView, onEdit, onDelete, depth = 0 }) {
    return (
        <ul className={depth === 0 ? 'space-y-1' : 'space-y-0.5 ml-5 mt-0.5 border-l border-gray-200 pl-3'}>
            {nodes.map((node) => (
                <TreeNode key={node.id} node={node} depth={depth} onView={onView} onEdit={onEdit} onDelete={onDelete} />
            ))}
        </ul>
    );
}

function TreeNode({ node, depth, onView, onEdit, onDelete }) {
    const [expanded, setExpanded] = useState(depth < 1);
    const hasChildren = node.children && node.children.length > 0;

    return (
        <li>
            <div className="flex items-center gap-2 py-1 px-2 rounded hover:bg-gray-50 group">
                {hasChildren ? (
                    <button onClick={() => setExpanded(!expanded)} className="text-gray-500 hover:text-gray-700">
                        {expanded ? <ChevronDownIcon className="w-4 h-4" /> : <ChevronRightIcon className="w-4 h-4" />}
                    </button>
                ) : (
                    <span className="w-4" />
                )}

                <span className={`font-mono text-xs ${node.is_active ? 'text-gray-700' : 'text-gray-400 line-through'}`}>
                    {node.code}
                </span>

                <span className={`text-sm flex-1 ${node.accepts_entries ? '' : 'font-semibold'} ${node.is_active ? 'text-gray-800' : 'text-gray-400'}`}>
                    {node.name}
                    {!node.accepts_entries && <span className="ml-2 text-xs text-gray-400">(grupo)</span>}
                </span>

                {node.accounting_class_label ? (
                    <span className="text-xs text-indigo-600" title={`Contábil: ${node.accounting_class_label}`}>
                        <LinkIcon className="w-3.5 h-3.5" />
                    </span>
                ) : node.accepts_entries && (
                    <span className="text-xs text-amber-500" title="Sem vínculo contábil">
                        <NoSymbolIcon className="w-3.5 h-3.5" />
                    </span>
                )}

                <div className="opacity-0 group-hover:opacity-100 transition-opacity">
                    <ActionButtons
                        onView={() => onView(node)}
                        onEdit={onEdit ? () => onEdit(node) : undefined}
                        onDelete={onDelete ? () => onDelete(node) : undefined}
                    />
                </div>
            </div>

            {hasChildren && expanded && (
                <TreeView
                    nodes={node.children}
                    depth={depth + 1}
                    onView={onView}
                    onEdit={onEdit}
                    onDelete={onDelete}
                />
            )}
        </li>
    );
}

// ------------------------------------------------------------------
// Form fields
// ------------------------------------------------------------------
function ManagementClassFormFields({ form, errors, onChange, parents, accountingClasses, costCenters }) {
    return (
        <>
            <StandardModal.Section title="Dados básicos">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Código *</label>
                        <input
                            type="text"
                            value={form.code || ''}
                            onChange={(e) => onChange({ code: e.target.value })}
                            maxLength={30}
                            className="w-full rounded-md border-gray-300 shadow-sm font-mono"
                        />
                        {errors.code && <p className="mt-1 text-xs text-red-600">{errors.code}</p>}
                    </div>
                    <div className="md:col-span-2">
                        <label className="block text-sm font-medium text-gray-700 mb-1">Nome *</label>
                        <input
                            type="text"
                            value={form.name || ''}
                            onChange={(e) => onChange({ name: e.target.value })}
                            className="w-full rounded-md border-gray-300 shadow-sm"
                        />
                        {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
                    </div>
                </div>

                <div className="mt-4">
                    <label className="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                    <textarea
                        value={form.description || ''}
                        onChange={(e) => onChange({ description: e.target.value })}
                        rows={2}
                        className="w-full rounded-md border-gray-300 shadow-sm"
                    />
                </div>
            </StandardModal.Section>

            <StandardModal.Section title="Vínculos opcionais">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Conta contábil vinculada
                        </label>
                        <select
                            value={form.accounting_class_id || ''}
                            onChange={(e) => onChange({ accounting_class_id: e.target.value })}
                            className="w-full rounded-md border-gray-300 shadow-sm"
                        >
                            <option value="">— Não vinculada —</option>
                            {accountingClasses.map((a) => (
                                <option key={a.id} value={a.id}>
                                    {a.code} · {a.name}
                                </option>
                            ))}
                        </select>
                        <p className="mt-1 text-xs text-gray-500">
                            Apenas contas folhas (analíticas) aparecem aqui. Vínculo opcional no MVP;
                            obrigatório quando o tenant ativar DRE.
                        </p>
                        {errors.accounting_class_id && (
                            <p className="mt-1 text-xs text-red-600">{errors.accounting_class_id}</p>
                        )}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Centro de custo default
                        </label>
                        <select
                            value={form.cost_center_id || ''}
                            onChange={(e) => onChange({ cost_center_id: e.target.value })}
                            className="w-full rounded-md border-gray-300 shadow-sm"
                        >
                            <option value="">— Sem CC default —</option>
                            {costCenters.map((cc) => (
                                <option key={cc.id} value={cc.id}>
                                    {cc.code} · {cc.name}
                                </option>
                            ))}
                        </select>
                        <p className="mt-1 text-xs text-gray-500">
                            Usado quando uma linha do orçamento vem só com a conta gerencial —
                            o sistema usa este CC como default.
                        </p>
                        {errors.cost_center_id && (
                            <p className="mt-1 text-xs text-red-600">{errors.cost_center_id}</p>
                        )}
                    </div>
                </div>
            </StandardModal.Section>

            <StandardModal.Section title="Hierarquia e tipo">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Conta pai (apenas grupos sintéticos)
                        </label>
                        <select
                            value={form.parent_id || ''}
                            onChange={(e) => onChange({ parent_id: e.target.value })}
                            className="w-full rounded-md border-gray-300 shadow-sm"
                        >
                            <option value="">— Raiz (sem pai) —</option>
                            {parents.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.code} · {p.name}
                                </option>
                            ))}
                        </select>
                        {errors.parent_id && (
                            <p className="mt-1 text-xs text-red-600">{errors.parent_id}</p>
                        )}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Ordem</label>
                        <input
                            type="number"
                            min="0"
                            value={form.sort_order || 0}
                            onChange={(e) => onChange({ sort_order: parseInt(e.target.value) || 0 })}
                            className="w-full rounded-md border-gray-300 shadow-sm"
                        />
                    </div>
                </div>

                <div className="mt-4 bg-gray-50 rounded p-3">
                    <label className="flex items-start gap-2 text-sm cursor-pointer">
                        <input
                            type="checkbox"
                            checked={!!form.accepts_entries}
                            onChange={(e) => onChange({ accepts_entries: e.target.checked })}
                            className="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <span>
                            <span className="font-medium">Aceita lançamentos (folha analítica)</span>
                            <span className="block text-xs text-gray-600 mt-0.5">
                                Desmarque para grupos sintéticos (agregadores).
                            </span>
                        </span>
                    </label>
                </div>
                {errors.accepts_entries && (
                    <p className="mt-1 text-xs text-red-600">{errors.accepts_entries}</p>
                )}

                <div className="mt-4 flex items-center">
                    <input
                        id="is_active"
                        type="checkbox"
                        checked={!!form.is_active}
                        onChange={(e) => onChange({ is_active: e.target.checked })}
                        className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <label htmlFor="is_active" className="ml-2 text-sm text-gray-700">Ativa</label>
                </div>
            </StandardModal.Section>
        </>
    );
}
