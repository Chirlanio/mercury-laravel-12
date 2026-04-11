import { useState, useEffect } from 'react';
import { ClipboardDocumentCheckIcon, StarIcon } from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';

const STATUS_VARIANTS = { completed: 'success', partial: 'warning', pending: 'gray' };

function ResponseDisplay({ question, response }) {
    if (!response) return <span className="text-gray-400">Nao respondida</span>;

    if (question.question_type === 'rating') {
        const rating = response.rating_value;
        return (
            <div className="flex items-center gap-1">
                {[1, 2, 3, 4, 5].map(n => (
                    <StarIcon key={n} className={`w-4 h-4 ${n <= rating ? 'text-yellow-400 fill-yellow-400' : 'text-gray-300'}`} />
                ))}
                <span className="text-sm text-gray-600 ml-1">{rating}/5</span>
            </div>
        );
    }

    if (question.question_type === 'yes_no') {
        return <StatusBadge variant={response.yes_no_value ? 'success' : 'danger'}>{response.yes_no_value ? 'Sim' : 'Nao'}</StatusBadge>;
    }

    return <p className="text-sm text-gray-700">{response.response_text || '-'}</p>;
}

export default function EvaluationDetailModal({ show, onClose, evaluationId, canFill }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        if (show && evaluationId) {
            setLoading(true);
            fetch(route('experience-tracker.show', evaluationId))
                .then(res => res.json())
                .then(result => { setData(result); setLoading(false); })
                .catch(() => setLoading(false));
        }
    }, [show, evaluationId]);

    const eval_ = data?.evaluation;

    return (
        <StandardModal
            show={show} onClose={onClose}
            title={eval_ ? `APE ${eval_.milestone_label} - ${eval_.employee?.name}` : 'Detalhes da Avaliacao'}
            headerColor="bg-teal-600" headerIcon={<ClipboardDocumentCheckIcon className="h-5 w-5" />}
            headerBadges={eval_ ? [
                { text: eval_.overall_status_label, className: 'bg-white/20 text-white' },
            ] : []}
            maxWidth="4xl" loading={loading}
            footer={<StandardModal.Footer onCancel={onClose} cancelLabel="Fechar" />}
        >
            {eval_ && data && (
                <>
                    <StandardModal.Section title="Informacoes">
                        <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <StandardModal.Field label="Colaborador" value={eval_.employee?.name || '-'} />
                            <StandardModal.Field label="Gestor" value={eval_.manager?.name || '-'} />
                            <StandardModal.Field label="Loja" value={eval_.store_name || eval_.store_id} />
                            <StandardModal.Field label="Admissao" value={eval_.date_admission} />
                            <StandardModal.Field label="Prazo" value={eval_.milestone_date} />
                            {eval_.recommendation && (
                                <StandardModal.Field label="Recomendacao"
                                    value={<StatusBadge variant={eval_.recommendation === 'yes' ? 'success' : 'danger'}>
                                        {eval_.recommendation === 'yes' ? 'Sim, efetivar' : 'Nao efetivar'}
                                    </StatusBadge>} />
                            )}
                        </div>
                    </StandardModal.Section>

                    {/* Manager Responses */}
                    <StandardModal.Section title={`Avaliacao do Gestor (${eval_.manager_status === 'completed' ? 'Concluida' : 'Pendente'})`}>
                        {eval_.manager_status === 'completed' ? (
                            <div className="space-y-3">
                                {data.managerQuestions?.map(q => (
                                    <div key={q.id} className="border-b border-gray-100 pb-3 last:border-0">
                                        <p className="text-xs font-medium text-gray-500 mb-1">{q.question_text}</p>
                                        <ResponseDisplay question={q} response={data.managerResponses?.[q.id]} />
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-gray-500">Aguardando resposta do gestor.</p>
                        )}
                    </StandardModal.Section>

                    {/* Employee Responses */}
                    <StandardModal.Section title={`Avaliacao do Colaborador (${eval_.employee_status === 'completed' ? 'Concluida' : 'Pendente'})`}>
                        {eval_.employee_status === 'completed' ? (
                            <div className="space-y-3">
                                {data.employeeQuestions?.map(q => (
                                    <div key={q.id} className="border-b border-gray-100 pb-3 last:border-0">
                                        <p className="text-xs font-medium text-gray-500 mb-1">{q.question_text}</p>
                                        <ResponseDisplay question={q} response={data.employeeResponses?.[q.id]} />
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div>
                                <p className="text-sm text-gray-500 mb-2">Aguardando resposta do colaborador.</p>
                                {eval_.employee_token && (
                                    <p className="text-xs text-gray-400">
                                        Link publico: <a href={route('experience-tracker.public-form', eval_.employee_token)}
                                            className="text-indigo-600 hover:underline break-all" target="_blank" rel="noopener">
                                            {route('experience-tracker.public-form', eval_.employee_token)}
                                        </a>
                                    </p>
                                )}
                            </div>
                        )}
                    </StandardModal.Section>

                    <StandardModal.Section title="Registro">
                        <div className="grid grid-cols-2 gap-4">
                            <StandardModal.MiniField label="Gestor respondeu em" value={eval_.manager_completed_at || '-'} />
                            <StandardModal.MiniField label="Colaborador respondeu em" value={eval_.employee_completed_at || '-'} />
                            <StandardModal.MiniField label="Criado em" value={eval_.created_at} />
                        </div>
                    </StandardModal.Section>
                </>
            )}
        </StandardModal>
    );
}
