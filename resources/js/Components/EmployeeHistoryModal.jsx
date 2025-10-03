import { useEffect, useState } from 'react';
import Modal from '@/Components/Modal';
import Button from '@/Components/Button';
import ContractCreateModal from '@/Components/ContractCreateModal';
import ContractEditModal from '@/Components/ContractEditModal';
import DocumentViewerModal from '@/Components/DocumentViewerModal';
import ExportEventsModal from '@/Components/ExportEventsModal';

export default function EmployeeHistoryModal({ show, onClose, employeeId, positions, stores }) {
    const [employee, setEmployee] = useState(null);
    const [histories, setHistories] = useState([]);
    const [contracts, setContracts] = useState([]);
    const [events, setEvents] = useState([]);
    const [eventTypes, setEventTypes] = useState([]);
    const [movementTypes, setMovementTypes] = useState([]);
    const [activeTab, setActiveTab] = useState('contracts'); // 'contracts', 'histories' ou 'events'
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [isContractModalOpen, setIsContractModalOpen] = useState(false);
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const [selectedContract, setSelectedContract] = useState(null);
    const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
    const [contractToDelete, setContractToDelete] = useState(null);
    const [isCreatingEvent, setIsCreatingEvent] = useState(false);
    const [isDeleteEventModalOpen, setIsDeleteEventModalOpen] = useState(false);
    const [eventToDelete, setEventToDelete] = useState(null);
    const [isDocumentViewerOpen, setIsDocumentViewerOpen] = useState(false);
    const [selectedDocument, setSelectedDocument] = useState(null);
    const [isExportModalOpen, setIsExportModalOpen] = useState(false);
    const [eventFormData, setEventFormData] = useState({
        event_type_id: '',
        start_date: '',
        end_date: '',
        document: null,
        notes: '',
    });
    const [eventErrors, setEventErrors] = useState({});
    const [selectedEventType, setSelectedEventType] = useState(null);

    useEffect(() => {
        if (show && employeeId) {
            fetchHistory();
            fetchMovementTypes();
            fetchEvents();
        } else if (!show) {
            // Reset state when modal closes
            setEmployee(null);
            setHistories([]);
            setContracts([]);
            setEvents([]);
            setActiveTab('contracts');
            setError(null);
        }
    }, [show, employeeId]);

    const fetchHistory = async () => {
        setLoading(true);
        setError(null);

        try {
            const response = await fetch(`/employees/${employeeId}/history`);
            if (!response.ok) {
                throw new Error('Erro ao carregar histórico');
            }
            const data = await response.json();
            setEmployee(data.employee);
            setHistories(data.histories || []);
            setContracts(data.contracts || []);
        } catch (err) {
            setError('Erro ao carregar histórico do funcionário');
            console.error('Erro ao buscar histórico:', err);
        } finally {
            setLoading(false);
        }
    };

    const fetchMovementTypes = async () => {
        try {
            // Por enquanto, vamos criar os tipos manualmente
            // Se precisar buscar do backend, adicione uma rota
            setMovementTypes([
                { id: 1, name: 'Admissão' },
                { id: 2, name: 'Promoção' },
                { id: 3, name: 'Mudança de Cargo' },
                { id: 4, name: 'Transferência' },
                { id: 5, name: 'Demissão' },
            ]);
        } catch (err) {
            console.error('Erro ao buscar tipos de movimentação:', err);
        }
    };

    const fetchEvents = async () => {
        try {
            const response = await fetch(`/employees/${employeeId}/events`);
            const data = await response.json();
            setEvents(data.events);
            setEventTypes(data.event_types);
        } catch (error) {
            console.error('Erro ao carregar eventos:', error);
        }
    };

    const handleEventTypeChange = (e) => {
        const typeId = e.target.value;
        setEventFormData({ ...eventFormData, event_type_id: typeId });

        const type = eventTypes.find(t => t.id === parseInt(typeId));
        setSelectedEventType(type);

        // Limpar campos que não são necessários
        if (type && !type.requires_date_range) {
            setEventFormData(prev => ({ ...prev, end_date: '' }));
        }
    };

    const handleFileChange = (e) => {
        setEventFormData({ ...eventFormData, document: e.target.files[0] });
    };

    const handleEventSubmit = async (e) => {
        e.preventDefault();
        setEventErrors({});

        const formDataToSend = new FormData();
        formDataToSend.append('event_type_id', eventFormData.event_type_id);
        formDataToSend.append('start_date', eventFormData.start_date);

        if (eventFormData.end_date) {
            formDataToSend.append('end_date', eventFormData.end_date);
        }

        if (eventFormData.document) {
            formDataToSend.append('document', eventFormData.document);
        }

        if (eventFormData.notes) {
            formDataToSend.append('notes', eventFormData.notes);
        }

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            const response = await fetch(`/employees/${employeeId}/events`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: formDataToSend,
            });

            const data = await response.json();

            if (!response.ok) {
                if (data.errors) {
                    setEventErrors(data.errors);
                }
                throw new Error(data.message || 'Erro ao criar evento');
            }

            // Resetar formulário
            setEventFormData({
                event_type_id: '',
                start_date: '',
                end_date: '',
                document: null,
                notes: '',
            });
            setSelectedEventType(null);
            setIsCreatingEvent(false);

            // Recarregar eventos
            fetchEvents();
        } catch (error) {
            console.error('Erro ao criar evento:', error);
        }
    };

    const handleDeleteEventClick = (event) => {
        setEventToDelete(event);
        setIsDeleteEventModalOpen(true);
    };

    const handleDeleteEventCancel = () => {
        setIsDeleteEventModalOpen(false);
        setEventToDelete(null);
    };

    const handleDeleteEventConfirm = async () => {
        if (!eventToDelete) return;

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            const response = await fetch(`/employees/${employeeId}/events/${eventToDelete.id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error('Erro ao excluir evento');
            }

            fetchEvents();
            setIsDeleteEventModalOpen(false);
            setEventToDelete(null);
        } catch (error) {
            console.error('Erro ao excluir evento:', error);
        }
    };

    const getEmployeeEventIcon = (eventType) => {
        const icons = {
            'Férias': '🏖️',
            'Licença': '🏥',
            'Falta': '❌',
            'Atestado Médico': '📄',
        };
        return icons[eventType] || '📋';
    };

    const getEmployeeEventColor = (eventType) => {
        const colors = {
            'Férias': 'bg-blue-100 text-blue-800 border-blue-300',
            'Licença': 'bg-yellow-100 text-yellow-800 border-yellow-300',
            'Falta': 'bg-red-100 text-red-800 border-red-300',
            'Atestado Médico': 'bg-green-100 text-green-800 border-green-300',
        };
        return colors[eventType] || 'bg-gray-100 text-gray-800 border-gray-300';
    };

    const handleViewDocument = (event) => {
        setSelectedDocument({
            url: event.document_url,
            eventType: event.event_type,
            period: event.period,
        });
        setIsDocumentViewerOpen(true);
    };

    const handleContractCreated = (newContract) => {
        // Recarregar o histórico completo para refletir todas as mudanças
        fetchHistory();
        setIsContractModalOpen(false);
    };

    const handleEditContract = (contract) => {
        setSelectedContract(contract);
        setIsEditModalOpen(true);
    };

    const handleContractUpdated = (updatedContract) => {
        // Recarregar o histórico completo para refletir todas as mudanças
        fetchHistory();
        setIsEditModalOpen(false);
        setSelectedContract(null);
    };

    const handleDeleteClick = (contract) => {
        setContractToDelete(contract);
        setIsDeleteModalOpen(true);
    };

    const handleDeleteConfirm = async () => {
        if (!contractToDelete) return;

        try {
            const response = await fetch(`/employees/${employeeId}/contracts/${contractToDelete.id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            });

            if (!response.ok) {
                throw new Error('Erro ao excluir contrato');
            }

            const data = await response.json();

            setIsDeleteModalOpen(false);
            setContractToDelete(null);

            // Se era o último contrato ativo e existe um contrato anterior, perguntar se quer reativar
            if (data.isLastActiveContract && data.previousContract) {
                const shouldReactivate = window.confirm(
                    `O contrato ativo foi excluído. Deseja reativar o contrato anterior?\n\n` +
                    `Cargo: ${data.previousContract.position}\n` +
                    `Loja: ${data.previousContract.store}\n` +
                    `Data de início: ${data.previousContract.start_date}`
                );

                if (shouldReactivate) {
                    await handleReactivateContract(data.previousContract.id);
                } else {
                    // Recarregar histórico se não reativar
                    fetchHistory();
                }
            } else {
                // Recarregar histórico após exclusão
                fetchHistory();
            }
        } catch (err) {
            console.error('Erro ao excluir contrato:', err);
            alert('Erro ao excluir contrato. Tente novamente.');
        }
    };

    const handleReactivateContract = async (contractId) => {
        try {
            const response = await fetch(`/employees/${employeeId}/contracts/${contractId}/reactivate`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            });

            if (!response.ok) {
                throw new Error('Erro ao reativar contrato');
            }

            // Recarregar histórico completo após reativação
            fetchHistory();
        } catch (err) {
            console.error('Erro ao reativar contrato:', err);
            alert('Erro ao reativar contrato. Tente novamente.');
        }
    };

    const handleDeleteCancel = () => {
        setIsDeleteModalOpen(false);
        setContractToDelete(null);
    };

    const getEventTypeColor = (eventType) => {
        const colors = {
            promotion: 'bg-green-100 text-green-800 border-green-200',
            position_change: 'bg-blue-100 text-blue-800 border-blue-200',
            transfer: 'bg-purple-100 text-purple-800 border-purple-200',
            salary_change: 'bg-yellow-100 text-yellow-800 border-yellow-200',
            status_change: 'bg-orange-100 text-orange-800 border-orange-200',
            admission: 'bg-teal-100 text-teal-800 border-teal-200',
            dismissal: 'bg-red-100 text-red-800 border-red-200',
            default: 'bg-gray-100 text-gray-800 border-gray-200',
        };
        return colors[eventType] || colors.default;
    };

    const getEventTypeIcon = (eventType) => {
        const icons = {
            promotion: (
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
            ),
            position_change: (
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
            ),
            transfer: (
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 8l4 4m0 0l-4 4m4-4H3" />
            ),
            salary_change: (
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            ),
            status_change: (
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            ),
            admission: (
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
            ),
            dismissal: (
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 7a4 4 0 11-8 0 4 4 0 018 0zM9 14a6 6 0 00-6 6v1h12v-1a6 6 0 00-6-6zM21 12h-6" />
            ),
            default: (
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            ),
        };
        return icons[eventType] || icons.default;
    };

    if (loading) {
        return (
            <Modal show={show} onClose={onClose} title="Carregando..." maxWidth="85vw">
                <div className="flex items-center justify-center py-12">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-indigo-600"></div>
                </div>
            </Modal>
        );
    }

    if (error) {
        return (
            <Modal show={show} onClose={onClose} title="Erro" maxWidth="85vw">
                <div className="text-center py-8">
                    <div className="inline-flex items-center justify-center w-16 h-16 bg-red-100 rounded-full mb-4">
                        <svg className="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                    </div>
                    <h3 className="text-lg font-medium text-gray-900 mb-2">Erro</h3>
                    <p className="text-gray-500 mb-4">{error}</p>
                    <div className="flex justify-center space-x-3">
                        <Button
                            variant="primary"
                            onClick={fetchHistory}
                        >
                            Tentar novamente
                        </Button>
                        <Button
                            variant="outline"
                            onClick={onClose}
                        >
                            Fechar
                        </Button>
                    </div>
                </div>
            </Modal>
        );
    }

    return (
        <Modal
            show={show}
            onClose={onClose}
            title={`Histórico - ${employee?.name || 'Funcionário'}`}
            maxWidth="85vw"
        >
            <div className="space-y-6">
                {/* Informações do funcionário */}
                {employee && (
                    <div className="bg-gradient-to-r from-indigo-50 to-blue-50 p-4 rounded-lg border border-indigo-100">
                        <div className="flex items-center space-x-3">
                            <div className="flex-shrink-0">
                                <svg className="w-10 h-10 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <div>
                                <h3 className="text-lg font-semibold text-gray-900">{employee.name}</h3>
                                <p className="text-sm text-gray-600">ID: #{employee.id}</p>
                            </div>
                        </div>
                    </div>
                )}

                {/* Tabs */}
                <div className="border-b border-gray-200">
                    <div className="flex items-center justify-between">
                        <nav className="-mb-px flex space-x-8">
                        <button
                            onClick={() => setActiveTab('contracts')}
                            className={`${
                                activeTab === 'contracts'
                                    ? 'border-indigo-500 text-indigo-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                            } whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center space-x-2`}
                        >
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span>Contratos ({contracts.length})</span>
                        </button>
                        <button
                            onClick={() => setActiveTab('events')}
                            className={`${
                                activeTab === 'events'
                                    ? 'border-indigo-500 text-indigo-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                            } whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm flex items-center space-x-2`}
                        >
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span>Eventos ({events.length})</span>
                        </button>
                    </nav>
                    {activeTab === 'contracts' && (
                        <Button
                            variant="primary"
                            size="sm"
                            onClick={() => setIsContractModalOpen(true)}
                            icon={({ className }) => (
                                <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                            )}
                        >
                            Adicionar Contrato
                        </Button>
                    )}
                    {activeTab === 'events' && !isCreatingEvent && (
                        <div className="flex space-x-2">
                            <Button
                                variant="secondary"
                                size="sm"
                                onClick={() => setIsExportModalOpen(true)}
                                icon={({ className }) => (
                                    <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                )}
                            >
                                Exportar
                            </Button>
                            <Button
                                variant="primary"
                                size="sm"
                                onClick={() => setIsCreatingEvent(true)}
                                icon={({ className }) => (
                                    <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                )}
                            >
                                Novo Evento
                            </Button>
                        </div>
                    )}
                </div>
                </div>

                {/* Conteúdo das Tabs */}
                {activeTab === 'contracts' && (
                    /* Lista de contratos */
                    contracts.length === 0 ? (
                        <div className="text-center py-12">
                            <svg className="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <h3 className="text-lg font-medium text-gray-900 mb-2">Nenhum contrato encontrado</h3>
                            <p className="text-gray-500">Este funcionário ainda não possui contratos registrados.</p>
                        </div>
                    ) : (
                        <div className="space-y-4 max-h-[60vh] overflow-y-auto overflow-x-hidden pr-2">
                            {contracts.map((contract, index) => (
                                <div
                                    key={contract.id}
                                    className={`relative bg-white border-2 rounded-lg p-4 hover:shadow-md transition-all duration-200 ${
                                        contract.is_active
                                            ? 'border-green-200 bg-green-50/30'
                                            : 'border-gray-200'
                                    }`}
                                >
                                    {/* Linha conectora */}
                                    {index < contracts.length - 1 && (
                                        <div className="absolute left-8 top-20 bottom-0 w-0.5 bg-gray-200 -mb-4"></div>
                                    )}

                                    <div className="flex items-start space-x-4">
                                        {/* Badge de status */}
                                        <div className={`flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center border-2 relative z-10 ${
                                            contract.is_active
                                                ? 'bg-green-100 border-green-300'
                                                : 'bg-gray-100 border-gray-300'
                                        }`}>
                                            <svg className={`w-6 h-6 ${contract.is_active ? 'text-green-600' : 'text-gray-600'}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                        </div>

                                        {/* Conteúdo do contrato */}
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-start justify-between mb-2">
                                                <div className="flex-1">
                                                    <div className="flex items-center gap-2 mb-1">
                                                        <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                                                            contract.status_label === 'Atual'
                                                                ? 'bg-green-100 text-green-800 border border-green-200'
                                                                : contract.status_label === 'Último contrato'
                                                                ? 'bg-orange-100 text-orange-800 border border-orange-200'
                                                                : 'bg-gray-100 text-gray-800 border border-gray-200'
                                                        }`}>
                                                            {contract.status_label || (contract.is_active ? 'Ativo' : 'Encerrado')}
                                                        </span>
                                                        <span className="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 border border-blue-200">
                                                            {contract.movement_type}
                                                        </span>
                                                    </div>
                                                    <h4 className="text-base font-bold text-gray-900">
                                                        {contract.position}
                                                    </h4>
                                                </div>

                                                {/* Botões de ação */}
                                                <div className="flex items-center space-x-2 ml-4">
                                                    <button
                                                        onClick={() => handleEditContract(contract)}
                                                        className="p-2 text-blue-600 hover:bg-blue-50 rounded-full transition-colors"
                                                        title="Editar contrato"
                                                    >
                                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                    </button>
                                                    <button
                                                        onClick={() => handleDeleteClick(contract)}
                                                        className="p-2 text-red-600 hover:bg-red-50 rounded-full transition-colors"
                                                        title="Excluir contrato"
                                                    >
                                                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>

                                            {/* Informações do contrato */}
                                            <div className="grid grid-cols-2 gap-3 mb-2">
                                                <div className="bg-white rounded-md p-2 border border-gray-100">
                                                    <div className="flex items-center space-x-1 mb-1">
                                                        <svg className="w-3 h-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                                        </svg>
                                                        <span className="text-xs font-medium text-gray-500">Loja</span>
                                                    </div>
                                                    <p className="text-sm font-semibold text-gray-900">{contract.store}</p>
                                                </div>

                                                <div className="bg-white rounded-md p-2 border border-gray-100">
                                                    <div className="flex items-center space-x-1 mb-1">
                                                        <svg className="w-3 h-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                        <span className="text-xs font-medium text-gray-500">Duração</span>
                                                    </div>
                                                    <p className="text-sm font-semibold text-gray-900">{contract.duration}</p>
                                                </div>
                                            </div>

                                            {/* Período */}
                                            <div className="bg-indigo-50 rounded-md p-2 border border-indigo-100">
                                                <div className="flex items-center justify-between text-sm">
                                                    <div className="flex items-center space-x-2">
                                                        <svg className="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                        <span className="font-semibold text-indigo-900">{contract.start_date}</span>
                                                    </div>
                                                    <svg className="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                                    </svg>
                                                    <div className="flex items-center space-x-2 relative group">
                                                        <span
                                                            className={`font-semibold ${contract.is_active ? 'text-green-700' : 'text-indigo-900'} ${contract.end_date_formatted ? 'cursor-help' : ''}`}
                                                            title={contract.end_date_formatted ? `Data de término: ${contract.end_date_formatted}` : ''}
                                                        >
                                                            {contract.end_date}
                                                        </span>
                                                        {/* Tooltip customizado */}
                                                        {contract.end_date_formatted && (
                                                            <div className="absolute bottom-full right-0 mb-2 px-3 py-2 bg-gray-900 text-white text-xs rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none whitespace-nowrap z-50">
                                                                Data de término: {contract.end_date_formatted}
                                                                <div className="absolute top-full right-4 -mt-1">
                                                                    <div className="border-4 border-transparent border-t-gray-900"></div>
                                                                </div>
                                                            </div>
                                                        )}
                                                        {contract.is_active && (
                                                            <svg className="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                            </svg>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Metadados */}
                                            <div className="mt-2 flex items-center text-xs text-gray-500">
                                                <svg className="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span>Registrado em {contract.created_at}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )
                )}

                {activeTab === 'histories' && (
                    /* Lista de histórico */
                    histories.length === 0 ? (
                        <div className="text-center py-12">
                            <svg className="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <h3 className="text-lg font-medium text-gray-900 mb-2">Nenhum histórico encontrado</h3>
                            <p className="text-gray-500">Este funcionário ainda não possui registros no histórico.</p>
                        </div>
                    ) : (
                        <div className="space-y-4 max-h-[60vh] overflow-y-auto overflow-x-hidden pr-2">
                            {histories.map((history, index) => (
                            <div
                                key={history.id}
                                className="relative bg-white border border-gray-200 rounded-lg p-5 hover:shadow-md transition-shadow duration-200"
                            >
                                {/* Linha conectora (exceto para o último item) */}
                                {index < histories.length - 1 && (
                                    <div className="absolute left-8 top-16 bottom-0 w-0.5 bg-gray-200 -mb-4"></div>
                                )}

                                <div className="flex items-start space-x-4">
                                    {/* Ícone do tipo de evento */}
                                    <div className={`flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center border-2 ${getEventTypeColor(history.event_type)} relative z-10 bg-white`}>
                                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            {getEventTypeIcon(history.event_type)}
                                        </svg>
                                    </div>

                                    {/* Conteúdo do evento */}
                                    <div className="flex-1 min-w-0">
                                        <div className="flex items-start justify-between mb-2">
                                            <div className="flex-1">
                                                <div className="flex items-center gap-2 mb-1">
                                                    <span className={`inline-flex px-3 py-1 text-xs font-semibold rounded-full border ${getEventTypeColor(history.event_type)}`}>
                                                        {history.event_type_label}
                                                    </span>
                                                    <span className="text-sm text-gray-500 font-medium">
                                                        {history.event_date}
                                                    </span>
                                                </div>
                                                <h4 className="text-base font-semibold text-gray-900 mb-1">
                                                    {history.title}
                                                </h4>
                                            </div>
                                        </div>

                                        {/* Descrição */}
                                        {history.description && (
                                            <p className="text-sm text-gray-600 mb-3">
                                                {history.description}
                                            </p>
                                        )}

                                        {/* Mudanças (old_value → new_value) */}
                                        {(history.old_value || history.new_value) && (
                                            <div className="bg-gray-50 rounded-md p-3 mb-3 border border-gray-100">
                                                <div className="flex items-center space-x-3 text-sm">
                                                    {history.old_value && (
                                                        <div className="flex items-center space-x-2">
                                                            <span className="text-gray-500 font-medium">De:</span>
                                                            <span className="text-red-600 font-semibold bg-red-50 px-2 py-1 rounded">
                                                                {history.old_value}
                                                            </span>
                                                        </div>
                                                    )}
                                                    {history.old_value && history.new_value && (
                                                        <svg className="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                                        </svg>
                                                    )}
                                                    {history.new_value && (
                                                        <div className="flex items-center space-x-2">
                                                            <span className="text-gray-500 font-medium">Para:</span>
                                                            <span className="text-green-600 font-semibold bg-green-50 px-2 py-1 rounded">
                                                                {history.new_value}
                                                            </span>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        )}

                                        {/* Metadados */}
                                        <div className="flex items-center space-x-4 text-xs text-gray-500">
                                            <div className="flex items-center space-x-1">
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                </svg>
                                                <span>{history.created_by}</span>
                                            </div>
                                            <div className="flex items-center space-x-1">
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span>{history.created_at}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            ))}
                        </div>
                    )
                )}

                {activeTab === 'events' && (
                    <div>
                        {isCreatingEvent && (
                            <div className="bg-indigo-50 border border-indigo-200 rounded-lg p-4 mb-6">
                                <h3 className="text-lg font-semibold text-indigo-900 mb-4">Novo Evento</h3>
                                <form onSubmit={handleEventSubmit} className="space-y-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Tipo de Evento *
                                        </label>
                                        <select
                                            value={eventFormData.event_type_id}
                                            onChange={handleEventTypeChange}
                                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            required
                                        >
                                            <option value="">Selecione...</option>
                                            {eventTypes.map((type) => (
                                                <option key={type.id} value={type.id}>
                                                    {type.name}
                                                </option>
                                            ))}
                                        </select>
                                        {eventErrors.event_type_id && (
                                            <p className="mt-1 text-sm text-red-600">{eventErrors.event_type_id[0]}</p>
                                        )}
                                    </div>

                                    {selectedEventType && (
                                        <>
                                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                                        {selectedEventType.requires_date_range ? 'Data de Início *' : 'Data *'}
                                                    </label>
                                                    <input
                                                        type="date"
                                                        value={eventFormData.start_date}
                                                        onChange={(e) => setEventFormData({ ...eventFormData, start_date: e.target.value })}
                                                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                        required
                                                    />
                                                    {eventErrors.start_date && (
                                                        <p className="mt-1 text-sm text-red-600">{eventErrors.start_date[0]}</p>
                                                    )}
                                                </div>

                                                {selectedEventType.requires_date_range && (
                                                    <div>
                                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                                            Data de Fim *
                                                        </label>
                                                        <input
                                                            type="date"
                                                            value={eventFormData.end_date}
                                                            onChange={(e) => setEventFormData({ ...eventFormData, end_date: e.target.value })}
                                                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                            required
                                                        />
                                                        {eventErrors.end_date && (
                                                            <p className="mt-1 text-sm text-red-600">{eventErrors.end_date[0]}</p>
                                                        )}
                                                    </div>
                                                )}
                                            </div>

                                            {selectedEventType.requires_document && (
                                                <div>
                                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                                        Documento * (PDF, JPG, PNG - máx. 10MB)
                                                    </label>
                                                    <input
                                                        type="file"
                                                        onChange={handleFileChange}
                                                        accept=".pdf,.jpg,.jpeg,.png"
                                                        className="w-full text-sm text-gray-500
                                                            file:mr-4 file:py-2 file:px-4
                                                            file:rounded-md file:border-0
                                                            file:text-sm file:font-semibold
                                                            file:bg-indigo-50 file:text-indigo-700
                                                            hover:file:bg-indigo-100"
                                                        required
                                                    />
                                                    {eventErrors.document && (
                                                        <p className="mt-1 text-sm text-red-600">{eventErrors.document[0]}</p>
                                                    )}
                                                </div>
                                            )}

                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                                    Observações
                                                </label>
                                                <textarea
                                                    value={eventFormData.notes}
                                                    onChange={(e) => setEventFormData({ ...eventFormData, notes: e.target.value })}
                                                    rows={3}
                                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    maxLength={1000}
                                                />
                                                {eventErrors.notes && (
                                                    <p className="mt-1 text-sm text-red-600">{eventErrors.notes[0]}</p>
                                                )}
                                            </div>
                                        </>
                                    )}

                                    <div className="flex justify-end gap-3">
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            size="sm"
                                            onClick={() => {
                                                setIsCreatingEvent(false);
                                                setEventFormData({
                                                    event_type_id: '',
                                                    start_date: '',
                                                    end_date: '',
                                                    document: null,
                                                    notes: '',
                                                });
                                                setSelectedEventType(null);
                                                setEventErrors({});
                                            }}
                                        >
                                            Cancelar
                                        </Button>
                                        <Button
                                            type="submit"
                                            variant="primary"
                                            size="sm"
                                        >
                                            Salvar Evento
                                        </Button>
                                    </div>
                                </form>
                            </div>
                        )}

                        {loading ? (
                            <div className="text-center py-12">
                                <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                                <p className="mt-2 text-sm text-gray-600">Carregando eventos...</p>
                            </div>
                        ) : events.length === 0 ? (
                            <div className="text-center py-12">
                                <svg className="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <h3 className="text-lg font-medium text-gray-900 mb-2">Nenhum evento encontrado</h3>
                                <p className="text-gray-500">Este funcionário ainda não possui eventos registrados.</p>
                            </div>
                        ) : (
                            <div className="space-y-4 max-h-[60vh] overflow-y-auto overflow-x-hidden pr-2">
                                {events.map((event, index) => (
                                    <div
                                        key={event.id}
                                        className={`relative bg-white border-2 rounded-lg p-4 hover:shadow-md transition-all duration-200 ${getEmployeeEventColor(event.event_type)}`}
                                    >
                                        {/* Linha conectora */}
                                        {index < events.length - 1 && (
                                            <div className="absolute left-8 top-20 bottom-0 w-0.5 bg-gray-200 -mb-4"></div>
                                        )}

                                        <div className="flex items-start space-x-4">
                                            {/* Ícone do tipo de evento */}
                                            <div className={`flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center border-2 relative z-10 bg-white ${getEmployeeEventColor(event.event_type)}`}>
                                                <span className="text-2xl">{getEmployeeEventIcon(event.event_type)}</span>
                                            </div>

                                            {/* Conteúdo do evento */}
                                            <div className="flex-1 min-w-0">
                                                <div className="flex items-start justify-between mb-2">
                                                    <div className="flex-1">
                                                        <div className="flex items-center gap-2 mb-1">
                                                            <span className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full border ${getEmployeeEventColor(event.event_type)}`}>
                                                                {event.event_type}
                                                            </span>
                                                        </div>
                                                        <h4 className="text-base font-bold text-gray-900">
                                                            {event.period}
                                                        </h4>
                                                    </div>

                                                    {/* Botão de ação */}
                                                    <div className="flex items-center space-x-2 ml-4">
                                                        <button
                                                            onClick={() => handleDeleteEventClick(event)}
                                                            className="p-2 text-red-600 hover:bg-red-50 rounded-full transition-colors"
                                                            title="Excluir evento"
                                                        >
                                                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </div>

                                                {/* Informações do evento */}
                                                <div className="grid grid-cols-2 gap-3 mb-2">
                                                    <div className="bg-white rounded-md p-2 border border-gray-100">
                                                        <div className="flex items-center space-x-1 mb-1">
                                                            <svg className="w-3 h-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                            </svg>
                                                            <span className="text-xs font-medium text-gray-500">Duração</span>
                                                        </div>
                                                        <p className="text-sm font-semibold text-gray-900">
                                                            {event.duration_in_days} {event.duration_in_days === 1 ? 'dia' : 'dias'}
                                                        </p>
                                                    </div>

                                                    {event.has_document && (
                                                        <div className="bg-white rounded-md p-2 border border-gray-100">
                                                            <div className="flex items-center space-x-1 mb-1">
                                                                <svg className="w-3 h-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                                                </svg>
                                                                <span className="text-xs font-medium text-gray-500">Documento</span>
                                                            </div>
                                                            <button
                                                                onClick={() => handleViewDocument(event)}
                                                                className="text-sm font-semibold text-indigo-600 hover:text-indigo-800 cursor-pointer"
                                                            >
                                                                Ver arquivo
                                                            </button>
                                                        </div>
                                                    )}
                                                </div>

                                                {/* Observações */}
                                                {event.notes && (
                                                    <div className="bg-amber-50 rounded-md p-2 border border-amber-100 mb-2">
                                                        <div className="flex items-center space-x-1 mb-1">
                                                            <svg className="w-3 h-3 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                                                            </svg>
                                                            <span className="text-xs font-medium text-amber-700">Observações</span>
                                                        </div>
                                                        <p className="text-sm text-gray-800">{event.notes}</p>
                                                    </div>
                                                )}

                                                {/* Metadados */}
                                                <div className="mt-2 flex items-center text-xs text-gray-500">
                                                    <svg className="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    <span>Registrado por {event.created_by} em {event.created_at}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                )}

                {/* Rodapé */}
                <div className="flex justify-end pt-4 border-t">
                    <Button
                        variant="outline"
                        onClick={onClose}
                    >
                        Fechar
                    </Button>
                </div>
            </div>

            {/* Contract Create Modal */}
            <ContractCreateModal
                show={isContractModalOpen}
                onClose={() => setIsContractModalOpen(false)}
                employeeId={employeeId}
                positions={positions}
                stores={stores}
                movementTypes={movementTypes}
                onSuccess={handleContractCreated}
            />

            {/* Contract Edit Modal */}
            <ContractEditModal
                show={isEditModalOpen}
                onClose={() => {
                    setIsEditModalOpen(false);
                    setSelectedContract(null);
                }}
                employeeId={employeeId}
                contract={selectedContract}
                positions={positions}
                stores={stores}
                movementTypes={movementTypes}
                onSuccess={handleContractUpdated}
            />

            {/* Delete Confirmation Modal */}
            <Modal show={isDeleteModalOpen} onClose={handleDeleteCancel} title="Confirmar Exclusão" maxWidth="md">
                <div className="space-y-4">
                    <div className="flex items-center space-x-3">
                        <div className="flex-shrink-0">
                            <div className="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                                <svg className="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                            </div>
                        </div>
                        <div>
                            <h3 className="text-lg font-semibold text-gray-900">
                                Tem certeza que deseja excluir este contrato?
                            </h3>
                            {contractToDelete && (
                                <p className="text-sm text-gray-600 mt-1">
                                    <strong>{contractToDelete.position}</strong> • {contractToDelete.date_range}
                                </p>
                            )}
                        </div>
                    </div>
                    <p className="text-sm text-gray-500">
                        Esta ação não pode ser desfeita. O contrato será permanentemente removido do histórico do funcionário.
                    </p>
                    <div className="flex justify-end space-x-3 pt-4 border-t">
                        <Button
                            variant="outline"
                            onClick={handleDeleteCancel}
                        >
                            Cancelar
                        </Button>
                        <Button
                            variant="danger"
                            onClick={handleDeleteConfirm}
                            icon={({ className }) => (
                                <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            )}
                        >
                            Excluir Contrato
                        </Button>
                    </div>
                </div>
            </Modal>

            {/* Document Viewer Modal */}
            <DocumentViewerModal
                show={isDocumentViewerOpen}
                onClose={() => setIsDocumentViewerOpen(false)}
                documentUrl={selectedDocument?.url}
                eventType={selectedDocument?.eventType}
            />

            {/* Export Events Modal */}
            <ExportEventsModal
                show={isExportModalOpen}
                onClose={() => setIsExportModalOpen(false)}
                employeeId={employeeId}
                employeeName={employee?.name}
                eventTypes={eventTypes}
            />

            {/* Delete Event Confirmation Modal */}
            <Modal show={isDeleteEventModalOpen} onClose={handleDeleteEventCancel} title="Confirmar Exclusão" maxWidth="md">
                <div className="space-y-4">
                    <div className="flex items-center space-x-3">
                        <div className="flex-shrink-0">
                            <div className="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                                <svg className="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                                </svg>
                            </div>
                        </div>
                        <div>
                            <h3 className="text-lg font-semibold text-gray-900">
                                Tem certeza que deseja excluir este evento?
                            </h3>
                            {eventToDelete && (
                                <p className="text-sm text-gray-600 mt-1">
                                    <strong>{eventToDelete.event_type}</strong> • {eventToDelete.period}
                                </p>
                            )}
                        </div>
                    </div>
                    <p className="text-sm text-gray-500">
                        Esta ação não pode ser desfeita. O evento será permanentemente removido do histórico do funcionário.
                    </p>
                    <div className="flex justify-end space-x-3 pt-4 border-t">
                        <Button
                            variant="outline"
                            onClick={handleDeleteEventCancel}
                        >
                            Cancelar
                        </Button>
                        <Button
                            variant="danger"
                            onClick={handleDeleteEventConfirm}
                            icon={({ className }) => (
                                <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            )}
                        >
                            Excluir Evento
                        </Button>
                    </div>
                </div>
            </Modal>
        </Modal>
    );
}
