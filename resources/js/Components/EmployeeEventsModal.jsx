import { useState, useEffect } from 'react';
import Modal from './Modal';
import Button from './Button';

export default function EmployeeEventsModal({ employee, isOpen, onClose }) {
    const [events, setEvents] = useState([]);
    const [eventTypes, setEventTypes] = useState([]);
    const [loading, setLoading] = useState(false);
    const [isCreating, setIsCreating] = useState(false);
    const [formData, setFormData] = useState({
        event_type_id: '',
        start_date: '',
        end_date: '',
        document: null,
        notes: '',
    });
    const [errors, setErrors] = useState({});
    const [selectedEventType, setSelectedEventType] = useState(null);

    useEffect(() => {
        if (isOpen && employee) {
            fetchEvents();
        }
    }, [isOpen, employee]);

    const fetchEvents = async () => {
        try {
            setLoading(true);
            const response = await fetch(`/employees/${employee.id}/events`);
            const data = await response.json();
            setEvents(data.events);
            setEventTypes(data.event_types);
        } catch (error) {
            console.error('Erro ao carregar eventos:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleEventTypeChange = (e) => {
        const typeId = e.target.value;
        setFormData({ ...formData, event_type_id: typeId });

        const type = eventTypes.find(t => t.id === parseInt(typeId));
        setSelectedEventType(type);

        // Limpar campos que não são necessários
        if (type && !type.requires_date_range) {
            setFormData(prev => ({ ...prev, end_date: '' }));
        }
    };

    const handleFileChange = (e) => {
        setFormData({ ...formData, document: e.target.files[0] });
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setErrors({});

        const formDataToSend = new FormData();
        formDataToSend.append('event_type_id', formData.event_type_id);
        formDataToSend.append('start_date', formData.start_date);

        if (formData.end_date) {
            formDataToSend.append('end_date', formData.end_date);
        }

        if (formData.document) {
            formDataToSend.append('document', formData.document);
        }

        if (formData.notes) {
            formDataToSend.append('notes', formData.notes);
        }

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            const response = await fetch(`/employees/${employee.id}/events`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: formDataToSend,
            });

            const data = await response.json();

            if (!response.ok) {
                if (data.errors) {
                    setErrors(data.errors);
                }
                throw new Error(data.message || 'Erro ao criar evento');
            }

            // Resetar formulário
            setFormData({
                event_type_id: '',
                start_date: '',
                end_date: '',
                document: null,
                notes: '',
            });
            setSelectedEventType(null);
            setIsCreating(false);

            // Recarregar eventos
            fetchEvents();
        } catch (error) {
            console.error('Erro ao criar evento:', error);
        }
    };

    const handleDelete = async (eventId) => {
        if (!confirm('Deseja realmente excluir este evento?')) {
            return;
        }

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            const response = await fetch(`/employees/${employee.id}/events/${eventId}`, {
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
        } catch (error) {
            console.error('Erro ao excluir evento:', error);
        }
    };

    const getEventTypeIcon = (eventType) => {
        const icons = {
            'Férias': '🏖️',
            'Licença': '🏥',
            'Falta': '❌',
            'Atestado Médico': '📄',
        };
        return icons[eventType] || '📋';
    };

    const getEventTypeColor = (eventType) => {
        const colors = {
            'Férias': 'bg-blue-100 border-blue-300',
            'Licença': 'bg-yellow-100 border-yellow-300',
            'Falta': 'bg-red-100 border-red-300',
            'Atestado Médico': 'bg-green-100 border-green-300',
        };
        return colors[eventType] || 'bg-gray-100 border-gray-300';
    };

    return (
        <Modal show={isOpen} onClose={onClose} maxWidth="6xl">
            <div className="p-6">
                <div className="flex justify-between items-center mb-6">
                    <div>
                        <h2 className="text-2xl font-bold text-gray-900">
                            Eventos - {employee?.name}
                        </h2>
                        <p className="text-sm text-gray-600 mt-1">
                            Gerencie férias, licenças, faltas e atestados
                        </p>
                    </div>
                    <button
                        onClick={onClose}
                        className="text-gray-400 hover:text-gray-600 transition-colors"
                    >
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {!isCreating && (
                    <div className="mb-4">
                        <Button
                            variant="primary"
                            size="sm"
                            onClick={() => setIsCreating(true)}
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

                {isCreating && (
                    <div className="bg-indigo-50 border border-indigo-200 rounded-lg p-4 mb-6">
                        <h3 className="text-lg font-semibold text-indigo-900 mb-4">Novo Evento</h3>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Tipo de Evento *
                                </label>
                                <select
                                    value={formData.event_type_id}
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
                                {errors.event_type_id && (
                                    <p className="mt-1 text-sm text-red-600">{errors.event_type_id[0]}</p>
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
                                                value={formData.start_date}
                                                onChange={(e) => setFormData({ ...formData, start_date: e.target.value })}
                                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                required
                                            />
                                            {errors.start_date && (
                                                <p className="mt-1 text-sm text-red-600">{errors.start_date[0]}</p>
                                            )}
                                        </div>

                                        {selectedEventType.requires_date_range && (
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                                    Data de Fim *
                                                </label>
                                                <input
                                                    type="date"
                                                    value={formData.end_date}
                                                    onChange={(e) => setFormData({ ...formData, end_date: e.target.value })}
                                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    required
                                                />
                                                {errors.end_date && (
                                                    <p className="mt-1 text-sm text-red-600">{errors.end_date[0]}</p>
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
                                            {errors.document && (
                                                <p className="mt-1 text-sm text-red-600">{errors.document[0]}</p>
                                            )}
                                        </div>
                                    )}

                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            Observações
                                        </label>
                                        <textarea
                                            value={formData.notes}
                                            onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                                            rows={3}
                                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            maxLength={1000}
                                        />
                                        {errors.notes && (
                                            <p className="mt-1 text-sm text-red-600">{errors.notes[0]}</p>
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
                                        setIsCreating(false);
                                        setFormData({
                                            event_type_id: '',
                                            start_date: '',
                                            end_date: '',
                                            document: null,
                                            notes: '',
                                        });
                                        setSelectedEventType(null);
                                        setErrors({});
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

                <div className="space-y-3 max-h-[50vh] overflow-y-auto pr-2">
                    {loading ? (
                        <div className="text-center py-8">
                            <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                            <p className="mt-2 text-sm text-gray-600">Carregando eventos...</p>
                        </div>
                    ) : events.length === 0 ? (
                        <div className="text-center py-8">
                            <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <p className="mt-2 text-sm text-gray-600">Nenhum evento registrado</p>
                        </div>
                    ) : (
                        events.map((event) => (
                            <div
                                key={event.id}
                                className={`relative border-2 rounded-lg p-4 ${getEventTypeColor(event.event_type)}`}
                            >
                                <div className="flex justify-between items-start">
                                    <div className="flex-1">
                                        <div className="flex items-center gap-2 mb-2">
                                            <span className="text-2xl">{getEventTypeIcon(event.event_type)}</span>
                                            <h4 className="font-bold text-lg text-gray-900">{event.event_type}</h4>
                                        </div>

                                        <div className="grid grid-cols-2 gap-4 text-sm">
                                            <div>
                                                <span className="font-semibold text-gray-700">Período:</span>
                                                <p className="text-gray-900">{event.period}</p>
                                            </div>
                                            <div>
                                                <span className="font-semibold text-gray-700">Duração:</span>
                                                <p className="text-gray-900">
                                                    {event.duration_in_days} {event.duration_in_days === 1 ? 'dia' : 'dias'}
                                                </p>
                                            </div>
                                        </div>

                                        {event.has_document && (
                                            <div className="mt-2">
                                                <a
                                                    href={event.document_url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800"
                                                >
                                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                                                    </svg>
                                                    Ver documento
                                                </a>
                                            </div>
                                        )}

                                        {event.notes && (
                                            <div className="mt-2 p-2 bg-white bg-opacity-50 rounded">
                                                <span className="text-xs font-semibold text-gray-700">Observações:</span>
                                                <p className="text-sm text-gray-800 mt-1">{event.notes}</p>
                                            </div>
                                        )}

                                        <div className="mt-2 text-xs text-gray-600">
                                            Registrado por {event.created_by} em {event.created_at}
                                        </div>
                                    </div>

                                    <Button
                                        variant="danger"
                                        size="sm"
                                        iconOnly={true}
                                        onClick={() => handleDelete(event.id)}
                                        icon={({ className }) => (
                                            <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        )}
                                        title="Excluir evento"
                                    />
                                </div>
                            </div>
                        ))
                    )}
                </div>
            </div>
        </Modal>
    );
}
