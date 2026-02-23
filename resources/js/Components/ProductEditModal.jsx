import { useState, useEffect } from 'react';
import Modal from '@/Components/Modal';

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

    const handleSubmit = (e) => {
        e.preventDefault();
        setSaving(true);
        setErrors({});

        fetch(`/products/${productId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify(form),
        })
            .then(res => {
                if (!res.ok) return res.json().then(d => Promise.reject(d));
                return res.json();
            })
            .then(() => {
                onSaved && onSaved();
                onClose();
            })
            .catch(err => {
                if (err.errors) setErrors(err.errors);
                setSaving(false);
            });
    };

    const handleVariantUpdate = (variantId, field, value) => {
        setVariants(prev => prev.map(v =>
            v.id === variantId ? { ...v, [field]: value } : v
        ));
    };

    const saveVariant = (variant) => {
        fetch(`/products/${productId}/variants/${variant.id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify({
                barcode: variant.barcode,
                size_cigam_code: variant.size_cigam_code,
                is_active: variant.is_active,
            }),
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
                if (data.variant) {
                    setVariants(prev => prev.map(v =>
                        v.id === data.variant.id ? data.variant : v
                    ));
                }
            });
    };

    const setField = (field, value) => setForm(prev => ({ ...prev, [field]: value }));

    return (
        <Modal show={show} onClose={onClose} maxWidth="5xl" title="Editar Produto">
            {loading ? (
                <div className="flex justify-center py-12">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                </div>
            ) : product ? (
                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Sync Lock Warning */}
                    <div className="bg-amber-50 border border-amber-200 rounded-lg p-3">
                        <p className="text-sm text-amber-800">
                            Editar este produto irá bloqueá-lo para atualizações automáticas do CIGAM.
                        </p>
                    </div>

                    {/* Read-only fields */}
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Referência</label>
                            <input type="text" value={product.reference} disabled
                                className="mt-1 w-full border-gray-300 rounded-md bg-gray-100 text-gray-500 text-sm" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Fornecedor</label>
                            <input type="text" value={product.supplier_codigo_for || '-'} disabled
                                className="mt-1 w-full border-gray-300 rounded-md bg-gray-100 text-gray-500 text-sm" />
                        </div>
                    </div>

                    {/* Editable fields */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700">Descrição *</label>
                        <input type="text" value={form.description} onChange={e => setField('description', e.target.value)}
                            className={`mt-1 w-full border-gray-300 rounded-md text-sm ${errors.description ? 'border-red-500' : ''}`} />
                        {errors.description && <p className="mt-1 text-xs text-red-600">{errors.description[0]}</p>}
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Preço Venda</label>
                            <input type="number" step="0.01" value={form.sale_price} onChange={e => setField('sale_price', e.target.value)}
                                className="mt-1 w-full border-gray-300 rounded-md text-sm" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Preço Custo</label>
                            <input type="number" step="0.01" value={form.cost_price} onChange={e => setField('cost_price', e.target.value)}
                                className="mt-1 w-full border-gray-300 rounded-md text-sm" />
                        </div>
                    </div>

                    {/* Dropdowns */}
                    {options && (
                        <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <SelectField label="Marca" value={form.brand_cigam_code} onChange={v => setField('brand_cigam_code', v)}
                                options={options.brands} />
                            <SelectField label="Coleção" value={form.collection_cigam_code} onChange={v => setField('collection_cigam_code', v)}
                                options={options.collections} />
                            <SelectField label="Subcoleção" value={form.subcollection_cigam_code} onChange={v => setField('subcollection_cigam_code', v)}
                                options={options.subcollections} />
                            <SelectField label="Categoria" value={form.category_cigam_code} onChange={v => setField('category_cigam_code', v)}
                                options={options.categories} />
                            <SelectField label="Cor" value={form.color_cigam_code} onChange={v => setField('color_cigam_code', v)}
                                options={options.colors} />
                            <SelectField label="Material" value={form.material_cigam_code} onChange={v => setField('material_cigam_code', v)}
                                options={options.materials} />
                            <SelectField label="Complemento Artigo" value={form.article_complement_cigam_code} onChange={v => setField('article_complement_cigam_code', v)}
                                options={options.article_complements} />
                        </div>
                    )}

                    {/* Variants */}
                    <div>
                        <h4 className="text-sm font-semibold text-gray-900 mb-2">Variantes</h4>
                        <div className="border rounded-lg overflow-hidden">
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
                                            <td className="px-3 py-2 text-xs font-mono text-gray-600">
                                                {v.aux_reference || '-'}
                                            </td>
                                            <td className="px-3 py-2">
                                                <input type="checkbox" checked={v.is_active !== false} onChange={e => handleVariantUpdate(v.id, 'is_active', e.target.checked)}
                                                    className="rounded border-gray-300 text-indigo-600" />
                                            </td>
                                            <td className="px-3 py-2 space-x-1">
                                                <button type="button" onClick={() => saveVariant(v)}
                                                    className="text-xs text-indigo-600 hover:text-indigo-800">Salvar</button>
                                                <button type="button" onClick={() => generateEan(v.id)}
                                                    className="text-xs text-green-600 hover:text-green-800">EAN</button>
                                            </td>
                                        </tr>
                                    ))}
                                    {/* Add new variant row */}
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
                                        <td className="px-3 py-2"></td>
                                        <td className="px-3 py-2"></td>
                                        <td className="px-3 py-2">
                                            <button type="button" onClick={addVariant}
                                                className="text-xs text-white bg-indigo-600 px-2 py-1 rounded hover:bg-indigo-700">Adicionar</button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {/* Submit */}
                    <div className="flex justify-end gap-3 pt-4 border-t">
                        <button type="button" onClick={onClose}
                            className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                            Cancelar
                        </button>
                        <button type="submit" disabled={saving}
                            className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 disabled:opacity-50">
                            {saving ? 'Salvando...' : 'Salvar Produto'}
                        </button>
                    </div>
                </form>
            ) : null}
        </Modal>
    );
}

function SelectField({ label, value, onChange, options }) {
    return (
        <div>
            <label className="block text-xs font-medium text-gray-500 mb-1">{label}</label>
            <select value={value || ''} onChange={e => onChange(e.target.value)}
                className="w-full border-gray-300 rounded-md text-sm">
                <option value="">-</option>
                {options?.map(opt => <option key={opt.cigam_code} value={opt.cigam_code}>{opt.name}</option>)}
            </select>
        </div>
    );
}
