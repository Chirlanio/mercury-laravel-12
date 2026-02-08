import { useState, useEffect } from 'react';
import Modal from '@/Components/Modal';
import Button from '@/Components/Button';

const DAY_SHORT_NAMES = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

export default function EmployeeScheduleManageModal({ isOpen, onClose, onSuccess, employeeId, employeeName, currentAssignment }) {
    const [schedules, setSchedules] = useState([]);
    const [selectedScheduleId, setSelectedScheduleId] = useState('');
    const [formData, setFormData] = useState({
        effective_date: new Date().toISOString().split('T')[0],
        end_date: '',
        notes: '',
    });
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (isOpen) {
            fetchSchedules();
            setSelectedScheduleId('');
            setFormData({
                effective_date: new Date().toISOString().split('T')[0],
                end_date: '',
                notes: '',
            });
            setErrors({});
        }
    }, [isOpen]);

    const fetchSchedules = async () => {
        setLoading(true);
        try {
            const response = await axios.get('/work-schedules/list-json');
            setSchedules(response.data.schedules || []);
        } catch (error) {
            console.error('Erro ao carregar escalas:', error);
            setSchedules([]);
        } finally {
            setLoading(false);
        }
    };

    const selectedSchedule = schedules.find(s => s.id === parseInt(selectedScheduleId));

    const handleSubmit = async (e) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        try {
            await axios.post(`/work-schedules/${selectedScheduleId}/employees`, {
                employee_id: employeeId,
                ...formData,
            });
            onSuccess();
        } catch (error) {
            if (error.response?.status === 422 && error.response?.data?.errors) {
                setErrors(error.response.data.errors);
            } else {
                console.error('Erro ao atribuir escala:', error);
            }
        } finally {
            setProcessing(false);
        }
    };

    if (!isOpen) return null;

    return (
        <Modal show={isOpen} onClose={onClose} title={`Atribuir Escala - ${employeeName}`} maxWidth="2xl">
            <form onSubmit={handleSubmit} className="space-y-4">
                {/* Escala atual */}
                {currentAssignment && (
                    <div className="bg-amber-50 border border-amber-200 rounded-lg p-3">
                        <div className="flex items-center gap-2 mb-1">
                            <svg className="w-4 h-4 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span className="text-sm font-medium text-amber-800">Escala atual: {currentAssignment.schedule_name}</span>
                        </div>
                        <p className="text-xs text-amber-700">
                            {currentAssignment.weekly_hours} semanais | Desde {currentAssignment.effective_date}.
                            A escala atual será encerrada automaticamente na data de vigência da nova.
                        </p>
                    </div>
                )}

                {/* Seletor de escala */}
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Escala de Trabalho *</label>
                    {loading ? (
                        <div className="flex items-center justify-center py-4">
                            <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-600"></div>
                            <span className="ml-2 text-sm text-gray-500">Carregando escalas...</span>
                        </div>
                    ) : (
                        <div className="space-y-2">
                            {schedules.map((schedule) => (
                                <label
                                    key={schedule.id}
                                    className={`flex items-start p-3 rounded-lg border-2 cursor-pointer transition-all ${
                                        parseInt(selectedScheduleId) === schedule.id
                                            ? 'border-indigo-500 bg-indigo-50'
                                            : 'border-gray-200 hover:border-gray-300 bg-white'
                                    } ${currentAssignment?.schedule_id === schedule.id ? 'opacity-50' : ''}`}
                                >
                                    <input
                                        type="radio"
                                        name="schedule"
                                        value={schedule.id}
                                        checked={parseInt(selectedScheduleId) === schedule.id}
                                        onChange={(e) => setSelectedScheduleId(e.target.value)}
                                        disabled={currentAssignment?.schedule_id === schedule.id}
                                        className="mt-1 text-indigo-600 focus:ring-indigo-500"
                                    />
                                    <div className="ml-3 flex-1">
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm font-semibold text-gray-900">{schedule.name}</span>
                                            <span className="text-xs px-2 py-0.5 bg-gray-100 text-gray-600 rounded-full">
                                                {schedule.work_days_label}
                                            </span>
                                            <span className="text-xs text-gray-500">{schedule.weekly_hours}</span>
                                            {schedule.is_default && (
                                                <span className="text-xs px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full">Padrão</span>
                                            )}
                                            {currentAssignment?.schedule_id === schedule.id && (
                                                <span className="text-xs px-2 py-0.5 bg-green-100 text-green-700 rounded-full">Atual</span>
                                            )}
                                        </div>
                                        {schedule.description && (
                                            <p className="text-xs text-gray-500 mt-0.5">{schedule.description}</p>
                                        )}
                                    </div>
                                </label>
                            ))}
                            {schedules.length === 0 && !loading && (
                                <p className="text-sm text-gray-500 text-center py-4">Nenhuma escala ativa encontrada.</p>
                            )}
                        </div>
                    )}
                    {errors.employee_id && <p className="mt-1 text-sm text-red-600">{errors.employee_id[0]}</p>}
                </div>

                {/* Preview da escala selecionada */}
                {selectedSchedule && (
                    <div className="bg-gray-50 rounded-lg p-3">
                        <h4 className="text-xs font-medium text-gray-600 mb-2 uppercase tracking-wider">Dias da Semana</h4>
                        <div className="grid grid-cols-7 gap-1">
                            {selectedSchedule.days?.map((day) => (
                                <div
                                    key={day.day_of_week}
                                    className={`p-2 rounded text-center text-xs ${
                                        day.is_work_day
                                            ? 'bg-green-100 text-green-800'
                                            : 'bg-gray-200 text-gray-500'
                                    }`}
                                >
                                    <div className="font-semibold">{day.day_short_name || DAY_SHORT_NAMES[day.day_of_week]}</div>
                                    {day.is_work_day ? (
                                        <div className="text-[10px]">{day.entry_time}-{day.exit_time}</div>
                                    ) : (
                                        <div className="text-[10px]">Folga</div>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Datas */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Data de Vigência *</label>
                        <input
                            type="date"
                            value={formData.effective_date}
                            onChange={(e) => setFormData({ ...formData, effective_date: e.target.value })}
                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            required
                        />
                        {errors.effective_date && <p className="mt-1 text-sm text-red-600">{errors.effective_date[0]}</p>}
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Data de Término</label>
                        <input
                            type="date"
                            value={formData.end_date}
                            onChange={(e) => setFormData({ ...formData, end_date: e.target.value })}
                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <p className="mt-1 text-xs text-gray-500">Deixe em branco para vigência indeterminada</p>
                        {errors.end_date && <p className="mt-1 text-sm text-red-600">{errors.end_date[0]}</p>}
                    </div>
                </div>

                {/* Observações */}
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Observações</label>
                    <textarea
                        value={formData.notes}
                        onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                        rows={2}
                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        maxLength={500}
                        placeholder="Observações opcionais..."
                    />
                </div>

                {/* Botões */}
                <div className="flex justify-end gap-3 pt-4 border-t">
                    <Button type="button" variant="outline" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button type="submit" variant="primary" disabled={processing || !selectedScheduleId}>
                        {processing ? 'Atribuindo...' : currentAssignment ? 'Alterar Escala' : 'Atribuir Escala'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
