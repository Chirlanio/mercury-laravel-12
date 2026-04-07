import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import Button from '@/Components/Button';
import { maskMoney, parseMoney } from '@/Hooks/useMasks';

export default function EditModal({ isOpen, onClose, onSuccess, goal }) {
    const [form, setForm] = useState({
        goal_amount: '',
        business_days: 26,
        non_working_days: 0,
    });
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (isOpen && goal) {
            setForm({
                goal_amount: goal.goal_amount ? maskMoney(String(Math.round(goal.goal_amount * 100))) : '',
                business_days: goal.business_days || 26,
                non_working_days: goal.non_working_days || 0,
            });
            setErrors({});
        }
    }, [isOpen, goal]);

    const handleChange = (field, value) => {
        setForm(prev => ({ ...prev, [field]: value }));
        setErrors(prev => ({ ...prev, [field]: null }));
    };

    const goalValue = parseMoney(form.goal_amount);
    const superGoal = goalValue > 0 ? goalValue * 1.15 : 0;

    const formatCurrency = (value) => {
        if (!value) return '';
        return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
    };

    const handleSubmit = (e) => {
        e.preventDefault();

        const newErrors = {};
        if (!form.goal_amount || parseMoney(form.goal_amount) <= 0) newErrors.goal_amount = 'Informe um valor válido.';
        if (!form.business_days || form.business_days < 1) newErrors.business_days = 'Informe os dias úteis.';

        if (Object.keys(newErrors).length > 0) {
            setErrors(newErrors);
            return;
        }

        setProcessing(true);
        router.put(`/store-goals/${goal.id}`, {
            goal_amount: parseMoney(form.goal_amount),
            business_days: parseInt(form.business_days),
            non_working_days: parseInt(form.non_working_days) || 0,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setProcessing(false);
                onSuccess();
            },
            onError: (errs) => {
                setProcessing(false);
                setErrors(errs);
            },
        });
    };

    if (!goal) return null;

    return (
        <Modal show={isOpen} onClose={onClose} maxWidth="lg">
            <form onSubmit={handleSubmit} className="p-6">
                <h2 className="text-lg font-semibold text-gray-900 mb-1">Editar Meta</h2>
                <p className="text-sm text-gray-500 mb-4">
                    {goal.store_name} - {goal.period_label}
                </p>

                <div className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Meta (R$)</label>
                        <input
                            type="text"
                            inputMode="numeric"
                            value={form.goal_amount}
                            onChange={(e) => handleChange('goal_amount', maskMoney(e.target.value))}
                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="0,00"
                        />
                        {errors.goal_amount && <p className="mt-1 text-sm text-red-600">{errors.goal_amount}</p>}
                        {superGoal && (
                            <p className="mt-1 text-xs text-gray-500">
                                Super Meta: {formatCurrency(superGoal)}
                            </p>
                        )}
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Dias Úteis</label>
                            <input
                                type="number"
                                min="1"
                                max="31"
                                value={form.business_days}
                                onChange={(e) => handleChange('business_days', e.target.value)}
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            {errors.business_days && <p className="mt-1 text-sm text-red-600">{errors.business_days}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Feriados/Folgas</label>
                            <input
                                type="number"
                                min="0"
                                max="15"
                                value={form.non_working_days}
                                onChange={(e) => handleChange('non_working_days', e.target.value)}
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                    </div>
                </div>

                <div className="mt-6 flex justify-end gap-3">
                    <Button variant="secondary" type="button" onClick={onClose}>Cancelar</Button>
                    <Button variant="primary" type="submit" disabled={processing}>
                        {processing ? 'Salvando...' : 'Salvar Alterações'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
