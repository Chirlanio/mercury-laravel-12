import Modal from '@/Components/Modal';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import ImageUpload from '@/Components/ImageUpload';
import UserAvatar from '@/Components/UserAvatar';
import { useForm, router } from '@inertiajs/react';
import { useEffect } from 'react';
import { TrashIcon } from '@heroicons/react/24/outline';

export default function UserEditModal({ show, onClose, user, roles = {}, stores = [] }) {
    const { data, setData, processing, errors, reset } = useForm({
        name: '',
        nickname: '',
        email: '',
        username: '',
        role: 'user',
        avatar: null,
        remove_avatar: false,
        store_id: '',
        status_id: '1',
    });

    useEffect(() => {
        if (user && show) {
            setData({
                name: user.name || '',
                nickname: user.nickname || '',
                email: user.email || '',
                username: user.username || '',
                role: user.role || 'user',
                avatar: null,
                remove_avatar: false,
                store_id: user.store_id || '',
                status_id: user.status_id || '1',
            });
        } else if (!show) {
            // Limpar dados quando modal fecha
            reset();
        }
    }, [user, show]);

    const submit = (e) => {
        e.preventDefault();

        const postData = {
            ...data,
            _method: 'put',
        };

        if (!(postData.avatar instanceof File)) {
            delete postData.avatar;
        }

        router.post(route('users.update', user.id), postData, {
            forceFormData: true,
            onSuccess: () => onClose(),
            onError: (errors) => {
                console.log('Validation errors:', errors);
            },
        });
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

    const handleClose = () => {
        reset();
        onClose();
    };

    if (!user) return null;

    return (
        <Modal show={show} onClose={handleClose} title="Editar Usuário" maxWidth="85vw">
            <div className="mb-4">
                <div className="bg-gray-50 p-3 rounded-lg">
                    <h4 className="text-sm font-medium text-gray-900 mb-1">
                        Informações do Usuário
                    </h4>
                    <div className="text-xs text-gray-600 space-y-0.5">
                        <p><span className="font-medium">Criado em:</span> {new Date(user.created_at).toLocaleDateString('pt-BR')}</p>
                        <p><span className="font-medium">Email verificado:</span> {user.email_verified_at ? 'Sim' : 'Não'}</p>
                    </div>
                </div>
            </div>

            <form onSubmit={submit} className="space-y-4">
                <div className="bg-gradient-to-br from-blue-50 to-indigo-50 p-4 rounded-lg border-2 border-dashed border-blue-200">
                    <div className="flex items-center space-x-4 mb-4">
                        <UserAvatar user={user} size="lg" />
                        <div>
                            <h4 className="text-sm font-medium text-gray-900">Foto atual</h4>
                            <p className="text-xs text-gray-500">
                                {user.avatar ? 'O usuário possui uma foto personalizada' : 'Usando avatar padrão com iniciais'}
                            </p>
                        </div>
                    </div>

                    <ImageUpload
                        label="📸 Nova Foto do Usuário (Opcional)"
                        value={data.avatar}
                        onChange={(file) => setData('avatar', file)}
                        error={errors.avatar}
                        maxSize={2}
                        className="mb-3"
                    />

                    {user.avatar && (
                        <div className="flex items-center justify-between p-2 bg-white rounded border border-gray-200">
                            <span className="text-xs text-gray-600">Remover foto atual</span>
                            <button
                                type="button"
                                onClick={() => setData('remove_avatar', !data.remove_avatar)}
                                className={`flex items-center space-x-1 px-2 py-1 rounded text-xs font-medium transition-colors ${
                                    data.remove_avatar
                                        ? 'bg-red-100 text-red-700 hover:bg-red-200'
                                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                }`}
                            >
                                <TrashIcon className="h-3 w-3" />
                                <span>{data.remove_avatar ? 'Cancelar remoção' : 'Remover foto'}</span>
                            </button>
                        </div>
                    )}

                    <p className="text-xs text-blue-600 mt-2 text-center font-medium">
                        ✨ Atualize a foto para personalizar o perfil
                    </p>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div className="lg:col-span-2">
                        <InputLabel htmlFor="name" value="Nome Completo" />
                        <TextInput
                            id="name"
                            name="name"
                            value={data.name}
                            className="mt-1 block w-full"
                            autoComplete="name"
                            isFocused={true}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                            placeholder="Digite o nome completo"
                        />
                        <InputError message={errors.name} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="nickname" value="Apelido/Nome Abreviado" />
                        <TextInput
                            id="nickname"
                            name="nickname"
                            value={data.nickname}
                            className="mt-1 block w-full"
                            onChange={(e) => setData('nickname', e.target.value)}
                            placeholder="Digite o apelido (opcional)"
                        />
                        <InputError message={errors.nickname} className="mt-2" />
                    </div>

                    <div className="lg:col-span-2">
                        <InputLabel htmlFor="email" value="E-mail" />
                        <TextInput
                            id="email"
                            type="email"
                            name="email"
                            value={data.email}
                            className="mt-1 block w-full"
                            autoComplete="username"
                            onChange={(e) => setData('email', e.target.value)}
                            required
                            placeholder="Digite o e-mail do usuário"
                        />
                        <InputError message={errors.email} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="username" value="Nome de Usuário (Login)" />
                        <TextInput
                            id="username"
                            name="username"
                            value={data.username}
                            className="mt-1 block w-full"
                            onChange={(e) => setData('username', e.target.value)}
                            placeholder="Digite o nome de usuário para login (opcional)"
                        />
                        <InputError message={errors.username} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="store_id" value="Loja" />
                        <select
                            id="store_id"
                            name="store_id"
                            value={data.store_id}
                            onChange={(e) => setData('store_id', e.target.value)}
                            className="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                            required
                        >
                            <option value="">Selecione uma loja</option>
                            {stores.map((store) => (
                                <option key={store.code} value={store.code}>
                                    {store.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.store_id} className="mt-2" />
                    </div>
                </div>

                <div>
                    <InputLabel htmlFor="role" value="Nível de Acesso" />
                    <div className="mt-2 grid grid-cols-2 gap-2">
                        {Object.entries(roles).map(([value, label]) => (
                            <label
                                key={value}
                                className={`flex items-center p-2 border rounded-lg cursor-pointer transition-colors ${
                                    data.role === value
                                        ? 'border-indigo-500 bg-indigo-50'
                                        : 'border-gray-200 hover:border-gray-300'
                                }`}
                            >
                                <input
                                    type="radio"
                                    name="role"
                                    value={value}
                                    checked={data.role === value}
                                    onChange={(e) => setData('role', e.target.value)}
                                    className="sr-only"
                                />
                                <div className="flex items-center justify-between w-full">
                                    <div className="flex items-center">
                                        <div className={`w-2 h-2 rounded-full mr-2 ${
                                            data.role === value ? 'bg-indigo-600' : 'bg-gray-300'
                                        }`}></div>
                                        <span className="text-sm font-medium text-gray-900">
                                            {label}
                                        </span>
                                    </div>
                                </div>
                            </label>
                        ))}
                    </div>
                    <InputError message={errors.role} className="mt-2" />
                </div>

                <div className="bg-blue-50 p-3 rounded-lg">
                    <h4 className="text-sm font-medium text-blue-900 mb-1">
                        Sobre alterações de nível de acesso:
                    </h4>
                    <ul className="text-xs text-blue-700 space-y-0.5">
                        <li>• Alterações são aplicadas imediatamente</li>
                        <li>• O usuário será notificado sobre mudanças de permissão</li>
                    </ul>
                </div>

                <div className="flex items-center justify-end space-x-4 pt-2 border-t border-gray-200">
                    <button
                        type="button"
                        onClick={handleClose}
                        className="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded transition-colors"
                    >
                        Cancelar
                    </button>
                    <PrimaryButton disabled={processing}>
                        {processing ? 'Salvando...' : 'Salvar Alterações'}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}