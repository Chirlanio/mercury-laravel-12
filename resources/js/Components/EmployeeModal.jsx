import { useEffect, useState } from 'react';
import StandardModal from '@/Components/StandardModal';
import EmployeeAvatar from '@/Components/EmployeeAvatar';
import EmployeeHistoryModal from '@/Components/EmployeeHistoryModal';
import EmployeeScheduleManageModal from '@/Components/EmployeeScheduleManageModal';
import WorkScheduleDayOverrideModal from '@/Components/WorkScheduleDayOverrideModal';
import Button from '@/Components/Button';
import StatusBadge from '@/Components/Shared/StatusBadge';
import { useConfirm } from '@/Hooks/useConfirm';
import { formatDate } from '@/Utils/dateHelpers';
import {
    ClockIcon,
    PencilSquareIcon,
    DocumentTextIcon,
    TrashIcon,
    PlusIcon,
    ArrowsRightLeftIcon,
    ExclamationTriangleIcon,
} from '@heroicons/react/24/outline';

const STATUS_VARIANT_MAP = {
    'Ativo': 'success',
    'Férias': 'info',
    'Licença': 'warning',
    'Inativo': 'danger',
    'Pendente': 'gray',
};

export default function EmployeeModal({ show, onClose, employeeId, onEdit, positions, stores }) {
    const [employee, setEmployee] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [isHistoryModalOpen, setIsHistoryModalOpen] = useState(false);
    const [currentSchedule, setCurrentSchedule] = useState(null);
    const [isScheduleManageOpen, setIsScheduleManageOpen] = useState(false);
    const [isOverrideModalOpen, setIsOverrideModalOpen] = useState(false);
    const { confirm, ConfirmDialogComponent } = useConfirm();

    useEffect(() => {
        if (show && employeeId) {
            fetchEmployee();
            fetchCurrentSchedule();
        } else if (!show) {
            setEmployee(null);
            setCurrentSchedule(null);
            setError(null);
            setIsScheduleManageOpen(false);
            setIsOverrideModalOpen(false);
        }
    }, [show, employeeId]);

    const fetchEmployee = async () => {
        setLoading(true);
        setError(null);

        try {
            const response = await fetch(`/employees/${employeeId}`);
            if (!response.ok) throw new Error('Erro ao carregar funcionário');
            const data = await response.json();
            setEmployee(data.employee);
        } catch (err) {
            setError('Erro ao carregar informações do funcionário');
            console.error('Erro ao buscar funcionário:', err);
        } finally {
            setLoading(false);
        }
    };

    const fetchCurrentSchedule = async () => {
        try {
            const response = await fetch(`/employees/${employeeId}/work-schedule`);
            if (response.ok) {
                const data = await response.json();
                const current = data.assignments?.find(a => a.is_current);
                setCurrentSchedule(current || null);
            }
        } catch (err) {
            console.error('Erro ao buscar escala:', err);
        }
    };

    const refreshEmployee = async () => {
        try {
            const response = await fetch(`/employees/${employeeId}?_t=${Date.now()}`);
            if (response.ok) {
                const data = await response.json();
                setEmployee(data.employee);
            }
        } catch (err) {
            console.error('Erro ao atualizar funcionário:', err);
        }
    };

    const handleEdit = () => {
        if (onEdit && employee) onEdit(employee);
    };

    const handleUnassignSchedule = async () => {
        if (!currentSchedule) return;

        const confirmed = await confirm({
            title: 'Remover Escala',
            message: 'Tem certeza que deseja remover a escala deste funcionário?',
            type: 'danger',
            confirmText: 'Remover',
        });

        if (confirmed) {
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.content;
                await fetch(`/work-schedules/${currentSchedule.schedule_id}/employees/${currentSchedule.id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token,
                    },
                });
                setCurrentSchedule(null);
                refreshEmployee();
            } catch (error) {
                console.error('Erro ao remover escala:', error);
            }
        }
    };

    const handleScheduleAssigned = () => {
        setIsScheduleManageOpen(false);
        fetchCurrentSchedule();
        refreshEmployee();
    };

    const handleOverrideCreated = () => {
        setIsOverrideModalOpen(false);
        fetchCurrentSchedule();
    };

    const closeHistoryModal = () => {
        setIsHistoryModalOpen(false);
        refreshEmployee();
        fetchCurrentSchedule();
    };

    const headerBadges = [];
    if (employee?.status) {
        headerBadges.push({ text: employee.status, className: 'bg-white/20 text-white' });
    }
    if (employee?.is_pcd) {
        headerBadges.push({ text: 'PcD', className: 'bg-blue-500/30 text-white' });
    }
    if (employee?.is_apprentice) {
        headerBadges.push({ text: 'Aprendiz', className: 'bg-purple-500/30 text-white' });
    }

    return (
        <>
            <StandardModal
                show={show}
                onClose={onClose}
                title={employee?.name || 'Detalhes do Funcionário'}
                subtitle={employee?.short_name}
                headerColor="bg-gray-700"
                headerIcon={employee ? <EmployeeAvatar employee={employee} size="sm" /> : undefined}
                headerBadges={headerBadges}
                loading={loading}
                errorMessage={error}
                footer={employee && (
                    <StandardModal.Footer onCancel={onClose} cancelLabel="Fechar" />
                )}
            >
                {employee && (
                    <>
                        {/* Informações Pessoais */}
                        <StandardModal.Section title="Informações Pessoais">
                            <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                                <StandardModal.Field label="ID" value={`#${employee.id}`} mono />
                                <StandardModal.Field label="Nome Completo" value={employee.name} />
                                <StandardModal.Field label="Nome Abreviado" value={employee.short_name} />
                                <StandardModal.Field label="CPF" value={employee.cpf} mono />
                                <StandardModal.Field
                                    label="Data de Nascimento"
                                    value={employee.birth_date
                                        ? `${employee.birth_date}${employee.age ? ` (${employee.age} anos)` : ''}`
                                        : null}
                                />
                                <StandardModal.Field label="Escolaridade" value={employee.education_level} />
                            </div>
                        </StandardModal.Section>

                        {/* Informações Profissionais */}
                        <StandardModal.Section title="Informações Profissionais">
                            <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                                <StandardModal.Field label="Cargo" value={employee.position} />
                                <StandardModal.Field label="Nível" value={employee.level} />
                                <StandardModal.Field
                                    label="Data de Admissão"
                                    value={employee.admission_date
                                        ? `${employee.admission_date}${employee.years_of_service !== null ? ` (${employee.years_of_service} ${employee.years_of_service === 1 ? 'ano' : 'anos'})` : ''}`
                                        : null}
                                />
                                {employee.dismissal_date && (
                                    <StandardModal.Field label="Data de Demissão" value={formatDate(employee.dismissal_date)} />
                                )}
                                <StandardModal.Field label="Loja" value={employee.store} />
                                <StandardModal.Field label="Cupom Site" value={employee.site_coupon} mono />
                                <div>
                                    <p className="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Status</p>
                                    <div className="mt-0.5">
                                        <StatusBadge variant={STATUS_VARIANT_MAP[employee.status] || 'danger'} dot>
                                            {employee.status}
                                        </StatusBadge>
                                    </div>
                                </div>
                            </div>
                        </StandardModal.Section>

                        {/* Escala de Trabalho */}
                        <StandardModal.Section
                            title="Escala de Trabalho"
                            icon={<ClockIcon className="h-4 w-4" />}
                        >
                            <div>
                                <div className="flex justify-end gap-2 mb-3">
                                    {currentSchedule && (
                                        <>
                                            <Button
                                                variant="warning"
                                                size="xs"
                                                icon={PencilSquareIcon}
                                                onClick={() => setIsOverrideModalOpen(true)}
                                            >
                                                Exceção
                                            </Button>
                                            <Button
                                                variant="danger"
                                                size="xs"
                                                icon={TrashIcon}
                                                onClick={handleUnassignSchedule}
                                            >
                                                Remover
                                            </Button>
                                        </>
                                    )}
                                    <Button
                                        variant="primary"
                                        size="xs"
                                        icon={currentSchedule ? ArrowsRightLeftIcon : PlusIcon}
                                        onClick={() => setIsScheduleManageOpen(true)}
                                    >
                                        {currentSchedule ? 'Alterar' : 'Atribuir Escala'}
                                    </Button>
                                </div>

                                {currentSchedule ? (
                                    <div>
                                        <div className="flex items-center gap-3 mb-3">
                                            <span className="text-sm font-semibold text-gray-900">{currentSchedule.schedule_name}</span>
                                            <StatusBadge variant="indigo" size="sm">{currentSchedule.weekly_hours}</StatusBadge>
                                            <span className="text-xs text-gray-500">Desde {currentSchedule.effective_date}</span>
                                        </div>

                                        {currentSchedule.days && (
                                            <div className="grid grid-cols-7 gap-1">
                                                {currentSchedule.days.map((day) => (
                                                    <div
                                                        key={day.day_of_week}
                                                        className={`p-2 rounded text-center text-xs ${
                                                            day.is_work_day
                                                                ? day.has_override
                                                                    ? 'bg-yellow-100 text-yellow-800 border border-yellow-300'
                                                                    : 'bg-green-100 text-green-800'
                                                                : day.has_override
                                                                    ? 'bg-yellow-50 text-yellow-700 border border-yellow-200'
                                                                    : 'bg-gray-100 text-gray-500'
                                                        }`}
                                                    >
                                                        <div className="font-semibold">{day.day_short_name}</div>
                                                        {day.is_work_day ? (
                                                            <div className="text-[10px]">
                                                                {day.entry_time?.substring(0, 5)}-{day.exit_time?.substring(0, 5)}
                                                            </div>
                                                        ) : (
                                                            <div className="text-[10px]">Folga</div>
                                                        )}
                                                        {day.has_override && (
                                                            <div className="text-[9px] font-medium mt-0.5" title={day.override_reason}>*</div>
                                                        )}
                                                    </div>
                                                ))}
                                            </div>
                                        )}

                                        {currentSchedule.overrides?.length > 0 && (
                                            <div className="mt-2">
                                                <p className="text-xs text-gray-500">
                                                    {currentSchedule.overrides.length} exceção(ões) ativa(s)
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                ) : (
                                    <div className="text-center py-4">
                                        <ClockIcon className="w-10 h-10 text-gray-300 mx-auto mb-2" />
                                        <p className="text-sm text-gray-500">Nenhuma escala atribuída</p>
                                        <button
                                            onClick={() => setIsScheduleManageOpen(true)}
                                            className="mt-2 text-sm text-indigo-600 hover:text-indigo-800 font-medium"
                                        >
                                            Atribuir escala agora
                                        </button>
                                    </div>
                                )}
                            </div>
                        </StandardModal.Section>

                        {/* Ações Disponíveis */}
                        {onEdit && (
                            <StandardModal.Section title="Ações Disponíveis">
                                <div className="flex flex-wrap gap-3">
                                    <Button variant="warning" icon={PencilSquareIcon} onClick={handleEdit}>
                                        Editar Funcionário
                                    </Button>
                                    <Button
                                        variant="success"
                                        icon={DocumentTextIcon}
                                        onClick={() => window.open(`/employees/${employee.id}/report`, '_blank')}
                                    >
                                        Gerar Relatório
                                    </Button>
                                    <Button
                                        variant="primary"
                                        icon={ClockIcon}
                                        onClick={() => setIsHistoryModalOpen(true)}
                                    >
                                        Ver Histórico
                                    </Button>
                                </div>
                            </StandardModal.Section>
                        )}
                    </>
                )}
            </StandardModal>

            {/* Sub-modals */}
            <EmployeeHistoryModal
                show={isHistoryModalOpen}
                onClose={closeHistoryModal}
                employeeId={employeeId}
                positions={positions}
                stores={stores}
                onEmployeeUpdated={refreshEmployee}
            />

            <EmployeeScheduleManageModal
                isOpen={isScheduleManageOpen}
                onClose={() => setIsScheduleManageOpen(false)}
                onSuccess={handleScheduleAssigned}
                employeeId={employeeId}
                employeeName={employee?.name || ''}
                currentAssignment={currentSchedule}
            />

            {currentSchedule && (
                <WorkScheduleDayOverrideModal
                    isOpen={isOverrideModalOpen}
                    onClose={() => setIsOverrideModalOpen(false)}
                    onSuccess={handleOverrideCreated}
                    assignment={{
                        id: currentSchedule.id,
                        employee_name: employee?.name,
                        employee_short_name: employee?.short_name,
                        overrides: currentSchedule.overrides || [],
                    }}
                    scheduleDays={currentSchedule.days || []}
                />
            )}

            <ConfirmDialogComponent />
        </>
    );
}
