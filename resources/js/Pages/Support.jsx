import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';

export default function Support({ auth }) {
    const { hasPermission } = usePermissions();

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="Painel de Suporte" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <div className="mb-6">
                                <h2 className="text-2xl font-bold text-gray-900">
                                    Painel de Suporte
                                </h2>
                                <p className="mt-1 text-sm text-gray-600">
                                    Ferramentas e recursos para suporte ao cliente.
                                </p>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                {/* Card de Visualizar Usuários */}
                                {hasPermission(PERMISSIONS.VIEW_USERS) && (
                                    <div className="bg-white p-6 border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                                        <div className="flex items-center">
                                            <div className="flex-shrink-0">
                                                <svg className="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                                                </svg>
                                            </div>
                                            <div className="ml-3">
                                                <h3 className="text-lg font-medium text-gray-900">
                                                    Consultar Usuários
                                                </h3>
                                                <p className="text-sm text-gray-600">
                                                    Visualizar informações dos usuários
                                                </p>
                                            </div>
                                        </div>
                                        <div className="mt-4">
                                            <a
                                                href={route('users.index')}
                                                className="text-indigo-600 hover:text-indigo-900 text-sm font-medium"
                                            >
                                                Acessar →
                                            </a>
                                        </div>
                                    </div>
                                )}

                                {/* Card de Logs de Atividade */}
                                {hasPermission(PERMISSIONS.VIEW_ACTIVITY_LOGS) && (
                                    <div className="bg-white p-6 border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                                        <div className="flex items-center">
                                            <div className="flex-shrink-0">
                                                <svg className="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                            </div>
                                            <div className="ml-3">
                                                <h3 className="text-lg font-medium text-gray-900">
                                                    Logs de Atividade
                                                </h3>
                                                <p className="text-sm text-gray-600">
                                                    Consultar logs e atividades dos usuários
                                                </p>
                                            </div>
                                        </div>
                                        <div className="mt-4">
                                            <a
                                                href={route('activity-logs.index')}
                                                className="text-yellow-600 hover:text-yellow-900 text-sm font-medium"
                                            >
                                                Acessar →
                                            </a>
                                        </div>
                                    </div>
                                )}

                                {/* Card de Documentação */}
                                <div className="bg-white p-6 border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                                    <div className="flex items-center">
                                        <div className="flex-shrink-0">
                                            <svg className="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C20.832 18.477 19.246 18 17.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                            </svg>
                                        </div>
                                        <div className="ml-3">
                                            <h3 className="text-lg font-medium text-gray-900">
                                                Documentação
                                            </h3>
                                            <p className="text-sm text-gray-600">
                                                Manuais e guias de suporte
                                            </p>
                                        </div>
                                    </div>
                                    <div className="mt-4">
                                        <span className="text-gray-400 text-sm">
                                            Em desenvolvimento
                                        </span>
                                    </div>
                                </div>

                                {/* Card de FAQ */}
                                <div className="bg-white p-6 border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                                    <div className="flex items-center">
                                        <div className="flex-shrink-0">
                                            <svg className="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                        </div>
                                        <div className="ml-3">
                                            <h3 className="text-lg font-medium text-gray-900">
                                                FAQ
                                            </h3>
                                            <p className="text-sm text-gray-600">
                                                Perguntas frequentes dos usuários
                                            </p>
                                        </div>
                                    </div>
                                    <div className="mt-4">
                                        <span className="text-gray-400 text-sm">
                                            Em desenvolvimento
                                        </span>
                                    </div>
                                </div>

                                {/* Card de Tickets */}
                                <div className="bg-white p-6 border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                                    <div className="flex items-center">
                                        <div className="flex-shrink-0">
                                            <svg className="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                                            </svg>
                                        </div>
                                        <div className="ml-3">
                                            <h3 className="text-lg font-medium text-gray-900">
                                                Sistema de Tickets
                                            </h3>
                                            <p className="text-sm text-gray-600">
                                                Gerenciar tickets de suporte
                                            </p>
                                        </div>
                                    </div>
                                    <div className="mt-4">
                                        <span className="text-gray-400 text-sm">
                                            Em desenvolvimento
                                        </span>
                                    </div>
                                </div>

                                {/* Card de Chat */}
                                <div className="bg-white p-6 border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                                    <div className="flex items-center">
                                        <div className="flex-shrink-0">
                                            <svg className="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                            </svg>
                                        </div>
                                        <div className="ml-3">
                                            <h3 className="text-lg font-medium text-gray-900">
                                                Chat de Suporte
                                            </h3>
                                            <p className="text-sm text-gray-600">
                                                Atendimento em tempo real
                                            </p>
                                        </div>
                                    </div>
                                    <div className="mt-4">
                                        <span className="text-gray-400 text-sm">
                                            Em desenvolvimento
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {/* Informações de acesso */}
                            <div className="mt-8 bg-green-50 p-4 rounded-lg">
                                <div className="flex">
                                    <div className="flex-shrink-0">
                                        <svg className="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                                        </svg>
                                    </div>
                                    <div className="ml-3">
                                        <h3 className="text-sm font-medium text-green-800">
                                            Informações de Acesso
                                        </h3>
                                        <div className="mt-2 text-sm text-green-700">
                                            <p>
                                                Você está logado como <strong>{auth.user.name}</strong> com nível de acesso <strong>Suporte</strong>.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Estatísticas rápidas */}
                            <div className="mt-8 grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div className="bg-gray-50 p-4 rounded-lg text-center">
                                    <div className="text-2xl font-bold text-gray-900">-</div>
                                    <div className="text-sm text-gray-600">Tickets Abertos</div>
                                </div>
                                <div className="bg-gray-50 p-4 rounded-lg text-center">
                                    <div className="text-2xl font-bold text-gray-900">-</div>
                                    <div className="text-sm text-gray-600">Usuários Ativos</div>
                                </div>
                                <div className="bg-gray-50 p-4 rounded-lg text-center">
                                    <div className="text-2xl font-bold text-gray-900">-</div>
                                    <div className="text-sm text-gray-600">Resolução Média</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}