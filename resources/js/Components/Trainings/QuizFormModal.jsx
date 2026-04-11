import { useState, useEffect } from 'react';
import { ClipboardDocumentListIcon, PlusIcon, TrashIcon } from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import Checkbox from '@/Components/Checkbox';
import Button from '@/Components/Button';

const QUESTION_TYPES = [
    { value: 'single', label: 'Escolha Única' },
    { value: 'multiple', label: 'Múltipla Escolha' },
    { value: 'boolean', label: 'Verdadeiro/Falso' },
];

const emptyQuestion = () => ({
    question_text: '', question_type: 'single', points: 1, explanation: '',
    options: [
        { option_text: '', is_correct: false },
        { option_text: '', is_correct: false },
    ],
});

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

            {/* Questions Builder */}
            <StandardModal.Section title={`Perguntas (${data.questions.length})`}>
                <div className="space-y-6">
                    {data.questions.map((q, qi) => (
                        <div key={qi} className="bg-gray-50 rounded-lg p-4 border border-gray-200">
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

                            <textarea className="w-full rounded-md border-gray-300 shadow-sm text-sm mb-3"
                                rows={2} placeholder="Texto da pergunta..." value={q.question_text}
                                onChange={e => updateQuestion(qi, 'question_text', e.target.value)} />

                            <div className="space-y-2">
                                {q.options.map((o, oi) => (
                                    <div key={oi} className="flex items-center gap-2">
                                        <Checkbox checked={o.is_correct}
                                            onChange={e => updateOption(qi, oi, 'is_correct', e.target.checked)} />
                                        <TextInput className="flex-1 text-sm" value={o.option_text}
                                            onChange={e => updateOption(qi, oi, 'option_text', e.target.value)}
                                            placeholder={`Opção ${oi + 1}`} />
                                        {q.options.length > 2 && (
                                            <button type="button" onClick={() => removeOption(qi, oi)}
                                                className="text-red-400 hover:text-red-600">
                                                <TrashIcon className="w-3.5 h-3.5" />
                                            </button>
                                        )}
                                    </div>
                                ))}
                                <button type="button" onClick={() => addOption(qi)}
                                    className="text-xs text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                                    <PlusIcon className="w-3.5 h-3.5" /> Adicionar opção
                                </button>
                            </div>
                        </div>
                    ))}
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
