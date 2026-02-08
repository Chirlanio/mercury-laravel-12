import { useState, useEffect } from 'react';
import Modal from '@/Components/Modal';
import Button from '@/Components/Button';

export default function WorkScheduleAssignModal({ isOpen, onClose, onSuccess, schedule }) {
    const [formData, setFormData] = useState({
        employee_id: '',
        effective_date: new Date().toISOString().split('T')[0],
        end_date: '',
        notes: '',
    });
    const [employees, setEmployees] = useState([]);
    const [searchTerm, setSearchTerm] = useState('');
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (isOpen) {
            fetchEmployees();
            setFormData({
                employee_id: '',
                effective_date: new Date().toISOString().split('T')[0],
                end_date: '',
                notes: '',
            });
            setErrors({});
            setSearchTerm('');
        }
    }, [isOpen]);

    const fetchEmployees = async () => {
        setLoading(true);
        try {
            const response = await fetch('/employees?per_page=1000');
            const html = await response.text();

            // Parse Inertia page props from the HTML response
            const match = html.match(/data-page="([^"]+)"/);
            if (match) {
                const pageData = JSON.parse(match[1].replace(/&quot;/g, '"'));
                setEmployees(pageData.props?.employees?.data || []);
            }
        } catch (error) {
            console.error('Erro ao carregar funcionários:', error);
            setEmployees([]);
        } finally {
            setLoading(false);
        }
    };

    const filteredEmployees = employees.filter(emp => {
        if (!searchTerm) return true;
        const term = searchTerm.toLowerCase();
        return emp.name?.toLowerCase().includes(term) || emp.short_name?.toLowerCase().includes(term);
    });

    const handleSubmit = async (e) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            const response = await fetch(`/work-schedules/${schedule.id}/employees`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(formData),
            });

            const data = await response.json();

            if (!response.ok) {
                if (data.errors) setErrors(data.errors);
                return;
            }

            onSuccess();
        } catch (error) {
            console.error('Erro ao atribuir funcionário:', error);
        } finally {
            setProcessing(false);
        }
    };

    if (!isOpen || !schedule) return null;

    return (
        <Modal show={isOpen} onClose={onClose} title={`Atribuir Funcionário - ${schedule.name}`} maxWidth="lg">
            <form onSubmit={handleSubmit} className="space-y-4">
                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">Funcionário *</label>
                    <input
                        type="text"
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 mb-2"
                        placeholder="Buscar funcionário..."
                    />
                    <select
                        value={formData.employee_id}
                        onChange={(e) => setFormData({ ...formData, employee_id: e.target.value })}
                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        required
                        size={5}
                    >
                        <option value="">Selecione um funcionário</option>
                        {loading ? (
                            <option disabled>Carregando...</option>
                        ) : (
                            filteredEmployees.map((emp) => (
                                <option key={emp.id} value={emp.id}>
                                    {emp.name} {emp.is_active ? '' : '(Inativo)'}
                                </option>
                            ))
                        )}
                    </select>
                    {errors.employee_id && <p className="mt-1 text-sm text-red-600">{errors.employee_id[0]}</p>}
                </div>

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

                <div className="bg-blue-50 p-3 rounded-md">
                    <p className="text-sm text-blue-700">
                        A atribuição anterior do funcionário (se existir) será encerrada automaticamente na data de vigência.
                    </p>
                </div>

                <div className="flex justify-end gap-3 pt-4 border-t">
                    <Button type="button" variant="outline" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button type="submit" variant="primary" disabled={processing}>
                        {processing ? 'Atribuindo...' : 'Atribuir'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
