import { useEffect, useState } from 'react';
import Modal from '@/Components/Modal';
import EmployeeAvatar from '@/Components/EmployeeAvatar';
import EmployeeHistoryModal from '@/Components/EmployeeHistoryModal';
import EmployeeScheduleManageModal from '@/Components/EmployeeScheduleManageModal';
import WorkScheduleDayOverrideModal from '@/Components/WorkScheduleDayOverrideModal';

export default function EmployeeModal({ show, onClose, employeeId, onEdit, positions, stores }) {
    const [employee, setEmployee] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [isHistoryModalOpen, setIsHistoryModalOpen] = useState(false);
    const [currentSchedule, setCurrentSchedule] = useState(null);
    const [isScheduleManageOpen, setIsScheduleManageOpen] = useState(false);
    const [isOverrideModalOpen, setIsOverrideModalOpen] = useState(false);

    useEffect(() => {
        if (show && employeeId) {
            fetchEmployee();
            fetchCurrentSchedule();
        } else if (!show) {
            // Reset state when modal closes
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
            if (!response.ok) {
                throw new Error('Erro ao carregar funcionário');
            }
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

    const getStatusBadgeColor = (status) => {
        const colors = {
            'Ativo': 'bg-green-100 text-green-800',
            'Férias': 'bg-blue-100 text-blue-800',
            'Licença': 'bg-yellow-100 text-yellow-800',
            'Inativo': 'bg-red-100 text-red-800',
            'Pendente': 'bg-gray-100 text-gray-800',
        };
        return colors[status] || 'bg-red-100 text-red-800';
    };

    const getCharacteristicBadges = (employee) => {
        const badges = [];

        if (employee?.is_pcd) {
            badges.push({ text: 'PcD', color: 'bg-blue-100 text-blue-800' });
        }

        if (employee?.is_apprentice) {
            badges.push({ text: 'Aprendiz', color: 'bg-purple-100 text-purple-800' });
        }

        return badges;
    };

    const handleEdit = () => {
        if (onEdit && employee) {
            onEdit(employee);
        }
    };

    const handleViewHistory = () => {
        setIsHistoryModalOpen(true);
    };

    const handleUnassignSchedule = async () => {
        if (!currentSchedule) return;
        if (!confirm('Tem certeza que deseja remover a escala deste funcionário?')) return;

        try {
            await axios.delete(`/work-schedules/${currentSchedule.schedule_id}/employees/${currentSchedule.id}`);
            setCurrentSchedule(null);
            refreshEmployee();
        } catch (error) {
            console.error('Erro ao remover escala:', error);
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

    if (loading) {
        return (
            <Modal show={show} onClose={onClose} title="Carregando..." maxWidth="85vw">
                <div className="flex items-center justify-center" style={{ minHeight: '400px' }}>
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
                </div>
            </Modal>
        );
    }

    if (error) {
        return (
            <Modal show={show} onClose={onClose} title="Erro" maxWidth="85vw">
                <div className="text-center py-8" style={{ minHeight: '400px' }}>
                    <div className="inline-flex items-center justify-center w-16 h-16 bg-red-100 rounded-full mb-4">
                        <svg className="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                    </div>
                    <h3 className="text-lg font-medium text-gray-900 mb-2">Erro</h3>
                    <p className="text-gray-500 mb-4">{error}</p>
                    <div className="flex justify-center space-x-3">
                        <button
                            onClick={fetchEmployee}
                            className="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded transition-colors"
                        >
                            Tentar novamente
                        </button>
                        <button
                            onClick={onClose}
                            className="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded transition-colors"
                        >
                            Fechar
                        </button>
                    </div>
                </div>
            </Modal>
        );
    }

    const characteristicBadges = employee ? getCharacteristicBadges(employee) : [];

    return (
        <Modal show={show} onClose={onClose} title="Detalhes do Funcionário" maxWidth="85vw">
            {employee && <div className="space-y-6">
                {/* Avatar e informações básicas */}
                <div className="flex items-center space-x-4">
                    <div className="flex-shrink-0">
                        <EmployeeAvatar employee={employee} size="2xl" />
                    </div>
                    <div className="flex-1">
                        <h3 className="text-xl font-semibold text-gray-900">{employee.name}</h3>
                        <p className="text-gray-600">{employee.short_name}</p>
                        <div className="mt-2 flex flex-wrap items-center gap-2">
                            <span className={`inline-flex px-3 py-1 text-sm font-semibold rounded-full ${getStatusBadgeColor(employee.status)}`}>
                                {employee.status}
                            </span>
                            {characteristicBadges.map((badge, index) => (
                                <span key={index} className={`inline-flex px-3 py-1 text-sm font-semibold rounded-full ${badge.color}`}>
                                    {badge.text}
                                </span>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Informações detalhadas */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="bg-gray-50 p-4 rounded-lg">
                        <h4 className="text-sm font-medium text-gray-900 mb-3">
                            Informações Pessoais
                        </h4>
                        <div className="space-y-2 text-sm">
                            <div>
                                <span className="font-medium text-gray-600">ID:</span>
                                <span className="ml-2 text-gray-900">#{employee.id}</span>
                            </div>
                            <div>
                                <span className="font-medium text-gray-600">Nome Completo:</span>
                                <span className="ml-2 text-gray-900">{employee.name}</span>
                            </div>
                            <div>
                                <span className="font-medium text-gray-600">Nome Abreviado:</span>
                                <span className="ml-2 text-gray-900">{employee.short_name || 'Não informado'}</span>
                            </div>
                            <div>
                                <span className="font-medium text-gray-600">CPF:</span>
                                <span className="ml-2 text-gray-900">{employee.cpf || 'Não informado'}</span>
                            </div>
                            <div>
                                <span className="font-medium text-gray-600">Data de Nascimento:</span>
                                <span className="ml-2 text-gray-900">
                                    {employee.birth_date || 'Não informado'}
                                    {employee.age && <span className="text-gray-500"> ({employee.age} anos)</span>}
                                </span>
                            </div>
                            <div>
                                <span className="font-medium text-gray-600">Escolaridade:</span>
                                <span className="ml-2 text-gray-900">{employee.education_level}</span>
                            </div>
                        </div>
                    </div>

                    <div className="bg-gray-50 p-4 rounded-lg">
                        <h4 className="text-sm font-medium text-gray-900 mb-3">
                            Informações Profissionais
                        </h4>
                        <div className="space-y-2 text-sm">
                            <div>
                                <span className="font-medium text-gray-600">Cargo:</span>
                                <span className="ml-2 text-gray-900">{employee.position || 'Não informado'}</span>
                            </div>
                            <div>
                                <span className="font-medium text-gray-600">Nível:</span>
                                <span className="ml-2 text-gray-900">{employee.level || 'Não informado'}</span>
                            </div>
                            <div>
                                <span className="font-medium text-gray-600">Data de Admissão:</span>
                                <span className="ml-2 text-gray-900">
                                    {employee.admission_date || 'Não informado'}
                                    {employee.years_of_service !== null && (
                                        <span className="text-gray-500"> ({employee.years_of_service} {employee.years_of_service === 1 ? 'ano' : 'anos'})</span>
                                    )}
                                </span>
                            </div>
                            {employee.dismissal_date && (
                                <div>
                                    <span className="font-medium text-gray-600">Data de Demissão:</span>
                                    <span className="ml-2 text-gray-900">{employee.dismissal_date}</span>
                                </div>
                            )}
                            <div>
                                <span className="font-medium text-gray-600">Loja:</span>
                                <span className="ml-2 text-gray-900">{employee.store || 'Não informado'}</span>
                            </div>
                            <div>
                                <span className="font-medium text-gray-600">Cupom Site:</span>
                                <span className="ml-2 text-gray-900">{employee.site_coupon || 'Não informado'}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Escala de Trabalho */}
                <div className="bg-gray-50 p-4 rounded-lg">
                    <div className="flex items-center justify-between mb-3">
                        <h4 className="text-sm font-medium text-gray-900 flex items-center gap-2">
                            <svg className="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Escala de Trabalho
                        </h4>
                        <div className="flex gap-2">
                            {currentSchedule && (
                                <>
                                    <button
                                        onClick={() => setIsOverrideModalOpen(true)}
                                        className="inline-flex items-center px-2.5 py-1.5 text-xs font-medium rounded-md text-yellow-700 bg-yellow-100 hover:bg-yellow-200 transition-colors"
                                        title="Adicionar exceção de dia"
                                    >
                                        <svg className="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        Exceção
                                    </button>
                                    <button
                                        onClick={handleUnassignSchedule}
                                        className="inline-flex items-center px-2.5 py-1.5 text-xs font-medium rounded-md text-red-700 bg-red-100 hover:bg-red-200 transition-colors"
                                        title="Remover escala"
                                    >
                                        <svg className="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                        Remover
                                    </button>
                                </>
                            )}
                            <button
                                onClick={() => setIsScheduleManageOpen(true)}
                                className="inline-flex items-center px-2.5 py-1.5 text-xs font-medium rounded-md text-indigo-700 bg-indigo-100 hover:bg-indigo-200 transition-colors"
                            >
                                <svg className="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    {currentSchedule ? (
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                    ) : (
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    )}
                                </svg>
                                {currentSchedule ? 'Alterar' : 'Atribuir Escala'}
                            </button>
                        </div>
                    </div>

                    {currentSchedule ? (
                        <div>
                            {/* Info da escala */}
                            <div className="flex items-center gap-3 mb-3">
                                <span className="text-sm font-semibold text-gray-900">{currentSchedule.schedule_name}</span>
                                <span className="text-xs px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded-full">
                                    {currentSchedule.weekly_hours}
                                </span>
                                <span className="text-xs text-gray-500">
                                    Desde {currentSchedule.effective_date}
                                </span>
                            </div>

                            {/* Grid visual dos 7 dias */}
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

                            {/* Overrides existentes */}
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
                            <svg className="w-10 h-10 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
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

                {/* Ações disponíveis */}
                {onEdit && (
                    <div className="bg-blue-50 p-4 rounded-lg">
                        <h4 className="text-sm font-medium text-blue-900 mb-3">
                            Ações Disponíveis
                        </h4>
                        <div className="flex flex-wrap gap-3">
                            <button
                                onClick={handleEdit}
                                className="inline-flex items-center justify-center px-4 py-2 bg-yellow-600 text-white text-sm font-medium rounded-md hover:bg-yellow-700 transition-colors border border-yellow-600 hover:border-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500"
                            >
                                <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                Editar Funcionário
                            </button>

                            <button
                                onClick={() => window.open(`/employees/${employee.id}/report`, '_blank')}
                                className="inline-flex items-center justify-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700 transition-colors border border-green-600 hover:border-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                            >
                                <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Gerar Relatório
                            </button>

                            <button
                                onClick={handleViewHistory}
                                className="inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 transition-colors border border-blue-600 hover:border-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                            >
                                <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Ver Histórico
                            </button>
                        </div>
                    </div>
                )}

                <div className="flex justify-end">
                    <button
                        type="button"
                        onClick={onClose}
                        className="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded transition-colors"
                    >
                        Fechar
                    </button>
                </div>
            </div>}

            {/* Employee History Modal */}
            <EmployeeHistoryModal
                show={isHistoryModalOpen}
                onClose={closeHistoryModal}
                employeeId={employeeId}
                positions={positions}
                stores={stores}
                onEmployeeUpdated={refreshEmployee}
            />

            {/* Schedule Manage Modal */}
            <EmployeeScheduleManageModal
                isOpen={isScheduleManageOpen}
                onClose={() => setIsScheduleManageOpen(false)}
                onSuccess={handleScheduleAssigned}
                employeeId={employeeId}
                employeeName={employee?.name || ''}
                currentAssignment={currentSchedule}
            />

            {/* Day Override Modal */}
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
        </Modal>
    );
}
