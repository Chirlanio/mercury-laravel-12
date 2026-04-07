import { useState, useEffect } from 'react';
import Modal from '@/Components/Modal';
import Button from '@/Components/Button';
import axios from 'axios';

export default function ConfirmSalesModal({ isOpen, onClose, onSuccess, storeGoalId }) {
    const [data, setData] = useState(null);
    const [consultants, setConsultants] = useState([]);
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (isOpen && storeGoalId) {
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
        } else {
            setData(null);
            setConsultants([]);
        }
    }, [isOpen, storeGoalId]);

    const formatBRL = (val) => {
        if (val === null || val === undefined || val === '') return '';
        return new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val);
    };

    const parseBRL = (str) => {
        if (!str || str.trim() === '') return null;
        const cleaned = str.replace(/\./g, '').replace(',', '.');
        const num = parseFloat(cleaned);
        return isNaN(num) ? null : num;
    };

    const handleValueChange = (index, rawValue) => {
        setConsultants(prev => prev.map((c, i) =>
            i === index ? { ...c, edit_value: rawValue } : c
        ));
    };

    const copyAllFromSystem = () => {
        setConsultants(prev => prev.map(c => ({
            ...c,
            edit_value: c.system_sales > 0 ? formatBRL(c.system_sales) : '',
        })));
    };

    const clearAll = () => {
        setConsultants(prev => prev.map(c => ({ ...c, edit_value: '' })));
    };

    const handleSubmit = async () => {
        setSaving(true);
        try {
            const sales = consultants.map(c => ({
                employee_id: c.employee_id,
                sale_value: parseBRL(c.edit_value),
            }));

            const res = await axios.post(`/store-goals/${storeGoalId}/confirm-sales`, { sales });
            onSuccess?.(res.data.message);
            onClose();
        } catch (err) {
            alert(err.response?.data?.message || 'Erro ao confirmar vendas.');
        } finally {
            setSaving(false);
        }
    };

    const fmt = (val) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(val || 0);

    const totalSystem = consultants.reduce((sum, c) => sum + (c.system_sales || 0), 0);
    const totalConfirmed = consultants.reduce((sum, c) => {
        const v = parseBRL(c.edit_value);
        return sum + (v || 0);
    }, 0);
    const hasChanges = consultants.some(c => c.edit_value !== '' || c.confirmed_sales !== null);

    return (
        <Modal show={isOpen} onClose={onClose} maxWidth="5xl">
            <div className="p-6">
                <h2 className="text-lg font-semibold text-gray-900 mb-1">Confirmar Vendas</h2>
                {data && (
                    <p className="text-sm text-gray-500 mb-4">{data.store_name} - {data.period_label}</p>
                )}

                {loading ? (
                    <div className="flex items-center justify-center py-12">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                    </div>
                ) : data ? (
                    <>
                        {/* Actions */}
                        <div className="flex gap-2 mb-4">
                            <Button variant="outline" size="sm" onClick={copyAllFromSystem}>
                                Copiar do Sistema
                            </Button>
                            <Button variant="outline" size="sm" onClick={clearAll}>
                                Limpar Tudo
                            </Button>
                        </div>

                        {/* Table */}
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
                                                <td className="px-4 py-2 text-sm text-gray-900">
                                                    {c.employee_name}
                                                </td>
                                                <td className="px-3 py-2 text-center">
                                                    <StatusBadge statusId={c.status_id} statusName={c.status_name} />
                                                </td>
                                                <td className="px-3 py-2 text-xs text-gray-500">{c.level}</td>
                                                <td className="px-3 py-2 text-sm text-gray-700 text-right font-mono">
                                                    {fmt(c.system_sales)}
                                                </td>
                                                <td className="px-3 py-1 text-right">
                                                    <input
                                                        type="text"
                                                        value={c.edit_value}
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

                        {/* Diff indicator */}
                        {totalConfirmed > 0 && Math.abs(totalConfirmed - totalSystem) > 0.01 && (
                            <div className="mt-2 text-xs text-amber-600">
                                Diferença: {fmt(totalConfirmed - totalSystem)} em relação ao sistema
                            </div>
                        )}

                        {/* Footer */}
                        <div className="mt-6 flex justify-end gap-3">
                            <Button variant="secondary" onClick={onClose}>Cancelar</Button>
                            <Button
                                variant="primary"
                                onClick={handleSubmit}
                                disabled={saving || !hasChanges}
                            >
                                {saving ? 'Salvando...' : 'Confirmar Vendas'}
                            </Button>
                        </div>
                    </>
                ) : (
                    <p className="text-center py-8 text-gray-500">Erro ao carregar dados.</p>
                )}
            </div>
        </Modal>
    );
}

const STATUS_COLORS = {
    1: 'bg-yellow-100 text-yellow-800', // Pendente
    2: 'bg-emerald-100 text-emerald-800', // Ativo
    3: 'bg-gray-100 text-gray-600', // Inativo
    4: 'bg-blue-100 text-blue-800', // Férias
    5: 'bg-purple-100 text-purple-800', // Licença
};

function StatusBadge({ statusId, statusName }) {
    const color = STATUS_COLORS[statusId] || 'bg-gray-100 text-gray-600';
    return (
        <span className={`inline-flex px-2 py-0.5 rounded-full text-[10px] font-medium ${color}`}>
            {statusName}
        </span>
    );
}
