import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import StandardModal from '@/Components/StandardModal';
import FormSection from '@/Components/Shared/FormSection';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import Checkbox from '@/Components/Checkbox';
import { DayScheduleGrid, calculateWeeklyHours } from '@/Components/WorkScheduleCreateModal';
import { PencilSquareIcon } from '@heroicons/react/24/outline';

export default function WorkScheduleEditModal({ show, onClose, onSuccess, schedule }) {
    const [formData, setFormData] = useState({ name: '', description: '', is_active: true, is_default: false });
    const [days, setDays] = useState([]);
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (schedule && show) {
            setFormData({
                name: schedule.name || '', description: schedule.description || '',
                is_active: schedule.is_active ?? true, is_default: schedule.is_default ?? false,
            });
            setDays(schedule.days?.map(d => ({
                day_of_week: d.day_of_week, is_work_day: d.is_work_day,
                entry_time: d.entry_time || '', exit_time: d.exit_time || '',
                break_start: d.break_start || '', break_end: d.break_end || '',
                break_duration_minutes: d.break_duration_minutes || 0, notes: d.notes || '',
            })) || []);
            setErrors({});
        }
    }, [schedule, show]);

    const handleSubmit = () => {
        setProcessing(true); setErrors({});
        router.put(`/work-schedules/${schedule.id}/update`, { ...formData, days }, {
            onSuccess: () => onSuccess(),
            onError: (errs) => setErrors(errs),
            onFinish: () => setProcessing(false),
        });
    };

    const weeklyHours = calculateWeeklyHours(days);

    return (
        <StandardModal show={show} onClose={onClose} title="Editar Escala de Trabalho"
            subtitle={schedule?.name} headerColor="bg-yellow-600" headerIcon={<PencilSquareIcon className="h-5 w-5" />}
            onSubmit={handleSubmit} errorMessage={errors.general}
            footer={<StandardModal.Footer onCancel={onClose} onSubmit="submit"
                submitLabel="Salvar Alterações" submitColor="bg-yellow-600 hover:bg-yellow-700" processing={processing} />}>

            <FormSection title="Informações Básicas" cols={2}>
                <div>
                    <InputLabel value="Nome *" />
                    <TextInput className="mt-1 w-full" value={formData.name} onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                        maxLength={100} required />
                    <InputError message={errors.name} className="mt-1" />
                </div>
                <div>
                    <InputLabel value="Horas Semanais" />
                    <div className="mt-1 flex items-center h-[38px] px-3 bg-gray-50 border border-gray-300 rounded-md">
                        <span className="text-lg font-bold text-indigo-600">{weeklyHours.toFixed(2).replace('.', ',')}h</span>
                        <span className="ml-2 text-xs text-gray-500">(calculado)</span>
                    </div>
                </div>
                <div className="col-span-full">
                    <InputLabel value="Descrição" />
                    <textarea value={formData.description} onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                        rows={2} className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                </div>
                <div className="flex items-center gap-2">
                    <Checkbox checked={formData.is_active} onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })} />
                    <span className="text-sm text-gray-700">Ativa</span>
                </div>
                <div className="flex items-center gap-2">
                    <Checkbox checked={formData.is_default} onChange={(e) => setFormData({ ...formData, is_default: e.target.checked })} />
                    <span className="text-sm text-gray-700">Escala Padrão</span>
                </div>
            </FormSection>

            <DayScheduleGrid days={days} setDays={setDays} errors={errors} />
        </StandardModal>
    );
}
