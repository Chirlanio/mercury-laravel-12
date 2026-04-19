import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

/**
 * Placeholder — substituído pela versão completa no Commit 4 da Fase 0.1.
 * Existe aqui para os testes de rota resolverem o componente Inertia.
 */
export default function Index({ costCenters, statistics }) {
    return (
        <AuthenticatedLayout>
            <Head title="Centros de Custo" />
            <div className="py-12">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <h1 className="text-2xl font-bold text-gray-900 mb-4">
                        Centros de Custo
                    </h1>
                    <p className="text-gray-600">
                        Carregando módulo... (placeholder — UI completa no próximo commit)
                    </p>
                    <div className="mt-4 text-sm text-gray-500">
                        Total: {statistics?.total ?? 0} · Ativos:{' '}
                        {statistics?.active ?? 0}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
