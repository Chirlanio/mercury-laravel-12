import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import Button from '@/Components/Button';
import { maskMoney, parseMoney } from '@/Hooks/useMasks';

export default function CreateModal({ isOpen, onClose, onSuccess, stores = [] }) {
    const [form, setForm] = useState({
        store_id: '',
        reference_month: new Date().getMonth() + 1,
        reference_year: new Date().getFullYear(),
        goal_amount: '',
        business_days: 26,
        non_working_days: 0,
    });
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (isOpen) {
            setForm({
                store_id: '',
                reference_month: new Date().getMonth() + 1,
                reference_year: new Date().getFullYear(),
                goal_amount: '',
                business_days: 26,
                non_working_days: 0,
            });
            setErrors({});
        }
    }, [isOpen]);

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

    const months = [
        { value: 1, label: 'Janeiro' }, { value: 2, label: 'Fevereiro' },
        { value: 3, label: 'Março' }, { value: 4, label: 'Abril' },
        { value: 5, label: 'Maio' }, { value: 6, label: 'Junho' },
        { value: 7, label: 'Julho' }, { value: 8, label: 'Agosto' },
        { value: 9, label: 'Setembro' }, { value: 10, label: 'Outubro' },
        { value: 11, label: 'Novembro' }, { value: 12, label: 'Dezembro' },
    ];

    const thisYear = new Date().getFullYear();
    const years = Array.from({ length: 5 }, (_, i) => thisYear + 1 - i);

    const handleSubmit = (e) => {
        e.preventDefault();

        const newErrors = {};
        if (!form.store_id) newErrors.store_id = 'Selecione uma loja.';
        if (!form.goal_amount || parseMoney(form.goal_amount) <= 0) newErrors.goal_amount = 'Informe um valor válido.';
        if (!form.business_days || form.business_days < 1) newErrors.business_days = 'Informe os dias úteis.';

        if (Object.keys(newErrors).length > 0) {
            setErrors(newErrors);
            return;
        }

        setProcessing(true);
        router.post('/store-goals', {
            store_id: parseInt(form.store_id),
            reference_month: parseInt(form.reference_month),
            reference_year: parseInt(form.reference_year),
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

    return (
        <Modal show={isOpen} onClose={onClose} maxWidth="lg">
            <form onSubmit={handleSubmit} className="p-6">
                <h2 className="text-lg font-semibold text-gray-900 mb-4">Nova Meta de Loja</h2>

                <div className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Loja</label>
                        <select
                            value={form.store_id}
                            onChange={(e) => handleChange('store_id', e.target.value)}
                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Selecione uma loja...</option>
                            {stores.map(s => (
                                <option key={s.id} value={s.id}>{s.name}</option>
                            ))}
                        </select>
                        {errors.store_id && <p className="mt-1 text-sm text-red-600">{errors.store_id}</p>}
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Mês</label>
                            <select
                                value={form.reference_month}
                                onChange={(e) => handleChange('reference_month', e.target.value)}
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                {months.map(m => (
                                    <option key={m.value} value={m.value}>{m.label}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Ano</label>
                            <select
                                value={form.reference_year}
                                onChange={(e) => handleChange('reference_year', e.target.value)}
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                {years.map(y => (
                                    <option key={y} value={y}>{y}</option>
                                ))}
                            </select>
                        </div>
                    </div>

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
                        {processing ? 'Salvando...' : 'Criar Meta'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
