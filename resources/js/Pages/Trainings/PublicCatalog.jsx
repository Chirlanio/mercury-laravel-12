import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    AcademicCapIcon,
    ClockIcon,
    UserGroupIcon,
    DocumentTextIcon,
    CheckBadgeIcon,
} from '@heroicons/react/24/outline';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

function GoogleIcon({ className }) {
    return (
        <svg className={className} viewBox="0 0 24 24">
            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4" />
            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853" />
            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05" />
            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335" />
        </svg>
    );
}

function CourseCard({ course, enrolled, authenticated, googleRedirectUrl, onEnroll }) {
    const [enrolling, setEnrolling] = useState(false);

    const handleEnroll = async () => {
        if (!authenticated) {
            window.location.href = googleRedirectUrl;
            return;
        }

        setEnrolling(true);
        try {
            const response = await fetch(route('public.courses.enroll', course.id), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            });
            const result = await response.json();
            if (response.ok) {
                onEnroll?.(course.id);
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
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden hover:shadow-lg transition-all duration-200">
            {/* Thumbnail placeholder */}
            <div className="h-40 bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
                <AcademicCapIcon className="w-16 h-16 text-white/30" />
            </div>

            <div className="p-5">
                <h3 className="text-base font-semibold text-gray-900 line-clamp-2">{course.title}</h3>

                {course.subject && (
                    <p className="text-xs text-indigo-600 font-medium mt-1">{course.subject.name}</p>
                )}

                {course.description && (
                    <p className="text-sm text-gray-500 mt-2 line-clamp-3">{course.description}</p>
                )}

                <div className="flex items-center gap-4 mt-4 text-xs text-gray-500">
                    {course.content_count > 0 && (
                        <span className="flex items-center gap-1">
                            <DocumentTextIcon className="w-3.5 h-3.5" />
                            {course.content_count} conteúdos
                        </span>
                    )}
                    {course.estimated_duration_minutes && (
                        <span className="flex items-center gap-1">
                            <ClockIcon className="w-3.5 h-3.5" />
                            {Math.floor(course.estimated_duration_minutes / 60)}h{course.estimated_duration_minutes % 60 > 0 ? `${course.estimated_duration_minutes % 60}min` : ''}
                        </span>
                    )}
                    {course.enrollment_count > 0 && (
                        <span className="flex items-center gap-1">
                            <UserGroupIcon className="w-3.5 h-3.5" />
                            {course.enrollment_count} inscritos
                        </span>
                    )}
                </div>

                {course.certificate_on_completion && (
                    <div className="flex items-center gap-1 mt-2 text-xs text-green-600">
                        <CheckBadgeIcon className="w-3.5 h-3.5" />
                        Certificado ao concluir
                    </div>
                )}

                {course.facilitator && (
                    <p className="text-xs text-gray-400 mt-2">Facilitador: {course.facilitator.name}</p>
                )}

                <div className="mt-4">
                    {enrolled ? (
                        <button disabled className="w-full py-2.5 px-4 rounded-lg text-sm font-medium bg-green-50 text-green-700 border border-green-200 cursor-default">
                            Inscrito
                        </button>
                    ) : (
                        <button
                            onClick={handleEnroll}
                            disabled={enrolling}
                            className="w-full py-2.5 px-4 rounded-lg text-sm font-medium bg-indigo-600 text-white hover:bg-indigo-700 transition-colors disabled:opacity-50"
                        >
                            {enrolling ? 'Inscrevendo...' : authenticated ? 'Inscrever-se' : 'Entrar com Google para se inscrever'}
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}

function PublicCatalog({ courses, enrolledIds, authenticated, userName, googleRedirectUrl }) {
    const [localEnrolled, setLocalEnrolled] = useState(enrolledIds || []);

    const handleEnrolled = (courseId) => {
        setLocalEnrolled(prev => [...prev, courseId]);
    };

    return (
        <>
            <Head title="Cursos Disponíveis" />

            {/* Header */}
            <div className="bg-gradient-to-r from-indigo-600 to-purple-700">
                <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-3xl font-bold text-white">Cursos Disponíveis</h1>
                            <p className="mt-2 text-indigo-100">Explore nossos cursos e trilhas de aprendizagem</p>
                        </div>
                        <div>
                            {authenticated ? (
                                <div className="flex items-center gap-3 bg-white/10 rounded-lg px-4 py-2">
                                    <span className="text-sm text-white">{userName}</span>
                                </div>
                            ) : (
                                <a
                                    href={googleRedirectUrl}
                                    className="flex items-center gap-2 bg-white rounded-lg px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors shadow-sm"
                                >
                                    <GoogleIcon className="w-5 h-5" />
                                    Entrar com Google
                                </a>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* Courses Grid */}
            <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                {courses.length > 0 ? (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {courses.map(course => (
                            <CourseCard
                                key={course.id}
                                course={course}
                                enrolled={localEnrolled.includes(course.id)}
                                authenticated={authenticated}
                                googleRedirectUrl={googleRedirectUrl}
                                onEnroll={handleEnrolled}
                            />
                        ))}
                    </div>
                ) : (
                    <div className="text-center py-16">
                        <AcademicCapIcon className="w-16 h-16 text-gray-300 mx-auto mb-4" />
                        <h2 className="text-xl font-semibold text-gray-600">Nenhum curso disponível no momento</h2>
                        <p className="text-gray-400 mt-2">Volte em breve para conferir novos cursos.</p>
                    </div>
                )}
            </div>

            {/* Footer */}
            <div className="border-t border-gray-200 mt-8">
                <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-center text-sm text-gray-400">
                    Mercury - Sistema de Gestão
                </div>
            </div>
        </>
    );
}

// Sem layout autenticado — página pública standalone
PublicCatalog.layout = (page) => page;

export default PublicCatalog;
