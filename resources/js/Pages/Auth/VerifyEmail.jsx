import PrimaryButton from '@/Components/PrimaryButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function VerifyEmail({ status }) {
    const { post, processing } = useForm({});

    const submit = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title="Verificação de E-mail" />

            <div className="mb-4 text-sm text-gray-600">
                Obrigado por se cadastrar! Antes de começar, você poderia verificar
<<<<<<< HEAD
                seu endereço de e-mail clicando no link que acabamos de enviar para
                você? Se você não recebeu o e-mail, teremos prazer em enviar outro.
=======
                seu endereço de e-mail clicando no link que acabamos de enviar?
                Se você não recebeu o e-mail, teremos prazer em enviar outro.
>>>>>>> 84c9a03193f1b3486603586c0fd28b83b97a6e75
            </div>

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-sm font-medium text-green-600">
<<<<<<< HEAD
                    Um novo link de verificação foi enviado para o endereço de e-mail
                    que você forneceu durante o cadastro.
=======
                    Um novo link de verificação foi enviado para o endereço de
                    e-mail fornecido durante o registro.
>>>>>>> 84c9a03193f1b3486603586c0fd28b83b97a6e75
                </div>
            )}

            <form onSubmit={submit}>
                <div className="mt-4 flex items-center justify-between">
                    <PrimaryButton disabled={processing}>
                        Reenviar E-mail de Verificação
                    </PrimaryButton>

                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        Sair
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}
