import { Head } from '@inertiajs/react';

export default function Welcome() {
    return (
        <>
            <Head title="Mercury - Plataforma de Gestão Empresarial" />

            <div className="min-h-screen bg-gray-50 flex flex-col items-center justify-center">
                <div className="text-center">
                    <h1 className="text-4xl font-bold text-gray-900 mb-4">
                        Mercury
                    </h1>
                    <p className="text-lg text-gray-600 mb-8">
                        Plataforma de Gestão Empresarial SaaS
                    </p>
                    <p className="text-sm text-gray-500">
                        Acesse sua empresa pelo subdomínio configurado.
                    </p>
                </div>
            </div>
        </>
    );
}
