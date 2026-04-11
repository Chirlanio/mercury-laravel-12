import { useState, useEffect } from 'react';
import {
    BookOpenIcon,
    PlusIcon,
    TrashIcon,
    ChevronUpIcon,
    ChevronDownIcon,
    MagnifyingGlassIcon,
    VideoCameraIcon,
    MusicalNoteIcon,
    DocumentTextIcon,
    LinkIcon,
    DocumentIcon,
} from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import Button from '@/Components/Button';
import Checkbox from '@/Components/Checkbox';
import StatusBadge from '@/Components/Shared/StatusBadge';
import LoadingSpinner from '@/Components/Shared/LoadingSpinner';

const TYPE_ICONS = {
    video: VideoCameraIcon,
    audio: MusicalNoteIcon,
    document: DocumentTextIcon,
    link: LinkIcon,
    text: DocumentIcon,
};

const TYPE_COLORS = {
    video: 'purple', audio: 'info', document: 'danger', link: 'success', text: 'gray',
};

const TYPE_LABELS = {
    video: 'Video', audio: 'Audio', document: 'Documento', link: 'Link', text: 'Texto',
};

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

export default function CourseContentsModal({ show, onClose, onSuccess, courseId }) {
    const [loading, setLoading] = useState(true);
    const [processing, setProcessing] = useState(false);
    const [courseTitle, setCourseTitle] = useState('');

    // Current course contents (ordered)
    const [courseContents, setCourseContents] = useState([]);

    // Available contents from library
    const [availableContents, setAvailableContents] = useState([]);
    const [availableLoading, setAvailableLoading] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [typeFilter, setTypeFilter] = useState('');

    // Load course details + available contents on open
    useEffect(() => {
        if (show && courseId) {
            setLoading(true);
            setSearchQuery('');
            setTypeFilter('');

            // Load course with its contents
            fetch(route('training-courses.show', courseId), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(res => res.json())
                .then(data => {
                    setCourseTitle(data.course.title);
                    setCourseContents(
                        (data.course.contents || []).map((c, i) => ({
                            content_id: c.id,
                            title: c.title,
                            content_type: c.content_type,
                            type_label: c.type_label,
                            duration_formatted: c.duration_formatted,
                            sort_order: c.sort_order ?? i,
                            is_required: c.is_required ?? true,
                        }))
                    );
                    setLoading(false);
                })
                .catch(() => setLoading(false));

            // Load available contents
            loadAvailableContents();
        }
    }, [show, courseId]);

    const loadAvailableContents = (search = '', type = '') => {
        setAvailableLoading(true);
        const params = new URLSearchParams();
        if (search) params.set('search', search);
        if (type) params.set('content_type', type);
        params.set('per_page', '50');

        const url = route('training-contents.index') + (params.toString() ? '?' + params.toString() : '');

        fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(res => res.json())
            .then(data => {
                setAvailableContents(data.contents?.data || []);
                setAvailableLoading(false);
            })
            .catch(() => setAvailableLoading(false));
    };

    // Search with debounce
    useEffect(() => {
        if (!show) return;
        const timer = setTimeout(() => {
            loadAvailableContents(searchQuery, typeFilter);
        }, 300);
        return () => clearTimeout(timer);
    }, [searchQuery, typeFilter, show]);

    const addContent = (content) => {
        // Don't add duplicates
        if (courseContents.some(c => c.content_id === content.id)) return;

        setCourseContents(prev => [
            ...prev,
            {
                content_id: content.id,
                title: content.title,
                content_type: content.content_type,
                type_label: content.type_label || TYPE_LABELS[content.content_type] || content.content_type,
                duration_formatted: content.duration_formatted,
                sort_order: prev.length,
                is_required: true,
            },
        ]);
    };

    const removeContent = (contentId) => {
        setCourseContents(prev =>
            prev.filter(c => c.content_id !== contentId)
                .map((c, i) => ({ ...c, sort_order: i }))
        );
    };

    const moveContent = (index, direction) => {
        const newIndex = index + direction;
        if (newIndex < 0 || newIndex >= courseContents.length) return;

        setCourseContents(prev => {
            const items = [...prev];
            [items[index], items[newIndex]] = [items[newIndex], items[index]];
            return items.map((c, i) => ({ ...c, sort_order: i }));
        });
    };

    const toggleRequired = (index) => {
        setCourseContents(prev => {
            const items = [...prev];
            items[index] = { ...items[index], is_required: !items[index].is_required };
            return items;
        });
    };

    const handleSave = async () => {
        setProcessing(true);

        const payload = {
            contents: courseContents.map((c, i) => ({
                content_id: c.content_id,
                sort_order: i,
                is_required: c.is_required,
            })),
        };

        try {
            const response = await fetch(route('training-courses.contents', courseId), {
                method: 'POST',
                body: JSON.stringify(payload),
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            });

            if (response.ok) {
                setProcessing(false);
                onSuccess?.();
            } else {
                setProcessing(false);
                const result = await response.json().catch(() => ({}));
                alert(result.error || result.message || 'Erro ao salvar conteúdos.');
            }
        } catch {
            setProcessing(false);
            alert('Erro de conexão.');
        }
    };

    // Contents already in the course (by id)
    const courseContentIds = new Set(courseContents.map(c => c.content_id));

    // Filter available contents that aren't in the course yet
    const filteredAvailable = availableContents.filter(c => !courseContentIds.has(c.id));

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title={`Conteúdos do Curso`}
            subtitle={courseTitle}
            headerColor="bg-green-600"
            headerIcon={<BookOpenIcon className="h-5 w-5" />}
            maxWidth="5xl"
            loading={loading}
            footer={
                <StandardModal.Footer
                    onCancel={onClose}
                    onSubmit={handleSave}
                    submitLabel="Salvar Conteúdos"
                    processing={processing}
                />
            }
        >
            {/* Course Contents - Ordered List */}
            <StandardModal.Section title={`Conteúdos do Curso (${courseContents.length})`}>
                {courseContents.length > 0 ? (
                    <div className="space-y-2 max-h-64 overflow-y-auto">
                        {courseContents.map((content, index) => {
                            const Icon = TYPE_ICONS[content.content_type] || DocumentIcon;
                            return (
                                <div key={content.content_id} className="flex items-center gap-3 p-2 bg-gray-50 rounded-lg border border-gray-200">
                                    {/* Order controls */}
                                    <div className="flex flex-col">
                                        <button
                                            type="button"
                                            onClick={() => moveContent(index, -1)}
                                            disabled={index === 0}
                                            className="text-gray-400 hover:text-gray-600 disabled:opacity-30"
                                        >
                                            <ChevronUpIcon className="w-4 h-4" />
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => moveContent(index, 1)}
                                            disabled={index === courseContents.length - 1}
                                            className="text-gray-400 hover:text-gray-600 disabled:opacity-30"
                                        >
                                            <ChevronDownIcon className="w-4 h-4" />
                                        </button>
                                    </div>

                                    {/* Order number */}
                                    <span className="text-xs font-bold text-gray-400 w-6 text-center">{index + 1}</span>

                                    {/* Content info */}
                                    <Icon className="w-5 h-5 text-gray-400 flex-shrink-0" />
                                    <div className="flex-1 min-w-0">
                                        <span className="text-sm font-medium text-gray-900 truncate block">{content.title}</span>
                                        <div className="flex items-center gap-2 mt-0.5">
                                            <StatusBadge variant={TYPE_COLORS[content.content_type] || 'gray'}>
                                                {content.type_label || content.content_type}
                                            </StatusBadge>
                                            {content.duration_formatted && (
                                                <span className="text-xs text-gray-500">{content.duration_formatted}</span>
                                            )}
                                        </div>
                                    </div>

                                    {/* Required toggle */}
                                    <label className="flex items-center gap-1.5 cursor-pointer flex-shrink-0">
                                        <Checkbox
                                            checked={content.is_required}
                                            onChange={() => toggleRequired(index)}
                                        />
                                        <span className="text-xs text-gray-600">Obrigatório</span>
                                    </label>

                                    {/* Remove button */}
                                    <button
                                        type="button"
                                        onClick={() => removeContent(content.content_id)}
                                        className="text-red-400 hover:text-red-600 flex-shrink-0 p-1"
                                    >
                                        <TrashIcon className="w-4 h-4" />
                                    </button>
                                </div>
                            );
                        })}
                    </div>
                ) : (
                    <p className="text-sm text-gray-500 text-center py-4">
                        Nenhum conteúdo adicionado. Use a busca abaixo para adicionar conteúdos da biblioteca.
                    </p>
                )}
            </StandardModal.Section>

            {/* Available Contents - Library */}
            <StandardModal.Section title="Biblioteca de Conteúdos">
                {/* Search & Filter */}
                <div className="flex items-center gap-3 mb-3">
                    <div className="flex-1 relative">
                        <MagnifyingGlassIcon className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                        <input
                            type="text"
                            placeholder="Buscar conteúdos..."
                            className="w-full pl-9 pr-3 py-2 rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={searchQuery}
                            onChange={e => setSearchQuery(e.target.value)}
                        />
                    </div>
                    <select
                        className="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        value={typeFilter}
                        onChange={e => setTypeFilter(e.target.value)}
                    >
                        <option value="">Todos os tipos</option>
                        {Object.entries(TYPE_LABELS).map(([key, label]) => (
                            <option key={key} value={key}>{label}</option>
                        ))}
                    </select>
                </div>

                {/* Available list */}
                {availableLoading ? (
                    <LoadingSpinner size="sm" label="Carregando..." />
                ) : filteredAvailable.length > 0 ? (
                    <div className="space-y-1 max-h-48 overflow-y-auto">
                        {filteredAvailable.map(content => {
                            const Icon = TYPE_ICONS[content.content_type] || DocumentIcon;
                            return (
                                <div key={content.id} className="flex items-center justify-between p-2 hover:bg-gray-50 rounded-md">
                                    <div className="flex items-center gap-3 min-w-0 flex-1">
                                        <Icon className="w-4 h-4 text-gray-400 flex-shrink-0" />
                                        <div className="min-w-0">
                                            <span className="text-sm text-gray-900 truncate block">{content.title}</span>
                                            <div className="flex items-center gap-2">
                                                <StatusBadge variant={TYPE_COLORS[content.content_type] || 'gray'}>
                                                    {TYPE_LABELS[content.content_type] || content.content_type}
                                                </StatusBadge>
                                                {content.duration_formatted && (
                                                    <span className="text-xs text-gray-500">{content.duration_formatted}</span>
                                                )}
                                                {content.category?.name && (
                                                    <span className="text-xs text-gray-500">{content.category.name}</span>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="xs"
                                        icon={PlusIcon}
                                        onClick={() => addContent(content)}
                                    >
                                        Adicionar
                                    </Button>
                                </div>
                            );
                        })}
                    </div>
                ) : (
                    <p className="text-sm text-gray-500 text-center py-3">
                        {searchQuery || typeFilter
                            ? 'Nenhum conteúdo encontrado com esses filtros.'
                            : 'Todos os conteúdos já foram adicionados ao curso.'}
                    </p>
                )}
            </StandardModal.Section>
        </StandardModal>
    );
}
