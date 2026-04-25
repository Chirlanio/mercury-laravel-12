import { Head, router } from '@inertiajs/react';
import { useState, useEffect, useCallback } from 'react';
import {
    AcademicCapIcon,
    PlusIcon,
    CalendarDaysIcon,
    UserGroupIcon,
    StarIcon,
    ClockIcon,
    FunnelIcon,
    XMarkIcon,
    VideoCameraIcon,
    BookOpenIcon,
    ClipboardDocumentListIcon,
    QueueListIcon,
    PlayIcon,
} from '@heroicons/react/24/outline';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import DataTable from '@/Components/DataTable';
import PageHeader from '@/Components/Shared/PageHeader';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import LoadingSpinner from '@/Components/Shared/LoadingSpinner';
import EmptyState from '@/Components/Shared/EmptyState';
import EventDetailModal from '@/Components/Trainings/EventDetailModal';
import EventFormModal from '@/Components/Trainings/EventFormModal';
import ContentDetailModal from '@/Components/Trainings/ContentDetailModal';
import ContentFormModal from '@/Components/Trainings/ContentFormModal';
import CourseDetailModal from '@/Components/Trainings/CourseDetailModal';
import CourseFormModal from '@/Components/Trainings/CourseFormModal';
import QuizDetailModal from '@/Components/Trainings/QuizDetailModal';
import QuizFormModal from '@/Components/Trainings/QuizFormModal';
import QuizGradeModal from '@/Components/Trainings/QuizGradeModal';
import CourseContentsModal from '@/Components/Trainings/CourseContentsModal';

const EVENT_STATUS_VARIANTS = {
    draft: 'gray', published: 'info', in_progress: 'warning', completed: 'success', cancelled: 'danger',
};

const COURSE_STATUS_VARIANTS = {
    draft: 'gray', published: 'success', archived: 'warning',
};

const TABS = [
    { key: 'events', label: 'Eventos', icon: CalendarDaysIcon },
    { key: 'contents', label: 'Conteúdos', icon: VideoCameraIcon },
    { key: 'courses', label: 'Cursos', icon: BookOpenIcon },
    { key: 'quizzes', label: 'Quizzes', icon: ClipboardDocumentListIcon },
];

const TYPE_LABELS = { video: 'Vídeo', audio: 'Áudio', document: 'Documento', link: 'Link', text: 'Texto' };
const TYPE_COLORS = { video: 'purple', audio: 'info', document: 'danger', link: 'success', text: 'gray' };

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

const fetchJson = async (url) => {
    const res = await fetch(url, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
};

const fetchDelete = async (url) => {
    const res = await fetch(url, {
        method: 'DELETE',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
};

export default function Index({ trainings, filters, statusOptions, statusCounts, facilitators, subjects }) {
    const { hasPermission } = usePermissions();
    const { modals, selected, openModal, closeModal } = useModalManager([
        'eventCreate', 'eventDetail', 'eventEdit',
        'contentCreate', 'contentDetail', 'contentEdit',
        'courseCreate', 'courseDetail', 'courseEdit', 'courseContents',
        'quizCreate', 'quizDetail', 'quizEdit', 'quizGrade',
    ]);

    const [activeTab, setActiveTab] = useState('events');
    const [stats, setStats] = useState(null);
    const [statsLoading, setStatsLoading] = useState(true);

    // Events tab filters
    const [showFilters, setShowFilters] = useState(false);
    const [localFilters, setLocalFilters] = useState({
        search: filters?.search || '', status: filters?.status || '',
        facilitator_id: filters?.facilitator_id || '', subject_id: filters?.subject_id || '',
        date_from: filters?.date_from || '', date_to: filters?.date_to || '',
    });

    // AJAX tab states
    const [contentsData, setContentsData] = useState(null);
    const [contentsLoading, setContentsLoading] = useState(false);
    const [contentsMeta, setContentsMeta] = useState({});
    const [coursesData, setCoursesData] = useState(null);
    const [coursesLoading, setCoursesLoading] = useState(false);
    const [coursesMeta, setCoursesMeta] = useState({});
    const [quizzesData, setQuizzesData] = useState(null);
    const [quizzesLoading, setQuizzesLoading] = useState(false);

    // Permissions
    const canCreateEvent = hasPermission(PERMISSIONS.CREATE_TRAININGS);
    const canEditEvent = hasPermission(PERMISSIONS.EDIT_TRAININGS);
    const canDeleteEvent = hasPermission(PERMISSIONS.DELETE_TRAININGS);
    const canManageContent = hasPermission(PERMISSIONS.MANAGE_TRAINING_CONTENT);
    const canCreateCourse = hasPermission(PERMISSIONS.CREATE_TRAINING_COURSES);
    const canEditCourse = hasPermission(PERMISSIONS.EDIT_TRAINING_COURSES);
    const canDeleteCourse = hasPermission(PERMISSIONS.DELETE_TRAINING_COURSES);
    const canManageQuiz = hasPermission(PERMISSIONS.MANAGE_TRAINING_QUIZZES);

    // Load event statistics
    useEffect(() => {
        fetchJson(route('trainings.statistics'))
            .then(data => { setStats(data); setStatsLoading(false); })
            .catch(() => setStatsLoading(false));
    }, []);

    // Load tab data on first activation
    useEffect(() => {
        if (activeTab === 'contents' && !contentsData && !contentsLoading) loadContents();
        if (activeTab === 'courses' && !coursesData && !coursesLoading) loadCourses();
        if (activeTab === 'quizzes' && !quizzesData && !quizzesLoading) loadQuizzes();
    }, [activeTab]);

    const loadContents = useCallback((url) => {
        setContentsLoading(true);
        fetchJson(url || route('training-contents.index'))
            .then(data => { setContentsData(data.contents); setContentsMeta(data); setContentsLoading(false); })
            .catch(() => setContentsLoading(false));
    }, []);

    const loadCourses = useCallback((url) => {
        setCoursesLoading(true);
        fetchJson(url || route('training-courses.index'))
            .then(data => { setCoursesData(data.courses); setCoursesMeta(data); setCoursesLoading(false); })
            .catch(() => setCoursesLoading(false));
    }, []);

    const loadQuizzes = useCallback((url) => {
        setQuizzesLoading(true);
        fetchJson(url || route('training-quizzes.index'))
            .then(data => { setQuizzesData(data.quizzes); setQuizzesLoading(false); })
            .catch(() => setQuizzesLoading(false));
    }, []);

    // ==========================================
    // Event handlers
    // ==========================================

    const applyEventFilters = () => {
        router.get(route('trainings.index'), {
            ...Object.fromEntries(Object.entries(localFilters).filter(([_, v]) => v !== '')),
        }, { preserveState: true, preserveScroll: true });
    };

    const clearEventFilters = () => {
        const empty = { search: '', status: '', facilitator_id: '', subject_id: '', date_from: '', date_to: '' };
        setLocalFilters(empty);
        router.get(route('trainings.index'), {}, { preserveState: true, preserveScroll: true });
    };

    const handleDeleteEvent = (training) => {
        if (confirm(`Excluir treinamento "${training.title}"?`)) {
            router.delete(route('trainings.destroy', training.id));
        }
    };

    const handleDeleteContent = async (content) => {
        if (!confirm(`Excluir conteúdo "${content.title}"?`)) return;
        try {
            await fetchDelete(route('training-contents.destroy', content.id));
            loadContents();
        } catch { /* ignore */ }
    };

    const handleDeleteCourse = async (course) => {
        if (!confirm(`Excluir curso "${course.title}"?`)) return;
        try {
            await fetchDelete(route('training-courses.destroy', course.id));
            loadCourses();
        } catch { /* ignore */ }
    };

    const handleDeleteQuiz = async (quiz) => {
        if (!confirm(`Excluir quiz "${quiz.title}"?`)) return;
        try {
            await fetchDelete(route('training-quizzes.destroy', quiz.id));
            loadQuizzes();
        } catch { /* ignore */ }
    };

    // ==========================================
    // Column definitions
    // ==========================================

    const eventColumns = [
        { field: 'title', label: 'Título', sortable: true },
        {
            field: 'event_date_formatted', label: 'Data', sortable: true,
            render: (row) => (
                <div>
                    <div className="font-medium">{row.event_date_formatted}</div>
                    <div className="text-xs text-gray-500">{row.start_time} - {row.end_time}</div>
                </div>
            ),
        },
        { key: 'facilitator', label: 'Facilitador', render: (row) => row.facilitator?.name || '-' },
        { key: 'subject', label: 'Assunto', render: (row) => row.subject?.name || '-' },
        {
            key: 'participant_count', label: 'Participantes',
            render: (row) => (
                <span>{row.participant_count}{row.max_participants && <span className="text-gray-400">/{row.max_participants}</span>}</span>
            ),
        },
        {
            key: 'average_rating', label: 'Avaliação',
            render: (row) => row.average_rating ? (
                <div className="flex items-center gap-1">
                    <StarIcon className="w-4 h-4 text-yellow-400" />
                    <span>{row.average_rating}</span>
                </div>
            ) : <span className="text-gray-400">-</span>,
        },
        {
            key: 'status', label: 'Status',
            render: (row) => <StatusBadge variant={EVENT_STATUS_VARIANTS[row.status] || 'gray'}>{row.status_label}</StatusBadge>,
        },
        {
            key: 'actions', label: 'Ações',
            render: (row) => (
                <ActionButtons
                    onView={() => openModal('eventDetail', row)}
                    onEdit={canEditEvent ? () => openModal('eventEdit', row) : undefined}
                    onDelete={canDeleteEvent ? () => handleDeleteEvent(row) : undefined}
                />
            ),
        },
    ];

    const contentColumns = [
        { field: 'title', label: 'Título', sortable: true },
        {
            key: 'content_type', label: 'Tipo',
            render: (row) => <StatusBadge variant={TYPE_COLORS[row.content_type] || 'gray'}>{TYPE_LABELS[row.content_type] || row.content_type}</StatusBadge>,
        },
        { key: 'category', label: 'Categoria', render: (row) => row.category?.name || '-' },
        { key: 'duration_formatted', label: 'Duração', render: (row) => row.duration_formatted || '-' },
        { key: 'file_size_formatted', label: 'Tamanho', render: (row) => row.file_size_formatted || '-' },
        {
            key: 'is_active', label: 'Status',
            render: (row) => <StatusBadge variant={row.is_active ? 'success' : 'gray'}>{row.is_active ? 'Ativo' : 'Inativo'}</StatusBadge>,
        },
        {
            key: 'actions', label: 'Ações',
            render: (row) => (
                <ActionButtons
                    onView={() => openModal('contentDetail', row)}
                    onEdit={canManageContent ? () => openModal('contentEdit', row) : undefined}
                    onDelete={canManageContent ? () => handleDeleteContent(row) : undefined}
                />
            ),
        },
    ];

    const courseColumns = [
        { field: 'title', label: 'Título', sortable: true },
        { key: 'subject', label: 'Assunto', render: (row) => row.subject?.name || '-' },
        {
            key: 'visibility', label: 'Visibilidade',
            render: (row) => <StatusBadge variant={row.visibility === 'public' ? 'success' : 'info'}>{row.visibility_label}</StatusBadge>,
        },
        {
            key: 'status', label: 'Status',
            render: (row) => <StatusBadge variant={COURSE_STATUS_VARIANTS[row.status] || 'gray'}>{row.status_label}</StatusBadge>,
        },
        { key: 'content_count', label: 'Conteúdos', render: (row) => row.content_count || 0 },
        { key: 'enrollment_count', label: 'Inscritos', render: (row) => row.enrollment_count || 0 },
        {
            key: 'actions', label: 'Ações',
            render: (row) => (
                <ActionButtons
                    onView={() => openModal('courseDetail', row)}
                    onEdit={canEditCourse ? () => openModal('courseEdit', row) : undefined}
                    onDelete={canDeleteCourse ? () => handleDeleteCourse(row) : undefined}
                >
                    {canEditCourse && (
                        <ActionButtons.Custom
                            variant="info"
                            icon={QueueListIcon}
                            title="Gerenciar Conteúdos"
                            onClick={() => openModal('courseContents', row)}
                        />
                    )}
                </ActionButtons>
            ),
        },
    ];

    const quizColumns = [
        { field: 'title', label: 'Título', sortable: true },
        { key: 'question_count', label: 'Perguntas', render: (row) => row.question_count || 0 },
        { key: 'total_points', label: 'Pontos', render: (row) => row.total_points || 0 },
        { key: 'passing_score', label: 'Nota Mínima', render: (row) => `${row.passing_score}%` },
        {
            key: 'time_limit', label: 'Tempo',
            render: (row) => row.time_limit_minutes ? `${row.time_limit_minutes} min` : 'Sem limite',
        },
        {
            key: 'is_active', label: 'Status',
            render: (row) => <StatusBadge variant={row.is_active ? 'success' : 'gray'}>{row.is_active ? 'Ativo' : 'Inativo'}</StatusBadge>,
        },
        {
            key: 'actions', label: 'Ações',
            render: (row) => (
                <ActionButtons
                    onView={() => openModal('quizDetail', row)}
                    onEdit={canManageQuiz ? () => openModal('quizEdit', row) : undefined}
                    onDelete={canManageQuiz ? () => handleDeleteQuiz(row) : undefined}
                >
                    {row.is_active && (
                        <ActionButtons.Custom
                            variant="primary"
                            icon={PlayIcon}
                            title="Responder Quiz"
                            onClick={() => router.get(route('training-quizzes.take', row.id))}
                        />
                    )}
                    {canManageQuiz && (
                        <ActionButtons.Custom
                            variant="outline"
                            icon={ClipboardDocumentListIcon}
                            title="Corrigir Respostas"
                            onClick={() => openModal('quizGrade', row)}
                        />
                    )}
                </ActionButtons>
            ),
        },
    ];

    // ==========================================
    // Statistics cards
    // ==========================================

    const statisticsCards = [
        { label: 'Total', value: stats?.total ?? 0, icon: AcademicCapIcon, color: 'indigo' },
        { label: 'Publicados', value: stats?.by_status?.published?.count ?? 0, icon: CalendarDaysIcon, color: 'blue' },
        { label: 'Em Andamento', value: stats?.by_status?.in_progress?.count ?? 0, icon: ClockIcon, color: 'yellow' },
        { label: 'Concluídos', value: stats?.by_status?.completed?.count ?? 0, icon: AcademicCapIcon, color: 'green' },
        { label: 'Participantes', value: stats?.total_participants ?? 0, icon: UserGroupIcon, color: 'purple' },
        { label: 'Avaliação Média', value: stats?.avg_rating ?? 0, format: 'number', icon: StarIcon, color: 'orange', sub: 'de 5.0' },
    ];

    // ==========================================
    // Tab action button
    // ==========================================

    // Ação de criação muda conforme a aba ativa — cada aba tem seu modal de cadastro próprio.
    const TAB_CREATE_ACTIONS = {
        events:   { label: 'Novo Treinamento', modal: 'eventCreate',   visible: canCreateEvent },
        contents: { label: 'Novo Conteúdo',    modal: 'contentCreate', visible: canManageContent },
        courses:  { label: 'Novo Curso',       modal: 'courseCreate',  visible: canCreateCourse },
        quizzes:  { label: 'Novo Quiz',        modal: 'quizCreate',    visible: canManageQuiz },
    };

    // ==========================================
    // Render
    // ==========================================

    return (
        <>
            <Head title="Treinamentos" />
            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <PageHeader
                        title="Treinamentos"
                        subtitle="Gestão de treinamentos, conteúdos, cursos e quizzes"
                        actions={[
                            activeTab === 'events' && {
                                type: 'filter',
                                onClick: () => setShowFilters(!showFilters),
                            },
                            TAB_CREATE_ACTIONS[activeTab] && {
                                type: 'create',
                                label: TAB_CREATE_ACTIONS[activeTab].label,
                                onClick: () => openModal(TAB_CREATE_ACTIONS[activeTab].modal),
                                visible: TAB_CREATE_ACTIONS[activeTab].visible,
                            },
                        ].filter(Boolean)}
                    />

                    {/* Tabs */}
                    <div className="border-b border-gray-200 mb-6">
                        <nav className="-mb-px flex space-x-8">
                            {TABS.map(tab => {
                                const Icon = tab.icon;
                                const isActive = activeTab === tab.key;
                                return (
                                    <button
                                        key={tab.key}
                                        onClick={() => setActiveTab(tab.key)}
                                        className={`flex items-center gap-2 py-3 px-1 border-b-2 text-sm font-medium transition-colors ${
                                            isActive
                                                ? 'border-indigo-500 text-indigo-600'
                                                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                        }`}
                                    >
                                        <Icon className="w-4 h-4" />
                                        {tab.label}
                                    </button>
                                );
                            })}
                        </nav>
                    </div>

                    {/* Events Tab */}
                    {activeTab === 'events' && (
                        <>
                            <StatisticsGrid cards={statisticsCards} loading={statsLoading} />

                            {showFilters && (
                                <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                                    <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
                                        <div>
                                            <label className="block text-xs font-medium text-gray-700 mb-1">Busca</label>
                                            <input type="text" placeholder="Título, local..."
                                                className="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                value={localFilters.search} onChange={e => setLocalFilters(f => ({ ...f, search: e.target.value }))} />
                                        </div>
                                        <div>
                                            <label className="block text-xs font-medium text-gray-700 mb-1">Status</label>
                                            <select className="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                value={localFilters.status} onChange={e => setLocalFilters(f => ({ ...f, status: e.target.value }))}>
                                                <option value="">Todos</option>
                                                {Object.entries(statusOptions).map(([key, label]) => (
                                                    <option key={key} value={key}>{label} ({statusCounts?.[key] ?? 0})</option>
                                                ))}
                                            </select>
                                        </div>
                                        <div>
                                            <label className="block text-xs font-medium text-gray-700 mb-1">Facilitador</label>
                                            <select className="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                value={localFilters.facilitator_id} onChange={e => setLocalFilters(f => ({ ...f, facilitator_id: e.target.value }))}>
                                                <option value="">Todos</option>
                                                {facilitators?.map(f => <option key={f.id} value={f.id}>{f.name}{f.external ? ' (Ext)' : ''}</option>)}
                                            </select>
                                        </div>
                                        <div>
                                            <label className="block text-xs font-medium text-gray-700 mb-1">Assunto</label>
                                            <select className="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                value={localFilters.subject_id} onChange={e => setLocalFilters(f => ({ ...f, subject_id: e.target.value }))}>
                                                <option value="">Todos</option>
                                                {subjects?.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
                                            </select>
                                        </div>
                                        <div>
                                            <label className="block text-xs font-medium text-gray-700 mb-1">De</label>
                                            <input type="date" className="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                value={localFilters.date_from} onChange={e => setLocalFilters(f => ({ ...f, date_from: e.target.value }))} />
                                        </div>
                                        <div>
                                            <label className="block text-xs font-medium text-gray-700 mb-1">Até</label>
                                            <input type="date" className="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                value={localFilters.date_to} onChange={e => setLocalFilters(f => ({ ...f, date_to: e.target.value }))} />
                                        </div>
                                    </div>
                                    <div className="flex justify-end gap-2 mt-4">
                                        <Button variant="light" size="xs" icon={XMarkIcon} onClick={clearEventFilters}>Limpar</Button>
                                        <Button variant="primary" size="xs" onClick={applyEventFilters}>Aplicar</Button>
                                    </div>
                                </div>
                            )}

                            <DataTable data={trainings} columns={eventColumns} emptyMessage="Nenhum treinamento encontrado." />
                        </>
                    )}

                    {/* Contents Tab */}
                    {activeTab === 'contents' && (
                        <>
                            {contentsLoading && !contentsData ? (
                                <LoadingSpinner size="lg" label="Carregando conteúdos..." fullPage />
                            ) : contentsData ? (
                                <DataTable
                                    data={contentsData}
                                    columns={contentColumns}
                                    emptyMessage="Nenhum conteúdo encontrado."
                                    baseUrl={route('training-contents.index')}
                                    onNavigate={(url) => loadContents(url)}
                                />
                            ) : (
                                <EmptyState title="Biblioteca de Conteúdos" description="Adicione vídeos, documentos e outros materiais de treinamento." icon={VideoCameraIcon} />
                            )}
                        </>
                    )}

                    {/* Courses Tab */}
                    {activeTab === 'courses' && (
                        <>
                            {coursesLoading && !coursesData ? (
                                <LoadingSpinner size="lg" label="Carregando cursos..." fullPage />
                            ) : coursesData ? (
                                <DataTable
                                    data={coursesData}
                                    columns={courseColumns}
                                    emptyMessage="Nenhum curso encontrado."
                                    baseUrl={route('training-courses.index')}
                                    onNavigate={(url) => loadCourses(url)}
                                />
                            ) : (
                                <EmptyState title="Trilhas de Aprendizagem" description="Crie cursos com conteúdos organizados em trilhas." icon={BookOpenIcon} />
                            )}
                        </>
                    )}

                    {/* Quizzes Tab */}
                    {activeTab === 'quizzes' && (
                        <>
                            {quizzesLoading && !quizzesData ? (
                                <LoadingSpinner size="lg" label="Carregando quizzes..." fullPage />
                            ) : quizzesData ? (
                                <DataTable
                                    data={quizzesData}
                                    columns={quizColumns}
                                    emptyMessage="Nenhum quiz encontrado."
                                    baseUrl={route('training-quizzes.index')}
                                    onNavigate={(url) => loadQuizzes(url)}
                                />
                            ) : (
                                <EmptyState title="Banco de Questões" description="Crie quizzes para avaliar o aprendizado." icon={ClipboardDocumentListIcon} />
                            )}
                        </>
                    )}
                </div>
            </div>

            {/* ==========================================
                Event Modals
               ========================================== */}
            <EventFormModal
                show={modals.eventCreate}
                onClose={() => closeModal('eventCreate')}
                onSuccess={() => { closeModal('eventCreate'); router.reload(); }}
                facilitators={facilitators}
                subjects={subjects}
            />

            {selected && modals.eventDetail && (
                <EventDetailModal
                    show={modals.eventDetail}
                    onClose={() => closeModal('eventDetail')}
                    trainingId={selected.id}
                    canEdit={canEditEvent}
                    onEdit={() => { closeModal('eventDetail'); openModal('eventEdit', selected); }}
                />
            )}

            {selected && modals.eventEdit && (
                <EventFormModal
                    show={modals.eventEdit}
                    onClose={() => closeModal('eventEdit')}
                    onSuccess={() => { closeModal('eventEdit'); router.reload(); }}
                    trainingId={selected.id}
                    facilitators={facilitators}
                    subjects={subjects}
                />
            )}

            {/* ==========================================
                Content Modals
               ========================================== */}
            <ContentFormModal
                show={modals.contentCreate}
                onClose={() => closeModal('contentCreate')}
                onSuccess={() => { closeModal('contentCreate'); loadContents(); }}
                categories={contentsMeta.categories || []}
            />

            {selected && modals.contentDetail && (
                <ContentDetailModal
                    show={modals.contentDetail}
                    onClose={() => closeModal('contentDetail')}
                    contentId={selected.id}
                />
            )}

            {selected && modals.contentEdit && (
                <ContentFormModal
                    show={modals.contentEdit}
                    onClose={() => closeModal('contentEdit')}
                    onSuccess={() => { closeModal('contentEdit'); loadContents(); }}
                    contentId={selected.id}
                    categories={contentsMeta.categories || []}
                />
            )}

            {/* ==========================================
                Course Modals
               ========================================== */}
            <CourseFormModal
                show={modals.courseCreate}
                onClose={() => closeModal('courseCreate')}
                onSuccess={() => { closeModal('courseCreate'); loadCourses(); }}
                facilitators={coursesMeta.facilitators || facilitators || []}
                subjects={coursesMeta.subjects || subjects || []}
                stores={coursesMeta.stores || []}
                templates={coursesMeta.templates || []}
            />

            {selected && modals.courseDetail && (
                <CourseDetailModal
                    show={modals.courseDetail}
                    onClose={() => closeModal('courseDetail')}
                    courseId={selected.id}
                    canEdit={canEditCourse}
                    onTransition={() => loadCourses()}
                />
            )}

            {selected && modals.courseEdit && (
                <CourseFormModal
                    show={modals.courseEdit}
                    onClose={() => closeModal('courseEdit')}
                    onSuccess={() => { closeModal('courseEdit'); loadCourses(); }}
                    courseId={selected.id}
                    facilitators={coursesMeta.facilitators || facilitators || []}
                    subjects={coursesMeta.subjects || subjects || []}
                    stores={coursesMeta.stores || []}
                    templates={coursesMeta.templates || []}
                />
            )}

            {selected && modals.courseContents && (
                <CourseContentsModal
                    show={modals.courseContents}
                    onClose={() => closeModal('courseContents')}
                    onSuccess={() => { closeModal('courseContents'); loadCourses(); }}
                    courseId={selected.id}
                />
            )}

            {/* ==========================================
                Quiz Modals
               ========================================== */}
            <QuizFormModal
                show={modals.quizCreate}
                onClose={() => closeModal('quizCreate')}
                onSuccess={() => { closeModal('quizCreate'); loadQuizzes(); }}
            />

            {selected && modals.quizDetail && (
                <QuizDetailModal
                    show={modals.quizDetail}
                    onClose={() => closeModal('quizDetail')}
                    quizId={selected.id}
                />
            )}

            {selected && modals.quizEdit && (
                <QuizFormModal
                    show={modals.quizEdit}
                    onClose={() => closeModal('quizEdit')}
                    onSuccess={() => { closeModal('quizEdit'); loadQuizzes(); }}
                    quizId={selected.id}
                />
            )}

            {selected && modals.quizGrade && (
                <QuizGradeModal
                    show={modals.quizGrade}
                    onClose={() => closeModal('quizGrade')}
                    onSuccess={() => loadQuizzes()}
                    quizId={selected.id}
                />
            )}
        </>
    );
}
