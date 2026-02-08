import { useState } from 'react';
import { router } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import Button from '@/Components/Button';

export default function SaleSyncModal({ isOpen, onClose, stores = [], cigamAvailable = false }) {
    const [mode, setMode] = useState('auto');
    const [month, setMonth] = useState(new Date().getMonth() + 1);
    const [year, setYear] = useState(new Date().getFullYear());
    const [startDate, setStartDate] = useState('');
    const [endDate, setEndDate] = useState('');
    const [storeId, setStoreId] = useState('');
    const [processing, setProcessing] = useState(false);
    const [result, setResult] = useState(null);

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

    const handleSync = () => {
        setProcessing(true);
        setResult(null);

        let url, data;
        if (mode === 'auto') {
            url = '/sales/sync/auto';
            data = {};
        } else if (mode === 'month') {
            url = '/sales/sync/month';
            data = { month, year, store_id: storeId || null };
        } else {
            url = '/sales/sync/range';
            data = { start_date: startDate, end_date: endDate, store_id: storeId || null };
        }

        router.post(url, data, {
            preserveScroll: true,
            onSuccess: (page) => {
                setProcessing(false);
                const flash = page.props?.flash;
                setResult({
                    type: flash?.error ? 'error' : 'success',
                    message: flash?.success || flash?.error || flash?.info || 'Sincronização concluída.',
                });
            },
            onError: () => {
                setProcessing(false);
                setResult({ type: 'error', message: 'Erro ao sincronizar.' });
            },
        });
    };

    const handleClose = () => {
        setResult(null);
        setMode('auto');
        onClose();
    };

    if (!cigamAvailable) {
        return (
            <Modal show={isOpen} onClose={handleClose} title="Sincronizar CIGAM" maxWidth="lg">
                <div className="p-6">
                    <div className="p-4 bg-yellow-50 border border-yellow-200 rounded-md">
                        <div className="flex">
                            <svg className="w-5 h-5 text-yellow-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                            </svg>
                            <div>
                                <h3 className="text-sm font-medium text-yellow-800">Conexão CIGAM não disponível</h3>
                                <p className="mt-1 text-sm text-yellow-700">
                                    A conexão com o banco de dados CIGAM (PostgreSQL) não está configurada ou não está acessível.
                                    Verifique as variáveis de ambiente CIGAM_DB_* no arquivo .env.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div className="flex justify-end mt-4">
                        <Button variant="secondary" onClick={handleClose}>Fechar</Button>
                    </div>
                </div>
            </Modal>
        );
    }

    return (
        <Modal show={isOpen} onClose={handleClose} title="Sincronizar CIGAM" maxWidth="lg">
            <div className="p-6">
                {/* Mode tabs */}
                <div className="flex border-b border-gray-200 mb-4">
                    {[
                        { key: 'auto', label: 'Automático' },
                        { key: 'month', label: 'Por Mês' },
                        { key: 'range', label: 'Por Período' },
                    ].map(tab => (
                        <button
                            key={tab.key}
                            type="button"
                            className={`px-4 py-2 text-sm font-medium border-b-2 -mb-px ${
                                mode === tab.key
                                    ? 'border-indigo-500 text-indigo-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700'
                            }`}
                            onClick={() => { setMode(tab.key); setResult(null); }}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>

                {/* Auto mode */}
                {mode === 'auto' && (
                    <div className="space-y-3">
                        <p className="text-sm text-gray-600">
                            Sincroniza automaticamente desde a última data registrada até ontem.
                        </p>
                    </div>
                )}

                {/* Month mode */}
                {mode === 'month' && (
                    <div className="space-y-3">
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Mês</label>
                                <select
                                    value={month}
                                    onChange={(e) => setMonth(parseInt(e.target.value))}
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
                                    onChange={(e) => setYear(parseInt(e.target.value))}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    {years.map(y => (
                                        <option key={y} value={y}>{y}</option>
                                    ))}
                                </select>
                            </div>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Loja (opcional)</label>
                            <select
                                value={storeId}
                                onChange={(e) => setStoreId(e.target.value)}
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">Todas as lojas</option>
                                {stores.map(s => (
                                    <option key={s.id} value={s.id}>{s.name}</option>
                                ))}
                            </select>
                        </div>
                    </div>
                )}

                {/* Range mode */}
                {mode === 'range' && (
                    <div className="space-y-3">
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Data Início</label>
                                <input
                                    type="date"
                                    value={startDate}
                                    onChange={(e) => setStartDate(e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Data Fim</label>
                                <input
                                    type="date"
                                    value={endDate}
                                    onChange={(e) => setEndDate(e.target.value)}
                                    className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                />
                            </div>
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Loja (opcional)</label>
                            <select
                                value={storeId}
                                onChange={(e) => setStoreId(e.target.value)}
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">Todas as lojas</option>
                                {stores.map(s => (
                                    <option key={s.id} value={s.id}>{s.name}</option>
                                ))}
                            </select>
                        </div>
                    </div>
                )}

                {/* Result */}
                {result && (
                    <div className={`mt-4 p-3 rounded-md ${
                        result.type === 'success'
                            ? 'bg-green-50 border border-green-200'
                            : 'bg-red-50 border border-red-200'
                    }`}>
                        <p className={`text-sm ${
                            result.type === 'success' ? 'text-green-700' : 'text-red-700'
                        }`}>{result.message}</p>
                    </div>
                )}

                {/* Progress */}
                {processing && (
                    <div className="mt-4 flex items-center gap-2">
                        <svg className="animate-spin w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span className="text-sm text-gray-600">Sincronizando...</span>
                    </div>
                )}

                <div className="flex justify-end gap-3 mt-6 pt-4 border-t">
                    <Button variant="secondary" onClick={handleClose}>
                        Fechar
                    </Button>
                    <Button
                        variant="primary"
                        onClick={handleSync}
                        loading={processing}
                        disabled={processing}
                    >
                        Sincronizar
                    </Button>
                </div>
            </div>
        </Modal>
    );
}
