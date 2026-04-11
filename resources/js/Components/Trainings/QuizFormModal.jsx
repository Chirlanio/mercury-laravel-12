import { useState, useEffect } from 'react';
import { ClipboardDocumentListIcon, PlusIcon, TrashIcon } from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import Checkbox from '@/Components/Checkbox';
import Button from '@/Components/Button';

const QUESTION_TYPES = [
    {
        value: 'single',
        label: 'Escolha Única',
        description: 'O participante seleciona apenas UMA opção correta.',
        correctHint: 'Marque a única resposta correta.',
    },
    {
        value: 'multiple',
        label: 'Múltipla Escolha',
        description: 'O participante pode selecionar VÁRIAS opções corretas.',
        correctHint: 'Marque todas as respostas corretas.',
    },
    {
        value: 'boolean',
        label: 'Verdadeiro/Falso',
        description: 'O participante escolhe entre Verdadeiro ou Falso.',
        correctHint: 'Marque qual é a resposta correta.',
    },
    {
        value: 'open_text',
        label: 'Resposta Aberta',
        description: 'O participante escreve a resposta livremente. Avaliada manualmente.',
        correctHint: 'Sem opções — o participante digita a resposta.',
    },
];

const emptyQuestion = () => ({
    question_text: '', question_type: 'single', points: 1, explanation: '',
    options: [
        { option_text: '', is_correct: false },
        { option_text: '', is_correct: false },
    ],
});

const booleanOptions = () => [
    { option_text: 'Verdadeiro', is_correct: false },
    { option_text: 'Falso', is_correct: false },
];

export default function QuizFormModal({ show, onClose, onSuccess, quizId = null }) {
    const isEditing = !!quizId;
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});
    const [data, setData] = useState({
        title: '', description: '', passing_score: 70, max_attempts: '',
        show_answers: false, time_limit_minutes: '',
        content_id: '', course_id: '',
        questions: [emptyQuestion()],
    });

    useEffect(() => {
        if (show && isEditing) {
            fetch(route('training-quizzes.show', quizId))
                .then(res => res.json())
                .then(result => {
                    const q = result.quiz;
                    setData({
                        title: q.title || '', description: q.description || '',
                        passing_score: q.passing_score || 70, max_attempts: q.max_attempts || '',
                        show_answers: q.show_answers || false, time_limit_minutes: q.time_limit_minutes || '',
                        content_id: q.content?.id || '', course_id: q.course?.id || '',
                        questions: q.questions?.length > 0 ? q.questions.map(qu => ({
                            question_text: qu.question_text, question_type: qu.question_type,
                            points: qu.points, explanation: qu.explanation || '',
                            options: qu.options.map(o => ({ option_text: o.option_text, is_correct: o.is_correct })),
                        })) : [emptyQuestion()],
                    });
                });
        } else if (show && !isEditing) {
            setData({
                title: '', description: '', passing_score: 70, max_attempts: '',
                show_answers: false, time_limit_minutes: '', content_id: '', course_id: '',
                questions: [emptyQuestion()],
            });
            setErrors({});
        }
    }, [show, quizId]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        const url = isEditing ? route('training-quizzes.update', quizId) : route('training-quizzes.store');
        const method = isEditing ? 'PUT' : 'POST';

        try {
            const response = await fetch(url, {
                method,
                body: JSON.stringify(data),
                headers: {
                    'Content-Type': 'application/json',
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

    const setField = (field, value) => setData(prev => ({ ...prev, [field]: value }));

    const updateQuestion = (qi, field, value) => {
        setData(prev => {
            const questions = [...prev.questions];
            questions[qi] = { ...questions[qi], [field]: value };

            if (field === 'question_type') {
                if (value === 'boolean') {
                    questions[qi].options = booleanOptions();
                } else if (value === 'open_text') {
                    questions[qi].options = [];
                } else if (prev.questions[qi].question_type === 'boolean' || prev.questions[qi].question_type === 'open_text') {
                    questions[qi].options = [
                        { option_text: '', is_correct: false },
                        { option_text: '', is_correct: false },
                    ];
                }
            }

            return { ...prev, questions };
        });
    };

    const addQuestion = () => setData(prev => ({ ...prev, questions: [...prev.questions, emptyQuestion()] }));

    const removeQuestion = (qi) => {
        if (data.questions.length <= 1) return;
        setData(prev => ({ ...prev, questions: prev.questions.filter((_, i) => i !== qi) }));
    };

    const updateOption = (qi, oi, field, value) => {
        setData(prev => {
            const questions = [...prev.questions];
            const options = [...questions[qi].options];
            options[oi] = { ...options[oi], [field]: value };

            // For single/boolean: uncheck others when checking one
            if (field === 'is_correct' && value && questions[qi].question_type !== 'multiple') {
                options.forEach((o, i) => { if (i !== oi) o.is_correct = false; });
            }

            questions[qi] = { ...questions[qi], options };
            return { ...prev, questions };
        });
    };

    const addOption = (qi) => {
        setData(prev => {
            const questions = [...prev.questions];
            questions[qi] = { ...questions[qi], options: [...questions[qi].options, { option_text: '', is_correct: false }] };
            return { ...prev, questions };
        });
    };

    const removeOption = (qi, oi) => {
        if (data.questions[qi].options.length <= 2) return;
        setData(prev => {
            const questions = [...prev.questions];
            questions[qi] = { ...questions[qi], options: questions[qi].options.filter((_, i) => i !== oi) };
            return { ...prev, questions };
        });
    };

    return (
        <StandardModal
            show={show} onClose={onClose}
            title={isEditing ? 'Editar Quiz' : 'Novo Quiz'}
            headerColor="bg-orange-600" headerIcon={<ClipboardDocumentListIcon className="h-5 w-5" />}
            maxWidth="4xl" onSubmit={handleSubmit}
            footer={<StandardModal.Footer onCancel={onClose} onSubmit="submit"
                submitLabel={isEditing ? 'Atualizar' : 'Criar'} processing={processing} />}
        >
            <StandardModal.Section title="Dados Gerais">
                <div className="space-y-4">
                    <div>
                        <InputLabel htmlFor="title" value="Título *" />
                        <TextInput id="title" className="mt-1 block w-full" value={data.title}
                            onChange={e => setField('title', e.target.value)} required />
                        <InputError message={errors.title} className="mt-1" />
                    </div>
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div>
                            <InputLabel value="Nota Mínima (%)" />
                            <TextInput type="number" className="mt-1 block w-full" value={data.passing_score}
                                onChange={e => setField('passing_score', parseInt(e.target.value) || 70)} min={1} max={100} />
                        </div>
                        <div>
                            <InputLabel value="Max. Tentativas" />
                            <TextInput type="number" className="mt-1 block w-full" value={data.max_attempts}
                                onChange={e => setField('max_attempts', e.target.value)} min={1} placeholder="Ilimitado" />
                        </div>
                        <div>
                            <InputLabel value="Tempo Limite (min)" />
                            <TextInput type="number" className="mt-1 block w-full" value={data.time_limit_minutes}
                                onChange={e => setField('time_limit_minutes', e.target.value)} min={1} placeholder="Sem limite" />
                        </div>
                        <div className="flex items-end">
                            <label className="flex items-center gap-2 pb-2">
                                <Checkbox checked={data.show_answers}
                                    onChange={e => setField('show_answers', e.target.checked)} />
                                <span className="text-sm text-gray-700">Mostrar respostas</span>
                            </label>
                        </div>
                    </div>
                </div>
            </StandardModal.Section>

            {/* Legenda dos tipos */}
            <StandardModal.Section title="Tipos de Pergunta">
                <div className="grid grid-cols-2 gap-3">
                    {QUESTION_TYPES.map(t => (
                        <div key={t.value} className="bg-gray-50 rounded-lg p-3 border border-gray-200">
                            <div className="text-xs font-semibold text-gray-700 mb-1">{t.label}</div>
                            <p className="text-xs text-gray-500">{t.description}</p>
                        </div>
                    ))}
                </div>
            </StandardModal.Section>

            {/* Questions Builder */}
            <StandardModal.Section title={`Perguntas (${data.questions.length})`}>
                <div className="space-y-6">
                    {data.questions.map((q, qi) => {
                        const typeInfo = QUESTION_TYPES.find(t => t.value === q.question_type);
                        const isBoolean = q.question_type === 'boolean';
                        const isOpenText = q.question_type === 'open_text';
                        const hasCorrect = isOpenText || q.options.some(o => o.is_correct);

                        return (
                            <div key={qi} className="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                {/* Header */}
                                <div className="flex items-center justify-between mb-3">
                                    <span className="text-sm font-semibold text-gray-700">Pergunta {qi + 1}</span>
                                    <div className="flex items-center gap-2">
                                        <select className="text-xs rounded-md border-gray-300 shadow-sm"
                                            value={q.question_type} onChange={e => updateQuestion(qi, 'question_type', e.target.value)}>
                                            {QUESTION_TYPES.map(t => <option key={t.value} value={t.value}>{t.label}</option>)}
                                        </select>
                                        <TextInput type="number" className="w-16 text-xs" value={q.points}
                                            onChange={e => updateQuestion(qi, 'points', parseInt(e.target.value) || 1)} min={1} />
                                        <span className="text-xs text-gray-500">pts</span>
                                        {data.questions.length > 1 && (
                                            <button type="button" onClick={() => removeQuestion(qi)}
                                                className="text-red-500 hover:text-red-700">
                                                <TrashIcon className="w-4 h-4" />
                                            </button>
                                        )}
                                    </div>
                                </div>

                                {/* Texto da pergunta */}
                                <textarea className="w-full rounded-md border-gray-300 shadow-sm text-sm mb-3"
                                    rows={2} placeholder="Texto da pergunta..." value={q.question_text}
                                    onChange={e => updateQuestion(qi, 'question_text', e.target.value)} />

                                {/* Dica do tipo + indicador de resposta correta */}
                                <div className="flex items-center justify-between mb-2">
                                    <p className="text-xs text-indigo-600">{typeInfo?.correctHint}</p>
                                    {!hasCorrect && (
                                        <span className="text-xs text-red-500 font-medium">Nenhuma resposta correta marcada</span>
                                    )}
                                </div>

                                {/* Opções ou preview de resposta aberta */}
                                {isOpenText ? (
                                    <div className="bg-white border border-dashed border-gray-300 rounded-md p-4 text-center">
                                        <p className="text-xs text-gray-400 italic">O participante verá um campo de texto para escrever a resposta.</p>
                                        <p className="text-xs text-amber-600 mt-1">A pontuação será atribuída manualmente após a avaliação.</p>
                                    </div>
                                ) : (
                                <div className="space-y-2">
                                    {q.options.map((o, oi) => (
                                        <div key={oi} className={`flex items-center gap-2 p-2 rounded-md border transition-colors ${
                                            o.is_correct
                                                ? 'bg-green-50 border-green-300'
                                                : 'bg-white border-gray-200'
                                        }`}>
                                            <button type="button"
                                                onClick={() => updateOption(qi, oi, 'is_correct', !o.is_correct)}
                                                className={`flex-shrink-0 w-5 h-5 rounded-full border-2 flex items-center justify-center transition-colors ${
                                                    o.is_correct
                                                        ? 'bg-green-500 border-green-500 text-white'
                                                        : 'border-gray-300 hover:border-green-400'
                                                }`}
                                                title={o.is_correct ? 'Resposta correta' : 'Marcar como correta'}
                                            >
                                                {o.is_correct && (
                                                    <svg className="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd" />
                                                    </svg>
                                                )}
                                            </button>
                                            {isBoolean ? (
                                                <span className={`flex-1 text-sm font-medium ${o.is_correct ? 'text-green-700' : 'text-gray-700'}`}>
                                                    {o.option_text}
                                                </span>
                                            ) : (
                                                <TextInput className="flex-1 text-sm" value={o.option_text}
                                                    onChange={e => updateOption(qi, oi, 'option_text', e.target.value)}
                                                    placeholder={`Opção ${oi + 1}`} />
                                            )}
                                            {o.is_correct && (
                                                <span className="text-xs font-medium text-green-600 flex-shrink-0">Correta</span>
                                            )}
                                            {!isBoolean && q.options.length > 2 && (
                                                <button type="button" onClick={() => removeOption(qi, oi)}
                                                    className="text-red-400 hover:text-red-600 flex-shrink-0">
                                                    <TrashIcon className="w-3.5 h-3.5" />
                                                </button>
                                            )}
                                        </div>
                                    ))}
                                    {!isBoolean && (
                                        <button type="button" onClick={() => addOption(qi)}
                                            className="text-xs text-indigo-600 hover:text-indigo-800 flex items-center gap-1 mt-1">
                                            <PlusIcon className="w-3.5 h-3.5" /> Adicionar opção
                                        </button>
                                    )}
                                </div>
                                )}

                                {/* Explicação (exibida após responder, se show_answers ativo) */}
                                <div className="mt-3">
                                    <InputLabel value="Explicação (opcional)" />
                                    <textarea className="mt-1 w-full rounded-md border-gray-300 shadow-sm text-xs"
                                        rows={2} placeholder="Explique por que esta é a resposta correta. Exibida ao participante após responder (se 'Mostrar respostas' estiver ativo)."
                                        value={q.explanation} onChange={e => updateQuestion(qi, 'explanation', e.target.value)} />
                                </div>
                            </div>
                        );
                    })}
                </div>
                <div className="mt-4">
                    <Button variant="outline" size="sm" icon={PlusIcon} onClick={addQuestion}>
                        Adicionar Pergunta
                    </Button>
                </div>
            </StandardModal.Section>
        </StandardModal>
    );
}
