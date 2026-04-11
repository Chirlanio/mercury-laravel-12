import { useState, useEffect } from 'react';
import {
    BookOpenIcon,
    UserGroupIcon,
    DocumentTextIcon,
    CheckCircleIcon,
    LockClosedIcon,
} from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';
import Button from '@/Components/Button';

const STATUS_VARIANTS = {
    draft: 'gray',
    published: 'success',
    archived: 'warning',
};

const ENROLLMENT_VARIANTS = {
    enrolled: 'info',
    in_progress: 'warning',
    completed: 'success',
    dropped: 'danger',
};

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

export default function CourseDetailModal({ show, onClose, courseId, canEdit = false, onTransition }) {
    const [course, setCourse] = useState(null);
    const [loading, setLoading] = useState(true);

    const loadCourse = () => {
        fetch(route('training-courses.show', courseId), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(res => res.json())
            .then(data => {
                setCourse(data.course);
                setLoading(false);
            })
            .catch(() => setLoading(false));
    };

    useEffect(() => {
        if (show && courseId) {
            setLoading(true);
            loadCourse();
        }
    }, [show, courseId]);

    const handleTransition = async (newStatus) => {
        try {
            const response = await fetch(route('training-courses.transition', courseId), {
                method: 'POST',
                body: JSON.stringify({ status: newStatus }),
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            });
            const result = await response.json();
            if (response.ok) {
                loadCourse();
                onTransition?.();
            } else {
                alert(result.error || 'Erro ao atualizar status.');
            }
        } catch {
            alert('Erro de conexão.');
        }
    };

    const headerActions = canEdit && course?.valid_transitions?.length > 0 ? (
        <div className="flex items-center gap-2">
            {Object.entries(course.transition_labels || {}).map(([status, label]) => (
                <Button key={status} variant="light" size="xs" onClick={() => handleTransition(status)}>
                    {label}
                </Button>
            ))}
        </div>
    ) : null;

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title={course?.title || 'Detalhes do Curso'}
            subtitle={course?.subject?.name}
            headerColor="bg-green-600"
            headerIcon={<BookOpenIcon className="h-5 w-5" />}
            headerBadges={course ? [
                { text: course.status_label, className: 'bg-white/20 text-white' },
                { text: course.visibility_label, className: 'bg-white/10 text-white/80' },
            ] : []}
            headerActions={headerActions}
            maxWidth="4xl"
            loading={loading}
            footer={<StandardModal.Footer onCancel={onClose} cancelLabel="Fechar" />}
        >
            {course && (
                <>
                    <StandardModal.Section title="Informacoes">
                        <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <StandardModal.Field label="Facilitador" value={course.facilitator?.name || '-'} />
                            <StandardModal.Field label="Assunto" value={course.subject?.name || '-'} />
                            <StandardModal.Field label="Duracao Estimada" value={course.estimated_duration_minutes ? `${Math.floor(course.estimated_duration_minutes / 60)}h${course.estimated_duration_minutes % 60}min` : '-'} />
                            <StandardModal.Field label="Conteudos" value={course.content_count} icon={DocumentTextIcon} />
                            <StandardModal.Field label="Inscritos" value={course.enrollment_count} icon={UserGroupIcon} />
                            <StandardModal.Field label="Sequencial" value={course.requires_sequential ? 'Sim' : 'Nao'} icon={course.requires_sequential ? LockClosedIcon : CheckCircleIcon} />
                        </div>
                        {course.description && (
                            <div className="mt-3">
                                <p className="text-xs font-medium text-gray-500 mb-1">Descricao</p>
                                <p className="text-sm text-gray-700">{course.description}</p>
                            </div>
                        )}
                    </StandardModal.Section>

                    {/* Conteudos */}
                    <StandardModal.Section title={`Conteudos (${course.contents?.length || 0})`}>
                        {course.contents?.length > 0 ? (
                            <div className="divide-y divide-gray-100 max-h-48 overflow-y-auto">
                                {course.contents.map((c, i) => (
                                    <div key={c.id} className="flex items-center justify-between py-2">
                                        <div className="flex items-center gap-3">
                                            <span className="text-xs font-medium text-gray-400 w-6">{i + 1}.</span>
                                            <div>
                                                <span className="text-sm font-medium text-gray-900">{c.title}</span>
                                                <span className="ml-2 text-xs text-gray-500">{c.type_label}</span>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2 text-xs">
                                            {c.duration_formatted && <span className="text-gray-500">{c.duration_formatted}</span>}
                                            {c.is_required && <StatusBadge variant="info">Obrigatorio</StatusBadge>}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-gray-500">Nenhum conteudo adicionado.</p>
                        )}
                    </StandardModal.Section>

                    {/* Inscricoes */}
                    <StandardModal.Section title={`Inscricoes (${course.enrollments?.length || 0})`}>
                        {course.enrollments?.length > 0 ? (
                            <div className="divide-y divide-gray-100 max-h-48 overflow-y-auto">
                                {course.enrollments.map(e => (
                                    <div key={e.id} className="flex items-center justify-between py-2">
                                        <span className="text-sm text-gray-900">{e.user_name}</span>
                                        <div className="flex items-center gap-3">
                                            <div className="w-24 bg-gray-200 rounded-full h-2">
                                                <div className="bg-indigo-600 h-2 rounded-full" style={{ width: `${e.completion_percent}%` }} />
                                            </div>
                                            <span className="text-xs text-gray-500 w-10">{e.completion_percent}%</span>
                                            <StatusBadge variant={ENROLLMENT_VARIANTS[e.status]}>{e.status_label}</StatusBadge>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-gray-500">Nenhuma inscricao.</p>
                        )}
                    </StandardModal.Section>

                    <StandardModal.Section title="Registro">
                        <div className="grid grid-cols-2 gap-4">
                            <StandardModal.MiniField label="Criado por" value={course.created_by || '-'} />
                            <StandardModal.MiniField label="Criado em" value={course.created_at} />
                            <StandardModal.MiniField label="Atualizado por" value={course.updated_by || '-'} />
                            <StandardModal.MiniField label="Atualizado em" value={course.updated_at || '-'} />
                        </div>
                    </StandardModal.Section>
                </>
            )}
        </StandardModal>
    );
}
