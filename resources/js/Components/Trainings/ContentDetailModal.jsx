import { useState, useEffect } from 'react';
import {
    DocumentTextIcon,
    VideoCameraIcon,
    MusicalNoteIcon,
    LinkIcon,
    DocumentIcon,
} from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';

const TYPE_COLORS = {
    video: 'bg-purple-600',
    audio: 'bg-blue-600',
    document: 'bg-red-600',
    link: 'bg-green-600',
    text: 'bg-gray-600',
};

const TYPE_ICONS = {
    video: VideoCameraIcon,
    audio: MusicalNoteIcon,
    document: DocumentTextIcon,
    link: LinkIcon,
    text: DocumentIcon,
};

export default function ContentDetailModal({ show, onClose, contentId }) {
    const [content, setContent] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        if (show && contentId) {
            setLoading(true);
            fetch(route('training-contents.show', contentId))
                .then(res => res.json())
                .then(data => {
                    setContent(data.content);
                    setLoading(false);
                })
                .catch(() => setLoading(false));
        }
    }, [show, contentId]);

    const HeaderIcon = content ? (TYPE_ICONS[content.content_type] || DocumentIcon) : DocumentIcon;

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title={content?.title || 'Detalhes do Conteudo'}
            subtitle={content?.type_label}
            headerColor={content ? (TYPE_COLORS[content.content_type] || 'bg-gray-600') : 'bg-gray-600'}
            headerIcon={<HeaderIcon className="h-5 w-5" />}
            headerBadges={content ? [
                { text: content.is_active ? 'Ativo' : 'Inativo', className: 'bg-white/20 text-white' },
            ] : []}
            maxWidth="3xl"
            loading={loading}
            footer={<StandardModal.Footer onCancel={onClose} cancelLabel="Fechar" />}
        >
            {content && (
                <>
                    {/* Info Geral */}
                    <StandardModal.Section title="Informacoes">
                        <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <StandardModal.Field label="Tipo" value={content.type_label} />
                            <StandardModal.Field label="Categoria" value={content.category?.name || 'Sem categoria'} />
                            {content.duration_formatted && (
                                <StandardModal.Field label="Duracao" value={content.duration_formatted} />
                            )}
                            {content.file_name && (
                                <StandardModal.Field label="Arquivo" value={content.file_name} />
                            )}
                            {content.file_size_formatted && (
                                <StandardModal.Field label="Tamanho" value={content.file_size_formatted} />
                            )}
                            {content.file_mime_type && (
                                <StandardModal.Field label="MIME" value={content.file_mime_type} />
                            )}
                        </div>
                        {content.description && (
                            <div className="mt-3">
                                <p className="text-xs font-medium text-gray-500 mb-1">Descricao</p>
                                <p className="text-sm text-gray-700">{content.description}</p>
                            </div>
                        )}
                    </StandardModal.Section>

                    {/* Preview */}
                    <StandardModal.Section title="Preview">
                        {content.content_type === 'video' && content.file_path && (
                            <video
                                controls
                                className="w-full rounded-lg max-h-64"
                                src={`/storage/${content.file_path}`}
                            />
                        )}
                        {content.content_type === 'video' && content.external_url && (
                            <div className="aspect-video">
                                <iframe
                                    src={content.external_url.replace('watch?v=', 'embed/')}
                                    className="w-full h-full rounded-lg"
                                    allowFullScreen
                                />
                            </div>
                        )}
                        {content.content_type === 'audio' && content.file_path && (
                            <audio controls className="w-full" src={`/storage/${content.file_path}`} />
                        )}
                        {content.content_type === 'document' && content.file_path && (
                            <div className="text-center py-6">
                                <DocumentTextIcon className="w-16 h-16 text-red-400 mx-auto mb-2" />
                                <a
                                    href={`/storage/${content.file_path}`}
                                    target="_blank"
                                    rel="noopener"
                                    className="text-indigo-600 hover:underline text-sm"
                                >
                                    Abrir documento ({content.file_name})
                                </a>
                            </div>
                        )}
                        {content.content_type === 'link' && content.external_url && (
                            <div className="text-center py-6">
                                <LinkIcon className="w-16 h-16 text-green-400 mx-auto mb-2" />
                                <a
                                    href={content.external_url}
                                    target="_blank"
                                    rel="noopener"
                                    className="text-indigo-600 hover:underline text-sm break-all"
                                >
                                    {content.external_url}
                                </a>
                            </div>
                        )}
                        {content.content_type === 'text' && content.text_content && (
                            <div className="prose prose-sm max-w-none bg-gray-50 rounded-lg p-4 max-h-64 overflow-y-auto"
                                 dangerouslySetInnerHTML={{ __html: content.text_content }}
                            />
                        )}
                    </StandardModal.Section>

                    {/* Audit */}
                    <StandardModal.Section title="Registro">
                        <div className="grid grid-cols-2 gap-4">
                            <StandardModal.MiniField label="Criado por" value={content.created_by || '-'} />
                            <StandardModal.MiniField label="Criado em" value={content.created_at} />
                            <StandardModal.MiniField label="Atualizado por" value={content.updated_by || '-'} />
                            <StandardModal.MiniField label="Atualizado em" value={content.updated_at || '-'} />
                        </div>
                    </StandardModal.Section>
                </>
            )}
        </StandardModal>
    );
}
