import { useState } from 'react';
import { router } from '@inertiajs/react';
import { toast } from 'react-toastify';
import {
    PlusIcon, RectangleStackIcon, SparklesIcon, TrashIcon,
    ExclamationTriangleIcon, MagnifyingGlassIcon, XCircleIcon,
} from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import Button from '@/Components/Button';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import SuggestionsModal from './SuggestionsModal';

const EMPTY_ITEM = {
    product_reference: '',
    product_name: '',
    product_color: '',
    size: '',
    barcode: '',
    qty_requested: 1,
    observations: '',
    _lookup_mode: null,
    _lookup_status: null,
    _product_id: null,
    _suggested_origin_id: null,
    _suggested_origin_code: null,
    _suggested_origin_name: null,
    _suggested_origin_stock: null,
    _resolved_stock: null,
};

const PRIORITY_OPTIONS = [
    { value: 'low', label: 'Baixa' },
    { value: 'normal', label: 'Normal' },
    { value: 'high', label: 'Alta' },
    { value: 'urgent', label: 'Urgente' },
];

const DEADLINE_DEFAULTS = { urgent: 0, high: 1, normal: 3, low: 4 };

const fmtDeadline = (days) => {
    if (days === 0) return 'mesmo dia';
    if (days === 1) return '1 dia';
    return `${days} dias`;
};

// Heurística pra decidir EAN vs referência no input combinado.
const isLikelyBarcode = (q) => /^\d{6,14}$/.test((q ?? '').trim());

// Lista campos internos que NÃO devem ir pro POST.
const INTERNAL_KEYS = [
    '_lookup_mode', '_lookup_status', '_lookup_error', '_available_sizes',
    '_product_id', '_suggested_origin_id', '_suggested_origin_code',
    '_suggested_origin_name', '_suggested_origin_stock', '_resolved_stock',
    '_originalIdx', '_resolved_origin_id', '_zeros_or_exceeds',
];

const stripInternal = (item) => {
    const out = {};
    for (const [k, v] of Object.entries(item)) {
        if (!INTERNAL_KEYS.includes(k)) out[k] = v;
    }
    return out;
};

export default function CreateModal({
    show, onClose, selects = {},
    isStoreScoped = false, scopedStoreId = null,
    priorityDeadlines = DEADLINE_DEFAULTS,
}) {
    const [form, setForm] = useState({
        relocation_type_id: '',
        origin_store_id: isStoreScoped ? (scopedStoreId ?? '') : '',
        destination_store_id: '',
        title: '',
        observations: '',
        priority: 'normal',
        items: [],
    });
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);
    const [validating, setValidating] = useState(false);
    const [suggestionsOpen, setSuggestionsOpen] = useState(false);
    const [confirmState, setConfirmState] = useState(null);

    const updateField = (key, value) => {
        setForm((prev) => {
            const next = { ...prev, [key]: value };
            if (key === 'origin_store_id') {
                const newOrigin = (selects.stores || []).find((s) => String(s.id) === String(value));
                const dest = (selects.stores || []).find((s) => String(s.id) === String(prev.destination_store_id));
                if (newOrigin && dest && newOrigin.network_id !== dest.network_id) {
                    next.destination_store_id = '';
                }
            }
            if (key === 'destination_store_id') {
                const newDest = (selects.stores || []).find((s) => String(s.id) === String(value));
                const origin = (selects.stores || []).find((s) => String(s.id) === String(prev.origin_store_id));
                if (newDest && origin && newDest.network_id !== origin.network_id) {
                    next.origin_store_id = isStoreScoped ? prev.origin_store_id : '';
                }
            }
            return next;
        });
    };

    const storesForOrigin = () => {
        const stores = selects.stores || [];
        const dest = stores.find((s) => String(s.id) === String(form.destination_store_id));
        if (!dest) return stores;
        return stores.filter((s) => s.network_id === dest.network_id);
    };

    const storesForDestination = () => {
        const stores = selects.stores || [];
        const origin = stores.find((s) => String(s.id) === String(form.origin_store_id));
        if (!origin) return stores.filter((s) => String(s.id) !== String(form.origin_store_id));
        return stores.filter(
            (s) => s.network_id === origin.network_id && String(s.id) !== String(origin.id)
        );
    };

    const networkHint = () => {
        const stores = selects.stores || [];
        const origin = stores.find((s) => String(s.id) === String(form.origin_store_id));
        const dest = stores.find((s) => String(s.id) === String(form.destination_store_id));
        const network = origin || dest;
        return network?.network_name ?? null;
    };

    const getStoreLabel = (storeId) => {
        const s = (selects.stores || []).find((st) => String(st.id) === String(storeId));
        return s ? `${s.code} — ${s.name}` : `#${storeId}`;
    };

    /**
     * Adiciona um item à tabela. Recebe o objeto já completo (vindo do
     * SearchPanel ou do SuggestionsModal). Faz dedup por barcode+origem
     * pra evitar linha duplicada quando o usuário busca o mesmo EAN 2x.
     */
    const addItemToList = (newItem) => {
        setForm((prev) => {
            const idx = prev.items.findIndex((it) => (
                (it.barcode || '') === (newItem.barcode || '')
                && (it._suggested_origin_id ?? null) === (newItem._suggested_origin_id ?? null)
            ));
            if (idx >= 0) {
                // Já existe: soma a qty
                const items = [...prev.items];
                items[idx] = {
                    ...items[idx],
                    qty_requested: (parseInt(items[idx].qty_requested, 10) || 0) + (parseInt(newItem.qty_requested, 10) || 1),
                };
                return { ...prev, items };
            }
            return { ...prev, items: [...prev.items, newItem] };
        });
    };

    const removeItem = (idx) => {
        setForm((prev) => ({ ...prev, items: prev.items.filter((_, i) => i !== idx) }));
    };

    /**
     * Recebe items vindos do SuggestionsModal — pré-popula loja origem
     * se houver consenso, anexa todos à tabela. Preserva _suggested_origin_*.
     */
    const applySuggestions = (suggestedItems) => {
        if (!suggestedItems?.length) return;

        if (!form.origin_store_id) {
            const distinctOrigins = [...new Set(
                suggestedItems
                    .map((it) => it._suggested_origin_id)
                    .filter((id) => id !== null && id !== undefined)
            )];
            if (distinctOrigins.length === 1) {
                updateField('origin_store_id', String(distinctOrigins[0]));
            }
        }

        suggestedItems.forEach((item) => {
            addItemToList({
                ...EMPTY_ITEM,
                ...item,
                _lookup_mode: 'barcode',
                _lookup_status: 'ok',
            });
        });
    };

    const reset = () => {
        setForm({
            relocation_type_id: '',
            origin_store_id: isStoreScoped ? (scopedStoreId ?? '') : '',
            destination_store_id: '',
            title: '',
            observations: '',
            priority: 'normal',
            items: [],
        });
        setErrors({});
        setConfirmState(null);
    };

    const handleClose = () => {
        if (processing || validating) return;
        reset();
        onClose();
    };

    // ------------------------------------------------------------------
    // Submit em 2 fases (lookup-stock → agrupa por origem → confirm).
    // ------------------------------------------------------------------
    const submit = async (e) => {
        e?.preventDefault();
        setErrors({});

        if (!form.relocation_type_id) {
            setErrors({ relocation_type_id: 'Selecione o tipo de remanejo.' });
            return;
        }
        if (!form.destination_store_id) {
            setErrors({ destination_store_id: 'Selecione a loja destino.' });
            return;
        }

        if (form.items.length === 0) {
            setErrors({ items: 'Adicione ao menos 1 produto à lista.' });
            return;
        }

        const itemsWithIdx = form.items.map((it, originalIdx) => ({ ...it, _originalIdx: originalIdx }));

        const formOriginId = form.origin_store_id ? parseInt(form.origin_store_id, 10) : null;
        const itemsWithOrigin = itemsWithIdx.map((it) => ({
            ...it,
            _resolved_origin_id: it._suggested_origin_id ?? formOriginId,
        }));

        const orphans = itemsWithOrigin.filter((it) => !it._resolved_origin_id);
        if (orphans.length > 0) {
            setErrors({ origin_store_id: 'Selecione a loja origem ou aplique sugestões antes de criar.' });
            return;
        }

        setValidating(true);
        let stocks = {};
        let cigamAvailable = true;
        try {
            const payload = itemsWithOrigin.map((it) => ({
                store_id: it._resolved_origin_id,
                barcode: (it.barcode || it.product_reference || '').toString().trim(),
            }));
            const res = await window.axios.post(route('relocations.lookup-stock'), { items: payload });
            stocks = res.data?.stocks || {};
            cigamAvailable = res.data?.cigam_available ?? false;
        } catch {
            cigamAvailable = false;
        } finally {
            setValidating(false);
        }

        const annotated = itemsWithOrigin.map((it) => {
            const code = (it.barcode || it.product_reference || '').toString().trim();
            const key = `${it._resolved_origin_id}|${code}`;
            const stock = (it._suggested_origin_stock !== null && it._suggested_origin_stock !== undefined)
                ? it._suggested_origin_stock
                : (stocks[key] !== undefined ? stocks[key] : null);
            const qty = parseInt(it.qty_requested, 10) || 1;
            return {
                ...it,
                _resolved_stock: stock,
                _zeros_or_exceeds: stock !== null && stock !== undefined && qty >= stock,
            };
        });

        // Atualiza form com saldo (chip vermelho na tabela).
        setForm((prev) => {
            const next = [...prev.items];
            annotated.forEach((it) => {
                next[it._originalIdx] = {
                    ...next[it._originalIdx],
                    _resolved_stock: it._resolved_stock,
                };
            });
            return { ...prev, items: next };
        });

        const groupsMap = new Map();
        annotated.forEach((it) => {
            const k = it._resolved_origin_id;
            if (!groupsMap.has(k)) groupsMap.set(k, []);
            groupsMap.get(k).push(it);
        });
        const groups = [...groupsMap.entries()].map(([originId, items]) => ({
            origin_store_id: originId,
            items,
        }));

        const problems = annotated.filter((it) => it._zeros_or_exceeds);
        const multiOrigin = groups.length > 1;

        if (multiOrigin || problems.length > 0 || !cigamAvailable) {
            setConfirmState({ groups, problems, removeFlags: {}, cigamAvailable, multiOrigin });
            return;
        }

        await doSubmit(groups);
    };

    const doSubmit = async (groups) => {
        setProcessing(true);
        const results = [];

        for (const group of groups) {
            const items = group.items.map((it) => ({
                ...stripInternal(it),
                product_id: it._product_id ?? null,
                qty_requested: parseInt(it.qty_requested, 10) || 1,
            }));

            const payload = {
                relocation_type_id: form.relocation_type_id || null,
                origin_store_id: group.origin_store_id,
                destination_store_id: form.destination_store_id || null,
                title: form.title || null,
                observations: form.observations || null,
                priority: form.priority,
                items,
            };

            try {
                await window.axios.post(route('relocations.store'), payload);
                results.push({ origin_store_id: group.origin_store_id, ok: true });
            } catch (e) {
                const errs = e.response?.data?.errors || {};
                const firstErr = Object.values(errs)[0];
                results.push({
                    origin_store_id: group.origin_store_id,
                    ok: false,
                    error: (Array.isArray(firstErr) ? firstErr[0] : firstErr)
                        || e.response?.data?.message
                        || 'Erro inesperado',
                });
            }
        }

        setProcessing(false);

        const okCount = results.filter((r) => r.ok).length;
        const failCount = results.filter((r) => !r.ok).length;

        if (failCount === 0) {
            toast.success(okCount === 1 ? 'Remanejo criado.' : `${okCount} remanejos criados.`);
            reset();
            onClose();
            router.reload({ only: ['items', 'statistics'] });
            return;
        }

        const failedLabels = results
            .filter((r) => !r.ok)
            .map((r) => `${getStoreLabel(r.origin_store_id)}: ${r.error}`)
            .join(' · ');

        if (okCount > 0) {
            toast.warning(`${okCount} criado(s), ${failCount} falhou(aram). ${failedLabels}`, { autoClose: 8000 });
            router.reload({ only: ['items', 'statistics'] });
        } else {
            toast.error(`Falha ao criar: ${failedLabels}`, { autoClose: 8000 });
        }
    };

    const cancelConfirm = () => setConfirmState(null);

    const toggleRemove = (originalIdx) => {
        setConfirmState((prev) => prev ? ({
            ...prev,
            removeFlags: { ...prev.removeFlags, [originalIdx]: !prev.removeFlags[originalIdx] },
        }) : null);
    };

    const confirmAndCreate = async () => {
        if (!confirmState) return;
        const { groups, removeFlags } = confirmState;

        const removedIdxs = new Set(
            Object.entries(removeFlags).filter(([, v]) => v).map(([k]) => parseInt(k, 10))
        );

        if (removedIdxs.size > 0) {
            setForm((prev) => ({
                ...prev,
                items: prev.items.filter((_, idx) => !removedIdxs.has(idx)),
            }));
        }

        const filteredGroups = groups
            .map((g) => ({ ...g, items: g.items.filter((it) => !removedIdxs.has(it._originalIdx)) }))
            .filter((g) => g.items.length > 0);

        setConfirmState(null);

        if (filteredGroups.length === 0) {
            toast.info('Todos os itens foram removidos. Ajuste a solicitação e tente novamente.');
            return;
        }

        await doSubmit(filteredGroups);
    };

    const currentDeadline = priorityDeadlines[form.priority] ?? 3;
    const submitLabel = validating ? 'Validando estoque...'
        : processing ? 'Criando...'
        : 'Criar remanejo';

    return (
        <>
            <StandardModal
                show={show}
                onClose={handleClose}
                title="Novo Remanejo"
                subtitle="Solicitação de transferência entre lojas"
                headerColor="bg-indigo-600"
                headerIcon={<RectangleStackIcon className="h-5 w-5" />}
                maxWidth="6xl"
                onSubmit={submit}
                footer={
                    <StandardModal.Footer
                        onCancel={handleClose}
                        onSubmit="submit"
                        submitLabel={submitLabel}
                        processing={processing || validating}
                    />
                }
            >
                <StandardModal.Section title="Dados gerais">
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <InputLabel htmlFor="relocation_type_id" value="Tipo de remanejo *" />
                            <select
                                id="relocation_type_id"
                                value={form.relocation_type_id}
                                onChange={(e) => updateField('relocation_type_id', e.target.value)}
                                className="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"
                            >
                                <option value="">Selecione...</option>
                                {selects.types?.map((t) => (
                                    <option key={t.id} value={t.id}>{t.name}</option>
                                ))}
                            </select>
                            <InputError message={errors.relocation_type_id} className="mt-1" />
                        </div>

                        <div>
                            <InputLabel htmlFor="origin_store_id" value="Loja origem" />
                            <select
                                id="origin_store_id"
                                value={form.origin_store_id}
                                onChange={(e) => updateField('origin_store_id', e.target.value)}
                                disabled={isStoreScoped}
                                className="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm disabled:bg-gray-100"
                            >
                                <option value="">Selecione...</option>
                                {storesForOrigin().map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.code} — {s.name}
                                        {s.network_name ? ` · ${s.network_name}` : ''}
                                    </option>
                                ))}
                            </select>
                            <p className="text-[11px] text-gray-500 mt-1">
                                Itens vindos de sugestões usam a origem sugerida —
                                pode ficar vazio se todos os itens forem sugeridos.
                            </p>
                            <InputError message={errors.origin_store_id} className="mt-1" />
                        </div>

                        <div>
                            <InputLabel htmlFor="destination_store_id" value="Loja destino *" />
                            <select
                                id="destination_store_id"
                                value={form.destination_store_id}
                                onChange={(e) => updateField('destination_store_id', e.target.value)}
                                className="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"
                            >
                                <option value="">Selecione...</option>
                                {storesForDestination().map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.code} — {s.name}
                                        {s.network_name ? ` · ${s.network_name}` : ''}
                                    </option>
                                ))}
                            </select>
                            {networkHint() && (
                                <p className="text-xs text-gray-500 mt-1">
                                    Apenas lojas da rede <strong>{networkHint()}</strong> (regra de negócio).
                                </p>
                            )}
                            <InputError message={errors.destination_store_id} className="mt-1" />
                        </div>

                        <div>
                            <InputLabel htmlFor="priority" value="Prioridade" />
                            <select
                                id="priority"
                                value={form.priority}
                                onChange={(e) => updateField('priority', e.target.value)}
                                className="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"
                            >
                                {PRIORITY_OPTIONS.map((p) => (
                                    <option key={p.value} value={p.value}>{p.label}</option>
                                ))}
                            </select>
                            <p className="text-xs text-indigo-700 mt-1 font-medium">
                                Prazo: {fmtDeadline(currentDeadline)}
                            </p>
                        </div>

                        <div className="sm:col-span-2">
                            <InputLabel htmlFor="title" value="Título / descrição curta" />
                            <TextInput
                                id="title"
                                value={form.title}
                                onChange={(e) => updateField('title', e.target.value)}
                                placeholder="Ex: Reposição coleção verão Schutz"
                                maxLength={200}
                                className="w-full"
                            />
                        </div>

                        <div className="sm:col-span-2 lg:col-span-3">
                            <InputLabel htmlFor="observations" value="Observações" />
                            <textarea
                                id="observations"
                                rows={2}
                                value={form.observations}
                                onChange={(e) => updateField('observations', e.target.value)}
                                className="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"
                                placeholder="Contexto adicional, justificativa, etc."
                                maxLength={2000}
                            />
                        </div>
                    </div>
                </StandardModal.Section>

                <StandardModal.Section
                    title="Buscar e adicionar produtos"
                    actions={
                        <Button
                            variant="outline"
                            size="sm"
                            icon={SparklesIcon}
                            onClick={() => {
                                if (!form.destination_store_id) {
                                    toast.warning('Selecione a loja destino antes de gerar sugestões.');
                                    return;
                                }
                                setSuggestionsOpen(true);
                            }}
                            type="button"
                        >
                            Gerar sugestões
                        </Button>
                    }
                >
                    <ProductSearchPanel onAdd={addItemToList} />
                </StandardModal.Section>

                <StandardModal.Section title={`Produtos adicionados (${form.items.length})`}>
                    {form.items.length === 0 ? (
                        <div className="border border-dashed border-gray-300 rounded p-6 text-center text-sm text-gray-500">
                            Nenhum produto adicionado. Use o painel acima pra buscar e adicionar
                            produtos, ou clique em <strong>Gerar sugestões</strong>.
                        </div>
                    ) : (
                        <div className="overflow-x-auto border border-gray-200 rounded-md">
                            <table className="min-w-full">
                                <thead className="bg-gray-50 text-[11px] uppercase text-gray-600">
                                    <tr>
                                        <th className="px-2 py-2 text-left w-10">#</th>
                                        <th className="px-2 py-2 text-left">Referência / EAN</th>
                                        <th className="px-2 py-2 text-left">Descrição</th>
                                        <th className="px-2 py-2 text-left w-24">Tamanho</th>
                                        <th className="px-2 py-2 text-right w-16">Qtd</th>
                                        <th className="px-2 py-2 text-left w-40">Origem (saldo)</th>
                                        <th className="px-2 py-2 w-10"></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200 bg-white">
                                    {form.items.map((item, idx) => (
                                        <ItemRowReadOnly
                                            key={`${item.barcode || item.product_reference}-${idx}`}
                                            idx={idx}
                                            item={item}
                                            onRemove={() => removeItem(idx)}
                                        />
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <InputError message={errors.items} className="mt-2" />
                </StandardModal.Section>

                <SuggestionsModal
                    show={suggestionsOpen}
                    onClose={() => setSuggestionsOpen(false)}
                    destinationStoreId={form.destination_store_id ? parseInt(form.destination_store_id, 10) : null}
                    destinationStoreLabel={
                        selects.stores?.find((s) => String(s.id) === String(form.destination_store_id))
                            ? `${selects.stores.find((s) => String(s.id) === String(form.destination_store_id)).code} — ${selects.stores.find((s) => String(s.id) === String(form.destination_store_id)).name}`
                            : null
                    }
                    originStoreId={form.origin_store_id ? parseInt(form.origin_store_id, 10) : null}
                    originStoreLabel={
                        selects.stores?.find((s) => String(s.id) === String(form.origin_store_id))
                            ? `${selects.stores.find((s) => String(s.id) === String(form.origin_store_id)).code} — ${selects.stores.find((s) => String(s.id) === String(form.origin_store_id)).name}`
                            : null
                    }
                    onApply={applySuggestions}
                />
            </StandardModal>

            {confirmState && (
                <ConfirmCreateModal
                    state={confirmState}
                    getStoreLabel={getStoreLabel}
                    onCancel={cancelConfirm}
                    onConfirm={confirmAndCreate}
                    onToggleRemove={toggleRemove}
                    processing={processing}
                />
            )}
        </>
    );
}

// ====================================================================
// ProductSearchPanel — busca + adicionar à lista
// ====================================================================
function ProductSearchPanel({ onAdd }) {
    const [input, setInput] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [lookup, setLookup] = useState(null); // payload do endpoint lookup-product
    const [size, setSize] = useState('');
    const [qty, setQty] = useState(1);

    const reset = () => {
        setInput(''); setLookup(null); setSize(''); setQty(1); setError('');
    };

    const search = async () => {
        const v = input.trim();
        if (!v) return;
        setLoading(true);
        setError('');
        setLookup(null);
        setSize('');
        try {
            const params = isLikelyBarcode(v) ? { barcode: v } : { reference: v };
            const { data } = await window.axios.get(route('relocations.lookup-product'), { params });
            if (!data.found) {
                setError(data.error || 'Produto não encontrado.');
                return;
            }
            setLookup(data);
            // Modo barcode já vem com tamanho definido; modo reference exige escolha.
            setSize(data.mode === 'barcode' ? (data.size || '') : '');
        } catch (e) {
            setError(e.response?.data?.error || 'Falha na consulta ao catálogo.');
        } finally {
            setLoading(false);
        }
    };

    const handleKey = (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            search();
        }
    };

    const handleAdd = () => {
        if (!lookup) return;
        const isReference = lookup.mode === 'reference';
        if (isReference && !size) {
            setError('Selecione o tamanho.');
            return;
        }
        const variant = isReference
            ? (lookup.variants || []).find((v) => String(v.size) === String(size))
            : null;
        const qtyInt = parseInt(qty, 10);
        if (!qtyInt || qtyInt < 1) {
            setError('Informe uma quantidade válida.');
            return;
        }

        onAdd({
            ...EMPTY_ITEM,
            product_reference: lookup.product_reference || '',
            product_name: lookup.product_name || '',
            product_color: lookup.product_color || '',
            size: isReference ? size : (lookup.size || ''),
            barcode: variant?.barcode || lookup.barcode || '',
            qty_requested: qtyInt,
            _product_id: lookup.product_id ?? null,
            _lookup_mode: lookup.mode,
            _lookup_status: 'ok',
        });

        reset();
    };

    const isReference = lookup?.mode === 'reference';

    return (
        <div className="bg-gray-50 border border-gray-200 rounded-md p-3">
            <p className="text-xs text-gray-600 mb-2">
                Bipe o EAN ou digite a referência e pressione <strong>Enter</strong>.
                O sistema detecta automaticamente o tipo e busca no catálogo.
            </p>
            <div className="flex items-stretch gap-2">
                <div className="flex-1 flex items-stretch">
                    <TextInput
                        value={input}
                        onChange={(e) => setInput(e.target.value)}
                        onKeyDown={handleKey}
                        placeholder="Bipe EAN ou digite referência..."
                        maxLength={100}
                        disabled={loading}
                        className="flex-1 min-w-0 text-sm font-mono rounded-r-none focus:z-10"
                        autoFocus
                    />
                    <Button
                        type="button"
                        variant="outline"
                        size="md"
                        onClick={search}
                        disabled={loading || !input.trim()}
                        icon={MagnifyingGlassIcon}
                        className="rounded-l-none"
                    >
                        Buscar
                    </Button>
                </div>
            </div>

            {error && (
                <p className="text-xs text-red-600 mt-2 flex items-center gap-1">
                    <XCircleIcon className="h-4 w-4" />
                    {error}
                </p>
            )}

            {lookup && (
                <div className="mt-3 bg-white border border-gray-200 rounded p-3">
                    <div className="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                        <div className="sm:col-span-6">
                            <p className="text-xs text-gray-500 uppercase tracking-wide font-medium">Produto</p>
                            <p className="text-sm font-medium text-gray-900 mt-0.5">
                                {lookup.product_name || '—'}
                            </p>
                            <p className="text-xs text-gray-500 mt-0.5">
                                Ref: <span className="font-mono">{lookup.product_reference}</span>
                                {lookup.product_color ? ` · ${lookup.product_color}` : ''}
                                {lookup.mode === 'barcode' && lookup.barcode
                                    ? ` · EAN ${lookup.barcode}` : ''}
                            </p>
                        </div>

                        <div className="sm:col-span-2">
                            <InputLabel value="Tamanho *" className="text-xs" />
                            {isReference ? (
                                <select
                                    value={size}
                                    onChange={(e) => setSize(e.target.value)}
                                    className="block w-full rounded-md border-gray-300 shadow-sm text-sm"
                                    autoFocus
                                >
                                    <option value="">Selecione...</option>
                                    {(lookup.variants || []).map((v) => (
                                        <option key={v.variant_id} value={v.size}>{v.size}</option>
                                    ))}
                                </select>
                            ) : (
                                <TextInput
                                    value={lookup.size || ''}
                                    readOnly
                                    className="w-full text-sm bg-gray-100 cursor-not-allowed"
                                />
                            )}
                        </div>

                        <div className="sm:col-span-2">
                            <InputLabel value="Qtd *" className="text-xs" />
                            <TextInput
                                type="number"
                                min="1"
                                value={qty}
                                onChange={(e) => setQty(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        e.preventDefault();
                                        handleAdd();
                                    }
                                }}
                                className="w-full text-sm text-right tabular-nums"
                            />
                        </div>

                        <div className="sm:col-span-2">
                            <Button
                                type="button"
                                variant="primary"
                                onClick={handleAdd}
                                icon={PlusIcon}
                                className="w-full justify-center"
                            >
                                Adicionar
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

// ====================================================================
// ItemRowReadOnly — linha somente-leitura da tabela de produtos adicionados
// ====================================================================
function ItemRowReadOnly({ idx, item, onRemove }) {
    const stock = item._suggested_origin_stock ?? item._resolved_stock ?? null;
    const qty = parseInt(item.qty_requested, 10) || 1;
    const exceedsStock = stock !== null && stock !== undefined && qty >= stock;

    return (
        <tr>
            <td className="px-2 py-2 align-top">
                <span className="text-xs text-gray-500 font-mono">{idx + 1}</span>
            </td>
            <td className="px-2 py-2 align-top text-sm">
                <div className="font-mono text-gray-900">
                    {item.product_reference || item.barcode || '—'}
                </div>
                {item.barcode && item.barcode !== item.product_reference && (
                    <div className="text-[10px] text-gray-400 font-mono">EAN {item.barcode}</div>
                )}
            </td>
            <td className="px-2 py-2 align-top text-sm">
                <div className="font-medium text-gray-900">
                    {item.product_name || <span className="text-gray-400">—</span>}
                </div>
                {item.product_color && <div className="text-xs text-gray-500">{item.product_color}</div>}
            </td>
            <td className="px-2 py-2 align-top text-sm">
                {item.size || <span className="text-gray-400">—</span>}
            </td>
            <td className={`px-2 py-2 align-top text-right text-sm tabular-nums font-medium ${exceedsStock ? 'text-red-700' : 'text-gray-900'}`}>
                {qty}
            </td>
            <td className="px-2 py-2 align-top">
                {item._suggested_origin_code ? (
                    <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium ${
                        exceedsStock
                            ? 'bg-red-100 text-red-800 border border-red-300'
                            : 'bg-blue-50 text-blue-700 border border-blue-200'
                    }`}>
                        <span className="font-mono">{item._suggested_origin_code}</span>
                        {stock !== null && stock !== undefined && (
                            <span>· {stock} un</span>
                        )}
                    </span>
                ) : stock !== null && stock !== undefined ? (
                    <span className={`text-xs ${exceedsStock ? 'text-red-700 font-medium' : 'text-gray-500'}`}>
                        {stock} un disponíveis
                    </span>
                ) : (
                    <span className="text-xs text-gray-400">—</span>
                )}
                {exceedsStock && (
                    <p className="text-[10px] text-red-600 mt-0.5">Vai zerar/exceder</p>
                )}
            </td>
            <td className="px-2 py-2 align-top text-center">
                <button
                    type="button"
                    onClick={onRemove}
                    title="Remover item"
                    className="text-red-500 hover:text-red-700"
                >
                    <TrashIcon className="h-4 w-4" />
                </button>
            </td>
        </tr>
    );
}

// ====================================================================
// ConfirmCreateModal — pré-validação antes de postar (mantido).
// ====================================================================
function ConfirmCreateModal({ state, getStoreLabel, onCancel, onConfirm, onToggleRemove, processing }) {
    const { groups, problems, removeFlags, multiOrigin, cigamAvailable } = state;

    const groupsAfterRemoval = groups
        .map((g) => ({ ...g, items: g.items.filter((it) => !removeFlags[it._originalIdx]) }))
        .filter((g) => g.items.length > 0);

    const totalAfter = groupsAfterRemoval.reduce((acc, g) => acc + g.items.length, 0);

    return (
        <StandardModal
            show={true}
            onClose={onCancel}
            title="Confirmar criação"
            subtitle={multiOrigin
                ? `${groupsAfterRemoval.length} remanejo(s) separado(s) — 1 por loja origem`
                : 'Validação de estoque'}
            headerColor="bg-amber-600"
            headerIcon={<ExclamationTriangleIcon className="h-5 w-5" />}
            maxWidth="3xl"
            footer={
                <StandardModal.Footer>
                    <div className="flex-1" />
                    <Button variant="outline" onClick={onCancel} disabled={processing}>
                        Cancelar
                    </Button>
                    <Button
                        variant="primary"
                        onClick={onConfirm}
                        disabled={processing || totalAfter === 0}
                    >
                        {processing
                            ? 'Criando...'
                            : groupsAfterRemoval.length > 1
                                ? `Confirmar e criar ${groupsAfterRemoval.length} remanejos`
                                : 'Confirmar e criar remanejo'}
                    </Button>
                </StandardModal.Footer>
            }
        >
            {!cigamAvailable && (
                <StandardModal.Highlight>
                    <strong>CIGAM indisponível.</strong> Não foi possível validar o saldo
                    em tempo real — alguns itens podem zerar/exceder o estoque sem aviso.
                </StandardModal.Highlight>
            )}

            {multiOrigin && (
                <StandardModal.Section title="Remanejos a serem criados">
                    <p className="text-sm text-gray-700 mb-3">
                        Os itens marcados vêm de origens diferentes. Cada loja origem terá
                        seu próprio remanejo, com fluxo independente de aprovação e recebimento:
                    </p>
                    <ul className="text-sm space-y-1 border border-gray-200 rounded divide-y divide-gray-200">
                        {groupsAfterRemoval.map((g) => (
                            <li key={g.origin_store_id} className="flex items-center justify-between px-3 py-2">
                                <span className="font-medium text-gray-800">{getStoreLabel(g.origin_store_id)}</span>
                                <span className="text-gray-500 text-xs">{g.items.length} item(ns)</span>
                            </li>
                        ))}
                    </ul>
                </StandardModal.Section>
            )}

            {problems.length > 0 && (
                <StandardModal.Section title={`Itens que vão zerar/exceder o estoque (${problems.length})`}>
                    <p className="text-sm text-gray-700 mb-3">
                        Esses itens pedem quantidade <strong>igual ou maior</strong> que o saldo
                        da loja origem. Marque <strong>"Remover"</strong> para excluir o item;
                        deixe desmarcado para seguir mesmo assim.
                    </p>
                    <div className="overflow-x-auto border border-gray-200 rounded">
                        <table className="min-w-full text-sm">
                            <thead className="bg-gray-50 text-[11px] uppercase text-gray-600">
                                <tr>
                                    <th className="px-3 py-2 text-left">Produto</th>
                                    <th className="px-3 py-2 text-left">Origem</th>
                                    <th className="px-3 py-2 text-right">Qtd / Saldo</th>
                                    <th className="px-3 py-2 text-center w-24">Remover</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200">
                                {problems.map((it) => {
                                    const removed = !!removeFlags[it._originalIdx];
                                    return (
                                        <tr key={it._originalIdx} className={removed ? 'opacity-40 bg-gray-50' : ''}>
                                            <td className="px-3 py-2">
                                                <div className="font-medium text-gray-900">
                                                    {it.product_name || it.product_reference || it.barcode}
                                                </div>
                                                <div className="text-xs text-gray-500">
                                                    {[it.product_color, it.size].filter(Boolean).join(' · ')}
                                                </div>
                                            </td>
                                            <td className="px-3 py-2 text-sm text-gray-700">
                                                {getStoreLabel(it._resolved_origin_id)}
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                <span className="font-mono font-bold text-red-700">
                                                    {it.qty_requested} / {it._resolved_stock}
                                                </span>
                                            </td>
                                            <td className="px-3 py-2 text-center">
                                                <input
                                                    type="checkbox"
                                                    checked={removed}
                                                    onChange={() => onToggleRemove(it._originalIdx)}
                                                    className="rounded border-gray-300 text-red-600 focus:ring-red-500"
                                                />
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </StandardModal.Section>
            )}

            {totalAfter === 0 && (
                <StandardModal.Highlight>
                    Todos os itens foram marcados pra remover. Cancele e ajuste a solicitação.
                </StandardModal.Highlight>
            )}
        </StandardModal>
    );
}
