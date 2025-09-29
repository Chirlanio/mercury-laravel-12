import { Head } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

export default function ComingSoon({ auth, title = "Em Desenvolvimento" }) {
    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title={title} />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-center">
                            <div className="mb-8">
                                <div className="inline-flex items-center justify-center w-20 h-20 bg-blue-100 rounded-full mb-4">
                                    <i className="fas fa-tools text-3xl text-blue-600"></i>
                                </div>
                                <h1 className="text-3xl font-bold text-gray-900 mb-2">
                                    {title}
                                </h1>
                                <p className="text-lg text-gray-600 mb-8">
                                    Esta página está em desenvolvimento e estará disponível em breve.
                                </p>
                            </div>

                            <div className="bg-gray-50 rounded-lg p-6 mb-8">
                                <h2 className="text-xl font-semibold text-gray-800 mb-3">
                                    O que esperar:
                                </h2>
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-gray-600">
                                    <div className="flex items-center">
                                        <i className="fas fa-check text-green-500 mr-2"></i>
                                        Interface intuitiva
                                    </div>
                                    <div className="flex items-center">
                                        <i className="fas fa-check text-green-500 mr-2"></i>
                                        Funcionalidades completas
                                    </div>
                                    <div className="flex items-center">
                                        <i className="fas fa-check text-green-500 mr-2"></i>
                                        Integração completa
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-3 text-sm text-gray-500">
                                <p>
                                    <i className="fas fa-info-circle mr-2"></i>
                                    Esta página faz parte do sistema Mercury e será implementada
                                    seguindo os padrões de qualidade e segurança do projeto.
                                </p>
                                <p>
                                    <i className="fas fa-clock mr-2"></i>
                                    Entre em contato com a equipe de desenvolvimento para mais informações
                                    sobre o cronograma de implementação.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}