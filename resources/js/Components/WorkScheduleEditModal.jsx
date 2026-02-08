import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import Button from '@/Components/Button';

const DAY_NAMES = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];

function calculateDailyHours(day) {
    if (!day.is_work_day || !day.entry_time || !day.exit_time) return 0;
    const [eh, em] = day.entry_time.split(':').map(Number);
    const [xh, xm] = day.exit_time.split(':').map(Number);
    let totalMin = (xh * 60 + xm) - (eh * 60 + em);
    if (totalMin <= 0) return 0;

    if (day.break_duration_minutes) {
        totalMin -= day.break_duration_minutes;
    } else if (day.break_start && day.break_end) {
        const [bsh, bsm] = day.break_start.split(':').map(Number);
        const [beh, bem] = day.break_end.split(':').map(Number);
        const breakMin = (beh * 60 + bem) - (bsh * 60 + bsm);
        if (breakMin > 0) totalMin -= breakMin;
    }

    return Math.max(0, Math.round(totalMin / 60 * 100) / 100);
}

function calculateWeeklyHours(days) {
    return days.reduce((sum, d) => sum + calculateDailyHours(d), 0);
}

export default function WorkScheduleEditModal({ isOpen, onClose, onSuccess, schedule }) {
    const [formData, setFormData] = useState({ name: '', description: '', is_active: true, is_default: false });
    const [days, setDays] = useState([]);
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (schedule && isOpen) {
            setFormData({
                name: schedule.name || '',
                description: schedule.description || '',
                is_active: schedule.is_active ?? true,
                is_default: schedule.is_default ?? false,
            });
            setDays(schedule.days?.map(d => ({
                day_of_week: d.day_of_week,
                is_work_day: d.is_work_day,
                entry_time: d.entry_time || '',
                exit_time: d.exit_time || '',
                break_start: d.break_start || '',
                break_end: d.break_end || '',
                break_duration_minutes: d.break_duration_minutes || 0,
                notes: d.notes || '',
            })) || []);
            setErrors({});
        }
    }, [schedule, isOpen]);

    const updateDay = (index, field, value) => {
        setDays(prev => {
            const updated = [...prev];
            updated[index] = { ...updated[index], [field]: value };

            if (field === 'is_work_day' && !value) {
                updated[index].entry_time = '';
                updated[index].exit_time = '';
                updated[index].break_start = '';
                updated[index].break_end = '';
                updated[index].break_duration_minutes = 0;
            }

            if ((field === 'break_start' || field === 'break_end') && updated[index].break_start && updated[index].break_end) {
                const [bsh, bsm] = updated[index].break_start.split(':').map(Number);
                const [beh, bem] = updated[index].break_end.split(':').map(Number);
                const mins = (beh * 60 + bem) - (bsh * 60 + bsm);
                updated[index].break_duration_minutes = Math.max(0, mins);
            }

            return updated;
        });
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        const payload = {
            ...formData,
            days: days,
        };

        router.put(`/work-schedules/${schedule.id}/update`, payload, {
            onSuccess: () => onSuccess(),
            onError: (errs) => setErrors(errs),
            onFinish: () => setProcessing(false),
        });
    };

    const weeklyHours = calculateWeeklyHours(days);

    if (!isOpen || !schedule) return null;

    return (
        <Modal show={isOpen} onClose={onClose} title="Editar Escala de Trabalho" maxWidth="85vw">
            <form onSubmit={handleSubmit} className="space-y-6">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Nome *</label>
                        <input
                            type="text"
                            value={formData.name}
                            onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            maxLength={100}
                            required
                        />
                        {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Horas Semanais</label>
                        <div className="flex items-center h-[42px] px-3 bg-gray-50 border border-gray-300 rounded-md">
                            <span className="text-lg font-bold text-indigo-600">
                                {weeklyHours.toFixed(2).replace('.', ',')}h
                            </span>
                            <span className="ml-2 text-xs text-gray-500">(calculado automaticamente)</span>
                        </div>
                    </div>
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                    <textarea
                        value={formData.description}
                        onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                        rows={2}
                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                </div>

                <div className="flex space-x-6">
                    <label className="flex items-center space-x-2">
                        <input
                            type="checkbox"
                            checked={formData.is_active}
                            onChange={(e) => setFormData({ ...formData, is_active: e.target.checked })}
                            className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <span className="text-sm text-gray-700">Ativa</span>
                    </label>
                    <label className="flex items-center space-x-2">
                        <input
                            type="checkbox"
                            checked={formData.is_default}
                            onChange={(e) => setFormData({ ...formData, is_default: e.target.checked })}
                            className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <span className="text-sm text-gray-700">Escala Padrão</span>
                    </label>
                </div>

                {/* Grid de 7 Dias */}
                <div>
                    <h3 className="text-sm font-semibold text-gray-900 mb-3">Configuração dos Dias</h3>
                    <div className="space-y-2">
                        {days.map((day, index) => (
                            <div
                                key={day.day_of_week}
                                className={`p-3 rounded-lg border ${
                                    day.is_work_day ? 'bg-green-50 border-green-200' : 'bg-gray-50 border-gray-200'
                                }`}
                            >
                                <div className="flex items-center gap-4">
                                    <div className="w-32 flex items-center space-x-2">
                                        <label className="flex items-center space-x-2 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                checked={day.is_work_day}
                                                onChange={(e) => updateDay(index, 'is_work_day', e.target.checked)}
                                                className="rounded border-gray-300 text-green-600 focus:ring-green-500"
                                            />
                                            <span className={`text-sm font-medium ${day.is_work_day ? 'text-green-800' : 'text-gray-500'}`}>
                                                {DAY_NAMES[day.day_of_week]}
                                            </span>
                                        </label>
                                    </div>

                                    {day.is_work_day && (
                                        <div className="flex items-center gap-3 flex-1">
                                            <div className="flex items-center gap-1">
                                                <label className="text-xs text-gray-500">Entrada</label>
                                                <input
                                                    type="time"
                                                    value={day.entry_time}
                                                    onChange={(e) => updateDay(index, 'entry_time', e.target.value)}
                                                    className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-1"
                                                />
                                            </div>
                                            <div className="flex items-center gap-1">
                                                <label className="text-xs text-gray-500">Saída</label>
                                                <input
                                                    type="time"
                                                    value={day.exit_time}
                                                    onChange={(e) => updateDay(index, 'exit_time', e.target.value)}
                                                    className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-1"
                                                />
                                            </div>
                                            <div className="flex items-center gap-1">
                                                <label className="text-xs text-gray-500">Intervalo</label>
                                                <input
                                                    type="time"
                                                    value={day.break_start}
                                                    onChange={(e) => updateDay(index, 'break_start', e.target.value)}
                                                    className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-1"
                                                />
                                                <span className="text-gray-400">-</span>
                                                <input
                                                    type="time"
                                                    value={day.break_end}
                                                    onChange={(e) => updateDay(index, 'break_end', e.target.value)}
                                                    className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm py-1"
                                                />
                                            </div>
                                            <div className="text-sm font-medium text-gray-700 ml-auto">
                                                {calculateDailyHours(day).toFixed(2).replace('.', ',')}h
                                            </div>
                                        </div>
                                    )}

                                    {!day.is_work_day && (
                                        <span className="text-sm text-gray-400 italic">Folga</span>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {errors.general && (
                    <div className="p-3 bg-red-50 border border-red-200 rounded-md">
                        <p className="text-sm text-red-600">{errors.general}</p>
                    </div>
                )}

                <div className="flex justify-end gap-3 pt-4 border-t">
                    <Button type="button" variant="outline" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button type="submit" variant="primary" disabled={processing}>
                        {processing ? 'Salvando...' : 'Salvar Alterações'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
