import Modal from "@/Components/Modal";
import Button from "@/Components/Button";

export default function StoreViewModal({ isOpen, onClose, store, onEdit }) {
    if (!store) return null;

    const getNetworkColor = (networkId) => {
        const colors = {
            1: 'bg-pink-100 text-pink-800',
            2: 'bg-purple-100 text-purple-800',
            3: 'bg-amber-100 text-amber-800',
            4: 'bg-blue-100 text-blue-800',
            5: 'bg-orange-100 text-orange-800',
            6: 'bg-cyan-100 text-cyan-800',
            7: 'bg-gray-100 text-gray-800',
            8: 'bg-rose-100 text-rose-800',
        };
        return colors[networkId] || 'bg-gray-100 text-gray-800';
    };

    return (
        <Modal show={isOpen} onClose={onClose} maxWidth="3xl">
            <div className="p-6">
                {/* Header */}
                <div className="flex justify-between items-start mb-6">
                    <div>
                        <div className="flex items-center gap-3">
                            <span className="text-2xl font-mono font-bold text-blue-600">
                                {store.code}
                            </span>
                            <span className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${
                                store.is_active
                                    ? 'bg-green-100 text-green-800'
                                    : 'bg-red-100 text-red-800'
                            }`}>
                                {store.is_active ? 'Ativa' : 'Inativa'}
                            </span>
                        </div>
                        <h2 className="text-xl font-semibold text-gray-900 mt-1">
                            {store.name}
                        </h2>
                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium mt-2 ${getNetworkColor(store.network_id)}`}>
                            {store.network_name}
                        </span>
                    </div>
                    <button
                        onClick={onClose}
                        className="text-gray-400 hover:text-gray-600"
                    >
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {/* Content Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {/* Informacoes da Empresa */}
                    <div className="bg-gray-50 rounded-lg p-4">
                        <h3 className="text-sm font-semibold text-gray-700 mb-3 uppercase tracking-wide">
                            Informacoes da Empresa
                        </h3>
                        <dl className="space-y-2">
                            <div>
                                <dt className="text-xs text-gray-500">Razao Social</dt>
                                <dd className="text-sm font-medium text-gray-900">{store.company_name}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-gray-500">CNPJ</dt>
                                <dd className="text-sm font-medium text-gray-900 font-mono">{store.formatted_cnpj}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-gray-500">Inscricao Estadual</dt>
                                <dd className="text-sm font-medium text-gray-900">{store.state_registration || 'Nao informado'}</dd>
                            </div>
                        </dl>
                    </div>

                    {/* Endereco */}
                    <div className="bg-gray-50 rounded-lg p-4">
                        <h3 className="text-sm font-semibold text-gray-700 mb-3 uppercase tracking-wide">
                            Endereco
                        </h3>
                        <p className="text-sm text-gray-900">{store.address}</p>
                    </div>

                    {/* Gestao */}
                    <div className="bg-gray-50 rounded-lg p-4">
                        <h3 className="text-sm font-semibold text-gray-700 mb-3 uppercase tracking-wide">
                            Gestao
                        </h3>
                        <dl className="space-y-2">
                            <div>
                                <dt className="text-xs text-gray-500">Gerente</dt>
                                <dd className="text-sm font-medium text-gray-900">
                                    {store.manager?.short_name || store.manager?.name || 'Nao informado'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-gray-500">Supervisor</dt>
                                <dd className="text-sm font-medium text-gray-900">
                                    {store.supervisor?.short_name || store.supervisor?.name || 'Nao informado'}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    {/* Ordenacao */}
                    <div className="bg-gray-50 rounded-lg p-4">
                        <h3 className="text-sm font-semibold text-gray-700 mb-3 uppercase tracking-wide">
                            Ordenacao
                        </h3>
                        <dl className="space-y-2">
                            <div className="flex justify-between">
                                <dt className="text-xs text-gray-500">Ordem da Loja</dt>
                                <dd className="text-sm font-medium text-gray-900">{store.store_order}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-xs text-gray-500">Ordem na Rede</dt>
                                <dd className="text-sm font-medium text-gray-900">{store.network_order}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                {/* Funcionarios */}
                <div className="mt-6">
                    <div className="flex items-center justify-between mb-3">
                        <h3 className="text-sm font-semibold text-gray-700 uppercase tracking-wide">
                            Funcionarios ({store.employees_count})
                        </h3>
                    </div>
                    {store.employees && store.employees.length > 0 ? (
                        <div className="bg-gray-50 rounded-lg p-4">
                            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
                                {store.employees.map((employee) => (
                                    <div key={employee.id} className="flex items-center gap-2 bg-white rounded-lg p-2 shadow-sm">
                                        <div className="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-semibold text-xs">
                                            {employee.short_name?.charAt(0) || employee.name?.charAt(0)}
                                        </div>
                                        <div className="overflow-hidden">
                                            <p className="text-xs font-medium text-gray-900 truncate">
                                                {employee.short_name || employee.name}
                                            </p>
                                            <p className="text-xs text-gray-500 truncate">
                                                {employee.position || 'Sem cargo'}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                            {store.employees_count > 10 && (
                                <p className="text-xs text-gray-500 mt-3 text-center">
                                    Mostrando 10 de {store.employees_count} funcionarios
                                </p>
                            )}
                        </div>
                    ) : (
                        <div className="bg-gray-50 rounded-lg p-4 text-center text-sm text-gray-500">
                            Nenhum funcionario cadastrado nesta loja.
                        </div>
                    )}
                </div>

                {/* Timestamps */}
                <div className="mt-6 pt-4 border-t border-gray-200">
                    <div className="flex justify-between text-xs text-gray-500">
                        <span>Criado em: {store.created_at}</span>
                        <span>Atualizado em: {store.updated_at}</span>
                    </div>
                </div>

                {/* Actions */}
                <div className="flex justify-end space-x-3 mt-6">
                    <Button variant="secondary" onClick={onClose}>
                        Fechar
                    </Button>
                    <Button onClick={() => onEdit(store)}>
                        <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Editar
                    </Button>
                </div>
            </div>
        </Modal>
    );
}
