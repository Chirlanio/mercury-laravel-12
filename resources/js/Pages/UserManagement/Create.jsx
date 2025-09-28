import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import ImageUpload from '@/Components/ImageUpload';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Create({ auth, roles = {} }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: 'user',
        avatar: null,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('users.store'), {
            onSuccess: () => reset('password', 'password_confirmation', 'avatar'),
            forceFormData: true // Necessário para upload de arquivos
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

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex justify-between items-center">
                    <h2 className="font-semibold text-xl text-gray-800 leading-tight">
                        Criar Novo Usuário
                    </h2>
                    <Link
                        href={route('users.index')}
                        className="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded"
                    >
                        Voltar
                    </Link>
                </div>
            }
        >
            <Head title="Criar Novo Usuário" />

            <div className="py-12">
                <div className="max-w-2xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <div className="mb-6">
                                <h3 className="text-lg font-medium text-gray-900">
                                    Informações do Usuário
                                </h3>
                                <p className="mt-1 text-sm text-gray-600">
                                    Preencha os dados do novo usuário. Uma senha temporária será criada.
                                </p>
                            </div>

                            <form onSubmit={submit} className="space-y-6">
                                <div className="bg-gradient-to-br from-blue-50 to-indigo-50 p-4 rounded-lg border-2 border-dashed border-blue-200">
                                    <ImageUpload
                                        label="📸 Foto do Usuário (Opcional)"
                                        value={null}
                                        onChange={(file) => setData('avatar', file)}
                                        error={errors.avatar}
                                        maxSize={5}
                                        className="mb-0"
                                    />
                                    <p className="text-xs text-blue-600 mt-2 text-center font-medium">
                                        ✨ Adicione uma foto para personalizar o perfil do usuário
                                    </p>
                                    <p className="text-xs text-gray-500 mt-1 text-center">
                                        Isso ajudará na identificação visual do usuário no sistema
                                    </p>
                                </div>

                                <div>
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

                                <div>
                                    <InputLabel htmlFor="role" value="Nível de Acesso" />

                                    <div className="mt-2 space-y-2">
                                        {Object.entries(roles).map(([value, label]) => (
                                            <label
                                                key={value}
                                                className={`flex items-center p-3 border rounded-lg cursor-pointer transition-colors ${
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
                                                        <div className={`w-3 h-3 rounded-full mr-3 ${
                                                            data.role === value ? 'bg-indigo-600' : 'bg-gray-300'
                                                        }`}></div>
                                                        <span className="font-medium text-gray-900">
                                                            {label}
                                                        </span>
                                                    </div>
                                                    <span className={`px-2 py-1 text-xs font-semibold rounded-full ${getRoleBadgeColor(value)}`}>
                                                        {label}
                                                    </span>
                                                </div>
                                            </label>
                                        ))}
                                    </div>

                                    <InputError message={errors.role} className="mt-2" />
                                </div>

                                <div className="bg-gray-50 p-4 rounded-lg">
                                    <h4 className="text-sm font-medium text-gray-900 mb-2">
                                        Informações importantes:
                                    </h4>
                                    <ul className="text-sm text-gray-600 space-y-1">
                                        <li>• O e-mail será verificado automaticamente</li>
                                        <li>• O usuário receberá acesso imediato ao sistema</li>
                                        <li>• Você pode alterar o nível de acesso posteriormente</li>
                                        <li>• Recomende que o usuário altere a senha no primeiro acesso</li>
                                    </ul>
                                </div>

                                <div className="flex items-center justify-end space-x-4 pt-4">
                                    <Link
                                        href={route('users.index')}
                                        className="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded transition-colors"
                                    >
                                        Cancelar
                                    </Link>

                                    <PrimaryButton disabled={processing}>
                                        {processing ? 'Criando...' : 'Criar Usuário'}
                                    </PrimaryButton>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}