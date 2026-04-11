import { useState, useEffect } from 'react';
import {
    ClipboardDocumentCheckIcon,
    CheckCircleIcon,
    ClockIcon,
} from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import Button from '@/Components/Button';
import StatusBadge from '@/Components/Shared/StatusBadge';
import LoadingSpinner from '@/Components/Shared/LoadingSpinner';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

export default function QuizGradeModal({ show, onClose, onSuccess, quizId }) {
    const [loading, setLoading] = useState(true);
    const [quizTitle, setQuizTitle] = useState('');
    const [attempts, setAttempts] = useState([]);
    const [grading, setGrading] = useState({});
    const [saving, setSaving] = useState({});

    useEffect(() => {
        if (show && quizId) {
            setLoading(true);
            fetch(route('training-quizzes.ungraded', quizId), {
                headers: { 'Accept': 'application/json' },
            })
                .then(res => res.json())
                .then(data => {
                    setQuizTitle(data.quiz_title);
                    setAttempts(data.attempts);
                    // Inicializar campos de correção
                    const initial = {};
                    data.attempts.forEach(a => {
                        a.responses.forEach(r => {
                            if (!r.graded) {
                                initial[r.id] = { points: '', feedback: '' };
                            }
                        });
                    });
                    setGrading(initial);
                    setLoading(false);
                })
                .catch(() => setLoading(false));
        }
    }, [show, quizId]);

    const setGradeField = (responseId, field, value) => {
        setGrading(prev => ({
            ...prev,
            [responseId]: { ...prev[responseId], [field]: value },
        }));
    };

    const handleGrade = async (responseId, maxPoints) => {
        const grade = grading[responseId];
        if (!grade || grade.points === '') return;

        const points = parseInt(grade.points);
        if (isNaN(points) || points < 0 || points > maxPoints) return;

        setSaving(prev => ({ ...prev, [responseId]: true }));

        try {
            const response = await fetch(route('training-quiz-responses.grade', responseId), {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    points_earned: points,
                    feedback: grade.feedback || null,
                }),
            });
            const result = await response.json();

            if (response.ok) {
                // Atualizar a resposta como corrigida localmente
                setAttempts(prev => prev.map(a => ({
                    ...a,
                    score: a.attempt_id === attempts.find(at => at.responses.some(r => r.id === responseId))?.attempt_id
                        ? result.score : a.score,
                    passed: a.attempt_id === attempts.find(at => at.responses.some(r => r.id === responseId))?.attempt_id
                        ? result.passed : a.passed,
                    responses: a.responses.map(r =>
                        r.id === responseId
                            ? { ...r, graded: true, points_earned: points, feedback: grade.feedback }
                            : r
                    ),
                })));

                // Remover do grading
                setGrading(prev => {
                    const next = { ...prev };
                    delete next[responseId];
                    return next;
                });
            } else {
                alert(result.error || 'Erro ao salvar correção.');
            }
        } catch {
            alert('Erro de conexão.');
        } finally {
            setSaving(prev => ({ ...prev, [responseId]: false }));
        }
    };

    const pendingTotal = attempts.reduce((sum, a) => sum + a.responses.filter(r => !r.graded).length, 0);

    return (
        <StandardModal
            show={show}
            onClose={() => { onClose(); if (pendingTotal === 0) onSuccess?.(); }}
            title="Correção de Respostas"
            subtitle={quizTitle}
            headerColor="bg-orange-600"
            headerIcon={<ClipboardDocumentCheckIcon className="h-5 w-5" />}
            headerBadges={[
                { text: `${pendingTotal} pendente${pendingTotal !== 1 ? 's' : ''}`, className: 'bg-white/20 text-white' },
            ]}
            maxWidth="5xl"
            loading={loading}
            footer={<StandardModal.Footer onCancel={onClose} cancelLabel="Fechar" />}
        >
            {attempts.length === 0 && !loading ? (
                <div className="text-center py-8">
                    <CheckCircleIcon className="w-12 h-12 text-green-400 mx-auto mb-3" />
                    <p className="text-sm text-gray-500">Todas as respostas já foram corrigidas.</p>
                </div>
            ) : (
                <div className="space-y-6">
                    {attempts.map(attempt => (
                        <StandardModal.Section
                            key={attempt.attempt_id}
                            title={`${attempt.user_name} — Tentativa ${attempt.attempt_number}`}
                        >
                            <div className="flex items-center gap-4 mb-3 text-xs text-gray-500">
                                <span>Nota atual: <strong className={attempt.passed ? 'text-green-600' : 'text-red-600'}>{attempt.score}%</strong></span>
                                <StatusBadge variant={attempt.passed ? 'success' : 'danger'}>
                                    {attempt.passed ? 'Aprovado' : 'Reprovado'}
                                </StatusBadge>
                                <span>{attempt.completed_at}</span>
                                <span>{attempt.pending_count} pendente{attempt.pending_count !== 1 ? 's' : ''}</span>
                            </div>

                            <div className="space-y-4">
                                {attempt.responses.map(r => (
                                    <div key={r.id} className={`rounded-lg border p-4 ${
                                        r.graded ? 'bg-green-50 border-green-200' : 'bg-amber-50 border-amber-200'
                                    }`}>
                                        {/* Pergunta */}
                                        <p className="text-sm font-medium text-gray-900 mb-2">{r.question_text}</p>

                                        {/* Resposta do aluno */}
                                        <div className="bg-white border border-gray-200 rounded-md p-3 mb-3">
                                            <p className="text-xs text-gray-500 mb-1">Resposta do participante:</p>
                                            <p className="text-sm text-gray-800 whitespace-pre-wrap">
                                                {r.response_text || <span className="italic text-gray-400">Sem resposta</span>}
                                            </p>
                                        </div>

                                        {r.graded ? (
                                            /* Já corrigida */
                                            <div className="flex items-center gap-4 text-sm">
                                                <span className="text-green-700 font-medium">
                                                    {r.points_earned}/{r.points_possible} pts
                                                </span>
                                                {r.feedback && (
                                                    <span className="text-gray-500 italic">"{r.feedback}"</span>
                                                )}
                                                <StatusBadge variant="success">Corrigida</StatusBadge>
                                            </div>
                                        ) : (
                                            /* Formulário de correção */
                                            <div className="flex items-end gap-3">
                                                <div className="w-28">
                                                    <InputLabel value={`Pontos (max ${r.points_possible})`} />
                                                    <TextInput
                                                        type="number"
                                                        className="mt-1 block w-full text-sm"
                                                        min={0}
                                                        max={r.points_possible}
                                                        value={grading[r.id]?.points ?? ''}
                                                        onChange={e => setGradeField(r.id, 'points', e.target.value)}
                                                        placeholder="0"
                                                    />
                                                </div>
                                                <div className="flex-1">
                                                    <InputLabel value="Feedback (opcional)" />
                                                    <TextInput
                                                        className="mt-1 block w-full text-sm"
                                                        value={grading[r.id]?.feedback ?? ''}
                                                        onChange={e => setGradeField(r.id, 'feedback', e.target.value)}
                                                        placeholder="Comentário sobre a resposta..."
                                                    />
                                                </div>
                                                <Button
                                                    variant="success"
                                                    size="sm"
                                                    onClick={() => handleGrade(r.id, r.points_possible)}
                                                    loading={saving[r.id]}
                                                    disabled={grading[r.id]?.points === '' || grading[r.id]?.points === undefined}
                                                >
                                                    Corrigir
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </StandardModal.Section>
                    ))}
                </div>
            )}
        </StandardModal>
    );
}
