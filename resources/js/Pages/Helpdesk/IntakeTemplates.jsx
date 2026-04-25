import { Head, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import {
    DocumentTextIcon,
    PlusIcon,
    PencilSquareIcon,
    TrashIcon,
} from '@heroicons/react/24/outline';
import Button from '@/Components/Button';
import StandardModal from '@/Components/StandardModal';
import PageHeader from '@/Components/Shared/PageHeader';
import StatusBadge from '@/Components/Shared/StatusBadge';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import Checkbox from '@/Components/Checkbox';

/**
 * Admin CRUD for intake templates. Follows the project's admin page
 * convention (see Permissions.jsx): max-w-5xl container, icon inline
 * com título, cards bg-white shadow-sm rounded-lg, tipografia responsiva.
 *
 * Templates are JSON-backed field schemas that any intake channel
 * (web, whatsapp, email) can render on top of the basic title/description.
 * Each template is scoped to a department and optionally a category.
 */
export default function IntakeTemplates({
    templates = [],
    departments = [],
    categories = [],
    fieldTypes = {},
}) {
    const [editing, setEditing] = useState(null);
    const [deleteTarget, setDeleteTarget] = useState(null);

    const startNew = () => setEditing({
        name: '',
        department_id: departments[0]?.id ?? '',
        category_id: '',
        active: true,
        sort_order: 0,
        fields: [],
    });

    const startEdit = (template) => setEditing({ ...template, fields: [...(template.fields || [])] });

    const handleDelete = () => {
        if (!deleteTarget) return;
        router.delete(route('helpdesk.intake-templates.destroy', deleteTarget.id), {
            onFinish: () => setDeleteTarget(null),
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Templates de Intake" />
            <div className="py-6 sm:py-12">
                <div className="max-w-full mx-auto px-3 sm:px-6 lg:px-8">
                    <PageHeader
                        title="Templates de Intake"
                        icon={DocumentTextIcon}
                        subtitle="Formulários estruturados para tipos específicos de chamado."
                        actions={[
                            { type: 'back', href: route('helpdesk.index') },
                            { type: 'create', label: 'Novo template', onClick: startNew },
                        ]}
                    />

                    {/* List */}
                    <div className="bg-white shadow-sm rounded-lg overflow-hidden">
                        {templates.length === 0 ? (
                            <div className="px-4 sm:px-6 py-10 sm:py-12 text-center text-gray-500 text-sm">
                                <DocumentTextIcon className="w-10 h-10 mx-auto text-gray-300 mb-2" />
                                Nenhum template cadastrado ainda.
                                <p className="text-xs text-gray-400 mt-1">
                                    Crie um template para padronizar a coleta de dados em tipos específicos de chamado.
                                </p>
                            </div>
                        ) : (
                            <>
                                {/* Desktop/tablet: table */}
                                <table className="hidden sm:table w-full text-sm">
                                    <thead className="bg-gray-50 text-xs uppercase text-gray-500">
                                        <tr>
                                            <th className="px-4 lg:px-6 py-3 text-left">Nome</th>
                                            <th className="px-4 lg:px-6 py-3 text-left">Departamento</th>
                                            <th className="px-4 lg:px-6 py-3 text-left hidden md:table-cell">Categoria</th>
                                            <th className="px-4 lg:px-6 py-3 text-left">Campos</th>
                                            <th className="px-4 lg:px-6 py-3 text-left">Status</th>
                                            <th className="px-4 lg:px-6 py-3 text-right">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {templates.map(t => (
                                            <tr key={t.id} className="hover:bg-gray-50">
                                                <td className="px-4 lg:px-6 py-3 font-medium text-gray-900">
                                                    {t.name}
                                                    <span className="block md:hidden text-xs text-gray-500 font-normal">
                                                        {t.category_name || '—'}
                                                    </span>
                                                </td>
                                                <td className="px-4 lg:px-6 py-3 text-gray-700">{t.department_name}</td>
                                                <td className="px-4 lg:px-6 py-3 text-gray-700 hidden md:table-cell">
                                                    {t.category_name || '—'}
                                                </td>
                                                <td className="px-4 lg:px-6 py-3 text-gray-500">{t.fields_count}</td>
                                                <td className="px-4 lg:px-6 py-3">
                                                    {t.active
                                                        ? <StatusBadge variant="success">Ativo</StatusBadge>
                                                        : <StatusBadge variant="gray">Inativo</StatusBadge>}
                                                </td>
                                                <td className="px-4 lg:px-6 py-3 text-right">
                                                    <div className="flex justify-end gap-1">
                                                        <Button variant="light" size="xs" icon={PencilSquareIcon}
                                                            onClick={() => startEdit(t)}>
                                                            <span className="hidden lg:inline">Editar</span>
                                                        </Button>
                                                        <Button variant="danger" size="xs" icon={TrashIcon}
                                                            onClick={() => setDeleteTarget(t)}>
                                                            <span className="hidden lg:inline">Remover</span>
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>

                                {/* Mobile: card list */}
                                <ul className="sm:hidden divide-y divide-gray-100">
                                    {templates.map(t => (
                                        <li key={t.id} className="p-4">
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="min-w-0 flex-1">
                                                    <div className="font-medium text-gray-900 truncate">{t.name}</div>
                                                    <div className="text-xs text-gray-500 truncate">
                                                        {t.department_name}
                                                        {t.category_name && ` · ${t.category_name}`}
                                                    </div>
                                                    <div className="flex items-center gap-2 mt-1">
                                                        <span className="text-xs text-gray-400">
                                                            {t.fields_count} campo{t.fields_count === 1 ? '' : 's'}
                                                        </span>
                                                        {t.active
                                                            ? <StatusBadge variant="success" className="text-[10px]">Ativo</StatusBadge>
                                                            : <StatusBadge variant="gray" className="text-[10px]">Inativo</StatusBadge>}
                                                    </div>
                                                </div>
                                                <div className="flex flex-col gap-1">
                                                    <Button variant="light" size="xs" icon={PencilSquareIcon}
                                                        onClick={() => startEdit(t)} />
                                                    <Button variant="danger" size="xs" icon={TrashIcon}
                                                        onClick={() => setDeleteTarget(t)} />
                                                </div>
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </>
                        )}
                    </div>
                </div>
            </div>

            {/* Editor modal */}
            {editing && (
                <TemplateEditorModal
                    template={editing}
                    departments={departments}
                    categories={categories}
                    fieldTypes={fieldTypes}
                    onClose={() => setEditing(null)}
                    onSaved={() => setEditing(null)}
                />
            )}

            {/* Delete confirm */}
            {deleteTarget && (
                <StandardModal show onClose={() => setDeleteTarget(null)}
                    title="Remover template"
                    headerColor="bg-red-600"
                    headerIcon={<TrashIcon className="h-5 w-5" />}
                    maxWidth="md"
                    footer={<StandardModal.Footer onCancel={() => setDeleteTarget(null)}
                        onSubmit={handleDelete} submitLabel="Remover" submitVariant="danger" />}>
                    <p className="text-sm text-gray-700">
                        Remover o template <strong>{deleteTarget.name}</strong>? Esta ação não pode ser desfeita.
                    </p>
                </StandardModal>
            )}
        </>
    );
}

// =============================================================
// Template editor modal
// =============================================================
function TemplateEditorModal({ template, departments, categories, fieldTypes, onClose, onSaved }) {
    const [data, setData] = useState(template);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});

    const isNew = !template.id;

    const availableCategories = useMemo(
        () => categories.filter(c => !data.department_id || c.department_id === Number(data.department_id)),
        [categories, data.department_id],
    );

    const addField = () => {
        setData(p => ({
            ...p,
            fields: [...p.fields, { name: '', label: '', type: 'text', required: false, options: [] }],
        }));
    };

    const removeField = (idx) => {
        setData(p => ({ ...p, fields: p.fields.filter((_, i) => i !== idx) }));
    };

    const updateField = (idx, patch) => {
        setData(p => ({
            ...p,
            fields: p.fields.map((f, i) => (i === idx ? { ...f, ...patch } : f)),
        }));
    };

    const moveField = (idx, direction) => {
        setData(p => {
            const next = [...p.fields];
            const to = idx + direction;
            if (to < 0 || to >= next.length) return p;
            [next[idx], next[to]] = [next[to], next[idx]];
            return { ...p, fields: next };
        });
    };

    const handleSave = () => {
        setProcessing(true);
        setErrors({});

        const payload = {
            ...data,
            category_id: data.category_id || null,
            department_id: Number(data.department_id),
        };

        const opts = {
            preserveScroll: true,
            preserveState: true,
            onError: (errs) => setErrors(errs),
            onSuccess: () => onSaved(),
            onFinish: () => setProcessing(false),
        };

        if (isNew) {
            router.post(route('helpdesk.intake-templates.store'), payload, opts);
        } else {
            router.put(route('helpdesk.intake-templates.update', template.id), payload, opts);
        }
    };

    return (
        <StandardModal
            show
            onClose={onClose}
            title={isNew ? 'Novo template' : 'Editar template'}
            subtitle={data.name || 'sem nome'}
            headerColor="bg-indigo-600"
            headerIcon={<DocumentTextIcon className="h-5 w-5" />}
            maxWidth="4xl"
            footer={
                <StandardModal.Footer
                    onCancel={onClose}
                    onSubmit={handleSave}
                    processing={processing}
                    submitLabel="Salvar"
                />
            }
        >
            <StandardModal.Section title="Identificação">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div className="sm:col-span-2">
                        <InputLabel value="Nome *" />
                        <TextInput
                            className="mt-1 w-full"
                            value={data.name}
                            onChange={e => setData(p => ({ ...p, name: e.target.value }))}
                            placeholder="Ex.: Solicitação de férias"
                        />
                        <InputError message={errors.name} />
                    </div>
                    <div>
                        <InputLabel value="Departamento *" />
                        <select
                            className="mt-1 w-full border-gray-300 rounded-lg text-sm"
                            value={data.department_id}
                            onChange={e => setData(p => ({ ...p, department_id: e.target.value, category_id: '' }))}
                        >
                            <option value="">Selecione</option>
                            {departments.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
                        </select>
                        <InputError message={errors.department_id} />
                    </div>
                    <div>
                        <InputLabel value="Categoria (opcional)" />
                        <select
                            className="mt-1 w-full border-gray-300 rounded-lg text-sm"
                            value={data.category_id || ''}
                            onChange={e => setData(p => ({ ...p, category_id: e.target.value }))}
                            disabled={!data.department_id}
                        >
                            <option value="">Qualquer categoria</option>
                            {availableCategories.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                        </select>
                        <InputError message={errors.category_id} />
                    </div>
                    <div>
                        <InputLabel value="Ordem" />
                        <TextInput
                            type="number"
                            min="0"
                            max="999"
                            className="mt-1 w-full sm:w-28"
                            value={data.sort_order}
                            onChange={e => setData(p => ({ ...p, sort_order: Number(e.target.value) }))}
                        />
                    </div>
                    <label className="flex items-center gap-2 mt-2 sm:mt-6">
                        <Checkbox
                            checked={data.active}
                            onChange={e => setData(p => ({ ...p, active: e.target.checked }))}
                        />
                        <span className="text-sm text-gray-700">Ativo</span>
                    </label>
                </div>
            </StandardModal.Section>

            <StandardModal.Section title={`Campos (${data.fields.length})`}>
                <div className="space-y-3">
                    {data.fields.map((field, idx) => (
                        <FieldEditorRow
                            key={idx}
                            field={field}
                            index={idx}
                            fieldTypes={fieldTypes}
                            errors={errors}
                            onUpdate={(patch) => updateField(idx, patch)}
                            onRemove={() => removeField(idx)}
                            onMoveUp={() => moveField(idx, -1)}
                            onMoveDown={() => moveField(idx, 1)}
                            canMoveUp={idx > 0}
                            canMoveDown={idx < data.fields.length - 1}
                        />
                    ))}
                    {data.fields.length === 0 && (
                        <p className="text-xs sm:text-sm text-gray-400 italic">Nenhum campo ainda.</p>
                    )}
                </div>
                <div className="mt-3">
                    <Button variant="light" size="sm" icon={PlusIcon} onClick={addField}>
                        Adicionar campo
                    </Button>
                </div>
            </StandardModal.Section>

            {data.fields.length > 0 && (
                <StandardModal.Section title="Pré-visualização">
                    <div className="bg-gray-50 rounded-lg p-3 sm:p-4 space-y-3">
                        {data.fields.map((field, idx) => (
                            <FieldPreview key={idx} field={field} />
                        ))}
                    </div>
                </StandardModal.Section>
            )}
        </StandardModal>
    );
}

// =============================================================
// Field editor row
// =============================================================
function FieldEditorRow({ field, index, fieldTypes, errors, onUpdate, onRemove, onMoveUp, onMoveDown, canMoveUp, canMoveDown }) {
    const isSelect = field.type === 'select' || field.type === 'multiselect';

    const addOption = () => {
        onUpdate({ options: [...(field.options || []), { value: '', label: '' }] });
    };

    const updateOption = (optIdx, patch) => {
        onUpdate({
            options: (field.options || []).map((o, i) => (i === optIdx ? { ...o, ...patch } : o)),
        });
    };

    const removeOption = (optIdx) => {
        onUpdate({ options: (field.options || []).filter((_, i) => i !== optIdx) });
    };

    return (
        <div className="border border-gray-200 rounded-lg p-3 bg-white">
            <div className="grid grid-cols-1 sm:grid-cols-12 gap-2">
                <div className="sm:col-span-1 flex sm:flex-col gap-1 justify-start">
                    <button type="button" onClick={onMoveUp} disabled={!canMoveUp}
                        className="text-xs text-gray-400 hover:text-gray-600 disabled:opacity-30">▲</button>
                    <button type="button" onClick={onMoveDown} disabled={!canMoveDown}
                        className="text-xs text-gray-400 hover:text-gray-600 disabled:opacity-30">▼</button>
                </div>
                <div className="sm:col-span-3">
                    <InputLabel value="Chave" />
                    <TextInput
                        className="mt-1 w-full font-mono text-xs"
                        value={field.name}
                        onChange={e => onUpdate({ name: e.target.value })}
                        placeholder="start_date"
                    />
                    <InputError message={errors[`fields.${index}.name`]} />
                </div>
                <div className="sm:col-span-4">
                    <InputLabel value="Rótulo" />
                    <TextInput
                        className="mt-1 w-full text-xs sm:text-sm"
                        value={field.label}
                        onChange={e => onUpdate({ label: e.target.value })}
                        placeholder="Ex.: Data inicial"
                    />
                    <InputError message={errors[`fields.${index}.label`]} />
                </div>
                <div className="sm:col-span-3">
                    <InputLabel value="Tipo" />
                    <select
                        className="mt-1 w-full border-gray-300 rounded-lg text-xs sm:text-sm"
                        value={field.type}
                        onChange={e => onUpdate({ type: e.target.value })}
                    >
                        {Object.entries(fieldTypes).map(([k, v]) => (
                            <option key={k} value={k}>{v}</option>
                        ))}
                    </select>
                </div>
                <div className="sm:col-span-1 flex items-end justify-end">
                    <button type="button" onClick={onRemove}
                        className="p-1 text-gray-400 hover:text-red-600" title="Remover campo">
                        <TrashIcon className="w-4 h-4" />
                    </button>
                </div>
                <label className="sm:col-span-12 flex items-center gap-2 text-xs sm:text-sm text-gray-700 mt-1">
                    <Checkbox
                        checked={!!field.required}
                        onChange={e => onUpdate({ required: e.target.checked })}
                    />
                    Obrigatório
                </label>
            </div>

            {isSelect && (
                <div className="mt-3 pt-3 border-t border-gray-100">
                    <InputLabel value="Opções" />
                    <div className="mt-2 space-y-2">
                        {(field.options || []).map((opt, optIdx) => (
                            <div key={optIdx} className="flex items-center gap-2">
                                <TextInput
                                    className="flex-1 text-xs font-mono"
                                    value={opt.value}
                                    onChange={e => updateOption(optIdx, { value: e.target.value })}
                                    placeholder="valor"
                                />
                                <TextInput
                                    className="flex-1 text-xs"
                                    value={opt.label}
                                    onChange={e => updateOption(optIdx, { label: e.target.value })}
                                    placeholder="rótulo"
                                />
                                <button type="button" onClick={() => removeOption(optIdx)}
                                    className="p-1 text-gray-400 hover:text-red-600">
                                    <TrashIcon className="w-4 h-4" />
                                </button>
                            </div>
                        ))}
                        <Button variant="light" size="xs" icon={PlusIcon} onClick={addOption}>
                            Adicionar opção
                        </Button>
                    </div>
                </div>
            )}
        </div>
    );
}

// =============================================================
// Live field preview
// =============================================================
function FieldPreview({ field }) {
    const label = (
        <label className="block text-xs sm:text-sm font-medium text-gray-700 mb-1">
            {field.label || <span className="italic text-gray-400">sem rótulo</span>}
            {field.required && <span className="text-red-500 ml-1">*</span>}
        </label>
    );

    switch (field.type) {
        case 'textarea':
            return (
                <div>
                    {label}
                    <textarea className="w-full border-gray-300 rounded-lg text-xs sm:text-sm" rows={3} disabled />
                </div>
            );
        case 'date':
            return (
                <div>
                    {label}
                    <input type="date" className="w-full border-gray-300 rounded-lg text-xs sm:text-sm" disabled />
                </div>
            );
        case 'select':
            return (
                <div>
                    {label}
                    <select className="w-full border-gray-300 rounded-lg text-xs sm:text-sm" disabled>
                        <option>Selecione</option>
                        {(field.options || []).map((o, i) => <option key={i}>{o.label}</option>)}
                    </select>
                </div>
            );
        case 'multiselect':
            return (
                <div>
                    {label}
                    <div className="flex flex-wrap gap-2">
                        {(field.options || []).map((o, i) => (
                            <label key={i} className="flex items-center gap-1 text-xs sm:text-sm text-gray-600">
                                <input type="checkbox" disabled /> {o.label}
                            </label>
                        ))}
                    </div>
                </div>
            );
        case 'boolean':
            return (
                <label className="flex items-center gap-2 text-xs sm:text-sm text-gray-700">
                    <input type="checkbox" disabled />
                    {field.label || <span className="italic text-gray-400">sem rótulo</span>}
                    {field.required && <span className="text-red-500">*</span>}
                </label>
            );
        case 'file':
            return (
                <div>
                    {label}
                    <input type="file" disabled className="text-xs sm:text-sm" />
                </div>
            );
        case 'text':
        default:
            return (
                <div>
                    {label}
                    <input type="text" className="w-full border-gray-300 rounded-lg text-xs sm:text-sm" disabled />
                </div>
            );
    }
}
