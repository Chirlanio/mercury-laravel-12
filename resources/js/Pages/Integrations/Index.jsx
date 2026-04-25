import { Head, router } from '@inertiajs/react';
import { PlusIcon, ArrowPathIcon, CheckCircleIcon, XCircleIcon, ExclamationTriangleIcon } from '@heroicons/react/24/outline';
import { useState } from 'react';
import PageHeader from '@/Components/Shared/PageHeader';

const statusIcons = {
    success: <CheckCircleIcon className="h-5 w-5 text-green-500" />,
    error: <XCircleIcon className="h-5 w-5 text-red-500" />,
    running: <ArrowPathIcon className="h-5 w-5 text-blue-500 animate-spin" />,
};

const driverLabels = {
    database: 'Banco de Dados',
    rest_api: 'API REST',
    webhook: 'Webhook',
    cigam_sales: 'CIGAM - Vendas',
    cigam_products: 'CIGAM - Produtos',
};

export default function Index({ integrations, providers, drivers }) {
    return (
        <>
            <Head title="Integrações" />

            <div className="py-6">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <PageHeader
                        title="Integrações"
                        subtitle="Gerencie conexões com sistemas externos."
                    />

                    {integrations.length === 0 ? (
                        <div className="bg-white rounded-lg shadow p-12 text-center">
                            <ExclamationTriangleIcon className="mx-auto h-12 w-12 text-gray-400" />
                            <h3 className="mt-2 text-sm font-semibold text-gray-900">
                                Nenhuma integração configurada
                            </h3>
                            <p className="mt-1 text-sm text-gray-500">
                                Configure integrações para conectar com sistemas como CIGAM, SAP e outros.
                            </p>
                        </div>
                    ) : (
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {integrations.map((integration) => (
                                <div
                                    key={integration.id}
                                    className="bg-white rounded-lg shadow p-6 hover:shadow-md transition-shadow cursor-pointer"
                                    onClick={() => router.visit(route('integrations.show', integration.id))}
                                >
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <h3 className="text-lg font-medium text-gray-900">
                                                {integration.name}
                                            </h3>
                                            <p className="text-sm text-gray-500 mt-1">
                                                {driverLabels[integration.driver] || integration.driver}
                                            </p>
                                        </div>
                                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                            integration.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                                        }`}>
                                            {integration.is_active ? 'Ativo' : 'Inativo'}
                                        </span>
                                    </div>

                                    {integration.last_sync_at && (
                                        <div className="mt-4 flex items-center gap-2 text-sm text-gray-500">
                                            {statusIcons[integration.last_sync_status] || null}
                                            <span>
                                                Último sync: {new Date(integration.last_sync_at).toLocaleString('pt-BR')}
                                            </span>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
