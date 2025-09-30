import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';

export default function EmailSettings({ auth, settings }) {
    const { hasPermission } = usePermissions();

    if (!hasPermission(PERMISSIONS.MANAGE_SETTINGS)) {
        return (
            <AuthenticatedLayout user={auth.user}>
                <Head title="Configurações de E-mail" />
                <div className="py-12">
                    <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                            <div className="text-center text-red-600">
                                Você não tem permissão para acessar esta página.
                            </div>
                        </div>
                    </div>
                </div>
            </AuthenticatedLayout>
        );
    }

    const getStatusBadge = (value) => {
        return value ? (
            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                <svg className="mr-1.5 h-2 w-2 text-green-400" fill="currentColor" viewBox="0 0 8 8">
                    <circle cx="4" cy="4" r="3" />
                </svg>
                Configurado
            </span>
        ) : (
            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                <svg className="mr-1.5 h-2 w-2 text-red-400" fill="currentColor" viewBox="0 0 8 8">
                    <circle cx="4" cy="4" r="3" />
                </svg>
                Não configurado
            </span>
        );
    };

    const maskPassword = (password) => {
        if (!password) return 'Não configurado';
        return '•'.repeat(12);
    };

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="Configurações de E-mail" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6">
                        <div className="flex items-center justify-between">
                            <div>
                                <h2 className="text-2xl font-bold text-gray-900">
                                    Configurações do Servidor de E-mail
                                </h2>
                                <p className="mt-1 text-sm text-gray-600">
                                    Visualize as configurações do servidor SMTP para envio de e-mails.
                                </p>
                            </div>
                            <a
                                href={route('admin')}
                                className="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 transition"
                            >
                                <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                </svg>
                                Voltar
                            </a>
                        </div>
                    </div>

                    {/* Status Overview */}
                    <div className="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-blue-500">
                            <div className="flex items-center">
                                <div className="flex-shrink-0">
                                    <svg className="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                </div>
                                <div className="ml-4">
                                    <p className="text-sm font-medium text-gray-600">Driver</p>
                                    <p className="text-lg font-semibold text-gray-900">{settings.driver}</p>
                                </div>
                            </div>
                        </div>

                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-green-500">
                            <div className="flex items-center">
                                <div className="flex-shrink-0">
                                    <svg className="h-8 w-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                    </svg>
                                </div>
                                <div className="ml-4">
                                    <p className="text-sm font-medium text-gray-600">Status</p>
                                    <p className="text-lg font-semibold text-gray-900">
                                        {getStatusBadge(settings.host && settings.port)}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-purple-500">
                            <div className="flex items-center">
                                <div className="flex-shrink-0">
                                    <svg className="h-8 w-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                </div>
                                <div className="ml-4">
                                    <p className="text-sm font-medium text-gray-600">Criptografia</p>
                                    <p className="text-lg font-semibold text-gray-900">{settings.encryption || 'Nenhuma'}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Configurações Detalhadas */}
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">
                                Detalhes da Configuração
                            </h3>

                            <div className="space-y-6">
                                {/* Configurações do Servidor */}
                                <div>
                                    <h4 className="text-sm font-medium text-gray-700 mb-3 flex items-center">
                                        <svg className="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                                        </svg>
                                        Servidor SMTP
                                    </h4>
                                    <div className="bg-gray-50 rounded-lg p-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-600">Host</label>
                                            <p className="mt-1 text-sm text-gray-900 font-mono bg-white px-3 py-2 rounded border border-gray-300">
                                                {settings.host || 'Não configurado'}
                                            </p>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-600">Porta</label>
                                            <p className="mt-1 text-sm text-gray-900 font-mono bg-white px-3 py-2 rounded border border-gray-300">
                                                {settings.port || 'Não configurado'}
                                            </p>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-600">Criptografia</label>
                                            <p className="mt-1 text-sm text-gray-900 font-mono bg-white px-3 py-2 rounded border border-gray-300">
                                                {settings.encryption || 'Nenhuma'}
                                            </p>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-600">Timeout</label>
                                            <p className="mt-1 text-sm text-gray-900 font-mono bg-white px-3 py-2 rounded border border-gray-300">
                                                {settings.timeout || '60'} segundos
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                {/* Autenticação */}
                                <div>
                                    <h4 className="text-sm font-medium text-gray-700 mb-3 flex items-center">
                                        <svg className="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        Autenticação
                                    </h4>
                                    <div className="bg-gray-50 rounded-lg p-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-600">Usuário</label>
                                            <p className="mt-1 text-sm text-gray-900 font-mono bg-white px-3 py-2 rounded border border-gray-300">
                                                {settings.username || 'Não configurado'}
                                            </p>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-600">Senha</label>
                                            <p className="mt-1 text-sm text-gray-900 font-mono bg-white px-3 py-2 rounded border border-gray-300">
                                                {maskPassword(settings.password)}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                {/* Remetente Padrão */}
                                <div>
                                    <h4 className="text-sm font-medium text-gray-700 mb-3 flex items-center">
                                        <svg className="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                        Remetente Padrão
                                    </h4>
                                    <div className="bg-gray-50 rounded-lg p-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-600">Nome</label>
                                            <p className="mt-1 text-sm text-gray-900 font-mono bg-white px-3 py-2 rounded border border-gray-300">
                                                {settings.from_name || 'Não configurado'}
                                            </p>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-600">E-mail</label>
                                            <p className="mt-1 text-sm text-gray-900 font-mono bg-white px-3 py-2 rounded border border-gray-300">
                                                {settings.from_address || 'Não configurado'}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Observações (se houver) */}
                            {settings.notes && (
                                <div className="mt-6 bg-yellow-50 border-l-4 border-yellow-400 p-4">
                                    <div className="flex">
                                        <div className="flex-shrink-0">
                                            <svg className="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                                            </svg>
                                        </div>
                                        <div className="ml-3">
                                            <h4 className="text-sm font-medium text-yellow-800 mb-1">Observações</h4>
                                            <p className="text-sm text-yellow-700">{settings.notes}</p>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* Nota Informativa */}
                            <div className={`mt-6 border-l-4 p-4 ${
                                settings.source === 'database'
                                    ? 'bg-green-50 border-green-400'
                                    : 'bg-blue-50 border-blue-400'
                            }`}>
                                <div className="flex">
                                    <div className="flex-shrink-0">
                                        <svg className={`h-5 w-5 ${
                                            settings.source === 'database' ? 'text-green-400' : 'text-blue-400'
                                        }`} fill="currentColor" viewBox="0 0 20 20">
                                            <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clipRule="evenodd" />
                                        </svg>
                                    </div>
                                    <div className="ml-3">
                                        <p className={`text-sm ${
                                            settings.source === 'database' ? 'text-green-700' : 'text-blue-700'
                                        }`}>
                                            <strong>Origem dos dados:</strong>{' '}
                                            {settings.source === 'database' ? (
                                                <>Configurações carregadas do <strong>banco de dados</strong>. Essas são as configurações ativas do sistema.</>
                                            ) : (
                                                <>Configurações carregadas do arquivo <code className="bg-blue-100 px-1 py-0.5 rounded">.env</code> do servidor.
                                                Para alterar essas configurações, entre em contato com o administrador do sistema ou cadastre as configurações no banco de dados.</>
                                            )}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Card de Teste (opcional) */}
                    <div className="mt-6 bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">
                                Testar Configuração
                            </h3>
                            <p className="text-sm text-gray-600 mb-4">
                                Envie um e-mail de teste para verificar se as configurações estão funcionando corretamente.
                            </p>
                            <button
                                className="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 transition disabled:opacity-50"
                                disabled
                            >
                                <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                                Enviar E-mail de Teste
                            </button>
                            <p className="text-xs text-gray-500 mt-2">
                                Funcionalidade em desenvolvimento
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
