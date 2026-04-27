import { useEffect, useState } from 'react';
import {
    DocumentTextIcon,
    AdjustmentsHorizontalIcon,
    PhotoIcon,
    PencilSquareIcon,
} from '@heroicons/react/24/outline';
import { useForm } from '@inertiajs/react';
import StandardModal from '@/Components/StandardModal';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import Checkbox from '@/Components/Checkbox';
import { maskMoney, parseMoney } from '@/Hooks/useMasks';

const FOOT_OPTIONS_DAMAGED = [
    { value: 'left',  label: 'Pé esquerdo' },
    { value: 'right', label: 'Pé direito' },
    { value: 'both',  label: 'Ambos os pés' },
    { value: 'na',    label: 'Não se aplica (não calçado)' },
];

const MAX_PHOTOS = 5;

export default function DamagedProductFormModal({
    show,
    onClose,
    onSuccess,
    mode = 'create',
    initial = null,
    selects = {},
    isStoreScoped = false,
    scopedStoreId = null,
    canManage = false,
}) {
    const isEdit = mode === 'edit';
    const empty = {
        store_id: scopedStoreId || '',
        product_id: '',
        product_reference: '',
        product_name: '',
        product_color: '',
        brand_cigam_code: '',
        brand_name: '',
        is_mismatched: false,
        is_damaged: false,
        mismatched_left_size: '',
        mismatched_right_size: '',
        damage_type_id: '',
        damaged_foot: '',
        damaged_size: '',
        damage_description: '',
        is_repairable: false,
        estimated_repair_cost: '',
        notes: '',
        photos: [],
    };

    const { data, setData, post, processing, errors, reset, transform } = useForm(empty);

    // Autocomplete de produto
    const [searchTerm, setSearchTerm] = useState('');
    const [searchResults, setSearchResults] = useState([]);
    const [searching, setSearching] = useState(false);

    // Tamanhos disponíveis do produto selecionado (via product-sizes endpoint)
    const [productSizes, setProductSizes] = useState([]);
    const [loadingSizes, setLoadingSizes] = useState(false);

    // Reset/load no abrir do modal
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
                brand_name: initial.brand_name ?? '',
                is_mismatched: !!initial.is_mismatched,
                is_damaged: !!initial.is_damaged,
                mismatched_left_size: initial.mismatched_left_size ?? '',
                mismatched_right_size: initial.mismatched_right_size ?? '',
                damage_type_id: initial.damage_type?.id ?? '',
                damaged_foot: initial.damaged_foot ?? '',
                damaged_size: initial.damaged_size ?? '',
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

    // Quando product_id muda, busca os tamanhos disponíveis (variants)
    useEffect(() => {
        if (!show || !data.product_id) {
            setProductSizes([]);
            return;
        }
        setLoadingSizes(true);
        window.axios
            .get(route('damaged-products.lookup.product-sizes', data.product_id))
            .then((res) => setProductSizes(res.data.sizes || []))
            .catch(() => setProductSizes([]))
            .finally(() => setLoadingSizes(false));
    }, [show, data.product_id]);

    // Autocomplete debounced (busca produto pra resolver brand/color/sizes)
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
            brand_name: product.brand_name || prev.brand_name,
            product_color: product.color_name || prev.product_color,
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
                setProductSizes([]);
                onSuccess?.();
            },
        };

        if (isEdit) {
            transform((d) => ({ ...d, _method: 'PUT' }));
            post(route('damaged-products.update', initial.ulid), config);
        } else {
            post(route('damaged-products.store'), config);
        }
    };

    const onFileChange = (e) => {
        const files = Array.from(e.target.files || []).slice(0, MAX_PHOTOS);
        setData('photos', files);
    };

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title={isEdit ? 'Editar produto avariado' : 'Novo produto avariado'}
            subtitle={isEdit ? `${initial?.product_reference}` : 'Cadastre um par trocado, avariado ou ambos'}
            headerColor="bg-indigo-600"
            headerIcon={isEdit ? <PencilSquareIcon className="h-5 w-5" /> : <DocumentTextIcon className="h-5 w-5" />}
            maxWidth="5xl"
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
                            className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
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
                                        {p.brand_name && (
                                            <span className="ml-2 text-xs text-gray-500">[{p.brand_name}]</span>
                                        )}
                                    </button>
                                ))}
                                {searching && (
                                    <div className="px-3 py-2 text-xs text-gray-500">Buscando...</div>
                                )}
                            </div>
                        )}
                    </div>

                    <ReadOnlyField label="Descrição do produto" value={data.product_name} />
                    <ReadOnlyField label="Cor" value={data.product_color} />
                    <ReadOnlyField
                        label="Marca"
                        value={data.brand_name}
                        sub={data.brand_cigam_code ? `Código CIGAM: ${data.brand_cigam_code}` : null}
                    />
                </div>
                {data.product_id ? null : (
                    <p className="mt-2 text-xs text-amber-600">
                        Selecione uma referência do catálogo para preencher descrição, cor e marca automaticamente.
                    </p>
                )}
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
                                Pé esquerdo e direito têm tamanhos diferentes (par embaralhado).
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

            {/* Detalhes mismatched — 2 grids clicáveis (Esq + Dir) */}
            {data.is_mismatched && (
                <StandardModal.Section
                    title="Detalhes do par trocado"
                    description={
                        data.product_id
                            ? 'Clique no tamanho de cada pé que está fisicamente neste par.'
                            : 'Selecione uma referência de produto cadastrada para listar os tamanhos disponíveis.'
                    }
                    headerClassName="bg-yellow-50"
                >
                    {!data.product_id ? (
                        <div className="rounded-md bg-yellow-50 border border-yellow-200 p-3 text-sm text-yellow-700">
                            Use o autocomplete de Referência acima para escolher um produto. Os tamanhos disponíveis virão das variantes cadastradas.
                        </div>
                    ) : loadingSizes ? (
                        <div className="text-sm text-gray-500">Carregando tamanhos...</div>
                    ) : productSizes.length === 0 ? (
                        <div className="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                            Esta referência não tem variantes de tamanho cadastradas.
                        </div>
                    ) : (
                        <div className="space-y-3">
                            <SizeRow
                                label="Esquerdo"
                                sizes={productSizes}
                                selected={data.mismatched_left_size}
                                onSelect={(s) => setData('mismatched_left_size', s)}
                            />
                            <SizeRow
                                label="Direito"
                                sizes={productSizes}
                                selected={data.mismatched_right_size}
                                onSelect={(s) => setData('mismatched_right_size', s)}
                            />
                            <InputError message={errors.mismatched_left_size} className="mt-1" />
                            <InputError message={errors.mismatched_right_size} className="mt-1" />
                        </div>
                    )}
                </StandardModal.Section>
            )}

            {/* Detalhes damaged */}
            {data.is_damaged && (
                <StandardModal.Section
                    title="Detalhes da avaria"
                    description="Especifique tipo, pé(s) e tamanho. O tamanho é necessário para o match com produtos complementares."
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

                        {/* Tamanho avariado — só faz sentido se foot != 'na' */}
                        {data.damaged_foot && data.damaged_foot !== 'na' && (
                            <div className="sm:col-span-2">
                                <InputLabel value="Tamanho do pé avariado *" />
                                {!data.product_id ? (
                                    <div className="rounded-md bg-yellow-50 border border-yellow-200 p-3 text-sm text-yellow-700">
                                        Selecione uma referência de produto para listar os tamanhos disponíveis.
                                    </div>
                                ) : loadingSizes ? (
                                    <div className="text-sm text-gray-500">Carregando tamanhos...</div>
                                ) : productSizes.length === 0 ? (
                                    <div className="rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                                        Esta referência não tem variantes de tamanho cadastradas.
                                    </div>
                                ) : (
                                    <SizeRow
                                        label="Tamanho"
                                        sizes={productSizes}
                                        selected={data.damaged_size}
                                        onSelect={(s) => setData('damaged_size', s)}
                                    />
                                )}
                                <InputError message={errors.damaged_size} className="mt-1" />
                            </div>
                        )}

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

                        {/* Reparo: só visível pra users com MANAGE (admin/support) */}
                        {canManage && (
                            <>
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
                            </>
                        )}
                    </div>
                </StandardModal.Section>
            )}

            {/* Fotos — obrigatórias quando avariado */}
            <StandardModal.Section
                title={data.is_damaged ? 'Fotos do dano *' : 'Fotos do dano'}
                description={
                    data.is_damaged
                        ? `JPG, PNG ou WebP. Mínimo 1 e máximo ${MAX_PHOTOS} fotos, 5MB cada. Obrigatório para documentar a avaria.`
                        : `JPG, PNG ou WebP. Máximo ${MAX_PHOTOS} fotos, 5MB cada.`
                }
                icon={<PhotoIcon className="h-4 w-4" />}
                headerClassName={data.is_damaged && (data.photos?.length ?? 0) === 0 ? 'bg-red-50' : undefined}
            >
                <input
                    type="file"
                    multiple
                    accept="image/jpeg,image/png,image/webp"
                    onChange={onFileChange}
                    className="block w-full text-sm text-gray-500 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100"
                />
                {data.photos?.length > 0 ? (
                    <div className="mt-2 text-xs text-gray-600">
                        {data.photos.length} arquivo(s) selecionado(s)
                        {data.photos.length === MAX_PHOTOS && ' (máximo atingido)'}
                    </div>
                ) : data.is_damaged ? (
                    <div className="mt-2 text-xs text-red-600">
                        Anexe ao menos uma foto do dano para concluir o cadastro.
                    </div>
                ) : null}
                <InputError message={errors['photos']} className="mt-1" />
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

/**
 * Campo readonly mostrando label + valor. Usado para Descrição/Cor/Marca
 * (auto-preenchidos do catálogo, não editáveis pelo usuário).
 */
function ReadOnlyField({ label, value, sub = null }) {
    return (
        <div>
            <InputLabel value={label} />
            <div className="mt-1 px-3 py-2 rounded-md border border-gray-200 bg-gray-50 text-sm text-gray-700 min-h-[38px]">
                {value || <span className="text-gray-400 italic">Será preenchido pelo catálogo</span>}
            </div>
            {sub && <p className="mt-1 text-xs text-gray-500">{sub}</p>}
        </div>
    );
}

/**
 * Linha de tamanhos clicáveis. Renderiza label do pé + grid horizontal
 * scrollável com os tamanhos disponíveis. O selecionado fica destacado
 * em indigo.
 */
function SizeRow({ label, sizes, selected, onSelect }) {
    return (
        <div className="flex items-center gap-3">
            <div className="w-20 shrink-0 text-sm font-medium text-gray-700">{label}</div>
            <div className="flex flex-wrap gap-1.5">
                {sizes.map((s) => {
                    const isSelected = String(selected) === String(s.cigam_code);
                    return (
                        <button
                            key={s.cigam_code}
                            type="button"
                            onClick={() => onSelect(s.cigam_code)}
                            className={`
                                min-w-[44px] min-h-[44px] px-3 py-2 rounded-md border text-sm font-medium transition
                                ${isSelected
                                    ? 'bg-indigo-600 border-indigo-600 text-white shadow-sm'
                                    : 'bg-white border-gray-300 text-gray-700 hover:bg-indigo-50 hover:border-indigo-400'
                                }
                            `}
                        >
                            {s.name}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
