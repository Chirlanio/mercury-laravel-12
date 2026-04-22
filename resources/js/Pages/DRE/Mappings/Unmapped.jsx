import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ExclamationTriangleIcon, Squares2X2Icon } from '@heroicons/react/24/outline';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Button from '@/Components/Button';
import StatusBadge from '@/Components/Shared/StatusBadge';
import EmptyState from '@/Components/Shared/EmptyState';
import BulkAssignModal from '@/Components/DRE/BulkAssignModal';

/**
 * Fila dedicada de contas 3/4/5 sem mapping vigente.
 * É a "to-do list" do time financeiro após imports do ERP.
 */
export default function MappingsUnmapped({
    accounts,
    managementLines,
    costCenters,
    effective_on,
    can,
}) {
    const [selectedIds, setSelectedIds] = useState(new Set());
    const [bulkOpen, setBulkOpen] = useState(false);

    const toggleRow = (id) => {
        const next = new Set(selectedIds);
        if (next.has(id)) next.delete(id);
        else next.add(id);
        setSelectedIds(next);
    };

    const toggleAll = () => {
        if (selectedIds.size === accounts.length) {
            setSelectedIds(new Set());
        } else {
            setSelectedIds(new Set(accounts.map((a) => a.id)));
        }
    };

    const groupLabel = (group) => {
        if (group === 3) return 'Receitas';
        if (group === 4) return 'Custos e Despesas';
        if (group === 5) return 'Resultado';
        return '—';
    };

    return (
        <AuthenticatedLayout>
            <Head title="Não Mapeadas — DRE" />

            <div className="py-8">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between mb-6">
                        <div className="flex items-center gap-3">
                            <ExclamationTriangleIcon className="h-7 w-7 text-yellow-500" />
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

                        <div className="flex gap-2">
                            <Link href={route('dre.mappings.index')}>
                                <Button variant="secondary" size="sm">
                                    Voltar ao mapeamento completo
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

                    {accounts.length === 0 ? (
                        <EmptyState
                            title="Nada pendente"
                            description="Todas as contas analíticas de resultado estão mapeadas."
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
                                                        selectedIds.size === accounts.length &&
                                                        accounts.length > 0
                                                    }
                                                    onChange={toggleAll}
                                                    className="rounded border-gray-300"
                                                />
                                            </th>
                                        )}
                                        <th className="px-3 py-2 text-left text-xs font-semibold text-gray-600">
                                            Conta
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold text-gray-600 w-40">
                                            Grupo
                                        </th>
                                        <th className="px-3 py-2 text-left text-xs font-semibold text-gray-600 w-56">
                                            Sugestão (plano gerencial)
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                    {accounts.map((account) => {
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
                                                <td className="px-3 py-2 text-sm">
                                                    <StatusBadge
                                                        variant={
                                                            account.account_group === 3
                                                                ? 'success'
                                                                : 'warning'
                                                        }
                                                        dot
                                                    >
                                                        {account.account_group_label ||
                                                            groupLabel(account.account_group)}
                                                    </StatusBadge>
                                                </td>
                                                <td className="px-3 py-2 text-sm text-gray-600">
                                                    {account.default_management_class ? (
                                                        <span>
                                                            {account.default_management_class.code}{' '}
                                                            —{' '}
                                                            {account.default_management_class.name}
                                                        </span>
                                                    ) : (
                                                        <span className="text-gray-400">
                                                            sem sugestão
                                                        </span>
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
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
                defaultEffectiveFrom={effective_on}
            />
        </AuthenticatedLayout>
    );
}
