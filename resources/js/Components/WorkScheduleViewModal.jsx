import { useState } from 'react';
import StandardModal from '@/Components/StandardModal';
import Button from '@/Components/Button';
import StatusBadge from '@/Components/Shared/StatusBadge';
import WorkScheduleDayOverrideModal from '@/Components/WorkScheduleDayOverrideModal';
import { useConfirm } from '@/Hooks/useConfirm';
import { formatDateTime } from '@/Utils/dateHelpers';
import { PencilSquareIcon, UserPlusIcon, PlusIcon, TrashIcon } from '@heroicons/react/24/outline';

export default function WorkScheduleViewModal({ show, onClose, schedule, onEdit, onAssign }) {
    const [isOverrideModalOpen, setIsOverrideModalOpen] = useState(false);
    const [selectedAssignment, setSelectedAssignment] = useState(null);
    const { confirm, ConfirmDialogComponent } = useConfirm();

    const handleUnassign = async (assignmentId) => {
        const confirmed = await confirm({
            title: 'Remover Funcionário',
            message: 'Tem certeza que deseja remover este funcionário da escala?',
            confirmText: 'Remover',
            type: 'danger',
        });
        if (!confirmed) return;
        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            await fetch(`/work-schedules/${schedule.id}/employees/${assignmentId}`, {
                method: 'DELETE',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token },
            });
            onClose();
        } catch (error) {
            console.error('Erro ao remover funcionário:', error);
        }
    };

    if (!schedule) return null;

    const headerBadges = [
        { text: schedule.is_active ? 'Ativa' : 'Inativa', className: schedule.is_active ? 'bg-emerald-500/20 text-white' : 'bg-red-500/20 text-white' },
        ...(schedule.is_default ? [{ text: 'Padrão', className: 'bg-white/20 text-white' }] : []),
    ];

    const footerContent = (
        <>
            {onEdit && <Button variant="warning" size="sm" icon={PencilSquareIcon} onClick={() => onEdit(schedule)}>Editar</Button>}
            {onAssign && <Button variant="primary" size="sm" icon={UserPlusIcon} onClick={() => onAssign(schedule)}>Atribuir Funcionário</Button>}
            <div className="flex-1" />
            <Button variant="outline" onClick={onClose}>Fechar</Button>
        </>
    );

    return (
        <>
            <StandardModal show={show} onClose={onClose}
                title={schedule.name} headerColor="bg-gray-700" headerBadges={headerBadges}
                footer={<StandardModal.Footer>{footerContent}</StandardModal.Footer>}>

                {/* Informações Gerais */}
                <StandardModal.Section title="Informações Gerais">
                    <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                        {schedule.description && <StandardModal.Field label="Descrição" value={schedule.description} />}
                        <StandardModal.Field label="Horas Semanais" value={schedule.weekly_hours} />
                        <StandardModal.Field label="Funcionários Atribuídos" value={schedule.employee_count || 0} />
                        <StandardModal.Field label="Criado por" value={schedule.created_by} />
                        <StandardModal.Field label="Criado em" value={formatDateTime(schedule.created_at)} />
                    </div>
                </StandardModal.Section>

                {/* Dias da Semana */}
                <StandardModal.Section title="Dias da Semana">
                    <div className="space-y-1 -mx-4 -mb-4 px-4 pb-4">
                        {schedule.days?.map((day) => (
                            <div key={day.day_of_week}
                                className={`flex items-center justify-between p-2 rounded ${day.is_work_day ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-500'}`}>
                                <span className="font-medium text-sm">{day.day_name}</span>
                                {day.is_work_day ? (
                                    <div className="text-xs flex items-center gap-2">
                                        <span>{day.entry_time?.substring(0, 5)} - {day.exit_time?.substring(0, 5)}</span>
                                        {day.break_start && (
                                            <span className="text-green-600">(intervalo: {day.break_start?.substring(0, 5)}-{day.break_end?.substring(0, 5)})</span>
                                        )}
                                        <span className="font-bold">{Number(day.daily_hours).toFixed(2).replace('.', ',')}h</span>
                                    </div>
                                ) : (
                                    <span className="text-xs italic">Folga</span>
                                )}
                            </div>
                        ))}
                    </div>
                </StandardModal.Section>

                {/* Funcionários Atribuídos */}
                {schedule.employees?.length > 0 && (
                    <StandardModal.Section title={`Funcionários Atribuídos (${schedule.employees.length})`}>
                        <div className="space-y-2 max-h-60 overflow-y-auto -mx-4 -mb-4 px-4 pb-4">
                            {schedule.employees.map((emp) => (
                                <div key={emp.id} className="flex items-center justify-between p-3 bg-white border border-gray-100 rounded-lg">
                                    <div>
                                        <span className="font-medium text-gray-900">{emp.employee_short_name || emp.employee_name}</span>
                                        <span className="ml-2 text-xs text-gray-500">{emp.position} - {emp.store}</span>
                                        <div className="text-xs text-gray-400">Desde {emp.effective_date}</div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {emp.overrides_count > 0 && (
                                            <StatusBadge variant="warning" size="sm">{emp.overrides_count} exceção(ões)</StatusBadge>
                                        )}
                                        <Button variant="outline" size="xs" iconOnly icon={PlusIcon} title="Adicionar exceção"
                                            onClick={() => { setSelectedAssignment(emp); setIsOverrideModalOpen(true); }} />
                                        <Button variant="danger" size="xs" iconOnly icon={TrashIcon} title="Remover"
                                            onClick={() => handleUnassign(emp.id)} />
                                    </div>
                                </div>
                            ))}
                        </div>
                    </StandardModal.Section>
                )}
            </StandardModal>

            <WorkScheduleDayOverrideModal
                isOpen={isOverrideModalOpen}
                onClose={() => { setIsOverrideModalOpen(false); setSelectedAssignment(null); }}
                onSuccess={() => { setIsOverrideModalOpen(false); setSelectedAssignment(null); }}
                assignment={selectedAssignment}
                scheduleDays={schedule?.days}
            />

            <ConfirmDialogComponent />
        </>
    );
}
