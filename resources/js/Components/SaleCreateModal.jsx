import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import Button from '@/Components/Button';
import axios from 'axios';

export default function SaleCreateModal({ isOpen, onClose, onSuccess, stores = [] }) {
    const [form, setForm] = useState({
        store_id: '',
        employee_id: '',
        date_sales: '',
        total_sales: '',
        qtde_total: '',
    });
    const [employees, setEmployees] = useState([]);
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (isOpen) {
            setForm({ store_id: '', employee_id: '', date_sales: '', total_sales: '', qtde_total: '' });
            setErrors({});
            loadEmployees();
        }
    }, [isOpen]);

    const loadEmployees = () => {
        axios.get('/employees/list-json')
            .then(res => setEmployees(res.data.employees || []))
            .catch(() => setEmployees([]));
    };

    const handleChange = (field, value) => {
        setForm(prev => ({ ...prev, [field]: value }));
        setErrors(prev => ({ ...prev, [field]: null }));
    };

    const handleSubmit = (e) => {
        e.preventDefault();

        // Client-side validation
        const newErrors = {};
        if (!form.store_id) newErrors.store_id = 'Selecione uma loja.';
        if (!form.employee_id) newErrors.employee_id = 'Selecione um funcionário.';
        if (!form.date_sales) newErrors.date_sales = 'Informe a data.';
        if (!form.total_sales || parseFloat(form.total_sales) <= 0) newErrors.total_sales = 'Informe um valor válido.';
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
            total_sales: parseFloat(form.total_sales),
            qtde_total: parseInt(form.qtde_total),
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
        <Modal show={isOpen} onClose={onClose} title="Nova Venda" maxWidth="lg">
            <form onSubmit={handleSubmit} className="p-6 space-y-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Loja</label>
                    <select
                        value={form.store_id}
                        onChange={(e) => handleChange('store_id', e.target.value)}
                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">Selecione...</option>
                        {stores.map(s => (
                            <option key={s.id} value={s.id}>{s.name}</option>
                        ))}
                    </select>
                    {errors.store_id && <p className="mt-1 text-sm text-red-600">{errors.store_id}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Funcionário</label>
                    <select
                        value={form.employee_id}
                        onChange={(e) => handleChange('employee_id', e.target.value)}
                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">Selecione...</option>
                        {employees.map(emp => (
                            <option key={emp.id} value={emp.id}>{emp.name}</option>
                        ))}
                    </select>
                    {errors.employee_id && <p className="mt-1 text-sm text-red-600">{errors.employee_id}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Data</label>
                    <input
                        type="date"
                        value={form.date_sales}
                        max={new Date().toISOString().split('T')[0]}
                        onChange={(e) => handleChange('date_sales', e.target.value)}
                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    {errors.date_sales && <p className="mt-1 text-sm text-red-600">{errors.date_sales}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Valor (R$)</label>
                    <input
                        type="number"
                        step="0.01"
                        min="0.01"
                        value={form.total_sales}
                        onChange={(e) => handleChange('total_sales', e.target.value)}
                        placeholder="0,00"
                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    {errors.total_sales && <p className="mt-1 text-sm text-red-600">{errors.total_sales}</p>}
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Quantidade</label>
                    <input
                        type="number"
                        min="1"
                        value={form.qtde_total}
                        onChange={(e) => handleChange('qtde_total', e.target.value)}
                        placeholder="0"
                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    {errors.qtde_total && <p className="mt-1 text-sm text-red-600">{errors.qtde_total}</p>}
                </div>

                <div className="flex justify-end gap-3 pt-4">
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button type="submit" variant="primary" loading={processing}>
                        Salvar
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
