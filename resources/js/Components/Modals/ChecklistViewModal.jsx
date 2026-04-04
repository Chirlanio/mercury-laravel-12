import { Fragment, useState, useEffect } from 'react';
import { Dialog, Transition } from '@headlessui/react';
import axios from 'axios';
import {
    XMarkIcon,
    ChevronDownIcon,
    ChevronUpIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    MinusCircleIcon,
    ClockIcon,
} from '@heroicons/react/24/outline';

const STATUS_LABELS = {
    pending: 'Pendente',
    in_progress: 'Em Andamento',
    completed: 'Concluído',
};

const ANSWER_STATUS_CONFIG = {
    pending: { label: 'Pendente', icon: ClockIcon, color: 'text-gray-500', bg: 'bg-gray-100' },
    compliant: { label: 'Conforme', icon: CheckCircleIcon, color: 'text-green-600', bg: 'bg-green-100' },
    partial: { label: 'Parcial', icon: ExclamationTriangleIcon, color: 'text-yellow-600', bg: 'bg-yellow-100' },
    non_compliant: { label: 'Não Conforme', icon: MinusCircleIcon, color: 'text-red-600', bg: 'bg-red-100' },
};

const PERFORMANCE_STYLES = {
    green: 'bg-green-100 text-green-800 border-green-300',
    blue: 'bg-blue-100 text-blue-800 border-blue-300',
    yellow: 'bg-yellow-100 text-yellow-800 border-yellow-300',
    red: 'bg-red-100 text-red-800 border-red-300',
};

export default function ChecklistViewModal({ show, onClose, checklistId }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);
    const [expandedAreas, setExpandedAreas] = useState({});

    useEffect(() => {
        if (show && checklistId) {
            setLoading(true);
            axios.get(`/checklists/${checklistId}`)
                .then(({ data: json }) => {
                    setData(json);
                    const areas = {};
                    json.answers_by_area?.forEach((group, i) => { areas[i] = true; });
                    setExpandedAreas(areas);
                    setLoading(false);
                })
                .catch(() => setLoading(false));
        }
    }, [show, checklistId]);

    const toggleArea = (index) => {
        setExpandedAreas((prev) => ({ ...prev, [index]: !prev[index] }));
    };

    const handleClose = () => {
        setData(null);
        onClose();
    };

    const checklist = data?.checklist;
    const stats = data?.statistics;
    const performance = stats?.performance;

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
                            <Dialog.Panel className="w-full max-w-4xl transform overflow-hidden rounded-lg bg-white shadow-xl transition-all max-h-[90vh] flex flex-col">
                                {/* Header */}
                                <div className="flex items-center justify-between p-6 border-b">
                                    <Dialog.Title className="text-lg font-semibold text-gray-900">
                                        Detalhes do Checklist
                                    </Dialog.Title>
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
                                        <div className="space-y-6">
                                            {/* General Info */}
                                            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                                <InfoField label="Loja" value={checklist?.store?.name} />
                                                <InfoField label="Aplicador" value={checklist?.applicator?.name || '-'} />
                                                <InfoField label="Status" value={STATUS_LABELS[checklist?.status] || checklist?.status} />
                                                <InfoField label="Criado em" value={checklist?.created_at} />
                                                {checklist?.started_at && <InfoField label="Iniciado em" value={checklist.started_at} />}
                                                {checklist?.completed_at && <InfoField label="Concluído em" value={checklist.completed_at} />}
                                            </div>

                                            {/* Score Overview */}
                                            {stats && (
                                                <div className="bg-gray-50 rounded-lg p-4">
                                                    <div className="flex items-center justify-between mb-3">
                                                        <h3 className="font-medium text-gray-900">Resultado Geral</h3>
                                                        {performance && (
                                                            <span className={`px-3 py-1 rounded-full text-sm font-medium border ${PERFORMANCE_STYLES[performance.color] || ''}`}>
                                                                {performance.label}
                                                            </span>
                                                        )}
                                                    </div>

                                                    {/* Progress bar */}
                                                    <div className="mb-3">
                                                        <div className="flex justify-between text-sm text-gray-600 mb-1">
                                                            <span>{stats.obtained_score} / {stats.max_score} pontos</span>
                                                            <span className="font-medium">{stats.percentage}%</span>
                                                        </div>
                                                        <div className="w-full bg-gray-200 rounded-full h-3">
                                                            <div
                                                                className="bg-indigo-600 h-3 rounded-full transition-all"
                                                                style={{ width: `${Math.min(stats.percentage, 100)}%` }}
                                                            />
                                                        </div>
                                                    </div>

                                                    {/* Distribution */}
                                                    <div className="grid grid-cols-4 gap-2">
                                                        <MiniStat label="Conforme" value={stats.distribution?.compliant} color="green" />
                                                        <MiniStat label="Parcial" value={stats.distribution?.partial} color="yellow" />
                                                        <MiniStat label="Não Conforme" value={stats.distribution?.non_compliant} color="red" />
                                                        <MiniStat label="Pendente" value={stats.distribution?.pending} color="gray" />
                                                    </div>
                                                </div>
                                            )}

                                            {/* Per-Area Statistics */}
                                            {stats?.by_area?.length > 0 && (
                                                <div>
                                                    <h3 className="font-medium text-gray-900 mb-3">Resultado por Área</h3>
                                                    <div className="space-y-2">
                                                        {stats.by_area.map((area) => (
                                                            <div key={area.area_id} className="flex items-center gap-3">
                                                                <span className="text-sm text-gray-700 w-40 truncate">{area.area_name}</span>
                                                                <div className="flex-1 bg-gray-200 rounded-full h-2">
                                                                    <div
                                                                        className="bg-indigo-500 h-2 rounded-full"
                                                                        style={{ width: `${Math.min(area.percentage, 100)}%` }}
                                                                    />
                                                                </div>
                                                                <span className="text-sm font-medium text-gray-700 w-14 text-right">
                                                                    {area.percentage}%
                                                                </span>
                                                            </div>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}

                                            {/* Answers by Area */}
                                            {data.answers_by_area?.map((group, index) => (
                                                <div key={index} className="border rounded-lg overflow-hidden">
                                                    <button
                                                        onClick={() => toggleArea(index)}
                                                        className="w-full flex items-center justify-between p-4 bg-gray-50 hover:bg-gray-100 transition"
                                                    >
                                                        <span className="font-medium text-gray-900">{group.area?.name}</span>
                                                        {expandedAreas[index]
                                                            ? <ChevronUpIcon className="h-5 w-5 text-gray-500" />
                                                            : <ChevronDownIcon className="h-5 w-5 text-gray-500" />
                                                        }
                                                    </button>
                                                    {expandedAreas[index] && (
                                                        <div className="divide-y">
                                                            {group.answers?.map((answer) => {
                                                                const config = ANSWER_STATUS_CONFIG[answer.answer_status] || ANSWER_STATUS_CONFIG.pending;
                                                                const Icon = config.icon;
                                                                return (
                                                                    <div key={answer.id} className="p-4">
                                                                        <div className="flex items-start justify-between gap-4">
                                                                            <p className="text-sm text-gray-800 flex-1">{answer.question?.description}</p>
                                                                            <span className={`inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium ${config.bg} ${config.color}`}>
                                                                                <Icon className="h-3.5 w-3.5" />
                                                                                {config.label}
                                                                            </span>
                                                                        </div>
                                                                        {(answer.justification || answer.action_plan || answer.responsible_employee || answer.deadline_date) && (
                                                                            <div className="mt-2 grid grid-cols-1 md:grid-cols-2 gap-2 text-xs text-gray-600">
                                                                                {answer.justification && (
                                                                                    <div><span className="font-medium">Justificativa:</span> {answer.justification}</div>
                                                                                )}
                                                                                {answer.action_plan && (
                                                                                    <div><span className="font-medium">Plano de Ação:</span> {answer.action_plan}</div>
                                                                                )}
                                                                                {answer.responsible_employee && (
                                                                                    <div><span className="font-medium">Responsável:</span> {answer.responsible_employee.name}</div>
                                                                                )}
                                                                                {answer.deadline_date && (
                                                                                    <div><span className="font-medium">Prazo:</span> {new Date(answer.deadline_date).toLocaleDateString('pt-BR')}</div>
                                                                                )}
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                );
                                                            })}
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
                                <div className="flex justify-end p-4 border-t">
                                    <button
                                        onClick={handleClose}
                                        className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 transition"
                                    >
                                        Fechar
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

function InfoField({ label, value }) {
    return (
        <div>
            <p className="text-xs text-gray-500">{label}</p>
            <p className="text-sm font-medium text-gray-900">{value || '-'}</p>
        </div>
    );
}

function MiniStat({ label, value, color }) {
    const colorMap = {
        green: 'text-green-700',
        yellow: 'text-yellow-700',
        red: 'text-red-700',
        gray: 'text-gray-700',
    };
    return (
        <div className="text-center">
            <p className={`text-lg font-bold ${colorMap[color] || 'text-gray-700'}`}>{value || 0}</p>
            <p className="text-xs text-gray-500">{label}</p>
        </div>
    );
}
