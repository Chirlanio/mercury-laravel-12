import { useState } from 'react';
import { useForm, router } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import Button from '@/Components/Button';

export default function WorkShiftCreateModal({ isOpen, onClose, onSuccess, employees = [] }) {
    const [isSubmitting, setIsSubmitting] = useState(false);
    const { data, setData, errors, reset, clearErrors, setError } = useForm({
        employee_id: '',
        date: '',
        start_time: '',
        end_time: '',
        type: '',
    });

    const types = [
        { value: 'abertura', label: 'Abertura' },
        { value: 'fechamento', label: 'Fechamento' },
        { value: 'integral', label: 'Integral' },
    ];

    const handleSubmit = (e) => {
        e.preventDefault();
        setIsSubmitting(true);

        router.post('/work-shifts', data, {
            preserveState: false,
            preserveScroll: false,
            onSuccess: () => {
                setIsSubmitting(false);
                reset();
                clearErrors();
                if (onSuccess) {
                    onSuccess();
                }
                onClose();
            },
            onError: (errors) => {
                console.error('Erro ao criar jornada:', errors);
                setIsSubmitting(false);
                setError(errors);
            },
            onFinish: () => {
                setIsSubmitting(false);
            }
        });
    };

    const handleClose = () => {
        reset();
        clearErrors();
        onClose();
    };

    return (
        <Modal show={isOpen} onClose={handleClose} title="Cadastrar Jornada" maxWidth="85vw">
            <form onSubmit={handleSubmit} className="space-y-6">
                {/* Informações da Jornada */}
                <div className="bg-gray-50 p-4 rounded-lg">
                    <h4 className="text-sm font-medium text-gray-900 mb-4">
                        Informações da Jornada
                    </h4>

                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div className="md:col-span-3 lg:col-span-2">
                            <label htmlFor="employee_id" className="block text-sm font-medium text-gray-700 mb-1">
                                Funcionário *
                            </label>
                            <select
                                id="employee_id"
                                value={data.employee_id}
                                onChange={(e) => setData('employee_id', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 ${
                                    errors.employee_id ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'
                                }`}
                                required
                            >
                                <option value="">Selecione um funcionário</option>
                                {employees.map((employee) => (
                                    <option key={employee.id} value={employee.id}>
                                        {employee.name}
                                    </option>
                                ))}
                            </select>
                            {errors.employee_id && <p className="mt-1 text-sm text-red-600">{errors.employee_id}</p>}
                        </div>

                        <div>
                            <label htmlFor="date" className="block text-sm font-medium text-gray-700 mb-1">
                                Data *
                            </label>
                            <input
                                type="date"
                                id="date"
                                value={data.date}
                                onChange={(e) => setData('date', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 ${
                                    errors.date ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'
                                }`}
                                required
                            />
                            {errors.date && <p className="mt-1 text-sm text-red-600">{errors.date}</p>}
                        </div>

                        <div>
                            <label htmlFor="type" className="block text-sm font-medium text-gray-700 mb-1">
                                Tipo de Jornada *
                            </label>
                            <select
                                id="type"
                                value={data.type}
                                onChange={(e) => setData('type', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 ${
                                    errors.type ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'
                                }`}
                                required
                            >
                                <option value="">Selecione o tipo</option>
                                {types.map((type) => (
                                    <option key={type.value} value={type.value}>
                                        {type.label}
                                    </option>
                                ))}
                            </select>
                            {errors.type && <p className="mt-1 text-sm text-red-600">{errors.type}</p>}
                        </div>

                        <div>
                            <label htmlFor="start_time" className="block text-sm font-medium text-gray-700 mb-1">
                                Hora de Início *
                            </label>
                            <input
                                type="time"
                                id="start_time"
                                value={data.start_time}
                                onChange={(e) => setData('start_time', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 ${
                                    errors.start_time ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'
                                }`}
                                required
                            />
                            {errors.start_time && <p className="mt-1 text-sm text-red-600">{errors.start_time}</p>}
                        </div>

                        <div>
                            <label htmlFor="end_time" className="block text-sm font-medium text-gray-700 mb-1">
                                Hora de Término *
                            </label>
                            <input
                                type="time"
                                id="end_time"
                                value={data.end_time}
                                onChange={(e) => setData('end_time', e.target.value)}
                                className={`w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 ${
                                    errors.end_time ? 'border-red-300 focus:ring-red-500' : 'border-gray-300'
                                }`}
                                required
                            />
                            {errors.end_time && <p className="mt-1 text-sm text-red-600">{errors.end_time}</p>}
                        </div>
                    </div>
                </div>

                {/* Ações */}
                <div className="flex justify-end space-x-3 pt-4 border-t">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={handleClose}
                        disabled={isSubmitting}
                    >
                        Cancelar
                    </Button>

                    <Button
                        type="submit"
                        variant="primary"
                        loading={isSubmitting}
                        icon={isSubmitting ? null : ({ className }) => (
                            <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                        )}
                    >
                        {isSubmitting ? 'Salvando...' : 'Cadastrar Jornada'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
