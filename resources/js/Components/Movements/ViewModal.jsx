import Modal from '@/Components/Modal';
import Button from '@/Components/Button';

export default function ViewModal({ isOpen, onClose, movement }) {
    if (!movement) return null;

    const fmt = (val) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val || 0);
    const fmtQty = (val) => new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 3 }).format(val || 0);

    const fields = [
        { label: 'Data', value: movement.movement_date },
        { label: 'Hora', value: movement.movement_time },
        { label: 'Loja', value: movement.store_code },
        { label: 'Tipo', value: movement.movement_type },
        { label: 'E/S', value: movement.entry_exit === 'E' ? 'Entrada' : 'Saída' },
        { label: 'NF', value: movement.invoice_number || '-' },
        { label: 'Ref/Tam', value: movement.ref_size || '-' },
        { label: 'Cód. Barras', value: movement.barcode || '-' },
        { label: 'CPF Consultor', value: movement.cpf_consultant || '-' },
        { label: 'CPF Cliente', value: movement.cpf_customer || '-' },
        { label: 'Quantidade', value: fmtQty(movement.quantity) },
        { label: 'Qtde Líquida', value: fmtQty(movement.net_quantity) },
        { label: 'Preço Venda', value: fmt(movement.sale_price) },
        { label: 'Preço Custo', value: fmt(movement.cost_price) },
        { label: 'Vlr. Realizado', value: fmt(movement.realized_value) },
        { label: 'Desconto', value: fmt(movement.discount_value) },
        { label: 'Vlr. Líquido', value: fmt(movement.net_value) },
        { label: 'Sincronizado em', value: movement.synced_at ? new Date(movement.synced_at).toLocaleString('pt-BR') : '-' },
    ];

    return (
        <Modal show={isOpen} onClose={onClose} maxWidth="2xl">
            <div className="p-6">
                <h2 className="text-lg font-semibold text-gray-900 mb-4">Detalhe da Movimentação</h2>
                <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                    {fields.map((f, i) => (
                        <div key={i}>
                            <p className="text-xs text-gray-500">{f.label}</p>
                            <p className="text-sm font-medium text-gray-900">{f.value}</p>
                        </div>
                    ))}
                </div>
                <div className="mt-6 flex justify-end">
                    <Button variant="secondary" onClick={onClose}>Fechar</Button>
                </div>
            </div>
        </Modal>
    );
}
