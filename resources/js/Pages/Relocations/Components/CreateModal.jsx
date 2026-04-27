import { useState } from 'react';
import { router } from '@inertiajs/react';
import { PlusIcon, RectangleStackIcon, SparklesIcon, TrashIcon } from '@heroicons/react/24/outline';
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
};

const PRIORITY_OPTIONS = [
    { value: 'low', label: 'Baixa' },
    { value: 'normal', label: 'Normal' },
    { value: 'high', label: 'Alta' },
    { value: 'urgent', label: 'Urgente' },
];

export default function CreateModal({ show, onClose, selects = {}, isStoreScoped = false, scopedStoreId = null }) {
    const [form, setForm] = useState({
        relocation_type_id: '',
        origin_store_id: isStoreScoped ? (scopedStoreId ?? '') : '',
        destination_store_id: '',
        title: '',
        observations: '',
        priority: 'normal',
        deadline_days: '',
        items: [{ ...EMPTY_ITEM }],
    });
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);
    const [suggestionsOpen, setSuggestionsOpen] = useState(false);

    const updateField = (key, value) => setForm((prev) => ({ ...prev, [key]: value }));

    const updateItem = (idx, key, value) => {
        setForm((prev) => {
            const items = [...prev.items];
            items[idx] = { ...items[idx], [key]: value };
            return { ...prev, items };
        });
    };

    const addItem = () => setForm((prev) => ({ ...prev, items: [...prev.items, { ...EMPTY_ITEM }] }));

    const removeItem = (idx) => {
        if (form.items.length === 1) return;
        setForm((prev) => ({ ...prev, items: prev.items.filter((_, i) => i !== idx) }));
    };

    /**
     * Recebe items vindos do SuggestionsModal. Comportamento:
     *  - Se o form ainda tem só o item vazio default, substitui.
     *  - Caso contrário, anexa ao final.
     *  - Se a loja origem ainda não foi selecionada e todas as sugestões
     *    apontam pra mesma origem, pré-popula automaticamente.
     */
    const applySuggestions = (suggestedItems) => {
        if (!suggestedItems?.length) return;

        // Pré-popula loja origem se ainda vazia e há consenso entre sugestões
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

        // Limpa props internas (_suggested_*) antes de injetar nos items
        const cleaned = suggestedItems.map(({ _suggested_origin_id, _suggested_origin_code, ...rest }) => rest);

        setForm((prev) => {
            const isEmpty = prev.items.length === 1
                && (prev.items[0].product_reference || '').trim() === '';
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
            deadline_days: '',
            items: [{ ...EMPTY_ITEM }],
        });
        setErrors({});
    };

    const handleClose = () => {
        reset();
        onClose();
    };

    const submit = (e) => {
        e?.preventDefault();
        setProcessing(true);
        setErrors({});

        // Limpa items vazios e converte qty
        const items = form.items
            .filter((it) => (it.product_reference || '').trim() !== '')
            .map((it) => ({
                ...it,
                qty_requested: parseInt(it.qty_requested, 10) || 1,
            }));

        router.post(route('relocations.store'), {
            relocation_type_id: form.relocation_type_id || null,
            origin_store_id: form.origin_store_id || null,
            destination_store_id: form.destination_store_id || null,
            title: form.title || null,
            observations: form.observations || null,
            priority: form.priority,
            deadline_days: form.deadline_days || null,
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
                    </div>

                    <div>
                        <InputLabel htmlFor="deadline_days" value="Prazo (dias)" />
                        <TextInput
                            id="deadline_days"
                            type="number"
                            min="1"
                            max="365"
                            value={form.deadline_days}
                            onChange={(e) => updateField('deadline_days', e.target.value)}
                            placeholder="Ex: 7"
                            className="w-full"
                        />
                        <p className="text-xs text-gray-500 mt-1">Contado a partir da aprovação</p>
                    </div>

                    <div className="sm:col-span-2 lg:col-span-3">
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
                <div className="space-y-3">
                    {form.items.map((item, idx) => (
                        <div key={idx} className="bg-gray-50 rounded-md border border-gray-200 p-3">
                            <div className="flex items-center justify-between mb-2">
                                <span className="text-xs font-medium text-gray-700">Item {idx + 1}</span>
                                {form.items.length > 1 && (
                                    <Button
                                        variant="outline"
                                        size="xs"
                                        icon={TrashIcon}
                                        onClick={() => removeItem(idx)}
                                        type="button"
                                        iconOnly
                                        title="Remover item"
                                    />
                                )}
                            </div>
                            <div className="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-2">
                                <div className="sm:col-span-2">
                                    <InputLabel value="Referência *" className="text-xs" />
                                    <TextInput
                                        value={item.product_reference}
                                        onChange={(e) => updateItem(idx, 'product_reference', e.target.value)}
                                        placeholder="SKU"
                                        maxLength={100}
                                        className="w-full text-sm"
                                    />
                                </div>
                                <div className="sm:col-span-2">
                                    <InputLabel value="Descrição" className="text-xs" />
                                    <TextInput
                                        value={item.product_name}
                                        onChange={(e) => updateItem(idx, 'product_name', e.target.value)}
                                        placeholder="Nome do produto"
                                        maxLength={255}
                                        className="w-full text-sm"
                                    />
                                </div>
                                <div>
                                    <InputLabel value="Cor" className="text-xs" />
                                    <TextInput
                                        value={item.product_color}
                                        onChange={(e) => updateItem(idx, 'product_color', e.target.value)}
                                        maxLength={80}
                                        className="w-full text-sm"
                                    />
                                </div>
                                <div>
                                    <InputLabel value="Tamanho" className="text-xs" />
                                    <TextInput
                                        value={item.size}
                                        onChange={(e) => updateItem(idx, 'size', e.target.value)}
                                        maxLength={20}
                                        className="w-full text-sm"
                                    />
                                </div>
                                <div className="sm:col-span-2">
                                    <InputLabel value="Código de barras" className="text-xs" />
                                    <TextInput
                                        value={item.barcode}
                                        onChange={(e) => updateItem(idx, 'barcode', e.target.value)}
                                        maxLength={50}
                                        className="w-full text-sm font-mono"
                                    />
                                </div>
                                <div className="sm:col-span-2">
                                    <InputLabel value="Qtd. solicitada *" className="text-xs" />
                                    <TextInput
                                        type="number"
                                        min="1"
                                        value={item.qty_requested}
                                        onChange={(e) => updateItem(idx, 'qty_requested', e.target.value)}
                                        className="w-full text-sm"
                                    />
                                </div>
                                <div className="sm:col-span-2">
                                    <InputLabel value="Obs. do item" className="text-xs" />
                                    <TextInput
                                        value={item.observations}
                                        onChange={(e) => updateItem(idx, 'observations', e.target.value)}
                                        maxLength={500}
                                        className="w-full text-sm"
                                    />
                                </div>
                            </div>
                        </div>
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
