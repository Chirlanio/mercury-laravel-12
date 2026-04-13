import { Head, router, Link } from '@inertiajs/react';
import { useState } from 'react';
import {
    BookOpenIcon,
    PlusIcon,
    PencilSquareIcon,
    TrashIcon,
    EyeIcon,
    HandThumbUpIcon,
    HandThumbDownIcon,
} from '@heroicons/react/24/outline';
import Button from '@/Components/Button';
import StatusBadge from '@/Components/Shared/StatusBadge';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import StandardModal from '@/Components/StandardModal';

/**
 * Knowledge Base article list / admin index. Follows the admin page
 * convention (see Permissions.jsx): max-w-5xl, ícone inline, responsive
 * typography.
 */
export default function Index({ articles, filters = {}, departments = [] }) {
    const [localFilters, setLocalFilters] = useState({
        search: filters.search || '',
        department_id: filters.department_id || '',
        published: filters.published ?? '',
    });
    const [deleteTarget, setDeleteTarget] = useState(null);

    const applyFilters = () => {
        router.get(route('helpdesk.articles.index'), localFilters, { preserveState: true });
    };

    const clearFilters = () => {
        setLocalFilters({ search: '', department_id: '', published: '' });
        router.get(route('helpdesk.articles.index'));
    };

    const handleDelete = () => {
        if (!deleteTarget) return;
        router.delete(route('helpdesk.articles.destroy', deleteTarget.id), {
            onFinish: () => setDeleteTarget(null),
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Knowledge Base" />
            <div className="py-6 sm:py-12">
                <div className="max-w-5xl mx-auto px-3 sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4 sm:mb-6">
                        <div>
                            <h1 className="text-xl sm:text-2xl font-bold text-gray-900 flex items-center gap-2">
                                <BookOpenIcon className="w-6 h-6 sm:w-7 sm:h-7 text-indigo-600 shrink-0" />
                                <span>Base de Conhecimento</span>
                            </h1>
                            <p className="text-xs sm:text-sm text-gray-500 mt-1">
                                Artigos de ajuda para reduzir abertura de chamados repetitivos.
                            </p>
                        </div>
                        <Link href={route('helpdesk.articles.create')}>
                            <Button variant="primary" icon={PlusIcon} size="sm" className="w-full sm:w-auto">
                                Novo artigo
                            </Button>
                        </Link>
                    </div>

                    {/* Filters */}
                    <div className="bg-white shadow-sm rounded-lg p-3 sm:p-4 mb-4 sm:mb-6">
                        <div className="grid grid-cols-1 sm:grid-cols-[1fr_200px_160px_auto] gap-2">
                            <div>
                                <InputLabel value="Buscar" />
                                <TextInput
                                    className="mt-1 w-full text-xs sm:text-sm"
                                    value={localFilters.search}
                                    onChange={e => setLocalFilters(p => ({ ...p, search: e.target.value }))}
                                    onKeyDown={e => e.key === 'Enter' && applyFilters()}
                                    placeholder="Título ou resumo"
                                />
                            </div>
                            <div>
                                <InputLabel value="Departamento" />
                                <select
                                    className="mt-1 w-full border-gray-300 rounded-lg text-xs sm:text-sm"
                                    value={localFilters.department_id}
                                    onChange={e => setLocalFilters(p => ({ ...p, department_id: e.target.value }))}
                                >
                                    <option value="">Todos</option>
                                    {departments.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
                                </select>
                            </div>
                            <div>
                                <InputLabel value="Status" />
                                <select
                                    className="mt-1 w-full border-gray-300 rounded-lg text-xs sm:text-sm"
                                    value={localFilters.published}
                                    onChange={e => setLocalFilters(p => ({ ...p, published: e.target.value }))}
                                >
                                    <option value="">Todos</option>
                                    <option value="1">Publicado</option>
                                    <option value="0">Rascunho</option>
                                </select>
                            </div>
                            <div className="flex items-end gap-2">
                                <Button variant="primary" size="sm" onClick={applyFilters}>Filtrar</Button>
                                <Button variant="light" size="sm" onClick={clearFilters}>Limpar</Button>
                            </div>
                        </div>
                    </div>

                    {/* List */}
                    <div className="bg-white shadow-sm rounded-lg overflow-hidden">
                        {articles.data.length === 0 ? (
                            <div className="px-4 sm:px-6 py-10 sm:py-12 text-center text-gray-500 text-sm">
                                <BookOpenIcon className="w-10 h-10 mx-auto text-gray-300 mb-2" />
                                Nenhum artigo ainda.
                                <p className="text-xs text-gray-400 mt-1">
                                    Crie artigos para resolver perguntas recorrentes antes que virem tickets.
                                </p>
                            </div>
                        ) : (
                            <>
                                {/* Desktop/tablet table */}
                                <table className="hidden sm:table w-full text-sm">
                                    <thead className="bg-gray-50 text-xs uppercase text-gray-500">
                                        <tr>
                                            <th className="px-4 lg:px-6 py-3 text-left">Título</th>
                                            <th className="px-4 lg:px-6 py-3 text-left hidden md:table-cell">Departamento</th>
                                            <th className="px-4 lg:px-6 py-3 text-left">Status</th>
                                            <th className="px-4 lg:px-6 py-3 text-left">Métricas</th>
                                            <th className="px-4 lg:px-6 py-3 text-right">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {articles.data.map(a => (
                                            <tr key={a.id} className="hover:bg-gray-50">
                                                <td className="px-4 lg:px-6 py-3">
                                                    <div className="font-medium text-gray-900">{a.title}</div>
                                                    {a.summary && (
                                                        <div className="text-xs text-gray-500 truncate max-w-xs">{a.summary}</div>
                                                    )}
                                                    <div className="text-xs text-gray-400">
                                                        atualizado em {a.updated_at}
                                                    </div>
                                                </td>
                                                <td className="px-4 lg:px-6 py-3 text-gray-700 hidden md:table-cell">
                                                    {a.department_name || '—'}
                                                    {a.category_name && (
                                                        <div className="text-xs text-gray-400">{a.category_name}</div>
                                                    )}
                                                </td>
                                                <td className="px-4 lg:px-6 py-3">
                                                    {a.is_published
                                                        ? <StatusBadge variant="success">Publicado</StatusBadge>
                                                        : <StatusBadge variant="gray">Rascunho</StatusBadge>}
                                                </td>
                                                <td className="px-4 lg:px-6 py-3 text-xs text-gray-500">
                                                    <div className="flex items-center gap-3">
                                                        <span className="flex items-center gap-1" title="Visualizações">
                                                            <EyeIcon className="w-3 h-3" /> {a.view_count}
                                                        </span>
                                                        <span className="flex items-center gap-1 text-green-600" title="Útil">
                                                            <HandThumbUpIcon className="w-3 h-3" /> {a.helpful_count}
                                                        </span>
                                                        <span className="flex items-center gap-1 text-red-600" title="Não útil">
                                                            <HandThumbDownIcon className="w-3 h-3" /> {a.not_helpful_count}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-4 lg:px-6 py-3 text-right">
                                                    <div className="flex justify-end gap-1">
                                                        <Link href={route('helpdesk.articles.edit', a.id)}>
                                                            <Button variant="light" size="xs" icon={PencilSquareIcon}>
                                                                <span className="hidden lg:inline">Editar</span>
                                                            </Button>
                                                        </Link>
                                                        <Button variant="danger" size="xs" icon={TrashIcon}
                                                            onClick={() => setDeleteTarget(a)}>
                                                            <span className="hidden lg:inline">Remover</span>
                                                        </Button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>

                                {/* Mobile cards */}
                                <ul className="sm:hidden divide-y divide-gray-100">
                                    {articles.data.map(a => (
                                        <li key={a.id} className="p-4">
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="min-w-0 flex-1">
                                                    <div className="font-medium text-gray-900 truncate">{a.title}</div>
                                                    <div className="text-xs text-gray-500 truncate">
                                                        {a.department_name || '—'}
                                                    </div>
                                                    <div className="flex items-center gap-2 mt-1">
                                                        {a.is_published
                                                            ? <StatusBadge variant="success" className="text-[10px]">Publicado</StatusBadge>
                                                            : <StatusBadge variant="gray" className="text-[10px]">Rascunho</StatusBadge>}
                                                        <span className="text-xs text-gray-400">
                                                            {a.view_count} views
                                                        </span>
                                                    </div>
                                                </div>
                                                <div className="flex flex-col gap-1">
                                                    <Link href={route('helpdesk.articles.edit', a.id)}>
                                                        <Button variant="light" size="xs" icon={PencilSquareIcon} />
                                                    </Link>
                                                    <Button variant="danger" size="xs" icon={TrashIcon}
                                                        onClick={() => setDeleteTarget(a)} />
                                                </div>
                                            </div>
                                        </li>
                                    ))}
                                </ul>

                                {/* Pagination footer */}
                                {articles.last_page > 1 && (
                                    <div className="px-4 sm:px-6 py-3 border-t border-gray-200 flex items-center justify-between text-xs sm:text-sm text-gray-500">
                                        <span>
                                            Página {articles.current_page} de {articles.last_page} · {articles.total} artigo(s)
                                        </span>
                                        <div className="flex gap-1">
                                            {articles.prev_page_url && (
                                                <Link href={articles.prev_page_url} preserveScroll>
                                                    <Button variant="light" size="xs">Anterior</Button>
                                                </Link>
                                            )}
                                            {articles.next_page_url && (
                                                <Link href={articles.next_page_url} preserveScroll>
                                                    <Button variant="light" size="xs">Próxima</Button>
                                                </Link>
                                            )}
                                        </div>
                                    </div>
                                )}
                            </>
                        )}
                    </div>
                </div>
            </div>

            {deleteTarget && (
                <StandardModal show onClose={() => setDeleteTarget(null)}
                    title="Remover artigo"
                    headerColor="bg-red-600"
                    headerIcon={<TrashIcon className="h-5 w-5" />}
                    maxWidth="md"
                    footer={<StandardModal.Footer onCancel={() => setDeleteTarget(null)}
                        onSubmit={handleDelete} submitLabel="Remover" submitVariant="danger" />}>
                    <p className="text-sm text-gray-700">
                        Remover o artigo <strong>{deleteTarget.title}</strong>? Esta ação pode ser desfeita (soft delete).
                    </p>
                </StandardModal>
            )}
        </>
    );
}
