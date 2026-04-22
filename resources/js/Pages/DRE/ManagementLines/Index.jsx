import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import {
    PlusIcon,
    ArrowUpIcon,
    ArrowDownIcon,
    ArrowsUpDownIcon,
    CheckCircleIcon,
} from '@heroicons/react/24/outline';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import StatusBadge from '@/Components/Shared/StatusBadge';
import EmptyState from '@/Components/Shared/EmptyState';
import DeleteConfirmModal from '@/Components/Shared/DeleteConfirmModal';

/**
 * Listagem ordenada das linhas da DRE gerencial.
 *
 * Reorder é client-side (setas ↑/↓ em cada linha + botão "Salvar ordem" que
 * envia POST /dre/management-lines/reorder). Sem busca/paginação porque o
 * volume é pequeno (≈20 linhas) e o reorder exige tudo visível ao mesmo
 * tempo. Por isso a tabela é renderizada "raw" — usa-se `DataTable` em
 * listagens paginadas do servidor.
 *
 * Visualmente espelha o shell do `DataTable` (`bg-white shadow-sm rounded-lg`)
 * e usa `ActionButtons` + `ActionButtons.Custom` na coluna de Ações para
 * consistência com o resto do sistema.
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
                                                className={
                                                    line.is_subtotal ? 'bg-gray-50' : ''
                                                }
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
                                                        className={
                                                            line.is_subtotal
                                                                ? 'font-semibold'
                                                                : ''
                                                        }
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
                                                            onEdit={() =>
                                                                router.visit(
                                                                    route(
                                                                        'dre.management-lines.edit',
                                                                        line.id
                                                                    )
                                                                )
                                                            }
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
