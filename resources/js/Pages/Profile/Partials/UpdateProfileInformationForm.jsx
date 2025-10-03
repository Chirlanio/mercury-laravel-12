import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import ImageUpload from '@/Components/ImageUpload';
import UserAvatar from '@/Components/UserAvatar';
import { Transition } from '@headlessui/react';
import { Link, useForm, usePage } from '@inertiajs/react';
import { TrashIcon } from '@heroicons/react/24/outline';

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
    className = '',
}) {
    const user = usePage().props.auth.user;

    const { data, setData, post, errors, processing, recentlySuccessful } =
        useForm({
            name: user.name,
            email: user.email,
            avatar: null,
            remove_avatar: false,
            _method: 'patch',
        });

    const submit = (e) => {
        e.preventDefault();

        post(route('profile.update'), {
            forceFormData: true,
        });
    };

    return (
        <section className={className}>
            <header>
<h2 className="text-lg font-medium text-gray-900">
                    Informações do Perfil
                </h2>

                <p className="mt-1 text-sm text-gray-600">
                    Atualize as informações de perfil e endereço de e-mail da sua conta.
                </p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-6">
                <div className="bg-gradient-to-br from-blue-50 to-indigo-50 p-4 rounded-lg border-2 border-dashed border-blue-200">
                    <div className="flex items-center space-x-4 mb-4">
                        <UserAvatar user={user} size="xl" />
                        <div>
                            <h4 className="text-sm font-medium text-gray-900">Foto do perfil</h4>
                            <p className="text-xs text-gray-500">
                                {user.avatar ? 'Você possui uma foto personalizada' : 'Usando avatar padrão com suas iniciais'}
                            </p>
                        </div>
                    </div>

                    <ImageUpload
                        label="📸 Nova Foto do Perfil (Opcional)"
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
                        ✨ Personalize seu perfil com uma foto
                    </p>
                </div>

                <div>
                    <InputLabel htmlFor="name" value="Nome" />

                    <TextInput
                        id="name"
                        className="mt-1 block w-full"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        isFocused
                        autoComplete="name"
                    />

                    <InputError className="mt-2" message={errors.name} />
                </div>

                <div>
                    <InputLabel htmlFor="email" value="E-mail" />

                    <TextInput
                        id="email"
                        type="email"
                        className="mt-1 block w-full"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        autoComplete="username"
                    />

                    <InputError className="mt-2" message={errors.email} />
                </div>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div>
                        <p className="mt-2 text-sm text-gray-800">
                            Seu endereço de e-mail não foi verificado.
                            <Link
                                href={route('verification.send')}
                                method="post"
                                as="button"
                                className="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            >
                                Clique aqui para reenviar o e-mail de verificação.
                            </Link>
                        </p>

                        {status === 'verification-link-sent' && (
                            <div className="mt-2 text-sm font-medium text-green-600">
                                Um novo link de verificação foi enviado para o seu
                                endereço de e-mail.
                            </div>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-4">
                    <PrimaryButton disabled={processing}>Salvar</PrimaryButton>

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm text-gray-600">
                            Salvo.
                        </p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
