import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import { useConfirm } from '@/Hooks/useConfirm';
import UserCreateModal from '@/Components/UserCreateModal';
import UserEditModal from '@/Components/UserEditModal';
import UserViewModal from '@/Components/UserViewModal';
import DeleteConfirmModal from '@/Components/Shared/DeleteConfirmModal';
import DataTable from '@/Components/DataTable';
import UserAvatar from '@/Components/UserAvatar';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import PageHeader from '@/Components/Shared/PageHeader';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import {
    XMarkIcon,
    UsersIcon,
    ShieldCheckIcon,
    UserIcon,
    CalendarDaysIcon,
} from '@heroicons/react/24/outline';

const ROLE_VARIANT_MAP = {
    super_admin: 'danger',
    admin: 'info',
    support: 'warning',
    user: 'success',
};

export default function Index({ auth, users = { data: [], links: [] }, roles = {}, stores = [], filters = {}, stats = {} }) {
    const [processing, setProcessing] = useState(false);
    const { canEditUser, canDeleteUser, canManageRole, hasPermission } = usePermissions();
    const { modals, selected, openModal, closeModal } = useModalManager(['create', 'edit', 'view']);
    const { confirm, ConfirmDialogComponent } = useConfirm();
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleting, setDeleting] = useState(false);

    const handleRoleChange = async (userId, newRole) => {
        const confirmed = await confirm({
            title: 'Alterar Nível de Acesso',
            message: 'Tem certeza que deseja alterar o nível de acesso deste usuário?',
            confirmText: 'Alterar',
            type: 'warning',
        });

        if (confirmed) {
            setProcessing(true);
            router.patch(`/users/${userId}/role`,
                { role: newRole },
                {
                    onFinish: () => setProcessing(false),
                    preserveScroll: true,
                    onError: (errors) => {
                        console.error('Erro ao alterar nível de acesso:', errors);
                    },
                }
            );
        }
    };

    const handleConfirmDelete = () => {
        if (!deleteTarget) return;
        setDeleting(true);
        router.delete(`/users/${deleteTarget.id}`, {
            preserveScroll: true,
            onSuccess: () => { setDeleteTarget(null); setDeleting(false); },
            onError: () => setDeleting(false),
        });
    };

    const handleEditUser = (user) => {
        openModal('edit', user);
    };

    const handleViewUser = (user) => {
        openModal('view', user);
    };

    const statisticsCards = [
        {
            label: 'Total de Usuários',
            value: stats.total ?? 0,
            format: 'number',
            icon: UsersIcon,
            color: 'indigo',
        },
        {
            label: 'Administradores',
            value: stats.admins ?? 0,
            format: 'number',
            icon: ShieldCheckIcon,
            color: 'red',
            sub: 'Super Admin + Admin',
        },
        {
            label: 'Suporte',
            value: stats.support ?? 0,
            format: 'number',
            icon: UserIcon,
            color: 'yellow',
        },
        {
            label: 'Usuários Padrão',
            value: stats.users ?? 0,
            format: 'number',
            icon: UserIcon,
            color: 'green',
        },
        {
            label: 'Novos este Mês',
            value: stats.new_this_month ?? 0,
            format: 'number',
            icon: CalendarDaysIcon,
            color: 'blue',
        },
    ];

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
                        <div className="text-sm text-gray-500">{user.email}</div>
                    </div>
                </div>
            ),
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
                        className={`text-sm rounded-full px-3 py-1 font-medium border-0 cursor-pointer
                            ${ROLE_VARIANT_MAP[user.role] === 'danger' ? 'bg-red-100 text-red-800' : ''}
                            ${ROLE_VARIANT_MAP[user.role] === 'info' ? 'bg-blue-100 text-blue-800' : ''}
                            ${ROLE_VARIANT_MAP[user.role] === 'warning' ? 'bg-yellow-100 text-yellow-800' : ''}
                            ${ROLE_VARIANT_MAP[user.role] === 'success' ? 'bg-green-100 text-green-800' : ''}
                        `}
                    >
                        {Object.entries(roles).map(([value, label]) => (
                            canManageRole(value) ? (
                                <option key={value} value={value}>{label}</option>
                            ) : null
                        ))}
                    </select>
                ) : (
                    <StatusBadge variant={ROLE_VARIANT_MAP[user.role] || 'gray'}>
                        {roles[user.role] || user.role}
                    </StatusBadge>
                )
            ),
        },
        {
            label: 'Criado em',
            field: 'created_at',
            sortable: true,
            render: (user) => new Date(user.created_at).toLocaleDateString('pt-BR'),
        },
        {
            label: 'Ações',
            field: 'actions',
            sortable: false,
            render: (user) => (
                <ActionButtons
                    onView={() => handleViewUser(user)}
                    onEdit={canEditUser(user) ? () => handleEditUser(user) : null}
                    onDelete={canDeleteUser(user) && hasPermission(PERMISSIONS.DELETE_USERS) ? () => setDeleteTarget(user) : null}
                />
            ),
        },
    ];

    return (
        <>
            <Head title="Gerenciamento de Usuários" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <PageHeader
                        title="Usuários"
                        subtitle="Gerencie os usuários e seus níveis de acesso"
                        actions={[
                            {
                                type: 'create',
                                label: 'Novo Usuário',
                                onClick: () => openModal('create'),
                                visible: hasPermission(PERMISSIONS.CREATE_USERS),
                            },
                        ]}
                    />

                    {/* Estatísticas */}
                    <StatisticsGrid cards={statisticsCards} />

                    {/* Filtros */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                            <div>
                                <label htmlFor="role-filter" className="block text-sm font-medium text-gray-700 mb-2">
                                    Filtrar por Nível de Acesso
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
                                    <option value="">Todos os níveis</option>
                                    {Object.entries(roles).map(([value, label]) => (
                                        <option key={value} value={value}>{label}</option>
                                    ))}
                                </select>
                            </div>

                            <div></div>

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
                        searchPlaceholder="Pesquisar usuários por nome ou email..."
                        emptyMessage="Nenhum usuário encontrado"
                        onRowClick={handleViewUser}
                        perPageOptions={[15, 25, 50, 100]}
                    />
                </div>
            </div>

            {/* Modais */}
            <UserCreateModal
                show={modals.create}
                onClose={() => closeModal('create')}
                roles={roles}
                stores={stores}
            />

            <UserEditModal
                show={modals.edit && selected !== null}
                onClose={() => closeModal('edit')}
                user={selected}
                roles={roles}
                stores={stores}
            />

            <UserViewModal
                show={modals.view && selected !== null}
                onClose={() => closeModal('view')}
                user={selected}
                roles={roles}
                onEdit={(user) => {
                    closeModal('view', false);
                    handleEditUser(user);
                }}
                onDelete={(user) => {
                    closeModal('view');
                    setDeleteTarget(user);
                }}
                canEdit={selected && canEditUser(selected)}
                canDelete={selected && canDeleteUser(selected) && hasPermission(PERMISSIONS.DELETE_USERS)}
            />

            {/* Delete Confirm Modal */}
            <DeleteConfirmModal
                show={deleteTarget !== null}
                onClose={() => setDeleteTarget(null)}
                onConfirm={handleConfirmDelete}
                itemType="usuário"
                itemName={deleteTarget?.name}
                details={[
                    { label: 'E-mail', value: deleteTarget?.email },
                    { label: 'Nível', value: deleteTarget?.role ? (roles[deleteTarget.role] || deleteTarget.role) : null },
                ]}
                processing={deleting}
            />

            <ConfirmDialogComponent />
        </>
    );
}
