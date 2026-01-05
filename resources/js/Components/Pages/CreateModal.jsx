import Modal from '@/Components/Modal';
import Button from '@/Components/Button';

export default function CreateModal({ show, onClose, onSubmit, onCancel, data, setData, errors, processing, pageGroups, iconSuggestions }) {
    return (
        <Modal show={show} onClose={onCancel} title="Cadastrar Nova Página" maxWidth="85vw">
            <form onSubmit={onSubmit} className="space-y-6 p-6">
                {/* Informações Básicas */}
                <div className="bg-gray-50 p-4 rounded-lg">
                    <h4 className="text-sm font-medium text-gray-900 mb-4">
                        Informações Básicas
                    </h4>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label htmlFor="page_name" className="block text-sm font-medium text-gray-700 mb-1">
                                Nome da Página *
                            </label>
                            <input
                                id="page_name"
                                type="text"
                                value={data.page_name}
                                onChange={(e) => setData('page_name', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 ${errors.page_name ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'}`}
                                placeholder="Ex: Listar Usuários"
                            />
                            {errors.page_name && (
                                <p className="mt-1 text-sm text-red-600">{errors.page_name}</p>
                            )}
                        </div>

                        <div>
                            <label htmlFor="page_group_id" className="block text-sm font-medium text-gray-700 mb-1">
                                Grupo da Página *
                            </label>
                            <select
                                id="page_group_id"
                                value={data.page_group_id}
                                onChange={(e) => setData('page_group_id', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 ${errors.page_group_id ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'}`}>
                                <option value="">Selecione um grupo</option>
                                {Object.entries(pageGroups).map(([id, name]) => (
                                    <option key={id} value={id}>
                                        {name}
                                    </option>
                                ))}
                            </select>
                            {errors.page_group_id && (
                                <p className="mt-1 text-sm text-red-600">{errors.page_group_id}</p>
                            )}
                        </div>
                    </div>
                </div>

                {/* Configurações de Rota */}
                <div className="bg-gray-50 p-4 rounded-lg">
                    <h4 className="text-sm font-medium text-gray-900 mb-4">
                        Configurações de Rota
                    </h4>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label htmlFor="controller" className="block text-sm font-medium text-gray-700 mb-1">
                                Controller *
                            </label>
                            <input
                                id="controller"
                                type="text"
                                value={data.controller}
                                onChange={(e) => setData('controller', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 ${errors.controller ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'}`}
                                placeholder="Ex: UserController"
                            />
                            {errors.controller && (
                                <p className="mt-1 text-sm text-red-600">{errors.controller}</p>
                            )}
                        </div>

                        <div>
                            <label htmlFor="method" className="block text-sm font-medium text-gray-700 mb-1">
                                Método *
                            </label>
                            <input
                                id="method"
                                type="text"
                                value={data.method}
                                onChange={(e) => setData('method', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 ${errors.method ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'}`}
                                placeholder="Ex: index"
                            />
                            {errors.method && (
                                <p className="mt-1 text-sm text-red-600">{errors.method}</p>
                            )}
                        </div>

                        <div>
                            <label htmlFor="menu_controller" className="block text-sm font-medium text-gray-700 mb-1">
                                Menu Controller
                            </label>
                            <input
                                id="menu_controller"
                                type="text"
                                value={data.menu_controller}
                                onChange={(e) => setData('menu_controller', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 ${errors.menu_controller ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'}`}
                                placeholder="Ex: usuarios"
                            />
                            {errors.menu_controller && (
                                <p className="mt-1 text-sm text-red-600">{errors.menu_controller}</p>
                            )}
                        </div>

                        <div>
                            <label htmlFor="menu_method" className="block text-sm font-medium text-gray-700 mb-1">
                                Menu Método
                            </label>
                            <input
                                id="menu_method"
                                type="text"
                                value={data.menu_method}
                                onChange={(e) => setData('menu_method', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 ${errors.menu_method ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'}`}
                                placeholder="Ex: listar"
                            />
                            {errors.menu_method && (
                                <p className="mt-1 text-sm text-red-600">{errors.menu_method}</p>
                            )}
                        </div>

                        <div className="md:col-span-2">
                            <label htmlFor="route" className="block text-sm font-medium text-gray-700 mb-1">
                                Rota Laravel *
                            </label>
                            <input
                                id="route"
                                type="text"
                                value={data.route}
                                onChange={(e) => setData('route', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 ${errors.route ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'}`}
                                placeholder="Ex: /users ou /stores"
                            />
                            <p className="mt-1 text-xs text-gray-500">
                                A rota Laravel que sera usada no menu. Exemplo: /users, /stores, /employees
                            </p>
                            {errors.route && (
                                <p className="mt-1 text-sm text-red-600">{errors.route}</p>
                            )}
                        </div>
                    </div>
                </div>

                {/* Configurações Visuais */}
                <div className="bg-gray-50 p-4 rounded-lg">
                    <h4 className="text-sm font-medium text-gray-900 mb-4">
                        Configurações Visuais
                    </h4>
                    <div className="space-y-6">
                        <div>
                            <label htmlFor="icon" className="block text-sm font-medium text-gray-700 mb-1">
                                Ícone
                            </label>
                            <input
                                id="icon"
                                type="text"
                                value={data.icon}
                                onChange={(e) => setData('icon', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 ${errors.icon ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'}`}
                                placeholder="Ex: fas fa-users"
                            />
                            {data.icon && (
                                <div className="mt-2 flex items-center">
                                    <i className={data.icon}></i>
                                    <span className="ml-2 text-sm text-gray-600">Preview do ícone</span>
                                </div>
                            )}
                            {errors.icon && (
                                <p className="mt-1 text-sm text-red-600">{errors.icon}</p>
                            )}

                            {/* Sugestões de ícones */}
                            <div className="mt-2">
                                <p className="text-xs text-gray-500 mb-2">Sugestões:</p>
                                <div className="grid grid-cols-8 gap-2 max-h-32 overflow-y-auto border border-gray-200 p-2 rounded">
                                    {iconSuggestions.map((iconClass) => (
                                        <button
                                            key={iconClass}
                                            type="button"
                                            onClick={() => setData('icon', iconClass)}
                                            className="flex items-center justify-center w-8 h-8 border border-gray-300 rounded hover:bg-gray-50 hover:border-indigo-300 transition-colors"
                                            title={iconClass}
                                        >
                                            <i className={`${iconClass} text-gray-600`}></i>
                                        </button>
                                    ))}
                                </div>
                            </div>
                        </div>

                        <div>
                            <label htmlFor="notes" className="block text-sm font-medium text-gray-700 mb-1">
                                Observações
                            </label>
                            <textarea
                                id="notes"
                                rows={3}
                                value={data.notes}
                                onChange={(e) => setData('notes', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 ${errors.notes ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'}`}
                                placeholder="Descrição da funcionalidade da página..."
                            />
                            {errors.notes && (
                                <p className="mt-1 text-sm text-red-600">{errors.notes}</p>
                            )}
                        </div>
                    </div>
                </div>

                {/* Configurações de Acesso */}
                <div className="bg-gray-50 p-4 rounded-lg">
                    <h4 className="text-sm font-medium text-gray-900 mb-4">
                        Configurações de Acesso
                    </h4>
                    <div className="space-y-4">
                        <div className="flex items-center">
                            <input
                                id="is_public"
                                type="checkbox"
                                checked={data.is_public}
                                onChange={(e) => setData('is_public', e.target.checked)}
                                className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                            />
                            <label htmlFor="is_public" className="ml-2 block text-sm text-gray-900">
                                Página Pública (acessível sem autenticação)
                            </label>
                        </div>

                        <div className="flex items-center">
                            <input
                                id="is_active"
                                type="checkbox"
                                checked={data.is_active}
                                onChange={(e) => setData('is_active', e.target.checked)}
                                className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                            />
                            <label htmlFor="is_active" className="ml-2 block text-sm text-gray-900">
                                Página Ativa
                            </label>
                        </div>
                    </div>
                </div>

                {/* Botões do Modal */}
                <div className="flex justify-end space-x-4 pt-6 border-t">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onCancel}
                        disabled={processing}
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="submit"
                        variant="primary"
                        loading={processing}
                    >
                        {processing ? 'Salvando...' : 'Criar Página'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
