import { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import { toast } from 'react-toastify';
import StandardModal from '@/Components/StandardModal';
import Button from '@/Components/Button';
import StatusBadge from '@/Components/Shared/StatusBadge';
import {
    LockClosedIcon, LockOpenIcon, PhotoIcon, TrashIcon, PencilSquareIcon,
} from '@heroicons/react/24/outline';

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
        } catch (err) {
            toast.error(err.response?.data?.message || 'Erro ao desbloquear sincronização.');
        }
    };

    const handleImageUpload = async (e) => {
        const file = e.target.files[0];
        if (!file) return;
        setUploading(true);
        try {
            const formData = new FormData();
            formData.append('image', file);
            const { data } = await axios.post(`/products/${productId}/upload-image`, formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            setProduct(prev => prev ? { ...prev, image: data.image, image_url: data.image_url } : prev);
        } catch (err) {
            const errors = err.response?.data?.errors;
            if (errors?.image) {
                toast.error(errors.image[0]);
            } else {
                toast.error(err.response?.data?.message || 'Erro ao enviar imagem.');
            }
        }
        setUploading(false);
        if (fileRef.current) fileRef.current.value = '';
    };

    const handleDeleteImage = async () => {
        try {
            await axios.delete(`/products/${productId}/delete-image`);
            setProduct(prev => prev ? { ...prev, image: null, image_url: null } : prev);
        } catch (err) {
            toast.error(err.response?.data?.message || 'Erro ao remover imagem.');
        }
    };

    const fmtBRL = (val) => val ? `R$ ${Number(val).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}` : '-';
    const fmtDate = (val) => val ? new Date(val).toLocaleString('pt-BR') : '-';

    const headerBadges = [];
    if (product) {
        headerBadges.push({
            text: product.is_active ? 'Ativo' : 'Inativo',
            className: product.is_active ? 'bg-emerald-500/20 text-white' : 'bg-red-500/20 text-white',
        });
        if (product.sync_locked) {
            headerBadges.push({ text: 'Bloqueado', className: 'bg-amber-500/20 text-white' });
        }
    }

    const footerContent = product && (
        <>
            {canEdit && product.sync_locked && (
                <Button variant="warning" size="sm" icon={LockOpenIcon} onClick={handleUnlockSync}>
                    Desbloquear Sync
                </Button>
            )}
            {canEdit && (
                <Button variant="primary" size="sm" icon={PencilSquareIcon} onClick={() => onEdit?.(product)}>
                    Editar
                </Button>
            )}
            <div className="flex-1" />
            <Button variant="outline" size="sm" onClick={onClose}>Fechar</Button>
        </>
    );

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title={product?.reference || 'Detalhes do Produto'}
            subtitle={product?.description}
            headerColor="bg-indigo-600"
            headerBadges={headerBadges}
            loading={loading}
            errorMessage={!loading && !product && show ? 'Produto não encontrado.' : null}
            maxWidth="5xl"
            footer={footerContent && <StandardModal.Footer>{footerContent}</StandardModal.Footer>}
        >
            {product && (
                <>
                    {/* Imagem + Preços */}
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
                                    <button onClick={() => canEdit && fileRef.current?.click()}
                                        className={`flex flex-col items-center gap-1 ${canEdit ? 'cursor-pointer hover:text-indigo-600' : ''}`}
                                        disabled={!canEdit}>
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

                        {/* Preços */}
                        <div className="flex-1 min-w-0">
                            <div className="grid grid-cols-3 gap-2 sm:gap-3">
                                <StandardModal.InfoCard label="Preço de Venda" value={fmtBRL(product.sale_price)} highlight />
                                <StandardModal.InfoCard label="Preço de Custo" value={fmtBRL(product.cost_price)} />
                                <StandardModal.InfoCard label="Markup" value={product.markup !== null ? `${product.markup}%` : '-'} />
                            </div>
                        </div>
                    </div>

                    {/* Classificação + Características */}
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <StandardModal.Section title="Classificação">
                            <div className="grid grid-cols-2 gap-3">
                                <StandardModal.Field label="Marca" value={product.brand?.name} />
                                <StandardModal.Field label="Estação" value={product.collection?.name} />
                                <StandardModal.Field label="Coleção" value={product.subcollection?.name} />
                                <StandardModal.Field label="Tipo" value={product.category?.name} />
                                <StandardModal.Field label="Grupo" value={product.article_complement?.name} />
                            </div>
                        </StandardModal.Section>

                        <StandardModal.Section title="Características">
                            <div className="grid grid-cols-2 gap-3">
                                <StandardModal.Field label="Cor" value={product.color?.name} />
                                <StandardModal.Field label="Material" value={product.material?.name} />
                                <StandardModal.Field
                                    label="Fornecedor"
                                    value={product.supplier
                                        ? `${product.supplier.nome_fantasia || product.supplier.razao_social} (${product.supplier_codigo_for})`
                                        : null}
                                />
                            </div>
                        </StandardModal.Section>
                    </div>

                    {/* Informações do Sistema */}
                    <StandardModal.Section title="Informações do Sistema">
                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <StandardModal.MiniField label="Última Sincronização" value={fmtDate(product.synced_at)} />
                            <StandardModal.MiniField label="Criado em" value={fmtDate(product.created_at)} />
                            <StandardModal.MiniField label="Atualizado em" value={fmtDate(product.updated_at)} />
                            <StandardModal.MiniField label="Atualizado por" value={product.updated_by?.name || '-'} />
                        </div>
                    </StandardModal.Section>

                    {/* Variantes */}
                    {product.variants?.length > 0 && (
                        <StandardModal.Section title={`Variantes (${product.variants.length})`}>
                            <div className="overflow-x-auto -mx-4 -mb-4">
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
                                                <td className="px-4 py-2 text-sm font-medium text-gray-900">{v.size?.name || v.size_cigam_code || '-'}</td>
                                                <td className="px-4 py-2 text-sm text-gray-600 font-mono">{v.barcode || '-'}</td>
                                                <td className="px-4 py-2 text-sm text-gray-600 font-mono">{v.aux_reference || '-'}</td>
                                                <td className="px-4 py-2 text-center">
                                                    <StatusBadge variant={v.is_active ? 'emerald' : 'danger'}>
                                                        {v.is_active ? 'Ativo' : 'Inativo'}
                                                    </StatusBadge>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </StandardModal.Section>
                    )}
                </>
            )}
        </StandardModal>
    );
}
