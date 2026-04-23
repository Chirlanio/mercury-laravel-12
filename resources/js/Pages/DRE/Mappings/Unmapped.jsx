import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { ExclamationTriangleIcon, Squares2X2Icon } from '@heroicons/react/24/outline';
import Button from '@/Components/Button';
import DataTable from '@/Components/DataTable';
import StatusBadge from '@/Components/Shared/StatusBadge';
import EmptyState from '@/Components/Shared/EmptyState';
import BulkAssignModal from '@/Components/DRE/BulkAssignModal';

/**
 * Fila de contas 3/4/5 sem mapping vigente — "to-do list" do time
 * financeiro após imports do ERP.
 *
 * Lista estática (sem paginação — volume típico é pequeno, algumas
 * dezenas após um import). Usa `DataTable` com `searchable=false` e
 * `selectable` para seleção múltipla + ação "Mapear em lote".
 */
export default function MappingsUnmapped({
    accounts,
    managementLines,
    costCenters,
    effective_on,
    can,
}) {
    const [selectedIds, setSelectedIds] = useState([]);
    const [bulkOpen, setBulkOpen] = useState(false);

    const groupLabel = (group) => {
        if (group === 3) return 'Receitas';
        if (group === 4) return 'Custos e Despesas';
        if (group === 5) return 'Resultado';
        return '—';
    };

    // DataTable espera shape paginado; como a lista é estática, montamos
    // um pseudo-paginator com total = N e sem links.
    const tableData = useMemo(
        () => ({
            data: accounts,
            links: [],
            per_page: accounts.length || 10,
            from: accounts.length > 0 ? 1 : 0,
            to: accounts.length,
            total: accounts.length,
            current_page: 1,
        }),
        [accounts]
    );

    const columns = useMemo(
        () => [
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
                key: 'account_group',
                label: 'Grupo',
                render: (account) => (
                    <StatusBadge
                        variant={account.account_group === 3 ? 'success' : 'warning'}
                        dot
                    >
                        {account.account_group_label ||
                            groupLabel(account.account_group)}
                    </StatusBadge>
                ),
            },
            {
                key: 'default_management_class',
                label: 'Sugestão (plano gerencial)',
                render: (account) =>
                    account.default_management_class ? (
                        <span className="text-sm text-gray-700">
                            <span className="font-mono text-xs text-gray-500 mr-1">
                                {account.default_management_class.code}
                            </span>
                            {account.default_management_class.name}
                        </span>
                    ) : (
                        <span className="text-gray-400 text-sm">sem sugestão</span>
                    ),
            },
        ],
        []
    );

    return (
        <>
            <Head title="Não Mapeadas — DRE" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:justify-between sm:items-center">
                        <div className="flex items-start gap-3 sm:items-center">
                            <ExclamationTriangleIcon className="h-7 w-7 text-yellow-500 flex-shrink-0" />
                            <div>
                                <h1 className="text-2xl font-semibold text-gray-900">
                                    Contas Não Mapeadas
                                </h1>
                                <p className="text-sm text-gray-600 mt-1">
                                    Analíticas de Receitas / Custos / Resultado sem mapeamento
                                    vigente em <strong>{effective_on}</strong>.
                                </p>
                            </div>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            <Link href={route('dre.mappings.index')}>
                                <Button variant="secondary" size="sm">
                                    Voltar ao mapeamento completo
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

                    {accounts.length === 0 ? (
                        <EmptyState
                            title="Nada pendente"
                            description="Todas as contas analíticas de resultado estão mapeadas."
                        />
                    ) : (
                        <DataTable
                            columns={columns}
                            data={tableData}
                            searchable={false}
                            emptyMessage="Nenhuma conta pendente."
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
                defaultEffectiveFrom={effective_on}
            />
        </>
    );
}
