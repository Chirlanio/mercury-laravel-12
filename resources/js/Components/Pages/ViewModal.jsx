import Modal from '@/Components/Modal';

export default function ViewModal({ show, onClose, selectedPage, getGroupBadge }) {
    return (
        <Modal show={show} onClose={onClose} title={selectedPage?.page_name}>
            {selectedPage && <div className="space-y-6 p-6">
                {/* Informações Básicas */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="bg-gray-50 p-4 rounded-lg">
                        <h4 className="text-sm font-medium text-gray-900 mb-3">
                            Informações Básicas
                        </h4>
                        <div className="space-y-2 text-sm">
                            <div>
                                <span className="font-medium text-gray-600">Nome da Página:</span>
                                <span className="ml-2 text-gray-900">{selectedPage.page_name}</span>
                            </div>
                            <div>
                                <span className="font-medium text-gray-600">Grupo da Página:</span>
                                <span className={`ml-2 inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getGroupBadge(selectedPage.page_group.name)}`}>
                                    {selectedPage.page_group.name}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="bg-gray-50 p-4 rounded-lg">
                        <h4 className="text-sm font-medium text-gray-900 mb-3">
                            Configurações de Rota
                        </h4>
                        <div className="space-y-2 text-sm">
                            <div>
                                <span className="font-medium text-gray-600">Controller:</span>
                                <span className="ml-2 font-mono text-gray-900">{selectedPage.controller}</span>
                            </div>
                            <div>
                                <span className="font-medium text-gray-600">Método:</span>
                                <span className="ml-2 font-mono text-gray-900">{selectedPage.method}</span>
                            </div>
                            <div>
                                <span className="font-medium text-gray-600">Menu Controller:</span>
                                <span className="ml-2 font-mono text-gray-900">{selectedPage.menu_controller || 'N/A'}</span>
                            </div>
                            <div>
                                <span className="font-medium text-gray-600">Menu Método:</span>
                                <span className="ml-2 font-mono text-gray-900">{selectedPage.menu_method || 'N/A'}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Configurações Visuais */}
                {(selectedPage.icon || selectedPage.notes) &&
                    <div className="bg-gray-50 p-4 rounded-lg">
                        <h4 className="text-sm font-medium text-gray-900 mb-3">
                            Configurações Visuais
                        </h4>
                        <div className="space-y-4 text-sm">
                            {selectedPage.icon && (
                                <div>
                                    <span className="font-medium text-gray-600">Ícone:</span>
                                    <div className="mt-1 flex items-center space-x-3">
                                        <i className={selectedPage.icon}></i>
                                        <span className="font-mono text-gray-600">{selectedPage.icon}</span>
                                    </div>
                                </div>
                            )}
                            {selectedPage.notes && (
                                <div>
                                    <span className="font-medium text-gray-600">Observações:</span>
                                    <div
                                        className="mt-1 text-gray-700 prose prose-sm max-w-none"
                                        dangerouslySetInnerHTML={{ __html: selectedPage.notes }}
                                    />
                                </div>
                            )}
                        </div>
                    </div>
                }

                {/* Configurações de Acesso */}
                <div className="bg-gray-50 p-4 rounded-lg">
                    <h4 className="text-sm font-medium text-gray-900 mb-3">
                        Configurações de Acesso
                    </h4>
                    <div className="space-y-2 text-sm">
                        <div className="flex items-center justify-between">
                            <span className="font-medium text-gray-600">Página Pública</span>
                            <span className={`px-2 py-1 text-xs font-semibold rounded-full ${selectedPage.is_public ? 'bg-blue-100 text-blue-800' : 'bg-orange-100 text-orange-800'}`}>
                                {selectedPage.is_public ? 'Sim' : 'Não'}
                            </span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span className="font-medium text-gray-600">Página Ativa</span>
                            <span className={`px-2 py-1 text-xs font-semibold rounded-full ${selectedPage.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                {selectedPage.is_active ? 'Sim' : 'Não'}
                            </span>
                        </div>
                    </div>
                </div>

                {/* Níveis de Acesso */}
                {selectedPage.access_levels && selectedPage.access_levels.length > 0 && (
                    <div className="bg-gray-50 p-4 rounded-lg">
                        <h4 className="text-sm font-medium text-gray-900 mb-3">
                            Níveis de Acesso Autorizados
                        </h4>
                        <div className="space-y-3">
                            {selectedPage.access_levels.map((accessLevel, index) => (
                                <div key={index} className="bg-white rounded-md border border-gray-300 p-3">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <h5 className="text-sm font-medium text-gray-900">{accessLevel.name}</h5>
                                            <div className="mt-1 flex space-x-4 text-xs text-gray-500">
                                                <span>Ordem: {accessLevel.order}</span>
                                                {accessLevel.dropdown && <span>Dropdown</span>}
                                                {accessLevel.lib_menu && <span>Menu Liberado</span>}
                                            </div>
                                        </div>
                                        <span className={`px-2 py-1 text-xs font-semibold rounded-full ${accessLevel.permission ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                            {accessLevel.permission ? 'Permitido' : 'Negado'}
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Informações do Sistema */}
                <div className="bg-gray-50 p-4 rounded-lg">
                    <h4 className="text-sm font-medium text-gray-900 mb-3">
                        Informações do Sistema
                    </h4>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-2 text-sm">
                        <div>
                            <span className="font-medium text-gray-600">ID:</span>
                            <span className="ml-2 font-mono text-gray-900">#{selectedPage.id}</span>
                        </div>
                        <div>
                            <span className="font-medium text-gray-600">Níveis com Acesso:</span>
                            <span className="ml-2 text-gray-900">{selectedPage.access_level_count || 0}</span>
                        </div>
                        <div className="col-span-2">
                            <span className="font-medium text-gray-600">Rota:</span>
                            <p className="mt-1 font-mono text-gray-900 break-all">{selectedPage.route}</p>
                        </div>
                        {selectedPage.menu_route && (
                            <div className="col-span-2">
                                <span className="font-medium text-gray-600">Rota do Menu:</span>
                                <p className="mt-1 font-mono text-gray-900 break-all">{selectedPage.menu_route}</p>
                            </div>
                        )}
                        <div>
                            <span className="font-medium text-gray-600">Criado em:</span>
                            <span className="ml-2 text-gray-900">{new Date(selectedPage.created_at).toLocaleString('pt-BR')}</span>
                        </div>
                        {selectedPage.updated_at && (
                            <div>
                                <span className="font-medium text-gray-600">Atualizado em:</span>
                                <span className="ml-2 text-gray-900">{new Date(selectedPage.updated_at).toLocaleString('pt-BR')}</span>
                            </div>
                        )}
                        <div>
                            <span className="font-medium text-gray-600">Acessível a Todos:</span>
                            <span className={`ml-2 px-2 py-1 text-xs font-semibold rounded-full ${selectedPage.is_accessible_to_all ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                                {selectedPage.is_accessible_to_all ? 'Sim' : 'Não'}
                            </span>
                        </div>
                    </div>
                </div>

                <div className="flex justify-end pt-6 border-t">
                    <button
                        type="button"
                        onClick={onClose}
                        className="bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded transition-colors"
                    >
                        Fechar
                    </button>
                </div>
            </div>}
        </Modal>
    );
}
