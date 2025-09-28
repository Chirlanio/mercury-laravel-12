import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';

export default function Admin({ auth }) {
    const { hasPermission } = usePermissions();

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="Painel Administrativo" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <div className="mb-6">
                                <h2 className="text-2xl font-bold text-gray-900">
                                    Painel Administrativo
                                </h2>
                                <p className="mt-1 text-sm text-gray-600">
                                    Acesso a funcionalidades administrativas do sistema.
                                </p>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                {/* Card de Gerenciamento de Usuários */}
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
                                                    Gerenciar Usuários
                                                </h3>
                                                <p className="text-sm text-gray-600">
                                                    Criar, editar e gerenciar usuários do sistema
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

                                {/* Card de Configurações */}
                                {hasPermission(PERMISSIONS.MANAGE_SETTINGS) && (
                                    <div className="bg-white p-6 border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                                        <div className="flex items-center">
                                            <div className="flex-shrink-0">
                                                <svg className="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                </svg>
                                            </div>
                                            <div className="ml-3">
                                                <h3 className="text-lg font-medium text-gray-900">
                                                    Configurações
                                                </h3>
                                                <p className="text-sm text-gray-600">
                                                    Configurações gerais do sistema
                                                </p>
                                            </div>
                                        </div>
                                        <div className="mt-4">
                                            <span className="text-gray-400 text-sm">
                                                Em desenvolvimento
                                            </span>
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
                                                    Histórico de atividades dos usuários
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

                                {/* Card de Relatórios */}
                                <div className="bg-white p-6 border border-gray-200 rounded-lg shadow-sm hover:shadow-md transition-shadow">
                                    <div className="flex items-center">
                                        <div className="flex-shrink-0">
                                            <svg className="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                            </svg>
                                        </div>
                                        <div className="ml-3">
                                            <h3 className="text-lg font-medium text-gray-900">
                                                Relatórios
                                            </h3>
                                            <p className="text-sm text-gray-600">
                                                Relatórios e estatísticas do sistema
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
                            <div className="mt-8 bg-blue-50 p-4 rounded-lg">
                                <div className="flex">
                                    <div className="flex-shrink-0">
                                        <svg className="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                                        </svg>
                                    </div>
                                    <div className="ml-3">
                                        <h3 className="text-sm font-medium text-blue-800">
                                            Informações de Acesso
                                        </h3>
                                        <div className="mt-2 text-sm text-blue-700">
                                            <p>
                                                Você está logado como <strong>{auth.user.name}</strong> com nível de acesso <strong>Administrador</strong>.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}