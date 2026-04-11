import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import StandardModal from '@/Components/StandardModal';
import FormSection from '@/Components/Shared/FormSection';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import { maskMoney, parseMoney } from '@/Hooks/useMasks';
import { PencilSquareIcon } from '@heroicons/react/24/outline';

const formatCurrency = (value) =>
    value ? new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value) : '';

export default function EditModal({ show, onClose, onSuccess, goal }) {
    const [form, setForm] = useState({ goal_amount: '', business_days: 26, non_working_days: 0 });
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (show && goal) {
            setForm({
                goal_amount: goal.goal_amount ? maskMoney(String(Math.round(goal.goal_amount * 100))) : '',
                business_days: goal.business_days || 26,
                non_working_days: goal.non_working_days || 0,
            });
            setErrors({});
        }
    }, [show, goal]);

    const handleChange = (field, value) => {
        setForm(prev => ({ ...prev, [field]: value }));
        setErrors(prev => ({ ...prev, [field]: null }));
    };

    const goalValue = parseMoney(form.goal_amount);
    const superGoal = goalValue > 0 ? goalValue * 1.15 : 0;

    const handleSubmit = () => {
        const newErrors = {};
        if (!form.goal_amount || parseMoney(form.goal_amount) <= 0) newErrors.goal_amount = 'Informe um valor válido.';
        if (!form.business_days || form.business_days < 1) newErrors.business_days = 'Informe os dias úteis.';

        if (Object.keys(newErrors).length > 0) { setErrors(newErrors); return; }

        setProcessing(true);
        router.put(`/store-goals/${goal.id}`, {
            goal_amount: parseMoney(form.goal_amount),
            business_days: parseInt(form.business_days),
            non_working_days: parseInt(form.non_working_days) || 0,
        }, {
            preserveScroll: true,
            onSuccess: () => { setProcessing(false); onSuccess(); },
            onError: (errs) => { setProcessing(false); setErrors(errs); },
        });
    };

    if (!goal) return null;

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title="Editar Meta"
            subtitle={`${goal.store_name} - ${goal.period_label}`}
            headerColor="bg-yellow-600"
            headerIcon={<PencilSquareIcon className="h-5 w-5" />}
            onSubmit={handleSubmit}
            footer={
                <StandardModal.Footer onCancel={onClose} onSubmit="submit"
                    submitLabel="Salvar Alterações" submitColor="bg-yellow-600 hover:bg-yellow-700"
                    processing={processing} />
            }
        >
            <FormSection title="Valores e Dias" cols={2}>
                <div>
                    <InputLabel value="Meta (R$) *" />
                    <TextInput className="mt-1 w-full" inputMode="numeric" value={form.goal_amount}
                        onChange={(e) => handleChange('goal_amount', maskMoney(e.target.value))} placeholder="0,00" />
                    <InputError message={errors.goal_amount} className="mt-1" />
                    {superGoal > 0 && <p className="mt-1 text-xs text-gray-500">Super Meta: {formatCurrency(superGoal)}</p>}
                </div>
                <div>
                    <InputLabel value="Dias Úteis *" />
                    <TextInput type="number" className="mt-1 w-full" min="1" max="31"
                        value={form.business_days} onChange={(e) => handleChange('business_days', e.target.value)} />
                    <InputError message={errors.business_days} className="mt-1" />
                </div>
                <div>
                    <InputLabel value="Feriados/Folgas" />
                    <TextInput type="number" className="mt-1 w-full" min="0" max="15"
                        value={form.non_working_days} onChange={(e) => handleChange('non_working_days', e.target.value)} />
                </div>
            </FormSection>
        </StandardModal>
    );
}
