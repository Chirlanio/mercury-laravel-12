import UserCreateModal from '@/Components/UserCreateModal';
import UserEditModal from '@/Components/UserEditModal';
import UserViewModal from '@/Components/UserViewModal';
import DataTable from '@/Components/DataTable';
import UserAvatar from '@/Components/UserAvatar';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { PlusIcon, XMarkIcon } from '@heroicons/react/24/outline';

export default function Index({ auth, users = { data: [], links: [] }, roles = {}, stores = [], filters = {} }) {
    const [processing, setProcessing] = useState(false);
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [showEditModal, setShowEditModal] = useState(false);
    const [showViewModal, setShowViewModal] = useState(false);
    const [selectedUser, setSelectedUser] = useState(null);
    const { canEditUser, canDeleteUser, canManageRole, hasPermission } = usePermissions();

    const handleRoleChange = async (userId, newRole) => {
        if (confirm('Tem certeza que deseja alterar o nivel de acesso deste usuario?')) {
            setProcessing(true);

            router.patch(`/users/${userId}/role`,
                { role: newRole },
                {
                    onFinish: () => setProcessing(false),
                    preserveScroll: true,
                    onError: (errors) => {
                        console.error('Erro ao alterar nivel de acesso:', errors);
                        if (errors.role) {
                            alert(errors.role);
                        } else {
                            alert('Erro ao alterar o nivel de acesso. Verifique suas permissoes.');
                        }
                    },
                    onSuccess: () => {
                        console.log('Nivel de acesso alterado com sucesso');
                    }
                }
            );
        }
    };

    const handleDelete = (userId, userName) => {
        if (confirm(`Tem certeza que deseja deletar o usuario ${userName}?`)) {
            router.delete(`/users/${userId}`, {
                preserveScroll: true,
            });
        }
    };

    const handleEditUser = (user) => {
        setSelectedUser(user);
        setShowEditModal(true);
    };

    const handleViewUser = (user) => {
        setSelectedUser(user);
        setShowViewModal(true);
    };

    const closeModals = () => {
        setShowCreateModal(false);
        setShowEditModal(false);
        setShowViewModal(false);
        setSelectedUser(null);
    };

    const getRoleBadgeColor = (role) => {
        const colors = {
            super_admin: 'bg-red-100 text-red-800',
            admin: 'bg-blue-100 text-blue-800',
            support: 'bg-yellow-100 text-yellow-800',
            user: 'bg-green-100 text-green-800'
        };
        return colors[role] || 'bg-gray-100 text-gray-800';
    };

    const columns = [
        {
            label: 'Usuario',
            field: 'name',
            sortable: true,
            render: (user) => (
                <div className="flex items-center">
                    <div className="flex-shrink-0">
                        <UserAvatar
                            user={user}
                            size="md"
                            onClick={() => handleViewUser(user)}
                        />
                    </div>
                    <div className="ml-4">
                        <div className="text-sm font-medium text-gray-900">
                            <button
                                onClick={() => handleViewUser(user)}
                                className="hover:text-indigo-600 transition-colors cursor-pointer"
                            >
                                {user.name}
                            </button>
                        </div>
                        <div className="text-sm text-gray-500">
                            {user.email}
                        </div>
                    </div>
                </div>
            )
        },
        {
            label: 'Nivel de Acesso',
            field: 'role',
            sortable: true,
            render: (user) => (
                hasPermission(PERMISSIONS.MANAGE_USER_ROLES) && canManageRole(user.role) && user.id !== auth.user.id ? (
                    <select
                        value={user.role}
                        onChange={(e) => handleRoleChange(user.id, e.target.value)}
                        disabled={processing}
                        className={`text-sm rounded-full px-3 py-1 font-medium border-0 ${getRoleBadgeColor(user.role)} cursor-pointer`}
                    >
                        {Object.entries(roles).map(([value, label]) => (
                            canManageRole(value) ? (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ) : null
                        ))}
                    </select>
                ) : (
                    <span className={`inline-flex px-3 py-1 text-sm font-semibold rounded-full ${getRoleBadgeColor(user.role)}`}>
                        {roles[user.role] || user.role}
                    </span>
                )
            )
        },
        {
            label: 'Criado em',
            field: 'created_at',
            sortable: true,
            render: (user) => new Date(user.created_at).toLocaleDateString('pt-BR')
        },
        {
            label: 'Acoes',
            field: 'actions',
            sortable: false,
            render: (user) => (
                <ActionButtons
                    onView={() => handleViewUser(user)}
                    onEdit={canEditUser(user) ? () => handleEditUser(user) : null}
                    onDelete={canDeleteUser(user) && hasPermission(PERMISSIONS.DELETE_USERS) ? () => handleDelete(user.id, user.name) : null}
                />
            )
        }
    ];

    return (
        <>
            <Head title="Gerenciamento de Usuarios" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6">
                        <div className="flex justify-between items-center">
                            <div>
                                <h1 className="text-3xl font-bold text-gray-900">
                                    Usuarios
                                </h1>
                                <p className="mt-1 text-sm text-gray-600">
                                    Gerencie os usuarios e seus niveis de acesso
                                </p>
                            </div>
                            <div className="flex gap-3">
                                {hasPermission(PERMISSIONS.CREATE_USERS) && (
                                    <Button
                                        onClick={() => setShowCreateModal(true)}
                                        variant="primary"
                                        icon={PlusIcon}
                                    >
                                        Novo Usuario
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Filtros */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                            {/* Filtro de Nivel de Acesso */}
                            <div>
                                <label htmlFor="role-filter" className="block text-sm font-medium text-gray-700 mb-2">
                                    Filtrar por Nivel de Acesso
                                </label>
                                <select
                                    id="role-filter"
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    value={filters?.role || ''}
                                    onChange={(e) => {
                                        const currentUrl = new URL(window.location);
                                        if (e.target.value) {
                                            currentUrl.searchParams.set('role', e.target.value);
                                        } else {
                                            currentUrl.searchParams.delete('role');
                                        }
                                        currentUrl.searchParams.delete('page');
                                        router.visit(currentUrl.toString(), {
                                            preserveState: true,
                                            preserveScroll: true,
                                        });
                                    }}
                                >
                                    <option value="">Todos os niveis</option>
                                    {Object.entries(roles).map(([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            {/* Spacer */}
                            <div></div>

                            {/* Botao Limpar Filtros */}
                            <div>
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    className="h-[42px] w-[150px]"
                                    onClick={() => {
                                        router.visit('/users', {
                                            preserveState: true,
                                            preserveScroll: true,
                                        });
                                    }}
                                    disabled={!filters?.role}
                                    icon={XMarkIcon}
                                >
                                    Limpar Filtros
                                </Button>
                            </div>
                        </div>
                    </div>

                    {/* DataTable */}
                    <DataTable
                        data={users}
                        columns={columns}
                        searchPlaceholder="Pesquisar usuarios por nome ou email..."
                        emptyMessage="Nenhum usuario encontrado"
                        onRowClick={handleViewUser}
                        perPageOptions={[15, 25, 50, 100]}
                    />
                </div>
            </div>

            {/* Modais */}
            <UserCreateModal
                show={showCreateModal}
                onClose={closeModals}
                roles={roles}
                stores={stores}
            />

            <UserEditModal
                show={showEditModal && selectedUser !== null}
                onClose={closeModals}
                user={selectedUser}
                roles={roles}
                stores={stores}
            />

            <UserViewModal
                show={showViewModal && selectedUser !== null}
                onClose={closeModals}
                user={selectedUser}
                roles={roles}
                onEdit={(user) => {
                    closeModals();
                    handleEditUser(user);
                }}
                onDelete={(user) => {
                    closeModals();
                    handleDelete(user.id, user.name);
                }}
                canEdit={selectedUser && canEditUser(selectedUser)}
                canDelete={selectedUser && canDeleteUser(selectedUser) && hasPermission(PERMISSIONS.DELETE_USERS)}
            />
        </>
    );
}
