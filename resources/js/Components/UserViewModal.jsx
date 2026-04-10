import StandardModal from '@/Components/StandardModal';
import UserAvatar from '@/Components/UserAvatar';
import { PencilIcon, TrashIcon, UserIcon, ShieldCheckIcon } from '@heroicons/react/24/outline';

const ROLE_COLORS = {
    super_admin: 'bg-red-100 text-red-800',
    admin: 'bg-blue-100 text-blue-800',
    support: 'bg-yellow-100 text-yellow-800',
    user: 'bg-green-100 text-green-800',
};

const ROLE_PERMISSIONS = {
    super_admin: { title: 'Super Administrador', items: ['Acesso total ao sistema', 'Gerenciar todos os usuários', 'Alterar níveis de acesso', 'Configurações do sistema'] },
    admin: { title: 'Administrador', items: ['Gerenciar usuários (exceto super admin)', 'Acesso a relatórios', 'Configurações gerais', 'Suporte avançado'] },
    support: { title: 'Suporte', items: ['Visualizar usuários', 'Acesso a relatórios básicos', 'Suporte ao cliente', 'Documentação'] },
    user: { title: 'Usuário', items: ['Acesso básico ao sistema', 'Visualizar próprios dados', 'Funcionalidades padrão', 'Suporte básico'] },
};

export default function UserViewModal({ show, onClose, user, roles = {}, onEdit, onDelete, canEdit = false, canDelete = false }) {
    if (!user) return null;

    const rolePerm = ROLE_PERMISSIONS[user.role];
    const fmtDate = (d) => d ? new Date(d).toLocaleDateString('pt-BR', { year: 'numeric', month: 'long', day: 'numeric' }) : '-';
    const fmtDateTime = (d) => d ? new Date(d).toLocaleDateString('pt-BR', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-';

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title={`${user.name}`}
            headerColor="bg-indigo-600"
            headerBadges={[{ text: roles[user.role] || user.role, className: ROLE_COLORS[user.role] || 'bg-gray-100 text-gray-800' }]}
            maxWidth="7xl"
            footer={(canEdit || canDelete) ? (
                <StandardModal.Footer>
                    {canEdit && onEdit && (
                        <button onClick={() => onEdit(user)}
                            className="inline-flex items-center px-4 py-2 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100 transition-colors">
                            <PencilIcon className="h-4 w-4 mr-1.5" /> Editar
                        </button>
                    )}
                    {canDelete && onDelete && (
                        <button onClick={() => onDelete(user)}
                            className="inline-flex items-center px-4 py-2 text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 transition-colors">
                            <TrashIcon className="h-4 w-4 mr-1.5" /> Excluir
                        </button>
                    )}
                    <button onClick={onClose}
                        className="ml-auto px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        Fechar
                    </button>
                </StandardModal.Footer>
            ) : (
                <StandardModal.Footer onCancel={onClose} cancelLabel="Fechar" />
            )}
        >
            {/* Avatar + Info básica */}
            <div className="flex items-center gap-4">
                <UserAvatar user={user} size="2xl" />
                <div>
                    <h3 className="text-lg font-semibold text-gray-900">{user.name}</h3>
                    {user.nickname && <p className="text-sm text-gray-500 italic">"{user.nickname}"</p>}
                    <p className="text-sm text-gray-600">{user.email}</p>
                </div>
            </div>

            {/* Grid de informações */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <StandardModal.Section title="Informações da Conta" icon={<UserIcon className="h-4 w-4" />}>
                    <div className="grid grid-cols-2 gap-x-4 gap-y-3">
                        <StandardModal.Field label="ID" value={`#${user.id}`} mono />
                        <StandardModal.Field label="Nome" value={user.name} />
                        {user.nickname && <StandardModal.Field label="Apelido" value={user.nickname} />}
                        <StandardModal.Field label="E-mail" value={user.email} />
                        {user.username && <StandardModal.Field label="Usuário" value={user.username} mono />}
                        <StandardModal.Field label="Nível de Acesso" value={roles[user.role] || user.role}
                            badge={user.role === 'super_admin' ? 'red' : user.role === 'admin' ? 'blue' : user.role === 'support' ? 'yellow' : 'green'} />
                        {user.store_id && <StandardModal.Field label="Loja" value={user.store_id} />}
                    </div>
                </StandardModal.Section>

                <StandardModal.Section title="Status da Conta" icon={<ShieldCheckIcon className="h-4 w-4" />}>
                    <div className="grid grid-cols-1 gap-y-3">
                        <StandardModal.Field label="Criado em" value={fmtDateTime(user.created_at)} />
                        <div>
                            <p className="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">E-mail verificado</p>
                            <span className={`inline-flex mt-0.5 px-2 py-0.5 rounded text-xs font-medium ${user.email_verified_at ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                                {user.email_verified_at ? 'Verificado' : 'Não verificado'}
                            </span>
                        </div>
                        {user.email_verified_at && (
                            <StandardModal.Field label="Verificado em" value={fmtDate(user.email_verified_at)} />
                        )}
                    </div>
                </StandardModal.Section>
            </div>

            {/* Permissões */}
            {rolePerm && (
                <StandardModal.Section title={`Permissões — ${rolePerm.title}`} icon={<ShieldCheckIcon className="h-4 w-4" />}>
                    <div className="grid grid-cols-2 gap-2">
                        {rolePerm.items.map((item, i) => (
                            <div key={i} className="flex items-center gap-2 text-sm text-gray-700">
                                <span className="h-1.5 w-1.5 rounded-full bg-indigo-500 shrink-0" />
                                {item}
                            </div>
                        ))}
                    </div>
                </StandardModal.Section>
            )}
        </StandardModal>
    );
}
