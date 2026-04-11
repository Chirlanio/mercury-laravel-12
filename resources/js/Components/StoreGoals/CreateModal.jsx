import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import StandardModal from '@/Components/StandardModal';
import FormSection from '@/Components/Shared/FormSection';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import { maskMoney, parseMoney } from '@/Hooks/useMasks';
import { PlusIcon } from '@heroicons/react/24/outline';

const formatCurrency = (value) =>
    value ? new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value) : '';

export default function CreateModal({ show, onClose, onSuccess, stores = [] }) {
    const [form, setForm] = useState({
        store_id: '', reference_month: new Date().getMonth() + 1,
        reference_year: new Date().getFullYear(), goal_amount: '',
        business_days: 26, non_working_days: 0,
    });
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (show) {
            setForm({
                store_id: '', reference_month: new Date().getMonth() + 1,
                reference_year: new Date().getFullYear(), goal_amount: '',
                business_days: 26, non_working_days: 0,
            });
            setErrors({});
        }
    }, [show]);

    const handleChange = (field, value) => {
        setForm(prev => ({ ...prev, [field]: value }));
        setErrors(prev => ({ ...prev, [field]: null }));
    };

    const goalValue = parseMoney(form.goal_amount);
    const superGoal = goalValue > 0 ? goalValue * 1.15 : 0;

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

    const handleSubmit = () => {
        const newErrors = {};
        if (!form.store_id) newErrors.store_id = 'Selecione uma loja.';
        if (!form.goal_amount || parseMoney(form.goal_amount) <= 0) newErrors.goal_amount = 'Informe um valor válido.';
        if (!form.business_days || form.business_days < 1) newErrors.business_days = 'Informe os dias úteis.';

        if (Object.keys(newErrors).length > 0) { setErrors(newErrors); return; }

        setProcessing(true);
        router.post('/store-goals', {
            store_id: parseInt(form.store_id), reference_month: parseInt(form.reference_month),
            reference_year: parseInt(form.reference_year), goal_amount: parseMoney(form.goal_amount),
            business_days: parseInt(form.business_days), non_working_days: parseInt(form.non_working_days) || 0,
        }, {
            preserveScroll: true,
            onSuccess: () => { setProcessing(false); onSuccess(); },
            onError: (errs) => { setProcessing(false); setErrors(errs); },
        });
    };

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title="Nova Meta de Loja"
            headerColor="bg-indigo-600"
            headerIcon={<PlusIcon className="h-5 w-5" />}
            onSubmit={handleSubmit}
            footer={
                <StandardModal.Footer onCancel={onClose} onSubmit="submit"
                    submitLabel="Criar Meta" processing={processing} />
            }
        >
            <FormSection title="Loja e Período" cols={3}>
                <div className="col-span-full sm:col-span-1">
                    <InputLabel value="Loja *" />
                    <select value={form.store_id} onChange={(e) => handleChange('store_id', e.target.value)}
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Selecione uma loja...</option>
                        {stores.map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
                    </select>
                    <InputError message={errors.store_id} className="mt-1" />
                </div>
                <div>
                    <InputLabel value="Mês" />
                    <select value={form.reference_month} onChange={(e) => handleChange('reference_month', e.target.value)}
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        {months.map(m => <option key={m.value} value={m.value}>{m.label}</option>)}
                    </select>
                </div>
                <div>
                    <InputLabel value="Ano" />
                    <select value={form.reference_year} onChange={(e) => handleChange('reference_year', e.target.value)}
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        {years.map(y => <option key={y} value={y}>{y}</option>)}
                    </select>
                </div>
            </FormSection>

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
