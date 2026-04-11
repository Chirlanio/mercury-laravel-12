import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { ClipboardDocumentListIcon, CheckCircleIcon, XCircleIcon, PlayIcon } from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';
import Button from '@/Components/Button';

export default function QuizDetailModal({ show, onClose, quizId }) {
    const [quiz, setQuiz] = useState(null);
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        if (show && quizId) {
            setLoading(true);
            fetch(route('training-quizzes.show', quizId))
                .then(res => res.json())
                .then(data => {
                    setQuiz(data.quiz);
                    setStats(data.stats);
                    setLoading(false);
                })
                .catch(() => setLoading(false));
        }
    }, [show, quizId]);

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title={quiz?.title || 'Detalhes do Quiz'}
            headerColor="bg-orange-600"
            headerIcon={<ClipboardDocumentListIcon className="h-5 w-5" />}
            headerBadges={quiz ? [
                { text: quiz.is_active ? 'Ativo' : 'Inativo', className: 'bg-white/20 text-white' },
            ] : []}
            maxWidth="4xl"
            loading={loading}
            footer={
                <StandardModal.Footer
                    onCancel={onClose}
                    cancelLabel="Fechar"
                    extraButtons={quiz?.is_active ? [
                        <Button key="take" variant="primary" size="sm" icon={PlayIcon}
                            onClick={() => { onClose(); router.get(route('training-quizzes.take', quizId)); }}>
                            Responder Quiz
                        </Button>,
                    ] : []}
                />
            }
        >
            {quiz && (
                <>
                    <StandardModal.Section title="Configuração">
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <StandardModal.Field label="Nota Mínima" value={`${quiz.passing_score}%`} />
                            <StandardModal.Field label="Tentativas" value={quiz.max_attempts || 'Ilimitado'} />
                            <StandardModal.Field label="Tempo Limite" value={quiz.time_limit_minutes ? `${quiz.time_limit_minutes} min` : 'Sem limite'} />
                            <StandardModal.Field label="Mostrar Respostas" value={quiz.show_answers ? 'Sim' : 'Não'} />
                        </div>
                        <div className="grid grid-cols-3 gap-4 mt-4">
                            <StandardModal.InfoCard label="Perguntas" value={quiz.question_count} />
                            <StandardModal.InfoCard label="Total Pontos" value={quiz.total_points} />
                            <StandardModal.InfoCard label="Vinculado a" value={quiz.content?.title || quiz.course?.title || 'Independente'} />
                        </div>
                    </StandardModal.Section>

                    {stats && (
                        <StandardModal.Section title="Estatísticas">
                            <div className="grid grid-cols-3 gap-4">
                                <StandardModal.InfoCard label="Tentativas" value={stats.total} />
                                <StandardModal.InfoCard label="Aprovados" value={stats.passed} sub={stats.total > 0 ? `${Math.round((stats.passed / stats.total) * 100)}%` : '0%'} />
                                <StandardModal.InfoCard label="Média" value={`${stats.avg_score}%`} />
                            </div>
                        </StandardModal.Section>
                    )}

                    <StandardModal.Section title={`Perguntas (${quiz.questions?.length || 0})`}>
                        <div className="space-y-4 max-h-64 overflow-y-auto">
                            {quiz.questions?.map((q, i) => (
                                <div key={q.id} className="bg-gray-50 rounded-lg p-3">
                                    <div className="flex items-start justify-between">
                                        <div className="flex-1">
                                            <span className="text-xs font-medium text-gray-500">Pergunta {i + 1} ({q.type_label}, {q.points}pts)</span>
                                            <p className="text-sm text-gray-900 mt-1">{q.question_text}</p>
                                        </div>
                                    </div>
                                    <div className="mt-2 space-y-1">
                                        {q.question_type === 'open_text' ? (
                                            <p className="text-xs text-amber-600 italic px-2 py-1">Resposta aberta — avaliação manual</p>
                                        ) : (
                                            q.options?.map(o => (
                                                <div key={o.id} className={`flex items-center gap-2 text-xs px-2 py-1 rounded ${o.is_correct ? 'bg-green-50 text-green-700' : 'text-gray-600'}`}>
                                                    {o.is_correct ? <CheckCircleIcon className="w-3.5 h-3.5 text-green-500" /> : <XCircleIcon className="w-3.5 h-3.5 text-gray-300" />}
                                                    <span>{o.option_text}</span>
                                                </div>
                                            ))
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </StandardModal.Section>
                </>
            )}
        </StandardModal>
    );
}
