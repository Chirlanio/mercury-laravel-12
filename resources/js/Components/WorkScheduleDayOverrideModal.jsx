import { useState } from 'react';
import Modal from '@/Components/Modal';
import Button from '@/Components/Button';

const DAY_NAMES = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];

export default function WorkScheduleDayOverrideModal({ isOpen, onClose, onSuccess, assignment, scheduleDays }) {
    const [formData, setFormData] = useState({
        day_of_week: '',
        is_work_day: false,
        entry_time: '',
        exit_time: '',
        break_start: '',
        break_end: '',
        reason: '',
    });
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    const handleDayChange = (e) => {
        const dayOfWeek = parseInt(e.target.value);
        const originalDay = scheduleDays?.find(d => d.day_of_week === dayOfWeek);

        setFormData({
            day_of_week: e.target.value,
            is_work_day: originalDay ? !originalDay.is_work_day : false,
            entry_time: '',
            exit_time: '',
            break_start: '',
            break_end: '',
            reason: '',
        });
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        try {
            await axios.post(`/employee-schedules/${assignment.id}/overrides`, {
                ...formData,
                day_of_week: parseInt(formData.day_of_week),
            });

            setFormData({ day_of_week: '', is_work_day: false, entry_time: '', exit_time: '', break_start: '', break_end: '', reason: '' });
            onSuccess();
        } catch (error) {
            if (error.response?.status === 422 && error.response?.data?.errors) {
                setErrors(error.response.data.errors);
            } else {
                console.error('Erro ao criar exceção:', error);
            }
        } finally {
            setProcessing(false);
        }
    };

    if (!isOpen || !assignment) return null;

    const selectedDay = formData.day_of_week !== '' ? scheduleDays?.find(d => d.day_of_week === parseInt(formData.day_of_week)) : null;

    return (
        <Modal show={isOpen} onClose={onClose} title={`Exceção - ${assignment.employee_short_name || assignment.employee_name}`} maxWidth="md">
            <form onSubmit={handleSubmit} className="space-y-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Dia da Semana *</label>
                    <select
                        value={formData.day_of_week}
                        onChange={handleDayChange}
                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        required
                    >
                        <option value="">Selecione um dia</option>
                        {DAY_NAMES.map((name, i) => {
                            const existing = assignment.overrides?.find(o => o.day_of_week === i);
                            return (
                                <option key={i} value={i}>
                                    {name} {existing ? '(já tem exceção)' : ''}
                                </option>
                            );
                        })}
                    </select>
                    {errors.day_of_week && <p className="mt-1 text-sm text-red-600">{errors.day_of_week[0]}</p>}
                </div>

                {selectedDay && (
                    <div className="p-3 bg-gray-50 rounded-md text-sm">
                        <span className="font-medium text-gray-600">Configuração original:</span>
                        <span className="ml-2 text-gray-900">
                            {selectedDay.is_work_day
                                ? `Trabalho (${selectedDay.entry_time?.substring(0, 5)} - ${selectedDay.exit_time?.substring(0, 5)})`
                                : 'Folga'
                            }
                        </span>
                    </div>
                )}

                {formData.day_of_week !== '' && (
                    <>
                        <div>
                            <label className="flex items-center space-x-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={formData.is_work_day}
                                    onChange={(e) => setFormData({ ...formData, is_work_day: e.target.checked })}
                                    className="rounded border-gray-300 text-green-600 focus:ring-green-500"
                                />
                                <span className="text-sm font-medium text-gray-700">Dia de trabalho</span>
                            </label>
                        </div>

                        {formData.is_work_day && (
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Entrada *</label>
                                    <input
                                        type="time"
                                        value={formData.entry_time}
                                        onChange={(e) => setFormData({ ...formData, entry_time: e.target.value })}
                                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Saída *</label>
                                    <input
                                        type="time"
                                        value={formData.exit_time}
                                        onChange={(e) => setFormData({ ...formData, exit_time: e.target.value })}
                                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Início Intervalo</label>
                                    <input
                                        type="time"
                                        value={formData.break_start}
                                        onChange={(e) => setFormData({ ...formData, break_start: e.target.value })}
                                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Fim Intervalo</label>
                                    <input
                                        type="time"
                                        value={formData.break_end}
                                        onChange={(e) => setFormData({ ...formData, break_end: e.target.value })}
                                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                </div>
                            </div>
                        )}

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Motivo</label>
                            <input
                                type="text"
                                value={formData.reason}
                                onChange={(e) => setFormData({ ...formData, reason: e.target.value })}
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Ex: Compensação de banco de horas"
                                maxLength={255}
                            />
                        </div>
                    </>
                )}

                <div className="flex justify-end gap-3 pt-4 border-t">
                    <Button type="button" variant="outline" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button type="submit" variant="primary" disabled={processing || formData.day_of_week === ''}>
                        {processing ? 'Salvando...' : 'Salvar Exceção'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
