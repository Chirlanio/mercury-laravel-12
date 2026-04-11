import { useState } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import StandardModal from '@/Components/StandardModal';
import FormSection from '@/Components/Shared/FormSection';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import Checkbox from '@/Components/Checkbox';
import Button from '@/Components/Button';
import { TrashIcon, MagnifyingGlassIcon, ExclamationTriangleIcon } from '@heroicons/react/24/outline';

const formatCurrency = (value) =>
    new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);

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

    const resetPreview = () => { setPreview(null); setConfirmDelete(false); };

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
        resetPreview();

        axios.post('/sales/bulk-delete/preview', buildParams())
            .then(res => { setPreview(res.data); setLoadingPreview(false); })
            .catch(() => setLoadingPreview(false));
    };

    const handleDelete = () => {
        setProcessing(true);
        router.post('/sales/bulk-delete', buildParams(), {
            preserveScroll: true,
            onSuccess: () => {
                setProcessing(false);
                resetPreview();
                onSuccess();
            },
            onError: () => setProcessing(false),
        });
    };

    const handleClose = () => {
        resetPreview();
        setMode('month');
        onClose();
    };

    const footerContent = (
        <>
            <div className="flex-1" />
            <Button variant="outline" onClick={handleClose}>Cancelar</Button>
            {!preview ? (
                <Button
                    variant="warning"
                    onClick={handlePreview}
                    loading={loadingPreview}
                    icon={MagnifyingGlassIcon}
                >
                    Visualizar
                </Button>
            ) : preview.total_records > 0 ? (
                <Button
                    variant="danger"
                    onClick={handleDelete}
                    loading={processing}
                    disabled={!confirmDelete}
                    icon={TrashIcon}
                >
                    Excluir {preview.total_records} registros
                </Button>
            ) : null}
        </>
    );

    return (
        <StandardModal
            show={isOpen}
            onClose={handleClose}
            title="Excluir Vendas em Lote"
            headerColor="bg-red-600"
            headerIcon={<TrashIcon className="h-5 w-5" />}
            maxWidth="lg"
            footer={<StandardModal.Footer>{footerContent}</StandardModal.Footer>}
        >
            {/* Seleção de modo */}
            <FormSection title="Tipo de Seleção" cols={1}>
                <div className="flex gap-6">
                    <label className="flex items-center gap-2 cursor-pointer">
                        <input
                            type="radio"
                            value="month"
                            checked={mode === 'month'}
                            onChange={() => { setMode('month'); resetPreview(); }}
                            className="text-indigo-600 focus:ring-indigo-500"
                        />
                        <span className="text-sm font-medium text-gray-700">Por Mês</span>
                    </label>
                    <label className="flex items-center gap-2 cursor-pointer">
                        <input
                            type="radio"
                            value="range"
                            checked={mode === 'range'}
                            onChange={() => { setMode('range'); resetPreview(); }}
                            className="text-indigo-600 focus:ring-indigo-500"
                        />
                        <span className="text-sm font-medium text-gray-700">Por Período</span>
                    </label>
                </div>
            </FormSection>

            {/* Filtros do período */}
            <FormSection title="Período" cols={2}>
                {mode === 'month' ? (
                    <>
                        <div>
                            <InputLabel value="Mês" />
                            <select
                                value={month}
                                onChange={(e) => { setMonth(parseInt(e.target.value)); resetPreview(); }}
                                className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                {months.map(m => (
                                    <option key={m.value} value={m.value}>{m.label}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Ano" />
                            <select
                                value={year}
                                onChange={(e) => { setYear(parseInt(e.target.value)); resetPreview(); }}
                                className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                {years.map(y => (
                                    <option key={y} value={y}>{y}</option>
                                ))}
                            </select>
                        </div>
                    </>
                ) : (
                    <>
                        <div>
                            <InputLabel value="Data Início" />
                            <TextInput
                                type="date"
                                className="mt-1 w-full"
                                value={startDate}
                                onChange={(e) => { setStartDate(e.target.value); resetPreview(); }}
                            />
                        </div>
                        <div>
                            <InputLabel value="Data Fim" />
                            <TextInput
                                type="date"
                                className="mt-1 w-full"
                                value={endDate}
                                onChange={(e) => { setEndDate(e.target.value); resetPreview(); }}
                            />
                        </div>
                    </>
                )}

                <div className="col-span-full">
                    <InputLabel value="Loja (opcional)" />
                    <select
                        value={storeId}
                        onChange={(e) => { setStoreId(e.target.value); resetPreview(); }}
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">Todas as lojas</option>
                        {stores.map(s => (
                            <option key={s.id} value={s.id}>{s.name}</option>
                        ))}
                    </select>
                </div>
            </FormSection>

            {/* Preview */}
            {preview && (
                <div className="bg-red-50 border border-red-200 rounded-lg p-4 space-y-3">
                    <div className="flex items-center gap-2">
                        <ExclamationTriangleIcon className="h-5 w-5 text-red-500 shrink-0" />
                        <h4 className="text-sm font-semibold text-red-800">Resumo da exclusão</h4>
                    </div>
                    <div className="grid grid-cols-2 gap-2 text-sm">
                        <div className="flex justify-between bg-white/60 rounded px-3 py-1.5">
                            <span className="text-red-700">Registros</span>
                            <span className="font-bold text-red-900">{preview.total_records}</span>
                        </div>
                        <div className="flex justify-between bg-white/60 rounded px-3 py-1.5">
                            <span className="text-red-700">Valor total</span>
                            <span className="font-bold text-red-900">{formatCurrency(preview.total_value)}</span>
                        </div>
                        <div className="flex justify-between bg-white/60 rounded px-3 py-1.5">
                            <span className="text-red-700">Lojas afetadas</span>
                            <span className="font-bold text-red-900">{preview.affected_stores}</span>
                        </div>
                        <div className="flex justify-between bg-white/60 rounded px-3 py-1.5">
                            <span className="text-red-700">Funcionários</span>
                            <span className="font-bold text-red-900">{preview.affected_employees}</span>
                        </div>
                    </div>

                    {preview.total_records > 0 && (
                        <label className="flex items-center gap-2 pt-1 cursor-pointer">
                            <Checkbox
                                checked={confirmDelete}
                                onChange={(e) => setConfirmDelete(e.target.checked)}
                                className="text-red-600 focus:ring-red-500"
                            />
                            <span className="text-sm text-red-700 font-medium">
                                Confirmo que desejo excluir {preview.total_records} registro(s)
                            </span>
                        </label>
                    )}
                </div>
            )}
        </StandardModal>
    );
}
