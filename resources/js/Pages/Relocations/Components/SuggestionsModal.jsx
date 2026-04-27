import { useEffect, useState } from 'react';
import { SparklesIcon, ArrowTrendingUpIcon, QuestionMarkCircleIcon } from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import Button from '@/Components/Button';
import InputLabel from '@/Components/InputLabel';
import StatusBadge from '@/Components/Shared/StatusBadge';

const CURVE_STYLES = {
    A: { bg: 'bg-emerald-100', text: 'text-emerald-800', border: 'border-emerald-300', label: 'A' },
    B: { bg: 'bg-amber-100', text: 'text-amber-800', border: 'border-amber-300', label: 'B' },
    C: { bg: 'bg-gray-100', text: 'text-gray-700', border: 'border-gray-300', label: 'C' },
};

function CurveBadge({ curve, cumulativePct }) {
    const style = CURVE_STYLES[curve] || CURVE_STYLES.C;
    const tooltip = curve === 'A'
        ? `Best-seller (acumula ${cumulativePct}% das vendas)`
        : curve === 'B'
            ? `Volume médio (acumula ${cumulativePct}%)`
            : `Cauda longa (acumula ${cumulativePct}%)`;
    return (
        <span
            title={tooltip}
            className={`inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold border ${style.bg} ${style.text} ${style.border}`}
        >
            {style.label}
        </span>
    );
}

function SeasonalBadge({ ratio, priorQty }) {
    return (
        <span
            title={`Vendendo ${ratio}× vs mesma janela do ano anterior (${priorQty} un)`}
            className="ml-1 inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-orange-100 text-orange-800 border border-orange-300"
        >
            <ArrowTrendingUpIcon className="h-3 w-3" />
            {ratio}×
        </span>
    );
}

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
    originStoreId = null,
    originStoreLabel = null,
    onApply,
}) {
    const [days, setDays] = useState(30);
    const [top, setTop] = useState(20);
    const [loading, setLoading] = useState(false);
    const [errorMsg, setErrorMsg] = useState(null);
    const [data, setData] = useState(null);
    const [selected, setSelected] = useState({});  // {idx: {checked, qty}}
    const [showHelp, setShowHelp] = useState(false);
    // Quando o usuário já escolheu a origem no form, default = filtrar
    // por essa origem. Toggle permite ver toda a rede sem fechar o modal.
    const [restrictToOrigin, setRestrictToOrigin] = useState(true);

    const load = async () => {
        if (!destinationStoreId) return;
        setLoading(true);
        setErrorMsg(null);
        try {
            const params = { destination_store_id: destinationStoreId, days, top };
            if (originStoreId && restrictToOrigin) {
                params.origin_store_id = originStoreId;
            }
            const res = await window.axios.get(route('relocations.suggestions'), { params });
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
    }, [show, destinationStoreId, originStoreId, restrictToOrigin, days, top]);

    // Quando o modal abre sem origem, garante toggle desligado pra
    // não confundir o usuário com checkbox marcado mas sem efeito.
    useEffect(() => {
        if (show && !originStoreId) {
            setRestrictToOrigin(false);
        } else if (show && originStoreId) {
            setRestrictToOrigin(true);
        }
    }, [show, originStoreId]);

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
                _suggested_origin_name: s.suggested_origin?.name ?? null,
                _suggested_origin_stock: s.suggested_origin?.stock ?? null,
            });
        });

        onApply?.(items);
        onClose();
    };

    return (
        <>
        <StandardModal
            show={show}
            onClose={onClose}
            title="Gerar sugestões"
            subtitle={destinationStoreLabel ? `Destino: ${destinationStoreLabel}` : null}
            headerColor="bg-purple-600"
            headerIcon={<SparklesIcon className="h-5 w-5" />}
            headerActions={
                <button
                    type="button"
                    onClick={() => setShowHelp(true)}
                    title="Como funciona a classificação?"
                    className="inline-flex items-center gap-1 text-white/90 hover:text-white text-sm font-medium px-2 py-1 rounded hover:bg-white/10 transition-colors"
                >
                    <QuestionMarkCircleIcon className="h-5 w-5" />
                    <span className="hidden sm:inline">Como funciona?</span>
                </button>
            }
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

                {originStoreId && originStoreLabel && (
                    <div className="mt-3 bg-indigo-50 border border-indigo-200 rounded p-2 flex items-start gap-2">
                        <input
                            id="restrict-to-origin"
                            type="checkbox"
                            checked={restrictToOrigin}
                            onChange={(e) => setRestrictToOrigin(e.target.checked)}
                            className="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <label htmlFor="restrict-to-origin" className="text-xs text-indigo-900 cursor-pointer flex-1">
                            <strong>Apenas produtos com saldo em {originStoreLabel}</strong>
                            <span className="text-indigo-700 font-normal block">
                                Desmarque pra ver os produtos vendidos no destino que outras lojas
                                da rede também têm — útil pra avaliar se essa origem é a melhor
                                escolha ou se outra loja teria mais variedade pra repor.
                            </span>
                        </label>
                    </div>
                )}
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
                    <div className="bg-gray-50 border border-gray-200 rounded p-2 mb-3 flex items-center gap-4 text-xs flex-wrap">
                        <span className="font-semibold text-gray-700">Legenda:</span>
                        <span className="inline-flex items-center gap-1">
                            <CurveBadge curve="A" cumulativePct={80} />
                            <span className="text-gray-600">A — best-seller (até 80% das vendas)</span>
                        </span>
                        <span className="inline-flex items-center gap-1">
                            <CurveBadge curve="B" cumulativePct={95} />
                            <span className="text-gray-600">B — volume médio (até 95%)</span>
                        </span>
                        <span className="inline-flex items-center gap-1">
                            <CurveBadge curve="C" cumulativePct={100} />
                            <span className="text-gray-600">C — cauda longa</span>
                        </span>
                        <span className="inline-flex items-center gap-1">
                            <SeasonalBadge ratio={1.5} priorQty={3} />
                            <span className="text-gray-600">Sazonal — venda &gt; 1.5× ano anterior</span>
                        </span>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-gray-50 text-xs uppercase text-gray-600">
                                <tr>
                                    <th className="px-2 py-2 w-8"></th>
                                    <th className="px-2 py-2 text-center" title="Curva ABC + sazonalidade">Curva</th>
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
                                            <td className="px-2 py-2 text-center">
                                                <CurveBadge curve={s.curve} cumulativePct={s.cumulative_pct} />
                                                {s.is_seasonal && (
                                                    <SeasonalBadge ratio={s.seasonality_ratio} priorQty={s.prior_year_qty} />
                                                )}
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

        <StandardModal
            show={showHelp}
            onClose={() => setShowHelp(false)}
            title="Como a classificação funciona"
            subtitle="Curva ABC e sinal de sazonalidade — base do ranqueamento das sugestões"
            headerColor="bg-indigo-600"
            headerIcon={<QuestionMarkCircleIcon className="h-5 w-5" />}
            maxWidth="2xl"
            footer={
                <StandardModal.Footer>
                    <div className="flex-1" />
                    <Button variant="primary" onClick={() => setShowHelp(false)}>Entendi</Button>
                </StandardModal.Footer>
            }
        >
            <StandardModal.Section title="O que a sugestão analisa">
                <p className="text-sm text-gray-700">
                    Olhamos as <strong>vendas reais da loja destino</strong> nos últimos {data?.period?.days ?? days} dias
                    (movimento CIGAM código 2). Cada produto vendido vira um candidato a remanejo, ordenado pela
                    quantidade vendida no período (mais vendido primeiro).
                </p>
                <p className="text-sm text-gray-700 mt-2">
                    A <strong>curva ABC</strong> classifica esses candidatos pelo princípio de Pareto: poucos produtos
                    representam a maior parte das vendas. Em vez de tratar todos igual, separamos em 3 grupos pra
                    priorizar reposição.
                </p>
            </StandardModal.Section>

            <StandardModal.Section title="Curvas A, B e C">
                <div className="space-y-3">
                    <div className="border border-emerald-200 bg-emerald-50 rounded p-3">
                        <div className="flex items-center gap-2 mb-1">
                            <CurveBadge curve="A" cumulativePct={80} />
                            <strong className="text-sm text-emerald-900">Curva A — Best-sellers</strong>
                        </div>
                        <p className="text-xs text-emerald-900">
                            Produtos cujas vendas <strong>acumuladas</strong> chegam a até <strong>80%</strong> do
                            total vendido no período. Tipicamente os ~20% de itens responsáveis por 80% do giro
                            (Pareto). <strong>Prioridade máxima de reposição</strong> — uma falha aqui custa muita
                            venda perdida.
                        </p>
                    </div>

                    <div className="border border-amber-200 bg-amber-50 rounded p-3">
                        <div className="flex items-center gap-2 mb-1">
                            <CurveBadge curve="B" cumulativePct={95} />
                            <strong className="text-sm text-amber-900">Curva B — Volume médio</strong>
                        </div>
                        <p className="text-xs text-amber-900">
                            Os próximos produtos cujo acumulado vai de <strong>80% até 95%</strong> das vendas. Giro
                            relevante mas não crítico. Vale repor com base na disponibilidade de estoque na rede.
                        </p>
                    </div>

                    <div className="border border-gray-300 bg-gray-50 rounded p-3">
                        <div className="flex items-center gap-2 mb-1">
                            <CurveBadge curve="C" cumulativePct={100} />
                            <strong className="text-sm text-gray-800">Curva C — Cauda longa</strong>
                        </div>
                        <p className="text-xs text-gray-700">
                            Os <strong>5% finais</strong> das vendas, distribuídos numa cauda longa de produtos com
                            baixo giro individual. Reposição opcional — avalie caso a caso. Produtos C costumam ser
                            ofertas pontuais, fim de coleção ou itens de nicho.
                        </p>
                    </div>
                </div>
            </StandardModal.Section>

            <StandardModal.Section title="Como o produto entra em cada curva">
                <ol className="list-decimal list-inside text-sm text-gray-700 space-y-1.5">
                    <li>Somamos a quantidade vendida de todos os produtos do período.</li>
                    <li>Ordenamos do mais vendido pro menos vendido.</li>
                    <li>Vamos acumulando o percentual de cada item sobre o total vendido.</li>
                    <li>
                        Enquanto o acumulado for <strong>≤ 80%</strong> → <strong>A</strong>;
                        de <strong>80% a 95%</strong> → <strong>B</strong>;
                        acima de <strong>95%</strong> → <strong>C</strong>.
                    </li>
                </ol>
                <p className="text-xs text-gray-500 mt-3">
                    Ex.: se o top 3 produtos somam 82% das vendas, os 2 primeiros entram em A (acumulado ainda
                    abaixo de 80%) e o terceiro entra em B (acumulado passou dos 80%).
                </p>
            </StandardModal.Section>

            <StandardModal.Section title="Sinal de sazonalidade">
                <div className="border border-orange-200 bg-orange-50 rounded p-3">
                    <div className="flex items-center gap-2 mb-1">
                        <SeasonalBadge ratio={1.5} priorQty={3} />
                        <strong className="text-sm text-orange-900">Produto sazonal — em alta</strong>
                    </div>
                    <p className="text-xs text-orange-900">
                        Marcamos como sazonal quando as vendas atuais são <strong>1,5× ou mais</strong> que as vendas
                        da <strong>mesma janela exatamente 1 ano antes</strong>, com pelo menos <strong>3
                        unidades</strong> vendidas no ano anterior. O mínimo evita falsos positivos (produto novo
                        com 0 venda no ano anterior daria razão infinita).
                    </p>
                    <p className="text-xs text-orange-900 mt-2">
                        O badge é independente da curva ABC — um produto C pode estar subindo (sazonal) e merecer
                        reposição mesmo com baixo acumulado. Use os dois sinais juntos pra decidir.
                    </p>
                </div>
            </StandardModal.Section>

            <StandardModal.Section title="Quanto sugerimos repor">
                <p className="text-sm text-gray-700">
                    Pra cada produto sugerido, calculamos:
                </p>
                <code className="block bg-gray-100 border border-gray-200 rounded p-2 mt-2 text-xs font-mono text-gray-800">
                    qty_sugerida = mín( ⌈ média_diária × cobertura ⌉, saldo_da_origem )
                </code>
                <p className="text-xs text-gray-600 mt-2">
                    Onde <strong>cobertura = {data?.period?.coverage_days ?? 14} dias</strong> (objetivo de
                    estoque a manter no ritmo atual de vendas) e <strong>saldo_da_origem</strong> é o estoque real
                    da loja origem no CIGAM. Nunca sugerimos mais do que a origem tem disponível.
                </p>
            </StandardModal.Section>
        </StandardModal>
        </>
    );
}
