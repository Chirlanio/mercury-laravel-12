import { useEffect, useState } from 'react';
import Modal from '@/Components/Modal';
import EmployeeAvatar from '@/Components/EmployeeAvatar';
import EmployeeHistoryModal from '@/Components/EmployeeHistoryModal';

export default function EmployeeModal({ show, onClose, employeeId, onEdit, positions, stores }) {
    const [employee, setEmployee] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [isHistoryModalOpen, setIsHistoryModalOpen] = useState(false);

    useEffect(() => {
        if (show && employeeId) {
            fetchEmployee();
        } else if (!show) {
            // Reset state when modal closes
            setEmployee(null);
            setError(null);
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

    const getStatusBadgeColor = (isActive) => {
        return isActive ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
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

    const closeHistoryModal = () => {
        setIsHistoryModalOpen(false);
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
                            <span className={`inline-flex px-3 py-1 text-sm font-semibold rounded-full ${getStatusBadgeColor(employee.is_active)}`}>
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
            />
        </Modal>
    );
}
