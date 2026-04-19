import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

/**
 * Placeholder — substituído pela versão completa no Commit 4 da Fase 0.3.
 */
export default function Index({ managementClasses, statistics }) {
    return (
        <AuthenticatedLayout>
            <Head title="Plano Gerencial" />
            <div className="py-12">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <h1 className="text-2xl font-bold text-gray-900 mb-4">
                        Plano de Contas Gerencial
                    </h1>
                    <p className="text-gray-600">
                        Carregando módulo... (placeholder)
                    </p>
                    <div className="mt-4 text-sm text-gray-500">
                        Total: {statistics?.total ?? 0} · Com vínculo contábil:{' '}
                        {statistics?.linked_to_accounting ?? 0}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
