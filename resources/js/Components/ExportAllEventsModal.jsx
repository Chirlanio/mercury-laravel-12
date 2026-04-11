import { useState } from 'react';
import StandardModal from '@/Components/StandardModal';
import Button from '@/Components/Button';
import {
    DocumentArrowDownIcon,
    CalendarDaysIcon,
    BuildingStorefrontIcon,
    ClipboardDocumentListIcon,
    CheckCircleIcon,
    XMarkIcon,
} from '@heroicons/react/24/outline';

export default function ExportAllEventsModal({ show, onClose, eventTypes, stores }) {
    const [filters, setFilters] = useState({
        event_type_ids: [],
        store_ids: [],
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

    const handleStoreToggle = (storeId) => {
        setFilters(prev => ({
            ...prev,
            store_ids: prev.store_ids.includes(storeId)
                ? prev.store_ids.filter(id => id !== storeId)
                : [...prev.store_ids, storeId]
        }));
    };

    const handleSelectAllEventTypes = () => {
        setFilters(prev => ({
            ...prev,
            event_type_ids: eventTypes.map(type => type.id)
        }));
    };

    const handleDeselectAllEventTypes = () => {
        setFilters(prev => ({
            ...prev,
            event_type_ids: []
        }));
    };

    const handleSelectAllStores = () => {
        setFilters(prev => ({
            ...prev,
            store_ids: stores.map(store => store.id)
        }));
    };

    const handleDeselectAllStores = () => {
        setFilters(prev => ({
            ...prev,
            store_ids: []
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

            if (filters.store_ids.length > 0) {
                filters.store_ids.forEach(id => {
                    params.append('store_ids[]', id);
                });
            }

            if (filters.start_date) {
                params.append('start_date', filters.start_date);
            }

            if (filters.end_date) {
                params.append('end_date', filters.end_date);
            }

            const url = `/employees/events/export?${params.toString()}`;

            // Create a temporary link to download the file
            const link = document.createElement('a');
            link.href = url;
            link.download = `eventos_todos_funcionarios_${Date.now()}.pdf`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            // Reset and close
            handleClose();
        } catch (error) {
            console.error('Erro ao exportar eventos:', error);
        } finally {
            setIsExporting(false);
        }
    };

    const handleClose = () => {
        setFilters({
            event_type_ids: [],
            store_ids: [],
            start_date: '',
            end_date: '',
        });
        onClose();
    };

    return (
        <StandardModal
            show={show}
            onClose={handleClose}
            title="Exportar Eventos Consolidado"
            subtitle="Gere um relatório de eventos de todos os funcionários"
            headerColor="bg-green-600"
            headerIcon={<DocumentArrowDownIcon className="h-6 w-6" />}
            maxWidth="4xl"
            footer={
                <StandardModal.Footer
                    onCancel={handleClose}
                    onSubmit={handleExport}
                    submitLabel={isExporting ? 'Exportando...' : 'Exportar PDF'}
                    submitIcon={DocumentArrowDownIcon}
                    processing={isExporting}
                />
            }
        >
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {/* Event Types Selection */}
                <StandardModal.Section 
                    title="Tipos de Eventos" 
                    icon={<ClipboardDocumentListIcon className="h-4 w-4" />}
                    actions={
                        <div className="flex gap-2">
                            <button onClick={handleSelectAllEventTypes} className="text-[10px] font-bold text-indigo-600 uppercase hover:underline">Todos</button>
                            <span className="text-gray-300">|</span>
                            <button onClick={handleDeselectAllEventTypes} className="text-[10px] font-bold text-indigo-600 uppercase hover:underline">Limpar</button>
                        </div>
                    }
                >
                    <div className="space-y-1 max-h-48 overflow-y-auto border border-gray-100 rounded-lg p-2 bg-gray-50/50">
                        {eventTypes.map((type) => (
                            <label
                                key={type.id}
                                className={`flex items-center p-2 hover:bg-white cursor-pointer transition-colors rounded-md border ${filters.event_type_ids.includes(type.id) ? 'bg-white border-indigo-200 shadow-sm' : 'border-transparent'}`}
                            >
                                <input
                                    type="checkbox"
                                    checked={filters.event_type_ids.includes(type.id)}
                                    onChange={() => handleEventTypeToggle(type.id)}
                                    className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                />
                                <span className={`ml-3 text-sm font-medium ${filters.event_type_ids.includes(type.id) ? 'text-indigo-900' : 'text-gray-700'}`}>
                                    {type.name}
                                </span>
                                {filters.event_type_ids.includes(type.id) && (
                                    <CheckCircleIcon className="h-4 w-4 ml-auto text-indigo-500" />
                                )}
                            </label>
                        ))}
                    </div>
                    <p className="mt-2 text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                        Sem seleção = todos os tipos
                    </p>
                </StandardModal.Section>

                {/* Stores Selection */}
                <StandardModal.Section 
                    title="Lojas" 
                    icon={<BuildingStorefrontIcon className="h-4 w-4" />}
                    actions={
                        <div className="flex gap-2">
                            <button onClick={handleSelectAllStores} className="text-[10px] font-bold text-indigo-600 uppercase hover:underline">Todas</button>
                            <span className="text-gray-300">|</span>
                            <button onClick={handleDeselectAllStores} className="text-[10px] font-bold text-indigo-600 uppercase hover:underline">Limpar</button>
                        </div>
                    }
                >
                    <div className="space-y-1 max-h-48 overflow-y-auto border border-gray-100 rounded-lg p-2 bg-gray-50/50">
                        {stores.map((store) => (
                            <label
                                key={store.id}
                                className={`flex items-center p-2 hover:bg-white cursor-pointer transition-colors rounded-md border ${filters.store_ids.includes(store.id) ? 'bg-white border-indigo-200 shadow-sm' : 'border-transparent'}`}
                            >
                                <input
                                    type="checkbox"
                                    checked={filters.store_ids.includes(store.id)}
                                    onChange={() => handleStoreToggle(store.id)}
                                    className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                />
                                <span className={`ml-3 text-sm font-medium ${filters.store_ids.includes(store.id) ? 'text-indigo-900' : 'text-gray-700'}`}>
                                    {store.code} - {store.name}
                                </span>
                                {filters.store_ids.includes(store.id) && (
                                    <CheckCircleIcon className="h-4 w-4 ml-auto text-indigo-500" />
                                )}
                            </label>
                        ))}
                    </div>
                    <p className="mt-2 text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                        Sem seleção = todas as lojas
                    </p>
                </StandardModal.Section>
            </div>

            {/* Date Range */}
            <StandardModal.Section title="Período de Referência" icon={<CalendarDaysIcon className="h-4 w-4" />}>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label htmlFor="start_date" className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">
                            Data Inicial
                        </label>
                        <input
                            type="date"
                            id="start_date"
                            value={filters.start_date}
                            onChange={(e) => setFilters({ ...filters, start_date: e.target.value })}
                            className="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        />
                    </div>
                    <div>
                        <label htmlFor="end_date" className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">
                            Data Final
                        </label>
                        <input
                            type="date"
                            id="end_date"
                            value={filters.end_date}
                            onChange={(e) => setFilters({ ...filters, end_date: e.target.value })}
                            className="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        />
                    </div>
                </div>
                <p className="mt-2 text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                    Sem datas = todos os períodos
                </p>
            </StandardModal.Section>
        </StandardModal>
    );
}
