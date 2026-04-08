import { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import Modal from '@/Components/Modal';
import { LockClosedIcon, LockOpenIcon, PhotoIcon, TrashIcon } from '@heroicons/react/24/outline';

export default function ProductDetailModal({ show, onClose, productId, onEdit, canEdit }) {
    const [product, setProduct] = useState(null);
    const [loading, setLoading] = useState(false);
    const [uploading, setUploading] = useState(false);
    const fileRef = useRef(null);

    useEffect(() => {
        if (show && productId) {
            setLoading(true);
            axios.get(`/products/${productId}`)
                .then(({ data }) => setProduct(data))
                .catch(() => setProduct(null))
                .finally(() => setLoading(false));
        } else {
            setProduct(null);
        }
    }, [show, productId]);

    const handleUnlockSync = async () => {
        try {
            await axios.post(`/products/${productId}/unlock-sync`);
            setProduct(prev => prev ? { ...prev, sync_locked: false } : prev);
        } catch {}
    };

    const handleImageUpload = async (e) => {
        const file = e.target.files[0];
        if (!file) return;

        setUploading(true);
        const formData = new FormData();
        formData.append('image', file);

        try {
            const { data } = await axios.post(`/products/${productId}/upload-image`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            setProduct(prev => prev ? { ...prev, image: data.image, image_url: data.image_url } : prev);
        } catch {}
        setUploading(false);
        if (fileRef.current) fileRef.current.value = '';
    };

    const handleDeleteImage = async () => {
        try {
            await axios.delete(`/products/${productId}/delete-image`);
            setProduct(prev => prev ? { ...prev, image: null, image_url: null } : prev);
        } catch {}
    };

    const fmtBRL = (val) => val ? `R$ ${Number(val).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}` : '-';
    const fmtDate = (val) => val ? new Date(val).toLocaleString('pt-BR') : '-';

    return (
        <Modal show={show} onClose={onClose} maxWidth="5xl">
            <div className="p-4 sm:p-6">
                {loading ? (
                    <div className="flex justify-center py-16">
                        <div className="animate-spin rounded-full h-8 w-8 border-2 border-indigo-600 border-t-transparent" />
                    </div>
                ) : product ? (
                    <div className="space-y-4 sm:space-y-6">
                        {/* Header */}
                        <div className="flex flex-col gap-3 sm:flex-row sm:gap-5">
                            {/* Image */}
                            <div className="flex-shrink-0 self-center sm:self-start">
                                <div className="relative w-24 h-24 rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 flex items-center justify-center overflow-hidden group sm:w-32 sm:h-32">
                                    {product.image_url ? (
                                        <>
                                            <img src={product.image_url} alt={product.reference}
                                                className="w-full h-full object-cover rounded-lg" />
                                            {canEdit && (
                                                <div className="absolute inset-0 bg-black bg-opacity-40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                                                    <button onClick={() => fileRef.current?.click()}
                                                        className="p-1.5 bg-white rounded-full text-gray-700 hover:bg-gray-100">
                                                        <PhotoIcon className="h-4 w-4" />
                                                    </button>
                                                    <button onClick={handleDeleteImage}
                                                        className="p-1.5 bg-white rounded-full text-red-600 hover:bg-red-50">
                                                        <TrashIcon className="h-4 w-4" />
                                                    </button>
                                                </div>
                                            )}
                                        </>
                                    ) : (
                                        <button
                                            onClick={() => canEdit && fileRef.current?.click()}
                                            className={`flex flex-col items-center gap-1 ${canEdit ? 'cursor-pointer hover:text-indigo-600' : ''}`}
                                            disabled={!canEdit}
                                        >
                                            <PhotoIcon className="h-8 w-8 text-gray-300" />
                                            {canEdit && <span className="text-xs text-gray-400">Adicionar</span>}
                                        </button>
                                    )}
                                    {uploading && (
                                        <div className="absolute inset-0 bg-white bg-opacity-80 flex items-center justify-center">
                                            <div className="animate-spin rounded-full h-6 w-6 border-2 border-indigo-600 border-t-transparent" />
                                        </div>
                                    )}
                                </div>
                                <input ref={fileRef} type="file" accept="image/jpeg,image/png,image/webp"
                                    onChange={handleImageUpload} className="hidden" />
                            </div>

                            {/* Title */}
                            <div className="flex-1 min-w-0">
                                <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <h2 className="text-lg font-bold text-gray-900 sm:text-xl">{product.reference}</h2>
                                        <p className="text-xs text-gray-600 mt-0.5 sm:text-sm">{product.description}</p>
                                    </div>
                                    <div className="flex items-center gap-2 flex-shrink-0">
                                        {product.sync_locked && (
                                            <span className="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                                <LockClosedIcon className="h-3 w-3" />
                                                Bloqueado
                                            </span>
                                        )}
                                        <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                                            product.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'
                                        }`}>
                                            {product.is_active ? 'Ativo' : 'Inativo'}
                                        </span>
                                    </div>
                                </div>

                                {/* Price cards */}
                                <div className="grid grid-cols-3 gap-2 mt-3 sm:gap-3 sm:mt-4">
                                    <PriceCard label="Preço de Venda" value={fmtBRL(product.sale_price)} color="text-emerald-700" />
                                    <PriceCard label="Preço de Custo" value={fmtBRL(product.cost_price)} color="text-gray-700" />
                                    <PriceCard label="Markup" value={product.markup !== null ? `${product.markup}%` : '-'} color="text-indigo-700" />
                                </div>
                            </div>
                        </div>

                        {/* Info sections */}
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-6">
                            {/* Classificação */}
                            <Section title="Classificação">
                                <InfoRow label="Marca" value={product.brand?.name} />
                                <InfoRow label="Estação" value={product.collection?.name} />
                                <InfoRow label="Coleção" value={product.subcollection?.name} />
                                <InfoRow label="Tipo" value={product.category?.name} />
                                <InfoRow label="Grupo" value={product.article_complement?.name} />
                            </Section>

                            {/* Características */}
                            <Section title="Características">
                                <InfoRow label="Cor" value={product.color?.name} />
                                <InfoRow label="Material" value={product.material?.name} />
                                <InfoRow label="Fornecedor"
                                    value={product.supplier
                                        ? `${product.supplier.nome_fantasia || product.supplier.razao_social} (${product.supplier_codigo_for})`
                                        : null
                                    }
                                />
                            </Section>
                        </div>

                        {/* Datas */}
                        <div>
                            <h3 className="text-sm font-semibold text-gray-900 mb-2">Informações do Sistema</h3>
                            <div className="grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
                                <InfoCard label="Última Sincronização" value={fmtDate(product.synced_at)} />
                                <InfoCard label="Criado em" value={fmtDate(product.created_at)} />
                                <InfoCard label="Atualizado em" value={fmtDate(product.updated_at)} />
                                <InfoCard label="Atualizado por" value={product.updated_by?.name || '-'} />
                            </div>
                        </div>

                        {/* Variantes */}
                        {product.variants?.length > 0 && (
                            <div>
                                <h3 className="text-sm font-semibold text-gray-900 mb-2">
                                    Variantes ({product.variants.length})
                                </h3>
                                <div className="border rounded-lg overflow-hidden">
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full divide-y divide-gray-200">
                                            <thead className="bg-gray-50">
                                                <tr>
                                                    <th className="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Tamanho</th>
                                                    <th className="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Código de Barras</th>
                                                    <th className="px-4 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">EAN-13</th>
                                                    <th className="px-4 py-2.5 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody className="bg-white divide-y divide-gray-200">
                                                {product.variants.map(v => (
                                                    <tr key={v.id} className="hover:bg-gray-50">
                                                        <td className="px-4 py-2 text-sm font-medium text-gray-900">
                                                            {v.size?.name || v.size_cigam_code || '-'}
                                                        </td>
                                                        <td className="px-4 py-2 text-sm text-gray-600 font-mono">
                                                            {v.barcode || '-'}
                                                        </td>
                                                        <td className="px-4 py-2 text-sm text-gray-600 font-mono">
                                                            {v.aux_reference || '-'}
                                                        </td>
                                                        <td className="px-4 py-2 text-center">
                                                            <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${
                                                                v.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'
                                                            }`}>
                                                                {v.is_active ? 'Ativo' : 'Inativo'}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Actions */}
                        <div className="flex flex-wrap justify-end gap-2 pt-4 border-t">
                            {canEdit && product.sync_locked && (
                                <button onClick={handleUnlockSync}
                                    className="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100">
                                    <LockOpenIcon className="h-4 w-4" />
                                    Desbloquear Sync
                                </button>
                            )}
                            {canEdit && (
                                <button onClick={() => onEdit?.(product)}
                                    className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">
                                    Editar
                                </button>
                            )}
                            <button onClick={onClose}
                                className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                Fechar
                            </button>
                        </div>
                    </div>
                ) : (
                    <p className="text-center text-gray-500 py-12">Produto não encontrado.</p>
                )}
            </div>
        </Modal>
    );
}

function PriceCard({ label, value, color }) {
    return (
        <div className="bg-gray-50 rounded-lg p-2 sm:p-2.5">
            <p className="text-[10px] text-gray-500 sm:text-xs">{label}</p>
            <p className={`text-sm font-bold sm:text-base ${color}`}>{value}</p>
        </div>
    );
}

function Section({ title, children }) {
    return (
        <div>
            <h3 className="text-xs font-semibold text-gray-900 mb-1.5 sm:text-sm sm:mb-2">{title}</h3>
            <div className="bg-gray-50 rounded-lg p-2.5 space-y-1 sm:p-3 sm:space-y-1.5">
                {children}
            </div>
        </div>
    );
}

function InfoCard({ label, value }) {
    return (
        <div className="bg-gray-50 rounded-lg p-2 sm:p-2.5">
            <p className="text-[10px] text-gray-500 sm:text-xs">{label}</p>
            <p className="text-xs font-medium text-gray-900 mt-0.5 sm:text-sm">{value}</p>
        </div>
    );
}

function InfoRow({ label, value }) {
    return (
        <div className="flex justify-between text-xs sm:text-sm">
            <span className="text-gray-500">{label}</span>
            <span className="text-gray-900 font-medium text-right">{value || '-'}</span>
        </div>
    );
}
