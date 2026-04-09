import { useState, useEffect } from 'react';
import Modal from '@/Components/Modal';
import Button from '@/Components/Button';
import { useForm } from '@inertiajs/react';

const AUDIT_TYPES = [
    { value: 'total', label: 'Total' },
    { value: 'parcial', label: 'Parcial' },
    { value: 'especifica', label: 'Especifica' },
    { value: 'aleatoria', label: 'Aleatoria' },
    { value: 'diaria', label: 'Diaria' },
];

export default function CreateModal({ show, onClose, stores = [], onSuccess }) {
    const [vendors, setVendors] = useState([]);
    const [auditCycles, setAuditCycles] = useState([]);
    const [employees, setEmployees] = useState([]);
    const [loadingOptions, setLoadingOptions] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        store_id: '',
        audit_type: 'total',
        vendor_id: '',
        audit_cycle_id: '',
        manager_responsible_id: '',
        stockist_id: '',
        random_sample_size: 10,
        requires_second_count: true,
        requires_third_count: false,
        notes: '',
    });

    const csrfToken = () =>
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    useEffect(() => {
        if (!show) return;

        setLoadingOptions(true);
        fetch(route('stock-audits.create-options'), {
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
        })
            .then((res) => res.json())
            .then((json) => {
                setVendors(json.vendors || []);
                setAuditCycles(json.audit_cycles || []);
                setEmployees(json.employees || []);
            })
            .catch(() => {})
            .finally(() => setLoadingOptions(false));
    }, [show]);

    useEffect(() => {
        if (data.audit_type === 'total') {
            setData('requires_second_count', true);
        }
    }, [data.audit_type]);

    const handleSubmit = (e) => {
        e.preventDefault();

        post(route('stock-audits.store'), {
            onSuccess: () => {
                reset();
                onSuccess?.();
            },
        });
    };

    const handleClose = () => {
        reset();
        onClose();
    };

    return (
        <Modal show={show} onClose={handleClose} maxWidth="2xl">
            <div className="flex flex-col max-h-[80vh]">
                {/* Header */}
                <div className="flex items-center justify-between px-6 py-4 bg-indigo-600 text-white flex-shrink-0 rounded-t-lg">
                    <h2 className="text-lg font-semibold">Nova Auditoria de Estoque</h2>
                    <button onClick={handleClose} className="text-white/80 hover:text-white transition">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {/* Body */}
                <form onSubmit={handleSubmit} className="flex flex-col flex-1 overflow-hidden">
                    <div className="flex-1 overflow-y-auto p-6 space-y-5">
                        {/* Loja */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Loja <span className="text-red-500">*</span>
                            </label>
                            <select
                                value={data.store_id}
                                onChange={(e) => setData('store_id', e.target.value)}
                                className={`w-full rounded-md border shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm ${
                                    errors.store_id ? 'border-red-300' : 'border-gray-300'
                                }`}
                            >
                                <option value="">Selecione uma loja...</option>
                                {stores.map((store) => (
                                    <option key={store.id} value={store.id}>
                                        {store.code ? `${store.code} - ` : ''}{store.name}
                                    </option>
                                ))}
                            </select>
                            {errors.store_id && <p className="mt-1 text-sm text-red-600">{errors.store_id}</p>}
                        </div>

                        {/* Tipo de Auditoria */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Tipo de Auditoria <span className="text-red-500">*</span>
                            </label>
                            <select
                                value={data.audit_type}
                                onChange={(e) => setData('audit_type', e.target.value)}
                                className={`w-full rounded-md border shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm ${
                                    errors.audit_type ? 'border-red-300' : 'border-gray-300'
                                }`}
                            >
                                {AUDIT_TYPES.map((t) => (
                                    <option key={t.value} value={t.value}>{t.label}</option>
                                ))}
                            </select>
                            {errors.audit_type && <p className="mt-1 text-sm text-red-600">{errors.audit_type}</p>}
                        </div>

                        {/* Fornecedor (opcional) */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Fornecedor
                            </label>
                            <select
                                value={data.vendor_id}
                                onChange={(e) => setData('vendor_id', e.target.value)}
                                disabled={loadingOptions}
                                className="w-full rounded-md border border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm disabled:bg-gray-100"
                            >
                                <option value="">Nenhum (opcional)</option>
                                {vendors.map((v) => (
                                    <option key={v.id} value={v.id}>{v.name}</option>
                                ))}
                            </select>
                            {errors.vendor_id && <p className="mt-1 text-sm text-red-600">{errors.vendor_id}</p>}
                        </div>

                        {/* Ciclo de Auditoria (opcional) */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Ciclo de Auditoria
                            </label>
                            <select
                                value={data.audit_cycle_id}
                                onChange={(e) => setData('audit_cycle_id', e.target.value)}
                                disabled={loadingOptions}
                                className="w-full rounded-md border border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm disabled:bg-gray-100"
                            >
                                <option value="">Nenhum (opcional)</option>
                                {auditCycles.map((c) => (
                                    <option key={c.id} value={c.id}>{c.name}</option>
                                ))}
                            </select>
                            {errors.audit_cycle_id && <p className="mt-1 text-sm text-red-600">{errors.audit_cycle_id}</p>}
                        </div>

                        {/* Gerente Responsavel */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Gerente Responsavel <span className="text-red-500">*</span>
                            </label>
                            <select
                                value={data.manager_responsible_id}
                                onChange={(e) => setData('manager_responsible_id', e.target.value)}
                                disabled={loadingOptions}
                                className={`w-full rounded-md border shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm disabled:bg-gray-100 ${
                                    errors.manager_responsible_id ? 'border-red-300' : 'border-gray-300'
                                }`}
                            >
                                <option value="">Selecione...</option>
                                {employees.map((emp) => (
                                    <option key={emp.id} value={emp.id}>{emp.name}</option>
                                ))}
                            </select>
                            {errors.manager_responsible_id && (
                                <p className="mt-1 text-sm text-red-600">{errors.manager_responsible_id}</p>
                            )}
                        </div>

                        {/* Estoquista */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Estoquista <span className="text-red-500">*</span>
                            </label>
                            <select
                                value={data.stockist_id}
                                onChange={(e) => setData('stockist_id', e.target.value)}
                                disabled={loadingOptions}
                                className={`w-full rounded-md border shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm disabled:bg-gray-100 ${
                                    errors.stockist_id ? 'border-red-300' : 'border-gray-300'
                                }`}
                            >
                                <option value="">Selecione...</option>
                                {employees.map((emp) => (
                                    <option key={emp.id} value={emp.id}>{emp.name}</option>
                                ))}
                            </select>
                            {errors.stockist_id && <p className="mt-1 text-sm text-red-600">{errors.stockist_id}</p>}
                        </div>

                        {/* Tamanho da Amostra (apenas para aleatoria) */}
                        {data.audit_type === 'aleatoria' && (
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Tamanho da Amostra <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="number"
                                    min={10}
                                    value={data.random_sample_size}
                                    onChange={(e) => setData('random_sample_size', parseInt(e.target.value) || 10)}
                                    className={`w-full rounded-md border shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm ${
                                        errors.random_sample_size ? 'border-red-300' : 'border-gray-300'
                                    }`}
                                />
                                <p className="mt-1 text-xs text-gray-500">Minimo: 10 itens</p>
                                {errors.random_sample_size && (
                                    <p className="mt-1 text-sm text-red-600">{errors.random_sample_size}</p>
                                )}
                            </div>
                        )}

                        {/* Checkboxes */}
                        <div className="space-y-3">
                            <label className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={data.requires_second_count}
                                    onChange={(e) => setData('requires_second_count', e.target.checked)}
                                    disabled={data.audit_type === 'total'}
                                    className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-50"
                                />
                                <span className="text-sm text-gray-700">
                                    Requer segunda contagem
                                    {data.audit_type === 'total' && (
                                        <span className="text-xs text-gray-400 ml-1">(obrigatorio para auditoria total)</span>
                                    )}
                                </span>
                            </label>

                            <label className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={data.requires_third_count}
                                    onChange={(e) => setData('requires_third_count', e.target.checked)}
                                    className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <span className="text-sm text-gray-700">Requer terceira contagem</span>
                            </label>
                        </div>

                        {/* Observacoes */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Observacoes
                            </label>
                            <textarea
                                value={data.notes}
                                onChange={(e) => setData('notes', e.target.value)}
                                rows={3}
                                className="w-full rounded-md border border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                placeholder="Observacoes adicionais sobre a auditoria..."
                            />
                            {errors.notes && <p className="mt-1 text-sm text-red-600">{errors.notes}</p>}
                        </div>
                    </div>

                    {/* Footer */}
                    <div className="flex justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 flex-shrink-0">
                        <Button type="button" variant="secondary" onClick={handleClose} disabled={processing}>
                            Cancelar
                        </Button>
                        <Button type="submit" variant="primary" disabled={processing} loading={processing}>
                            {processing ? 'Criando...' : 'Criar Auditoria'}
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>
    );
}
