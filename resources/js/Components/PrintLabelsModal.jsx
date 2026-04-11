import { useState, useEffect, useCallback } from 'react';
import axios from 'axios';
import StandardModal from '@/Components/StandardModal';
import Button from '@/Components/Button';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import Checkbox from '@/Components/Checkbox';
import { TagIcon, PrinterIcon, MagnifyingGlassIcon, XMarkIcon } from '@heroicons/react/24/outline';

const DEFAULT_PRESET = { width: 60, height: 30, columns: 3, gap: 2, format: 'A4' };

export default function PrintLabelsModal({ show, onClose }) {
    const [search, setSearch] = useState('');
    const [searchResults, setSearchResults] = useState([]);
    const [selectedVariants, setSelectedVariants] = useState([]);
    const [preset, setPreset] = useState(() => {
        try {
            const saved = localStorage.getItem('label_preset');
            return saved ? JSON.parse(saved) : DEFAULT_PRESET;
        } catch {
            return DEFAULT_PRESET;
        }
    });
    const [generating, setGenerating] = useState(false);
    const [searching, setSearching] = useState(false);

    useEffect(() => {
        localStorage.setItem('label_preset', JSON.stringify(preset));
    }, [preset]);

    const handleSearch = useCallback(async (query) => {
        setSearch(query);
        if (query.length < 2) { setSearchResults([]); return; }
        setSearching(true);
        try {
            const { data } = await axios.get('/products/search-variants', { params: { search: query } });
            setSearchResults(data);
        } catch { setSearchResults([]); }
        finally { setSearching(false); }
    }, []);

    const toggleVariant = (variant, product) => {
        setSelectedVariants(prev => {
            if (prev.find(v => v.id === variant.id)) return prev.filter(v => v.id !== variant.id);
            return [...prev, {
                id: variant.id, reference: product.reference, description: product.description,
                size_name: variant.size_name, barcode: variant.barcode || variant.aux_reference || '-',
            }];
        });
    };

    const selectAllVariants = (product) => {
        setSelectedVariants(prev => {
            const existingIds = new Set(prev.map(v => v.id));
            const newVariants = product.variants
                .filter(v => !existingIds.has(v.id))
                .map(v => ({
                    id: v.id, reference: product.reference, description: product.description,
                    size_name: v.size_name, barcode: v.barcode || v.aux_reference || '-',
                }));
            return [...prev, ...newVariants];
        });
    };

    const handleGenerate = async () => {
        if (selectedVariants.length === 0) return;
        setGenerating(true);
        try {
            const response = await axios.post('/products/print-labels', {
                variant_ids: selectedVariants.map(v => v.id), preset,
            }, { responseType: 'blob', timeout: 0 });
            const blob = new Blob([response.data], { type: 'application/pdf' });
            const url = URL.createObjectURL(blob);
            window.open(url, '_blank');
            setTimeout(() => URL.revokeObjectURL(url), 60000);
        } catch {
            alert('Erro ao gerar etiquetas.');
        } finally { setGenerating(false); }
    };

    const handleClose = () => {
        setSearch(''); setSearchResults([]); setSelectedVariants([]);
        onClose();
    };

    const updatePreset = (key, value) => setPreset(prev => ({ ...prev, [key]: value }));

    const footerContent = (
        <>
            <span className="text-xs text-gray-500">{selectedVariants.length} variante(s) selecionada(s)</span>
            <div className="flex-1" />
            <Button variant="outline" onClick={handleClose}>Fechar</Button>
            <Button variant="primary" onClick={handleGenerate}
                disabled={selectedVariants.length === 0}
                loading={generating}
                icon={PrinterIcon}
            >
                Gerar PDF ({selectedVariants.length})
            </Button>
        </>
    );

    return (
        <StandardModal
            show={show}
            onClose={handleClose}
            title="Imprimir Etiquetas"
            headerColor="bg-gray-700"
            headerIcon={<TagIcon className="h-5 w-5" />}
            maxWidth="4xl"
            footer={<StandardModal.Footer>{footerContent}</StandardModal.Footer>}
        >
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Esquerda: Busca e Seleção */}
                <div className="space-y-3">
                    <StandardModal.Section title="Buscar Produto" icon={<MagnifyingGlassIcon className="h-4 w-4" />}>
                        <div className="-mx-4 -mb-4 px-4 pb-4 space-y-3">
                            <TextInput
                                className="w-full"
                                value={search}
                                onChange={(e) => handleSearch(e.target.value)}
                                placeholder="Referência ou descrição..."
                            />

                            {/* Resultados da busca */}
                            <div className="border rounded-lg max-h-[250px] overflow-y-auto">
                                {searching && (
                                    <div className="p-4 text-center text-sm text-gray-400">Buscando...</div>
                                )}
                                {!searching && searchResults.length === 0 && search.length >= 2 && (
                                    <div className="p-4 text-center text-sm text-gray-400">Nenhum produto encontrado</div>
                                )}
                                {!searching && search.length < 2 && (
                                    <div className="p-4 text-center text-sm text-gray-400">Digite ao menos 2 caracteres</div>
                                )}
                                {searchResults.map(product => (
                                    <div key={product.id} className="border-b last:border-b-0">
                                        <div className="flex items-center justify-between px-3 py-2 bg-gray-50">
                                            <div className="text-xs">
                                                <span className="font-medium">{product.reference}</span>
                                                <span className="text-gray-500 ml-2">{product.description?.substring(0, 30)}</span>
                                            </div>
                                            <button onClick={() => selectAllVariants(product)}
                                                className="text-xs text-indigo-600 hover:text-indigo-800">
                                                Todas
                                            </button>
                                        </div>
                                        {product.variants?.map(variant => {
                                            const isSelected = selectedVariants.some(v => v.id === variant.id);
                                            return (
                                                <label key={variant.id}
                                                    className={`flex items-center gap-2 px-3 py-1.5 cursor-pointer hover:bg-gray-50 ${isSelected ? 'bg-indigo-50' : ''}`}>
                                                    <Checkbox checked={isSelected} onChange={() => toggleVariant(variant, product)} />
                                                    <span className="text-xs text-gray-700">
                                                        Tam: {variant.size_name} | {variant.barcode || variant.aux_reference || 'Sem código'}
                                                    </span>
                                                </label>
                                            );
                                        })}
                                    </div>
                                ))}
                            </div>
                        </div>
                    </StandardModal.Section>

                    {/* Variantes selecionadas */}
                    {selectedVariants.length > 0 && (
                        <div className="border rounded-lg max-h-[150px] overflow-y-auto">
                            {selectedVariants.map(v => (
                                <div key={v.id} className="flex items-center justify-between px-3 py-1.5 border-b last:border-b-0 text-xs">
                                    <span>{v.reference} - Tam: {v.size_name}</span>
                                    <button onClick={() => setSelectedVariants(prev => prev.filter(s => s.id !== v.id))}
                                        className="text-red-500 hover:text-red-700">
                                        <XMarkIcon className="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            ))}
                        </div>
                    )}
                </div>

                {/* Direita: Configuração */}
                <StandardModal.Section title="Configuração da Etiqueta">
                    <div className="-mx-4 -mb-4 px-4 pb-4 space-y-3">
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <InputLabel value="Largura (mm)" className="text-xs" />
                                <TextInput type="number" className="mt-1 w-full" value={preset.width} min={20} max={200}
                                    onChange={(e) => updatePreset('width', Number(e.target.value))} />
                            </div>
                            <div>
                                <InputLabel value="Altura (mm)" className="text-xs" />
                                <TextInput type="number" className="mt-1 w-full" value={preset.height} min={10} max={200}
                                    onChange={(e) => updatePreset('height', Number(e.target.value))} />
                            </div>
                            <div>
                                <InputLabel value="Colunas" className="text-xs" />
                                <TextInput type="number" className="mt-1 w-full" value={preset.columns} min={1} max={6}
                                    onChange={(e) => updatePreset('columns', Number(e.target.value))} />
                            </div>
                            <div>
                                <InputLabel value="Espaçamento (mm)" className="text-xs" />
                                <TextInput type="number" className="mt-1 w-full" value={preset.gap} min={0} max={20} step={0.5}
                                    onChange={(e) => updatePreset('gap', Number(e.target.value))} />
                            </div>
                        </div>

                        <div>
                            <InputLabel value="Formato" className="text-xs" />
                            <select value={preset.format} onChange={(e) => updatePreset('format', e.target.value)}
                                className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="A4">A4 (210 x 297mm)</option>
                                <option value="custom">Personalizado / Rolo</option>
                            </select>
                        </div>

                        {/* Preview */}
                        <div className="bg-gray-50 rounded-lg p-3 border">
                            <p className="text-xs text-gray-500 mb-2">Pré-visualização do layout</p>
                            <div className="flex gap-1 justify-center">
                                {Array.from({ length: Math.min(preset.columns, 4) }).map((_, i) => (
                                    <div key={i} className="border border-dashed border-gray-400 rounded text-center p-1"
                                        style={{ width: `${Math.min(preset.width * 0.8, 60)}px`, height: `${Math.min(preset.height * 0.8, 50)}px` }}>
                                        <div className="text-[6px] font-bold truncate">REF001</div>
                                        <div className="text-[5px] text-gray-400">Tam: P</div>
                                        <div className="bg-gray-300 h-2 mx-auto mt-0.5" style={{ width: '70%' }} />
                                        <div className="text-[4px] text-gray-400 mt-0.5">123456789</div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </StandardModal.Section>
            </div>
        </StandardModal>
    );
}
