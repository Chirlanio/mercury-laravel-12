import { useState } from 'react';
import { router } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import Button from '@/Components/Button';
import axios from 'axios';

const formatCurrency = (value) => {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(value);
};

export default function SaleBulkDeleteModal({ isOpen, onClose, onSuccess, stores = [] }) {
    const [mode, setMode] = useState('month');
    const [month, setMonth] = useState(new Date().getMonth() + 1);
    const [year, setYear] = useState(new Date().getFullYear());
    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState('');
    const [storeId, setStoreId] = useState('');
    const [preview, setPreview] = useState(null);
    const [loadingPreview, setLoadingPreview] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [processing, setProcessing] = useState(false);

    const months = [
        { value: 1, label: 'Janeiro' }, { value: 2, label: 'Fevereiro' },
        { value: 3, label: 'Março' }, { value: 4, label: 'Abril' },
        { value: 5, label: 'Maio' }, { value: 6, label: 'Junho' },
        { value: 7, label: 'Julho' }, { value: 8, label: 'Agosto' },
        { value: 9, label: 'Setembro' }, { value: 10, label: 'Outubro' },
        { value: 11, label: 'Novembro' }, { value: 12, label: 'Dezembro' },
    ];

    const currentYear = new Date().getFullYear();
    const years = Array.from({ length: currentYear - 2019 }, (_, i) => currentYear - i);

    const buildParams = () => {
        const params = { mode, store_id: storeId || null };
        if (mode === 'month') {
            params.month = month;
            params.year = year;
        } else {
            params.start_date = startDate;
            params.end_date = endDate;
        }
        return params;
    };

    const handlePreview = () => {
        setLoadingPreview(true);
        setPreview(null);
        setConfirmDelete(false);

        axios.post('/sales/bulk-delete/preview', buildParams())
            .then(res => {
                setPreview(res.data);
                setLoadingPreview(false);
            })
            .catch(() => {
                setLoadingPreview(false);
            });
    };

    const handleDelete = () => {
        setProcessing(true);
        router.post('/sales/bulk-delete', buildParams(), {
            preserveScroll: true,
            onSuccess: () => {
                setProcessing(false);
                setPreview(null);
                setConfirmDelete(false);
                onSuccess();
            },
            onError: () => {
                setProcessing(false);
            },
        });
    };

    const handleClose = () => {
        setPreview(null);
        setConfirmDelete(false);
        setMode('month');
        onClose();
    };

    return (
        <Modal show={isOpen} onClose={handleClose} title="Excluir Vendas em Lote" maxWidth="lg">
            <div className="p-6">
                {/* Mode selector */}
                <div className="flex gap-4 mb-4">
                    <label className="flex items-center">
                        <input
                            type="radio"
                            value="month"
                            checked={mode === 'month'}
                            onChange={() => { setMode('month'); setPreview(null); setConfirmDelete(false); }}
                            className="mr-2"
                        />
                        <span className="text-sm">Por Mês</span>
                    </label>
                    <label className="flex items-center">
                        <input
                            type="radio"
                            value="range"
                            checked={mode === 'range'}
                            onChange={() => { setMode('range'); setPreview(null); setConfirmDelete(false); }}
                            className="mr-2"
                        />
                        <span className="text-sm">Por Período</span>
                    </label>
                </div>

                {mode === 'month' ? (
                    <div className="grid grid-cols-2 gap-3 mb-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Mês</label>
                            <select
                                value={month}
                                onChange={(e) => { setMonth(parseInt(e.target.value)); setPreview(null); setConfirmDelete(false); }}
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                {months.map(m => (
                                    <option key={m.value} value={m.value}>{m.label}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Ano</label>
                            <select
                                value={year}
                                onChange={(e) => { setYear(parseInt(e.target.value)); setPreview(null); setConfirmDelete(false); }}
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                {years.map(y => (
                                    <option key={y} value={y}>{y}</option>
                                ))}
                            </select>
                        </div>
                    </div>
                ) : (
                    <div className="grid grid-cols-2 gap-3 mb-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Data Início</label>
                            <input
                                type="date"
                                value={startDate}
                                onChange={(e) => { setStartDate(e.target.value); setPreview(null); setConfirmDelete(false); }}
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Data Fim</label>
                            <input
                                type="date"
                                value={endDate}
                                onChange={(e) => { setEndDate(e.target.value); setPreview(null); setConfirmDelete(false); }}
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                        </div>
                    </div>
                )}

                <div className="mb-4">
                    <label className="block text-sm font-medium text-gray-700 mb-1">Loja (opcional)</label>
                    <select
                        value={storeId}
                        onChange={(e) => { setStoreId(e.target.value); setPreview(null); setConfirmDelete(false); }}
                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">Todas as lojas</option>
                        {stores.map(s => (
                            <option key={s.id} value={s.id}>{s.name}</option>
                        ))}
                    </select>
                </div>

                {/* Preview result */}
                {preview && (
                    <div className="p-4 bg-red-50 border border-red-200 rounded-md mb-4">
                        <h4 className="text-sm font-semibold text-red-800 mb-2">Resumo da exclusão:</h4>
                        <div className="grid grid-cols-2 gap-2 text-sm text-red-700">
                            <div>Registros: <strong>{preview.total_records}</strong></div>
                            <div>Valor total: <strong>{formatCurrency(preview.total_value)}</strong></div>
                            <div>Lojas afetadas: <strong>{preview.affected_stores}</strong></div>
                            <div>Funcionários: <strong>{preview.affected_employees}</strong></div>
                        </div>

                        {preview.total_records > 0 && (
                            <div className="mt-3">
                                <label className="flex items-center">
                                    <input
                                        type="checkbox"
                                        checked={confirmDelete}
                                        onChange={(e) => setConfirmDelete(e.target.checked)}
                                        className="rounded border-gray-300 text-red-600 focus:ring-red-500 mr-2"
                                    />
                                    <span className="text-sm text-red-700 font-medium">
                                        Confirmo que desejo excluir {preview.total_records} registro(s)
                                    </span>
                                </label>
                            </div>
                        )}
                    </div>
                )}

                <div className="flex justify-end gap-3 pt-4 border-t">
                    <Button variant="secondary" onClick={handleClose}>
                        Cancelar
                    </Button>
                    {!preview ? (
                        <Button
                            variant="warning"
                            onClick={handlePreview}
                            loading={loadingPreview}
                        >
                            Visualizar
                        </Button>
                    ) : preview.total_records > 0 ? (
                        <Button
                            variant="danger"
                            onClick={handleDelete}
                            loading={processing}
                            disabled={!confirmDelete}
                        >
                            Excluir {preview.total_records} registros
                        </Button>
                    ) : null}
                </div>
            </div>
        </Modal>
    );
}
