import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

/**
 * Stub da listagem de Consignações — substituído na Fase 2b com UI
 * mobile-first completa (StatisticsGrid + DataTable + filtros + modais).
 * Mantido aqui para permitir que os testes HTTP do ConsignmentController
 * da Fase 2a passem sem depender da UI.
 */
export default function Index() {
    return (
        <AuthenticatedLayout>
            <Head title="Consignações" />
            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <p className="text-gray-500 dark:text-gray-400">
                            Módulo de Consignações — UI em construção.
                        </p>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
