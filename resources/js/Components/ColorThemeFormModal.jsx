import Modal from '@/Components/Modal';
import Button from '@/Components/Button';
import { useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function ColorThemeFormModal({ show, onClose, colorTheme = null, colorPalette = {} }) {
    const isEditing = colorTheme !== null;
    const [activeTab, setActiveTab] = useState('palette');

    const { data, setData, post, put, processing, errors, reset } = useForm({
        name: '',
        color_class: '',
        hex_color: '#3B82F6',
    });

    useEffect(() => {
        if (colorTheme) {
            setData({
                name: colorTheme.name || '',
                color_class: colorTheme.color_class || '',
                hex_color: colorTheme.hex_color || '#3B82F6',
            });
        } else {
            reset();
            setData('hex_color', '#3B82F6');
        }
    }, [colorTheme]);

    const submit = (e) => {
        e.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
            onError: (errors) => {
                console.log('Validation errors:', errors);
            },
        };

        if (isEditing) {
            put(route('color-themes.update', colorTheme.id), options);
        } else {
            post(route('color-themes.store'), options);
        }
    };

    const handleClose = () => {
        reset();
        setActiveTab('palette');
        onClose();
    };

    const selectPaletteColor = (color) => {
        setData({
            ...data,
            name: data.name || color.name,
            color_class: color.class,
            hex_color: color.hex,
        });
    };

    const handleHexChange = (e) => {
        let value = e.target.value;
        // Adicionar # se nao tiver
        if (value && !value.startsWith('#')) {
            value = '#' + value;
        }
        setData('hex_color', value.toUpperCase());
    };

    const isLightColor = (hex) => {
        if (!hex) return false;
        const color = hex.replace('#', '');
        const r = parseInt(color.substr(0, 2), 16);
        const g = parseInt(color.substr(2, 2), 16);
        const b = parseInt(color.substr(4, 2), 16);
        const brightness = (r * 299 + g * 587 + b * 114) / 1000;
        return brightness > 155;
    };

    const getTextColor = (hex) => {
        return isLightColor(hex) ? 'text-gray-800' : 'text-white';
    };

    return (
        <Modal
            show={show}
            onClose={handleClose}
            title={isEditing ? 'Editar Tema de Cor' : 'Cadastrar Novo Tema de Cor'}
            maxWidth="3xl"
        >
            <form onSubmit={submit} className="p-6">
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Coluna Esquerda - Formulario */}
                    <div className="space-y-5">
                        {/* Nome do Tema */}
                        <div>
                            <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-1">
                                Nome do Tema *
                            </label>
                            <input
                                id="name"
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 ${
                                    errors.name ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'
                                }`}
                                placeholder="Ex: Azul Corporativo"
                                autoFocus
                            />
                            {errors.name && (
                                <p className="mt-1 text-sm text-red-600">{errors.name}</p>
                            )}
                        </div>

                        {/* Tabs para selecao de cor */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Selecione a Cor *
                            </label>
                            <div className="flex border-b border-gray-200">
                                <button
                                    type="button"
                                    onClick={() => setActiveTab('palette')}
                                    className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
                                        activeTab === 'palette'
                                            ? 'border-indigo-500 text-indigo-600'
                                            : 'border-transparent text-gray-500 hover:text-gray-700'
                                    }`}
                                >
                                    Paleta de Cores
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setActiveTab('custom')}
                                    className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
                                        activeTab === 'custom'
                                            ? 'border-indigo-500 text-indigo-600'
                                            : 'border-transparent text-gray-500 hover:text-gray-700'
                                    }`}
                                >
                                    Cor Personalizada
                                </button>
                            </div>

                            {/* Tab Content */}
                            <div className="mt-4">
                                {activeTab === 'palette' ? (
                                    <div className="space-y-4 max-h-80 overflow-y-auto pr-2">
                                        {Object.entries(colorPalette).map(([groupKey, group]) => (
                                            <div key={groupKey}>
                                                <h4 className="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
                                                    {group.label}
                                                </h4>
                                                <div className="grid grid-cols-4 gap-2">
                                                    {group.colors.map((color) => (
                                                        <button
                                                            key={color.class}
                                                            type="button"
                                                            onClick={() => selectPaletteColor(color)}
                                                            className={`group relative h-10 rounded-lg transition-all ${
                                                                data.hex_color === color.hex
                                                                    ? 'ring-2 ring-offset-2 ring-indigo-500 scale-105'
                                                                    : 'hover:scale-105'
                                                            }`}
                                                            style={{ backgroundColor: color.hex }}
                                                            title={`${color.name} (${color.hex})`}
                                                        >
                                                            {data.hex_color === color.hex && (
                                                                <svg
                                                                    className={`absolute inset-0 m-auto h-5 w-5 ${getTextColor(color.hex)}`}
                                                                    fill="none"
                                                                    stroke="currentColor"
                                                                    viewBox="0 0 24 24"
                                                                >
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M5 13l4 4L19 7" />
                                                                </svg>
                                                            )}
                                                            <span className={`absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 text-xs font-medium ${getTextColor(color.hex)}`}>
                                                                {color.name.length > 10 ? color.name.substring(0, 8) + '...' : color.name}
                                                            </span>
                                                        </button>
                                                    ))}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <div className="space-y-4">
                                        {/* Color Picker */}
                                        <div className="flex items-center gap-4">
                                            <div className="relative">
                                                <input
                                                    type="color"
                                                    value={data.hex_color || '#3B82F6'}
                                                    onChange={(e) => setData('hex_color', e.target.value.toUpperCase())}
                                                    className="w-20 h-20 rounded-lg cursor-pointer border-2 border-gray-200"
                                                />
                                            </div>
                                            <div className="flex-1">
                                                <label htmlFor="hex_color" className="block text-sm font-medium text-gray-700 mb-1">
                                                    Codigo Hexadecimal
                                                </label>
                                                <div className="flex">
                                                    <span className="inline-flex items-center px-3 rounded-l-md border border-r-0 border-gray-300 bg-gray-50 text-gray-500 text-sm">
                                                        #
                                                    </span>
                                                    <input
                                                        id="hex_color"
                                                        type="text"
                                                        value={(data.hex_color || '').replace('#', '')}
                                                        onChange={(e) => {
                                                            const value = e.target.value.replace(/[^0-9A-Fa-f]/g, '').substring(0, 6);
                                                            setData('hex_color', '#' + value.toUpperCase());
                                                        }}
                                                        className={`flex-1 px-3 py-2 border rounded-r-md focus:outline-none focus:ring-2 focus:ring-indigo-500 uppercase font-mono ${
                                                            errors.hex_color ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'
                                                        }`}
                                                        placeholder="3B82F6"
                                                        maxLength={6}
                                                    />
                                                </div>
                                                {errors.hex_color && (
                                                    <p className="mt-1 text-sm text-red-600">{errors.hex_color}</p>
                                                )}
                                            </div>
                                        </div>

                                        {/* Cores recentes/sugeridas */}
                                        <div>
                                            <p className="text-xs text-gray-500 mb-2">Cores populares:</p>
                                            <div className="flex flex-wrap gap-2">
                                                {['#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7', '#DDA0DD', '#98D8C8', '#F7DC6F'].map((hex) => (
                                                    <button
                                                        key={hex}
                                                        type="button"
                                                        onClick={() => setData('hex_color', hex)}
                                                        className={`w-8 h-8 rounded-full transition-all ${
                                                            data.hex_color === hex
                                                                ? 'ring-2 ring-offset-2 ring-indigo-500 scale-110'
                                                                : 'hover:scale-110'
                                                        }`}
                                                        style={{ backgroundColor: hex }}
                                                        title={hex}
                                                    />
                                                ))}
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Classe CSS (opcional) */}
                        <div>
                            <label htmlFor="color_class" className="block text-sm font-medium text-gray-700 mb-1">
                                Classe CSS <span className="text-gray-400 font-normal">(opcional)</span>
                            </label>
                            <input
                                id="color_class"
                                type="text"
                                value={data.color_class}
                                onChange={(e) => setData('color_class', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 ${
                                    errors.color_class ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'
                                }`}
                                placeholder="Ex: primary, custom-blue"
                            />
                            <p className="mt-1 text-xs text-gray-500">
                                Se nao informado, sera gerado automaticamente a partir do nome.
                            </p>
                        </div>
                    </div>

                    {/* Coluna Direita - Preview */}
                    <div className="lg:border-l lg:pl-6">
                        <h3 className="text-sm font-medium text-gray-700 mb-4">Preview</h3>

                        {/* Preview Grande */}
                        <div
                            className="w-full h-32 rounded-xl shadow-lg flex items-center justify-center mb-6 transition-all"
                            style={{ backgroundColor: data.hex_color || '#3B82F6' }}
                        >
                            <span className={`text-2xl font-bold ${getTextColor(data.hex_color)}`}>
                                {data.name || 'Nome da Cor'}
                            </span>
                        </div>

                        {/* Informacoes da Cor */}
                        <div className="bg-gray-50 rounded-lg p-4 space-y-3">
                            <div className="flex justify-between items-center">
                                <span className="text-sm text-gray-500">Nome:</span>
                                <span className="text-sm font-medium text-gray-900">{data.name || '-'}</span>
                            </div>
                            <div className="flex justify-between items-center">
                                <span className="text-sm text-gray-500">Codigo Hex:</span>
                                <code className="px-2 py-1 bg-white rounded text-sm font-mono border">
                                    {data.hex_color || '-'}
                                </code>
                            </div>
                            <div className="flex justify-between items-center">
                                <span className="text-sm text-gray-500">Classe CSS:</span>
                                <code className="px-2 py-1 bg-white rounded text-sm font-mono border">
                                    {data.color_class || (data.name ? data.name.toLowerCase().replace(/\s+/g, '-') : '-')}
                                </code>
                            </div>
                        </div>

                        {/* Exemplos de Uso */}
                        <div className="mt-6">
                            <h4 className="text-sm font-medium text-gray-700 mb-3">Exemplos de Uso</h4>
                            <div className="space-y-2">
                                {/* Badge */}
                                <div className="flex items-center gap-3">
                                    <span className="text-xs text-gray-500 w-16">Badge:</span>
                                    <span
                                        className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getTextColor(data.hex_color)}`}
                                        style={{ backgroundColor: data.hex_color }}
                                    >
                                        {data.name || 'Label'}
                                    </span>
                                </div>

                                {/* Botao */}
                                <div className="flex items-center gap-3">
                                    <span className="text-xs text-gray-500 w-16">Botao:</span>
                                    <button
                                        type="button"
                                        className={`px-3 py-1.5 rounded-md text-xs font-medium ${getTextColor(data.hex_color)}`}
                                        style={{ backgroundColor: data.hex_color }}
                                    >
                                        Botao
                                    </button>
                                </div>

                                {/* Borda */}
                                <div className="flex items-center gap-3">
                                    <span className="text-xs text-gray-500 w-16">Borda:</span>
                                    <div
                                        className="px-3 py-1.5 rounded-md text-xs border-2 bg-white"
                                        style={{ borderColor: data.hex_color, color: data.hex_color }}
                                    >
                                        Com Borda
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Botoes */}
                <div className="flex justify-end space-x-3 pt-6 mt-6 border-t">
                    <Button
                        type="button"
                        variant="secondary"
                        onClick={handleClose}
                        disabled={processing}
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="submit"
                        variant="primary"
                        disabled={processing || !data.name || !data.hex_color}
                        icon={({ className }) => (
                            <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                {isEditing ? (
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                                ) : (
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                )}
                            </svg>
                        )}
                    >
                        {processing ? (isEditing ? 'Salvando...' : 'Criando...') : (isEditing ? 'Salvar Alteracoes' : 'Criar Tema')}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
