import { useState, useEffect } from 'react';
import { useForm, router } from '@inertiajs/react';
import StandardModal from '@/Components/StandardModal';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import { PencilSquareIcon } from '@heroicons/react/24/outline';

export default function WorkShiftEditModal({ show, onClose, onSuccess, workShift, employees = [] }) {
    const [isSubmitting, setIsSubmitting] = useState(false);
    const { data, setData, errors, reset, clearErrors, setError } = useForm({
        employee_id: '',
        date: '',
        start_time: '',
        end_time: '',
        type: '',
    });

    useEffect(() => {
        if (workShift && show) {
            setData({
                employee_id: workShift.employee_id || '',
                date: formatDateForInput(workShift.date) || '',
                start_time: workShift.start_time || '',
                end_time: workShift.end_time || '',
                type: workShift.type || '',
            });
        }
    }, [workShift, show]);

    const formatDateForInput = (dateString) => {
        if (!dateString) return '';
        if (dateString.includes('/')) {
            const [day, month, year] = dateString.split('/');
            return `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`;
        }
        return dateString;
    };

    const types = [
        { value: 'abertura', label: 'Abertura' },
        { value: 'fechamento', label: 'Fechamento' },
        { value: 'integral', label: 'Integral' },
        { value: 'compensar', label: 'Compensar' },
    ];

    const handleSubmit = () => {
        setIsSubmitting(true);

        router.post(`/work-shifts/${workShift.id}`, {
            ...data,
            _method: 'PUT',
        }, {
            preserveState: false,
            preserveScroll: false,
            onSuccess: () => {
                setIsSubmitting(false);
                reset();
                clearErrors();
                if (onSuccess) onSuccess();
            },
            onError: (errors) => {
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
        <StandardModal
            show={show}
            onClose={handleClose}
            title="Editar Jornada"
            subtitle={workShift?.employee_name}
            headerColor="bg-yellow-600"
            headerIcon={<PencilSquareIcon className="h-6 w-6" />}
            onSubmit={handleSubmit}
            maxWidth="2xl"
            footer={
                <StandardModal.Footer
                    onCancel={handleClose}
                    onSubmit="submit"
                    submitLabel="Salvar Alterações"
                    submitColor="bg-yellow-600 hover:bg-yellow-700"
                    processing={isSubmitting}
                />
            }
        >
            {workShift && (
                <StandardModal.Section title="Informações da Jornada">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="md:col-span-2">
                            <InputLabel htmlFor="edit-employee_id" value="Funcionário *" />
                            <select
                                id="edit-employee_id"
                                value={data.employee_id}
                                onChange={(e) => setData('employee_id', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                required
                            >
                                <option value="">Selecione um funcionário</option>
                                {employees.map((employee) => (
                                    <option key={employee.id} value={employee.id}>
                                        {employee.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.employee_id} className="mt-1" />
                        </div>

                        <div>
                            <InputLabel htmlFor="edit-date" value="Data *" />
                            <TextInput
                                id="edit-date"
                                type="date"
                                className="mt-1 w-full"
                                value={data.date}
                                onChange={(e) => setData('date', e.target.value)}
                                required
                            />
                            <InputError message={errors.date} className="mt-1" />
                        </div>

                        <div>
                            <InputLabel htmlFor="edit-type" value="Tipo de Jornada *" />
                            <select
                                id="edit-type"
                                value={data.type}
                                onChange={(e) => setData('type', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                required
                            >
                                <option value="">Selecione o tipo</option>
                                {types.map((type) => (
                                    <option key={type.value} value={type.value}>
                                        {type.label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.type} className="mt-1" />
                        </div>

                        <div>
                            <InputLabel htmlFor="edit-start_time" value="Hora de Início *" />
                            <TextInput
                                id="edit-start_time"
                                type="time"
                                className="mt-1 w-full"
                                value={data.start_time}
                                onChange={(e) => setData('start_time', e.target.value)}
                                required
                            />
                            <InputError message={errors.start_time} className="mt-1" />
                        </div>

                        <div>
                            <InputLabel htmlFor="edit-end_time" value="Hora de Término *" />
                            <TextInput
                                id="edit-end_time"
                                type="time"
                                className="mt-1 w-full"
                                value={data.end_time}
                                onChange={(e) => setData('end_time', e.target.value)}
                                required
                            />
                            <InputError message={errors.end_time} className="mt-1" />
                        </div>
                    </div>
                </StandardModal.Section>
            )}
        </StandardModal>
    );
}
