import { useState, useEffect } from 'react';
import axios from 'axios';
import StandardModal from '@/Components/StandardModal';
import Button from '@/Components/Button';
import StatusBadge from '@/Components/Shared/StatusBadge';
import { CheckBadgeIcon, ClipboardDocumentCheckIcon, TrashIcon } from '@heroicons/react/24/outline';

const STATUS_VARIANT = {
    1: 'warning', 2: 'emerald', 3: 'gray', 4: 'info', 5: 'purple',
};

export default function ConfirmSalesModal({ show, onClose, onSuccess, storeGoalId }) {
    const [data, setData] = useState(null);
    const [consultants, setConsultants] = useState([]);
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (show && storeGoalId) {
            setLoading(true);
            axios.get(`/store-goals/${storeGoalId}/confirm-data`)
                .then(res => {
                    setData(res.data);
                    setConsultants(res.data.consultants.map(c => ({
                        ...c,
                        edit_value: c.confirmed_sales !== null ? formatBRL(c.confirmed_sales) : '',
                    })));
                })
                .catch(() => setData(null))
                .finally(() => setLoading(false));
        } else { setData(null); setConsultants([]); }
    }, [show, storeGoalId]);

    const formatBRL = (val) => {
        if (val === null || val === undefined || val === '') return '';
        return new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val);
    };

    const parseBRL = (str) => {
        if (!str || str.trim() === '') return null;
        const num = parseFloat(str.replace(/\./g, '').replace(',', '.'));
        return isNaN(num) ? null : num;
    };

    const fmt = (val) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val || 0);

    const handleValueChange = (index, rawValue) => {
        setConsultants(prev => prev.map((c, i) => i === index ? { ...c, edit_value: rawValue } : c));
    };

    const copyAllFromSystem = () => {
        setConsultants(prev => prev.map(c => ({
            ...c, edit_value: c.system_sales > 0 ? formatBRL(c.system_sales) : '',
        })));
    };

    const clearAll = () => {
        setConsultants(prev => prev.map(c => ({ ...c, edit_value: '' })));
    };

    const handleSubmit = async () => {
        setSaving(true);
        try {
            const sales = consultants.map(c => ({
                employee_id: c.employee_id, sale_value: parseBRL(c.edit_value),
            }));
            const res = await axios.post(`/store-goals/${storeGoalId}/confirm-sales`, { sales });
            onSuccess?.(res.data.message);
        } catch (err) {
            alert(err.response?.data?.message || 'Erro ao confirmar vendas.');
        } finally { setSaving(false); }
    };

    const totalSystem = consultants.reduce((sum, c) => sum + (c.system_sales || 0), 0);
    const totalConfirmed = consultants.reduce((sum, c) => sum + (parseBRL(c.edit_value) || 0), 0);
    const hasChanges = consultants.some(c => c.edit_value !== '' || c.confirmed_sales !== null);

    const footerContent = data && (
        <>
            <Button variant="outline" size="sm" icon={ClipboardDocumentCheckIcon} onClick={copyAllFromSystem}>
                Copiar do Sistema
            </Button>
            <Button variant="outline" size="sm" icon={TrashIcon} onClick={clearAll}>
                Limpar Tudo
            </Button>
            <div className="flex-1" />
            <Button variant="outline" onClick={onClose}>Cancelar</Button>
            <Button variant="primary" onClick={handleSubmit} disabled={saving || !hasChanges}
                loading={saving} icon={CheckBadgeIcon}>
                Confirmar Vendas
            </Button>
        </>
    );

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title="Confirmar Vendas"
            subtitle={data ? `${data.store_name} - ${data.period_label}` : undefined}
            headerColor="bg-emerald-600"
            headerIcon={<CheckBadgeIcon className="h-5 w-5" />}
            loading={loading}
            errorMessage={!loading && !data && show ? 'Erro ao carregar dados.' : null}
            maxWidth="5xl"
            footer={footerContent && <StandardModal.Footer>{footerContent}</StandardModal.Footer>}
        >
            {data && (
                <>
                    <div className="overflow-x-auto border rounded-lg max-h-[500px] overflow-y-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50 sticky top-0 z-10">
                                <tr>
                                    <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Consultora</th>
                                    <th className="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase">Situação</th>
                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Nível</th>
                                    <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Vendas Sistema</th>
                                    <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase w-48">Valor Confirmado (R$)</th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {consultants.map((c, index) => {
                                    const parsed = parseBRL(c.edit_value);
                                    const isDiff = parsed !== null && Math.abs(parsed - c.system_sales) > 0.01;
                                    return (
                                        <tr key={c.employee_id} className={isDiff ? 'bg-amber-50' : 'hover:bg-gray-50'}>
                                            <td className="px-4 py-2 text-sm text-gray-900">{c.employee_name}</td>
                                            <td className="px-3 py-2 text-center">
                                                <StatusBadge variant={STATUS_VARIANT[c.status_id] || 'gray'} size="sm">
                                                    {c.status_name}
                                                </StatusBadge>
                                            </td>
                                            <td className="px-3 py-2 text-xs text-gray-500">{c.level}</td>
                                            <td className="px-3 py-2 text-sm text-gray-700 text-right font-mono">{fmt(c.system_sales)}</td>
                                            <td className="px-3 py-1 text-right">
                                                <input type="text" value={c.edit_value}
                                                    onChange={(e) => handleValueChange(index, e.target.value)}
                                                    placeholder="0,00"
                                                    className="w-full text-right text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 py-1 px-2 font-mono"
                                                />
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                            <tfoot className="bg-gray-100 font-semibold sticky bottom-0">
                                <tr>
                                    <td className="px-4 py-2 text-sm" colSpan={3}>Total</td>
                                    <td className="px-3 py-2 text-sm text-right font-mono">{fmt(totalSystem)}</td>
                                    <td className="px-3 py-2 text-sm text-right font-mono">{fmt(totalConfirmed)}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    {totalConfirmed > 0 && Math.abs(totalConfirmed - totalSystem) > 0.01 && (
                        <div className="text-xs text-amber-600">
                            Diferença: {fmt(totalConfirmed - totalSystem)} em relação ao sistema
                        </div>
                    )}
                </>
            )}
        </StandardModal>
    );
}
