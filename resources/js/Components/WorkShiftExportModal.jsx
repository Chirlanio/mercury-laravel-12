import { useState, useEffect } from 'react';
import Modal from './Modal';
import Button from './Button';

export default function WorkShiftExportModal({ show, onClose, employees, stores, types, currentFilters = {} }) {
    const [filters, setFilters] = useState({
        employee_ids: [],
        store_ids: [],
        type_ids: [],
        start_date: '',
        end_date: '',
    });
    const [isExporting, setIsExporting] = useState(false);

    // Preencher com filtros atuais da página quando o modal abre
    useEffect(() => {
        if (show) {
            const initialFilters = {
                employee_ids: [],
                store_ids: currentFilters.store ? [currentFilters.store] : [],
                type_ids: currentFilters.type ? [currentFilters.type] : [],
                start_date: '',
                end_date: '',
            };
            setFilters(initialFilters);
        }
    }, [show, currentFilters]);

    const handleEmployeeToggle = (employeeId) => {
        setFilters(prev => ({
            ...prev,
            employee_ids: prev.employee_ids.includes(employeeId)
                ? prev.employee_ids.filter(id => id !== employeeId)
                : [...prev.employee_ids, employeeId]
        }));
    };

    const handleStoreToggle = (storeCode) => {
        setFilters(prev => ({
            ...prev,
            store_ids: prev.store_ids.includes(storeCode)
                ? prev.store_ids.filter(id => id !== storeCode)
                : [...prev.store_ids, storeCode]
        }));
    };

    const handleTypeToggle = (typeValue) => {
        setFilters(prev => ({
            ...prev,
            type_ids: prev.type_ids.includes(typeValue)
                ? prev.type_ids.filter(id => id !== typeValue)
                : [...prev.type_ids, typeValue]
        }));
    };

    const handleSelectAllEmployees = () => {
        setFilters(prev => ({
            ...prev,
            employee_ids: employees.map(emp => emp.id)
        }));
    };

    const handleDeselectAllEmployees = () => {
        setFilters(prev => ({
            ...prev,
            employee_ids: []
        }));
    };

    const handleSelectAllStores = () => {
        setFilters(prev => ({
            ...prev,
            store_ids: stores.map(store => store.code)
        }));
    };

    const handleDeselectAllStores = () => {
        setFilters(prev => ({
            ...prev,
            store_ids: []
        }));
    };

    const handleSelectAllTypes = () => {
        setFilters(prev => ({
            ...prev,
            type_ids: types.map(type => type.value)
        }));
    };

    const handleDeselectAllTypes = () => {
        setFilters(prev => ({
            ...prev,
            type_ids: []
        }));
    };

    const handleExport = async () => {
        setIsExporting(true);

        try {
            const params = new URLSearchParams();

            if (filters.employee_ids.length > 0) {
                filters.employee_ids.forEach(id => {
                    params.append('employee_ids[]', id);
                });
            }

            if (filters.store_ids.length > 0) {
                filters.store_ids.forEach(id => {
                    params.append('store_ids[]', id);
                });
            }

            if (filters.type_ids.length > 0) {
                filters.type_ids.forEach(id => {
                    params.append('type_ids[]', id);
                });
            }

            if (filters.start_date) {
                params.append('start_date', filters.start_date);
            }

            if (filters.end_date) {
                params.append('end_date', filters.end_date);
            }

            const url = `/work-shifts/export?${params.toString()}`;

            // Create a temporary link to download the file
            const link = document.createElement('a');
            link.href = url;
            link.download = `jornadas_trabalho_${Date.now()}.xlsx`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            // Reset and close
            setFilters({
                employee_ids: [],
                store_ids: [],
                type_ids: [],
                start_date: '',
                end_date: '',
            });
            onClose();
        } catch (error) {
            console.error('Erro ao exportar jornadas:', error);
            alert('Erro ao exportar jornadas. Tente novamente.');
        } finally {
            setIsExporting(false);
        }
    };

    const handleClose = () => {
        setFilters({
            employee_ids: [],
            store_ids: [],
            type_ids: [],
            start_date: '',
            end_date: '',
        });
        onClose();
    };

    return (
        <Modal show={show} onClose={handleClose} maxWidth="4xl">
            <div className="p-6">
                <div className="flex justify-between items-center mb-6">
                    <div>
                        <h2 className="text-2xl font-bold text-gray-900">
                            Exportar Jornadas de Trabalho
                        </h2>
                        <p className="text-sm text-gray-600 mt-1">
                            Gere um relatório consolidado de jornadas de trabalho
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
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        {/* Employees Selection */}
                        <div>
                            <div className="flex justify-between items-center mb-3">
                                <label className="block text-sm font-medium text-gray-700">
                                    Funcionários
                                </label>
                                <div className="flex space-x-2">
                                    <button
                                        type="button"
                                        onClick={handleSelectAllEmployees}
                                        className="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                                    >
                                        Todos
                                    </button>
                                    <span className="text-gray-400">|</span>
                                    <button
                                        type="button"
                                        onClick={handleDeselectAllEmployees}
                                        className="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                                    >
                                        Limpar
                                    </button>
                                </div>
                            </div>
                            <div className="space-y-2 max-h-40 overflow-y-auto border rounded-lg p-2">
                                {employees.map((employee) => (
                                    <label
                                        key={employee.id}
                                        className="flex items-center p-2 hover:bg-gray-50 cursor-pointer transition-colors rounded"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={filters.employee_ids.includes(employee.id)}
                                            onChange={() => handleEmployeeToggle(employee.id)}
                                            className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                        />
                                        <span className="ml-3 text-sm font-medium text-gray-900">
                                            {employee.short_name || employee.name}
                                        </span>
                                    </label>
                                ))}
                            </div>
                            <p className="mt-2 text-xs text-gray-500">
                                Sem seleção = todos os funcionários
                            </p>
                        </div>

                        {/* Stores Selection */}
                        <div>
                            <div className="flex justify-between items-center mb-3">
                                <label className="block text-sm font-medium text-gray-700">
                                    Lojas
                                </label>
                                <div className="flex space-x-2">
                                    <button
                                        type="button"
                                        onClick={handleSelectAllStores}
                                        className="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                                    >
                                        Todas
                                    </button>
                                    <span className="text-gray-400">|</span>
                                    <button
                                        type="button"
                                        onClick={handleDeselectAllStores}
                                        className="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                                    >
                                        Limpar
                                    </button>
                                </div>
                            </div>
                            <div className="space-y-2 max-h-40 overflow-y-auto border rounded-lg p-2">
                                {stores.map((store) => (
                                    <label
                                        key={store.code}
                                        className="flex items-center p-2 hover:bg-gray-50 cursor-pointer transition-colors rounded"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={filters.store_ids.includes(store.code)}
                                            onChange={() => handleStoreToggle(store.code)}
                                            className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                        />
                                        <span className="ml-3 text-sm font-medium text-gray-900">
                                            {store.name}
                                        </span>
                                    </label>
                                ))}
                            </div>
                            <p className="mt-2 text-xs text-gray-500">
                                Sem seleção = todas as lojas
                            </p>
                        </div>

                        {/* Types Selection */}
                        <div>
                            <div className="flex justify-between items-center mb-3">
                                <label className="block text-sm font-medium text-gray-700">
                                    Tipos de Jornada
                                </label>
                                <div className="flex space-x-2">
                                    <button
                                        type="button"
                                        onClick={handleSelectAllTypes}
                                        className="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                                    >
                                        Todos
                                    </button>
                                    <span className="text-gray-400">|</span>
                                    <button
                                        type="button"
                                        onClick={handleDeselectAllTypes}
                                        className="text-xs text-indigo-600 hover:text-indigo-800 font-medium"
                                    >
                                        Limpar
                                    </button>
                                </div>
                            </div>
                            <div className="space-y-2 max-h-40 overflow-y-auto border rounded-lg p-2">
                                {types.map((type) => (
                                    <label
                                        key={type.value}
                                        className="flex items-center p-2 hover:bg-gray-50 cursor-pointer transition-colors rounded"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={filters.type_ids.includes(type.value)}
                                            onChange={() => handleTypeToggle(type.value)}
                                            className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
                                        />
                                        <span className="ml-3 text-sm font-medium text-gray-900">
                                            {type.label}
                                        </span>
                                    </label>
                                ))}
                            </div>
                            <p className="mt-2 text-xs text-gray-500">
                                Sem seleção = todos os tipos
                            </p>
                        </div>
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
                            Sem datas = todos os períodos
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
                            {isExporting ? 'Exportando...' : 'Exportar Excel'}
                        </Button>
                    </div>
                </div>
            </div>
        </Modal>
    );
}
