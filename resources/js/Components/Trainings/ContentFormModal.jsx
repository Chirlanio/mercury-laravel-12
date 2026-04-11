import { useState, useEffect } from 'react';
import { DocumentPlusIcon } from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';

const CONTENT_TYPES = [
    { value: 'video', label: 'Vídeo', hint: 'Upload de vídeo ou URL externa (YouTube, Vimeo)' },
    { value: 'audio', label: 'Áudio', hint: 'Upload de áudio (MP3, WAV, OGG)' },
    { value: 'document', label: 'Documento', hint: 'Upload de documento (PDF, PPT, DOC)' },
    { value: 'link', label: 'Link', hint: 'URL de recurso externo' },
    { value: 'text', label: 'Texto', hint: 'Conteúdo em texto/HTML' },
];

const FILE_TYPES = ['video', 'audio', 'document'];

export default function ContentFormModal({ show, onClose, onSuccess, contentId = null, categories = [] }) {
    const isEditing = !!contentId;
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});
    const [data, setData] = useState({
        title: '',
        description: '',
        content_type: 'video',
        file: null,
        external_url: '',
        text_content: '',
        duration_seconds: '',
        thumbnail: null,
        category_id: '',
    });

    useEffect(() => {
        if (show && isEditing) {
            fetch(route('training-contents.show', contentId))
                .then(res => res.json())
                .then(result => {
                    const c = result.content;
                    setData({
                        title: c.title || '',
                        description: c.description || '',
                        content_type: c.content_type || 'video',
                        file: null,
                        external_url: c.external_url || '',
                        text_content: c.text_content || '',
                        duration_seconds: c.duration_seconds || '',
                        thumbnail: null,
                        category_id: c.category?.id || '',
                    });
                });
        } else if (show && !isEditing) {
            setData({
                title: '', description: '', content_type: 'video', file: null,
                external_url: '', text_content: '', duration_seconds: '',
                thumbnail: null, category_id: '',
            });
            setErrors({});
        }
    }, [show, contentId]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        const formData = new FormData();
        formData.append('title', data.title);
        formData.append('description', data.description || '');
        formData.append('content_type', data.content_type);
        formData.append('category_id', data.category_id || '');

        if (data.file) formData.append('file', data.file);
        if (data.external_url) formData.append('external_url', data.external_url);
        if (data.text_content) formData.append('text_content', data.text_content);
        if (data.duration_seconds) formData.append('duration_seconds', data.duration_seconds);
        if (data.thumbnail) formData.append('thumbnail', data.thumbnail);

        const url = isEditing
            ? route('training-contents.update', contentId)
            : route('training-contents.store');

        if (isEditing) {
            formData.append('_method', 'PUT');
        }

        try {
            const response = await fetch(url, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                },
            });
            const result = await response.json();
            if (response.ok) {
                setProcessing(false);
                onSuccess?.();
            } else if (response.status === 422 && result.errors) {
                setProcessing(false);
                setErrors(result.errors);
            } else {
                setProcessing(false);
                setErrors({ _general: [result.error || result.message || 'Erro ao salvar.'] });
            }
        } catch {
            setProcessing(false);
            setErrors({ _general: ['Erro de conexão.'] });
        }
    };

    const setField = (field, value) => {
        setData(prev => ({ ...prev, [field]: value }));
    };

    const isFileType = FILE_TYPES.includes(data.content_type);
    const showExternalUrl = ['video', 'link'].includes(data.content_type);
    const showTextContent = data.content_type === 'text';
    const selectedType = CONTENT_TYPES.find(t => t.value === data.content_type);

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title={isEditing ? 'Editar Conteúdo' : 'Novo Conteúdo'}
            headerColor="bg-purple-600"
            headerIcon={<DocumentPlusIcon className="h-5 w-5" />}
            maxWidth="3xl"
            onSubmit={handleSubmit}
            footer={
                <StandardModal.Footer
                    onCancel={onClose}
                    onSubmit="submit"
                    submitLabel={isEditing ? 'Atualizar' : 'Criar'}
                    processing={processing}
                />
            }
        >
            {/* Tipo */}
            <StandardModal.Section title="Tipo de Conteúdo">
                <div className="grid grid-cols-5 gap-2">
                    {CONTENT_TYPES.map(type => (
                        <button
                            key={type.value}
                            type="button"
                            className={`p-3 rounded-lg border-2 text-center text-sm transition-colors ${
                                data.content_type === type.value
                                    ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                                    : 'border-gray-200 hover:border-gray-300 text-gray-600'
                            }`}
                            onClick={() => !isEditing && setField('content_type', type.value)}
                            disabled={isEditing}
                        >
                            {type.label}
                        </button>
                    ))}
                </div>
                {selectedType && (
                    <p className="text-xs text-gray-500 mt-2">{selectedType.hint}</p>
                )}
            </StandardModal.Section>

            {/* Dados Gerais */}
            <StandardModal.Section title="Dados Gerais">
                <div className="space-y-4">
                    <div>
                        <InputLabel htmlFor="title" value="Título *" />
                        <TextInput
                            id="title"
                            className="mt-1 block w-full"
                            value={data.title}
                            onChange={e => setField('title', e.target.value)}
                            required
                        />
                        <InputError message={errors.title} className="mt-1" />
                    </div>
                    <div>
                        <InputLabel htmlFor="description" value="Descrição" />
                        <textarea
                            id="description"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            rows={2}
                            value={data.description}
                            onChange={e => setField('description', e.target.value)}
                        />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel htmlFor="category_id" value="Categoria" />
                            <select
                                id="category_id"
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={data.category_id}
                                onChange={e => setField('category_id', e.target.value)}
                            >
                                <option value="">Sem categoria</option>
                                {categories.map(c => (
                                    <option key={c.id} value={c.id}>{c.name}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <InputLabel htmlFor="duration_seconds" value="Duração (segundos)" />
                            <TextInput
                                id="duration_seconds"
                                type="number"
                                className="mt-1 block w-full"
                                value={data.duration_seconds}
                                onChange={e => setField('duration_seconds', e.target.value)}
                                min={0}
                                placeholder="Opcional"
                            />
                        </div>
                    </div>
                </div>
            </StandardModal.Section>

            {/* Upload / URL / Text */}
            <StandardModal.Section title="Conteúdo">
                {isFileType && (
                    <div>
                        <InputLabel htmlFor="file" value={`Arquivo ${isEditing ? '(substituir)' : '*'}`} />
                        <input
                            id="file"
                            type="file"
                            className="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                            onChange={e => setField('file', e.target.files[0] || null)}
                        />
                        <InputError message={errors.file} className="mt-1" />
                    </div>
                )}
                {showExternalUrl && (
                    <div className={isFileType ? 'mt-4' : ''}>
                        <InputLabel htmlFor="external_url" value={data.content_type === 'link' ? 'URL *' : 'URL externa (alternativa ao upload)'} />
                        <TextInput
                            id="external_url"
                            type="url"
                            className="mt-1 block w-full"
                            value={data.external_url}
                            onChange={e => setField('external_url', e.target.value)}
                            placeholder="https://..."
                        />
                        <InputError message={errors.external_url} className="mt-1" />
                    </div>
                )}
                {showTextContent && (
                    <div>
                        <InputLabel htmlFor="text_content" value="Conteúdo *" />
                        <textarea
                            id="text_content"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            rows={8}
                            value={data.text_content}
                            onChange={e => setField('text_content', e.target.value)}
                            placeholder="Texto ou HTML..."
                        />
                        <InputError message={errors.text_content} className="mt-1" />
                    </div>
                )}
            </StandardModal.Section>

            {/* Thumbnail */}
            <StandardModal.Section title="Miniatura (opcional)">
                <input
                    type="file"
                    accept="image/*"
                    className="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-gray-50 file:text-gray-700 hover:file:bg-gray-100"
                    onChange={e => setField('thumbnail', e.target.files[0] || null)}
                />
                <InputError message={errors.thumbnail} className="mt-1" />
            </StandardModal.Section>
        </StandardModal>
    );
}
