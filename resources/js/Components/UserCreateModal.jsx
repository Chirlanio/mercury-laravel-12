import Modal from '@/Components/Modal';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import ImageUpload from '@/Components/ImageUpload';
import { useForm } from '@inertiajs/react';

export default function UserCreateModal({ show, onClose, roles = {}, stores = [] }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        nickname: '',
        email: '',
        username: '',
        password: '',
        password_confirmation: '',
        role: 'user',
        avatar: null,
        store_id: '',
        status_id: '1',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('users.store'), {
            onSuccess: () => {
                reset();
                onClose();
            },
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

    return (
        <Modal show={show} onClose={handleClose} title="Criar Novo Usuário" maxWidth="85vw">
            <div className="mb-4">
                <p className="text-sm text-gray-600">
                    Preencha os dados do novo usuário. Uma senha temporária será criada.
                </p>
            </div>

            <form onSubmit={submit} className="space-y-4">
                <div className="bg-gradient-to-br from-blue-50 to-indigo-50 p-3 rounded-lg border-2 border-dashed border-blue-200">
                    <ImageUpload
                        label="📸 Foto do Usuário (Opcional)"
                        value={data.avatar}
                        onChange={(file) => setData('avatar', file)}
                        error={errors.avatar}
                        maxSize={2}
                        className="mb-0"
                    />
                    <p className="text-xs text-blue-600 mt-2 text-center font-medium">
                        ✨ Adicione uma foto para personalizar o perfil
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

                    <div>
                        <InputLabel htmlFor="password" value="Senha" />
                        <TextInput
                            id="password"
                            type="password"
                            name="password"
                            value={data.password}
                            className="mt-1 block w-full"
                            autoComplete="new-password"
                            onChange={(e) => setData('password', e.target.value)}
                            required
                            placeholder="Digite uma senha temporária"
                        />
                        <InputError message={errors.password} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel
                            htmlFor="password_confirmation"
                            value="Confirmar Senha"
                        />
                        <TextInput
                            id="password_confirmation"
                            type="password"
                            name="password_confirmation"
                            value={data.password_confirmation}
                            className="mt-1 block w-full"
                            autoComplete="new-password"
                            onChange={(e) =>
                                setData('password_confirmation', e.target.value)
                            }
                            required
                            placeholder="Confirme a senha"
                        />
                        <InputError
                            message={errors.password_confirmation}
                            className="mt-2"
                        />
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

                <div className="bg-gray-50 p-3 rounded-lg">
                    <h4 className="text-sm font-medium text-gray-900 mb-1">
                        Informações importantes:
                    </h4>
                    <ul className="text-xs text-gray-600 space-y-0.5">
                        <li>• O e-mail será verificado automaticamente</li>
                        <li>• O usuário receberá acesso imediato ao sistema</li>
                        <li>• Você pode alterar o nível de acesso posteriormente</li>
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
                        {processing ? 'Criando...' : 'Criar Usuário'}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}
