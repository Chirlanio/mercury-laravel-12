import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { ArrowLeftIcon } from '@heroicons/react/24/outline';

export default function Show({ auth, log }) {
    const getActionBadgeColor = (action) => {
        const colors = {
            create: 'bg-green-100 text-green-800',
            update: 'bg-blue-100 text-blue-800',
            delete: 'bg-red-100 text-red-800',
            login: 'bg-indigo-100 text-indigo-800',
            logout: 'bg-gray-100 text-gray-800',
        };
        return colors[action] || 'bg-gray-100 text-gray-800';
    };

    const formatDate = (dateString) => {
        const date = new Date(dateString);
        return {
            date: date.toLocaleDateString('pt-BR'),
            time: date.toLocaleTimeString('pt-BR'),
            full: date.toLocaleString('pt-BR')
        };
    };

    const formattedDate = formatDate(log.created_at);

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title={`Log de Atividade #${log.id}`} />

            <div className="py-12">
                <div className="max-w-4xl mx-auto sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6 flex items-center justify-between">
                        <div className="flex items-center space-x-4">
                            <button
                                onClick={() => router.visit('/activity-logs')}
                                className="flex items-center text-gray-600 hover:text-gray-900 transition-colors"
                            >
                                <ArrowLeftIcon className="h-5 w-5 mr-2" />
                                Voltar aos Logs
                            </button>
                        </div>
                    </div>

                    {/* Log Details Card */}
                    <div className="bg-white rounded-lg shadow-sm border overflow-hidden">
                        {/* Header do Card */}
                        <div className="bg-gray-50 px-6 py-4 border-b">
                            <div className="flex items-center justify-between">
                                <h1 className="text-xl font-semibold text-gray-900">
                                    Log de Atividade #{log.id}
                                </h1>
                                <span className={`inline-flex px-3 py-1 text-sm font-semibold rounded-full ${getActionBadgeColor(log.action)}`}>
                                    {log.action}
                                </span>
                            </div>
                        </div>

                        <div className="p-6">
                            {/* Informações Principais */}
                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                                {/* Usuário */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                        Usuário
                                    </label>
                                    {log.user ? (
                                        <div className="bg-gray-50 p-3 rounded-lg">
                                            <div className="font-medium text-gray-900">{log.user.name}</div>
                                            <div className="text-sm text-gray-600">{log.user.email}</div>
                                        </div>
                                    ) : (
                                        <div className="bg-gray-50 p-3 rounded-lg">
                                            <span className="text-gray-500">Sistema</span>
                                        </div>
                                    )}
                                </div>

                                {/* Data/Hora */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                        Data e Hora
                                    </label>
                                    <div className="bg-gray-50 p-3 rounded-lg">
                                        <div className="font-medium text-gray-900">{formattedDate.date}</div>
                                        <div className="text-sm text-gray-600">{formattedDate.time}</div>
                                    </div>
                                </div>

                                {/* Ação */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                        Ação
                                    </label>
                                    <div className="bg-gray-50 p-3 rounded-lg">
                                        <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getActionBadgeColor(log.action)}`}>
                                            {log.action}
                                        </span>
                                    </div>
                                </div>

                                {/* IP Address */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                        Endereço IP
                                    </label>
                                    <div className="bg-gray-50 p-3 rounded-lg">
                                        <span className="font-mono text-sm">{log.ip_address || '-'}</span>
                                    </div>
                                </div>
                            </div>

                            {/* Descrição */}
                            <div className="mb-6">
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Descrição
                                </label>
                                <div className="bg-gray-50 p-4 rounded-lg">
                                    <p className="text-gray-900">{log.description}</p>
                                </div>
                            </div>

                            {/* Detalhes Técnicos */}
                            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                                {/* URL */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                        URL
                                    </label>
                                    <div className="bg-gray-50 p-3 rounded-lg">
                                        <span className="font-mono text-xs break-all text-gray-800">
                                            {log.url || '-'}
                                        </span>
                                    </div>
                                </div>

                                {/* Método HTTP */}
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                        Método HTTP
                                    </label>
                                    <div className="bg-gray-50 p-3 rounded-lg">
                                        <span className="font-mono text-sm font-semibold text-blue-600">
                                            {log.method || '-'}
                                        </span>
                                    </div>
                                </div>

                                {/* Model Type */}
                                {log.model_type && (
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Tipo do Modelo
                                        </label>
                                        <div className="bg-gray-50 p-3 rounded-lg">
                                            <span className="font-mono text-sm">{log.model_type}</span>
                                        </div>
                                    </div>
                                )}

                                {/* Model ID */}
                                {log.model_id && (
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            ID do Modelo
                                        </label>
                                        <div className="bg-gray-50 p-3 rounded-lg">
                                            <span className="font-mono text-sm">{log.model_id}</span>
                                        </div>
                                    </div>
                                )}
                            </div>

                            {/* User Agent */}
                            {log.user_agent && (
                                <div className="mb-6">
                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                        User Agent
                                    </label>
                                    <div className="bg-gray-50 p-3 rounded-lg">
                                        <p className="font-mono text-xs break-all text-gray-700">
                                            {log.user_agent}
                                        </p>
                                    </div>
                                </div>
                            )}

                            {/* Alterações */}
                            {log.has_changes && log.changes && Object.keys(log.changes).length > 0 && (
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                        Alterações Registradas
                                    </label>
                                    <div className="bg-gray-50 p-4 rounded-lg">
                                        <div className="space-y-4">
                                            {Object.entries(log.changes).map(([field, change]) => (
                                                <div key={field} className="border-l-4 border-blue-500 pl-4">
                                                    <div className="font-medium text-gray-900 mb-2 capitalize">
                                                        {field.replace('_', ' ')}
                                                    </div>
                                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                        <div>
                                                            <div className="text-xs font-medium text-red-600 mb-1">
                                                                Valor Anterior
                                                            </div>
                                                            <div className="bg-red-50 p-2 rounded text-sm text-red-800 font-mono">
                                                                {change.old !== null ? String(change.old) : 'null'}
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <div className="text-xs font-medium text-green-600 mb-1">
                                                                Novo Valor
                                                            </div>
                                                            <div className="bg-green-50 p-2 rounded text-sm text-green-800 font-mono">
                                                                {change.new !== null ? String(change.new) : 'null'}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* Valores Brutos (para debug) */}
                            {(log.old_values || log.new_values) && (
                                <div className="mt-6 border-t pt-6">
                                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                        {log.old_values && (
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                                    Valores Antigos (JSON)
                                                </label>
                                                <div className="bg-gray-900 p-3 rounded-lg overflow-auto">
                                                    <pre className="text-green-400 text-xs font-mono">
                                                        {JSON.stringify(log.old_values, null, 2)}
                                                    </pre>
                                                </div>
                                            </div>
                                        )}

                                        {log.new_values && (
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                                    Novos Valores (JSON)
                                                </label>
                                                <div className="bg-gray-900 p-3 rounded-lg overflow-auto">
                                                    <pre className="text-green-400 text-xs font-mono">
                                                        {JSON.stringify(log.new_values, null, 2)}
                                                    </pre>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}