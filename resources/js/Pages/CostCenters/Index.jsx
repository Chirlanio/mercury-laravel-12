import { Head, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import axios from 'axios';
import {
    PlusIcon,
    Squares2X2Icon,
    CheckCircleIcon,
    XCircleIcon,
    FolderIcon,
    Bars3BottomLeftIcon,
    DocumentArrowDownIcon,
    DocumentArrowUpIcon,
    ExclamationTriangleIcon,
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

export default function Index({ costCenters, filters = {}, statistics = {}, selects = {} }) {
    const { hasPermission } = usePermissions();
    const canCreate = hasPermission(PERMISSIONS.CREATE_COST_CENTERS);
    const canEdit = hasPermission(PERMISSIONS.EDIT_COST_CENTERS);
    const canDelete = hasPermission(PERMISSIONS.DELETE_COST_CENTERS);
    const canImport = hasPermission(PERMISSIONS.IMPORT_COST_CENTERS);
    const canExport = hasPermission(PERMISSIONS.EXPORT_COST_CENTERS);

    const { modals, selected, openModal, closeModal } = useModalManager([
        'create', 'detail', 'edit', 'import',
    ]);

    const emptyForm = {
        code: '',
        name: '',
        description: '',
        parent_id: '',
        manager_id: '',
        area_id: '',
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

        router.post(route('cost-centers.store'), createForm, {
            preserveScroll: true,
            onSuccess: () => {
                setCreateForm(emptyForm);
                closeModal('create');
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

    const handleEditOpen = (costCenter) => {
        setEditForm({
            id: costCenter.id,
            code: costCenter.code,
            name: costCenter.name,
            description: costCenter.description || '',
            parent_id: costCenter.parent_id || '',
            manager_id: costCenter.manager_id || '',
            area_id: costCenter.area_id || '',
            is_active: costCenter.is_active,
        });
        setEditErrors({});
        openModal('edit', costCenter);
    };

    const handleEditSubmit = (e) => {
        e.preventDefault();
        setEditProcessing(true);
        setEditErrors({});

        const { id, ...payload } = editForm;
        router.put(route('cost-centers.update', id), payload, {
            preserveScroll: true,
            onSuccess: () => closeModal('edit'),
            onError: (errors) => setEditErrors(errors),
            onFinish: () => setEditProcessing(false),
        });
    };

    // ------------------------------------------------------------------
    // Detail
    // ------------------------------------------------------------------
    const [detail, setDetail] = useState(null);
    const [loadingDetail, setLoadingDetail] = useState(false);

    const handleDetailOpen = async (costCenter) => {
        openModal('detail', costCenter);
        setLoadingDetail(true);
        try {
            const { data } = await axios.get(route('cost-centers.show', costCenter.id));
            setDetail(data.costCenter);
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
        router.delete(route('cost-centers.destroy', deleteTarget.id), {
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
            const { data } = await axios.post(route('cost-centers.import.preview'), formData);
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
        router.post(route('cost-centers.import.store'), formData, {
            preserveScroll: true,
            onSuccess: () => {
                setImportFile(null);
                setImportPreview(null);
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
        router.get(route('cost-centers.index'), {
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
            icon: Squares2X2Icon,
            color: 'indigo',
        },
        {
            label: 'Ativos',
            value: statistics.active || 0,
            format: 'number',
            icon: CheckCircleIcon,
            color: 'green',
            active: filters.is_active === '1' || filters.is_active === 1 || filters.is_active === true,
            onClick: () => applyFilter('is_active', filters.is_active ? '' : '1'),
        },
        {
            label: 'Inativos',
            value: statistics.inactive || 0,
            format: 'number',
            icon: XCircleIcon,
            color: 'red',
        },
        {
            label: 'Raízes',
            value: statistics.roots || 0,
            format: 'number',
            icon: FolderIcon,
            color: 'purple',
        },
        {
            label: 'Com hierarquia',
            value: statistics.with_parent || 0,
            format: 'number',
            icon: Bars3BottomLeftIcon,
            color: 'orange',
        },
    ], [statistics, filters]);

    const columns = [
        { key: 'code', label: 'Código', sortable: true, className: 'font-mono' },
        { key: 'name', label: 'Nome', sortable: true },
        {
            key: 'parent_label',
            label: 'Pai',
            render: (c) => c.parent_label ? (
                <span className="text-xs text-gray-600">{c.parent_label}</span>
            ) : <span className="text-xs text-gray-400">—</span>,
        },
        {
            key: 'manager_name',
            label: 'Responsável',
            render: (c) => c.manager_name || <span className="text-xs text-gray-400">—</span>,
        },
        {
            key: 'is_active',
            label: 'Status',
            render: (c) => (
                <StatusBadge
                    status={c.is_active ? 'success' : 'gray'}
                    text={c.is_active ? 'Ativo' : 'Inativo'}
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
            <Head title="Centros de Custo" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="mb-6 flex justify-between items-center">
                        <div>
                            <h1 className="text-3xl font-bold text-gray-900">Centros de Custo</h1>
                            <p className="mt-1 text-sm text-gray-600">
                                Cadastro de centros de custo — base para orçamentos e DRE.
                            </p>
                        </div>
                        <div className="flex gap-2">
                            {canExport && (
                                <Button
                                    variant="secondary"
                                    icon={DocumentArrowDownIcon}
                                    onClick={() => window.location.href = route('cost-centers.export', filters)}
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
                                    Novo
                                </Button>
                            )}
                        </div>
                    </div>

                    <StatisticsGrid cards={statisticsCards} cols={5} />

                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6 mt-6">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Buscar
                                </label>
                                <input
                                    type="text"
                                    value={filters.search || ''}
                                    onChange={(e) => applyFilter('search', e.target.value)}
                                    placeholder="Código, nome ou descrição..."
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Hierarquia
                                </label>
                                <select
                                    value={filters.parent_id || ''}
                                    onChange={(e) => applyFilter('parent_id', e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm"
                                >
                                    <option value="">Todos</option>
                                    <option value="root">Apenas raízes</option>
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Status
                                </label>
                                <select
                                    value={filters.is_active ?? ''}
                                    onChange={(e) => applyFilter('is_active', e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm"
                                >
                                    <option value="">Todos</option>
                                    <option value="1">Ativos</option>
                                    <option value="0">Inativos</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <DataTable
                        columns={columns}
                        data={costCenters}
                        emptyMessage="Nenhum centro de custo cadastrado."
                    />
                </div>
            </div>

            {/* ---------------- Create Modal ---------------- */}
            <StandardModal
                show={modals.create}
                onClose={() => closeModal('create')}
                title="Novo Centro de Custo"
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
                <CostCenterFormFields
                    form={createForm}
                    errors={createErrors}
                    onChange={(patch) => setCreateForm({ ...createForm, ...patch })}
                    parents={selects.parents || []}
                    managers={selects.managers || []}
                />
            </StandardModal>

            {/* ---------------- Edit Modal ---------------- */}
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
                <CostCenterFormFields
                    form={editForm}
                    errors={editErrors}
                    onChange={(patch) => setEditForm({ ...editForm, ...patch })}
                    parents={(selects.parents || []).filter((p) => p.id !== editForm.id)}
                    managers={selects.managers || []}
                />
            </StandardModal>

            {/* ---------------- Detail Modal ---------------- */}
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
                headerBadges={detail ? [{
                    text: detail.is_active ? 'Ativo' : 'Inativo',
                    className: detail.is_active
                        ? 'bg-white/20 text-white'
                        : 'bg-white/20 text-white/70',
                }] : []}
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
                                    label="Responsável"
                                    value={detail.manager_name || '—'}
                                />
                                <StandardModal.Field
                                    label="Área"
                                    value={detail.area_id ?? '—'}
                                />
                                <StandardModal.Field
                                    label="Criado por"
                                    value={detail.created_by || '—'}
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
                        </StandardModal.Section>

                        {detail.children && detail.children.length > 0 && (
                            <StandardModal.Section title={`Filhos (${detail.children.length})`}>
                                <ul className="divide-y divide-gray-200 bg-white rounded border border-gray-200">
                                    {detail.children.map((c) => (
                                        <li key={c.id} className="p-3 flex justify-between items-center text-sm">
                                            <div>
                                                <span className="font-mono text-gray-700">{c.code}</span>
                                                <span className="text-gray-500 ml-2">{c.name}</span>
                                            </div>
                                            <StatusBadge
                                                status={c.is_active ? 'success' : 'gray'}
                                                text={c.is_active ? 'Ativo' : 'Inativo'}
                                            />
                                        </li>
                                    ))}
                                </ul>
                            </StandardModal.Section>
                        )}
                    </>
                )}
            </StandardModal>

            {/* ---------------- Delete Modal ---------------- */}
            <StandardModal
                show={deleteTarget !== null}
                onClose={() => { setDeleteTarget(null); setDeleteReason(''); }}
                title="Excluir centro de custo"
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
                            Esta ação é irreversível (soft delete). Centros com filhos
                            ativos não podem ser excluídos.
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

            {/* ---------------- Import Modal ---------------- */}
            <StandardModal
                show={modals.import}
                onClose={() => closeModal('import')}
                title="Importar Centros de Custo"
                headerColor="bg-emerald-600"
                headerIcon={DocumentArrowUpIcon}
                maxWidth="4xl"
                footer={(
                    <div className="flex justify-between items-center w-full">
                        <p className="text-xs text-gray-500">
                            Cabeçalhos aceitos: <code>codigo</code>, <code>nome</code>,{' '}
                            <code>descricao</code>, <code>codigo_pai</code>,{' '}
                            <code>responsavel</code>, <code>ativo</code>
                        </p>
                        <div className="flex gap-2">
                            <Button
                                variant="secondary"
                                onClick={() => closeModal('import')}
                            >
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
                                                <th className="px-2 py-1 text-left font-medium">Responsável</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-200">
                                            {importPreview.rows.map((r, i) => (
                                                <tr key={i}>
                                                    <td className="px-2 py-1 font-mono">{r.code}</td>
                                                    <td className="px-2 py-1">{r.name}</td>
                                                    <td className="px-2 py-1 text-gray-500">{r.parent_code || '—'}</td>
                                                    <td className="px-2 py-1 text-gray-500">{r.manager_name || '—'}</td>
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
        </AuthenticatedLayout>
    );
}

// ------------------------------------------------------------------
// Form fields sub-component (reused in create + edit)
// ------------------------------------------------------------------
function CostCenterFormFields({ form, errors, onChange, parents, managers }) {
    return (
        <>
            <StandardModal.Section title="Dados básicos">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Código *
                        </label>
                        <input
                            type="text"
                            value={form.code || ''}
                            onChange={(e) => onChange({ code: e.target.value })}
                            maxLength={20}
                            className="w-full rounded-md border-gray-300 shadow-sm font-mono"
                        />
                        {errors.code && (
                            <p className="mt-1 text-xs text-red-600">{errors.code}</p>
                        )}
                    </div>
                    <div className="md:col-span-2">
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Nome *
                        </label>
                        <input
                            type="text"
                            value={form.name || ''}
                            onChange={(e) => onChange({ name: e.target.value })}
                            className="w-full rounded-md border-gray-300 shadow-sm"
                        />
                        {errors.name && (
                            <p className="mt-1 text-xs text-red-600">{errors.name}</p>
                        )}
                    </div>
                </div>

                <div className="mt-4">
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Descrição
                    </label>
                    <textarea
                        value={form.description || ''}
                        onChange={(e) => onChange({ description: e.target.value })}
                        rows={2}
                        className="w-full rounded-md border-gray-300 shadow-sm"
                    />
                </div>
            </StandardModal.Section>

            <StandardModal.Section title="Hierarquia e responsabilidade">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Centro pai
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
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Responsável
                        </label>
                        <select
                            value={form.manager_id || ''}
                            onChange={(e) => onChange({ manager_id: e.target.value })}
                            className="w-full rounded-md border-gray-300 shadow-sm"
                        >
                            <option value="">— Sem responsável —</option>
                            {managers.map((m) => (
                                <option key={m.id} value={m.id}>{m.name}</option>
                            ))}
                        </select>
                    </div>
                </div>

                <div className="mt-4 flex items-center">
                    <input
                        id="is_active"
                        type="checkbox"
                        checked={!!form.is_active}
                        onChange={(e) => onChange({ is_active: e.target.checked })}
                        className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <label htmlFor="is_active" className="ml-2 text-sm text-gray-700">
                        Ativo
                    </label>
                </div>
            </StandardModal.Section>
        </>
    );
}
