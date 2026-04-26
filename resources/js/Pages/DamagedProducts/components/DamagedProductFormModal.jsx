import { useEffect, useMemo, useState } from 'react';
import {
    DocumentTextIcon,
    AdjustmentsHorizontalIcon,
    PhotoIcon,
    PencilSquareIcon,
    XMarkIcon,
} from '@heroicons/react/24/outline';
import { useForm } from '@inertiajs/react';
import StandardModal from '@/Components/StandardModal';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import Checkbox from '@/Components/Checkbox';
import Button from '@/Components/Button';
import { maskMoney, parseMoney } from '@/Hooks/useMasks';

const FOOT_OPTIONS_DAMAGED = [
    { value: 'left',  label: 'Pé esquerdo' },
    { value: 'right', label: 'Pé direito' },
    { value: 'both',  label: 'Ambos os pés' },
    { value: 'na',    label: 'Não se aplica (não calçado)' },
];

const FOOT_OPTIONS_MISMATCHED = [
    { value: 'left',  label: 'Pé esquerdo' },
    { value: 'right', label: 'Pé direito' },
];

export default function DamagedProductFormModal({
    show,
    onClose,
    onSuccess,
    mode = 'create', // 'create' | 'edit'
    initial = null,
    selects = {},
    isStoreScoped = false,
    scopedStoreId = null,
}) {
    const isEdit = mode === 'edit';
    const empty = {
        store_id: scopedStoreId || '',
        product_id: '',
        product_reference: '',
        product_name: '',
        product_color: '',
        brand_cigam_code: '',
        product_size: '',
        is_mismatched: false,
        is_damaged: false,
        mismatched_foot: '',
        mismatched_actual_size: '',
        mismatched_expected_size: '',
        damage_type_id: '',
        damaged_foot: '',
        damage_description: '',
        is_repairable: false,
        estimated_repair_cost: '',
        notes: '',
        photos: [],
    };

    const { data, setData, post, put, processing, errors, reset, transform } = useForm(empty);

    // Autocomplete de produto por reference
    const [searchTerm, setSearchTerm] = useState('');
    const [searchResults, setSearchResults] = useState([]);
    const [searching, setSearching] = useState(false);

    useEffect(() => {
        if (!show) return;
        if (isEdit && initial) {
            setData({
                ...empty,
                store_id: initial.store?.id ?? '',
                product_id: initial.product_id ?? '',
                product_reference: initial.product_reference ?? '',
                product_name: initial.product_name ?? '',
                product_color: initial.product_color ?? '',
                brand_cigam_code: initial.brand_cigam_code ?? '',
                product_size: initial.product_size ?? '',
                is_mismatched: !!initial.is_mismatched,
                is_damaged: !!initial.is_damaged,
                mismatched_foot: initial.mismatched_foot ?? '',
                mismatched_actual_size: initial.mismatched_actual_size ?? '',
                mismatched_expected_size: initial.mismatched_expected_size ?? '',
                damage_type_id: initial.damage_type?.id ?? '',
                damaged_foot: initial.damaged_foot ?? '',
                damage_description: initial.damage_description ?? '',
                is_repairable: !!initial.is_repairable,
                estimated_repair_cost: initial.estimated_repair_cost
                    ? maskMoney(String(initial.estimated_repair_cost))
                    : '',
                notes: initial.notes ?? '',
                photos: [],
            });
        } else if (!isEdit) {
            reset();
            setData('store_id', scopedStoreId || '');
        }
    }, [show, isEdit, initial?.id]);

    // Autocomplete debounced
    useEffect(() => {
        if (searchTerm.length < 2) {
            setSearchResults([]);
            return;
        }
        const t = setTimeout(async () => {
            setSearching(true);
            try {
                const res = await window.axios.get(route('damaged-products.lookup.products'), {
                    params: { q: searchTerm },
                });
                setSearchResults(res.data.products || []);
            } catch {
                setSearchResults([]);
            } finally {
                setSearching(false);
            }
        }, 300);
        return () => clearTimeout(t);
    }, [searchTerm]);

    const pickProduct = (product) => {
        setData((prev) => ({
            ...prev,
            product_id: product.id,
            product_reference: product.reference,
            product_name: product.description || prev.product_name,
            brand_cigam_code: product.brand_cigam_code || prev.brand_cigam_code,
            product_color: product.color_cigam_code || prev.product_color,
        }));
        setSearchTerm('');
        setSearchResults([]);
    };

    const onSubmit = (e) => {
        e.preventDefault();

        transform((d) => ({
            ...d,
            estimated_repair_cost: d.estimated_repair_cost
                ? parseMoney(d.estimated_repair_cost)
                : null,
        }));

        const config = {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setSearchTerm('');
                setSearchResults([]);
                onSuccess?.();
            },
        };

        if (isEdit) {
            // PUT com files via _method=PUT pra Laravel processar multipart
            transform((d) => ({ ...d, _method: 'PUT' }));
            post(route('damaged-products.update', initial.ulid), config);
        } else {
            post(route('damaged-products.store'), config);
        }
    };

    const onFileChange = (e) => {
        setData('photos', Array.from(e.target.files || []));
    };

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title={isEdit ? 'Editar produto avariado' : 'Novo produto avariado'}
            subtitle={isEdit ? `${initial?.product_reference}` : 'Cadastre um par trocado, avariado ou ambos'}
            headerColor="bg-indigo-600"
            headerIcon={isEdit ? <PencilSquareIcon className="h-5 w-5" /> : <DocumentTextIcon className="h-5 w-5" />}
            maxWidth="3xl"
            onSubmit={onSubmit}
            footer={
                <StandardModal.Footer
                    onCancel={onClose}
                    onSubmit="submit"
                    submitLabel={isEdit ? 'Salvar alterações' : 'Cadastrar'}
                    processing={processing}
                />
            }
        >
            {/* Identificação */}
            <StandardModal.Section
                title="Identificação"
                icon={<DocumentTextIcon className="h-4 w-4" />}
            >
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <InputLabel htmlFor="store_id" value="Loja *" />
                        <select
                            id="store_id"
                            className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            value={data.store_id}
                            onChange={(e) => setData('store_id', e.target.value)}
                            disabled={isStoreScoped || isEdit}
                        >
                            <option value="">Selecione...</option>
                            {selects.stores?.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.code} — {s.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.store_id} className="mt-1" />
                    </div>

                    <div className="relative">
                        <InputLabel htmlFor="product_reference" value="Referência *" />
                        <TextInput
                            id="product_reference"
                            value={data.product_reference}
                            onChange={(e) => {
                                setData('product_reference', e.target.value.toUpperCase());
                                setSearchTerm(e.target.value);
                            }}
                            placeholder="Digite ao menos 2 caracteres..."
                            className="w-full"
                        />
                        <InputError message={errors.product_reference} className="mt-1" />

                        {/* Dropdown de autocomplete */}
                        {searchResults.length > 0 && (
                            <div className="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-md border bg-white shadow-lg">
                                {searchResults.map((p) => (
                                    <button
                                        type="button"
                                        key={p.id}
                                        onClick={() => pickProduct(p)}
                                        className="block w-full px-3 py-2 text-left text-sm hover:bg-indigo-50"
                                    >
                                        <span className="font-mono font-semibold">{p.reference}</span>
                                        <span className="ml-2 text-gray-600">{p.description}</span>
                                    </button>
                                ))}
                                {searching && (
                                    <div className="px-3 py-2 text-xs text-gray-500">Buscando...</div>
                                )}
                            </div>
                        )}
                    </div>

                    <div>
                        <InputLabel htmlFor="product_name" value="Descrição do produto" />
                        <TextInput
                            id="product_name"
                            value={data.product_name}
                            onChange={(e) => setData('product_name', e.target.value)}
                            className="w-full"
                        />
                    </div>

                    <div>
                        <InputLabel htmlFor="product_color" value="Cor" />
                        <TextInput
                            id="product_color"
                            value={data.product_color}
                            onChange={(e) => setData('product_color', e.target.value)}
                            className="w-full"
                        />
                    </div>

                    <div>
                        <InputLabel htmlFor="brand_cigam_code" value="Código da marca (CIGAM)" />
                        <TextInput
                            id="brand_cigam_code"
                            value={data.brand_cigam_code}
                            onChange={(e) => setData('brand_cigam_code', e.target.value)}
                            placeholder="Ex: AREZZO"
                            className="w-full"
                        />
                    </div>

                    <div>
                        <InputLabel htmlFor="product_size" value="Tamanho do par" />
                        <TextInput
                            id="product_size"
                            value={data.product_size}
                            onChange={(e) => setData('product_size', e.target.value)}
                            className="w-full"
                        />
                    </div>
                </div>
            </StandardModal.Section>

            {/* Tipo de problema */}
            <StandardModal.Section
                title="Tipo do problema"
                icon={<AdjustmentsHorizontalIcon className="h-4 w-4" />}
            >
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label className="flex items-start gap-3 rounded-lg border p-3 cursor-pointer hover:bg-yellow-50">
                        <Checkbox
                            checked={data.is_mismatched}
                            onChange={(e) => setData('is_mismatched', e.target.checked)}
                        />
                        <div>
                            <div className="font-medium text-sm">Par trocado</div>
                            <div className="text-xs text-gray-500">
                                Pé esquerdo ou direito está com tamanho diferente do par.
                            </div>
                        </div>
                    </label>

                    <label className="flex items-start gap-3 rounded-lg border p-3 cursor-pointer hover:bg-red-50">
                        <Checkbox
                            checked={data.is_damaged}
                            onChange={(e) => setData('is_damaged', e.target.checked)}
                        />
                        <div>
                            <div className="font-medium text-sm">Produto avariado</div>
                            <div className="text-xs text-gray-500">
                                Tem algum dano físico (rasgo, mancha, descostura etc).
                            </div>
                        </div>
                    </label>
                </div>
                <InputError message={errors.is_mismatched} className="mt-2" />
            </StandardModal.Section>

            {/* Detalhes mismatched */}
            {data.is_mismatched && (
                <StandardModal.Section
                    title="Detalhes do par trocado"
                    description="Informe qual pé está com tamanho diferente e os dois tamanhos."
                    headerClassName="bg-yellow-50"
                >
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <InputLabel htmlFor="mismatched_foot" value="Pé com tamanho trocado *" />
                            <select
                                id="mismatched_foot"
                                className="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"
                                value={data.mismatched_foot}
                                onChange={(e) => setData('mismatched_foot', e.target.value)}
                            >
                                <option value="">Selecione...</option>
                                {FOOT_OPTIONS_MISMATCHED.map((o) => (
                                    <option key={o.value} value={o.value}>{o.label}</option>
                                ))}
                            </select>
                            <InputError message={errors.mismatched_foot} className="mt-1" />
                        </div>

                        <div>
                            <InputLabel htmlFor="mismatched_actual_size" value="Tamanho real *" />
                            <TextInput
                                id="mismatched_actual_size"
                                value={data.mismatched_actual_size}
                                onChange={(e) => setData('mismatched_actual_size', e.target.value)}
                                placeholder="Ex: 38"
                                className="w-full"
                            />
                            <InputError message={errors.mismatched_actual_size} className="mt-1" />
                        </div>

                        <div>
                            <InputLabel htmlFor="mismatched_expected_size" value="Tamanho esperado *" />
                            <TextInput
                                id="mismatched_expected_size"
                                value={data.mismatched_expected_size}
                                onChange={(e) => setData('mismatched_expected_size', e.target.value)}
                                placeholder="Ex: 39"
                                className="w-full"
                            />
                            <InputError message={errors.mismatched_expected_size} className="mt-1" />
                        </div>
                    </div>
                </StandardModal.Section>
            )}

            {/* Detalhes damaged */}
            {data.is_damaged && (
                <StandardModal.Section
                    title="Detalhes da avaria"
                    description="Especifique o tipo, qual pé está afetado e a descrição."
                    headerClassName="bg-red-50"
                >
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel htmlFor="damage_type_id" value="Tipo de dano *" />
                            <select
                                id="damage_type_id"
                                className="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"
                                value={data.damage_type_id}
                                onChange={(e) => setData('damage_type_id', e.target.value)}
                            >
                                <option value="">Selecione...</option>
                                {selects.damageTypes?.map((dt) => (
                                    <option key={dt.id} value={dt.id}>{dt.name}</option>
                                ))}
                            </select>
                            <InputError message={errors.damage_type_id} className="mt-1" />
                        </div>

                        <div>
                            <InputLabel htmlFor="damaged_foot" value="Pé(s) avariado(s) *" />
                            <select
                                id="damaged_foot"
                                className="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"
                                value={data.damaged_foot}
                                onChange={(e) => setData('damaged_foot', e.target.value)}
                            >
                                <option value="">Selecione...</option>
                                {FOOT_OPTIONS_DAMAGED.map((o) => (
                                    <option key={o.value} value={o.value}>{o.label}</option>
                                ))}
                            </select>
                            <InputError message={errors.damaged_foot} className="mt-1" />
                        </div>

                        <div className="sm:col-span-2">
                            <InputLabel htmlFor="damage_description" value="Descrição do dano" />
                            <textarea
                                id="damage_description"
                                rows={3}
                                value={data.damage_description}
                                onChange={(e) => setData('damage_description', e.target.value)}
                                className="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"
                                placeholder="Detalhe a localização e a extensão do dano..."
                            />
                        </div>

                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="is_repairable"
                                checked={data.is_repairable}
                                onChange={(e) => setData('is_repairable', e.target.checked)}
                            />
                            <InputLabel htmlFor="is_repairable" value="Pode ser reparado na loja" className="!mb-0" />
                        </div>

                        <div>
                            <InputLabel htmlFor="estimated_repair_cost" value="Custo estimado de reparo" />
                            <TextInput
                                id="estimated_repair_cost"
                                value={data.estimated_repair_cost}
                                onChange={(e) => setData('estimated_repair_cost', maskMoney(e.target.value))}
                                placeholder="R$ 0,00"
                                className="w-full"
                                disabled={!data.is_repairable}
                            />
                        </div>
                    </div>
                </StandardModal.Section>
            )}

            {/* Fotos */}
            <StandardModal.Section
                title="Fotos do dano"
                description="JPG, PNG ou WebP. Máximo 10 fotos, 5MB cada."
                icon={<PhotoIcon className="h-4 w-4" />}
            >
                <input
                    type="file"
                    multiple
                    accept="image/jpeg,image/png,image/webp"
                    onChange={onFileChange}
                    className="block w-full text-sm text-gray-500 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100"
                />
                {data.photos?.length > 0 && (
                    <div className="mt-2 text-xs text-gray-600">
                        {data.photos.length} arquivo(s) selecionado(s)
                    </div>
                )}
                <InputError message={errors['photos.0']} className="mt-1" />
            </StandardModal.Section>

            {/* Observações */}
            <StandardModal.Section title="Observações">
                <textarea
                    rows={3}
                    value={data.notes}
                    onChange={(e) => setData('notes', e.target.value)}
                    className="block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"
                    placeholder="Notas internas (opcional)..."
                />
            </StandardModal.Section>
        </StandardModal>
    );
}
