import Modal from '@/Components/Modal';
import Button from '@/Components/Button';
import { useForm } from '@inertiajs/react';

export default function AccessLevelCreateModal({ show, onClose, colorThemes = [] }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        color_theme_id: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('access-levels.store'), {
            onSuccess: () => {
                reset();
                onClose();
            },
            onError: (errors) => {
                console.log('Validation errors:', errors);
            },
        });
    };

    const handleClose = () => {
        reset();
        onClose();
    };

    // Mapeamento de cores para classes Tailwind
    const getColorPreview = (colorClass) => {
        const colorMap = {
            'primary': 'bg-blue-500',
            'secondary': 'bg-gray-500',
            'success': 'bg-green-500',
            'danger': 'bg-red-500',
            'warning': 'bg-yellow-500',
            'info': 'bg-cyan-500',
            'light': 'bg-gray-200',
            'dark': 'bg-gray-800',
        };
        return colorMap[colorClass] || 'bg-gray-400';
    };

    return (
        <Modal show={show} onClose={handleClose} title="Cadastrar Novo Nivel de Acesso" maxWidth="md">
            <form onSubmit={submit} className="space-y-6 p-6">
                {/* Nome do Nivel de Acesso */}
                <div>
                    <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-1">
                        Nome do Nivel de Acesso *
                    </label>
                    <input
                        id="name"
                        type="text"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 ${
                            errors.name ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'
                        }`}
                        placeholder="Ex: Gerente Regional"
                        autoFocus
                    />
                    {errors.name && (
                        <p className="mt-1 text-sm text-red-600">{errors.name}</p>
                    )}
                </div>

                {/* Tema de Cor */}
                <div>
                    <label htmlFor="color_theme_id" className="block text-sm font-medium text-gray-700 mb-1">
                        Tema de Cor
                    </label>
                    <select
                        id="color_theme_id"
                        value={data.color_theme_id}
                        onChange={(e) => setData('color_theme_id', e.target.value)}
                        className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 ${
                            errors.color_theme_id ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'
                        }`}
                    >
                        <option value="">Selecione uma cor (opcional)</option>
                        {colorThemes.map((theme) => (
                            <option key={theme.id} value={theme.id}>
                                {theme.name}
                            </option>
                        ))}
                    </select>
                    {errors.color_theme_id && (
                        <p className="mt-1 text-sm text-red-600">{errors.color_theme_id}</p>
                    )}

                    {/* Preview de cores */}
                    {colorThemes.length > 0 && (
                        <div className="mt-3">
                            <p className="text-xs text-gray-500 mb-2">Ou clique para selecionar:</p>
                            <div className="flex flex-wrap gap-2">
                                {colorThemes.map((theme) => (
                                    <button
                                        key={theme.id}
                                        type="button"
                                        onClick={() => setData('color_theme_id', theme.id.toString())}
                                        className={`w-8 h-8 rounded-full border-2 transition-all ${
                                            getColorPreview(theme.color_class)
                                        } ${
                                            data.color_theme_id === theme.id.toString()
                                                ? 'border-gray-900 ring-2 ring-offset-2 ring-gray-400'
                                                : 'border-transparent hover:border-gray-400'
                                        }`}
                                        title={theme.name}
                                    />
                                ))}
                            </div>
                        </div>
                    )}
                </div>

                {/* Informacoes */}
                <div className="bg-blue-50 p-4 rounded-lg">
                    <div className="flex">
                        <svg className="h-5 w-5 text-blue-400 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div className="text-sm text-blue-700">
                            <p className="font-medium mb-1">Apos criar o nivel de acesso:</p>
                            <ul className="text-xs space-y-1">
                                <li>- Configure as permissoes de acesso as paginas</li>
                                <li>- Defina quais menus serao visiveis</li>
                                <li>- A ordem sera definida automaticamente</li>
                            </ul>
                        </div>
                    </div>
                </div>

                {/* Botoes */}
                <div className="flex justify-end space-x-3 pt-4 border-t">
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
                        disabled={processing}
                        icon={({ className }) => (
                            <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                        )}
                    >
                        {processing ? 'Criando...' : 'Criar Nivel de Acesso'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
