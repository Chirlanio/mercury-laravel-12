import { useEffect, useState } from 'react';
import { SparklesIcon } from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import Button from '@/Components/Button';
import InputLabel from '@/Components/InputLabel';
import StatusBadge from '@/Components/Shared/StatusBadge';

const DAYS_OPTIONS = [
    { value: 30, label: 'Últimos 30 dias' },
    { value: 60, label: 'Últimos 60 dias' },
    { value: 90, label: 'Últimos 90 dias' },
    { value: 180, label: 'Últimos 180 dias' },
];

/**
 * Painel de sugestões. Carrega top produtos vendidos na loja destino,
 * com sugestão de origem por estimativa de saldo. Usuário marca
 * checkboxes e clica "Adicionar ao remanejo" para injetar no CreateModal.
 */
export default function SuggestionsModal({
    show,
    onClose,
    destinationStoreId,
    destinationStoreLabel,
    onApply,
}) {
    const [days, setDays] = useState(30);
    const [top, setTop] = useState(20);
    const [loading, setLoading] = useState(false);
    const [errorMsg, setErrorMsg] = useState(null);
    const [data, setData] = useState(null);
    const [selected, setSelected] = useState({});  // {idx: {checked, qty}}

    const load = async () => {
        if (!destinationStoreId) return;
        setLoading(true);
        setErrorMsg(null);
        try {
            const res = await window.axios.get(route('relocations.suggestions'), {
                params: { destination_store_id: destinationStoreId, days, top },
            });
            setData(res.data);
            // Inicializa selected: tudo marcado por padrão, qty = qty_suggested
            const initial = {};
            (res.data.suggestions || []).forEach((s, idx) => {
                initial[idx] = { checked: true, qty: s.qty_suggested };
            });
            setSelected(initial);
        } catch (e) {
            setErrorMsg(e.response?.data?.message || 'Falha ao gerar sugestões.');
            setData(null);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (show) load();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [show, destinationStoreId, days, top]);

    const toggleAll = (checked) => {
        if (!data) return;
        const next = {};
        data.suggestions.forEach((s, idx) => {
            next[idx] = { checked, qty: selected[idx]?.qty ?? s.qty_suggested };
        });
        setSelected(next);
    };

    const updateRow = (idx, key, value) => {
        setSelected((prev) => ({ ...prev, [idx]: { ...(prev[idx] ?? {}), [key]: value } }));
    };

    const totalSelected = Object.values(selected).filter((s) => s.checked).length;

    const apply = () => {
        if (!data) return;
        const items = [];
        data.suggestions.forEach((s, idx) => {
            const sel = selected[idx];
            if (!sel || !sel.checked) return;

            items.push({
                product_reference: s.product_reference || s.barcode,
                product_name: s.product_name || '',
                product_color: s.product_color || '',
                size: s.size || '',
                barcode: s.barcode,
                qty_requested: parseInt(sel.qty, 10) || s.qty_suggested,
                observations: '',
                // Hint pra UI: origem sugerida (não vai pro backend, só
                // pré-popula o seletor de loja origem se ainda não foi escolhido)
                _suggested_origin_id: s.suggested_origin?.id ?? null,
                _suggested_origin_code: s.suggested_origin?.code ?? null,
            });
        });

        onApply?.(items);
        onClose();
    };

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title="Gerar sugestões"
            subtitle={destinationStoreLabel ? `Destino: ${destinationStoreLabel}` : null}
            headerColor="bg-purple-600"
            headerIcon={<SparklesIcon className="h-5 w-5" />}
            maxWidth="6xl"
            loading={loading}
            errorMessage={errorMsg}
            footer={
                <StandardModal.Footer
                    onCancel={onClose}
                    onSubmit={apply}
                    submitLabel={
                        totalSelected > 0
                            ? `Adicionar ${totalSelected} item(ns) ao remanejo`
                            : 'Selecione ao menos 1 item'
                    }
                    submitVariant="primary"
                    submitDisabled={totalSelected === 0}
                />
            }
        >
            <StandardModal.Section title="Período da análise">
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <InputLabel value="Janela de análise" />
                        <select
                            value={days}
                            onChange={(e) => setDays(parseInt(e.target.value, 10))}
                            className="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"
                        >
                            {DAYS_OPTIONS.map((o) => (
                                <option key={o.value} value={o.value}>{o.label}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Top produtos" />
                        <select
                            value={top}
                            onChange={(e) => setTop(parseInt(e.target.value, 10))}
                            className="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"
                        >
                            <option value="10">Top 10</option>
                            <option value="20">Top 20</option>
                            <option value="30">Top 30</option>
                            <option value="50">Top 50</option>
                        </select>
                    </div>
                    <div className="flex items-end">
                        <p className="text-xs text-gray-600">
                            Cobertura sugerida: <strong>{data?.period?.coverage_days ?? 14} dias</strong>
                            {' '}no ritmo atual de vendas.
                        </p>
                    </div>
                </div>
            </StandardModal.Section>

            {data && data.cigam_available === false && (
                <StandardModal.Highlight>
                    <strong>CIGAM indisponível.</strong> Sem acesso ao saldo real,
                    nenhuma sugestão de origem viável pode ser calculada
                    {data.cigam_unavailable_reason ? ` (${data.cigam_unavailable_reason})` : ''}.
                    As sugestões abaixo (se houver) não terão origem confirmada.
                </StandardModal.Highlight>
            )}

            {data?.destination_store?.network_name && (
                <p className="text-xs text-gray-600 mb-3">
                    Origens filtradas: apenas lojas da rede{' '}
                    <strong>{data.destination_store.network_name}</strong> com{' '}
                    <strong>saldo &gt; 0</strong> em estoque (CIGAM).
                </p>
            )}

            {data && data.suggestions?.length === 0 && (
                <StandardModal.Highlight>
                    Nenhum produto vendido no período tem estoque na rede da loja destino
                    {(data.suppressed_no_stock ?? 0) > 0
                        ? ` — ${data.suppressed_no_stock} produto(s) suprimido(s) por falta de saldo na rede`
                        : ''}.
                    Tente uma janela maior ou cadastre os produtos manualmente.
                </StandardModal.Highlight>
            )}

            {data && data.suggestions?.length > 0 && (
                <StandardModal.Section
                    title={`Sugestões (${data.suggestions.length})`}
                    actions={
                        <div className="flex gap-2">
                            <Button variant="outline" size="sm" onClick={() => toggleAll(true)} type="button">
                                Marcar todos
                            </Button>
                            <Button variant="outline" size="sm" onClick={() => toggleAll(false)} type="button">
                                Desmarcar
                            </Button>
                        </div>
                    }
                >
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-gray-50 text-xs uppercase text-gray-600">
                                <tr>
                                    <th className="px-2 py-2 w-8"></th>
                                    <th className="px-2 py-2 text-left">Referência / Barcode</th>
                                    <th className="px-2 py-2 text-left">Produto</th>
                                    <th className="px-2 py-2 text-left">Tamanho</th>
                                    <th className="px-2 py-2 text-right">Vendas</th>
                                    <th className="px-2 py-2 text-right">Média/dia</th>
                                    <th className="px-2 py-2 text-right">Sugerir</th>
                                    <th className="px-2 py-2 text-left">Origem sugerida</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200">
                                {data.suggestions.map((s, idx) => {
                                    const sel = selected[idx] ?? { checked: false, qty: s.qty_suggested };
                                    return (
                                        <tr key={idx} className={sel.checked ? 'bg-purple-50' : ''}>
                                            <td className="px-2 py-2">
                                                <input
                                                    type="checkbox"
                                                    checked={sel.checked}
                                                    onChange={(e) => updateRow(idx, 'checked', e.target.checked)}
                                                    className="rounded border-gray-300"
                                                />
                                            </td>
                                            <td className="px-2 py-2">
                                                <div className="font-mono text-xs">{s.product_reference || s.barcode}</div>
                                                {s.product_reference && s.product_reference !== s.barcode && (
                                                    <div className="text-xs text-gray-500 font-mono">EAN {s.barcode}</div>
                                                )}
                                            </td>
                                            <td className="px-2 py-2">
                                                <div>{s.product_name || <span className="text-gray-400">—</span>}</div>
                                                {s.product_color && <div className="text-xs text-gray-500">{s.product_color}</div>}
                                            </td>
                                            <td className="px-2 py-2 font-mono text-xs">{s.size || '—'}</td>
                                            <td className="px-2 py-2 text-right tabular-nums">{s.sales_qty}</td>
                                            <td className="px-2 py-2 text-right tabular-nums text-gray-600">{s.daily_average}</td>
                                            <td className="px-2 py-2 text-right">
                                                <input
                                                    type="number"
                                                    min="1"
                                                    value={sel.qty ?? s.qty_suggested}
                                                    onChange={(e) => updateRow(idx, 'qty', e.target.value)}
                                                    disabled={!sel.checked}
                                                    className="w-20 rounded-md border-gray-300 text-sm text-right tabular-nums disabled:bg-gray-50 disabled:text-gray-400"
                                                />
                                            </td>
                                            <td className="px-2 py-2">
                                                {s.suggested_origin ? (
                                                    <div>
                                                        <div className="font-mono text-xs">{s.suggested_origin.code}</div>
                                                        <div className="text-xs text-emerald-700 font-semibold">
                                                            saldo {s.suggested_origin.stock} un
                                                        </div>
                                                        {s.other_origins?.length > 0 && (
                                                            <div className="text-xs text-gray-400 mt-0.5">
                                                                +{s.other_origins.length} alt.
                                                            </div>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <StatusBadge variant="warning">Sem origem viável</StatusBadge>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </StandardModal.Section>
            )}
        </StandardModal>
    );
}
