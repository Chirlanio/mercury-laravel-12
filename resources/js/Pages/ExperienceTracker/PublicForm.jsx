import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { CheckCircleIcon, StarIcon } from '@heroicons/react/24/outline';
import Button from '@/Components/Button';

function RatingInput({ value, onChange }) {
    return (
        <div className="flex items-center gap-1">
            {[1, 2, 3, 4, 5].map(n => (
                <button key={n} type="button" onClick={() => onChange(n)}
                    className="focus:outline-none">
                    <StarIcon className={`w-8 h-8 transition-colors ${n <= (value || 0) ? 'text-yellow-400 fill-yellow-400' : 'text-gray-300 hover:text-yellow-200'}`} />
                </button>
            ))}
            {value && <span className="text-sm text-gray-600 ml-2">{value}/5</span>}
        </div>
    );
}

export default function PublicForm({ evaluation, questions, alreadyCompleted }) {
    const [answers, setAnswers] = useState({});
    const [submitting, setSubmitting] = useState(false);
    const [submitted, setSubmitted] = useState(false);
    const [error, setError] = useState(null);

    if (alreadyCompleted || submitted) {
        return (
            <>
                <Head title="Avaliação de Experiência" />
                <div className="min-h-screen bg-gray-50 flex items-center justify-center p-4">
                    <div className="bg-white rounded-lg shadow-sm p-8 max-w-md text-center">
                        <CheckCircleIcon className="w-16 h-16 text-green-500 mx-auto mb-4" />
                        <h1 className="text-xl font-bold text-gray-900 mb-2">
                            {submitted ? 'Obrigado!' : 'Avaliação já respondida'}
                        </h1>
                        <p className="text-gray-600">
                            {submitted
                                ? 'Sua avaliação foi registrada com sucesso.'
                                : 'Esta avaliação já foi respondida anteriormente.'}
                        </p>
                    </div>
                </div>
            </>
        );
    }

    if (!evaluation) return null;

    const handleSubmit = (e) => {
        e.preventDefault();
        setSubmitting(true);
        setError(null);

        const formData = {};
        questions.forEach(q => {
            formData[`response_${q.id}`] = answers[q.id] ?? '';
        });

        fetch(route('experience-tracker.public-submit', evaluation.token), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
            },
            body: JSON.stringify(formData),
        })
        .then(res => {
            if (!res.ok) throw new Error('Erro ao enviar');
            return res.json();
        })
        .then(() => { setSubmitting(false); setSubmitted(true); })
        .catch(() => { setSubmitting(false); setError('Erro ao enviar. Tente novamente.'); });
    };

    return (
        <>
            <Head title={`Avaliação ${evaluation.milestone_label}`} />
            <div className="min-h-screen bg-gray-50 py-8">
                <div className="max-w-2xl mx-auto px-4">
                    <div className="bg-white rounded-lg shadow-sm overflow-hidden">
                        {/* Header */}
                        <div className="bg-teal-600 px-6 py-4 text-white">
                            <h1 className="text-lg font-bold">Avaliação de Período de Experiência</h1>
                            <p className="text-sm text-teal-100">{evaluation.milestone_label} - {evaluation.employee_name}</p>
                        </div>

                        {/* Form */}
                        <form onSubmit={handleSubmit} className="p-6">
                            <p className="text-sm text-gray-600 mb-6">
                                Por favor, avalie sua experiência até o momento. Suas respostas são confidenciais.
                            </p>

                            <div className="space-y-6">
                                {questions?.map((q, i) => (
                                    <div key={q.id} className="border-b border-gray-100 pb-6 last:border-0">
                                        <label className="block text-sm font-medium text-gray-900 mb-2">
                                            {i + 1}. {q.question_text}
                                            {q.is_required && <span className="text-red-500 ml-1">*</span>}
                                        </label>

                                        {q.question_type === 'rating' && (
                                            <RatingInput value={answers[q.id]} onChange={v => setAnswers(prev => ({ ...prev, [q.id]: v }))} />
                                        )}

                                        {q.question_type === 'text' && (
                                            <textarea
                                                className="w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-teal-500 focus:ring-teal-500"
                                                rows={3} value={answers[q.id] || ''}
                                                onChange={e => setAnswers(prev => ({ ...prev, [q.id]: e.target.value }))}
                                                required={q.is_required}
                                            />
                                        )}

                                        {q.question_type === 'yes_no' && (
                                            <div className="flex items-center gap-4">
                                                <label className="flex items-center gap-2">
                                                    <input type="radio" name={`q_${q.id}`} value="1"
                                                        checked={answers[q.id] === true}
                                                        onChange={() => setAnswers(prev => ({ ...prev, [q.id]: true }))}
                                                        className="text-teal-600 focus:ring-teal-500" />
                                                    <span className="text-sm">Sim</span>
                                                </label>
                                                <label className="flex items-center gap-2">
                                                    <input type="radio" name={`q_${q.id}`} value="0"
                                                        checked={answers[q.id] === false}
                                                        onChange={() => setAnswers(prev => ({ ...prev, [q.id]: false }))}
                                                        className="text-teal-600 focus:ring-teal-500" />
                                                    <span className="text-sm">Nao</span>
                                                </label>
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>

                            {error && <p className="text-red-500 text-sm mt-4">{error}</p>}

                            <div className="mt-8">
                                <Button variant="primary" size="lg" type="submit" loading={submitting} className="w-full">
                                    Enviar Avaliação
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </>
    );
}
