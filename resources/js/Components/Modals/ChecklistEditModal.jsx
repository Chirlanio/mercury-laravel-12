import { useState, useEffect } from 'react';
import axios from 'axios';
import StandardModal from '@/Components/StandardModal';
import Button from '@/Components/Button';
import {
    ChevronDownIcon,
    ChevronUpIcon,
    CheckIcon,
    ClipboardDocumentCheckIcon,
    ArrowPathIcon,
} from '@heroicons/react/24/outline';

const ANSWER_OPTIONS = [
    { value: 'pending', label: 'Pendente', color: 'bg-gray-100 text-gray-800' },
    { value: 'compliant', label: 'Conforme', color: 'bg-green-100 text-green-800' },
    { value: 'partial', label: 'Parcial', color: 'bg-yellow-100 text-yellow-800' },
    { value: 'non_compliant', label: 'Não Conforme', color: 'bg-red-100 text-red-800' },
];

export default function ChecklistEditModal({ show, onClose, checklistId, onSuccess }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);
    const [expandedAreas, setExpandedAreas] = useState({});
    const [saving, setSaving] = useState({});
    const [savedAnswers, setSavedAnswers] = useState({});
    const [employees, setEmployees] = useState([]);
    const [answerForms, setAnswerForms] = useState({});

    useEffect(() => {
        if (show && checklistId) {
            loadData();
            loadEmployees();
        }
    }, [show, checklistId]);

    const loadData = () => {
        setLoading(true);
        axios.get(`/checklists/${checklistId}`)
            .then(({ data: json }) => {
                setData(json);
                const areas = {};
                json.answers_by_area?.forEach((_, i) => { areas[i] = true; });
                setExpandedAreas(areas);
                const forms = {};
                json.answers_by_area?.forEach((group) => {
                    group.answers?.forEach((answer) => {
                        forms[answer.id] = {
                            answer_status: answer.answer_status || 'pending',
                            justification: answer.justification || '',
                            action_plan: answer.action_plan || '',
                            responsible_employee_id: answer.responsible_employee?.id || '',
                            deadline_date: answer.deadline_date || '',
                        };
                    });
                });
                setAnswerForms(forms);
                setLoading(false);
            })
            .catch(() => setLoading(false));
    };

    const loadEmployees = () => {
        axios.get('/checklists/employees')
            .then(({ data: json }) => setEmployees(json))
            .catch(() => {});
    };

    const toggleArea = (index) => {
        setExpandedAreas((prev) => ({ ...prev, [index]: !prev[index] }));
    };

    const updateFormField = (answerId, field, value) => {
        setAnswerForms((prev) => ({
            ...prev,
            [answerId]: { ...prev[answerId], [field]: value },
        }));
        // Clear saved status when field changes
        setSavedAnswers((prev) => ({ ...prev, [answerId]: false }));
    };

    const saveAnswer = async (answerId) => {
        const form = answerForms[answerId];
        if (!form) return;

        setSaving((prev) => ({ ...prev, [answerId]: true }));

        try {
            const { data: result } = await axios.put(
                `/checklists/${checklistId}/answers/${answerId}`,
                form
            );

            setSavedAnswers((prev) => ({ ...prev, [answerId]: true }));

            if (result.checklist) {
                setData((prev) => prev ? {
                    ...prev,
                    checklist: { ...prev.checklist, ...result.checklist },
                } : prev);
            }
        } catch (err) {
            // Silent error handling
        } finally {
            setSaving((prev) => ({ ...prev, [answerId]: false }));
        }
    };

    const handleClose = () => {
        setData(null);
        setAnswerForms({});
        setSavedAnswers({});
        onClose();
    };

    const handleDone = () => {
        handleClose();
        onSuccess?.();
    };

    const checklist = data?.checklist;
    const stats = data?.statistics;
    const progressText = stats ? `${stats.answered}/${stats.total_questions}` : '';
    const progressPct = stats ? (stats.answered / stats.total_questions) * 100 : 0;

    return (
        <StandardModal
            show={show}
            onClose={handleClose}
            loading={loading}
            title="Responder Checklist"
            subtitle={checklist ? `${checklist.store?.name}` : ''}
            headerColor="bg-blue-600"
            headerIcon={<ClipboardDocumentCheckIcon className="h-6 w-6" />}
            maxWidth="5xl"
            headerActions={
                <div className="flex items-center gap-3 bg-white/10 px-3 py-1.5 rounded-lg border border-white/20">
                    <div className="text-right hidden sm:block">
                        <p className="text-[10px] font-bold text-white/70 uppercase">Progresso</p>
                        <p className="text-xs font-bold text-white">{progressText} perguntas</p>
                    </div>
                    <div className="w-20 bg-white/20 rounded-full h-2">
                        <div 
                            className="bg-white h-2 rounded-full transition-all duration-500" 
                            style={{ width: `${progressPct}%` }}
                        />
                    </div>
                </div>
            }
            footer={
                <StandardModal.Footer onCancel={handleClose}>
                    <Button variant="primary" onClick={handleDone}>
                        Concluir e Salvar
                    </Button>
                </StandardModal.Footer>
            }
        >
            {data && (
                <div className="space-y-4">
                    {data.answers_by_area?.map((group, areaIndex) => (
                        <div key={areaIndex} className="border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                            <button
                                onClick={() => toggleArea(areaIndex)}
                                className="w-full flex items-center justify-between p-4 bg-gray-50 hover:bg-gray-100 transition"
                            >
                                <div className="flex items-center gap-2">
                                    <span className="font-bold text-gray-900">{group.area?.name}</span>
                                    <span className="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-700 uppercase">
                                        {group.answers?.length || 0} Itens
                                    </span>
                                </div>
                                <div className="flex items-center gap-3">
                                    <span className="text-xs font-semibold text-gray-500">
                                        {group.answers?.filter(a => answerForms[a.id]?.answer_status !== 'pending').length || 0} de {group.answers?.length || 0} respondidos
                                    </span>
                                    {expandedAreas[areaIndex]
                                        ? <ChevronUpIcon className="h-5 w-5 text-gray-400" />
                                        : <ChevronDownIcon className="h-5 w-5 text-gray-400" />
                                    }
                                </div>
                            </button>
                            {expandedAreas[areaIndex] && (
                                <div className="divide-y divide-gray-100 bg-white">
                                    {group.answers?.map((answer) => (
                                        <AnswerForm
                                            key={answer.id}
                                            answer={answer}
                                            form={answerForms[answer.id] || {}}
                                            employees={employees}
                                            isSaving={saving[answer.id]}
                                            isSaved={savedAnswers[answer.id]}
                                            onFieldChange={(field, value) => updateFormField(answer.id, field, value)}
                                            onSave={() => saveAnswer(answer.id)}
                                        />
                                    ))}
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            )}
        </StandardModal>
    );
}

function AnswerForm({ answer, form, employees, isSaving, isSaved, onFieldChange, onSave }) {
    const isAnswered = form.answer_status && form.answer_status !== 'pending';

    return (
        <div className={`p-5 transition-colors ${isSaved ? 'bg-green-50/30' : ''}`}>
            {/* Question */}
            <div className="flex flex-col md:flex-row md:items-start justify-between gap-4">
                <div className="flex-1">
                    <p className="text-sm font-medium text-gray-800 leading-relaxed">
                        {answer.question?.description}
                    </p>
                    <div className="mt-1 flex items-center gap-3">
                        <span className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">
                            Peso: {answer.question?.points} pt{answer.question?.points !== 1 ? 's' : ''}
                        </span>
                        {isSaved && (
                            <span className="flex items-center text-[10px] font-bold text-green-600 uppercase">
                                <CheckIcon className="h-3 w-3 mr-0.5" /> Salvo
                            </span>
                        )}
                    </div>
                </div>

                {/* Status select + Save button */}
                <div className="flex items-center gap-2 self-start shrink-0">
                    <select
                        value={form.answer_status || 'pending'}
                        onChange={(e) => onFieldChange('answer_status', e.target.value)}
                        className={`text-xs font-bold rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-offset-1 focus:ring-indigo-500 py-1.5 pr-8 pl-3 ${
                            ANSWER_OPTIONS.find(o => o.value === form.answer_status)?.color || ''
                        }`}
                    >
                        {ANSWER_OPTIONS.map((opt) => (
                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                        ))}
                    </select>

                    <button
                        onClick={onSave}
                        disabled={isSaving}
                        className={`inline-flex items-center justify-center min-w-[90px] px-3 py-2 text-xs font-bold rounded-lg transition shadow-sm ${
                            isSaved
                                ? 'bg-green-600 text-white hover:bg-green-700'
                                : 'bg-indigo-600 text-white hover:bg-indigo-700'
                        } disabled:opacity-50`}
                    >
                        {isSaving ? (
                            <ArrowPathIcon className="animate-spin h-3 w-3 mr-1.5" />
                        ) : isSaved ? (
                            <CheckIcon className="h-3 w-3 mr-1.5" />
                        ) : null}
                        {isSaving ? 'Salvando' : isSaved ? 'Salvo' : 'Salvar'}
                    </button>
                </div>
            </div>

            {/* Detail fields (shown when answer is not pending) */}
            {isAnswered && (
                <div className="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4 pl-4 border-l-2 border-indigo-100">
                    <div>
                        <label className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Justificativa</label>
                        <textarea
                            value={form.justification || ''}
                            onChange={(e) => onFieldChange('justification', e.target.value)}
                            rows={2}
                            className="w-full rounded-lg border-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm placeholder:text-gray-300"
                            placeholder="Descreva o motivo desta avaliação..."
                        />
                    </div>
                    <div>
                        <label className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Plano de Ação</label>
                        <textarea
                            value={form.action_plan || ''}
                            onChange={(e) => onFieldChange('action_plan', e.target.value)}
                            rows={2}
                            className="w-full rounded-lg border-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm placeholder:text-gray-300"
                            placeholder="Quais ações serão tomadas?"
                        />
                    </div>
                    <div>
                        <label className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Responsável</label>
                        <select
                            value={form.responsible_employee_id || ''}
                            onChange={(e) => onFieldChange('responsible_employee_id', e.target.value || null)}
                            className="w-full rounded-lg border-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        >
                            <option value="">Selecionar responsável...</option>
                            {employees.map((emp) => (
                                <option key={emp.id} value={emp.id}>{emp.name}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Prazo para Ajuste</label>
                        <input
                            type="date"
                            value={form.deadline_date || ''}
                            onChange={(e) => onFieldChange('deadline_date', e.target.value || null)}
                            className="w-full rounded-lg border-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        />
                    </div>
                </div>
            )}
        </div>
    );
}
