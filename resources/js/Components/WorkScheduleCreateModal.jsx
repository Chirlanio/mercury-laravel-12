import { useState } from 'react';
import { router } from '@inertiajs/react';
import StandardModal from '@/Components/StandardModal';
import FormSection from '@/Components/Shared/FormSection';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import Checkbox from '@/Components/Checkbox';
import { PlusIcon } from '@heroicons/react/24/outline';

const DAY_NAMES = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];

const defaultDays = () => DAY_NAMES.map((_, i) => ({
    day_of_week: i, is_work_day: i >= 1 && i <= 5,
    entry_time: i >= 1 && i <= 5 ? '08:00' : '', exit_time: i >= 1 && i <= 5 ? '17:48' : '',
    break_start: i >= 1 && i <= 5 ? '12:00' : '', break_end: i >= 1 && i <= 5 ? '13:00' : '',
    break_duration_minutes: i >= 1 && i <= 5 ? 60 : 0, notes: '',
}));

export function calculateDailyHours(day) {
    if (!day.is_work_day || !day.entry_time || !day.exit_time) return 0;
    const [eh, em] = day.entry_time.split(':').map(Number);
    const [xh, xm] = day.exit_time.split(':').map(Number);
    let totalMin = (xh * 60 + xm) - (eh * 60 + em);
    if (totalMin <= 0) return 0;
    if (day.break_duration_minutes) totalMin -= day.break_duration_minutes;
    else if (day.break_start && day.break_end) {
        const [bsh, bsm] = day.break_start.split(':').map(Number);
        const [beh, bem] = day.break_end.split(':').map(Number);
        const breakMin = (beh * 60 + bem) - (bsh * 60 + bsm);
        if (breakMin > 0) totalMin -= breakMin;
    }
    return Math.max(0, Math.round(totalMin / 60 * 100) / 100);
}

export function calculateWeeklyHours(days) {
    return days.reduce((sum, d) => sum + calculateDailyHours(d), 0);
}

export function updateDayHelper(days, index, field, value) {
    const updated = [...days];
    updated[index] = { ...updated[index], [field]: value };
    if (field === 'is_work_day' && !value) {
        updated[index].entry_time = ''; updated[index].exit_time = '';
        updated[index].break_start = ''; updated[index].break_end = '';
        updated[index].break_duration_minutes = 0;
    }
    if ((field === 'break_start' || field === 'break_end') && updated[index].break_start && updated[index].break_end) {
        const [bsh, bsm] = updated[index].break_start.split(':').map(Number);
        const [beh, bem] = updated[index].break_end.split(':').map(Number);
        updated[index].break_duration_minutes = Math.max(0, (beh * 60 + bem) - (bsh * 60 + bsm));
    }
    return updated;
}

export function DayScheduleGrid({ days, setDays, errors }) {
    const updateDay = (index, field, value) => setDays(prev => updateDayHelper(prev, index, field, value));

    return (
        <StandardModal.Section title="Configuração dos Dias">
            {errors?.days && <p className="mb-2 text-sm text-red-600">{errors.days}</p>}
            <div className="space-y-2 -mx-4 -mb-4 px-4 pb-4">
                {days.map((day, index) => (
                    <div key={day.day_of_week} className={`p-3 rounded-lg border ${day.is_work_day ? 'bg-green-50 border-green-200' : 'bg-gray-50 border-gray-200'}`}>
                        <div className="flex items-center gap-4">
                            <div className="w-32 flex items-center gap-2">
                                <Checkbox checked={day.is_work_day} onChange={(e) => updateDay(index, 'is_work_day', e.target.checked)}
                                    className="text-green-600 focus:ring-green-500" />
                                <span className={`text-sm font-medium ${day.is_work_day ? 'text-green-800' : 'text-gray-500'}`}>
                                    {DAY_NAMES[day.day_of_week]}
                                </span>
                            </div>
                            {day.is_work_day ? (
                                <div className="flex items-center gap-3 flex-1">
                                    <div className="flex items-center gap-1">
                                        <label className="text-xs text-gray-500">Entrada</label>
                                        <input type="time" value={day.entry_time} onChange={(e) => updateDay(index, 'entry_time', e.target.value)}
                                            className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-1" />
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <label className="text-xs text-gray-500">Saída</label>
                                        <input type="time" value={day.exit_time} onChange={(e) => updateDay(index, 'exit_time', e.target.value)}
                                            className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-1" />
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <label className="text-xs text-gray-500">Intervalo</label>
                                        <input type="time" value={day.break_start} onChange={(e) => updateDay(index, 'break_start', e.target.value)}
                                            className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-1" />
                                        <span className="text-gray-400">-</span>
                                        <input type="time" value={day.break_end} onChange={(e) => updateDay(index, 'break_end', e.target.value)}
                                            className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-1" />
                                    </div>
                                    <div className="text-sm font-medium text-gray-700 ml-auto">
                                        {calculateDailyHours(day).toFixed(2).replace('.', ',')}h
                                    </div>
                                </div>
                            ) : (
                                <span className="text-sm text-gray-400 italic">Folga</span>
                            )}
                        </div>
                    </div>
                ))}
            </div>
        </StandardModal.Section>
    );
}

export default function WorkScheduleCreateModal({ show, onClose, onSuccess }) {
    const [formData, setFormData] = useState({ name: '', description: '', is_active: true, is_default: false });
    const [days, setDays] = useState(defaultDays());
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    const resetForm = () => { setFormData({ name: '', description: '', is_active: true, is_default: false }); setDays(defaultDays()); setErrors({}); };
    const handleClose = () => { resetForm(); onClose(); };

    const handleSubmit = () => {
        setProcessing(true); setErrors({});
        router.post('/work-schedules', { ...formData, days }, {
            onSuccess: () => { resetForm(); onSuccess(); },
            onError: (errs) => setErrors(errs),
            onFinish: () => setProcessing(false),
        });
    };

    const weeklyHours = calculateWeeklyHours(days);

    return (
        <StandardModal show={show} onClose={handleClose} title="Nova Escala de Trabalho"
            headerColor="bg-indigo-600" headerIcon={<PlusIcon className="h-5 w-5" />}
            onSubmit={handleSubmit} errorMessage={errors.general}
            footer={<StandardModal.Footer onCancel={handleClose} onSubmit="submit" submitLabel="Criar Escala" processing={processing} />}>

            <FormSection title="Informações Básicas" cols={2}>
                <div>
                    <InputLabel value="Nome *" />
                    <TextInput className="mt-1 w-full" value={formData.name} onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                        placeholder="Ex: Comercial 5x2" maxLength={100} required />
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
                        rows={2} className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Descrição opcional..." />
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
