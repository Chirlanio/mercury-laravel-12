import { useMemo } from 'react';

const BRL = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

/**
 * Tabela de seleção de itens para devolução. Diferente do Reversals,
 * permite editar a quantidade a devolver por item — no e-commerce é
 * comum o cliente devolver apenas parte das unidades compradas.
 *
 * Cada item selecionado aparece com input de quantidade (entre 1 e qty
 * original). Total reembolsável calculado em tempo real.
 *
 * @param {Array} items Items do lookup (movement_id, reference, size, barcode, quantity, unit_price, realized_value)
 * @param {Array} selectedItems [{ movement_id, quantity }]
 * @param {Function} onChange (newSelectedItems) => void
 * @param {string} error
 */
export default function ItemSelectionWithQuantityTable({
    items = [],
    selectedItems = [],
    onChange,
    error,
}) {
    const selectedById = useMemo(
        () => Object.fromEntries(selectedItems.map((s) => [s.movement_id, s])),
        [selectedItems]
    );

    const totalSelected = useMemo(
        () =>
            items.reduce((sum, item) => {
                const sel = selectedById[item.movement_id];
                if (!sel) return sum;
                const qty = Number(sel.quantity || 0);
                return sum + qty * Number(item.unit_price || 0);
            }, 0),
        [items, selectedById]
    );

    const totalUnitsSelected = useMemo(
        () => selectedItems.reduce((s, i) => s + Number(i.quantity || 0), 0),
        [selectedItems]
    );

    const toggleItem = (item) => {
        const sel = selectedById[item.movement_id];
        if (sel) {
            onChange(selectedItems.filter((s) => s.movement_id !== item.movement_id));
        } else {
            // Seleciona com a quantidade total por padrão
            onChange([
                ...selectedItems,
                { movement_id: item.movement_id, quantity: Number(item.quantity) || 1 },
            ]);
        }
    };

    const updateQty = (movementId, raw) => {
        const item = items.find((i) => i.movement_id === movementId);
        if (!item) return;

        const max = Number(item.quantity) || 1;
        const parsed = parseFloat(raw);
        const clamped = Number.isFinite(parsed) && parsed > 0 ? Math.min(parsed, max) : 1;

        onChange(
            selectedItems.map((s) =>
                s.movement_id === movementId ? { ...s, quantity: clamped } : s
            )
        );
    };

    if (!items.length) {
        return (
            <div className="p-4 bg-gray-50 border border-gray-200 rounded-lg text-center text-sm text-gray-500">
                Resolva a NF primeiro para selecionar os itens a devolver.
            </div>
        );
    }

    return (
        <div>
            <div className="flex items-center justify-between mb-2">
                <p className="text-sm font-medium text-gray-700">
                    Selecione os itens e informe a quantidade a devolver *
                </p>
                <span className="text-sm text-gray-600">
                    {selectedItems.length} de {items.length} itens —
                    <strong className="text-indigo-700 ml-1">
                        {totalUnitsSelected.toLocaleString('pt-BR')} unid ·{' '}
                        {BRL.format(totalSelected)}
                    </strong>
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
                                Qtd vendida
                            </th>
                            <th className="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase">
                                Qtd a devolver
                            </th>
                            <th className="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase">
                                Unitário
                            </th>
                            <th className="px-3 py-2 text-right text-xs font-semibold text-gray-500 uppercase">
                                Subtotal
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200 bg-white">
                        {items.map((item) => {
                            const sel = selectedById[item.movement_id];
                            const selected = Boolean(sel);
                            const qtyToReturn = sel ? Number(sel.quantity || 0) : 0;
                            const subtotal = qtyToReturn * Number(item.unit_price || 0);

                            return (
                                <tr
                                    key={item.movement_id}
                                    className={selected ? 'bg-indigo-50' : 'hover:bg-gray-50'}
                                >
                                    <td className="px-3 py-2">
                                        <input
                                            type="checkbox"
                                            checked={selected}
                                            onChange={() => toggleItem(item)}
                                            className="rounded border-gray-300 text-indigo-600"
                                        />
                                    </td>
                                    <td className="px-3 py-2 font-mono text-xs">
                                        {item.barcode || '—'}
                                    </td>
                                    <td className="px-3 py-2">
                                        {[item.reference, item.size].filter(Boolean).join(' · ') ||
                                            '—'}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        {Number(item.quantity).toLocaleString('pt-BR')}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        {selected ? (
                                            <input
                                                type="number"
                                                min="1"
                                                max={item.quantity}
                                                step="1"
                                                value={qtyToReturn}
                                                onChange={(e) =>
                                                    updateQty(item.movement_id, e.target.value)
                                                }
                                                className="w-20 text-right rounded-md border-gray-300 shadow-sm"
                                            />
                                        ) : (
                                            <span className="text-gray-400">—</span>
                                        )}
                                    </td>
                                    <td className="px-3 py-2 text-right">
                                        {BRL.format(item.unit_price)}
                                    </td>
                                    <td className="px-3 py-2 text-right font-semibold">
                                        {selected ? BRL.format(subtotal) : (
                                            <span className="text-gray-400">—</span>
                                        )}
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
