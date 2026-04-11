import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';

export default function ViewModal({ show, onClose, movement }) {
    if (!movement) return null;

    const fmt = (val) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val || 0);
    const fmtQty = (val) => new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 3 }).format(val || 0);

    const headerBadges = [
        {
            text: movement.entry_exit === 'E' ? 'Entrada' : 'Saída',
            className: movement.entry_exit === 'E' ? 'bg-emerald-500/20 text-white' : 'bg-amber-500/20 text-white',
        },
        { text: movement.store_code, className: 'bg-white/20 text-white' },
    ];

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title="Detalhe da Movimentação"
            subtitle={`${movement.movement_date} ${movement.movement_time}`}
            headerColor="bg-gray-700"
            headerBadges={headerBadges}
            maxWidth="3xl"
            footer={<StandardModal.Footer onCancel={onClose} cancelLabel="Fechar" />}
        >
            <StandardModal.Section title="Identificação">
                <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <StandardModal.Field label="Data" value={movement.movement_date} />
                    <StandardModal.Field label="Hora" value={movement.movement_time} />
                    <StandardModal.Field label="Loja" value={movement.store_code} />
                    <StandardModal.Field label="Tipo" value={movement.movement_type} />
                    <div>
                        <p className="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">E/S</p>
                        <div className="mt-0.5">
                            <StatusBadge variant={movement.entry_exit === 'E' ? 'emerald' : 'warning'}>
                                {movement.entry_exit === 'E' ? 'Entrada' : 'Saída'}
                            </StatusBadge>
                        </div>
                    </div>
                    <StandardModal.Field label="NF" value={movement.invoice_number} mono />
                </div>
            </StandardModal.Section>

            <StandardModal.Section title="Produto">
                <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <StandardModal.Field label="Ref/Tam" value={movement.ref_size} />
                    <StandardModal.Field label="Código de Barras" value={movement.barcode} mono />
                    <StandardModal.Field label="CPF Consultor" value={movement.cpf_consultant} mono />
                    <StandardModal.Field label="CPF Cliente" value={movement.cpf_customer} mono />
                </div>
            </StandardModal.Section>

            <StandardModal.Section title="Valores">
                <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <StandardModal.InfoCard label="Quantidade" value={fmtQty(movement.quantity)} />
                    <StandardModal.InfoCard label="Qtde Líquida" value={fmtQty(movement.net_quantity)} />
                    <StandardModal.InfoCard label="Preço Venda" value={fmt(movement.sale_price)} />
                    <StandardModal.InfoCard label="Preço Custo" value={fmt(movement.cost_price)} />
                    <StandardModal.InfoCard label="Vlr. Realizado" value={fmt(movement.realized_value)} />
                    <StandardModal.InfoCard label="Desconto" value={fmt(movement.discount_value)} />
                    <StandardModal.InfoCard label="Vlr. Líquido" value={fmt(movement.net_value)} highlight />
                </div>
            </StandardModal.Section>

            <div className="text-xs text-gray-400 text-right">
                Sincronizado em: {movement.synced_at ? new Date(movement.synced_at).toLocaleString('pt-BR') : '-'}
            </div>
        </StandardModal>
    );
}
