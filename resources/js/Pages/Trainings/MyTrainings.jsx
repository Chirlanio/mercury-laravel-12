import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    AcademicCapIcon,
    PlayIcon,
    CheckCircleIcon,
    BookOpenIcon,
    ArrowDownTrayIcon,
    ArrowPathIcon,
} from '@heroicons/react/24/outline';
import StatusBadge from '@/Components/Shared/StatusBadge';
import Button from '@/Components/Button';

const ENROLLMENT_COLORS = {
    enrolled: 'info',
    in_progress: 'warning',
    completed: 'success',
    dropped: 'danger',
};

function CourseCard({ enrollment, type }) {
    const course = enrollment.course;
    if (!course) return null;

    return (
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">
            <div className="p-5">
                <div className="flex items-start justify-between">
                    <div className="flex-1">
                        <h3 className="text-sm font-semibold text-gray-900">{course.title}</h3>
                        {course.subject && <p className="text-xs text-gray-500 mt-0.5">{course.subject.name}</p>}
                    </div>
                    <StatusBadge variant={ENROLLMENT_COLORS[enrollment.status]}>
                        {enrollment.status_label}
                    </StatusBadge>
                </div>

                {type === 'in_progress' && (
                    <div className="mt-3">
                        <div className="flex items-center justify-between text-xs text-gray-500 mb-1">
                            <span>Progresso</span>
                            <span>{enrollment.completion_percent}%</span>
                        </div>
                        <div className="w-full bg-gray-200 rounded-full h-2">
                            <div className="bg-indigo-600 h-2 rounded-full transition-all" style={{ width: `${enrollment.completion_percent}%` }} />
                        </div>
                    </div>
                )}

                <div className="mt-4 space-y-3">
                    <div className="text-xs text-gray-500">
                        {type === 'completed' && enrollment.completed_at && <span>Concluído em {enrollment.completed_at}</span>}
                        {type !== 'completed' && enrollment.enrolled_at && <span>Inscrito em {enrollment.enrolled_at}</span>}
                    </div>
                    {type === 'in_progress' && (
                        <Button variant="primary" size="xs" icon={PlayIcon} className="w-full"
                            onClick={() => router.get(route('training-courses.start', course.id))}>
                            Continuar
                        </Button>
                    )}
                    {type === 'completed' && (
                        <div className="grid grid-cols-2 gap-2">
                            <Button variant="outline" size="xs" icon={ArrowPathIcon}
                                onClick={() => router.get(route('training-courses.start', course.id))}>
                                Reassistir
                            </Button>
                            {enrollment.certificate_generated ? (
                                <a href={route('training-courses.certificate', course.id)}>
                                    <Button variant="success" size="xs" icon={ArrowDownTrayIcon} className="w-full">
                                        Certificado
                                    </Button>
                                </a>
                            ) : (
                                <span />
                            )}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

function AvailableCourseCard({ course, onEnrolled }) {
    const [enrolling, setEnrolling] = useState(false);

    const handleEnroll = async () => {
        setEnrolling(true);
        try {
            const response = await fetch(route('training-courses.enroll', course.id), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                },
            });
            const result = await response.json();
            if (response.ok) {
                onEnrolled?.();
            } else {
                alert(result.error || 'Erro ao se inscrever.');
            }
        } catch {
            alert('Erro de conexão.');
        } finally {
            setEnrolling(false);
        }
    };

    return (
        <div className="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition-shadow">
            <div className="p-5">
                <h3 className="text-sm font-semibold text-gray-900">{course.title}</h3>
                {course.subject && <p className="text-xs text-gray-500 mt-0.5">{course.subject.name}</p>}
                {course.description && <p className="text-xs text-gray-400 mt-2 line-clamp-2">{course.description}</p>}
                <div className="flex items-center justify-between mt-4">
                    <span className="text-xs text-gray-500">{course.content_count} conteúdos</span>
                    <Button variant="primary" size="xs" icon={BookOpenIcon} onClick={handleEnroll} loading={enrolling}>
                        Inscrever
                    </Button>
                </div>
            </div>
        </div>
    );
}

export default function MyTrainings({ inProgress, completed, available }) {
    return (
        <>
            <Head title="Meus Treinamentos" />
            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center gap-3 mb-6">
                        <AcademicCapIcon className="w-8 h-8 text-indigo-600" />
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">Meus Treinamentos</h1>
                            <p className="text-sm text-gray-500">Acompanhe seu progresso nos cursos</p>
                        </div>
                    </div>

                    {/* Em andamento */}
                    {inProgress?.length > 0 && (
                        <div className="mb-8">
                            <h2 className="text-lg font-semibold text-gray-800 mb-4">Em Andamento ({inProgress.length})</h2>
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                {inProgress.map(e => <CourseCard key={e.id} enrollment={e} type="in_progress" />)}
                            </div>
                        </div>
                    )}

                    {/* Concluidos */}
                    {completed?.length > 0 && (
                        <div className="mb-8">
                            <h2 className="text-lg font-semibold text-gray-800 mb-4">Concluídos ({completed.length})</h2>
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                {completed.map(e => <CourseCard key={e.id} enrollment={e} type="completed" />)}
                            </div>
                        </div>
                    )}

                    {/* Disponiveis */}
                    {available?.length > 0 && (
                        <div className="mb-8">
                            <h2 className="text-lg font-semibold text-gray-800 mb-4">Cursos Disponíveis ({available.length})</h2>
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                {available.map(c => <AvailableCourseCard key={c.id} course={c} onEnrolled={() => router.reload()} />)}
                            </div>
                        </div>
                    )}

                    {!inProgress?.length && !completed?.length && !available?.length && (
                        <div className="text-center py-12">
                            <AcademicCapIcon className="w-16 h-16 text-gray-300 mx-auto mb-4" />
                            <p className="text-gray-500">Nenhum curso disponível no momento.</p>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
