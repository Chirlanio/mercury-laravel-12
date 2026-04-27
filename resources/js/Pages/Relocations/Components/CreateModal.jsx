import { useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import {
    PlusIcon, RectangleStackIcon, SparklesIcon, TrashIcon,
    CheckCircleIcon, ExclamationTriangleIcon, MagnifyingGlassIcon,
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
    // Estado interno do lookup AJAX (não vai pro backend)
    _lookup_mode: null,         // 'barcode' | 'reference' | null
    _lookup_status: null,       // 'idle' | 'loading' | 'ok' | 'notfound' | 'error'
    _lookup_error: null,
    _available_sizes: [],       // [{variant_id, barcode, size, size_cigam_code}]
    _product_id: null,
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
        items: [{ ...EMPTY_ITEM }],
    });
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);
    const [suggestionsOpen, setSuggestionsOpen] = useState(false);

    // Tokens evitam race entre lookups concorrentes (onBlur + Enter)
    const lookupTokensRef = useRef({});

    const updateField = (key, value) => setForm((prev) => ({ ...prev, [key]: value }));

    const updateItem = (idx, key, value) => {
        setForm((prev) => {
            const items = [...prev.items];
            items[idx] = { ...items[idx], [key]: value };
            return { ...prev, items };
        });
    };

    const updateItemMany = (idx, patch) => {
        setForm((prev) => {
            const items = [...prev.items];
            items[idx] = { ...items[idx], ...patch };
            return { ...prev, items };
        });
    };

    const addItem = () => setForm((prev) => ({ ...prev, items: [...prev.items, { ...EMPTY_ITEM }] }));

    const removeItem = (idx) => {
        if (form.items.length === 1) return;
        setForm((prev) => ({ ...prev, items: prev.items.filter((_, i) => i !== idx) }));
    };

    // ------------------------------------------------------------------
    // Lookup AJAX no catálogo (auto-fill da linha)
    // ------------------------------------------------------------------
    const performLookup = async (idx, params) => {
        const token = Date.now() + Math.random();
        lookupTokensRef.current[idx] = token;

        updateItemMany(idx, { _lookup_status: 'loading', _lookup_error: null });

        try {
            const res = await window.axios.get(route('relocations.lookup-product'), { params });
            // Race protection
            if (lookupTokensRef.current[idx] !== token) return;

            const d = res.data || {};
            if (!d.found) {
                updateItemMany(idx, {
                    _lookup_status: 'notfound',
                    _lookup_error: d.error || 'Produto não encontrado',
                });
                return;
            }

            // Modo barcode — tudo preenchido + size readonly
            if (d.mode === 'barcode') {
                updateItemMany(idx, {
                    product_reference: d.product_reference || '',
                    product_name: d.product_name || '',
                    product_color: d.product_color || '',
                    barcode: d.barcode || '',
                    size: d.size || '',
                    _product_id: d.product_id,
                    _lookup_mode: 'barcode',
                    _lookup_status: 'ok',
                    _lookup_error: null,
                    _available_sizes: [],
                });
                return;
            }

            // Modo reference — nome/cor preenchidos, size vira select dos variants
            if (d.mode === 'reference') {
                const sizes = d.variants || [];
                updateItemMany(idx, {
                    product_reference: d.product_reference || '',
                    product_name: d.product_name || '',
                    product_color: d.product_color || '',
                    size: '',
                    barcode: '',
                    _product_id: d.product_id,
                    _lookup_mode: 'reference',
                    _lookup_status: 'ok',
                    _lookup_error: null,
                    _available_sizes: sizes,
                });
            }
        } catch (e) {
            if (lookupTokensRef.current[idx] !== token) return;
            updateItemMany(idx, {
                _lookup_status: 'error',
                _lookup_error: e.response?.data?.error || 'Falha na consulta ao catálogo',
            });
        }
    };

    const lookupByBarcode = (idx) => {
        const it = form.items[idx];
        if (!it.barcode || it.barcode.trim() === '') return;
        performLookup(idx, { barcode: it.barcode.trim() });
    };

    const lookupByReference = (idx) => {
        const it = form.items[idx];
        if (!it.product_reference || it.product_reference.trim() === '') return;
        performLookup(idx, { reference: it.product_reference.trim() });
    };

    // No modo reference, ao escolher um tamanho preenche o barcode da variant
    const handleSizeSelect = (idx, sizeValue) => {
        const it = form.items[idx];
        const variant = (it._available_sizes || []).find((v) => String(v.size) === String(sizeValue));
        updateItemMany(idx, {
            size: sizeValue,
            barcode: variant?.barcode || '',
        });
    };

    /**
     * Recebe items vindos do SuggestionsModal — pré-popula loja origem
     * se houver consenso, anexa ou substitui items dependendo do estado.
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

        const cleaned = suggestedItems.map(({ _suggested_origin_id, _suggested_origin_code, ...rest }) => ({
            ...EMPTY_ITEM,
            ...rest,
            _lookup_mode: 'barcode',
            _lookup_status: 'ok',
        }));

        setForm((prev) => {
            const isEmpty = prev.items.length === 1
                && (prev.items[0].product_reference || '').trim() === ''
                && (prev.items[0].barcode || '').trim() === '';
            return {
                ...prev,
                items: isEmpty ? cleaned : [...prev.items, ...cleaned],
            };
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
            items: [{ ...EMPTY_ITEM }],
        });
        setErrors({});
        lookupTokensRef.current = {};
    };

    const handleClose = () => {
        reset();
        onClose();
    };

    const submit = (e) => {
        e?.preventDefault();
        setProcessing(true);
        setErrors({});

        // Limpa items vazios e remove campos internos
        const items = form.items
            .filter((it) => (it.product_reference || it.barcode || '').toString().trim() !== '')
            .map(({ _lookup_mode, _lookup_status, _lookup_error, _available_sizes, _product_id, ...rest }) => ({
                ...rest,
                product_id: _product_id ?? null,
                qty_requested: parseInt(rest.qty_requested, 10) || 1,
            }));

        router.post(route('relocations.store'), {
            relocation_type_id: form.relocation_type_id || null,
            origin_store_id: form.origin_store_id || null,
            destination_store_id: form.destination_store_id || null,
            title: form.title || null,
            observations: form.observations || null,
            priority: form.priority,
            items,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
            onError: (errs) => setErrors(errs),
            onFinish: () => setProcessing(false),
        });
    };

    const currentDeadline = priorityDeadlines[form.priority] ?? 3;

    return (
        <StandardModal
            show={show}
            onClose={handleClose}
            title="Novo Remanejo"
            subtitle="Solicitação de transferência entre lojas"
            headerColor="bg-indigo-600"
            headerIcon={<RectangleStackIcon className="h-5 w-5" />}
            maxWidth="5xl"
            onSubmit={submit}
            footer={
                <StandardModal.Footer
                    onCancel={handleClose}
                    onSubmit="submit"
                    submitLabel="Criar remanejo"
                    processing={processing}
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
                        <InputLabel htmlFor="origin_store_id" value="Loja origem *" />
                        <select
                            id="origin_store_id"
                            value={form.origin_store_id}
                            onChange={(e) => updateField('origin_store_id', e.target.value)}
                            disabled={isStoreScoped}
                            className="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm disabled:bg-gray-100"
                        >
                            <option value="">Selecione...</option>
                            {selects.stores?.map((s) => (
                                <option key={s.id} value={s.id}>{s.code} — {s.name}</option>
                            ))}
                        </select>
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
                            {selects.stores
                                ?.filter((s) => String(s.id) !== String(form.origin_store_id))
                                .map((s) => (
                                    <option key={s.id} value={s.id}>{s.code} — {s.name}</option>
                                ))}
                        </select>
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
                title={`Produtos (${form.items.length})`}
                actions={
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            icon={SparklesIcon}
                            onClick={() => {
                                if (!form.destination_store_id) {
                                    alert('Selecione a loja destino antes de gerar sugestões.');
                                    return;
                                }
                                setSuggestionsOpen(true);
                            }}
                            type="button"
                        >
                            Gerar sugestões
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            icon={PlusIcon}
                            onClick={addItem}
                            type="button"
                        >
                            Adicionar item
                        </Button>
                    </div>
                }
            >
                <p className="text-xs text-gray-600 mb-3">
                    Digite ou bipe o <strong>código de barras / EAN</strong> e tudo é preenchido
                    automaticamente. Se preferir, digite a <strong>referência</strong> e selecione
                    o tamanho — a descrição e a cor virão do catálogo.
                </p>
                <div className="space-y-3">
                    {form.items.map((item, idx) => (
                        <ItemCard
                            key={idx}
                            idx={idx}
                            item={item}
                            canRemove={form.items.length > 1}
                            onChange={(key, value) => updateItem(idx, key, value)}
                            onRemove={() => removeItem(idx)}
                            onLookupBarcode={() => lookupByBarcode(idx)}
                            onLookupReference={() => lookupByReference(idx)}
                            onSizeSelect={(v) => handleSizeSelect(idx, v)}
                        />
                    ))}
                </div>
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
                onApply={applySuggestions}
            />
        </StandardModal>
    );
}

// ====================================================================
// ItemCard — linha de produto com lookup AJAX integrado
// ====================================================================
function ItemCard({ idx, item, canRemove, onChange, onRemove, onLookupBarcode, onLookupReference, onSizeSelect }) {
    const isLoading = item._lookup_status === 'loading';
    const productInfoFromCatalog = item._lookup_status === 'ok';
    const sizesFromCatalog = item._lookup_mode === 'reference' && (item._available_sizes?.length ?? 0) > 0;

    return (
        <div className="bg-gray-50 rounded-md border border-gray-200 p-3">
            <div className="flex items-center justify-between mb-2">
                <span className="text-xs font-medium text-gray-700">
                    Item {idx + 1}
                    {item._lookup_status === 'ok' && (
                        <span className="ml-2 inline-flex items-center gap-1 text-green-700">
                            <CheckCircleIcon className="h-3.5 w-3.5" />
                            <span className="text-[11px]">
                                {item._lookup_mode === 'barcode' ? 'Casado por código de barras' : 'Casado por referência'}
                            </span>
                        </span>
                    )}
                    {item._lookup_status === 'notfound' && (
                        <span className="ml-2 inline-flex items-center gap-1 text-amber-700">
                            <ExclamationTriangleIcon className="h-3.5 w-3.5" />
                            <span className="text-[11px]">{item._lookup_error}</span>
                        </span>
                    )}
                    {isLoading && (
                        <span className="ml-2 text-[11px] text-gray-500">Consultando catálogo...</span>
                    )}
                </span>
                {canRemove && (
                    <Button
                        variant="outline"
                        size="xs"
                        icon={TrashIcon}
                        onClick={onRemove}
                        type="button"
                        iconOnly
                        title="Remover item"
                    />
                )}
            </div>

            <div className="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-2">
                {/* Código de barras (input principal — match exato) */}
                <div className="sm:col-span-3">
                    <InputLabel value="Código de barras / EAN" className="text-xs" />
                    <div className="relative">
                        <TextInput
                            value={item.barcode}
                            onChange={(e) => onChange('barcode', e.target.value)}
                            onBlur={() => item.barcode?.trim() && onLookupBarcode()}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    e.preventDefault();
                                    onLookupBarcode();
                                }
                            }}
                            placeholder="Bipe ou digite e pressione Enter"
                            maxLength={50}
                            disabled={isLoading}
                            className="w-full text-sm font-mono pr-9"
                        />
                        <button
                            type="button"
                            onClick={onLookupBarcode}
                            className="absolute right-1 top-1/2 -translate-y-1/2 p-1.5 text-gray-500 hover:text-indigo-700"
                            title="Consultar catálogo"
                        >
                            <MagnifyingGlassIcon className="h-4 w-4" />
                        </button>
                    </div>
                </div>

                {/* Referência (alternativa — exige depois escolher tamanho) */}
                <div className="sm:col-span-3">
                    <InputLabel value="Referência (alternativa ao EAN)" className="text-xs" />
                    <div className="relative">
                        <TextInput
                            value={item.product_reference}
                            onChange={(e) => onChange('product_reference', e.target.value)}
                            onBlur={() => {
                                if (item.product_reference?.trim() && !item.barcode?.trim()) {
                                    onLookupReference();
                                }
                            }}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    e.preventDefault();
                                    onLookupReference();
                                }
                            }}
                            placeholder="SKU do produto"
                            maxLength={100}
                            disabled={isLoading}
                            className="w-full text-sm pr-9"
                        />
                        <button
                            type="button"
                            onClick={onLookupReference}
                            className="absolute right-1 top-1/2 -translate-y-1/2 p-1.5 text-gray-500 hover:text-indigo-700"
                            title="Consultar catálogo"
                        >
                            <MagnifyingGlassIcon className="h-4 w-4" />
                        </button>
                    </div>
                </div>

                {/* Descrição — sempre readonly (do catálogo) */}
                <div className="sm:col-span-3">
                    <InputLabel value="Descrição (catálogo)" className="text-xs" />
                    <TextInput
                        value={item.product_name || ''}
                        readOnly
                        placeholder={productInfoFromCatalog ? '' : '— preencha referência ou EAN —'}
                        className="w-full text-sm bg-gray-100 cursor-not-allowed"
                    />
                </div>

                {/* Cor — sempre readonly (do catálogo) */}
                <div>
                    <InputLabel value="Cor (catálogo)" className="text-xs" />
                    <TextInput
                        value={item.product_color || ''}
                        readOnly
                        placeholder={productInfoFromCatalog ? '' : '—'}
                        className="w-full text-sm bg-gray-100 cursor-not-allowed"
                    />
                </div>

                {/* Tamanho — select se modo reference, readonly se barcode, livre se ainda nada */}
                <div>
                    <InputLabel value="Tamanho *" className="text-xs" />
                    {sizesFromCatalog ? (
                        <select
                            value={item.size || ''}
                            onChange={(e) => onSizeSelect(e.target.value)}
                            className="block w-full rounded-md border-gray-300 shadow-sm text-sm"
                        >
                            <option value="">Selecione...</option>
                            {item._available_sizes.map((v) => (
                                <option key={v.variant_id} value={v.size}>{v.size}</option>
                            ))}
                        </select>
                    ) : item._lookup_mode === 'barcode' ? (
                        <TextInput
                            value={item.size || ''}
                            readOnly
                            className="w-full text-sm bg-gray-100 cursor-not-allowed"
                        />
                    ) : (
                        <TextInput
                            value={item.size || ''}
                            onChange={(e) => onChange('size', e.target.value)}
                            placeholder="—"
                            maxLength={20}
                            className="w-full text-sm"
                        />
                    )}
                </div>

                <div>
                    <InputLabel value="Qtd. solicitada *" className="text-xs" />
                    <TextInput
                        type="number"
                        min="1"
                        value={item.qty_requested}
                        onChange={(e) => onChange('qty_requested', e.target.value)}
                        className="w-full text-sm"
                    />
                </div>

                <div className="sm:col-span-3">
                    <InputLabel value="Obs. do item" className="text-xs" />
                    <TextInput
                        value={item.observations}
                        onChange={(e) => onChange('observations', e.target.value)}
                        maxLength={500}
                        className="w-full text-sm"
                    />
                </div>
            </div>
        </div>
    );
}
