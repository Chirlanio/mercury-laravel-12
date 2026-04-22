import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import {
    ExclamationTriangleIcon,
    DocumentIcon,
    ArrowPathIcon,
    Squares2X2Icon,
    TrashIcon,
} from '@heroicons/react/24/outline';
import Button from '@/Components/Button';
import DataTable from '@/Components/DataTable';
import ActionButtons from '@/Components/ActionButtons';
import Checkbox from '@/Components/Checkbox';
import EmptyState from '@/Components/Shared/EmptyState';
import ManagementLinePicker from '@/Components/DRE/ManagementLinePicker';
import BulkAssignModal from '@/Components/DRE/BulkAssignModal';

/**
 * Tela central de mapeamento conta contábil → linha gerencial da DRE.
 *
 * Usa `DataTable` do projeto (busca embutida via query param `search`,
 * pagination e `selectable`). Os filtros de domínio (grupo contábil, linha
 * gerencial, só não-mapeadas) ficam num painel separado acima — DataTable
 * não sabe sobre filtros específicos de DRE.
 *
 * Edição inline: selects por linha atualizam o mapping (PUT/POST) via
 * `router` com `preserveScroll`. Sem modais — mapping é operação granular
 * de ajuste contínuo, não CRUD discreto.
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
        account_group: filters.account_group ?? '',
        cost_center_id: filters.cost_center_id ?? '',
        management_line_id: filters.management_line_id ?? '',
        only_unmapped: filters.only_unmapped ?? false,
    });
    const [selectedIds, setSelectedIds] = useState([]);
    const [bulkOpen, setBulkOpen] = useState(false);

    const ccLabelById = useMemo(() => {
        const map = {};
        (costCenters || []).forEach((cc) => {
            map[cc.id] = cc.label || `${cc.code} — ${cc.name}`;
        });
        return map;
    }, [costCenters]);

    const lineLabelById = useMemo(() => {
        const map = {};
        (managementLines || []).forEach((l) => {
            map[l.id] = l.label;
        });
        return map;
    }, [managementLines]);

    const applyFilters = () => {
        router.get(
            route('dre.mappings.index'),
            { ...buildFilterParams(filters), ...localFilters },
            {
                preserveScroll: true,
                preserveState: true,
            }
        );
    };

    const clearFilters = () => {
        const empty = {
            account_group: '',
            cost_center_id: '',
            management_line_id: '',
            only_unmapped: false,
        };
        setLocalFilters(empty);
        // Preserva só search (DataTable controla) e per_page.
        const kept = {};
        if (filters.search) kept.search = filters.search;
        if (filters.per_page) kept.per_page = filters.per_page;
        router.get(route('dre.mappings.index'), kept, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const toggleOnlyUnmapped = (checked) => {
        const next = { ...localFilters, only_unmapped: checked };
        setLocalFilters(next);
        router.get(
            route('dre.mappings.index'),
            { ...buildFilterParams(filters), ...next },
            { preserveScroll: true, preserveState: true }
        );
    };

    const updateMapping = (account, patch) => {
        // Patch é { dre_management_line_id?, cost_center_id? }.
        if (account.mapping) {
            router.put(
                route('dre.mappings.update', account.mapping.id),
                { ...patch },
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

    // Flatten para o formato esperado pelo DataTable.
    const tableData = useMemo(
        () => ({
            data: accounts.data,
            links: accounts.links,
            per_page: accounts.meta?.per_page ?? 25,
            from: accounts.meta?.from ?? 0,
            to: accounts.meta?.to ?? 0,
            total: accounts.meta?.total ?? 0,
            current_page: accounts.meta?.current_page ?? 1,
        }),
        [accounts]
    );

    const columns = useMemo(() => {
        const cols = [
            {
                key: 'account',
                label: 'Conta',
                render: (account) => {
                    const indentPx = (account.classification_level || 0) * 12;
                    return (
                        <div
                            className="flex items-center gap-2"
                            style={{ paddingLeft: `${indentPx}px` }}
                        >
                            <span className="font-mono text-xs text-gray-500">
                                {account.code}
                            </span>
                            <span className="text-gray-900">{account.name}</span>
                        </div>
                    );
                },
            },
            {
                key: 'type',
                label: 'Tipo',
                render: () => (
                    <DocumentIcon
                        className="h-4 w-4 text-gray-400"
                        title="Analítica"
                    />
                ),
            },
            {
                key: 'management_line',
                label: 'Linha Gerencial',
                render: (account) =>
                    can?.manage ? (
                        <ManagementLinePicker
                            lines={managementLines}
                            value={account.mapping?.dre_management_line_id ?? null}
                            onChange={(v) =>
                                updateMapping(account, { dre_management_line_id: v })
                            }
                            placeholder="— não mapeada —"
                        />
                    ) : account.mapping ? (
                        <span className="text-gray-900">
                            {lineLabelById[account.mapping.dre_management_line_id] || '—'}
                        </span>
                    ) : (
                        <span className="text-red-600 text-xs font-medium">Pendente</span>
                    ),
            },
            {
                key: 'cost_center',
                label: 'Centro de Custo',
                render: (account) => {
                    const mapped = account.mapping !== null;
                    return can?.manage && mapped ? (
                        <select
                            value={account.mapping.cost_center_id ?? ''}
                            onChange={(e) =>
                                updateMapping(account, {
                                    cost_center_id:
                                        e.target.value === ''
                                            ? null
                                            : Number(e.target.value),
                                })
                            }
                            className="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm w-full"
                        >
                            <option value="">(coringa — qualquer CC)</option>
                            {costCenters.map((cc) => (
                                <option key={cc.id} value={cc.id}>
                                    {cc.label || `${cc.code} — ${cc.name}`}
                                </option>
                            ))}
                        </select>
                    ) : account.mapping?.cost_center_id ? (
                        <span className="text-gray-700 text-sm">
                            {ccLabelById[account.mapping.cost_center_id]}
                        </span>
                    ) : (
                        <span className="text-gray-400 text-sm">—</span>
                    );
                },
            },
        ];

        if (can?.manage) {
            cols.push({
                key: 'actions',
                label: 'Ações',
                render: (account) =>
                    account.mapping ? (
                        <ActionButtons>
                            <ActionButtons.Custom
                                variant="danger"
                                icon={TrashIcon}
                                title="Remover mapeamento"
                                onClick={() => removeMapping(account)}
                            />
                        </ActionButtons>
                    ) : (
                        <span className="text-gray-300 text-sm">—</span>
                    ),
            });
        }

        return cols;
    }, [can, managementLines, costCenters, ccLabelById, lineLabelById]);

    return (
        <>
            <Head title="Mapeamento da DRE" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="mb-6 flex justify-between items-center">
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
                                <Button
                                    variant="warning"
                                    size="sm"
                                    icon={ExclamationTriangleIcon}
                                >
                                    Não Mapeadas
                                </Button>
                            </Link>
                            {can?.manage && selectedIds.length > 0 && (
                                <Button
                                    variant="primary"
                                    size="sm"
                                    icon={Squares2X2Icon}
                                    onClick={() => setBulkOpen(true)}
                                >
                                    Mapear em lote ({selectedIds.length})
                                </Button>
                            )}
                        </div>
                    </div>

                    {/* Filtros de domínio (grupo + linha + só não-mapeadas) */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
                            <div>
                                <label className="block text-xs font-medium text-gray-600 mb-1">
                                    Grupo contábil
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
                                    Linha gerencial
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

                            <div className="flex items-end">
                                <label className="inline-flex items-center gap-2 text-sm">
                                    <Checkbox
                                        checked={localFilters.only_unmapped}
                                        onChange={(e) => toggleOnlyUnmapped(e.target.checked)}
                                    />
                                    Só <strong>não mapeadas</strong>
                                </label>
                            </div>

                            <div className="flex items-end gap-2">
                                <Button
                                    variant="primary"
                                    size="sm"
                                    onClick={applyFilters}
                                >
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
                    </div>

                    {/* Tabela */}
                    {accounts.data.length === 0 ? (
                        <EmptyState
                            title="Nenhuma conta encontrada"
                            description="Ajuste os filtros ou verifique se há contas analíticas de grupos 3, 4 e 5 cadastradas."
                        />
                    ) : (
                        <DataTable
                            columns={columns}
                            data={tableData}
                            searchPlaceholder="Código, código reduzido ou nome"
                            emptyMessage="Nenhuma conta encontrada."
                            selectable={can?.manage}
                            selectedIds={selectedIds}
                            onSelectionChange={setSelectedIds}
                        />
                    )}
                </div>
            </div>

            <BulkAssignModal
                show={bulkOpen}
                onClose={() => setBulkOpen(false)}
                selectedAccountIds={selectedIds}
                managementLines={managementLines}
                costCenters={costCenters}
            />
        </>
    );
}

/**
 * Remove chaves null/undefined/empty — evita sujar a URL após apply.
 */
function buildFilterParams(filters) {
    const out = {};
    if (filters.search) out.search = filters.search;
    if (filters.per_page) out.per_page = filters.per_page;
    return out;
}
