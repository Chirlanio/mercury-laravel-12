import Modal from '@/Components/Modal';
import UserAvatar from '@/Components/UserAvatar';
import Button from '@/Components/Button';
import { PencilIcon, TrashIcon } from '@heroicons/react/24/outline';

export default function UserViewModal({ show, onClose, user, roles = {}, onEdit, onDelete, canEdit = false, canDelete = false }) {
    const getRoleBadgeColor = (role) => {
        const colors = {
            super_admin: 'bg-red-100 text-red-800',
            admin: 'bg-blue-100 text-blue-800',
            support: 'bg-yellow-100 text-yellow-800',
            user: 'bg-green-100 text-green-800'
        };
        return colors[role] || 'bg-gray-100 text-gray-800';
    };

    if (!user) return null;

    return (
        <Modal show={show} onClose={onClose} title="Detalhes do Usuário" maxWidth="85vw">
            <div className="space-y-6">
                {/* Avatar e informações básicas */}
                <div className="flex items-center space-x-4">
                    <div className="flex-shrink-0">
                        <UserAvatar user={user} size="2xl" />
                    </div>
                    <div className="flex-1">
                        <h3 className="text-xl font-semibold text-gray-900">{user.name}</h3>
                        {user.nickname && (
                            <p className="text-sm text-gray-500 italic">"{user.nickname}"</p>
                        )}
                        <p className="text-gray-600">{user.email}</p>
                        <div className="mt-2">
                            <span className={`inline-flex px-3 py-1 text-sm font-semibold rounded-full ${getRoleBadgeColor(user.role)}`}>
                                {roles[user.role] || user.role}
                            </span>
                        </div>
                    </div>
                </div>

                {/* Informações detalhadas */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="bg-gray-50 p-4 rounded-lg">
                        <h4 className="text-sm font-medium text-gray-900 mb-3">
                            Informações da Conta
                        </h4>
                        <div className="space-y-2 text-sm">
                            <div>
                                <span className="font-medium text-gray-600">ID:</span>
                                <span className="ml-2 text-gray-900">#{user.id}</span>
                            </div>
                            <div>
                                <span className="font-medium text-gray-600">Nome:</span>
                                <span className="ml-2 text-gray-900">{user.name}</span>
                            </div>
                            {user.nickname && (
                                <div>
                                    <span className="font-medium text-gray-600">Apelido:</span>
                                    <span className="ml-2 text-gray-900">{user.nickname}</span>
                                </div>
                            )}
                            <div>
                                <span className="font-medium text-gray-600">E-mail:</span>
                                <span className="ml-2 text-gray-900">{user.email}</span>
                            </div>
                            {user.username && (
                                <div>
                                    <span className="font-medium text-gray-600">Usuário:</span>
                                    <span className="ml-2 text-gray-900">{user.username}</span>
                                </div>
                            )}
                            <div>
                                <span className="font-medium text-gray-600">Nível de Acesso:</span>
                                <span className="ml-2 text-gray-900">{roles[user.role] || user.role}</span>
                            </div>
                            {user.store_id && (
                                <div>
                                    <span className="font-medium text-gray-600">Loja:</span>
                                    <span className="ml-2 text-gray-900">{user.store_id}</span>
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="bg-gray-50 p-4 rounded-lg">
                        <h4 className="text-sm font-medium text-gray-900 mb-3">
                            Status da Conta
                        </h4>
                        <div className="space-y-2 text-sm">
                            <div>
                                <span className="font-medium text-gray-600">Criado em:</span>
                                <span className="ml-2 text-gray-900">
                                    {new Date(user.created_at).toLocaleDateString('pt-BR', {
                                        year: 'numeric',
                                        month: 'long',
                                        day: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit'
                                    })}
                                </span>
                            </div>
                            <div>
                                <span className="font-medium text-gray-600">E-mail verificado:</span>
                                <span className={`ml-2 font-medium ${user.email_verified_at ? 'text-green-600' : 'text-red-600'}`}>
                                    {user.email_verified_at ? 'Verificado' : 'Não verificado'}
                                </span>
                            </div>
                            {user.email_verified_at && (
                                <div>
                                    <span className="font-medium text-gray-600">Verificado em:</span>
                                    <span className="ml-2 text-gray-900">
                                        {new Date(user.email_verified_at).toLocaleDateString('pt-BR', {
                                            year: 'numeric',
                                            month: 'long',
                                            day: 'numeric'
                                        })}
                                    </span>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Permissões baseadas no role */}
                <div className="bg-blue-50 p-4 rounded-lg">
                    <h4 className="text-sm font-medium text-blue-900 mb-3">
                        Permissões do Nível de Acesso
                    </h4>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        {user.role === 'super_admin' && (
                            <div>
                                <h5 className="font-medium text-blue-800 mb-2">Super Administrador</h5>
                                <ul className="text-blue-700 space-y-1">
                                    <li>• Acesso total ao sistema</li>
                                    <li>• Gerenciar todos os usuários</li>
                                    <li>• Alterar níveis de acesso</li>
                                    <li>• Configurações do sistema</li>
                                </ul>
                            </div>
                        )}
                        {user.role === 'admin' && (
                            <div>
                                <h5 className="font-medium text-blue-800 mb-2">Administrador</h5>
                                <ul className="text-blue-700 space-y-1">
                                    <li>• Gerenciar usuários (exceto super admin)</li>
                                    <li>• Acesso a relatórios</li>
                                    <li>• Configurações gerais</li>
                                    <li>• Suporte avançado</li>
                                </ul>
                            </div>
                        )}
                        {user.role === 'support' && (
                            <div>
                                <h5 className="font-medium text-blue-800 mb-2">Suporte</h5>
                                <ul className="text-blue-700 space-y-1">
                                    <li>• Visualizar usuários</li>
                                    <li>• Acesso a relatórios básicos</li>
                                    <li>• Suporte ao cliente</li>
                                    <li>• Documentação</li>
                                </ul>
                            </div>
                        )}
                        {user.role === 'user' && (
                            <div>
                                <h5 className="font-medium text-blue-800 mb-2">Usuário</h5>
                                <ul className="text-blue-700 space-y-1">
                                    <li>• Acesso básico ao sistema</li>
                                    <li>• Visualizar próprios dados</li>
                                    <li>• Funcionalidades padrão</li>
                                    <li>• Suporte básico</li>
                                </ul>
                            </div>
                        )}
                    </div>
                </div>

                {/* Ações disponíveis */}
                {(canEdit || canDelete) && (
                    <div className="bg-blue-50 p-4 rounded-lg">
                        <h4 className="text-sm font-medium text-blue-900 mb-3">
                            Ações Disponíveis
                        </h4>
                        <div className="flex flex-wrap gap-3">
                            {canEdit && onEdit && (
                                <Button
                                    onClick={() => onEdit(user)}
                                    variant="warning"
                                    size="md"
                                    icon={PencilIcon}
                                >
                                    Editar Usuário
                                </Button>
                            )}
                            {canDelete && onDelete && (
                                <Button
                                    onClick={() => onDelete(user)}
                                    variant="danger"
                                    size="md"
                                    icon={TrashIcon}
                                >
                                    Excluir Usuário
                                </Button>
                            )}
                        </div>
                    </div>
                )}

                <div className="flex justify-end space-x-3">
                    <button
                        type="button"
                        onClick={onClose}
                        className="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded transition-colors"
                    >
                        Fechar
                    </button>
                </div>
            </div>
        </Modal>
    );
}