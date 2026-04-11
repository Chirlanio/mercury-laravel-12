import { useState, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import StandardModal from '@/Components/StandardModal';
import FormSection from '@/Components/Shared/FormSection';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import Checkbox from '@/Components/Checkbox';
import { PlusIcon } from '@heroicons/react/24/outline';

const AUDIT_TYPES = [
    { value: 'total', label: 'Total' },
    { value: 'parcial', label: 'Parcial' },
    { value: 'especifica', label: 'Específica' },
    { value: 'aleatoria', label: 'Aleatória' },
    { value: 'diaria', label: 'Diária' },
];

export default function CreateModal({ show, onClose, stores = [], onSuccess }) {
    const [vendors, setVendors] = useState([]);
    const [auditCycles, setAuditCycles] = useState([]);
    const [employees, setEmployees] = useState([]);
    const [loadingOptions, setLoadingOptions] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        store_id: '', audit_type: 'total', vendor_id: '', audit_cycle_id: '',
        manager_responsible_id: '', stockist_id: '', random_sample_size: 10,
        requires_second_count: true, requires_third_count: false, notes: '',
    });

    useEffect(() => {
        if (!show) return;
        setLoadingOptions(true);
        fetch(route('stock-audits.create-options'), {
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
        })
            .then(r => r.json())
            .then(json => { setVendors(json.vendors || []); setAuditCycles(json.audit_cycles || []); setEmployees(json.employees || []); })
            .catch(() => {})
            .finally(() => setLoadingOptions(false));
    }, [show]);

    useEffect(() => {
        if (data.audit_type === 'total') setData('requires_second_count', true);
    }, [data.audit_type]);

    const handleSubmit = () => {
        post(route('stock-audits.store'), { onSuccess: () => { reset(); onSuccess?.(); } });
    };

    const handleClose = () => { reset(); onClose(); };

    return (
        <StandardModal show={show} onClose={handleClose} title="Nova Auditoria de Estoque"
            headerColor="bg-indigo-600" headerIcon={<PlusIcon className="h-5 w-5" />}
            onSubmit={handleSubmit}
            footer={<StandardModal.Footer onCancel={handleClose} onSubmit="submit" submitLabel="Criar Auditoria" processing={processing} />}>

            <AuditFormFields data={data} setData={setData} errors={errors} stores={stores}
                vendors={vendors} auditCycles={auditCycles} employees={employees} loadingOptions={loadingOptions} />
        </StandardModal>
    );
}

export function AuditFormFields({ data, setData, errors, stores, vendors, auditCycles, employees, loadingOptions }) {
    return (
        <>
            <FormSection title="Informações Gerais" cols={2}>
                <div>
                    <InputLabel value="Loja *" />
                    <select value={data.store_id} onChange={(e) => setData('store_id', e.target.value)}
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="">Selecione uma loja...</option>
                        {stores.map(s => <option key={s.id} value={s.id}>{s.code ? `${s.code} - ` : ''}{s.name}</option>)}
                    </select>
                    <InputError message={errors.store_id} className="mt-1" />
                </div>
                <div>
                    <InputLabel value="Tipo de Auditoria *" />
                    <select value={data.audit_type} onChange={(e) => setData('audit_type', e.target.value)}
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        {AUDIT_TYPES.map(t => <option key={t.value} value={t.value}>{t.label}</option>)}
                    </select>
                    <InputError message={errors.audit_type} className="mt-1" />
                </div>
                <div>
                    <InputLabel value="Fornecedor" />
                    <select value={data.vendor_id} onChange={(e) => setData('vendor_id', e.target.value)} disabled={loadingOptions}
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-100">
                        <option value="">Nenhum (opcional)</option>
                        {vendors.map(v => <option key={v.id} value={v.id}>{v.name}</option>)}
                    </select>
                </div>
                <div>
                    <InputLabel value="Ciclo de Auditoria" />
                    <select value={data.audit_cycle_id} onChange={(e) => setData('audit_cycle_id', e.target.value)} disabled={loadingOptions}
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-100">
                        <option value="">Nenhum (opcional)</option>
                        {auditCycles.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                    </select>
                </div>
            </FormSection>

            <FormSection title="Responsáveis" cols={2}>
                <div>
                    <InputLabel value="Gerente Responsável *" />
                    <select value={data.manager_responsible_id} onChange={(e) => setData('manager_responsible_id', e.target.value)} disabled={loadingOptions}
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-100">
                        <option value="">Selecione...</option>
                        {employees.map(e => <option key={e.id} value={e.id}>{e.name}</option>)}
                    </select>
                    <InputError message={errors.manager_responsible_id} className="mt-1" />
                </div>
                <div>
                    <InputLabel value="Estoquista *" />
                    <select value={data.stockist_id} onChange={(e) => setData('stockist_id', e.target.value)} disabled={loadingOptions}
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-100">
                        <option value="">Selecione...</option>
                        {employees.map(e => <option key={e.id} value={e.id}>{e.name}</option>)}
                    </select>
                    <InputError message={errors.stockist_id} className="mt-1" />
                </div>
            </FormSection>

            {data.audit_type === 'aleatoria' && (
                <FormSection title="Amostragem" cols={1}>
                    <div>
                        <InputLabel value="Tamanho da Amostra *" />
                        <TextInput type="number" min={10} className="mt-1 w-full" value={data.random_sample_size}
                            onChange={(e) => setData('random_sample_size', parseInt(e.target.value) || 10)} />
                        <p className="mt-1 text-xs text-gray-500">Mínimo: 10 itens</p>
                        <InputError message={errors.random_sample_size} className="mt-1" />
                    </div>
                </FormSection>
            )}

            <FormSection title="Configurações" cols={1}>
                <div className="flex items-center gap-2">
                    <Checkbox checked={data.requires_second_count}
                        onChange={(e) => setData('requires_second_count', e.target.checked)}
                        disabled={data.audit_type === 'total'} />
                    <span className="text-sm text-gray-700">
                        Requer segunda contagem
                        {data.audit_type === 'total' && <span className="text-xs text-gray-400 ml-1">(obrigatório para auditoria total)</span>}
                    </span>
                </div>
                <div className="flex items-center gap-2">
                    <Checkbox checked={data.requires_third_count}
                        onChange={(e) => setData('requires_third_count', e.target.checked)} />
                    <span className="text-sm text-gray-700">Requer terceira contagem</span>
                </div>
            </FormSection>

            <FormSection title="Observações" cols={1}>
                <div>
                    <textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} rows={3}
                        placeholder="Observações adicionais sobre a auditoria..."
                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                    <InputError message={errors.notes} className="mt-1" />
                </div>
            </FormSection>
        </>
    );
}
