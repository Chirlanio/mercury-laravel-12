import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import UserCreateModal from '@/Components/UserCreateModal';
import UserEditModal from '@/Components/UserEditModal';
import UserViewModal from '@/Components/UserViewModal';
import DataTable from '@/Components/DataTable';
import UserAvatar from '@/Components/UserAvatar';
import Button from '@/Components/Button';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/react/24/outline';

export default function Index({ auth, users = { data: [], links: [] }, roles = {}, filters = {} }) {
    const [processing, setProcessing] = useState(false);
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [showEditModal, setShowEditModal] = useState(false);
    const [showViewModal, setShowViewModal] = useState(false);
    const [selectedUser, setSelectedUser] = useState(null);
    const { canEditUser, canDeleteUser, canManageRole, hasPermission } = usePermissions();

    const handleRoleChange = async (userId, newRole) => {
        if (confirm('Tem certeza que deseja alterar o nível de acesso deste usuário?')) {
            setProcessing(true);

            router.patch(`/users/${userId}/role`,
                { role: newRole },
                {
                    onFinish: () => setProcessing(false),
                    preserveScroll: true,
                }
            );
        }
    };

    const handleDelete = (userId, userName) => {
        if (confirm(`Tem certeza que deseja deletar o usuário ${userName}?`)) {
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
            label: 'Usuário',
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
            label: 'Nível de Acesso',
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
            label: 'Ações',
            field: 'actions',
            sortable: false,
            render: (user) => (
                <div className="flex items-center space-x-2">
                    {canEditUser(user) && (
                        <Button
                            onClick={() => handleEditUser(user)}
                            variant="info"
                            size="sm"
                            icon={PencilIcon}
                            iconOnly
                        />
                    )}
                    {canDeleteUser(user) && hasPermission(PERMISSIONS.DELETE_USERS) && (
                        <Button
                            onClick={() => handleDelete(user.id, user.name)}
                            variant="danger"
                            size="sm"
                            icon={TrashIcon}
                            iconOnly
                        />
                    )}
                </div>
            )
        }
    ];

    return (
        <AuthenticatedLayout
            user={auth.user}
        >
            <Head title="Gerenciamento de Usuários" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {/* Header com título e botão */}
                    <div className="mb-6 flex justify-between items-center">
                        <div>
                            <h2 className="text-2xl font-bold text-gray-900">
                                Gerenciamento de Usuários
                            </h2>
                            <p className="mt-1 text-sm text-gray-600">
                                Gerencie os usuários e seus níveis de acesso.
                            </p>
                        </div>
                        {hasPermission(PERMISSIONS.CREATE_USERS) && (
                            <Button
                                onClick={() => setShowCreateModal(true)}
                                variant="primary"
                                size="md"
                            >
                                Novo Usuário
                            </Button>
                        )}
                    </div>

                    {/* DataTable */}
                    <DataTable
                        data={users}
                        columns={columns}
                        searchable={true}
                        searchPlaceholder="Buscar usuários..."
                        perPageOptions={[10, 25, 50, 100]}
                        emptyMessage="Nenhum usuário encontrado"
                    />
                </div>
            </div>

            {/* Modais */}
            <UserCreateModal
                show={showCreateModal}
                onClose={closeModals}
                roles={roles}
            />

            <UserEditModal
                show={showEditModal}
                onClose={closeModals}
                user={selectedUser}
                roles={roles}
            />

            <UserViewModal
                show={showViewModal}
                onClose={closeModals}
                user={selectedUser}
                roles={roles}
            />
        </AuthenticatedLayout>
    );
}