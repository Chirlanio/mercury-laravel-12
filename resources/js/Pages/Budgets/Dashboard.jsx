import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

/**
 * Placeholder — substituído pela versão completa no Commit 3 da Fase 3.
 */
export default function Dashboard({ budget, consumption }) {
    return (
        <AuthenticatedLayout>
            <Head title={`Dashboard — ${budget?.scope_label || 'Orçamento'}`} />
            <div className="py-12">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <h1 className="text-2xl font-bold text-gray-900 mb-2">
                        Dashboard de Consumo
                    </h1>
                    <p className="text-gray-600 mb-4">
                        {budget?.scope_label} — {budget?.year} · v{budget?.version_label}
                    </p>
                    <p className="text-sm text-gray-500">
                        (Placeholder — UI completa no próximo commit)
                    </p>
                    <div className="mt-4 bg-white rounded shadow p-4">
                        <p className="text-sm">
                            Previsto: R$ {consumption?.totals?.forecast?.toLocaleString('pt-BR')}
                        </p>
                        <p className="text-sm">
                            Realizado: R$ {consumption?.totals?.realized?.toLocaleString('pt-BR')}
                        </p>
                        <p className="text-sm font-semibold">
                            Utilização: {consumption?.totals?.utilization_pct}%
                        </p>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
