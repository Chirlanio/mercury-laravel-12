import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import {
    XMarkIcon,
    ArchiveBoxIcon,
    ClockIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    ArrowPathIcon,
    ArrowUturnLeftIcon,
    NoSymbolIcon,
    EyeIcon,
    CurrencyDollarIcon,
} from '@heroicons/react/24/outline';

import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import { useConfirm } from '@/Hooks/useConfirm';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import DataTable from '@/Components/DataTable';
import PageHeader from '@/Components/Shared/PageHeader';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import CreateConsignmentModal from './Partials/CreateConsignmentModal';
import ConsignmentDetailModal from './Partials/ConsignmentDetailModal';
import RegisterReturnModal from './Partials/RegisterReturnModal';

const COLOR_MAP = {
    success: 'success',
    warning: 'warning',
    info: 'info',
    danger: 'danger',
    purple: 'purple',
    gray: 'gray',
    teal: 'teal',
};

const TYPE_COLOR_MAP = {
    cliente: 'info',
    influencer: 'purple',
    ecommerce: 'teal',
};

export default function Index({
    consignments,
    filters = {},
    statistics = {},
    user_store_code = null,
    typeOptions = {},
    statusOptions = {},
    statusColors = {},
    statusTransitions = {},
    isStoreScoped = false,
    scopedStoreId = null,
    selects = {},
    can = {},
}) {
    const { hasPermission } = usePermissions();
    const canCreate = can.create ?? hasPermission(PERMISSIONS.CREATE_CONSIGNMENTS);
    const canEdit = can.edit ?? hasPermission(PERMISSIONS.EDIT_CONSIGNMENTS);
    const canDelete = can.delete ?? hasPermission(PERMISSIONS.DELETE_CONSIGNMENTS);
    const canComplete = can.complete ?? false;
    const canCancel = can.cancel ?? false;
    const canRegisterReturn = can.register_return ?? false;
    const canOverrideLock = can.override_lock ?? false;
    const canExport = can.export ?? false;
    const canImport = can.import ?? false;

    const { modals, selected, openModal, closeModal } = useModalManager([
        'create', 'detail', 'return',
    ]);

    const { confirm, ConfirmDialogComponent } = useConfirm();

    const statsCards = useMemo(() => ([
        {
            label: 'Total',
            value: statistics.total ?? 0,
            format: 'number',
            icon: ArchiveBoxIcon,
            color: 'gray',
        },
        {
            label: 'Pendentes',
            value: statistics.pending ?? 0,
            format: 'number',
            icon: ClockIcon,
            color: 'info',
            onClick: () => applyFilter('status', 'pending'),
            active: filters.status === 'pending',
        },
        {
            label: 'Parciais',
            value: statistics.partially_returned ?? 0,
            format: 'number',
            icon: ArrowPathIcon,
            color: 'warning',
            onClick: () => applyFilter('status', 'partially_returned'),
            active: filters.status === 'partially_returned',
        },
        {
            label: 'Em atraso',
            value: statistics.overdue ?? 0,
            format: 'number',
            icon: ExclamationTriangleIcon,
            color: 'danger',
            onClick: () => applyFilter('status', 'overdue'),
            active: filters.status === 'overdue',
        },
        {
            label: 'Finalizadas',
            value: statistics.completed ?? 0,
            format: 'number',
            icon: CheckCircleIcon,
            color: 'success',
        },
        {
            label: 'Canceladas',
            value: statistics.cancelled ?? 0,
            format: 'number',
            icon: NoSymbolIcon,
            color: 'gray',
        },
    ]), [statistics, filters.status]);

    const applyFilter = (key, value) => {
        router.get(
            route('consignments.index'),
            { ...filters, [key]: value || undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const clearFilters = () => {
        router.get(route('consignments.index'), {}, {
            preserveState: false,
            preserveScroll: true,
        });
    };

    const hasActiveFilters = useMemo(() => {
        return ['search', 'type', 'status', 'date_from', 'date_to', 'include_terminal']
            .some((k) => filters[k] !== undefined && filters[k] !== '' && filters[k] !== null);
    }, [filters]);

    const openDetail = async (row) => {
        try {
            const response = await fetch(route('consignments.show', row.id), {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) throw new Error();
            const { consignment } = await response.json();
            openModal('detail', consignment);
        } catch (e) {
            // fallback — abre com row resumido
            openModal('detail', row);
        }
    };

    const handleDelete = async (row) => {
        const ok = await confirm({
            title: 'Excluir consignação',
            message: `Deseja excluir a consignação "${row.recipient_name}" (NF ${row.outbound_invoice_number})?`,
            confirmText: 'Sim, excluir',
            type: 'danger',
        });
        if (!ok) return;

        router.delete(route('consignments.destroy', row.id), {
            data: { deleted_reason: 'Excluído pelo usuário' },
            preserveScroll: true,
        });
    };

    const handleComplete = async (row) => {
        const ok = await confirm({
            title: 'Finalizar consignação',
            message: 'Após finalizar, a consignação entra em estado terminal. Itens pendentes serão marcados como perdidos. Confirmar?',
            confirmText: 'Sim, finalizar',
            type: 'success',
        });
        if (!ok) return;

        router.post(route('consignments.transition', row.id), {
            to_status: 'completed',
        }, { preserveScroll: true });
    };

    const columns = [
        {
            key: 'id',
            label: '#',
            className: 'whitespace-nowrap text-xs text-gray-500',
            render: (row) => <span className="font-mono">#{row.id}</span>,
        },
        {
            key: 'type',
            label: 'Tipo',
            render: (row) => (
                <StatusBadge color={COLOR_MAP[TYPE_COLOR_MAP[row.type]] || 'info'}>
                    {row.type_label}
                </StatusBadge>
            ),
        },
        {
            key: 'recipient_name',
            label: 'Destinatário',
            render: (row) => (
                <div className="min-w-0">
                    <div className="font-medium text-gray-900 truncate">{row.recipient_name}</div>
                    {row.recipient_document && (
                        <div className="text-xs text-gray-500 truncate">
                            {row.recipient_document}
                        </div>
                    )}
                </div>
            ),
        },
        {
            key: 'store',
            label: 'Loja',
            className: 'hidden md:table-cell',
            render: (row) => (
                <div className="text-sm text-gray-700 whitespace-nowrap">
                    {row.store?.code} <span className="text-gray-400 hidden xl:inline">— {row.store?.name}</span>
                </div>
            ),
        },
        {
            key: 'outbound_invoice_number',
            label: 'NF Saída',
            className: 'hidden sm:table-cell',
            render: (row) => (
                <div className="text-sm whitespace-nowrap">
                    <div>{row.outbound_invoice_number}</div>
                    <div className="text-xs text-gray-500">
                        {row.outbound_invoice_date}
                    </div>
                </div>
            ),
        },
        {
            key: 'items_count',
            label: 'Itens',
            className: 'text-center hidden md:table-cell',
            render: (row) => (
                <div className="text-center">
                    <span className="font-medium">{row.outbound_items_count}</span>
                    {(row.returned_items_count > 0 || row.sold_items_count > 0) && (
                        <div className="text-xs text-gray-500 mt-0.5">
                            {row.returned_items_count}d / {row.sold_items_count}v
                        </div>
                    )}
                </div>
            ),
        },
        {
            key: 'expected_return_date',
            label: 'Prazo',
            className: 'hidden lg:table-cell',
            render: (row) => (
                <div className={`text-sm whitespace-nowrap ${row.is_overdue ? 'text-red-600 font-medium' : 'text-gray-700'}`}>
                    {row.expected_return_date}
                </div>
            ),
        },
        {
            key: 'status',
            label: 'Status',
            render: (row) => (
                <StatusBadge color={COLOR_MAP[row.status_color] || 'gray'}>
                    {row.status_label}
                </StatusBadge>
            ),
        },
        {
            key: 'actions',
            label: '',
            className: 'text-right',
            render: (row) => (
                <ActionButtons onView={() => openDetail(row)}>
                    {canRegisterReturn
                        && !['completed', 'cancelled'].includes(row.status)
                        && (row.outbound_items_count - (row.returned_items_count || 0) - (row.sold_items_count || 0) - (row.lost_items_count || 0)) > 0
                        && (
                            <ActionButtons.Custom
                                onClick={() => openModal('return', row)}
                                icon={ArrowUturnLeftIcon}
                                title="Registrar retorno"
                                variant="info"
                            />
                        )}
                    {canComplete
                        && ['pending', 'partially_returned', 'overdue'].includes(row.status)
                        && (
                            <ActionButtons.Custom
                                onClick={() => handleComplete(row)}
                                icon={CheckCircleIcon}
                                title="Finalizar"
                                variant="success"
                            />
                        )}
                    {canDelete && row.status === 'draft' && (
                        <ActionButtons.Custom
                            onClick={() => handleDelete(row)}
                            icon={XMarkIcon}
                            title="Excluir"
                            variant="danger"
                        />
                    )}
                </ActionButtons>
            ),
        },
    ];

    return (
        <>
            <Head title="Consignações" />

            <div className="py-6 sm:py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <PageHeader
                        title="Consignações"
                        subtitle="Remessa de produtos para Cliente, Influencer ou E-commerce com prazo de retorno"
                        scopeBadge={isStoreScoped ? 'escopo: sua loja' : null}
                        actions={[
                            {
                                type: 'dashboard',
                                href: route('consignments.dashboard'),
                            },
                            {
                                type: 'download',
                                download: route('consignments.export', filters),
                                visible: canExport,
                                title: 'Exportar XLSX',
                            },
                            {
                                type: 'import',
                                href: route('consignments.import'),
                                visible: canImport,
                                title: 'Importar planilha',
                            },
                            {
                                type: 'create',
                                label: 'Nova Consignação',
                                onClick: () => openModal('create'),
                                visible: canCreate,
                            },
                        ]}
                    />

                    {/* Statistics */}
                    <StatisticsGrid cards={statsCards} cols={6} />

                    {/* Filtros — mobile 1 col, sm 2 cols, lg 5 cols */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
                            <div className="sm:col-span-2 lg:col-span-2">
                                <InputLabel htmlFor="search" value="Buscar" />
                                <TextInput
                                    id="search"
                                    type="text"
                                    value={filters.search || ''}
                                    onChange={(e) => applyFilter('search', e.target.value)}
                                    placeholder="Destinatário, CPF, NF..."
                                    className="mt-1 block w-full"
                                />
                            </div>
                            <div>
                                <InputLabel value="Tipo" />
                                <select
                                    value={filters.type || ''}
                                    onChange={(e) => applyFilter('type', e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 min-h-[42px]"
                                >
                                    <option value="">Todos</option>
                                    {Object.entries(typeOptions).map(([v, lbl]) => (
                                        <option key={v} value={v}>{lbl}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <InputLabel value="Status" />
                                <select
                                    value={filters.status || ''}
                                    onChange={(e) => applyFilter('status', e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 min-h-[42px]"
                                >
                                    <option value="">Ativas</option>
                                    {Object.entries(statusOptions).map(([v, lbl]) => (
                                        <option key={v} value={v}>{lbl}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="flex items-center gap-2 text-sm text-gray-700 h-[42px]">
                                    <input
                                        type="checkbox"
                                        checked={!!filters.include_terminal}
                                        onChange={(e) => applyFilter('include_terminal', e.target.checked ? '1' : '')}
                                        className="rounded border-gray-300 w-5 h-5"
                                    />
                                    <span>Incluir finalizadas/canceladas</span>
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

                    {/* Tabela */}
                    <DataTable
                        data={consignments}
                        columns={columns}
                        searchable={false}
                        emptyMessage={
                            hasActiveFilters
                                ? 'Nenhuma consignação encontrada para os filtros aplicados.'
                                : 'Nenhuma consignação cadastrada ainda.'
                        }
                    />
                </div>
            </div>

            {/* Modais */}
            {canCreate && (
                <CreateConsignmentModal
                    show={modals.create}
                    onClose={() => closeModal('create')}
                    typeOptions={typeOptions}
                    selects={selects}
                    canOverrideLock={canOverrideLock}
                    canEditReturnPeriod={can.edit_return_period ?? false}
                />
            )}

            <ConsignmentDetailModal
                show={modals.detail}
                onClose={() => closeModal('detail')}
                consignment={selected}
                statusColors={statusColors}
                statusOptions={statusOptions}
                stores={selects.stores || []}
            />

            {canRegisterReturn && (
                <RegisterReturnModal
                    show={modals.return}
                    onClose={() => closeModal('return')}
                    consignmentSummary={selected}
                    userStoreCode={user_store_code}
                    canChooseStore={can.choose_return_store ?? false}
                    stores={selects.stores || []}
                />
            )}

            <ConfirmDialogComponent />
        </>
    );
}
