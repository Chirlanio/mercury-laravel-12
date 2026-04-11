import { useState } from 'react';
import { router } from '@inertiajs/react';
import { ClipboardDocumentCheckIcon } from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';

export default function EvaluationFormModal({ show, onClose, onSuccess, stores = [] }) {
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});
    const [data, setData] = useState({
        employee_id: '', manager_id: '', store_id: '',
        milestone: '45', date_admission: '', milestone_date: '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        router.post(route('experience-tracker.store'), data, {
            preserveScroll: true,
            onSuccess: () => { setProcessing(false); onSuccess?.(); },
            onError: (errs) => { setProcessing(false); setErrors(errs); },
        });
    };

    const setField = (field, value) => setData(prev => ({ ...prev, [field]: value }));

    // Auto-calculate milestone date
    const handleAdmissionChange = (value) => {
        setField('date_admission', value);
        if (value) {
            const admission = new Date(value);
            const days = parseInt(data.milestone) || 45;
            admission.setDate(admission.getDate() + days);
            setField('milestone_date', admission.toISOString().split('T')[0]);
        }
    };

    const handleMilestoneChange = (value) => {
        setField('milestone', value);
        if (data.date_admission) {
            const admission = new Date(data.date_admission);
            admission.setDate(admission.getDate() + parseInt(value));
            setField('milestone_date', admission.toISOString().split('T')[0]);
        }
    };

    return (
        <StandardModal
            show={show} onClose={onClose}
            title="Nova Avaliacao de Experiencia"
            headerColor="bg-teal-600" headerIcon={<ClipboardDocumentCheckIcon className="h-5 w-5" />}
            maxWidth="2xl" onSubmit={handleSubmit}
            footer={<StandardModal.Footer onCancel={onClose} onSubmit="submit" submitLabel="Criar" processing={processing} />}
        >
            <StandardModal.Section title="Dados da Avaliacao">
                <div className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="ID do Colaborador *" />
                            <TextInput type="number" className="mt-1 block w-full" value={data.employee_id}
                                onChange={e => setField('employee_id', e.target.value)} required placeholder="ID" />
                            <InputError message={errors.employee_id} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="ID do Gestor *" />
                            <TextInput type="number" className="mt-1 block w-full" value={data.manager_id}
                                onChange={e => setField('manager_id', e.target.value)} required placeholder="ID do usuario" />
                            <InputError message={errors.manager_id} className="mt-1" />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="Loja *" />
                            <select className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={data.store_id} onChange={e => setField('store_id', e.target.value)} required>
                                <option value="">Selecione...</option>
                                {stores?.map(s => <option key={s.code} value={s.code}>{s.name}</option>)}
                            </select>
                            <InputError message={errors.store_id} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Marco *" />
                            <select className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={data.milestone} onChange={e => handleMilestoneChange(e.target.value)}>
                                <option value="45">45 dias</option>
                                <option value="90">90 dias</option>
                            </select>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="Data Admissao *" />
                            <TextInput type="date" className="mt-1 block w-full" value={data.date_admission}
                                onChange={e => handleAdmissionChange(e.target.value)} required />
                            <InputError message={errors.date_admission} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Data Prazo *" />
                            <TextInput type="date" className="mt-1 block w-full" value={data.milestone_date}
                                onChange={e => setField('milestone_date', e.target.value)} required />
                            <InputError message={errors.milestone_date} className="mt-1" />
                        </div>
                    </div>
                </div>
            </StandardModal.Section>
        </StandardModal>
    );
}
