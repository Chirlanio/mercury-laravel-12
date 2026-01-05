import { Head, router } from "@inertiajs/react";
import { useState } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Modal from "@/Components/Modal";
import DataTable from "@/Components/DataTable";
import Button from "@/Components/Button";
import StoreCreateModal from "@/Components/StoreCreateModal";
import StoreEditModal from "@/Components/StoreEditModal";
import StoreViewModal from "@/Components/StoreViewModal";

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
                <div className="flex space-x-2">
                    <Button
                        onClick={(e) => {
                            e.stopPropagation();
                            viewStore(store);
                        }}
                        variant="secondary"
                        size="sm"
                        iconOnly={true}
                        icon={({ className }) => (
                            <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        )}
                        title="Visualizar detalhes da loja"
                    />
                    <Button
                        onClick={(e) => {
                            e.stopPropagation();
                            editStore(store);
                        }}
                        variant="warning"
                        size="sm"
                        iconOnly={true}
                        icon={({ className }) => (
                            <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        )}
                        title="Editar loja"
                    />
                    <Button
                        onClick={(e) => {
                            e.stopPropagation();
                            openDeleteModal(store);
                        }}
                        variant="danger"
                        size="sm"
                        iconOnly={true}
                        icon={({ className }) => (
                            <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        )}
                        title="Excluir loja"
                    />
                </div>
            )
        }
    ];

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="Lojas" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
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
                                    icon={({ className }) => (
                                        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                        </svg>
                                    )}
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
                                    icon={({ className }) => (
                                        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    )}
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
                            <svg className="h-12 w-12 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
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
                            icon={({ className }) => (
                                <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            )}
                        >
                            Excluir Loja
                        </Button>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}
