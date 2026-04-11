import { useState, useEffect } from 'react';
import StandardModal from '@/Components/StandardModal';
import {
    DocumentArrowDownIcon,
    PrinterIcon,
    UserIcon,
    BuildingStorefrontIcon,
    ClockIcon,
    CalendarDaysIcon,
    CheckCircleIcon,
} from '@heroicons/react/24/outline';

export default function WorkShiftExportModal({ show, onClose, employees, stores, types, currentFilters = {} }) {
    const [filters, setFilters] = useState({
        employee_ids: [],
        store_ids: [],
        type_ids: [],
        start_date: '',
        end_date: '',
    });
    const [isExporting, setIsExporting] = useState(false);

    useEffect(() => {
        if (show) {
            setFilters({
                employee_ids: [],
                store_ids: currentFilters.store ? [currentFilters.store] : [],
                type_ids: currentFilters.type ? [currentFilters.type] : [],
                start_date: '',
                end_date: '',
            });
        }
    }, [show, currentFilters]);

    const handleToggle = (field, value) => {
        setFilters(prev => ({
            ...prev,
            [field]: prev[field].includes(value)
                ? prev[field].filter(v => v !== value)
                : [...prev[field], value]
        }));
    };

    const handleSelectAll = (field, allValues) => {
        setFilters(prev => ({ ...prev, [field]: allValues }));
    };

    const handleClear = (field) => {
        setFilters(prev => ({ ...prev, [field]: [] }));
    };

    const runAction = async (actionUrl, fileName) => {
        setIsExporting(true);
        try {
            const params = new URLSearchParams();
            if (filters.employee_ids.length > 0) filters.employee_ids.forEach(id => params.append('employee_ids[]', id));
            if (filters.store_ids.length > 0) filters.store_ids.forEach(id => params.append('store_ids[]', id));
            if (filters.type_ids.length > 0) filters.type_ids.forEach(id => params.append('type_ids[]', id));
            if (filters.start_date) params.append('start_date', filters.start_date);
            if (filters.end_date) params.append('end_date', filters.end_date);

            const link = document.createElement('a');
            link.href = `${actionUrl}?${params.toString()}`;
            link.download = fileName;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            handleClose();
        } catch (error) {
            console.error('Erro na ação:', error);
        } finally {
            setIsExporting(false);
        }
    };

    const handleClose = () => {
        setFilters({ employee_ids: [], store_ids: [], type_ids: [], start_date: '', end_date: '' });
        onClose();
    };

    const SectionHeader = ({ title, field, allValues }) => (
        <div className="flex justify-between items-center mb-2 px-1">
            <span className="text-[10px] font-bold text-gray-400 uppercase tracking-wider">{title}</span>
            <div className="flex gap-2">
                <button onClick={() => handleSelectAll(field, allValues)} className="text-[10px] font-bold text-indigo-600 uppercase hover:underline">Todos</button>
                <span className="text-gray-300">|</span>
                <button onClick={() => handleClear(field)} className="text-[10px] font-bold text-indigo-600 uppercase hover:underline">Limpar</button>
            </div>
        </div>
    );

    return (
        <StandardModal
            show={show}
            onClose={handleClose}
            title="Exportar Jornadas"
            subtitle="Relatórios consolidados de turnos"
            headerColor="bg-green-600"
            headerIcon={<DocumentArrowDownIcon className="h-6 w-6" />}
            maxWidth="5xl"
            footer={
                <StandardModal.Footer onCancel={handleClose}>
                    <div className="flex gap-3">
                        <button
                            onClick={() => runAction('/work-shifts/print-summary', `resumo_jornadas_${Date.now()}.pdf`)}
                            disabled={isExporting}
                            className="inline-flex items-center px-4 py-2 bg-gray-800 text-white text-sm font-bold rounded-lg hover:bg-gray-900 transition disabled:opacity-50"
                        >
                            <PrinterIcon className="w-4 h-4 mr-2" />
                            Imprimir PDF
                        </button>
                        <button
                            onClick={() => runAction('/work-shifts/export', `jornadas_${Date.now()}.xlsx`)}
                            disabled={isExporting}
                            className="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-bold rounded-lg hover:bg-green-700 transition disabled:opacity-50"
                        >
                            <DocumentArrowDownIcon className="w-4 h-4 mr-2" />
                            {isExporting ? 'Processando...' : 'Exportar Excel'}
                        </button>
                    </div>
                </StandardModal.Footer>
            }
        >
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                <StandardModal.Section title="Funcionários" icon={<UserIcon className="h-4 w-4" />}>
                    <SectionHeader title="Selecionar" field="employee_ids" allValues={employees.map(e => e.id)} />
                    <div className="space-y-1 max-h-48 overflow-y-auto border border-gray-100 rounded-lg p-2 bg-gray-50/50">
                        {employees.map((emp) => (
                            <label key={emp.id} className={`flex items-center p-2 hover:bg-white cursor-pointer transition rounded-md border ${filters.employee_ids.includes(emp.id) ? 'bg-white border-indigo-200 shadow-sm' : 'border-transparent'}`}>
                                <input type="checkbox" checked={filters.employee_ids.includes(emp.id)} onChange={() => handleToggle('employee_ids', emp.id)} className="h-4 w-4 text-indigo-600 border-gray-300 rounded" />
                                <span className="ml-3 text-sm font-medium text-gray-700">{emp.short_name || emp.name}</span>
                                {filters.employee_ids.includes(emp.id) && <CheckCircleIcon className="h-4 w-4 ml-auto text-indigo-500" />}
                            </label>
                        ))}
                    </div>
                </StandardModal.Section>

                <StandardModal.Section title="Lojas" icon={<BuildingStorefrontIcon className="h-4 w-4" />}>
                    <SectionHeader title="Selecionar" field="store_ids" allValues={stores.map(s => s.code)} />
                    <div className="space-y-1 max-h-48 overflow-y-auto border border-gray-100 rounded-lg p-2 bg-gray-50/50">
                        {stores.map((store) => (
                            <label key={store.code} className={`flex items-center p-2 hover:bg-white cursor-pointer transition rounded-md border ${filters.store_ids.includes(store.code) ? 'bg-white border-indigo-200 shadow-sm' : 'border-transparent'}`}>
                                <input type="checkbox" checked={filters.store_ids.includes(store.code)} onChange={() => handleToggle('store_ids', store.code)} className="h-4 w-4 text-indigo-600 border-gray-300 rounded" />
                                <span className="ml-3 text-sm font-medium text-gray-700">{store.name}</span>
                                {filters.store_ids.includes(store.code) && <CheckCircleIcon className="h-4 w-4 ml-auto text-indigo-500" />}
                            </label>
                        ))}
                    </div>
                </StandardModal.Section>

                <StandardModal.Section title="Tipos" icon={<ClockIcon className="h-4 w-4" />}>
                    <SectionHeader title="Selecionar" field="type_ids" allValues={types.map(t => t.value)} />
                    <div className="space-y-1 max-h-48 overflow-y-auto border border-gray-100 rounded-lg p-2 bg-gray-50/50">
                        {types.map((type) => (
                            <label key={type.value} className={`flex items-center p-2 hover:bg-white cursor-pointer transition rounded-md border ${filters.type_ids.includes(type.value) ? 'bg-white border-indigo-200 shadow-sm' : 'border-transparent'}`}>
                                <input type="checkbox" checked={filters.type_ids.includes(type.value)} onChange={() => handleToggle('type_ids', type.value)} className="h-4 w-4 text-indigo-600 border-gray-300 rounded" />
                                <span className="ml-3 text-sm font-medium text-gray-700">{type.label}</span>
                                {filters.type_ids.includes(type.value) && <CheckCircleIcon className="h-4 w-4 ml-auto text-indigo-500" />}
                            </label>
                        ))}
                    </div>
                </StandardModal.Section>
            </div>

            <StandardModal.Section title="Período" icon={<CalendarDaysIcon className="h-4 w-4" />}>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Data Inicial</label>
                        <input type="date" value={filters.start_date} onChange={(e) => setFilters({ ...filters, start_date: e.target.value })} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    </div>
                    <div>
                        <label className="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1">Data Final</label>
                        <input type="date" value={filters.end_date} onChange={(e) => setFilters({ ...filters, end_date: e.target.value })} className="w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
                    </div>
                </div>
            </StandardModal.Section>
        </StandardModal>
    );
}
