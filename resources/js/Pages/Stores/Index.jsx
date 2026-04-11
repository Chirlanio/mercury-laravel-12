import { Head, router } from "@inertiajs/react";
import { useState } from "react";
import { usePermissions, PERMISSIONS } from "@/Hooks/usePermissions";
import useModalManager from "@/Hooks/useModalManager";
import DataTable from "@/Components/DataTable";
import Button from "@/Components/Button";
import ActionButtons from "@/Components/ActionButtons";
import StatusBadge from "@/Components/Shared/StatusBadge";
import DeleteConfirmModal from "@/Components/Shared/DeleteConfirmModal";
import StoreCreateModal from "@/Components/StoreCreateModal";
import StoreEditModal from "@/Components/StoreEditModal";
import StoreViewModal from "@/Components/StoreViewModal";
import { PlusIcon, XMarkIcon } from "@heroicons/react/24/outline";

const NETWORK_VARIANT = {
    1: 'pink', 2: 'purple', 3: 'amber', 4: 'info',
    5: 'orange', 6: 'cyan', 7: 'gray', 8: 'rose',
};

export default function Index({ stores, networks, statuses, managers, filters }) {
    const { hasPermission } = usePermissions();
    const { modals, selected, openModal, closeModal, switchModal } = useModalManager(['create', 'view', 'edit']);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleting, setDeleting] = useState(false);

    const viewStore = async (store) => {
        try {
            const response = await fetch(`/stores/${store.id}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json();
            openModal('view', data.store);
        } catch (error) {
            console.error('Erro ao carregar dados da loja:', error);
        }
    };

    const handleEditFromView = () => {
        switchModal('view', 'edit');
    };

    const handleCreated = () => {
        closeModal('create');
        router.reload();
    };

    const handleUpdated = () => {
        closeModal('edit');
        router.reload();
    };

    const handleConfirmDelete = () => {
        if (!deleteTarget) return;
        setDeleting(true);
        router.delete(`/stores/${deleteTarget.id}`, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => { setDeleteTarget(null); setDeleting(false); },
            onError: () => setDeleting(false),
        });
    };

    const columns = [
        {
            label: "Código",
            field: "code",
            sortable: true,
            render: (store) => (
                <span className="font-mono font-semibold text-blue-600">{store.code}</span>
            ),
        },
        {
            label: "Loja",
            field: "name",
            sortable: true,
            render: (store) => (
                <div>
                    <div className="text-sm font-medium text-gray-900">{store.name}</div>
                    <div className="text-xs text-gray-500">{store.company_name?.substring(0, 40)}...</div>
                </div>
            ),
        },
        {
            label: "Rede",
            field: "network_id",
            sortable: true,
            render: (store) => (
                <StatusBadge variant={NETWORK_VARIANT[store.network_id] || 'gray'}>
                    {store.network_name}
                </StatusBadge>
            ),
        },
        {
            label: "Gerente",
            field: "manager_name",
            sortable: false,
            render: (store) => (
                <span className="text-sm text-gray-700">{store.manager_name || 'Não informado'}</span>
            ),
        },
        {
            label: "Funcionários",
            field: "employees_count",
            sortable: false,
            render: (store) => (
                <StatusBadge variant="gray">{store.employees_count} funcionários</StatusBadge>
            ),
        },
        {
            label: "Status",
            field: "is_active",
            sortable: false,
            render: (store) => (
                <StatusBadge variant={store.is_active ? 'success' : 'danger'}>
                    {store.is_active ? 'Ativa' : 'Inativa'}
                </StatusBadge>
            ),
        },
        {
            label: "Ações",
            field: "actions",
            sortable: false,
            render: (store) => (
                <ActionButtons
                    onView={() => viewStore(store)}
                    onEdit={hasPermission(PERMISSIONS.EDIT_USERS) ? () => openModal('edit', store) : undefined}
                    onDelete={hasPermission(PERMISSIONS.DELETE_USERS) ? () => setDeleteTarget(store) : undefined}
                />
            ),
        },
    ];

    return (
        <>
            <Head title="Lojas" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6">
                        <div className="flex justify-between items-center">
                            <div>
                                <h1 className="text-3xl font-bold text-gray-900">Lojas</h1>
                                <p className="mt-1 text-sm text-gray-600">
                                    Gerencie e visualize informações das lojas cadastradas
                                </p>
                            </div>
                            <div className="flex gap-3">
                                {hasPermission(PERMISSIONS.CREATE_USERS) && (
                                    <Button variant="primary" onClick={() => openModal('create')} icon={PlusIcon}>
                                        Nova Loja
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Filtros */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                            <div>
                                <label htmlFor="network-filter" className="block text-sm font-medium text-gray-700 mb-2">
                                    Filtrar por Rede
                                </label>
                                <select
                                    id="network-filter"
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    value={filters?.network || ''}
                                    onChange={(e) => {
                                        const currentUrl = new URL(window.location);
                                        if (e.target.value) currentUrl.searchParams.set('network', e.target.value);
                                        else currentUrl.searchParams.delete('network');
                                        currentUrl.searchParams.delete('page');
                                        router.visit(currentUrl.toString(), { preserveState: true, preserveScroll: true });
                                    }}
                                >
                                    <option value="">Todas as redes</option>
                                    {networks?.map((network) => (
                                        <option key={network.id} value={network.id}>{network.name}</option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label htmlFor="status-filter" className="block text-sm font-medium text-gray-700 mb-2">
                                    Filtrar por Status
                                </label>
                                <select
                                    id="status-filter"
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    value={filters?.status || ''}
                                    onChange={(e) => {
                                        const currentUrl = new URL(window.location);
                                        if (e.target.value) currentUrl.searchParams.set('status', e.target.value);
                                        else currentUrl.searchParams.delete('status');
                                        currentUrl.searchParams.delete('page');
                                        router.visit(currentUrl.toString(), { preserveState: true, preserveScroll: true });
                                    }}
                                >
                                    <option value="">Todos os status</option>
                                    {statuses?.map((status) => (
                                        <option key={status.id} value={status.id}>{status.name}</option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    className="h-[42px] w-[150px]"
                                    onClick={() => router.visit('/stores', { preserveState: true, preserveScroll: true })}
                                    disabled={!filters?.network && !filters?.status}
                                    icon={XMarkIcon}
                                >
                                    Limpar Filtros
                                </Button>
                            </div>
                        </div>
                    </div>

                    {/* DataTable */}
                    <DataTable
                        data={stores}
                        columns={columns}
                        searchPlaceholder="Pesquisar lojas por código, nome, CNPJ..."
                        emptyMessage="Nenhuma loja encontrada"
                        onRowClick={viewStore}
                        perPageOptions={[15, 25, 50, 100]}
                    />
                </div>
            </div>

            {/* View Modal */}
            <StoreViewModal
                show={modals.view && selected !== null}
                onClose={() => closeModal('view')}
                store={selected}
                onEdit={handleEditFromView}
            />

            {/* Create Modal */}
            <StoreCreateModal
                show={modals.create}
                onClose={() => closeModal('create')}
                onSuccess={handleCreated}
                networks={networks}
                managers={managers}
            />

            {/* Edit Modal */}
            <StoreEditModal
                show={modals.edit && selected !== null}
                onClose={() => closeModal('edit')}
                onSuccess={handleUpdated}
                store={selected}
                networks={networks}
                managers={managers}
            />

            {/* Delete Confirm Modal */}
            <DeleteConfirmModal
                show={deleteTarget !== null}
                onClose={() => setDeleteTarget(null)}
                onConfirm={handleConfirmDelete}
                itemType="loja"
                itemName={deleteTarget?.name}
                details={[
                    { label: 'Código', value: deleteTarget?.code },
                    { label: 'Funcionários', value: deleteTarget?.employees_count?.toString() },
                ]}
                processing={deleting}
            />
        </>
    );
}
