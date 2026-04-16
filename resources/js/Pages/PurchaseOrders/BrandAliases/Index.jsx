import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import {
    ArrowLeftIcon, ArrowPathIcon, PlusIcon, PencilSquareIcon,
    MagnifyingGlassIcon, XMarkIcon, CheckCircleIcon, ExclamationTriangleIcon,
    TagIcon, SparklesIcon,
} from '@heroicons/react/24/outline';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import DeleteConfirmModal from '@/Components/Shared/DeleteConfirmModal';
import EmptyState from '@/Components/Shared/EmptyState';
import FormSection from '@/Components/Shared/FormSection';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';

export default function Index({ aliases, filters = {}, stats = {}, productBrands = [] }) {
    const [showForm, setShowForm] = useState(false);
    const [showManualBrand, setShowManualBrand] = useState(false);
    const [editing, setEditing] = useState(null);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleting, setDeleting] = useState(false);

    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');

    const applyFilters = () => {
        router.get(route('purchase-orders.brand-aliases.index'), {
            search: search || undefined,
            status: status || undefined,
        }, { preserveState: true });
    };

    const clearFilters = () => {
        setSearch('');
        setStatus('');
        router.get(route('purchase-orders.brand-aliases.index'));
    };

    const openCreate = () => { setEditing(null); setShowForm(true); };
    const openEdit = (alias) => { setEditing(alias); setShowForm(true); };
    const closeForm = () => { setShowForm(false); setEditing(null); };

    const handleAutoDetect = () => {
        router.post(route('purchase-orders.brand-aliases.auto-detect'), {}, {
            preserveScroll: true,
        });
    };

    const handleConfirmDelete = () => {
        if (!deleteTarget) return;
        setDeleting(true);
        router.delete(route('purchase-orders.brand-aliases.destroy', deleteTarget.id), {
            onSuccess: () => { setDeleteTarget(null); setDeleting(false); },
            onError: () => setDeleting(false),
        });
    };

    const statsCards = [
        { label: 'Total', value: stats.total || 0, format: 'number', icon: TagIcon, color: 'indigo' },
        { label: 'Resolvidos', value: stats.resolved || 0, format: 'number', icon: CheckCircleIcon, color: 'green' },
        { label: 'Pendentes', value: stats.pending || 0, format: 'number', icon: ExclamationTriangleIcon, color: 'yellow', active: (stats.pending || 0) > 0 },
        { label: 'Inativos', value: stats.inactive || 0, format: 'number', color: 'gray' },
    ];

    return (
        <>
            <Head title="Aliases de Marca" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="mb-6">
                        <div className="flex justify-between items-center">
                            <div>
                                <h1 className="text-3xl font-bold text-gray-900">Aliases de Marca</h1>
                                <p className="mt-1 text-sm text-gray-600">
                                    Resolve diferenças entre nomes de marca da planilha de importação e o catálogo oficial (CIGAM)
                                </p>
                            </div>
                            <div className="flex gap-2">
                                <Button variant="outline" onClick={handleAutoDetect} icon={SparklesIcon}>
                                    Auto-detectar MS
                                </Button>
                                <Button variant="outline" onClick={() => setShowManualBrand(true)} icon={TagIcon}>
                                    Criar Marca Manual
                                </Button>
                                <Button variant="primary" onClick={openCreate} icon={PlusIcon}>
                                    Novo Alias
                        </Button>
                                <Link href={route('purchase-orders.import.page')}>
                                    <Button variant="outline" size="sm" icon={ArrowLeftIcon}>Voltar</Button>
                                </Link>
                            </div>
                        </div>
                    </div>

                    <div className="mb-6">
                        <StatisticsGrid cards={statsCards} />
                    </div>

                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                            <div className="md:col-span-2">
                                <label className="block text-xs font-medium text-gray-700 mb-1">Buscar nome</label>
                                <input type="text" placeholder="Ex: DIAN PATRIS..."
                                    value={search} onChange={(e) => setSearch(e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">Status</label>
                                <select value={status} onChange={(e) => setStatus(e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Todos</option>
                                    <option value="resolved">Resolvidos</option>
                                    <option value="pending">Pendentes</option>
                                    <option value="inactive">Inativos</option>
                                </select>
                            </div>
                            <div className="flex gap-2">
                                <Button variant="primary" size="sm" onClick={applyFilters} icon={MagnifyingGlassIcon}>Filtrar</Button>
                                <Button variant="outline" size="sm" onClick={clearFilters} icon={XMarkIcon}>Limpar</Button>
                            </div>
                        </div>
                    </div>

                    <div className="bg-white shadow-sm rounded-lg overflow-hidden">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    {['Nome da planilha', 'Marca oficial (CIGAM)', 'Origem', 'Status', 'Ações'].map((h) => (
                                        <th key={h} className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200">
                                {aliases.data?.length > 0 ? aliases.data.map((a) => (
                                    <tr key={a.id} className={`hover:bg-gray-50 ${!a.product_brand_id ? 'bg-yellow-50/40' : ''}`}>
                                        <td className="px-4 py-3 text-sm font-medium text-gray-900">{a.source_name}</td>
                                        <td className="px-4 py-3 text-sm">
                                            {a.product_brand_name ? (
                                                <div>
                                                    <div className="text-gray-900">{a.product_brand_name}</div>
                                                    {a.product_brand_cigam_code && (
                                                        <div className="text-xs text-gray-500 font-mono">{a.product_brand_cigam_code}</div>
                                                    )}
                                                </div>
                                            ) : (
                                                <span className="text-yellow-700 italic">Pendente — configure</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            {a.auto_detected ? (
                                                <StatusBadge variant="info">Auto-detectado</StatusBadge>
                                            ) : (
                                                <StatusBadge variant="purple">Manual</StatusBadge>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <StatusBadge variant={a.is_active ? 'success' : 'gray'}>
                                                {a.is_active ? 'Ativo' : 'Inativo'}
                                            </StatusBadge>
                                        </td>
                                        <td className="px-4 py-3">
                                            <ActionButtons
                                                onEdit={() => openEdit(a)}
                                                onDelete={() => setDeleteTarget(a)}
                                            />
                                        </td>
                                    </tr>
                                )) : (
                                    <tr>
                                        <td colSpan="5" className="px-4 py-12">
                                            <EmptyState
                                                icon={TagIcon}
                                                title="Nenhum alias"
                                                description="Aliases são criados automaticamente durante a importação de planilhas."
                                                compact
                                            />
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                        {aliases.last_page > 1 && (
                            <div className="px-4 py-3 border-t flex justify-between items-center">
                                <span className="text-sm text-gray-700">{aliases.from} a {aliases.to} de {aliases.total}</span>
                                <div className="flex space-x-1">
                                    {aliases.links.map((link, i) => (
                                        <button key={i} onClick={() => link.url && router.get(link.url)} disabled={!link.url}
                                            className={`px-3 py-1 text-sm rounded ${link.active ? 'bg-indigo-600 text-white' : link.url ? 'bg-white text-gray-700 hover:bg-gray-50 border' : 'bg-gray-100 text-gray-400'}`}
                                            dangerouslySetInnerHTML={{ __html: link.label }} />
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>

                    <div className="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-900">
                        <h3 className="font-medium mb-2">Como funciona</h3>
                        <ul className="text-xs space-y-1 list-disc ml-4">
                            <li>Durante o import, marcas da planilha são procuradas primeiro em <code className="bg-blue-100 px-1 rounded">product_brands</code> (match direto)</li>
                            <li>Se não achar, consulta aqui por <strong>alias</strong> ativo resolvido</li>
                            <li>Se ainda não achar, a ordem é <strong>rejeitada</strong></li>
                            <li><strong>Auto-detectar MS:</strong> procura "MS {'{nome}'}" em product_brands pra aliases pendentes (convenção Meia Sola)</li>
                            <li><strong>Criar Marca Manual:</strong> cria uma ProductBrand nova (com cigam_code=MANUAL-...) quando a marca não existe no CIGAM. Usado pra marcas históricas descontinuadas</li>
                        </ul>
                    </div>
                </div>
            </div>

            {showForm && (
                <AliasFormModal
                    alias={editing}
                    productBrands={productBrands}
                    onClose={closeForm}
                />
            )}

            {showManualBrand && (
                <ManualBrandModal
                    onClose={() => setShowManualBrand(false)}
                />
            )}

            <DeleteConfirmModal
                show={deleteTarget !== null}
                onClose={() => setDeleteTarget(null)}
                onConfirm={handleConfirmDelete}
                itemType="alias"
                itemName={deleteTarget?.source_name}
                details={deleteTarget ? [
                    { label: 'Marca oficial', value: deleteTarget.product_brand_name || '(pendente)' },
                ] : []}
                processing={deleting}
            />
        </>
    );
}

function AliasFormModal({ alias, productBrands, onClose }) {
    const isEdit = !!alias;
    const form = useForm({
        source_name: alias?.source_name || '',
        product_brand_id: alias?.product_brand_id || '',
        is_active: alias?.is_active ?? true,
        notes: alias?.notes || '',
    });

    const handleSubmit = () => {
        if (isEdit) {
            form.put(route('purchase-orders.brand-aliases.update', alias.id), { onSuccess: onClose });
        } else {
            form.post(route('purchase-orders.brand-aliases.store'), { onSuccess: onClose });
        }
    };

    return (
        <StandardModal
            show={true}
            onClose={onClose}
            title={isEdit ? `Editar — ${alias.source_name}` : 'Novo Alias'}
            headerColor={isEdit ? 'bg-yellow-600' : 'bg-indigo-600'}
            headerIcon={isEdit ? <PencilSquareIcon className="h-5 w-5" /> : <PlusIcon className="h-5 w-5" />}
            onSubmit={handleSubmit}
            footer={(
                <StandardModal.Footer
                    onCancel={onClose}
                    onSubmit="submit"
                    submitLabel={isEdit ? 'Salvar' : 'Criar'}
                    submitColor={isEdit ? 'bg-yellow-600 hover:bg-yellow-700' : undefined}
                    processing={form.processing}
                />
            )}
        >
            <FormSection title="Mapeamento" cols={1}>
                {!isEdit && (
                    <div>
                        <InputLabel value="Nome da planilha *" />
                        <TextInput className="mt-1 w-full uppercase" value={form.data.source_name}
                            onChange={(e) => form.setData('source_name', e.target.value.toUpperCase())}
                            placeholder="Ex: DIAN PATRIS" required />
                        <p className="mt-1 text-xs text-gray-500">Como o nome da marca aparece na planilha</p>
                        <InputError message={form.errors.source_name} className="mt-1" />
                    </div>
                )}
                <div>
                    <InputLabel value="Marca oficial (CIGAM)" />
                    <select value={form.data.product_brand_id || ''}
                        onChange={(e) => form.setData('product_brand_id', e.target.value || null)}
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="">— Deixar pendente —</option>
                        {productBrands.map((pb) => (
                            <option key={pb.id} value={pb.id}>
                                {pb.name} {pb.cigam_code && `(${pb.cigam_code})`}
                            </option>
                        ))}
                    </select>
                    <p className="mt-1 text-xs text-gray-500">
                        Se deixar pendente, ordens com esse nome serão rejeitadas no import
                    </p>
                    <InputError message={form.errors.product_brand_id} className="mt-1" />
                </div>
                <div>
                    <InputLabel value="Situação" />
                    <select value={form.data.is_active ? 'true' : 'false'}
                        onChange={(e) => form.setData('is_active', e.target.value === 'true')}
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="true">Ativo</option>
                        <option value="false">Inativo</option>
                    </select>
                </div>
                <div>
                    <InputLabel value="Notas" />
                    <textarea rows={2} value={form.data.notes}
                        onChange={(e) => form.setData('notes', e.target.value)}
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        placeholder="Opcional — contexto ou decisão de mapeamento" />
                    <InputError message={form.errors.notes} className="mt-1" />
                </div>
            </FormSection>
        </StandardModal>
    );
}

function ManualBrandModal({ onClose }) {
    const form = useForm({
        source_name: '',
        brand_name: '',
    });

    const handleSubmit = () => {
        form.post(route('purchase-orders.brand-aliases.create-manual-brand'), { onSuccess: onClose });
    };

    return (
        <StandardModal
            show={true}
            onClose={onClose}
            title="Criar Marca Manual"
            subtitle="Usar apenas quando a marca não existe no catálogo CIGAM"
            headerColor="bg-green-600"
            headerIcon={<TagIcon className="h-5 w-5" />}
            onSubmit={handleSubmit}
            footer={(
                <StandardModal.Footer
                    onCancel={onClose}
                    onSubmit="submit"
                    submitLabel="Criar Marca + Alias"
                    submitColor="bg-green-600 hover:bg-green-700"
                    processing={form.processing}
                />
            )}
        >
            <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mb-4 text-xs text-yellow-800">
                <strong>Atenção:</strong> isso cria uma nova entrada em <code className="bg-yellow-100 px-1 rounded">product_brands</code> com código <code className="bg-yellow-100 px-1 rounded">MANUAL-...</code>. Use apenas pra marcas históricas descontinuadas que não existem mais no CIGAM. Se a marca existe no CIGAM com nome diferente, use um alias comum em vez disso.
            </div>

            <FormSection title="Marca manual" cols={1}>
                <div>
                    <InputLabel value="Nome da planilha *" />
                    <TextInput className="mt-1 w-full uppercase" value={form.data.source_name}
                        onChange={(e) => form.setData('source_name', e.target.value.toUpperCase())}
                        placeholder="Ex: ROSA DO CAMPO" required />
                    <InputError message={form.errors.source_name} className="mt-1" />
                </div>
                <div>
                    <InputLabel value="Nome oficial da marca *" />
                    <TextInput className="mt-1 w-full" value={form.data.brand_name}
                        onChange={(e) => form.setData('brand_name', e.target.value)}
                        placeholder="Ex: Rosa do Campo" required />
                    <p className="mt-1 text-xs text-gray-500">Como ficará salvo em product_brands</p>
                    <InputError message={form.errors.brand_name} className="mt-1" />
                </div>
            </FormSection>
        </StandardModal>
    );
}
