import { Fragment, useState, useEffect } from 'react';
import { Dialog, Transition } from '@headlessui/react';
import axios from 'axios';
import {
    XMarkIcon,
    ChevronDownIcon,
    ChevronUpIcon,
    CheckIcon,
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
    const progress = stats ? `${stats.answered}/${stats.total_questions}` : '';

    return (
        <Transition appear show={show} as={Fragment}>
            <Dialog as="div" className="relative z-50" onClose={handleClose}>
                <Transition.Child
                    as={Fragment}
                    enter="ease-out duration-300"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-200"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="fixed inset-0 bg-black bg-opacity-25" />
                </Transition.Child>

                <div className="fixed inset-0 overflow-y-auto">
                    <div className="flex min-h-full items-center justify-center p-4">
                        <Transition.Child
                            as={Fragment}
                            enter="ease-out duration-300"
                            enterFrom="opacity-0 scale-95"
                            enterTo="opacity-100 scale-100"
                            leave="ease-in duration-200"
                            leaveFrom="opacity-100 scale-100"
                            leaveTo="opacity-0 scale-95"
                        >
                            <Dialog.Panel className="w-full max-w-5xl transform overflow-hidden rounded-lg bg-white shadow-xl transition-all max-h-[90vh] flex flex-col">
                                {/* Header */}
                                <div className="flex items-center justify-between p-6 border-b">
                                    <div>
                                        <Dialog.Title className="text-lg font-semibold text-gray-900">
                                            Responder Checklist
                                        </Dialog.Title>
                                        {checklist && (
                                            <p className="text-sm text-gray-500 mt-1">
                                                {checklist.store?.name} — Progresso: {progress}
                                            </p>
                                        )}
                                    </div>
                                    <button onClick={handleClose} className="text-gray-400 hover:text-gray-600">
                                        <XMarkIcon className="h-5 w-5" />
                                    </button>
                                </div>

                                {/* Content */}
                                <div className="flex-1 overflow-y-auto p-6">
                                    {loading ? (
                                        <div className="flex items-center justify-center py-12">
                                            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600" />
                                        </div>
                                    ) : data ? (
                                        <div className="space-y-4">
                                            {data.answers_by_area?.map((group, areaIndex) => (
                                                <div key={areaIndex} className="border rounded-lg overflow-hidden">
                                                    <button
                                                        onClick={() => toggleArea(areaIndex)}
                                                        className="w-full flex items-center justify-between p-4 bg-gray-50 hover:bg-gray-100 transition"
                                                    >
                                                        <span className="font-medium text-gray-900">{group.area?.name}</span>
                                                        <div className="flex items-center gap-2">
                                                            <span className="text-xs text-gray-500">
                                                                {group.answers?.filter(a => answerForms[a.id]?.answer_status !== 'pending').length || 0}/{group.answers?.length || 0}
                                                            </span>
                                                            {expandedAreas[areaIndex]
                                                                ? <ChevronUpIcon className="h-5 w-5 text-gray-500" />
                                                                : <ChevronDownIcon className="h-5 w-5 text-gray-500" />
                                                            }
                                                        </div>
                                                    </button>
                                                    {expandedAreas[areaIndex] && (
                                                        <div className="divide-y">
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
                                    ) : (
                                        <p className="text-center text-gray-500 py-12">Dados não encontrados.</p>
                                    )}
                                </div>

                                {/* Footer */}
                                <div className="flex justify-end gap-3 p-4 border-t">
                                    <button
                                        onClick={handleClose}
                                        className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition"
                                    >
                                        Cancelar
                                    </button>
                                    <button
                                        onClick={handleDone}
                                        className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 transition"
                                    >
                                        Concluir
                                    </button>
                                </div>
                            </Dialog.Panel>
                        </Transition.Child>
                    </div>
                </div>
            </Dialog>
        </Transition>
    );
}

function AnswerForm({ answer, form, employees, isSaving, isSaved, onFieldChange, onSave }) {
    const showDetails = form.answer_status && form.answer_status !== 'pending';

    return (
        <div className="p-4 space-y-3">
            {/* Question */}
            <div className="flex items-start justify-between gap-4">
                <p className="text-sm text-gray-800 flex-1">
                    {answer.question?.description}
                    <span className="ml-1 text-xs text-gray-400">({answer.question?.points} pt{answer.question?.points !== 1 ? 's' : ''})</span>
                </p>
            </div>

            {/* Status select + Save button */}
            <div className="flex items-center gap-3">
                <select
                    value={form.answer_status || 'pending'}
                    onChange={(e) => onFieldChange('answer_status', e.target.value)}
                    className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                >
                    {ANSWER_OPTIONS.map((opt) => (
                        <option key={opt.value} value={opt.value}>{opt.label}</option>
                    ))}
                </select>

                <button
                    onClick={onSave}
                    disabled={isSaving}
                    className={`inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md transition ${
                        isSaved
                            ? 'bg-green-100 text-green-700'
                            : 'bg-indigo-600 text-white hover:bg-indigo-700'
                    } disabled:opacity-50`}
                >
                    {isSaving ? (
                        <div className="animate-spin rounded-full h-3 w-3 border-b-2 border-current mr-1" />
                    ) : isSaved ? (
                        <CheckIcon className="h-3 w-3 mr-1" />
                    ) : null}
                    {isSaving ? 'Salvando...' : isSaved ? 'Salvo' : 'Salvar'}
                </button>
            </div>

            {/* Detail fields (shown when answer is not pending) */}
            {showDetails && (
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3 pl-0 md:pl-4 border-l-2 border-gray-200 ml-0 md:ml-2">
                    <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1">Justificativa</label>
                        <textarea
                            value={form.justification || ''}
                            onChange={(e) => onFieldChange('justification', e.target.value)}
                            rows={2}
                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            placeholder="Descreva a justificativa..."
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1">Plano de Ação</label>
                        <textarea
                            value={form.action_plan || ''}
                            onChange={(e) => onFieldChange('action_plan', e.target.value)}
                            rows={2}
                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            placeholder="Descreva o plano de ação..."
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1">Responsável</label>
                        <select
                            value={form.responsible_employee_id || ''}
                            onChange={(e) => onFieldChange('responsible_employee_id', e.target.value || null)}
                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        >
                            <option value="">Selecionar responsável...</option>
                            {employees.map((emp) => (
                                <option key={emp.id} value={emp.id}>{emp.name}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="block text-xs font-medium text-gray-600 mb-1">Prazo</label>
                        <input
                            type="date"
                            value={form.deadline_date || ''}
                            onChange={(e) => onFieldChange('deadline_date', e.target.value || null)}
                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        />
                    </div>
                </div>
            )}
        </div>
    );
}
