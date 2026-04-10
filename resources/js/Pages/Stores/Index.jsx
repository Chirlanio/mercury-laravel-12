import { Head, router } from "@inertiajs/react";
import { useState } from "react";
import Modal from "@/Components/Modal";
import DataTable from "@/Components/DataTable";
import Button from "@/Components/Button";
import ActionButtons from "@/Components/ActionButtons";
import StoreCreateModal from "@/Components/StoreCreateModal";
import StoreEditModal from "@/Components/StoreEditModal";
import StoreViewModal from "@/Components/StoreViewModal";
import { PlusIcon, XMarkIcon, TrashIcon, ExclamationTriangleIcon } from "@heroicons/react/24/outline";

export default function Index({ auth, stores, networks, statuses, managers, filters }) {
    const [selectedStore, setSelectedStore] = useState(null);
    const [isViewModalOpen, setIsViewModalOpen] = useState(false);
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [storeToDelete, setStoreToDelete] = useState(null);

    const viewStore = async (store) => {
        try {
            const response = await fetch(`/stores/${store.id}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                }
            });
            const data = await response.json();
            setSelectedStore(data.store);
            setIsViewModalOpen(true);
        } catch (error) {
            console.error('Erro ao carregar dados da loja:', error);
        }
    };

    const closeViewModal = () => {
        setIsViewModalOpen(false);
        setSelectedStore(null);
    };

    const openCreateModal = () => {
        setIsCreateModalOpen(true);
    };

    const closeCreateModal = () => {
        setIsCreateModalOpen(false);
    };

    const handleStoreCreated = () => {
        router.reload();
    };

    const editStore = (store) => {
        setSelectedStore(store);
        setIsEditModalOpen(true);
    };

    const handleEditFromModal = (store) => {
        closeViewModal();
        editStore(store);
    };

    const closeEditModal = () => {
        setIsEditModalOpen(false);
        setSelectedStore(null);
    };

    const handleStoreUpdated = () => {
        closeEditModal();
        router.reload();
    };

    const openDeleteModal = (store) => {
        setStoreToDelete(store);
        setIsDeleteModalOpen(true);
    };

    const closeDeleteModal = () => {
        setIsDeleteModalOpen(false);
        setStoreToDelete(null);
    };

    const handleDeleteStore = () => {
        if (!storeToDelete) return;

        router.delete(`/stores/${storeToDelete.id}`, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                closeDeleteModal();
            },
            onError: (errors) => {
                console.error('Erro ao excluir loja:', errors);
            }
        });
    };

    const toggleStoreStatus = (store) => {
        const route = store.is_active ? `/stores/${store.id}/deactivate` : `/stores/${store.id}/activate`;
        router.post(route, {}, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                router.reload();
            }
        });
    };

    function getNetworkColor(networkId) {
        const colors = {
            1: 'bg-pink-100 text-pink-800',     // Arezzo
            2: 'bg-purple-100 text-purple-800', // Anacapri
            3: 'bg-amber-100 text-amber-800',   // Meia Sola
            4: 'bg-blue-100 text-blue-800',     // Schutz
            5: 'bg-orange-100 text-orange-800', // Outlet
            6: 'bg-cyan-100 text-cyan-800',     // E-Commerce
            7: 'bg-gray-100 text-gray-800',     // Operacional
            8: 'bg-rose-100 text-rose-800',     // Arezzo Brizza
        };
        return colors[networkId] || 'bg-gray-100 text-gray-800';
    }

    // Column definitions
    const columns = [
        {
            label: "Codigo",
            field: "code",
            sortable: true,
            render: (store) => (
                <span className="font-mono font-semibold text-blue-600">
                    {store.code}
                </span>
            )
        },
        {
            label: "Loja",
            field: "name",
            sortable: true,
            render: (store) => (
                <div>
                    <div className="text-sm font-medium text-gray-900">
                        {store.name}
                    </div>
                    <div className="text-xs text-gray-500">
                        {store.company_name?.substring(0, 40)}...
                    </div>
                </div>
            )
        },
        {
            label: "Rede",
            field: "network_id",
            sortable: true,
            render: (store) => (
                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getNetworkColor(store.network_id)}`}>
                    {store.network_name}
                </span>
            )
        },
        {
            label: "Gerente",
            field: "manager_name",
            sortable: false,
            render: (store) => (
                <span className="text-sm text-gray-700">
                    {store.manager_name || 'Nao informado'}
                </span>
            )
        },
        {
            label: "Funcionarios",
            field: "employees_count",
            sortable: false,
            render: (store) => (
                <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                    {store.employees_count} funcionarios
                </span>
            )
        },
        {
            label: "Status",
            field: "is_active",
            sortable: false,
            render: (store) => (
                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                    store.is_active
                        ? 'bg-green-100 text-green-800'
                        : 'bg-red-100 text-red-800'
                }`}>
                    {store.is_active ? 'Ativa' : 'Inativa'}
                </span>
            )
        },
        {
            label: "Acoes",
            field: "actions",
            sortable: false,
            render: (store) => (
                <ActionButtons
                    onView={() => viewStore(store)}
                    onEdit={() => editStore(store)}
                    onDelete={() => openDeleteModal(store)}
                />
            )
        }
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
                                <h1 className="text-3xl font-bold text-gray-900">
                                    Lojas
                                </h1>
                                <p className="mt-1 text-sm text-gray-600">
                                    Gerencie e visualize informacoes das lojas cadastradas
                                </p>
                            </div>
                            <div className="flex gap-3">
                                <Button
                                    variant="primary"
                                    onClick={openCreateModal}
                                    icon={PlusIcon}
                                >
                                    Nova Loja
                                </Button>
                            </div>
                        </div>
                    </div>

                    {/* Filtros */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                            {/* Filtro de Rede */}
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
                                        if (e.target.value) {
                                            currentUrl.searchParams.set('network', e.target.value);
                                        } else {
                                            currentUrl.searchParams.delete('network');
                                        }
                                        currentUrl.searchParams.delete('page');
                                        router.visit(currentUrl.toString(), {
                                            preserveState: true,
                                            preserveScroll: true,
                                        });
                                    }}
                                >
                                    <option value="">Todas as redes</option>
                                    {networks?.map((network) => (
                                        <option key={network.id} value={network.id}>
                                            {network.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Filtro de Status */}
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
                                        if (e.target.value) {
                                            currentUrl.searchParams.set('status', e.target.value);
                                        } else {
                                            currentUrl.searchParams.delete('status');
                                        }
                                        currentUrl.searchParams.delete('page');
                                        router.visit(currentUrl.toString(), {
                                            preserveState: true,
                                            preserveScroll: true,
                                        });
                                    }}
                                >
                                    <option value="">Todos os status</option>
                                    {statuses?.map((status) => (
                                        <option key={status.id} value={status.id}>
                                            {status.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Botao Limpar Filtros */}
                            <div>
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    className="h-[42px] w-[150px]"
                                    onClick={() => {
                                        router.visit('/stores', {
                                            preserveState: true,
                                            preserveScroll: true,
                                        });
                                    }}
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
                        searchPlaceholder="Pesquisar lojas por codigo, nome, CNPJ..."
                        emptyMessage="Nenhuma loja encontrada"
                        onRowClick={viewStore}
                        perPageOptions={[15, 25, 50, 100]}
                    />
                </div>
            </div>

            {/* View Modal */}
            <StoreViewModal
                isOpen={isViewModalOpen}
                onClose={closeViewModal}
                store={selectedStore}
                onEdit={handleEditFromModal}
            />

            {/* Create Modal */}
            <StoreCreateModal
                isOpen={isCreateModalOpen}
                onClose={closeCreateModal}
                onSuccess={handleStoreCreated}
                networks={networks}
                managers={managers}
            />

            {/* Edit Modal */}
            <StoreEditModal
                isOpen={isEditModalOpen}
                onClose={closeEditModal}
                onSuccess={handleStoreUpdated}
                store={selectedStore}
                networks={networks}
                managers={managers}
            />

            {/* Delete Confirmation Modal */}
            <Modal show={isDeleteModalOpen} onClose={closeDeleteModal} title="Confirmar Exclusao" maxWidth="85vw">
                <div className="space-y-6">
                    <div className="flex items-start space-x-4">
                        <div className="flex-shrink-0">
                            <ExclamationTriangleIcon className="h-12 w-12 text-red-600" />
                        </div>
                        <div className="flex-1">
                            <h3 className="text-lg font-medium text-gray-900 mb-2">
                                Tem certeza que deseja excluir esta loja?
                            </h3>
                            {storeToDelete && (
                                <div className="text-sm text-gray-600 space-y-1">
                                    <p><strong>Codigo:</strong> {storeToDelete.code}</p>
                                    <p><strong>Nome:</strong> {storeToDelete.name}</p>
                                    <p><strong>Funcionarios:</strong> {storeToDelete.employees_count}</p>
                                </div>
                            )}
                            <p className="mt-4 text-sm text-red-600 font-semibold">
                                Esta acao nao pode ser desfeita. Todos os dados da loja serao permanentemente excluidos.
                            </p>
                        </div>
                    </div>

                    <div className="flex justify-end space-x-3 pt-4 border-t">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={closeDeleteModal}
                        >
                            Cancelar
                        </Button>
                        <Button
                            type="button"
                            variant="danger"
                            onClick={handleDeleteStore}
                            icon={TrashIcon}
                        >
                            Excluir Loja
                        </Button>
                    </div>
                </div>
            </Modal>
        </>
    );
}
