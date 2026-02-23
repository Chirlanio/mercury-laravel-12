import { useState, useEffect } from 'react';
import Modal from '@/Components/Modal';
import { LockClosedIcon, LockOpenIcon } from '@heroicons/react/24/outline';

export default function ProductDetailModal({ show, onClose, productId, onEdit, canEdit }) {
    const [product, setProduct] = useState(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (show && productId) {
            setLoading(true);
            fetch(`/products/${productId}`)
                .then(res => res.json())
                .then(data => {
                    setProduct(data);
                    setLoading(false);
                })
                .catch(() => setLoading(false));
        } else {
            setProduct(null);
        }
    }, [show, productId]);

    const handleUnlockSync = () => {
        fetch(`/products/${productId}/unlock-sync`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
            },
        })
            .then(res => res.json())
            .then(() => {
                setProduct(prev => prev ? { ...prev, sync_locked: false } : prev);
            });
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="4xl" title="Detalhes do Produto">
            {loading ? (
                <div className="flex justify-center py-12">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                </div>
            ) : product ? (
                <div className="space-y-6">
                    {/* Header */}
                    <div className="flex items-start justify-between">
                        <div>
                            <h3 className="text-lg font-semibold text-gray-900">{product.reference}</h3>
                            <p className="text-gray-600">{product.description}</p>
                        </div>
                        <div className="flex items-center gap-2">
                            {product.sync_locked && (
                                <span className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                    <LockClosedIcon className="h-3.5 w-3.5" />
                                    Sync Bloqueado
                                </span>
                            )}
                            <span className={`px-2.5 py-1 rounded-full text-xs font-medium ${
                                product.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
                            }`}>
                                {product.is_active ? 'Ativo' : 'Inativo'}
                            </span>
                        </div>
                    </div>

                    {/* Info Grid */}
                    <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <InfoItem label="Marca" value={product.brand?.name} />
                        <InfoItem label="Coleção" value={product.collection?.name} />
                        <InfoItem label="Subcoleção" value={product.subcollection?.name} />
                        <InfoItem label="Categoria" value={product.category?.name} />
                        <InfoItem label="Cor" value={product.color?.name} />
                        <InfoItem label="Material" value={product.material?.name} />
                        <InfoItem label="Complemento Artigo" value={product.article_complement?.name} />
                        <InfoItem label="Fornecedor" value={product.supplier?.nome_fantasia || product.supplier?.razao_social} />
                        <InfoItem label="Preço Venda" value={product.sale_price ? `R$ ${Number(product.sale_price).toFixed(2).replace('.', ',')}` : '-'} />
                        <InfoItem label="Preço Custo" value={product.cost_price ? `R$ ${Number(product.cost_price).toFixed(2).replace('.', ',')}` : '-'} />
                        <InfoItem label="Última Sync" value={product.synced_at ? new Date(product.synced_at).toLocaleString('pt-BR') : '-'} />
                        <InfoItem label="Atualizado em" value={product.updated_at ? new Date(product.updated_at).toLocaleString('pt-BR') : '-'} />
                    </div>

                    {/* Variants */}
                    {product.variants && product.variants.length > 0 && (
                        <div>
                            <h4 className="text-sm font-semibold text-gray-900 mb-2">
                                Variantes ({product.variants.length})
                            </h4>
                            <div className="border rounded-lg overflow-hidden">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tamanho</th>
                                            <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Código Barras</th>
                                            <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ref. Auxiliar</th>
                                            <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {product.variants.map((v) => (
                                            <tr key={v.id}>
                                                <td className="px-4 py-2 text-sm text-gray-900">{v.size?.name || v.size_cigam_code || '-'}</td>
                                                <td className="px-4 py-2 text-sm text-gray-600 font-mono">{v.barcode || '-'}</td>
                                                <td className="px-4 py-2 text-sm text-gray-600 font-mono">{v.aux_reference || '-'}</td>
                                                <td className="px-4 py-2">
                                                    <span className={`px-2 py-0.5 rounded-full text-xs ${
                                                        v.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'
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
                    )}

                    {/* Actions */}
                    <div className="flex justify-end gap-3 pt-4 border-t">
                        {canEdit && product.sync_locked && (
                            <button
                                onClick={handleUnlockSync}
                                className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100"
                            >
                                <LockOpenIcon className="h-4 w-4" />
                                Desbloquear Sync
                            </button>
                        )}
                        {canEdit && (
                            <button
                                onClick={() => onEdit && onEdit(product)}
                                className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700"
                            >
                                Editar
                            </button>
                        )}
                        <button
                            onClick={onClose}
                            className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                        >
                            Fechar
                        </button>
                    </div>
                </div>
            ) : (
                <p className="text-center text-gray-500 py-8">Produto não encontrado.</p>
            )}
        </Modal>
    );
}

function InfoItem({ label, value }) {
    return (
        <div>
            <dt className="text-xs font-medium text-gray-500">{label}</dt>
            <dd className="mt-0.5 text-sm text-gray-900">{value || '-'}</dd>
        </div>
    );
}
