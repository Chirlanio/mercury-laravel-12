import { Head, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import {
    PlusIcon,
    ArrowUpIcon,
    ArrowDownIcon,
    ArrowsUpDownIcon,
    CheckCircleIcon,
    PencilSquareIcon,
} from '@heroicons/react/24/outline';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';
import EmptyState from '@/Components/Shared/EmptyState';
import DeleteConfirmModal from '@/Components/Shared/DeleteConfirmModal';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import Checkbox from '@/Components/Checkbox';
import useModalManager from '@/Hooks/useModalManager';

/**
 * Listagem ordenada + CRUD de linhas da DRE gerencial.
 *
 * Create/Edit via `StandardModal` (padrão do projeto — ver
 * `AccountingClasses/Index.jsx`). Sem páginas dedicadas de create/edit.
 *
 * Reorder é client-side (setas ↑/↓ + botão "Salvar ordem" → POST
 * /dre/management-lines/reorder). Sem busca/paginação porque o volume é
 * pequeno (≈20 linhas) e o reorder exige tudo visível ao mesmo tempo.
 */
export default function ManagementLinesIndex({ lines, can, natureOptions }) {
    const { flash } = usePage().props;
    const { modals, selected, openModal, closeModal } = useModalManager(['create', 'edit']);

    const [ordered, setOrdered] = useState(() => [...lines]);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [isDirty, setIsDirty] = useState(false);

    const emptyForm = {
        code: '',
        sort_order: (lines.length + 1) * 10,
        is_subtotal: false,
        accumulate_until_sort_order: null,
        level_1: '',
        nature: 'expense',
        is_active: true,
        notes: '',
    };

    const [createForm, setCreateForm] = useState(emptyForm);
    const [createErrors, setCreateErrors] = useState({});
    const [createProcessing, setCreateProcessing] = useState(false);

    const [editForm, setEditForm] = useState({});
    const [editErrors, setEditErrors] = useState({});
    const [editProcessing, setEditProcessing] = useState(false);

    const natureLabel = useMemo(() => {
        const map = {};
        (natureOptions || []).forEach((opt) => {
            map[opt.value] = opt.label;
        });
        return map;
    }, [natureOptions]);

    // ------------------------------------------------------------------
    // Reorder
    // ------------------------------------------------------------------

    const move = (index, delta) => {
        const next = [...ordered];
        const target = index + delta;
        if (target < 0 || target >= next.length) return;
        [next[index], next[target]] = [next[target], next[index]];
        setOrdered(next);
        setIsDirty(true);
    };

    const resetOrder = () => {
        setOrdered([...lines]);
        setIsDirty(false);
    };

    const saveOrder = () => {
        router.post(
            route('dre.management-lines.reorder'),
            { ids: ordered.map((l) => l.id) },
            {
                preserveScroll: true,
                onSuccess: () => setIsDirty(false),
            }
        );
    };

    // ------------------------------------------------------------------
    // Create
    // ------------------------------------------------------------------

    const handleCreateOpen = () => {
        setCreateForm({ ...emptyForm, sort_order: (lines.length + 1) * 10 });
        setCreateErrors({});
        openModal('create');
    };

    const handleCreateSubmit = (e) => {
        e.preventDefault();
        setCreateProcessing(true);
        setCreateErrors({});

        router.post(route('dre.management-lines.store'), createForm, {
            preserveScroll: true,
            onSuccess: () => {
                closeModal('create');
                setCreateForm(emptyForm);
            },
            onError: (errors) => setCreateErrors(errors),
            onFinish: () => setCreateProcessing(false),
        });
    };

    // ------------------------------------------------------------------
    // Edit
    // ------------------------------------------------------------------

    const handleEditOpen = (line) => {
        setEditForm({
            id: line.id,
            code: line.code ?? '',
            sort_order: line.sort_order ?? 1,
            is_subtotal: line.is_subtotal ?? false,
            accumulate_until_sort_order: line.accumulate_until_sort_order ?? null,
            level_1: line.level_1 ?? '',
            nature: line.nature ?? 'expense',
            is_active: line.is_active ?? true,
            notes: line.notes ?? '',
        });
        setEditErrors({});
        openModal('edit', line);
    };

    const handleEditSubmit = (e) => {
        e.preventDefault();
        setEditProcessing(true);
        setEditErrors({});

        const { id, ...payload } = editForm;
        router.put(route('dre.management-lines.update', id), payload, {
            preserveScroll: true,
            onSuccess: () => closeModal('edit'),
            onError: (errors) => setEditErrors(errors),
            onFinish: () => setEditProcessing(false),
        });
    };

    // ------------------------------------------------------------------
    // Delete
    // ------------------------------------------------------------------

    const confirmDelete = () => {
        if (!deleteTarget) return;
        router.delete(route('dre.management-lines.destroy', deleteTarget.id), {
            preserveScroll: true,
            onSuccess: () => setDeleteTarget(null),
        });
    };

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    const natureBadgeVariant = (nature) => {
        if (nature === 'revenue') return 'success';
        if (nature === 'expense') return 'danger';
        return 'info';
    };

    return (
        <>
            <Head title="Plano Gerencial da DRE" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="mb-6 flex justify-between items-center">
                        <div>
                            <h1 className="text-2xl font-semibold text-gray-900">
                                Plano Gerencial da DRE
                            </h1>
                            <p className="text-sm text-gray-600 mt-1">
                                Linhas executivas do relatório. Subtotais acumulam linhas
                                anteriores até a ordem configurada em{' '}
                                <code className="text-xs">accumulate_until_sort_order</code>.
                            </p>
                        </div>

                        {can?.manage && (
                            <div className="flex gap-2">
                                {isDirty && (
                                    <>
                                        <Button
                                            variant="secondary"
                                            size="sm"
                                            onClick={resetOrder}
                                        >
                                            Cancelar ordem
                                        </Button>
                                        <Button
                                            variant="success"
                                            size="sm"
                                            icon={ArrowsUpDownIcon}
                                            onClick={saveOrder}
                                        >
                                            Salvar ordem
                                        </Button>
                                    </>
                                )}
                                <Button
                                    variant="primary"
                                    size="sm"
                                    icon={PlusIcon}
                                    onClick={handleCreateOpen}
                                >
                                    Nova linha
                                </Button>
                            </div>
                        )}
                    </div>

                    {ordered.length === 0 ? (
                        <EmptyState
                            title="Nenhuma linha cadastrada"
                            description="Comece criando as linhas da DRE executiva."
                        />
                    ) : (
                        <div className="bg-white overflow-hidden shadow-sm rounded-lg">
                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-12">
                                                #
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-20">
                                                Ordem
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-28">
                                                Código
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                                Rótulo
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">
                                                Natureza
                                            </th>
                                            <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider w-24">
                                                Subtotal
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-28">
                                                Acumula até
                                            </th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">
                                                Status
                                            </th>
                                            {can?.manage && (
                                                <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-56">
                                                    Ações
                                                </th>
                                            )}
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {ordered.map((line, idx) => (
                                            <tr
                                                key={line.id}
                                                className={line.is_subtotal ? 'bg-gray-50' : ''}
                                            >
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 tabular-nums">
                                                    {idx + 1}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-700 tabular-nums">
                                                    {line.sort_order}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-mono">
                                                    {line.code}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <span
                                                        className={line.is_subtotal ? 'font-semibold' : ''}
                                                    >
                                                        {line.level_1}
                                                    </span>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm">
                                                    <StatusBadge
                                                        variant={natureBadgeVariant(line.nature)}
                                                        dot
                                                    >
                                                        {natureLabel[line.nature] || line.nature}
                                                    </StatusBadge>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-center">
                                                    {line.is_subtotal ? (
                                                        <CheckCircleIcon className="h-5 w-5 text-indigo-600 inline" />
                                                    ) : (
                                                        <span className="text-gray-300">—</span>
                                                    )}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-700 tabular-nums">
                                                    {line.accumulate_until_sort_order ?? '—'}
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <StatusBadge
                                                        variant={line.is_active ? 'success' : 'gray'}
                                                        dot
                                                    >
                                                        {line.is_active ? 'Ativa' : 'Inativa'}
                                                    </StatusBadge>
                                                </td>
                                                {can?.manage && (
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <ActionButtons
                                                            onEdit={() => handleEditOpen(line)}
                                                            onDelete={() => setDeleteTarget(line)}
                                                        >
                                                            <ActionButtons.Custom
                                                                variant="light"
                                                                icon={ArrowUpIcon}
                                                                title="Mover acima"
                                                                onClick={() => move(idx, -1)}
                                                                disabled={idx === 0}
                                                            />
                                                            <ActionButtons.Custom
                                                                variant="light"
                                                                icon={ArrowDownIcon}
                                                                title="Mover abaixo"
                                                                onClick={() => move(idx, 1)}
                                                                disabled={idx === ordered.length - 1}
                                                            />
                                                        </ActionButtons>
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* -------- Create Modal -------- */}
            <StandardModal
                show={modals.create}
                onClose={() => closeModal('create')}
                title="Nova Linha da DRE"
                headerColor="bg-indigo-600"
                headerIcon={<PlusIcon className="h-6 w-6 text-white" />}
                maxWidth="2xl"
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
                <ManagementLineFormFields
                    form={createForm}
                    errors={createErrors}
                    onChange={(patch) => setCreateForm({ ...createForm, ...patch })}
                    natureOptions={natureOptions}
                />
            </StandardModal>

            {/* -------- Edit Modal -------- */}
            <StandardModal
                show={modals.edit}
                onClose={() => closeModal('edit')}
                title={selected?.level_1 ? `Editar "${selected.level_1}"` : 'Editar linha'}
                headerColor="bg-amber-600"
                headerIcon={<PencilSquareIcon className="h-6 w-6 text-white" />}
                maxWidth="2xl"
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
                <ManagementLineFormFields
                    form={editForm}
                    errors={editErrors}
                    onChange={(patch) => setEditForm({ ...editForm, ...patch })}
                    natureOptions={natureOptions}
                />
            </StandardModal>

            {/* -------- Delete Confirm -------- */}
            <DeleteConfirmModal
                show={deleteTarget !== null}
                onClose={() => setDeleteTarget(null)}
                onConfirm={confirmDelete}
                itemType="linha gerencial"
                itemName={deleteTarget?.level_1}
                details={
                    deleteTarget
                        ? [
                              { label: 'Código', value: deleteTarget.code },
                              { label: 'Ordem', value: String(deleteTarget.sort_order) },
                          ]
                        : []
                }
                warningMessage="A linha será marcada como excluída (soft delete). Mapeamentos vigentes impedem a exclusão."
            />
        </>
    );
}

// ----------------------------------------------------------------------
// Fields subcomponent — reutilizado entre create e edit.
// ----------------------------------------------------------------------

function ManagementLineFormFields({ form, errors, onChange, natureOptions }) {
    return (
        <>
            <StandardModal.Section title="Dados gerais">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <InputLabel htmlFor="code" value="Código" />
                        <TextInput
                            id="code"
                            className="w-full mt-1"
                            value={form.code}
                            onChange={(e) => onChange({ code: e.target.value })}
                            placeholder="Ex: L21"
                            maxLength={20}
                            required
                        />
                        <InputError className="mt-1" message={errors.code} />
                    </div>

                    <div>
                        <InputLabel htmlFor="sort_order" value="Ordem" />
                        <TextInput
                            id="sort_order"
                            type="number"
                            min="1"
                            className="w-full mt-1"
                            value={form.sort_order}
                            onChange={(e) =>
                                onChange({
                                    sort_order: parseInt(e.target.value, 10) || 1,
                                })
                            }
                            required
                        />
                        <InputError className="mt-1" message={errors.sort_order} />
                    </div>
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="level_1" value="Rótulo (level_1)" />
                    <TextInput
                        id="level_1"
                        className="w-full mt-1"
                        value={form.level_1}
                        onChange={(e) => onChange({ level_1: e.target.value })}
                        placeholder="Ex: (+) Faturamento Bruto"
                        required
                    />
                    <InputError className="mt-1" message={errors.level_1} />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="nature" value="Natureza" />
                    <select
                        id="nature"
                        value={form.nature}
                        onChange={(e) => onChange({ nature: e.target.value })}
                        className="mt-1 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm w-full"
                    >
                        {(natureOptions || []).map((opt) => (
                            <option key={opt.value} value={opt.value}>
                                {opt.label}
                            </option>
                        ))}
                    </select>
                    <InputError className="mt-1" message={errors.nature} />
                </div>
            </StandardModal.Section>

            <StandardModal.Section title="Subtotal">
                <div className="flex items-start gap-3">
                    <Checkbox
                        id="is_subtotal"
                        checked={form.is_subtotal}
                        onChange={(e) => onChange({ is_subtotal: e.target.checked })}
                    />
                    <div>
                        <InputLabel htmlFor="is_subtotal" value="É subtotal?" className="!mb-0" />
                        <p className="text-xs text-gray-500 mt-1">
                            Subtotais acumulam linhas anteriores até a ordem configurada
                            abaixo (ex: EBITDA acumula até o sort_order da Depreciação).
                        </p>
                    </div>
                </div>

                {form.is_subtotal && (
                    <div className="mt-4">
                        <InputLabel
                            htmlFor="accumulate_until_sort_order"
                            value="Acumular até a ordem"
                        />
                        <TextInput
                            id="accumulate_until_sort_order"
                            type="number"
                            min="1"
                            className="w-full mt-1"
                            value={form.accumulate_until_sort_order ?? ''}
                            onChange={(e) =>
                                onChange({
                                    accumulate_until_sort_order:
                                        e.target.value === ''
                                            ? null
                                            : parseInt(e.target.value, 10),
                                })
                            }
                        />
                        <InputError
                            className="mt-1"
                            message={errors.accumulate_until_sort_order}
                        />
                    </div>
                )}
            </StandardModal.Section>

            <StandardModal.Section title="Detalhes">
                <div className="flex items-center gap-2">
                    <Checkbox
                        id="is_active"
                        checked={form.is_active}
                        onChange={(e) => onChange({ is_active: e.target.checked })}
                    />
                    <InputLabel htmlFor="is_active" value="Ativa" className="!mb-0" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="notes" value="Observações" />
                    <textarea
                        id="notes"
                        rows="3"
                        className="mt-1 border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm w-full text-sm"
                        value={form.notes ?? ''}
                        onChange={(e) => onChange({ notes: e.target.value })}
                    />
                    <InputError className="mt-1" message={errors.notes} />
                </div>
            </StandardModal.Section>
        </>
    );
}
