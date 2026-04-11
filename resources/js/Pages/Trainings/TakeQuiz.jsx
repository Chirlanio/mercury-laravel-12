import { Head } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';
import {
    ClockIcon,
    CheckCircleIcon,
    XCircleIcon,
    ArrowLeftIcon,
    ArrowRightIcon,
} from '@heroicons/react/24/outline';
import Button from '@/Components/Button';

export default function TakeQuiz({ quiz }) {
    const [attemptId, setAttemptId] = useState(null);
    const [questions, setQuestions] = useState([]);
    const [answers, setAnswers] = useState({});
    const [currentIndex, setCurrentIndex] = useState(0);
    const [timeLeft, setTimeLeft] = useState(null);
    const [loading, setLoading] = useState(false);
    const [result, setResult] = useState(null);
    const [review, setReview] = useState(null);
    const [error, setError] = useState(null);
    const timerRef = useRef(null);

    // Start attempt
    const startQuiz = () => {
        setLoading(true);
        fetch(route('training-quizzes.start', quiz.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
            },
        })
        .then(res => res.json())
        .then(data => {
            setLoading(false);
            if (data.success) {
                setAttemptId(data.attempt_id);
                setQuestions(data.questions);
                if (quiz.time_limit_minutes) {
                    setTimeLeft(quiz.time_limit_minutes * 60);
                }
            } else {
                setError(data.error);
            }
        })
        .catch(() => { setLoading(false); setError('Erro ao iniciar quiz.'); });
    };

    // Timer
    useEffect(() => {
        if (timeLeft !== null && timeLeft > 0 && !result) {
            timerRef.current = setTimeout(() => setTimeLeft(t => t - 1), 1000);
            return () => clearTimeout(timerRef.current);
        }
        if (timeLeft === 0 && !result) {
            handleSubmit();
        }
    }, [timeLeft, result]);

    const formatTime = (seconds) => {
        const m = Math.floor(seconds / 60);
        const s = seconds % 60;
        return `${m}:${s.toString().padStart(2, '0')}`;
    };

    // Select option
    const selectOption = (questionId, optionId, questionType) => {
        setAnswers(prev => {
            const current = prev[questionId] || [];
            if (questionType === 'multiple') {
                const updated = current.includes(optionId)
                    ? current.filter(id => id !== optionId)
                    : [...current, optionId];
                return { ...prev, [questionId]: updated };
            }
            return { ...prev, [questionId]: [optionId] };
        });
    };

    // Submit
    const handleSubmit = () => {
        setLoading(true);
        const formattedAnswers = questions.map(q => ({
            question_id: q.id,
            selected_options: answers[q.id] || [],
        }));

        fetch(route('training-quiz-attempts.submit', attemptId), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
            },
            body: JSON.stringify({ answers: formattedAnswers }),
        })
        .then(res => res.json())
        .then(data => {
            setLoading(false);
            if (data.success) {
                setResult(data.result);
                clearTimeout(timerRef.current);
                // Load review
                fetch(route('training-quiz-attempts.review', attemptId))
                    .then(r => r.json())
                    .then(r => setReview(r));
            } else {
                setError(data.error);
            }
        })
        .catch(() => { setLoading(false); setError('Erro ao enviar respostas.'); });
    };

    const currentQ = questions[currentIndex];
    const answeredCount = Object.values(answers).filter(a => a.length > 0).length;

    // Result screen
    if (result) {
        return (
            <>
                <Head title={`Resultado - ${quiz.title}`} />
                <div className="py-12">
                    <div className="max-w-2xl mx-auto px-4">
                        <div className={`text-center p-8 rounded-lg ${result.passed ? 'bg-green-50' : 'bg-red-50'}`}>
                            {result.passed
                                ? <CheckCircleIcon className="w-16 h-16 text-green-500 mx-auto mb-4" />
                                : <XCircleIcon className="w-16 h-16 text-red-500 mx-auto mb-4" />
                            }
                            <h1 className="text-2xl font-bold mb-2">{result.passed ? 'Aprovado!' : 'Reprovado'}</h1>
                            <p className="text-4xl font-bold mb-4">{result.score}%</p>
                            <p className="text-gray-600">
                                {result.earned_points}/{result.total_points} pontos (mínimo: {result.passing_score}%)
                            </p>
                        </div>

                        {review?.responses && quiz.show_answers && (
                            <div className="mt-8 space-y-4">
                                <h2 className="text-lg font-semibold">Revisão</h2>
                                {review.responses.map((r, i) => (
                                    <div key={i} className={`p-4 rounded-lg border ${r.is_correct ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50'}`}>
                                        <p className="text-sm font-medium mb-2">{i + 1}. {r.question_text}</p>
                                        {r.options?.map(o => (
                                            <div key={o.id} className={`text-xs px-2 py-1 rounded mb-1 ${
                                                o.is_correct ? 'bg-green-100 text-green-700' :
                                                r.selected_options.includes(o.id) ? 'bg-red-100 text-red-700' : 'text-gray-500'
                                            }`}>
                                                {o.is_correct ? '✓' : r.selected_options.includes(o.id) ? '✗' : '○'} {o.option_text}
                                            </div>
                                        ))}
                                        {r.explanation && <p className="text-xs text-gray-500 mt-2 italic">{r.explanation}</p>}
                                    </div>
                                ))}
                            </div>
                        )}

                        <div className="mt-6 text-center">
                            <Button variant="primary" onClick={() => window.history.back()}>Voltar</Button>
                        </div>
                    </div>
                </div>
            </>
        );
    }

    // Start screen
    if (!attemptId) {
        return (
            <>
                <Head title={quiz.title} />
                <div className="py-12">
                    <div className="max-w-lg mx-auto px-4 text-center">
                        <h1 className="text-2xl font-bold mb-4">{quiz.title}</h1>
                        {quiz.description && <p className="text-gray-600 mb-6">{quiz.description}</p>}
                        <div className="grid grid-cols-2 gap-4 mb-6 text-sm text-gray-500">
                            <div>Nota minima: <strong>{quiz.passing_score}%</strong></div>
                            <div>Tempo: <strong>{quiz.time_limit_minutes ? `${quiz.time_limit_minutes} min` : 'Sem limite'}</strong></div>
                        </div>
                        {error && <p className="text-red-500 mb-4">{error}</p>}
                        <Button variant="primary" size="lg" onClick={startQuiz} loading={loading}>
                            Iniciar Quiz
                        </Button>
                    </div>
                </div>
            </>
        );
    }

    // Quiz in progress
    return (
        <>
            <Head title={`${quiz.title} - Pergunta ${currentIndex + 1}`} />
            <div className="py-6">
                <div className="max-w-3xl mx-auto px-4">
                    {/* Header */}
                    <div className="flex items-center justify-between mb-6">
                        <h2 className="text-lg font-semibold">{quiz.title}</h2>
                        <div className="flex items-center gap-4">
                            <span className="text-sm text-gray-500">{answeredCount}/{questions.length} respondidas</span>
                            {timeLeft !== null && (
                                <div className={`flex items-center gap-1 text-sm font-mono ${timeLeft < 60 ? 'text-red-600' : 'text-gray-700'}`}>
                                    <ClockIcon className="w-4 h-4" />
                                    {formatTime(timeLeft)}
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Progress bar */}
                    <div className="w-full bg-gray-200 rounded-full h-1.5 mb-6">
                        <div className="bg-indigo-600 h-1.5 rounded-full transition-all"
                            style={{ width: `${((currentIndex + 1) / questions.length) * 100}%` }} />
                    </div>

                    {/* Question */}
                    {currentQ && (
                        <div className="bg-white rounded-lg shadow-sm p-6">
                            <div className="flex items-start justify-between mb-4">
                                <span className="text-xs text-gray-500">Pergunta {currentIndex + 1} de {questions.length} ({currentQ.points} pts)</span>
                            </div>
                            <p className="text-lg font-medium text-gray-900 mb-6">{currentQ.question_text}</p>

                            <div className="space-y-3">
                                {currentQ.options.map(o => {
                                    const selected = (answers[currentQ.id] || []).includes(o.id);
                                    return (
                                        <button key={o.id} type="button"
                                            className={`w-full text-left px-4 py-3 rounded-lg border-2 transition-colors text-sm ${
                                                selected ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-200 hover:border-gray-300'
                                            }`}
                                            onClick={() => selectOption(currentQ.id, o.id, currentQ.question_type)}>
                                            {o.option_text}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>
                    )}

                    {/* Navigation */}
                    <div className="flex items-center justify-between mt-6">
                        <Button variant="outline" size="sm" icon={ArrowLeftIcon}
                            onClick={() => setCurrentIndex(i => Math.max(0, i - 1))}
                            disabled={currentIndex === 0}>
                            Anterior
                        </Button>

                        {/* Question dots */}
                        <div className="flex items-center gap-1">
                            {questions.map((q, i) => (
                                <button key={q.id} type="button"
                                    className={`w-6 h-6 rounded-full text-xs ${
                                        i === currentIndex ? 'bg-indigo-600 text-white' :
                                        (answers[q.id]?.length > 0) ? 'bg-green-200 text-green-700' : 'bg-gray-200 text-gray-500'
                                    }`}
                                    onClick={() => setCurrentIndex(i)}>
                                    {i + 1}
                                </button>
                            ))}
                        </div>

                        {currentIndex < questions.length - 1 ? (
                            <Button variant="primary" size="sm"
                                onClick={() => setCurrentIndex(i => i + 1)}>
                                Próxima <ArrowRightIcon className="w-4 h-4 ml-1 inline" />
                            </Button>
                        ) : (
                            <Button variant="success" size="sm" onClick={handleSubmit} loading={loading}>
                                Finalizar Quiz
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
