import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import StandardModal from '@/Components/StandardModal';
import FormSection from '@/Components/Shared/FormSection';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import { maskMoney, parseMoney } from '@/Hooks/useMasks';
import { PlusIcon } from '@heroicons/react/24/outline';

export default function SaleCreateModal({ show, onClose, onSuccess, stores = [] }) {
    const [form, setForm] = useState({
        store_id: '', employee_id: '', date_sales: '', total_sales: '', qtde_total: '',
    });
    const [employees, setEmployees] = useState([]);
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (show) {
            setForm({ store_id: '', employee_id: '', date_sales: '', total_sales: '', qtde_total: '' });
            setErrors({});
            axios.get('/employees/list-json')
                .then(res => setEmployees(res.data.employees || []))
                .catch(() => setEmployees([]));
        }
    }, [show]);

    const handleChange = (field, value) => {
        setForm(prev => ({ ...prev, [field]: value }));
        setErrors(prev => ({ ...prev, [field]: null }));
    };

    const handleSubmit = () => {
        const newErrors = {};
        if (!form.store_id) newErrors.store_id = 'Selecione uma loja.';
        if (!form.employee_id) newErrors.employee_id = 'Selecione um funcionário.';
        if (!form.date_sales) newErrors.date_sales = 'Informe a data.';
        if (!form.total_sales || parseMoney(form.total_sales) <= 0) newErrors.total_sales = 'Informe um valor válido.';
        if (!form.qtde_total || parseInt(form.qtde_total) < 1) newErrors.qtde_total = 'Informe a quantidade.';

        if (Object.keys(newErrors).length > 0) {
            setErrors(newErrors);
            return;
        }

        setProcessing(true);
        router.post('/sales', {
            store_id: parseInt(form.store_id),
            employee_id: parseInt(form.employee_id),
            date_sales: form.date_sales,
            total_sales: parseMoney(form.total_sales),
            qtde_total: parseInt(form.qtde_total),
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
            title="Nova Venda"
            headerColor="bg-indigo-600"
            headerIcon={<PlusIcon className="h-5 w-5" />}
            maxWidth="lg"
            onSubmit={handleSubmit}
            footer={
                <StandardModal.Footer
                    onCancel={onClose}
                    onSubmit="submit"
                    submitLabel="Salvar"
                    processing={processing}
                />
            }
        >
            <FormSection title="Dados da Venda" cols={2}>
                <div>
                    <InputLabel value="Loja *" />
                    <select
                        value={form.store_id}
                        onChange={(e) => handleChange('store_id', e.target.value)}
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">Selecione...</option>
                        {stores.map(s => (
                            <option key={s.id} value={s.id}>{s.name}</option>
                        ))}
                    </select>
                    <InputError message={errors.store_id} className="mt-1" />
                </div>

                <div>
                    <InputLabel value="Funcionário *" />
                    <select
                        value={form.employee_id}
                        onChange={(e) => handleChange('employee_id', e.target.value)}
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">Selecione...</option>
                        {employees.map(emp => (
                            <option key={emp.id} value={emp.id}>{emp.name}</option>
                        ))}
                    </select>
                    <InputError message={errors.employee_id} className="mt-1" />
                </div>

                <div>
                    <InputLabel value="Data *" />
                    <TextInput
                        type="date"
                        className="mt-1 w-full"
                        value={form.date_sales}
                        max={new Date().toISOString().split('T')[0]}
                        onChange={(e) => handleChange('date_sales', e.target.value)}
                    />
                    <InputError message={errors.date_sales} className="mt-1" />
                </div>

                <div>
                    <InputLabel value="Valor (R$) *" />
                    <TextInput
                        className="mt-1 w-full"
                        inputMode="numeric"
                        value={form.total_sales}
                        onChange={(e) => handleChange('total_sales', maskMoney(e.target.value))}
                        placeholder="0,00"
                    />
                    <InputError message={errors.total_sales} className="mt-1" />
                </div>

                <div>
                    <InputLabel value="Quantidade *" />
                    <TextInput
                        type="number"
                        className="mt-1 w-full"
                        min="1"
                        value={form.qtde_total}
                        onChange={(e) => handleChange('qtde_total', e.target.value)}
                        placeholder="0"
                    />
                    <InputError message={errors.qtde_total} className="mt-1" />
                </div>
            </FormSection>
        </StandardModal>
    );
}
