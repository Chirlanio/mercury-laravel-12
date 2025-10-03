import { useEffect, useState } from 'react';
import Modal from '@/Components/Modal';
import Button from '@/Components/Button';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';

export default function ContractEditModal({ show, onClose, employeeId, contract, positions, stores, movementTypes, onSuccess }) {
    const [formData, setFormData] = useState({
        position_id: '',
        movement_type_id: '',
        store_id: '',
        start_date: '',
        end_date: '',
    });
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (show && contract) {
            // Populate form with contract data
            // Convert dates from dd/mm/yyyy to yyyy-mm-dd for input[type=date]
            const convertToInputDate = (dateStr) => {
                if (!dateStr || dateStr === 'Atual') return '';
                const [day, month, year] = dateStr.split('/');
                return `${year}-${month}-${day}`;
            };

            // Find IDs from names
            const position = positions?.find(p => p.name === contract.position);
            const movementType = movementTypes?.find(mt => mt.name === contract.movement_type);
            const store = stores?.find(s => s.name === contract.store || s.code === contract.store);

            setFormData({
                position_id: position?.id || '',
                movement_type_id: movementType?.id || '',
                store_id: store?.code || '',
                start_date: convertToInputDate(contract.start_date),
                end_date: convertToInputDate(contract.end_date),
            });
        } else if (!show) {
            // Reset form when modal closes
            setFormData({
                position_id: '',
                movement_type_id: '',
                store_id: '',
                start_date: '',
                end_date: '',
            });
            setErrors({});
        }
    }, [show, contract, positions, movementTypes, stores]);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        try {
            const response = await fetch(`/employees/${employeeId}/contracts/${contract.id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify(formData),
            });

            const data = await response.json();

            if (!response.ok) {
                if (response.status === 422) {
                    setErrors(data.errors || {});
                } else {
                    throw new Error(data.message || 'Erro ao atualizar contrato');
                }
                return;
            }

            // Success
            if (onSuccess) {
                onSuccess(data.contract);
            }
            onClose();
        } catch (error) {
            console.error('Error updating contract:', error);
            setErrors({ general: error.message || 'Erro ao atualizar contrato' });
        } finally {
            setProcessing(false);
        }
    };

    const handleChange = (field, value) => {
        setFormData(prev => ({ ...prev, [field]: value }));
        // Clear error for this field
        if (errors[field]) {
            setErrors(prev => {
                const newErrors = { ...prev };
                delete newErrors[field];
                return newErrors;
            });
        }
    };

    return (
        <Modal show={show} onClose={onClose} title="Editar Contrato" maxWidth="85vw">
            <form onSubmit={handleSubmit} className="space-y-6">
                {/* Error geral */}
                {errors.general && (
                    <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded relative">
                        <span className="block sm:inline">{errors.general}</span>
                    </div>
                )}

                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {/* Cargo */}
                    <div>
                        <InputLabel htmlFor="position_id" value="Cargo *" />
                        <select
                            id="position_id"
                            value={formData.position_id}
                            onChange={(e) => handleChange('position_id', e.target.value)}
                            className="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                            disabled={processing}
                        >
                            <option value="">Selecione um cargo</option>
                            {positions?.map((position) => (
                                <option key={position.id} value={position.id}>
                                    {position.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.position_id?.[0]} className="mt-2" />
                    </div>

                    {/* Tipo de Movimentação */}
                    <div>
                        <InputLabel htmlFor="movement_type_id" value="Tipo de Movimentação *" />
                        <select
                            id="movement_type_id"
                            value={formData.movement_type_id}
                            onChange={(e) => handleChange('movement_type_id', e.target.value)}
                            className="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                            disabled={processing}
                        >
                            <option value="">Selecione o tipo</option>
                            {movementTypes?.map((type) => (
                                <option key={type.id} value={type.id}>
                                    {type.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.movement_type_id?.[0]} className="mt-2" />
                    </div>

                    {/* Loja */}
                    <div>
                        <InputLabel htmlFor="store_id" value="Loja *" />
                        <select
                            id="store_id"
                            value={formData.store_id}
                            onChange={(e) => handleChange('store_id', e.target.value)}
                            className="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                            disabled={processing}
                        >
                            <option value="">Selecione uma loja</option>
                            {stores?.map((store) => (
                                <option key={store.code} value={store.code}>
                                    {store.name || store.code}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.store_id?.[0]} className="mt-2" />
                    </div>

                    {/* Data de Início */}
                    <div>
                        <InputLabel htmlFor="start_date" value="Data de Início *" />
                        <TextInput
                            id="start_date"
                            type="date"
                            value={formData.start_date}
                            onChange={(e) => handleChange('start_date', e.target.value)}
                            className="mt-1 block w-full"
                            disabled={processing}
                        />
                        <InputError message={errors.start_date?.[0]} className="mt-2" />
                    </div>

                    {/* Data de Término */}
                    <div className="md:col-span-2">
                        <InputLabel htmlFor="end_date" value="Data de Término (deixe em branco se ainda estiver ativo)" />
                        <TextInput
                            id="end_date"
                            type="date"
                            value={formData.end_date}
                            onChange={(e) => handleChange('end_date', e.target.value)}
                            className="mt-1 block w-full"
                            disabled={processing}
                        />
                        <InputError message={errors.end_date?.[0]} className="mt-2" />
                    </div>
                </div>

                {/* Rodapé */}
                <div className="flex justify-end space-x-3 pt-4 border-t">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onClose}
                        disabled={processing}
                    >
                        Cancelar
                    </Button>
                    <Button
                        type="submit"
                        variant="primary"
                        disabled={processing}
                        icon={({ className }) => (
                            <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                            </svg>
                        )}
                    >
                        {processing ? 'Salvando...' : 'Salvar Alterações'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
