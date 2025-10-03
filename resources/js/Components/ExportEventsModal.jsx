import { useState } from 'react';
import Modal from './Modal';
import Button from './Button';

export default function ExportEventsModal({ show, onClose, employeeId, employeeName, eventTypes }) {
    const [filters, setFilters] = useState({
        event_type_ids: [],
        start_date: '',
        end_date: '',
    });
    const [isExporting, setIsExporting] = useState(false);

    const handleEventTypeToggle = (typeId) => {
        setFilters(prev => ({
            ...prev,
            event_type_ids: prev.event_type_ids.includes(typeId)
                ? prev.event_type_ids.filter(id => id !== typeId)
                : [...prev.event_type_ids, typeId]
        }));
    };

    const handleSelectAll = () => {
        setFilters(prev => ({
            ...prev,
            event_type_ids: eventTypes.map(type => type.id)
        }));
    };

    const handleDeselectAll = () => {
        setFilters(prev => ({
            ...prev,
            event_type_ids: []
        }));
    };

    const handleExport = async () => {
        setIsExporting(true);

        try {
            const params = new URLSearchParams();

            if (filters.event_type_ids.length > 0) {
                filters.event_type_ids.forEach(id => {
                    params.append('event_type_ids[]', id);
                });
            }

            if (filters.start_date) {
                params.append('start_date', filters.start_date);
            }

            if (filters.end_date) {
                params.append('end_date', filters.end_date);
            }

            const url = `/employees/${employeeId}/events/export?${params.toString()}`;

            // Create a temporary link to download the file
            const link = document.createElement('a');
            link.href = url;
            link.download = `eventos_${employeeName}_${Date.now()}.pdf`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            // Reset and close
            setFilters({
                event_type_ids: [],
                start_date: '',
                end_date: '',
            });
            onClose();
        } catch (error) {
            console.error('Erro ao exportar eventos:', error);
            alert('Erro ao exportar eventos. Tente novamente.');
        } finally {
            setIsExporting(false);
        }
    };

    const handleClose = () => {
        setFilters({
            event_type_ids: [],
            start_date: '',
            end_date: '',
        });
        onClose();
    };

    return (
        <Modal show={show} onClose={handleClose} maxWidth="2xl">
            <div className="p-6">
                <div className="flex justify-between items-center mb-6">
                    <div>
                        <h2 className="text-2xl font-bold text-gray-900">
                            Exportar Eventos
                        </h2>
                        <p className="text-sm text-gray-600 mt-1">
                            {employeeName}
                        </p>
                    </div>
                    <button
                        onClick={handleClose}
                        className="text-gray-400 hover:text-gray-600 transition-colors"
                    >
                        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div className="space-y-6">
                    {/* Event Types Selection */}
                    <div>
                        <div className="flex justify-between items-center mb-3">
                            <label className="block text-sm font-medium text-gray-700">
                                Tipos de Eventos
                            </label>
                            <div className="flex space-x-2">
                                <button
                                    type="button"
                                    onClick={handleSelectAll}
                                    className="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                                >
                                    Selecionar todos
                                </button>
                                <span className="text-gray-400">|</span>
                                <button
                                    type="button"
                                    onClick={handleDeselectAll}
                                    className="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                                >
                                    Limpar seleção
                                </button>
                            </div>
                        </div>
                        <div className="space-y-2">
                            {eventTypes.map((type) => (
                                <label
                                    key={type.id}
                                    className="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer transition-colors"
                                >
                                    <input
                                        type="checkbox"
                                        checked={filters.event_type_ids.includes(type.id)}
                                        onChange={() => handleEventTypeToggle(type.id)}
                                        className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                    />
                                    <span className="ml-3 text-sm font-medium text-gray-900">
                                        {type.name}
                                    </span>
                                </label>
                            ))}
                        </div>
                        <p className="mt-2 text-xs text-gray-500">
                            Se nenhum tipo for selecionado, todos os eventos serão exportados
                        </p>
                    </div>

                    {/* Date Range */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-3">
                            Período
                        </label>
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label htmlFor="start_date" className="block text-xs text-gray-600 mb-1">
                                    Data Inicial
                                </label>
                                <input
                                    type="date"
                                    id="start_date"
                                    value={filters.start_date}
                                    onChange={(e) => setFilters({ ...filters, start_date: e.target.value })}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                />
                            </div>
                            <div>
                                <label htmlFor="end_date" className="block text-xs text-gray-600 mb-1">
                                    Data Final
                                </label>
                                <input
                                    type="date"
                                    id="end_date"
                                    value={filters.end_date}
                                    onChange={(e) => setFilters({ ...filters, end_date: e.target.value })}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                />
                            </div>
                        </div>
                        <p className="mt-2 text-xs text-gray-500">
                            Se nenhuma data for informada, todos os períodos serão exportados
                        </p>
                    </div>

                    {/* Actions */}
                    <div className="flex justify-end space-x-3 pt-4 border-t">
                        <Button
                            variant="outline"
                            onClick={handleClose}
                            disabled={isExporting}
                        >
                            Cancelar
                        </Button>
                        <Button
                            variant="primary"
                            onClick={handleExport}
                            disabled={isExporting}
                            icon={({ className }) => (
                                <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            )}
                        >
                            {isExporting ? 'Exportando...' : 'Exportar PDF'}
                        </Button>
                    </div>
                </div>
            </div>
        </Modal>
    );
}
