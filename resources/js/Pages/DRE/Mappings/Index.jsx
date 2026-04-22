import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import {
    MagnifyingGlassIcon,
    ExclamationTriangleIcon,
    DocumentIcon,
    FolderIcon,
    ArrowPathIcon,
    Squares2X2Icon,
} from '@heroicons/react/24/outline';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Button from '@/Components/Button';
import TextInput from '@/Components/TextInput';
import Checkbox from '@/Components/Checkbox';
import StatusBadge from '@/Components/Shared/StatusBadge';
import EmptyState from '@/Components/Shared/EmptyState';
import ManagementLinePicker from '@/Components/DRE/ManagementLinePicker';
import BulkAssignModal from '@/Components/DRE/BulkAssignModal';

/**
 * Tela central de mapeamento conta → linha gerencial.
 *
 * Layout:
 *   - Filtros no topo (busca, grupo, CC, linha gerencial, "só não mapeadas")
 *   - Tabela com contas analíticas e mapping vigente embutido
 *   - Edição inline via select da linha + select de CC por linha
 *   - Seleção múltipla com bulk assign via modal
 */
export default function MappingsIndex({
    accounts,
    filters,
    managementLines,
    costCenters,
    accountGroups,
    can,
}) {
    const [localFilters, setLocalFilters] = useState({
        search: filters.search ?? '',
        account_group: filters.account_group ?? '',
        cost_center_id: filters.cost_center_id ?? '',
        management_line_id: filters.management_line_id ?? '',
        only_unmapped: filters.only_unmapped ?? false,
    });
    const [selectedIds, setSelectedIds] = useState(new Set());
    const [bulkOpen, setBulkOpen] = useState(false);

    const lineLabelById = useMemo(() => {
        const map = {};
        (managementLines || []).forEach((l) => {
            map[l.id] = l.label;
        });
        return map;
    }, [managementLines]);

    const ccLabelById = useMemo(() => {
        const map = {};
        (costCenters || []).forEach((cc) => {
            map[cc.id] = cc.label || `${cc.code} — ${cc.name}`;
        });
        return map;
    }, [costCenters]);

    const applyFilters = () => {
        router.get(route('dre.mappings.index'), localFilters, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const clearFilters = () => {
        const empty = {
            search: '',
            account_group: '',
            cost_center_id: '',
            management_line_id: '',
            only_unmapped: false,
        };
        setLocalFilters(empty);
        router.get(route('dre.mappings.index'), empty, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const toggleRow = (id) => {
        const next = new Set(selectedIds);
        if (next.has(id)) next.delete(id);
        else next.add(id);
        setSelectedIds(next);
    };

    const toggleAll = () => {
        if (selectedIds.size === accounts.data.length) {
            setSelectedIds(new Set());
        } else {
            setSelectedIds(new Set(accounts.data.map((a) => a.id)));
        }
    };

    const updateMapping = (account, patch) => {
        // Patch é { dre_management_line_id?, cost_center_id? }.
        if (account.mapping) {
            router.put(
                route('dre.mappings.update', account.mapping.id),
                {
                    ...patch,
                },
                { preserveScroll: true, preserveState: true }
            );
        } else if (patch.dre_management_line_id) {
            router.post(
                route('dre.mappings.store'),
                {
                    chart_of_account_id: account.id,
                    cost_center_id: patch.cost_center_id ?? null,
                    dre_management_line_id: patch.dre_management_line_id,
                    effective_from: new Date().toISOString().slice(0, 10),
                },
                { preserveScroll: true, preserveState: true }
            );
        }
    };

    const removeMapping = (account) => {
        if (!account.mapping) return;
        router.delete(route('dre.mappings.destroy', account.mapping.id), {
            preserveScroll: true,
            preserveState: true,
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Mapeamento da DRE" />

            <div className="py-8">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between mb-6">
                        <div>
                            <h1 className="text-2xl font-semibold text-gray-900">
                                Mapeamento da DRE
                            </h1>
                            <p className="text-sm text-gray-600 mt-1">
                                De-para entre Plano Contábil (contas analíticas de Receitas,
                                Custos/Despesas e Resultado) e Linha Gerencial da DRE.
                            </p>
                        </div>
                        <div className="flex gap-2">
                            <Link href={route('dre.mappings.unmapped')}>
                                <Button variant="warning" size="sm" icon={ExclamationTriangleIcon}>
                                    Não Mapeadas
                                </Button>
                            </Link>
                            {can?.manage && selectedIds.size > 0 && (
                                <Button
                                    variant="primary"
                                    size="sm"
                                    icon={Squares2X2Icon}
                                    onClick={() => setBulkOpen(true)}
                                >
                                    Mapear em lote ({selectedIds.size})
                                </Button>
                            )}
                        </div>
                    </div>

                    {/* Filtros */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-5 gap-3">
                            <div className="md:col-span-2">
                                <label className="block text-xs font-medium text-gray-600 mb-1">
                                    Busca
                                </label>
                                <div className="relative">
                                    <MagnifyingGlassIcon className="absolute top-2.5 left-3 h-4 w-4 text-gray-400" />
                                    <TextInput
                                        placeholder="Código, código reduzido ou nome"
                                        value={localFilters.search}
                                        onChange={(e) =>
                                            setLocalFilters({
                                                ...localFilters,
                                                search: e.target.value,
                                            })
                                        }
                                        onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                                        className="pl-9 w-full"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-gray-600 mb-1">
                                    Grupo
                                </label>
                                <select
                                    value={localFilters.account_group}
                                    onChange={(e) =>
                                        setLocalFilters({
                                            ...localFilters,
                                            account_group: e.target.value,
                                        })
                                    }
                                    className="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm w-full"
                                >
                                    <option value="">Todos (3, 4, 5)</option>
                                    {accountGroups.map((g) => (
                                        <option key={g.value} value={g.value}>
                                            {g.label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="block text-xs font-medium text-gray-600 mb-1">
                                    Linha Gerencial
                                </label>
                                <ManagementLinePicker
                                    lines={managementLines}
                                    value={
                                        localFilters.management_line_id === ''
                                            ? null
                                            : Number(localFilters.management_line_id)
                                    }
                                    onChange={(v) =>
                                        setLocalFilters({
                                            ...localFilters,
                                            management_line_id: v ?? '',
                                        })
                                    }
                                    placeholder="Todas"
                                />
                            </div>

                            <div className="flex items-end gap-2">
                                <Button variant="primary" size="sm" onClick={applyFilters}>
                                    Filtrar
                                </Button>
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    onClick={clearFilters}
                                    icon={ArrowPathIcon}
                                >
                                    Limpar
                                </Button>
                            </div>
                        </div>

                        <div className="mt-3 flex items-center gap-4">
                            <label className="inline-flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={localFilters.only_unmapped}
                                    onChange={(e) => {
                                        const checked = e.target.checked;
                                        const next = { ...localFilters, only_unmapped: checked };
                                        setLocalFilters(next);
                                        router.get(route('dre.mappings.index'), next, {
                                            preserveScroll: true,
                                            preserveState: true,
                                        });
                                    }}
                                />
                                Mostrar só <strong>não mapeadas</strong>
                            </label>
                        </div>
                    </div>

                    {/* Tabela */}
                    {accounts.data.length === 0 ? (
                        <EmptyState
                            title="Nenhuma conta encontrada"
                            description="Ajuste os filtros ou verifique se há contas analíticas de grupos 3, 4 e 5 cadastradas."
                        />
                    ) : (
                        <div className="bg-white shadow-sm rounded-lg overflow-hidden">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        {can?.manage && (
                                            <th className="px-3 py-2 w-10">
                                                <input
                                                    type="checkbox"
                                                    checked={
                                                        selectedIds.size ===
                                                            accounts.data.length &&
                                                        accounts.data.length > 0
                                                    }
                                                    onChange={toggleAll}
                                                    className="rounded border-gray-300"
                                                />
                                            </th>
                                        )}
                                        <th className="px-3 py-2 text-left text-xs font-semibold text-gray-600">
                                            Conta
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold text-gray-600 w-14">
                                            Tipo
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold text-gray-600 w-60">
                                            Linha Gerencial
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold text-gray-600 w-60">
                                            Centro de Custo
                                        </th>
                                        {can?.manage && (
                                            <th className="px-3 py-2 text-right text-xs font-semibold text-gray-600 w-20">
                                                Ações
                                            </th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {accounts.data.map((account) => {
                                        const mapped = account.mapping !== null;
                                        const indentPx = (account.classification_level || 0) * 12;
                                        return (
                                            <tr key={account.id}>
                                                {can?.manage && (
                                                    <td className="px-3 py-2">
                                                        <input
                                                            type="checkbox"
                                                            checked={selectedIds.has(account.id)}
                                                            onChange={() => toggleRow(account.id)}
                                                            className="rounded border-gray-300"
                                                        />
                                                    </td>
                                                )}
                                                <td
                                                    className="px-3 py-2 text-sm"
                                                    style={{ paddingLeft: `${12 + indentPx}px` }}
                                                >
                                                    <div className="flex items-center gap-2">
                                                        <span className="font-mono text-xs text-gray-500">
                                                            {account.code}
                                                        </span>
                                                        <span className="text-gray-900">
                                                            {account.name}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="px-3 py-2">
                                                    <DocumentIcon
                                                        className="h-4 w-4 text-gray-400"
                                                        title="Analítica"
                                                    />
                                                </td>
                                                <td className="px-3 py-2 text-sm">
                                                    {can?.manage ? (
                                                        <ManagementLinePicker
                                                            lines={managementLines}
                                                            value={
                                                                account.mapping
                                                                    ?.dre_management_line_id ??
                                                                null
                                                            }
                                                            onChange={(v) =>
                                                                updateMapping(account, {
                                                                    dre_management_line_id: v,
                                                                })
                                                            }
                                                            placeholder="— não mapeada —"
                                                        />
                                                    ) : mapped ? (
                                                        <StatusBadge variant="success" dot>
                                                            {lineLabelById[
                                                                account.mapping
                                                                    .dre_management_line_id
                                                            ] || '—'}
                                                        </StatusBadge>
                                                    ) : (
                                                        <StatusBadge variant="danger" dot>
                                                            Pendente
                                                        </StatusBadge>
                                                    )}
                                                </td>
                                                <td className="px-3 py-2 text-sm">
                                                    {can?.manage && mapped ? (
                                                        <select
                                                            value={
                                                                account.mapping.cost_center_id ??
                                                                ''
                                                            }
                                                            onChange={(e) =>
                                                                updateMapping(account, {
                                                                    cost_center_id:
                                                                        e.target.value === ''
                                                                            ? null
                                                                            : Number(
                                                                                  e.target.value
                                                                              ),
                                                                })
                                                            }
                                                            className="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm w-full"
                                                        >
                                                            <option value="">
                                                                (coringa — qualquer CC)
                                                            </option>
                                                            {costCenters.map((cc) => (
                                                                <option key={cc.id} value={cc.id}>
                                                                    {cc.label ||
                                                                        `${cc.code} — ${cc.name}`}
                                                                </option>
                                                            ))}
                                                        </select>
                                                    ) : account.mapping?.cost_center_id ? (
                                                        <span className="text-gray-700">
                                                            {
                                                                ccLabelById[
                                                                    account.mapping.cost_center_id
                                                                ]
                                                            }
                                                        </span>
                                                    ) : (
                                                        <span className="text-gray-400">—</span>
                                                    )}
                                                </td>
                                                {can?.manage && (
                                                    <td className="px-3 py-2 text-right text-sm">
                                                        {mapped && (
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    removeMapping(account)
                                                                }
                                                                className="text-red-600 hover:text-red-800 text-xs"
                                                                title="Remover mapeamento"
                                                            >
                                                                Remover
                                                            </button>
                                                        )}
                                                    </td>
                                                )}
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>

                            {/* Paginação simples */}
                            {accounts.meta.last_page > 1 && (
                                <div className="px-4 py-3 border-t flex items-center justify-between text-sm text-gray-600">
                                    <span>
                                        Página {accounts.meta.current_page} de{' '}
                                        {accounts.meta.last_page}
                                    </span>
                                    <div className="flex gap-2">
                                        {accounts.links.map((link, idx) => (
                                            <button
                                                key={idx}
                                                disabled={!link.url || link.active}
                                                onClick={() =>
                                                    link.url &&
                                                    router.get(link.url, {}, {
                                                        preserveScroll: true,
                                                        preserveState: true,
                                                    })
                                                }
                                                className={`px-2 py-1 rounded ${
                                                    link.active
                                                        ? 'bg-indigo-600 text-white'
                                                        : link.url
                                                        ? 'text-indigo-600 hover:bg-indigo-50'
                                                        : 'text-gray-300'
                                                }`}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>

            <BulkAssignModal
                show={bulkOpen}
                onClose={() => setBulkOpen(false)}
                selectedAccountIds={Array.from(selectedIds)}
                managementLines={managementLines}
                costCenters={costCenters}
            />
        </AuthenticatedLayout>
    );
}
