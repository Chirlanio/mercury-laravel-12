import { useMemo } from 'react';

const BRL = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

/**
 * Tabela de seleção múltipla de itens de uma NF para estorno parcial
 * por produto. Renderizada quando partial_mode=by_item.
 *
 * @param {Array} items Lista de items do lookup da NF (movement_id, barcode, ref_size, quantity, unit_price, realized_value)
 * @param {number[]} selectedIds IDs selecionados
 * @param {Function} onToggle (movementId) => void
 * @param {string} error
 */
export default function ItemSelectionTable({ items = [], selectedIds = [], onToggle, error }) {
    const totalSelected = useMemo(
        () =>
            items
                .filter((i) => selectedIds.includes(i.movement_id))
                .reduce((sum, i) => sum + Number(i.realized_value || 0), 0),
        [items, selectedIds]
    );

    if (!items.length) {
        return (
            <div className="p-4 bg-gray-50 border border-gray-200 rounded-lg text-center text-sm text-gray-500">
                Resolva a NF primeiro para selecionar os itens.
            </div>
        );
    }

    return (
        <div>
            <div className="flex items-center justify-between mb-2">
                <p className="text-sm font-medium text-gray-700">
                    Selecione os itens a estornar *
                </p>
                <span className="text-sm text-gray-600">
                    {selectedIds.length} de {items.length} selecionados —
                    <strong className="text-indigo-700 ml-1">{BRL.format(totalSelected)}</strong>
                </span>
            </div>
            <div className="overflow-hidden border border-gray-200 rounded-lg">
                <table className="min-w-full divide-y divide-gray-200 text-sm">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="w-10 px-3 py-2"></th>
                            <th className="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">
                                Código
                            </th>
                            <th className="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase">
                                Ref/Tamanho
                            </th>
                            <th className="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase">
                                Qtde
                            </th>
                            <th className="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase">
                                Unitário
                            </th>
                            <th className="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase">
                                Total
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200 bg-white">
                        {items.map((item) => {
                            const selected = selectedIds.includes(item.movement_id);
                            return (
                                <tr
                                    key={item.movement_id}
                                    className={`cursor-pointer ${selected ? 'bg-indigo-50' : 'hover:bg-gray-50'}`}
                                    onClick={() => onToggle(item.movement_id)}
                                >
                                    <td className="px-3 py-2">
                                        <input
                                            type="checkbox"
                                            checked={selected}
                                            onChange={() => onToggle(item.movement_id)}
                                            onClick={(e) => e.stopPropagation()}
                                            className="rounded border-gray-300 text-indigo-600"
                                        />
                                    </td>
                                    <td className="px-3 py-2 font-mono text-xs">
                                        {item.barcode || '—'}
                                    </td>
                                    <td className="px-3 py-2">{item.ref_size || '—'}</td>
                                    <td className="px-3 py-2 text-right">
                                        {Number(item.quantity).toLocaleString('pt-BR')}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        {BRL.format(item.unit_price)}
                                    </td>
                                    <td className="px-3 py-2 text-right font-semibold">
                                        {BRL.format(item.realized_value)}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
            {error && <p className="mt-1 text-xs text-red-600">{error}</p>}
        </div>
    );
}
