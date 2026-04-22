import { Head, router } from '@inertiajs/react';
import { useState, useMemo, useEffect } from 'react';
import axios from 'axios';
import {
    PlusIcon,
    DocumentChartBarIcon,
    CheckCircleIcon,
    XCircleIcon,
    FolderIcon,
    DocumentIcon,
    DocumentArrowDownIcon,
    DocumentArrowUpIcon,
    ExclamationTriangleIcon,
    ListBulletIcon,
    Squares2X2Icon,
    ChevronRightIcon,
    ChevronDownIcon,
} from '@heroicons/react/24/outline';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import DataTable from '@/Components/DataTable';
import StandardModal from '@/Components/StandardModal';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import StatusBadge from '@/Components/Shared/StatusBadge';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';

const DRE_GROUP_COLORS = {
    receita_bruta: 'bg-green-100 text-green-800 border-green-200',
    deducoes: 'bg-yellow-100 text-yellow-800 border-yellow-200',
    cmv: 'bg-orange-100 text-orange-800 border-orange-200',
    despesas_comerciais: 'bg-red-100 text-red-800 border-red-200',
    despesas_administrativas: 'bg-red-100 text-red-800 border-red-200',
    despesas_gerais: 'bg-red-100 text-red-800 border-red-200',
    outras_receitas_op: 'bg-green-100 text-green-800 border-green-200',
    outras_despesas_op: 'bg-red-100 text-red-800 border-red-200',
    receitas_financeiras: 'bg-green-100 text-green-800 border-green-200',
    despesas_financeiras: 'bg-red-100 text-red-800 border-red-200',
    impostos_sobre_lucro: 'bg-yellow-100 text-yellow-800 border-yellow-200',
};

export default function Index({ accountingClasses, filters = {}, statistics = {}, enums = {}, selects = {} }) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission(PERMISSIONS.CREATE_ACCOUNTING_CLASSES);
    const canEdit = hasPermission(PERMISSIONS.EDIT_ACCOUNTING_CLASSES);
    const canDelete = hasPermission(PERMISSIONS.DELETE_ACCOUNTING_CLASSES);
    const canImport = hasPermission(PERMISSIONS.IMPORT_ACCOUNTING_CLASSES);
    const canExport = hasPermission(PERMISSIONS.EXPORT_ACCOUNTING_CLASSES);

    const { modals, selected, openModal, closeModal } = useModalManager([
        'create', 'detail', 'edit', 'import',
    ]);

    const [viewMode, setViewMode] = useState('list'); // 'list' or 'tree'
    const [tree, setTree] = useState(null);
    const [loadingTree, setLoadingTree] = useState(false);

    useEffect(() => {
        if (viewMode === 'tree' && !tree) {
            loadTree();
        }
    }, [viewMode]);

    const loadTree = async () => {
        setLoadingTree(true);
        try {
            const { data } = await axios.get(route('accounting-classes.tree'));
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
        nature: 'debit',
        dre_group: 'despesas_administrativas',
        accepts_entries: true,
        sort_order: 0,
        is_active: true,
    };

    // ------------------------------------------------------------------
    // Create
    // ------------------------------------------------------------------
    const [createForm, setCreateForm] = useState(emptyForm);
    const [createErrors, setCreateErrors] = useState({});
    const [createProcessing, setCreateProcessing] = useState(false);

    const handleCreateSubmit = (e) => {
        e.preventDefault();
        setCreateProcessing(true);
        setCreateErrors({});

        router.post(route('accounting-classes.store'), createForm, {
            preserveScroll: true,
            onSuccess: () => {
                setCreateForm(emptyForm);
                closeModal('create');
                setTree(null); // força reload da árvore ao abrir novamente
            },
            onError: (errors) => setCreateErrors(errors),
            onFinish: () => setCreateProcessing(false),
        });
    };

    // ------------------------------------------------------------------
    // Edit
    // ------------------------------------------------------------------
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
            nature: c.nature,
            dre_group: c.dre_group,
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
        router.put(route('accounting-classes.update', id), payload, {
            preserveScroll: true,
            onSuccess: () => {
                closeModal('edit');
                setTree(null);
            },
            onError: (errors) => setEditErrors(errors),
            onFinish: () => setEditProcessing(false),
        });
    };

    // ------------------------------------------------------------------
    // Detail
    // ------------------------------------------------------------------
    const [detail, setDetail] = useState(null);
    const [loadingDetail, setLoadingDetail] = useState(false);

    const handleDetailOpen = async (c) => {
        openModal('detail', c);
        setLoadingDetail(true);
        try {
            const { data } = await axios.get(route('accounting-classes.show', c.id));
            setDetail(data.accountingClass);
        } catch (_) {
            setDetail(null);
        } finally {
            setLoadingDetail(false);
        }
    };

    // ------------------------------------------------------------------
    // Delete
    // ------------------------------------------------------------------
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleteReason, setDeleteReason] = useState('');
    const [deleteProcessing, setDeleteProcessing] = useState(false);

    const handleDelete = () => {
        if (!deleteTarget) return;
        setDeleteProcessing(true);
        router.delete(route('accounting-classes.destroy', deleteTarget.id), {
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

    // ------------------------------------------------------------------
    // Import
    // ------------------------------------------------------------------
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
            const { data } = await axios.post(route('accounting-classes.import.preview'), formData);
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
        router.post(route('accounting-classes.import.store'), formData, {
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

    // ------------------------------------------------------------------
    // Filters
    // ------------------------------------------------------------------
    const applyFilter = (key, value) => {
        router.get(route('accounting-classes.index'), {
            ...filters,
            [key]: value || undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    // ------------------------------------------------------------------
    // Cards
    // ------------------------------------------------------------------
    const statisticsCards = useMemo(() => [
        {
            label: 'Total',
            value: statistics.total || 0,
            format: 'number',
            icon: DocumentChartBarIcon,
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
            label: 'Folhas (analíticas)',
            value: statistics.leaves || 0,
            format: 'number',
            icon: DocumentIcon,
            color: 'purple',
            active: filters.accepts_entries === '1',
            onClick: () => applyFilter('accepts_entries', filters.accepts_entries === '1' ? '' : '1'),
        },
        {
            label: 'Grupos (sintéticas)',
            value: statistics.synthetic_groups || 0,
            format: 'number',
            icon: FolderIcon,
            color: 'orange',
            active: filters.accepts_entries === '0',
            onClick: () => applyFilter('accepts_entries', filters.accepts_entries === '0' ? '' : '0'),
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
            key: 'dre_group',
            label: 'Grupo DRE',
            render: (c) => (
                <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border ${DRE_GROUP_COLORS[c.dre_group] || 'bg-gray-100 text-gray-800'}`}>
                    {c.dre_group_label}
                </span>
            ),
        },
        {
            key: 'nature',
            label: 'Natureza',
            render: (c) => (
                <div className="flex items-center gap-1">
                    <span className={`inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold ${c.nature === 'debit' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'}`}>
                        {c.nature_short}
                    </span>
                    {!c.follows_natural_nature && (
                        <span title="Divergente da natureza natural do grupo DRE">
                            <ExclamationTriangleIcon className="w-4 h-4 text-amber-500" />
                        </span>
                    )}
                </div>
            ),
        },
        {
            key: 'is_active',
            label: 'Status',
            render: (c) => (
                <StatusBadge variant={c.is_active ? 'success' : 'gray'}>
                    {c.is_active ? 'Ativa' : 'Inativa'}
                </StatusBadge>
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
        <>
            <Head title="Plano de Contas" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="mb-6 flex justify-between items-center">
                        <div>
                            <h1 className="text-3xl font-bold text-gray-900">Plano de Contas</h1>
                            <p className="mt-1 text-sm text-gray-600">
                                Estrutura contábil — base para orçamentos e DRE.
                            </p>
                        </div>
                        <div className="flex gap-2">
                            {canExport && (
                                <Button
                                    variant="secondary"
                                    icon={DocumentArrowDownIcon}
                                    onClick={() => window.location.href = route('accounting-classes.export', filters)}
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
                                <TextInput
                                    value={filters.search || ''}
                                    onChange={(e) => applyFilter('search', e.target.value)}
                                    placeholder="Buscar por código ou nome..."
                                    className="text-sm w-64"
                                />
                                <select
                                    value={filters.dre_group || ''}
                                    onChange={(e) => applyFilter('dre_group', e.target.value)}
                                    className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                >
                                    <option value="">Todos os grupos</option>
                                    {Object.entries(enums.dreGroups || {}).map(([k, v]) => (
                                        <option key={k} value={k}>{v}</option>
                                    ))}
                                </select>
                                <select
                                    value={filters.nature || ''}
                                    onChange={(e) => applyFilter('nature', e.target.value)}
                                    className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                >
                                    <option value="">Ambas naturezas</option>
                                    {Object.entries(enums.natures || {}).map(([k, v]) => (
                                        <option key={k} value={k}>{v}</option>
                                    ))}
                                </select>
                            </div>
                        )}
                    </div>

                    {viewMode === 'list' ? (
                        <DataTable
                            columns={columns}
                            data={accountingClasses}
                            emptyMessage="Nenhuma conta cadastrada."
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
                title="Nova Conta Contábil"
                headerColor="bg-indigo-600"
                headerIcon={<PlusIcon className="h-6 w-6" />}
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
                <AccountingClassFormFields
                    form={createForm}
                    errors={createErrors}
                    onChange={(patch) => setCreateForm({ ...createForm, ...patch })}
                    parents={selects.parents || []}
                    enums={enums}
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
                <AccountingClassFormFields
                    form={editForm}
                    errors={editErrors}
                    onChange={(patch) => setEditForm({ ...editForm, ...patch })}
                    parents={(selects.parents || []).filter((p) => p.id !== editForm.id)}
                    enums={enums}
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
                        text: detail.dre_group_label,
                        className: 'bg-white/20 text-white',
                    },
                    {
                        text: detail.nature_label,
                        className: 'bg-white/20 text-white',
                    },
                    {
                        text: detail.is_active ? 'Ativa' : 'Inativa',
                        className: detail.is_active
                            ? 'bg-white/20 text-white'
                            : 'bg-white/10 text-white/70',
                    },
                ] : []}
            >
                {detail && (
                    <>
                        <StandardModal.Section title="Informações">
                            <div className="grid grid-cols-2 gap-4">
                                <StandardModal.Field label="Código" value={detail.code} />
                                <StandardModal.Field label="Nome" value={detail.name} />
                                <StandardModal.Field
                                    label="Pai"
                                    value={detail.parent_label || '—'}
                                />
                                <StandardModal.Field
                                    label="Tipo"
                                    value={detail.accepts_entries ? 'Folha (analítica — aceita lançamento)' : 'Grupo (sintética — agregadora)'}
                                />
                                <StandardModal.Field
                                    label="Natureza"
                                    value={detail.nature_label}
                                />
                                <StandardModal.Field
                                    label="Grupo DRE"
                                    value={detail.dre_group_label}
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
                            {!detail.follows_natural_nature && (
                                <div className="mt-4 bg-amber-50 border border-amber-200 rounded p-3 text-sm text-amber-800 flex items-start gap-2">
                                    <ExclamationTriangleIcon className="w-5 h-5 shrink-0 mt-0.5" />
                                    <div>
                                        <p className="font-medium">Natureza divergente do padrão do grupo</p>
                                        <p className="text-xs mt-0.5">
                                            Esta conta é {detail.nature_label.toLowerCase()}, mas o grupo{' '}
                                            <strong>{detail.dre_group_label}</strong> tipicamente usa a
                                            natureza oposta. Geralmente isso é intencional (ex: desconto
                                            obtido como redutor de despesa), mas vale conferir.
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
                                            <StatusBadge variant={c.is_active ? 'success' : 'gray'}>
                                                {c.is_active ? 'Ativa' : 'Inativa'}
                                            </StatusBadge>
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
                title="Excluir conta contábil"
                subtitle={deleteTarget ? `${deleteTarget.code} · ${deleteTarget.name}` : ''}
                headerColor="bg-red-600"
                headerIcon={<ExclamationTriangleIcon className="h-6 w-6" />}
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
                            Soft delete. Contas com filhas ativas ou lançamentos vinculados
                            não podem ser excluídas.
                        </p>
                    </div>
                    <div>
                        <InputLabel value="Motivo da exclusão *" className="mb-1 text-xs" />
                        <TextInput
                            className="w-full text-sm"
                            value={deleteReason}
                            onChange={(e) => setDeleteReason(e.target.value)}
                            placeholder="Mínimo 3 caracteres"
                        />
                    </div>
                </div>
            </StandardModal>

            {/* -------- Import Modal -------- */}
            <StandardModal
                show={modals.import}
                onClose={() => closeModal('import')}
                title="Importar Plano de Contas"
                headerColor="bg-emerald-600"
                headerIcon={<DocumentArrowUpIcon className="h-6 w-6" />}
                maxWidth="4xl"
                footer={(
                    <div className="flex justify-between items-center gap-4 px-6 py-4 border-t bg-gray-50 rounded-b-xl shrink-0">
                        <p className="text-xs text-gray-500 flex-1 leading-relaxed">
                            Cabeçalhos aceitos: <code>codigo</code>, <code>nome</code>,{' '}
                            <code>descricao</code>, <code>codigo_pai</code>,{' '}
                            <code>natureza</code>, <code>grupo_dre</code>,{' '}
                            <code>aceita_lancamento</code>, <code>ativo</code>
                        </p>
                        <div className="flex gap-2 shrink-0">
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
                                    <p className="text-xs text-green-700 uppercase font-semibold">
                                        Válidas
                                    </p>
                                    <p className="text-2xl font-bold text-green-800">
                                        {importPreview.valid_count}
                                    </p>
                                </div>
                                <div className="bg-red-50 border border-red-200 rounded p-3">
                                    <p className="text-xs text-red-700 uppercase font-semibold">
                                        Com erro
                                    </p>
                                    <p className="text-2xl font-bold text-red-800">
                                        {importPreview.invalid_count}
                                    </p>
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
                                                <th className="px-2 py-1 text-left font-medium">Grupo DRE</th>
                                                <th className="px-2 py-1 text-left font-medium">Natureza</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-200">
                                            {importPreview.rows.map((r, i) => (
                                                <tr key={i}>
                                                    <td className="px-2 py-1 font-mono">{r.code}</td>
                                                    <td className="px-2 py-1">{r.name}</td>
                                                    <td className="px-2 py-1 text-gray-500">{r.parent_code || '—'}</td>
                                                    <td className="px-2 py-1 text-xs text-gray-600">{r.dre_group}</td>
                                                    <td className="px-2 py-1 text-xs">{r.nature}</td>
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
                                            <span className="font-mono text-xs text-red-800">
                                                Linha {e.row}:
                                            </span>
                                            <span className="text-red-700 ml-2">
                                                {e.messages.join('; ')}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </StandardModal.Section>
                        )}
                    </>
                )}
            </StandardModal>
        </>
    );
}

// ------------------------------------------------------------------
// Tree view sub-component
// ------------------------------------------------------------------
function TreeView({ nodes, onView, onEdit, onDelete, depth = 0 }) {
    return (
        <ul className={depth === 0 ? 'space-y-1' : 'space-y-0.5 ml-5 mt-0.5 border-l border-gray-200 pl-3'}>
            {nodes.map((node) => (
                <TreeNode
                    key={node.id}
                    node={node}
                    depth={depth}
                    onView={onView}
                    onEdit={onEdit}
                    onDelete={onDelete}
                />
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
                    <button
                        onClick={() => setExpanded(!expanded)}
                        className="text-gray-500 hover:text-gray-700"
                    >
                        {expanded ? (
                            <ChevronDownIcon className="w-4 h-4" />
                        ) : (
                            <ChevronRightIcon className="w-4 h-4" />
                        )}
                    </button>
                ) : (
                    <span className="w-4" />
                )}

                <span className={`font-mono text-xs ${node.is_active ? 'text-gray-700' : 'text-gray-400 line-through'}`}>
                    {node.code}
                </span>

                <span className={`text-sm flex-1 ${node.accepts_entries ? '' : 'font-semibold'} ${node.is_active ? 'text-gray-800' : 'text-gray-400'}`}>
                    {node.name}
                    {!node.accepts_entries && (
                        <span className="ml-2 text-xs text-gray-400">(grupo)</span>
                    )}
                </span>

                <span className={`inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-bold ${node.nature === 'debit' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'}`}>
                    {node.nature === 'debit' ? 'D' : 'C'}
                </span>

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
// Form fields (reused in create + edit)
// ------------------------------------------------------------------
function AccountingClassFormFields({ form, errors, onChange, parents, enums }) {
    return (
        <>
            <StandardModal.Section title="Dados básicos">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <InputLabel value="Código *" className="mb-1" />
                        <TextInput
                            className="w-full font-mono"
                            value={form.code || ''}
                            onChange={(e) => onChange({ code: e.target.value })}
                            maxLength={30}
                            placeholder="ex: 3.1.01.001"
                        />
                        <InputError message={errors.code} className="mt-1 text-xs" />
                    </div>
                    <div className="md:col-span-2">
                        <InputLabel value="Nome *" className="mb-1" />
                        <TextInput
                            className="w-full"
                            value={form.name || ''}
                            onChange={(e) => onChange({ name: e.target.value })}
                        />
                        <InputError message={errors.name} className="mt-1 text-xs" />
                    </div>
                </div>

                <div className="mt-4">
                    <InputLabel value="Descrição" className="mb-1" />
                    <textarea
                        value={form.description || ''}
                        onChange={(e) => onChange({ description: e.target.value })}
                        rows={2}
                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                </div>
            </StandardModal.Section>

            <StandardModal.Section title="Classificação contábil">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <InputLabel value="Natureza *" className="mb-1" />
                        <select
                            value={form.nature || 'debit'}
                            onChange={(e) => onChange({ nature: e.target.value })}
                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            {Object.entries(enums.natures || {}).map(([k, v]) => (
                                <option key={k} value={k}>{v}</option>
                            ))}
                        </select>
                        <InputError message={errors.nature} className="mt-1 text-xs" />
                    </div>

                    <div>
                        <InputLabel value="Grupo DRE *" className="mb-1" />
                        <select
                            value={form.dre_group || 'despesas_administrativas'}
                            onChange={(e) => onChange({ dre_group: e.target.value })}
                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            {Object.entries(enums.dreGroups || {}).map(([k, v]) => (
                                <option key={k} value={k}>{v}</option>
                            ))}
                        </select>
                        <InputError message={errors.dre_group} className="mt-1 text-xs" />
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
                                Desmarque para grupos sintéticos (agregadores). Grupos não
                                recebem lançamentos diretos — apenas totalizam suas filhas.
                            </span>
                        </span>
                    </label>
                </div>
                <InputError message={errors.accepts_entries} className="mt-1 text-xs" />
            </StandardModal.Section>

            <StandardModal.Section title="Hierarquia">
                <div>
                    <InputLabel value="Conta pai (apenas grupos sintéticos)" className="mb-1" />
                    <select
                        value={form.parent_id || ''}
                        onChange={(e) => onChange({ parent_id: e.target.value })}
                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">— Raiz (sem pai) —</option>
                        {parents.map((p) => (
                            <option key={p.id} value={p.id}>
                                {p.code} · {p.name}
                            </option>
                        ))}
                    </select>
                    <InputError message={errors.parent_id} className="mt-1 text-xs" />
                </div>
            </StandardModal.Section>

            <StandardModal.Section title="Organização">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <InputLabel value="Ordem" className="mb-1" />
                        <TextInput
                            type="number"
                            min="0"
                            className="w-full"
                            value={form.sort_order || 0}
                            onChange={(e) => onChange({ sort_order: parseInt(e.target.value) || 0 })}
                        />
                    </div>

                    <div className="flex items-center pt-6">
                        <input
                            id="is_active"
                            type="checkbox"
                            checked={!!form.is_active}
                            onChange={(e) => onChange({ is_active: e.target.checked })}
                            className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <InputLabel htmlFor="is_active" value="Ativa" className="ml-2" />
                    </div>
                </div>
            </StandardModal.Section>
        </>
    );
}
