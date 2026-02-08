import { useState } from 'react';
import Modal from '@/Components/Modal';
import Button from '@/Components/Button';
import WorkScheduleDayOverrideModal from '@/Components/WorkScheduleDayOverrideModal';

const DAY_NAMES = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];

export default function WorkScheduleViewModal({ isOpen, onClose, schedule, onEdit, onAssign }) {
    const [isOverrideModalOpen, setIsOverrideModalOpen] = useState(false);
    const [selectedAssignment, setSelectedAssignment] = useState(null);

    const handleUnassign = async (assignmentId) => {
        if (!confirm('Tem certeza que deseja remover este funcionário da escala?')) return;

        try {
            await axios.delete(`/work-schedules/${schedule.id}/employees/${assignmentId}`);
            onClose();
        } catch (error) {
            console.error('Erro ao remover funcionário:', error);
        }
    };

    const handleOverrideCreated = () => {
        setIsOverrideModalOpen(false);
        setSelectedAssignment(null);
    };

    if (!isOpen || !schedule) return null;

    return (
        <Modal show={isOpen} onClose={onClose} title={`Escala: ${schedule.name}`} maxWidth="85vw">
            <div className="space-y-6">
                {/* Header com badges */}
                <div className="flex items-center gap-3">
                    <h3 className="text-xl font-bold text-gray-900">{schedule.name}</h3>
                    {schedule.is_active && (
                        <span className="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Ativa</span>
                    )}
                    {!schedule.is_active && (
                        <span className="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Inativa</span>
                    )}
                    {schedule.is_default && (
                        <span className="px-2 py-1 text-xs font-semibold rounded-full bg-indigo-100 text-indigo-800">Padrão</span>
                    )}
                </div>

                {/* Informações */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="bg-gray-50 p-4 rounded-lg">
                        <h4 className="text-sm font-medium text-gray-900 mb-3">Informações Gerais</h4>
                        <div className="space-y-2 text-sm">
                            {schedule.description && (
                                <div>
                                    <span className="font-medium text-gray-600">Descrição:</span>
                                    <span className="ml-2 text-gray-900">{schedule.description}</span>
                                </div>
                            )}
                            <div>
                                <span className="font-medium text-gray-600">Horas Semanais:</span>
                                <span className="ml-2 text-gray-900 font-bold">{schedule.weekly_hours}</span>
                            </div>
                            <div>
                                <span className="font-medium text-gray-600">Funcionários Atribuídos:</span>
                                <span className="ml-2 text-gray-900">{schedule.employee_count || 0}</span>
                            </div>
                            <div>
                                <span className="font-medium text-gray-600">Criado por:</span>
                                <span className="ml-2 text-gray-900">{schedule.created_by}</span>
                            </div>
                            <div>
                                <span className="font-medium text-gray-600">Criado em:</span>
                                <span className="ml-2 text-gray-900">{schedule.created_at}</span>
                            </div>
                        </div>
                    </div>

                    {/* Grid visual de dias */}
                    <div className="bg-gray-50 p-4 rounded-lg">
                        <h4 className="text-sm font-medium text-gray-900 mb-3">Dias da Semana</h4>
                        <div className="space-y-1">
                            {schedule.days?.map((day) => (
                                <div
                                    key={day.day_of_week}
                                    className={`flex items-center justify-between p-2 rounded ${
                                        day.is_work_day ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-500'
                                    }`}
                                >
                                    <span className="font-medium text-sm">{day.day_name}</span>
                                    {day.is_work_day ? (
                                        <div className="text-xs">
                                            <span>{day.entry_time?.substring(0, 5)} - {day.exit_time?.substring(0, 5)}</span>
                                            {day.break_start && (
                                                <span className="ml-2 text-green-600">
                                                    (intervalo: {day.break_start?.substring(0, 5)}-{day.break_end?.substring(0, 5)})
                                                </span>
                                            )}
                                            <span className="ml-2 font-bold">{Number(day.daily_hours).toFixed(2).replace('.', ',')}h</span>
                                        </div>
                                    ) : (
                                        <span className="text-xs italic">Folga</span>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Lista de funcionários atribuídos */}
                {schedule.employees && schedule.employees.length > 0 && (
                    <div>
                        <h4 className="text-sm font-semibold text-gray-900 mb-3">
                            Funcionários Atribuídos ({schedule.employees.length})
                        </h4>
                        <div className="space-y-2 max-h-60 overflow-y-auto">
                            {schedule.employees.map((emp) => (
                                <div key={emp.id} className="flex items-center justify-between p-3 bg-white border rounded-lg">
                                    <div>
                                        <span className="font-medium text-gray-900">{emp.employee_short_name || emp.employee_name}</span>
                                        <span className="ml-2 text-xs text-gray-500">{emp.position} - {emp.store}</span>
                                        <div className="text-xs text-gray-400">Desde {emp.effective_date}</div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {emp.overrides_count > 0 && (
                                            <span className="px-2 py-0.5 text-xs font-medium rounded-full bg-yellow-100 text-yellow-800">
                                                {emp.overrides_count} exceção(ões)
                                            </span>
                                        )}
                                        <button
                                            onClick={() => { setSelectedAssignment(emp); setIsOverrideModalOpen(true); }}
                                            className="p-1 text-indigo-600 hover:bg-indigo-50 rounded"
                                            title="Adicionar exceção"
                                        >
                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                            </svg>
                                        </button>
                                        <button
                                            onClick={() => handleUnassign(emp.id)}
                                            className="p-1 text-red-600 hover:bg-red-50 rounded"
                                            title="Remover"
                                        >
                                            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Ações */}
                <div className="flex justify-between pt-4 border-t">
                    <div className="flex gap-2">
                        {onEdit && (
                            <Button variant="warning" size="sm" onClick={() => onEdit(schedule)}>
                                Editar
                            </Button>
                        )}
                        {onAssign && (
                            <Button variant="primary" size="sm" onClick={() => onAssign(schedule)}>
                                Atribuir Funcionário
                            </Button>
                        )}
                    </div>
                    <Button variant="outline" onClick={onClose}>
                        Fechar
                    </Button>
                </div>
            </div>

            <WorkScheduleDayOverrideModal
                isOpen={isOverrideModalOpen}
                onClose={() => { setIsOverrideModalOpen(false); setSelectedAssignment(null); }}
                onSuccess={handleOverrideCreated}
                assignment={selectedAssignment}
                scheduleDays={schedule?.days}
            />
        </Modal>
    );
}
