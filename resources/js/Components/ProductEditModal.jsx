import { useState, useEffect } from 'react';
import StandardModal from '@/Components/StandardModal';
import FormSection from '@/Components/Shared/FormSection';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import Button from '@/Components/Button';
import { PencilSquareIcon, ExclamationTriangleIcon } from '@heroicons/react/24/outline';

export default function ProductEditModal({ show, onClose, productId, onSaved }) {
    const [product, setProduct] = useState(null);
    const [options, setOptions] = useState(null);
    const [form, setForm] = useState({});
    const [variants, setVariants] = useState([]);
    const [newVariant, setNewVariant] = useState({ size_cigam_code: '', barcode: '' });
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState({});

    useEffect(() => {
        if (show && productId) {
            setLoading(true);
            fetch(`/products/${productId}/edit`)
                .then(res => res.json())
                .then(data => {
                    setProduct(data.product);
                    setOptions(data.options);
                    setForm({
                        description: data.product.description || '',
                        sale_price: data.product.sale_price || '',
                        cost_price: data.product.cost_price || '',
                        brand_cigam_code: data.product.brand_cigam_code || '',
                        collection_cigam_code: data.product.collection_cigam_code || '',
                        subcollection_cigam_code: data.product.subcollection_cigam_code || '',
                        category_cigam_code: data.product.category_cigam_code || '',
                        color_cigam_code: data.product.color_cigam_code || '',
                        material_cigam_code: data.product.material_cigam_code || '',
                        article_complement_cigam_code: data.product.article_complement_cigam_code || '',
                    });
                    setVariants(data.product.variants || []);
                    setLoading(false);
                })
                .catch(() => setLoading(false));
        }
    }, [show, productId]);

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    const handleSubmit = () => {
        setSaving(true);
        setErrors({});

        fetch(`/products/${productId}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify(form),
        })
            .then(res => {
                if (!res.ok) return res.json().then(d => Promise.reject(d));
                return res.json();
            })
            .then(() => { onSaved?.(); onClose(); })
            .catch(err => { if (err.errors) setErrors(err.errors); setSaving(false); });
    };

    const setField = (field, value) => setForm(prev => ({ ...prev, [field]: value }));

    const handleVariantUpdate = (variantId, field, value) => {
        setVariants(prev => prev.map(v => v.id === variantId ? { ...v, [field]: value } : v));
    };

    const saveVariant = (variant) => {
        fetch(`/products/${productId}/variants/${variant.id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify({ barcode: variant.barcode, size_cigam_code: variant.size_cigam_code, is_active: variant.is_active }),
        }).then(res => res.json());
    };

    const addVariant = () => {
        if (!newVariant.size_cigam_code && !newVariant.barcode) return;
        fetch(`/products/${productId}/variants`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify(newVariant),
        })
            .then(res => res.json())
            .then(data => {
                if (data.variant) {
                    setVariants(prev => [...prev, data.variant]);
                    setNewVariant({ size_cigam_code: '', barcode: '' });
                }
            });
    };

    const generateEan = (variantId) => {
        fetch(`/products/${productId}/variants/${variantId}/generate-ean`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
        })
            .then(res => res.json())
            .then(data => {
                if (data.variant) setVariants(prev => prev.map(v => v.id === data.variant.id ? data.variant : v));
            });
    };

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title="Editar Produto"
            subtitle={product?.reference}
            headerColor="bg-yellow-600"
            headerIcon={<PencilSquareIcon className="h-5 w-5" />}
            loading={loading}
            maxWidth="5xl"
            onSubmit={handleSubmit}
            footer={product && (
                <StandardModal.Footer
                    onCancel={onClose}
                    onSubmit="submit"
                    submitLabel="Salvar Produto"
                    submitColor="bg-yellow-600 hover:bg-yellow-700"
                    processing={saving}
                />
            )}
        >
            {product && (
                <>
                    {/* Sync Lock Warning */}
                    <div className="flex items-start gap-2 bg-amber-50 border border-amber-200 rounded-lg p-3">
                        <ExclamationTriangleIcon className="h-5 w-5 text-amber-500 shrink-0 mt-0.5" />
                        <p className="text-sm text-amber-800">
                            Editar este produto irá bloqueá-lo para atualizações automáticas do CIGAM.
                        </p>
                    </div>

                    {/* Campos somente leitura */}
                    <FormSection title="Identificação" cols={2}>
                        <div>
                            <InputLabel value="Referência" />
                            <TextInput className="mt-1 w-full bg-gray-100 text-gray-500" value={product.reference} disabled />
                        </div>
                        <div>
                            <InputLabel value="Fornecedor" />
                            <TextInput className="mt-1 w-full bg-gray-100 text-gray-500" value={product.supplier_codigo_for || '-'} disabled />
                        </div>
                    </FormSection>

                    {/* Campos editáveis */}
                    <FormSection title="Dados do Produto" cols={2}>
                        <div className="col-span-full">
                            <InputLabel value="Descrição *" />
                            <TextInput className="mt-1 w-full" value={form.description} onChange={e => setField('description', e.target.value)} />
                            <InputError message={errors.description?.[0]} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Preço Venda" />
                            <TextInput type="number" step="0.01" className="mt-1 w-full" value={form.sale_price} onChange={e => setField('sale_price', e.target.value)} />
                        </div>
                        <div>
                            <InputLabel value="Preço Custo" />
                            <TextInput type="number" step="0.01" className="mt-1 w-full" value={form.cost_price} onChange={e => setField('cost_price', e.target.value)} />
                        </div>
                    </FormSection>

                    {/* Classificação */}
                    {options && (
                        <FormSection title="Classificação" cols={3}>
                            <CigamSelect label="Marca" value={form.brand_cigam_code} onChange={v => setField('brand_cigam_code', v)} options={options.brands} />
                            <CigamSelect label="Estação" value={form.collection_cigam_code} onChange={v => setField('collection_cigam_code', v)} options={options.collections} />
                            <CigamSelect label="Coleção" value={form.subcollection_cigam_code} onChange={v => setField('subcollection_cigam_code', v)} options={options.subcollections} />
                            <CigamSelect label="Tipo" value={form.category_cigam_code} onChange={v => setField('category_cigam_code', v)} options={options.categories} />
                            <CigamSelect label="Cor" value={form.color_cigam_code} onChange={v => setField('color_cigam_code', v)} options={options.colors} />
                            <CigamSelect label="Material" value={form.material_cigam_code} onChange={v => setField('material_cigam_code', v)} options={options.materials} />
                            <CigamSelect label="Grupo" value={form.article_complement_cigam_code} onChange={v => setField('article_complement_cigam_code', v)} options={options.article_complements} />
                        </FormSection>
                    )}

                    {/* Variantes */}
                    <StandardModal.Section title="Variantes">
                        <div className="overflow-x-auto -mx-4 -mb-4">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tamanho</th>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Código Barras</th>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">EAN-13</th>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ativo</th>
                                        <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200">
                                    {variants.map(v => (
                                        <tr key={v.id}>
                                            <td className="px-3 py-2">
                                                {options ? (
                                                    <select value={v.size_cigam_code || ''} onChange={e => handleVariantUpdate(v.id, 'size_cigam_code', e.target.value)}
                                                        className="w-full border-gray-300 rounded text-xs">
                                                        <option value="">-</option>
                                                        {options.sizes?.map(s => <option key={s.cigam_code} value={s.cigam_code}>{s.name}</option>)}
                                                    </select>
                                                ) : v.size_cigam_code}
                                            </td>
                                            <td className="px-3 py-2">
                                                <input type="text" value={v.barcode || ''} onChange={e => handleVariantUpdate(v.id, 'barcode', e.target.value)}
                                                    className="w-full border-gray-300 rounded text-xs font-mono" />
                                            </td>
                                            <td className="px-3 py-2 text-xs font-mono text-gray-600">{v.aux_reference || '-'}</td>
                                            <td className="px-3 py-2">
                                                <input type="checkbox" checked={v.is_active !== false} onChange={e => handleVariantUpdate(v.id, 'is_active', e.target.checked)}
                                                    className="rounded border-gray-300 text-indigo-600" />
                                            </td>
                                            <td className="px-3 py-2 space-x-1">
                                                <Button variant="info" size="xs" onClick={() => saveVariant(v)}>Salvar</Button>
                                                <Button variant="success" size="xs" onClick={() => generateEan(v.id)}>EAN</Button>
                                            </td>
                                        </tr>
                                    ))}
                                    <tr className="bg-gray-50">
                                        <td className="px-3 py-2">
                                            {options && (
                                                <select value={newVariant.size_cigam_code} onChange={e => setNewVariant(prev => ({ ...prev, size_cigam_code: e.target.value }))}
                                                    className="w-full border-gray-300 rounded text-xs">
                                                    <option value="">Tamanho</option>
                                                    {options.sizes?.map(s => <option key={s.cigam_code} value={s.cigam_code}>{s.name}</option>)}
                                                </select>
                                            )}
                                        </td>
                                        <td className="px-3 py-2">
                                            <input type="text" value={newVariant.barcode} onChange={e => setNewVariant(prev => ({ ...prev, barcode: e.target.value }))}
                                                placeholder="Código barras" className="w-full border-gray-300 rounded text-xs" />
                                        </td>
                                        <td className="px-3 py-2" />
                                        <td className="px-3 py-2" />
                                        <td className="px-3 py-2">
                                            <Button variant="primary" size="xs" onClick={addVariant}>Adicionar</Button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </StandardModal.Section>
                </>
            )}
        </StandardModal>
    );
}

function CigamSelect({ label, value, onChange, options }) {
    return (
        <div>
            <InputLabel value={label} />
            <select value={value || ''} onChange={e => onChange(e.target.value)}
                className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                <option value="">-</option>
                {options?.map(opt => <option key={opt.cigam_code} value={opt.cigam_code}>{opt.name}</option>)}
            </select>
        </div>
    );
}
