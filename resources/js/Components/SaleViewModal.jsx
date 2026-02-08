import Modal from '@/Components/Modal';
import Button from '@/Components/Button';

const formatCurrency = (value) => {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(value);
};

export default function SaleViewModal({ isOpen, onClose, sale, onEdit }) {
    if (!sale) return null;

    return (
        <Modal show={isOpen} onClose={onClose} title="Detalhes da Venda" maxWidth="lg">
            <div className="p-6">
                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="block text-xs font-medium text-gray-500 uppercase">Loja</label>
                        <p className="mt-1 text-sm text-gray-900">{sale.store_name}</p>
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-500 uppercase">Funcionário</label>
                        <p className="mt-1 text-sm text-gray-900">{sale.employee_name}</p>
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-500 uppercase">Data</label>
                        <p className="mt-1 text-sm text-gray-900">{sale.date_sales}</p>
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-500 uppercase">Valor</label>
                        <p className="mt-1 text-sm font-semibold text-gray-900">{sale.formatted_total || formatCurrency(sale.total_sales)}</p>
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-500 uppercase">Quantidade</label>
                        <p className="mt-1 text-sm text-gray-900">{sale.qtde_total}</p>
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-500 uppercase">Origem</label>
                        <p className="mt-1">
                            <span className={`px-2 py-1 text-xs font-semibold rounded-full ${
                                sale.source === 'cigam'
                                    ? 'bg-blue-100 text-blue-800'
                                    : 'bg-gray-100 text-gray-800'
                            }`}>
                                {sale.source === 'cigam' ? 'CIGAM' : 'Manual'}
                            </span>
                        </p>
                    </div>
                    {sale.created_by && (
                        <div>
                            <label className="block text-xs font-medium text-gray-500 uppercase">Criado por</label>
                            <p className="mt-1 text-sm text-gray-900">{sale.created_by}</p>
                        </div>
                    )}
                    {sale.created_at && (
                        <div>
                            <label className="block text-xs font-medium text-gray-500 uppercase">Data de Criação</label>
                            <p className="mt-1 text-sm text-gray-900">{sale.created_at}</p>
                        </div>
                    )}
                </div>

                <div className="flex justify-end gap-3 mt-6 pt-4 border-t">
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Fechar
                    </Button>
                    {onEdit && (
                        <Button type="button" variant="warning" onClick={() => onEdit(sale)}>
                            Editar
                        </Button>
                    )}
                </div>
            </div>
        </Modal>
    );
}
