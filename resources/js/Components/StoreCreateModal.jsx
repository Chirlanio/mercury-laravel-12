import { useForm } from "@inertiajs/react";
import StandardModal from "@/Components/StandardModal";
import FormSection from "@/Components/Shared/FormSection";
import TextInput from "@/Components/TextInput";
import InputLabel from "@/Components/InputLabel";
import InputError from "@/Components/InputError";
import { maskCnpj } from "@/Hooks/useMasks";
import { PlusIcon } from "@heroicons/react/24/outline";

export default function StoreCreateModal({ show, onClose, onSuccess, networks, managers }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        code: '', name: '', cnpj: '', company_name: '', state_registration: '',
        address: '', network_id: '', manager_id: '', supervisor_id: '',
        store_order: 1, network_order: 1, status_id: 1,
    });

    const handleSubmit = () => {
        post('/stores', {
            onSuccess: () => { reset(); onSuccess(); onClose(); },
        });
    };

    const handleClose = () => { reset(); onClose(); };

    return (
        <StandardModal
            show={show}
            onClose={handleClose}
            title="Nova Loja"
            headerColor="bg-indigo-600"
            headerIcon={<PlusIcon className="h-5 w-5" />}
            maxWidth="7xl"
            onSubmit={handleSubmit}
            footer={
                <StandardModal.Footer
                    onCancel={handleClose}
                    onSubmit="submit"
                    submitLabel="Salvar"
                    processing={processing}
                />
            }
        >
            <FormSection title="Identificação" cols={2}>
                <div>
                    <InputLabel htmlFor="create-code" value="Código *" />
                    <TextInput id="create-code" className="mt-1 w-full" value={data.code}
                        onChange={(e) => setData('code', e.target.value.toUpperCase())} placeholder="Z000" maxLength={4} />
                    <InputError message={errors.code} className="mt-1" />
                </div>
                <div>
                    <InputLabel htmlFor="create-name" value="Nome da Loja *" />
                    <TextInput id="create-name" className="mt-1 w-full" value={data.name}
                        onChange={(e) => setData('name', e.target.value)} placeholder="Nome da loja" />
                    <InputError message={errors.name} className="mt-1" />
                </div>
                <div>
                    <InputLabel htmlFor="create-cnpj" value="CNPJ *" />
                    <TextInput id="create-cnpj" className="mt-1 w-full" value={data.cnpj}
                        onChange={(e) => setData('cnpj', maskCnpj(e.target.value))} placeholder="00.000.000/0000-00" maxLength={18} />
                    <InputError message={errors.cnpj} className="mt-1" />
                </div>
                <div>
                    <InputLabel htmlFor="create-ie" value="Inscrição Estadual" />
                    <TextInput id="create-ie" className="mt-1 w-full" value={data.state_registration}
                        onChange={(e) => setData('state_registration', e.target.value)} placeholder="000000000" />
                    <InputError message={errors.state_registration} className="mt-1" />
                </div>
                <div className="col-span-full">
                    <InputLabel htmlFor="create-razao" value="Razão Social *" />
                    <TextInput id="create-razao" className="mt-1 w-full" value={data.company_name}
                        onChange={(e) => setData('company_name', e.target.value)} placeholder="Razão social completa" />
                    <InputError message={errors.company_name} className="mt-1" />
                </div>
                <div className="col-span-full">
                    <InputLabel htmlFor="create-address" value="Endereço *" />
                    <TextInput id="create-address" className="mt-1 w-full" value={data.address}
                        onChange={(e) => setData('address', e.target.value)} placeholder="Endereço completo" />
                    <InputError message={errors.address} className="mt-1" />
                </div>
            </FormSection>

            <FormSection title="Gestão e Classificação" cols={2}>
                <div>
                    <InputLabel htmlFor="create-network" value="Rede *" />
                    <select id="create-network" value={data.network_id} onChange={(e) => setData('network_id', e.target.value)}
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Selecione uma rede</option>
                        {networks.map((n) => <option key={n.id} value={n.id}>{n.name}</option>)}
                    </select>
                    <InputError message={errors.network_id} className="mt-1" />
                </div>
                <div>
                    <InputLabel htmlFor="create-status" value="Status" />
                    <select id="create-status" value={data.status_id} onChange={(e) => setData('status_id', e.target.value)}
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="1">Ativa</option>
                        <option value="2">Inativa</option>
                    </select>
                    <InputError message={errors.status_id} className="mt-1" />
                </div>
                <div>
                    <InputLabel htmlFor="create-manager" value="Gerente *" />
                    <select id="create-manager" value={data.manager_id} onChange={(e) => setData('manager_id', e.target.value)}
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Selecione um gerente</option>
                        {managers.map((m) => <option key={m.id} value={m.id}>{m.short_name || m.name}</option>)}
                    </select>
                    <InputError message={errors.manager_id} className="mt-1" />
                </div>
                <div>
                    <InputLabel htmlFor="create-supervisor" value="Supervisor *" />
                    <select id="create-supervisor" value={data.supervisor_id} onChange={(e) => setData('supervisor_id', e.target.value)}
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Selecione um supervisor</option>
                        {managers.map((m) => <option key={m.id} value={m.id}>{m.short_name || m.name}</option>)}
                    </select>
                    <InputError message={errors.supervisor_id} className="mt-1" />
                </div>
                <div>
                    <InputLabel htmlFor="create-store-order" value="Ordem da Loja *" />
                    <TextInput id="create-store-order" type="number" className="mt-1 w-full" value={data.store_order}
                        onChange={(e) => setData('store_order', parseInt(e.target.value) || 1)} min={1} />
                    <InputError message={errors.store_order} className="mt-1" />
                </div>
                <div>
                    <InputLabel htmlFor="create-network-order" value="Ordem na Rede *" />
                    <TextInput id="create-network-order" type="number" className="mt-1 w-full" value={data.network_order}
                        onChange={(e) => setData('network_order', parseInt(e.target.value) || 1)} min={1} />
                    <InputError message={errors.network_order} className="mt-1" />
                </div>
            </FormSection>
        </StandardModal>
    );
}
