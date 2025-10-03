import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import ImageUpload from '@/Components/ImageUpload';
import UserAvatar from '@/Components/UserAvatar';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { TrashIcon } from '@heroicons/react/24/outline';

export default function Edit({ auth, user = {}, roles = {} }) {
    const { data, setData, post, patch, processing, errors } = useForm({
        name: user.name || '',
        email: user.email || '',
        role: user.role || 'user',
        avatar: null,
        remove_avatar: false,
    });

    const submit = (e) => {
        console.log('🚀 SUBMIT FUNCTION CALLED!');
        e.preventDefault();
        console.log('Form data being sent:', data);
        console.log('Avatar type:', typeof data.avatar);
        console.log('Avatar instanceof File:', data.avatar instanceof File);
        console.log('Avatar value:', data.avatar);

        post(route('users.update', user.id), {
            forceFormData: true,
            preserveScroll: true,
            onError: (errors) => {
                console.log('❌ Validation errors:', errors);
            },
            onSuccess: (response) => {
                console.log('✅ Success:', response);
            }
        });
    };

    const removeAvatar = () => {
        if (confirm('Tem certeza que deseja remover o avatar do usuário?')) {
            router.delete(route('users.removeAvatar', user.id), {
                onSuccess: () => {
                    // Atualizar a página para refletir a mudança
                    window.location.reload();
                }
            });
        }
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
                        Editar Usuário: {user.name || 'Carregando...'}
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
            <Head title={`Editar Usuário: ${user.name || 'Carregando...'}`} />

            <div className="py-12">
                <div className="max-w-2xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <div className="mb-6">
                                <h3 className="text-lg font-medium text-gray-900">
                                    Informações do Usuário
                                </h3>
                                <p className="mt-1 text-sm text-gray-600">
                                    Edite as informações e o nível de acesso do usuário.
                                </p>
                            </div>

                            {/* Info Card */}
                            <div className="mb-6 bg-gray-50 p-4 rounded-lg">
                                <div className="flex items-center space-x-4">
                                    <div className="flex-shrink-0">
                                        <UserAvatar
                                            user={user}
                                            size="xl"
                                            showTooltip={false}
                                        />
                                    </div>
                                    <div className="flex-1">
                                        <h4 className="text-lg font-medium text-gray-900">{user.name}</h4>
                                        <p className="text-sm text-gray-500">{user.email}</p>
                                        <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getRoleBadgeColor(user.role)}`}>
                                            {roles[user.role]}
                                        </span>
                                    </div>
                                    {user.has_custom_avatar && (
                                        <div>
                                            <button
                                                type="button"
                                                onClick={removeAvatar}
                                                className="text-red-600 hover:text-red-800 transition-colors p-2"
                                                title="Remover avatar"
                                            >
                                                <TrashIcon className="h-4 w-4" />
                                            </button>
                                        </div>
                                    )}
                                </div>
                                <div className="mt-4 text-sm text-gray-500">
                                    <p>Criado em: {new Date(user.created_at).toLocaleDateString('pt-BR')}</p>
                                    {user.email_verified_at && (
                                        <p>Email verificado em: {new Date(user.email_verified_at).toLocaleDateString('pt-BR')}</p>
                                    )}
                                </div>
                            </div>

                            <form onSubmit={submit} className="space-y-6">
                                <div>
                                    <ImageUpload
                                        label="Alterar Foto do Usuário"
                                        value={data.avatar}
                                        onChange={(file) => setData('avatar', file)}
                                        error={errors.avatar}
                                        maxSize={5}
                                        className="mb-6"
                                    />
                                </div>

                                <div>
                                    <InputLabel htmlFor="name" value="Nome" />

                                    <TextInput
                                        id="name"
                                        name="name"
                                        value={data.name}
                                        className="mt-1 block w-full"
                                        autoComplete="name"
                                        isFocused={true}
                                        onChange={(e) => setData('name', e.target.value)}
                                        required
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
                                    />

                                    <InputError message={errors.email} className="mt-2" />
                                </div>

                                <div>
                                    <InputLabel htmlFor="role" value="Nível de Acesso" />

                                    {user.id === auth.user.id ? (
                                        <div className="mt-1">
                                            <span className={`inline-flex px-3 py-2 text-sm font-medium rounded-md ${getRoleBadgeColor(user.role)}`}>
                                                {roles[user.role]} (Você não pode alterar seu próprio nível)
                                            </span>
                                        </div>
                                    ) : (
                                        <select
                                            id="role"
                                            name="role"
                                            value={data.role}
                                            className="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                            onChange={(e) => setData('role', e.target.value)}
                                            required
                                        >
                                            {Object.entries(roles).map(([value, label]) => (
                                                <option key={value} value={value}>
                                                    {label}
                                                </option>
                                            ))}
                                        </select>
                                    )}

                                    <InputError message={errors.role} className="mt-2" />
                                </div>

                                <div className="flex items-center justify-end space-x-4">
                                    <Link
                                        href={route('users.index')}
                                        className="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded"
                                    >
                                        Cancelar
                                    </Link>

                                    <PrimaryButton
                                        disabled={processing}
                                        onClick={() => console.log('🔴 BUTTON CLICKED!')}
                                    >
                                        {processing ? 'Salvando...' : 'Salvar Alterações'}
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