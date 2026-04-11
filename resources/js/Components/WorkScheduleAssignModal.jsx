import { useState, useEffect } from 'react';
import axios from 'axios';
import StandardModal from '@/Components/StandardModal';
import FormSection from '@/Components/Shared/FormSection';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import { UserPlusIcon } from '@heroicons/react/24/outline';

export default function WorkScheduleAssignModal({ show, onClose, onSuccess, schedule }) {
    const [formData, setFormData] = useState({
        employee_id: '', effective_date: new Date().toISOString().split('T')[0], end_date: '', notes: '',
    });
    const [employees, setEmployees] = useState([]);
    const [searchTerm, setSearchTerm] = useState('');
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (show) {
            setLoading(true);
            axios.get('/employees/list-json')
                .then(res => setEmployees(res.data.employees || []))
                .catch(() => setEmployees([]))
                .finally(() => setLoading(false));
            setFormData({ employee_id: '', effective_date: new Date().toISOString().split('T')[0], end_date: '', notes: '' });
            setErrors({}); setSearchTerm('');
        }
    }, [show]);

    const filteredEmployees = employees.filter(emp => {
        if (!searchTerm) return true;
        const term = searchTerm.toLowerCase();
        return emp.name?.toLowerCase().includes(term) || emp.short_name?.toLowerCase().includes(term);
    });

    const handleSubmit = async () => {
        setProcessing(true); setErrors({});
        try {
            await axios.post(`/work-schedules/${schedule.id}/employees`, formData);
            onSuccess();
        } catch (error) {
            if (error.response?.status === 422) setErrors(error.response.data.errors || {});
        } finally { setProcessing(false); }
    };

    if (!schedule) return null;

    return (
        <StandardModal show={show} onClose={onClose}
            title={`Atribuir Funcionário`}
            subtitle={schedule.name}
            headerColor="bg-indigo-600" headerIcon={<UserPlusIcon className="h-5 w-5" />}
            onSubmit={handleSubmit}
            footer={<StandardModal.Footer onCancel={onClose} onSubmit="submit"
                submitLabel="Atribuir" processing={processing} />}>

            <FormSection title="Funcionário" cols={1}>
                <div>
                    <InputLabel value="Buscar Funcionário" />
                    <TextInput className="mt-1 w-full mb-2" value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)} placeholder="Buscar funcionário..." />
                    <select value={formData.employee_id}
                        onChange={(e) => setFormData({ ...formData, employee_id: e.target.value })}
                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required size={5}>
                        <option value="">Selecione um funcionário</option>
                        {loading ? <option disabled>Carregando...</option> : (
                            filteredEmployees.map(emp => (
                                <option key={emp.id} value={emp.id}>{emp.name} {emp.is_active ? '' : '(Inativo)'}</option>
                            ))
                        )}
                    </select>
                    <InputError message={errors.employee_id?.[0]} className="mt-1" />
                </div>
            </FormSection>

            <FormSection title="Vigência" cols={2}>
                <div>
                    <InputLabel value="Data de Vigência *" />
                    <TextInput type="date" className="mt-1 w-full" value={formData.effective_date}
                        onChange={(e) => setFormData({ ...formData, effective_date: e.target.value })} required />
                    <InputError message={errors.effective_date?.[0]} className="mt-1" />
                </div>
                <div>
                    <InputLabel value="Data de Término" />
                    <TextInput type="date" className="mt-1 w-full" value={formData.end_date}
                        onChange={(e) => setFormData({ ...formData, end_date: e.target.value })} />
                    <p className="mt-1 text-xs text-gray-500">Deixe em branco para vigência indeterminada</p>
                    <InputError message={errors.end_date?.[0]} className="mt-1" />
                </div>
            </FormSection>

            <FormSection title="Observações" cols={1}>
                <div>
                    <textarea value={formData.notes} onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                        rows={2} maxLength={500} placeholder="Observações opcionais..."
                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                </div>
            </FormSection>

            <div className="bg-blue-50 border border-blue-200 rounded-lg p-3">
                <p className="text-sm text-blue-700">
                    A atribuição anterior do funcionário (se existir) será encerrada automaticamente na data de vigência.
                </p>
            </div>
        </StandardModal>
    );
}
