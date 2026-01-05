import { useEffect } from "react";
import { useForm } from "@inertiajs/react";
import Modal from "@/Components/Modal";
import Button from "@/Components/Button";
import TextInput from "@/Components/TextInput";
import InputLabel from "@/Components/InputLabel";
import InputError from "@/Components/InputError";

export default function StoreEditModal({ isOpen, onClose, onSuccess, store, networks, managers }) {
    const { data, setData, put, processing, errors, reset } = useForm({
        code: '',
        name: '',
        cnpj: '',
        company_name: '',
        state_registration: '',
        address: '',
        network_id: '',
        manager_id: '',
        supervisor_id: '',
        store_order: 1,
        network_order: 1,
        status_id: 1,
    });

    useEffect(() => {
        if (store) {
            setData({
                code: store.code || '',
                name: store.name || '',
                cnpj: store.cnpj || '',
                company_name: store.company_name || '',
                state_registration: store.state_registration || '',
                address: store.address || '',
                network_id: store.network_id || '',
                manager_id: store.manager_id || '',
                supervisor_id: store.supervisor_id || '',
                store_order: store.store_order || 1,
                network_order: store.network_order || 1,
                status_id: store.status_id || 1,
            });
        }
    }, [store]);

    const handleSubmit = (e) => {
        e.preventDefault();

        if (!store) return;

        put(`/stores/${store.id}`, {
            onSuccess: () => {
                reset();
                onSuccess();
                onClose();
            },
        });
    };

    const handleClose = () => {
        reset();
        onClose();
    };

    const formatCNPJ = (value) => {
        const numbers = value.replace(/\D/g, '');
        if (numbers.length <= 14) {
            return numbers.replace(
                /^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/,
                '$1.$2.$3/$4-$5'
            );
        }
        return value;
    };

    const handleCNPJChange = (e) => {
        const formatted = formatCNPJ(e.target.value);
        setData('cnpj', formatted);
    };

    if (!store) return null;

    return (
        <Modal show={isOpen} onClose={handleClose} maxWidth="2xl">
            <form onSubmit={handleSubmit} className="p-6">
                <h3 className="text-lg font-medium text-gray-900 mb-6">
                    Editar Loja: {store.code}
                </h3>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {/* Codigo */}
                    <div>
                        <InputLabel htmlFor="code" value="Codigo *" />
                        <TextInput
                            id="code"
                            type="text"
                            value={data.code}
                            onChange={(e) => setData('code', e.target.value.toUpperCase())}
                            className="mt-1 block w-full"
                            placeholder="Z000"
                            maxLength={4}
                        />
                        <InputError message={errors.code} className="mt-2" />
                    </div>

                    {/* Nome */}
                    <div>
                        <InputLabel htmlFor="name" value="Nome da Loja *" />
                        <TextInput
                            id="name"
                            type="text"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            className="mt-1 block w-full"
                            placeholder="Nome da loja"
                        />
                        <InputError message={errors.name} className="mt-2" />
                    </div>

                    {/* CNPJ */}
                    <div>
                        <InputLabel htmlFor="cnpj" value="CNPJ *" />
                        <TextInput
                            id="cnpj"
                            type="text"
                            value={data.cnpj}
                            onChange={handleCNPJChange}
                            className="mt-1 block w-full"
                            placeholder="00.000.000/0000-00"
                            maxLength={18}
                        />
                        <InputError message={errors.cnpj} className="mt-2" />
                    </div>

                    {/* Inscricao Estadual */}
                    <div>
                        <InputLabel htmlFor="state_registration" value="Inscricao Estadual" />
                        <TextInput
                            id="state_registration"
                            type="text"
                            value={data.state_registration}
                            onChange={(e) => setData('state_registration', e.target.value)}
                            className="mt-1 block w-full"
                            placeholder="000000000"
                        />
                        <InputError message={errors.state_registration} className="mt-2" />
                    </div>

                    {/* Razao Social */}
                    <div className="md:col-span-2">
                        <InputLabel htmlFor="company_name" value="Razao Social *" />
                        <TextInput
                            id="company_name"
                            type="text"
                            value={data.company_name}
                            onChange={(e) => setData('company_name', e.target.value)}
                            className="mt-1 block w-full"
                            placeholder="Razao social completa"
                        />
                        <InputError message={errors.company_name} className="mt-2" />
                    </div>

                    {/* Endereco */}
                    <div className="md:col-span-2">
                        <InputLabel htmlFor="address" value="Endereco *" />
                        <TextInput
                            id="address"
                            type="text"
                            value={data.address}
                            onChange={(e) => setData('address', e.target.value)}
                            className="mt-1 block w-full"
                            placeholder="Endereco completo"
                        />
                        <InputError message={errors.address} className="mt-2" />
                    </div>

                    {/* Rede */}
                    <div>
                        <InputLabel htmlFor="network_id" value="Rede *" />
                        <select
                            id="network_id"
                            value={data.network_id}
                            onChange={(e) => setData('network_id', e.target.value)}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Selecione uma rede</option>
                            {networks.map((network) => (
                                <option key={network.id} value={network.id}>
                                    {network.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.network_id} className="mt-2" />
                    </div>

                    {/* Status */}
                    <div>
                        <InputLabel htmlFor="status_id" value="Status" />
                        <select
                            id="status_id"
                            value={data.status_id}
                            onChange={(e) => setData('status_id', e.target.value)}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="1">Ativa</option>
                            <option value="2">Inativa</option>
                        </select>
                        <InputError message={errors.status_id} className="mt-2" />
                    </div>

                    {/* Gerente */}
                    <div>
                        <InputLabel htmlFor="manager_id" value="Gerente *" />
                        <select
                            id="manager_id"
                            value={data.manager_id}
                            onChange={(e) => setData('manager_id', e.target.value)}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Selecione um gerente</option>
                            {managers.map((manager) => (
                                <option key={manager.id} value={manager.id}>
                                    {manager.short_name || manager.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.manager_id} className="mt-2" />
                    </div>

                    {/* Supervisor */}
                    <div>
                        <InputLabel htmlFor="supervisor_id" value="Supervisor *" />
                        <select
                            id="supervisor_id"
                            value={data.supervisor_id}
                            onChange={(e) => setData('supervisor_id', e.target.value)}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="">Selecione um supervisor</option>
                            {managers.map((manager) => (
                                <option key={manager.id} value={manager.id}>
                                    {manager.short_name || manager.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.supervisor_id} className="mt-2" />
                    </div>

                    {/* Ordem da Loja */}
                    <div>
                        <InputLabel htmlFor="store_order" value="Ordem da Loja *" />
                        <TextInput
                            id="store_order"
                            type="number"
                            value={data.store_order}
                            onChange={(e) => setData('store_order', parseInt(e.target.value) || 1)}
                            className="mt-1 block w-full"
                            min={1}
                        />
                        <InputError message={errors.store_order} className="mt-2" />
                    </div>

                    {/* Ordem na Rede */}
                    <div>
                        <InputLabel htmlFor="network_order" value="Ordem na Rede *" />
                        <TextInput
                            id="network_order"
                            type="number"
                            value={data.network_order}
                            onChange={(e) => setData('network_order', parseInt(e.target.value) || 1)}
                            className="mt-1 block w-full"
                            min={1}
                        />
                        <InputError message={errors.network_order} className="mt-2" />
                    </div>
                </div>

                <div className="flex justify-end space-x-3 mt-6">
                    <Button type="button" variant="secondary" onClick={handleClose}>
                        Cancelar
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Salvando...' : 'Salvar'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
