import { useState } from 'react';
import { useForm, router } from '@inertiajs/react';
import StandardModal from '@/Components/StandardModal';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import { CalendarDaysIcon } from '@heroicons/react/24/outline';

export default function WorkShiftCreateModal({ show, onClose, onSuccess, employees = [] }) {
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
        { value: 'compensar', label: 'Compensar' },
    ];

    const handleSubmit = () => {
        setIsSubmitting(true);

        router.post('/work-shifts', data, {
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
            title="Cadastrar Jornada"
            subtitle="Criação de novo registro de turno"
            headerColor="bg-indigo-600"
            headerIcon={<CalendarDaysIcon className="h-6 w-6" />}
            onSubmit={handleSubmit}
            maxWidth="2xl"
            footer={
                <StandardModal.Footer
                    onCancel={handleClose}
                    onSubmit="submit"
                    submitLabel="Cadastrar Jornada"
                    processing={isSubmitting}
                />
            }
        >
            <StandardModal.Section title="Informações da Jornada">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="md:col-span-2">
                        <InputLabel htmlFor="create-employee_id" value="Funcionário *" />
                        <select
                            id="create-employee_id"
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
                        <InputLabel htmlFor="create-date" value="Data *" />
                        <TextInput
                            id="create-date"
                            type="date"
                            className="mt-1 w-full"
                            value={data.date}
                            onChange={(e) => setData('date', e.target.value)}
                            required
                        />
                        <InputError message={errors.date} className="mt-1" />
                    </div>

                    <div>
                        <InputLabel htmlFor="create-type" value="Tipo de Jornada *" />
                        <select
                            id="create-type"
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
                        <InputLabel htmlFor="create-start_time" value="Hora de Início *" />
                        <TextInput
                            id="create-start_time"
                            type="time"
                            className="mt-1 w-full"
                            value={data.start_time}
                            onChange={(e) => setData('start_time', e.target.value)}
                            required
                        />
                        <InputError message={errors.start_time} className="mt-1" />
                    </div>

                    <div>
                        <InputLabel htmlFor="create-end_time" value="Hora de Término *" />
                        <TextInput
                            id="create-end_time"
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
        </StandardModal>
    );
}
