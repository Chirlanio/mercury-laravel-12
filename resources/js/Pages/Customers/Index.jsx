import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import {
    UserGroupIcon,
    UsersIcon,
    CheckCircleIcon,
    CloudArrowDownIcon,
    ArrowPathIcon,
    XMarkIcon,
    MapPinIcon,
    ClockIcon,
} from '@heroicons/react/24/outline';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import { useConfirm } from '@/Hooks/useConfirm';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import DataTable from '@/Components/DataTable';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import CustomerDetailModal from './Partials/CustomerDetailModal';
import SyncHistoryModal from './Partials/SyncHistoryModal';

/**
 * Listagem de Clientes — read-only (fonte: CIGAM sync).
 * Mobile-first com StatisticsGrid + filtros responsivos + DataTable.
 */
export default function Index({
    customers,
    filters = {},
    statistics = {},
    states = [],
    can = {},
}) {
    const { hasPermission } = usePermissions();
    const canSync = can.sync ?? hasPermission(PERMISSIONS.SYNC_CUSTOMERS);
    const canExport = can.export ?? hasPermission(PERMISSIONS.EXPORT_CUSTOMERS);

    const { modals, selected, openModal, closeModal } = useModalManager(['detail', 'history']);
    const { confirm, ConfirmDialogComponent } = useConfirm();

    const [syncing, setSyncing] = useState(false);

    const statsCards = useMemo(() => {
        const lastSync = statistics.last_sync
            ? new Date(statistics.last_sync).toLocaleString('pt-BR', {
                day: '2-digit', month: '2-digit', year: 'numeric',
                hour: '2-digit', minute: '2-digit',
            })
            : '—';

        return [
            {
                label: 'Total de clientes',
                value: statistics.total ?? 0,
                format: 'number',
                icon: UsersIcon,
                color: 'gray',
            },
            {
                label: 'Ativos',
                value: statistics.active ?? 0,
                format: 'number',
                icon: CheckCircleIcon,
                color: 'success',
            },
            {
                label: 'Última sincronização',
                value: lastSync,
                icon: CloudArrowDownIcon,
                color: 'info',
            },
        ];
    }, [statistics]);

    const applyFilter = (key, value) => {
        router.get(
            route('customers.index'),
            { ...filters, [key]: value || undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const clearFilters = () => {
        router.get(route('customers.index'), {}, {
            preserveState: false,
            preserveScroll: true,
        });
    };

    const hasActiveFilters = useMemo(() => {
        return ['search', 'state', 'city']
            .some((k) => filters[k] !== undefined && filters[k] !== '');
    }, [filters]);

    const openDetail = async (row) => {
        try {
            const response = await fetch(route('customers.show', row.id), {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) throw new Error();
            const { customer } = await response.json();
            openModal('detail', customer);
        } catch {
            openModal('detail', row);
        }
    };

    const handleSync = async () => {
        const ok = await confirm({
            title: 'Sincronizar clientes',
            message: 'Isso dispara um novo sync com a view CIGAM msl_dcliente_ em background. Pode levar alguns minutos dependendo do volume. Confirmar?',
            confirmText: 'Sim, sincronizar',
            type: 'info',
        });
        if (!ok) return;

        setSyncing(true);
        router.post(route('customers.sync'), {}, {
            preserveScroll: true,
            onFinish: () => setSyncing(false),
        });
    };

    const columns = [
        {
            key: 'cigam_code',
            label: '#',
            className: 'hidden md:table-cell whitespace-nowrap text-xs text-gray-500',
            render: (row) => <span className="font-mono">{row.cigam_code}</span>,
        },
        {
            key: 'name',
            label: 'Nome',
            render: (row) => (
                <div className="min-w-0">
                    <div className="font-medium text-gray-900 truncate">{row.name}</div>
                    {row.formatted_cpf && (
                        <div className="text-xs text-gray-500 truncate">{row.formatted_cpf}</div>
                    )}
                </div>
            ),
        },
        {
            key: 'contact',
            label: 'Contato',
            className: 'hidden sm:table-cell',
            render: (row) => (
                <div className="text-sm">
                    {row.formatted_mobile && (
                        <div className="text-gray-700">{row.formatted_mobile}</div>
                    )}
                    {row.email && (
                        <div className="text-xs text-gray-500 truncate max-w-[200px]">{row.email}</div>
                    )}
                    {!row.formatted_mobile && !row.email && <span className="text-gray-400">—</span>}
                </div>
            ),
        },
        {
            key: 'location',
            label: 'Localização',
            className: 'hidden lg:table-cell',
            render: (row) => (
                <div className="text-sm text-gray-700 whitespace-nowrap">
                    {row.city ? `${row.city}${row.state ? ` / ${row.state}` : ''}` : '—'}
                </div>
            ),
        },
        {
            key: 'active',
            label: 'Status',
            render: (row) => (
                <StatusBadge color={row.is_active ? 'success' : 'gray'}>
                    {row.is_active ? 'Ativo' : 'Inativo'}
                </StatusBadge>
            ),
        },
        {
            key: 'actions',
            label: '',
            className: 'text-right',
            render: (row) => (
                <ActionButtons onView={() => openDetail(row)} />
            ),
        },
    ];

    return (
        <>
            <Head title="Clientes" />

            <div className="py-6 sm:py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-center">
                        <div>
                            <h1 className="text-2xl sm:text-3xl font-bold text-gray-900 flex items-center gap-2">
                                <UserGroupIcon className="h-7 w-7 text-indigo-600" />
                                Clientes
                            </h1>
                            <p className="mt-1 text-sm text-gray-600">
                                Base sincronizada do CIGAM — atualização diária às 04:00.
                                Escrita acontece no ERP.
                            </p>
                        </div>
                        <div className="flex gap-2 shrink-0">
                            <Button
                                variant="secondary"
                                onClick={() => openModal('history')}
                                title="Histórico de sincronizações"
                                className="min-h-[44px]"
                            >
                                <ClockIcon className="w-4 h-4 sm:mr-2" />
                                <span className="hidden sm:inline">Histórico</span>
                            </Button>
                            {canSync && (
                                <Button
                                    variant="primary"
                                    onClick={handleSync}
                                    disabled={syncing}
                                    className="min-h-[44px]"
                                >
                                    <ArrowPathIcon className={`w-4 h-4 sm:mr-2 ${syncing ? 'animate-spin' : ''}`} />
                                    <span className="hidden sm:inline">
                                        {syncing ? 'Sincronizando…' : 'Sincronizar'}
                                    </span>
                                </Button>
                            )}
                        </div>
                    </div>

                    <StatisticsGrid cards={statsCards} cols={3} />

                    {/* Filtros */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                            <div className="md:col-span-2">
                                <InputLabel htmlFor="search" value="Buscar" />
                                <TextInput
                                    id="search"
                                    type="text"
                                    value={filters.search || ''}
                                    onChange={(e) => applyFilter('search', e.target.value)}
                                    placeholder="Nome, CPF, e-mail, telefone…"
                                    className="mt-1 block w-full"
                                />
                            </div>
                            <div>
                                <InputLabel value="UF" />
                                <select
                                    value={filters.state || ''}
                                    onChange={(e) => applyFilter('state', e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 min-h-[42px]"
                                >
                                    <option value="">Todas</option>
                                    {states.map((s) => (
                                        <option key={s} value={s}>{s}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <InputLabel htmlFor="city" value="Cidade" />
                                <TextInput
                                    id="city"
                                    type="text"
                                    value={filters.city || ''}
                                    onChange={(e) => applyFilter('city', e.target.value)}
                                    placeholder="Ex: FORTALEZA"
                                    className="mt-1 block w-full"
                                />
                            </div>
                            <div>
                                <label className="flex items-center gap-2 text-sm text-gray-700 h-[42px]">
                                    <input
                                        type="checkbox"
                                        checked={filters.only_active !== '0' && filters.only_active !== false}
                                        onChange={(e) => applyFilter('only_active', e.target.checked ? '1' : '0')}
                                        className="rounded border-gray-300 w-5 h-5"
                                    />
                                    <span>Apenas ativos</span>
                                </label>
                            </div>
                        </div>
                        {hasActiveFilters && (
                            <div className="mt-3 flex justify-end">
                                <Button variant="outline" size="sm" icon={XMarkIcon} onClick={clearFilters}>
                                    Limpar filtros
                                </Button>
                            </div>
                        )}
                    </div>

                    <DataTable
                        data={customers}
                        columns={columns}
                        searchable={false}
                        emptyMessage={
                            hasActiveFilters
                                ? 'Nenhum cliente encontrado para os filtros aplicados.'
                                : 'Nenhum cliente sincronizado ainda. Execute uma sincronização com o CIGAM.'
                        }
                    />
                </div>
            </div>

            <CustomerDetailModal
                show={modals.detail}
                onClose={() => closeModal('detail')}
                customer={selected}
            />

            <SyncHistoryModal
                show={modals.history}
                onClose={() => closeModal('history')}
            />

            <ConfirmDialogComponent />
        </>
    );
}
