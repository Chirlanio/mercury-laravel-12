import { useState, useEffect } from 'react';
import axios from 'axios';
import { formatDateTime } from '@/Utils/dateHelpers';
import StandardModal from '@/Components/StandardModal';
import {
    ChevronDownIcon,
    ChevronUpIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    MinusCircleIcon,
    ClockIcon,
    ClipboardDocumentCheckIcon,
    CalendarIcon,
    BuildingStorefrontIcon,
    UserIcon,
} from '@heroicons/react/24/outline';

const STATUS_LABELS = {
    pending: 'Pendente',
    in_progress: 'Em Andamento',
    completed: 'Concluído',
};

const ANSWER_STATUS_CONFIG = {
    pending: { label: 'Pendente', icon: ClockIcon, color: 'text-gray-500', bg: 'bg-gray-100', badge: 'gray' },
    compliant: { label: 'Conforme', icon: CheckCircleIcon, color: 'text-green-600', bg: 'bg-green-100', badge: 'green' },
    partial: { label: 'Parcial', icon: ExclamationTriangleIcon, color: 'text-yellow-600', bg: 'bg-yellow-100', badge: 'yellow' },
    non_compliant: { label: 'Não Conforme', icon: MinusCircleIcon, color: 'text-red-600', bg: 'bg-red-100', badge: 'red' },
};

const PERFORMANCE_BADGES = {
    green: 'green',
    blue: 'blue',
    yellow: 'yellow',
    red: 'red',
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
        <StandardModal
            show={show}
            onClose={handleClose}
            loading={loading}
            title="Detalhes do Checklist"
            subtitle={checklist ? `${checklist.store?.name} — ${STATUS_LABELS[checklist.status]}` : ''}
            headerColor={checklist?.status === 'completed' ? 'bg-green-600' : 'bg-gray-800'}
            headerIcon={<ClipboardDocumentCheckIcon className="h-6 w-6" />}
            maxWidth="5xl"
            headerBadges={performance ? [{ text: performance.label, className: `bg-white/20 text-white font-bold` }] : []}
            footer={<StandardModal.Footer onCancel={handleClose} submitLabel="Fechar" onSubmit={handleClose} />}
        >
            {data && (
                <div className="space-y-6">
                    {/* General Info */}
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <StandardModal.InfoCard label="Loja" value={checklist?.store?.name} icon={<BuildingStorefrontIcon className="h-4 w-4" />} />
                        <StandardModal.InfoCard label="Aplicador" value={checklist?.applicator?.name || '-'} icon={<UserIcon className="h-4 w-4" />} />
                        <StandardModal.InfoCard label="Criado em" value={formatDateTime(checklist?.created_at)} icon={<CalendarIcon className="h-4 w-4" />} />
                        <StandardModal.InfoCard 
                            label="Conformidade" 
                            value={stats ? `${stats.percentage}%` : '-'} 
                            highlight 
                            colorClass={performance ? `bg-${performance.color}-50` : 'bg-indigo-50'}
                        />
                    </div>

                    {/* Timeline/Dates */}
                    <StandardModal.Section title="Datas de Execução" icon={<ClockIcon className="h-4 w-4" />}>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <StandardModal.Field label="Iniciado em" value={checklist?.started_at ? formatDateTime(checklist.started_at) : 'Não iniciado'} />
                            <StandardModal.Field label="Concluído em" value={checklist?.completed_at ? formatDateTime(checklist.completed_at) : 'Em andamento'} />
                        </div>
                    </StandardModal.Section>

                    {/* Statistics Overview */}
                    {stats && (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <StandardModal.Section title="Distribuição de Respostas" icon={<CheckCircleIcon className="h-4 w-4" />}>
                                <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                    <StandardModal.MiniField label="Conforme" value={stats.distribution?.compliant} />
                                    <StandardModal.MiniField label="Parcial" value={stats.distribution?.partial} />
                                    <StandardModal.MiniField label="Não Conforme" value={stats.distribution?.non_compliant} />
                                    <StandardModal.MiniField label="Pendente" value={stats.distribution?.pending} />
                                </div>
                            </StandardModal.Section>

                            <StandardModal.Section title="Pontuação" icon={<ClipboardDocumentCheckIcon className="h-4 w-4" />}>
                                <div className="space-y-2">
                                    <div className="flex justify-between text-xs font-semibold text-gray-500 uppercase">
                                        <span>Progresso</span>
                                        <span>{stats.obtained_score} / {stats.max_score} pts</span>
                                    </div>
                                    <div className="w-full bg-gray-200 rounded-full h-2.5">
                                        <div
                                            className="bg-indigo-600 h-2.5 rounded-full transition-all"
                                            style={{ width: `${Math.min(stats.percentage, 100)}%` }}
                                        />
                                    </div>
                                </div>
                            </StandardModal.Section>
                        </div>
                    )}

                    {/* Per-Area Statistics Bar Chart-like view */}
                    {stats?.by_area?.length > 0 && (
                        <StandardModal.Section title="Desempenho por Área" icon={<ExclamationTriangleIcon className="h-4 w-4" />}>
                            <div className="space-y-3">
                                {stats.by_area.map((area) => (
                                    <div key={area.area_id} className="flex items-center gap-4">
                                        <span className="text-sm font-medium text-gray-700 w-48 truncate">{area.area_name}</span>
                                        <div className="flex-1 bg-gray-100 rounded-full h-2 overflow-hidden">
                                            <div
                                                className={`h-2 rounded-full ${area.percentage >= 90 ? 'bg-green-500' : area.percentage >= 75 ? 'bg-blue-500' : area.percentage >= 50 ? 'bg-yellow-500' : 'bg-red-500'}`}
                                                style={{ width: `${Math.min(area.percentage, 100)}%` }}
                                            />
                                        </div>
                                        <span className="text-sm font-bold text-gray-900 w-12 text-right">
                                            {area.percentage}%
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </StandardModal.Section>
                    )}

                    {/* Answers by Area */}
                    <div className="space-y-4">
                        <h3 className="text-sm font-bold text-gray-700 uppercase tracking-wider px-1">Detalhamento das Respostas</h3>
                        {data.answers_by_area?.map((group, index) => (
                            <div key={index} className="border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                                <button
                                    onClick={() => toggleArea(index)}
                                    className="w-full flex items-center justify-between p-4 bg-white hover:bg-gray-50 transition"
                                >
                                    <div className="flex items-center gap-3">
                                        <div className="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center">
                                            <ClipboardDocumentCheckIcon className="h-5 w-5 text-indigo-600" />
                                        </div>
                                        <span className="font-bold text-gray-900">{group.area?.name}</span>
                                    </div>
                                    <div className="flex items-center gap-4">
                                        <div className="hidden sm:flex items-center gap-2">
                                            <div className="w-24 bg-gray-200 rounded-full h-1.5">
                                                <div 
                                                    className="bg-indigo-600 h-1.5 rounded-full" 
                                                    style={{ width: `${group.answers?.length > 0 ? (group.answers.filter(a => a.answer_status !== 'pending').length / group.answers.length) * 100 : 0}%` }}
                                                />
                                            </div>
                                            <span className="text-xs text-gray-500 font-medium">
                                                {group.answers?.filter(a => a.answer_status !== 'pending').length}/{group.answers?.length}
                                            </span>
                                        </div>
                                        {expandedAreas[index]
                                            ? <ChevronUpIcon className="h-5 w-5 text-gray-400" />
                                            : <ChevronDownIcon className="h-5 w-5 text-gray-400" />
                                        }
                                    </div>
                                </button>
                                {expandedAreas[index] && (
                                    <div className="divide-y border-t border-gray-100 bg-white">
                                        {group.answers?.map((answer) => {
                                            const config = ANSWER_STATUS_CONFIG[answer.answer_status] || ANSWER_STATUS_CONFIG.pending;
                                            const Icon = config.icon;
                                            return (
                                                <div key={answer.id} className="p-4 hover:bg-gray-50/50 transition">
                                                    <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                                                        <div className="flex-1">
                                                            <p className="text-sm font-medium text-gray-800 leading-relaxed">{answer.question?.description}</p>
                                                            <p className="text-[10px] text-gray-400 mt-1 uppercase font-semibold">Peso: {answer.question?.points} pt{answer.question?.points !== 1 ? 's' : ''}</p>
                                                        </div>
                                                        <div className={`inline-flex items-center self-start gap-1.5 px-3 py-1 rounded-full text-xs font-bold ring-1 ring-inset ${config.bg} ${config.color} ring-current/20`}>
                                                            <Icon className="h-3.5 w-3.5" />
                                                            {config.label}
                                                        </div>
                                                    </div>
                                                    
                                                    {(answer.justification || answer.action_plan || answer.responsible_employee || answer.deadline_date) && (
                                                        <div className="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4 p-3 bg-gray-50 rounded-lg border border-gray-100">
                                                            {answer.justification && (
                                                                <StandardModal.MiniField label="Justificativa" value={answer.justification} />
                                                            )}
                                                            {answer.action_plan && (
                                                                <StandardModal.MiniField label="Plano de Ação" value={answer.action_plan} />
                                                            )}
                                                            {answer.responsible_employee && (
                                                                <StandardModal.MiniField label="Responsável" value={answer.responsible_employee.name} />
                                                            )}
                                                            {answer.deadline_date && (
                                                                <StandardModal.MiniField label="Prazo" value={new Date(answer.deadline_date).toLocaleDateString('pt-BR')} />
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
                </div>
            )}
        </StandardModal>
    );
}
