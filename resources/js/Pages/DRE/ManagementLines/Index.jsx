import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import {
    PlusIcon,
    PencilIcon,
    TrashIcon,
    ArrowUpIcon,
    ArrowDownIcon,
    ArrowsUpDownIcon,
    CheckCircleIcon,
} from '@heroicons/react/24/outline';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Button from '@/Components/Button';
import StatusBadge from '@/Components/Shared/StatusBadge';
import EmptyState from '@/Components/Shared/EmptyState';
import DeleteConfirmModal from '@/Components/Shared/DeleteConfirmModal';

/**
 * Listagem ordenada das linhas da DRE gerencial.
 *
 * Ordem: sort_order ASC + is_subtotal ASC (analíticas antes de subtotais no mesmo sort_order).
 * Reorder: setas ↑/↓ + botão "Salvar ordem" envia POST /dre/management-lines/reorder.
 * Os testes e o projeto não têm biblioteca de drag-and-drop — setas como fallback são suficientes.
 */
export default function ManagementLinesIndex({ lines, can, natureOptions }) {
    const { flash } = usePage().props;
    const [ordered, setOrdered] = useState(() => [...lines]);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [isDirty, setIsDirty] = useState(false);

    const natureLabel = useMemo(() => {
        const map = {};
        (natureOptions || []).forEach((opt) => {
            map[opt.value] = opt.label;
        });
        return map;
    }, [natureOptions]);

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

    const confirmDelete = () => {
        if (!deleteTarget) return;
        router.delete(route('dre.management-lines.destroy', deleteTarget.id), {
            preserveScroll: true,
            onSuccess: () => setDeleteTarget(null),
        });
    };

    const natureBadgeVariant = (nature) => {
        if (nature === 'revenue') return 'success';
        if (nature === 'expense') return 'danger';
        return 'info';
    };

    return (
        <AuthenticatedLayout>
            <Head title="Plano Gerencial da DRE" />

            <div className="py-8">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between mb-6">
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
                                <Link href={route('dre.management-lines.create')}>
                                    <Button variant="primary" size="sm" icon={PlusIcon}>
                                        Nova linha
                                    </Button>
                                </Link>
                            </div>
                        )}
                    </div>

                    {ordered.length === 0 ? (
                        <EmptyState
                            title="Nenhuma linha cadastrada"
                            description="Comece criando as linhas da DRE executiva."
                        />
                    ) : (
                        <div className="bg-white shadow-sm rounded-lg overflow-hidden">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-3 py-2 text-left text-xs font-semibold text-gray-600 w-12">
                                            #
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold text-gray-600 w-20">
                                            Ordem
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold text-gray-600 w-24">
                                            Código
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold text-gray-600">
                                            Rótulo
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold text-gray-600 w-28">
                                            Natureza
                                        </th>
                                        <th className="px-3 py-2 text-center text-xs font-semibold text-gray-600 w-20">
                                            Subtotal
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold text-gray-600 w-28">
                                            Acumula até
                                        </th>
                                        <th className="px-3 py-2 text-center text-xs font-semibold text-gray-600 w-20">
                                            Ativa
                                        </th>
                                        {can?.manage && (
                                            <th className="px-3 py-2 text-right text-xs font-semibold text-gray-600 w-36">
                                                Ações
                                            </th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {ordered.map((line, idx) => (
                                        <tr
                                            key={line.id}
                                            className={
                                                line.is_subtotal
                                                    ? 'bg-gray-50 font-semibold'
                                                    : ''
                                            }
                                        >
                                            <td className="px-3 py-2 text-sm text-gray-500 tabular-nums">
                                                {idx + 1}
                                            </td>
                                            <td className="px-3 py-2 text-sm text-gray-700 tabular-nums">
                                                {line.sort_order}
                                            </td>
                                            <td className="px-3 py-2 text-sm text-gray-700 font-mono">
                                                {line.code}
                                            </td>
                                            <td className="px-3 py-2 text-sm text-gray-900">
                                                {line.level_1}
                                            </td>
                                            <td className="px-3 py-2 text-sm">
                                                <StatusBadge
                                                    variant={natureBadgeVariant(line.nature)}
                                                    dot
                                                >
                                                    {natureLabel[line.nature] || line.nature}
                                                </StatusBadge>
                                            </td>
                                            <td className="px-3 py-2 text-center">
                                                {line.is_subtotal ? (
                                                    <CheckCircleIcon className="h-5 w-5 text-indigo-600 inline" />
                                                ) : (
                                                    <span className="text-gray-300">—</span>
                                                )}
                                            </td>
                                            <td className="px-3 py-2 text-sm text-gray-700 tabular-nums">
                                                {line.accumulate_until_sort_order ?? '—'}
                                            </td>
                                            <td className="px-3 py-2 text-center">
                                                {line.is_active ? (
                                                    <StatusBadge variant="success" dot>
                                                        Sim
                                                    </StatusBadge>
                                                ) : (
                                                    <StatusBadge variant="gray" dot>
                                                        Não
                                                    </StatusBadge>
                                                )}
                                            </td>
                                            {can?.manage && (
                                                <td className="px-3 py-2 text-right">
                                                    <div className="flex justify-end gap-1">
                                                        <button
                                                            type="button"
                                                            title="Mover acima"
                                                            className="p-1 text-gray-500 hover:text-gray-700 disabled:opacity-30"
                                                            disabled={idx === 0}
                                                            onClick={() => move(idx, -1)}
                                                        >
                                                            <ArrowUpIcon className="h-4 w-4" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            title="Mover abaixo"
                                                            className="p-1 text-gray-500 hover:text-gray-700 disabled:opacity-30"
                                                            disabled={idx === ordered.length - 1}
                                                            onClick={() => move(idx, 1)}
                                                        >
                                                            <ArrowDownIcon className="h-4 w-4" />
                                                        </button>
                                                        <Link
                                                            href={route(
                                                                'dre.management-lines.edit',
                                                                line.id
                                                            )}
                                                            className="p-1 text-indigo-600 hover:text-indigo-800"
                                                            title="Editar"
                                                        >
                                                            <PencilIcon className="h-4 w-4" />
                                                        </Link>
                                                        <button
                                                            type="button"
                                                            className="p-1 text-red-600 hover:text-red-800"
                                                            title="Excluir"
                                                            onClick={() => setDeleteTarget(line)}
                                                        >
                                                            <TrashIcon className="h-4 w-4" />
                                                        </button>
                                                    </div>
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

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
        </AuthenticatedLayout>
    );
}
