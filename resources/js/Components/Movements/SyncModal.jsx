import { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import Modal from '@/Components/Modal';
import Button from '@/Components/Button';

const TABS = [
    { key: 'auto', label: 'Automático' },
    { key: 'today', label: 'Hoje' },
    { key: 'month', label: 'Por Mês' },
    { key: 'range', label: 'Por Período' },
    { key: 'types', label: 'Tipos' },
];

export default function SyncModal({ isOpen, onClose, cigamAvailable, cigamUnavailableReason }) {
    const [activeTab, setActiveTab] = useState('auto');
    const [month, setMonth] = useState(new Date().getMonth() + 1);
    const [year, setYear] = useState(new Date().getFullYear());
    const [dateStart, setDateStart] = useState('');
    const [dateEnd, setDateEnd] = useState('');

    const [syncState, setSyncState] = useState('idle'); // idle | syncing | done | error
    const [result, setResult] = useState(null);
    const [elapsed, setElapsed] = useState(0);
    const timerRef = useRef(null);

    const months = [
        { value: 1, label: 'Janeiro' }, { value: 2, label: 'Fevereiro' },
        { value: 3, label: 'Março' }, { value: 4, label: 'Abril' },
        { value: 5, label: 'Maio' }, { value: 6, label: 'Junho' },
        { value: 7, label: 'Julho' }, { value: 8, label: 'Agosto' },
        { value: 9, label: 'Setembro' }, { value: 10, label: 'Outubro' },
        { value: 11, label: 'Novembro' }, { value: 12, label: 'Dezembro' },
    ];

    const thisYear = new Date().getFullYear();
    const years = Array.from({ length: 7 }, (_, i) => thisYear - i);

    useEffect(() => {
        return () => stopTimer();
    }, []);

    const startTimer = () => {
        setElapsed(0);
        const start = Date.now();
        timerRef.current = setInterval(() => {
            setElapsed(Math.floor((Date.now() - start) / 1000));
        }, 1000);
    };

    const stopTimer = () => {
        if (timerRef.current) {
            clearInterval(timerRef.current);
            timerRef.current = null;
        }
    };

    const handleSync = async (route, data = {}) => {
        setSyncState('syncing');
        setResult(null);
        startTimer();

        try {
            const response = await axios.post(route, data, { timeout: 0 });
            stopTimer();
            setResult(response.data);
            setSyncState(response.data.status === 'completed' ? 'done' : 'error');
        } catch (err) {
            stopTimer();
            setSyncState('error');
            const msg = err.response?.data?.error_details?.message
                || err.response?.data?.message
                || 'Erro ao executar sincronização.';
            setResult({ status: 'failed', error_details: { message: msg } });
        }
    };

    const handleClose = () => {
        stopTimer();
        const shouldRefresh = syncState === 'done';
        if (syncState !== 'syncing') {
            setSyncState('idle');
            setResult(null);
            setElapsed(0);
        }
        onClose(shouldRefresh);
    };

    const handleNewSync = () => {
        setSyncState('idle');
        setResult(null);
        setElapsed(0);
    };

    const isSyncing = syncState === 'syncing';
    const isFinished = syncState === 'done' || syncState === 'error';

    const fmt = (val) => new Intl.NumberFormat('pt-BR').format(val || 0);
    const fmtSeconds = (s) => {
        if (!s) return '0s';
        const min = Math.floor(s / 60);
        const sec = s % 60;
        return min > 0 ? `${min}m ${sec}s` : `${sec}s`;
    };

    return (
        <Modal show={isOpen} onClose={handleClose} maxWidth="lg">
            <div className="p-6">
                <h2 className="text-lg font-semibold text-gray-900 mb-4">Sincronizar Movimentações</h2>

                {!cigamAvailable && syncState === 'idle' && (
                    <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-md">
                        <p className="text-sm text-red-700">{cigamUnavailableReason || 'CIGAM indisponível'}</p>
                    </div>
                )}

                {/* Syncing / Result View */}
                {(isSyncing || isFinished) && (
                    <div className="space-y-4">
                        {/* Status Header */}
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                {isSyncing && (
                                    <div className="animate-spin rounded-full h-5 w-5 border-2 border-indigo-600 border-t-transparent" />
                                )}
                                {syncState === 'done' && (
                                    <svg className="h-5 w-5 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                                    </svg>
                                )}
                                {syncState === 'error' && (
                                    <svg className="h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                                    </svg>
                                )}
                                <span className={`text-sm font-medium ${
                                    isSyncing ? 'text-blue-600' :
                                    syncState === 'done' ? 'text-emerald-600' : 'text-red-600'
                                }`}>
                                    {isSyncing ? 'Sincronizando...' :
                                     syncState === 'done' ? 'Concluído' : 'Falha'}
                                </span>
                            </div>
                            <span className="text-xs text-gray-500">
                                {fmtSeconds(isFinished && result?.elapsed_seconds ? result.elapsed_seconds : elapsed)}
                            </span>
                        </div>

                        {/* Progress Bar */}
                        <div className="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                            {isSyncing ? (
                                <div className="h-3 rounded-full bg-indigo-600 animate-progress-indeterminate" />
                            ) : (
                                <div
                                    className={`h-3 rounded-full transition-all duration-500 w-full ${
                                        syncState === 'error' ? 'bg-red-500' : 'bg-emerald-500'
                                    }`}
                                />
                            )}
                        </div>

                        {/* Syncing hint */}
                        {isSyncing && (
                            <p className="text-sm text-gray-500 text-center">
                                Importando dados do CIGAM. Isso pode levar alguns minutos...
                            </p>
                        )}

                        {/* Date Range */}
                        {result?.date_range_start && (
                            <p className="text-xs text-gray-500">
                                Período: {result.date_range_start} a {result.date_range_end}
                            </p>
                        )}

                        {/* Stats Grid */}
                        {isFinished && result && result.total_records > 0 && (
                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <StatItem label="Total" value={fmt(result.total_records)} />
                                <StatItem label="Processados" value={fmt(result.processed_records)} />
                                <StatItem label="Inseridos" value={fmt(result.inserted_records)} color="text-emerald-600" />
                                <StatItem label="Removidos" value={fmt(result.deleted_records)} color="text-amber-600" />
                                {result.skipped_records > 0 && (
                                    <StatItem label="Ignorados" value={fmt(result.skipped_records)} color="text-gray-500" />
                                )}
                                {result.error_count > 0 && (
                                    <StatItem label="Erros" value={fmt(result.error_count)} color="text-red-600" />
                                )}
                            </div>
                        )}

                        {/* Error Message */}
                        {syncState === 'error' && result?.error_details?.message && (
                            <div className="p-3 bg-red-50 border border-red-200 rounded-md">
                                <p className="text-sm text-red-700">{result.error_details.message}</p>
                            </div>
                        )}

                        {/* Completed Info */}
                        {syncState === 'done' && result?.completed_at && (
                            <p className="text-xs text-gray-500">
                                Finalizado em: {result.completed_at}
                            </p>
                        )}

                        {/* Actions */}
                        <div className="flex justify-end gap-2 pt-2">
                            {isFinished && (
                                <Button variant="primary" size="sm" onClick={handleNewSync}>
                                    Nova Sincronização
                                </Button>
                            )}
                            <Button
                                variant="secondary"
                                size="sm"
                                onClick={handleClose}
                                disabled={isSyncing}
                            >
                                {isSyncing ? 'Aguarde...' : 'Fechar'}
                            </Button>
                        </div>
                    </div>
                )}

                {/* Tab Selection (only when idle) */}
                {syncState === 'idle' && (
                    <>
                        <div className="flex border-b mb-4 overflow-x-auto">
                            {TABS.map(tab => (
                                <button
                                    key={tab.key}
                                    onClick={() => setActiveTab(tab.key)}
                                    className={`px-4 py-2 text-sm font-medium whitespace-nowrap border-b-2 transition-colors ${
                                        activeTab === tab.key
                                            ? 'border-indigo-500 text-indigo-600'
                                            : 'border-transparent text-gray-500 hover:text-gray-700'
                                    }`}
                                >
                                    {tab.label}
                                </button>
                            ))}
                        </div>

                        <div className="min-h-[120px]">
                            {activeTab === 'auto' && (
                                <div>
                                    <p className="text-sm text-gray-600 mb-4">
                                        Sincroniza automaticamente desde a última data registrada até ontem. Registros existentes são substituídos.
                                    </p>
                                    <Button variant="primary" onClick={() => handleSync('/movements/sync/auto')} disabled={!cigamAvailable}>
                                        Sincronizar
                                    </Button>
                                </div>
                            )}

                            {activeTab === 'today' && (
                                <div>
                                    <p className="text-sm text-gray-600 mb-4">
                                        Busca apenas novos registros de hoje (incremental). Não remove dados existentes.
                                    </p>
                                    <Button variant="primary" onClick={() => handleSync('/movements/sync/today')} disabled={!cigamAvailable}>
                                        Sincronizar Hoje
                                    </Button>
                                </div>
                            )}

                            {activeTab === 'month' && (
                                <div>
                                    <p className="text-sm text-gray-600 mb-4">
                                        Re-sincroniza todos os registros do mês selecionado.
                                    </p>
                                    <div className="flex gap-3 mb-4">
                                        <select value={month} onChange={(e) => setMonth(parseInt(e.target.value))}
                                            className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            {months.map(m => <option key={m.value} value={m.value}>{m.label}</option>)}
                                        </select>
                                        <select value={year} onChange={(e) => setYear(parseInt(e.target.value))}
                                            className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            {years.map(y => <option key={y} value={y}>{y}</option>)}
                                        </select>
                                    </div>
                                    <Button variant="primary" onClick={() => handleSync('/movements/sync/month', { month, year })} disabled={!cigamAvailable}>
                                        Sincronizar Mês
                                    </Button>
                                </div>
                            )}

                            {activeTab === 'range' && (
                                <div>
                                    <p className="text-sm text-gray-600 mb-4">
                                        Re-sincroniza um período específico (máximo 180 dias).
                                    </p>
                                    <div className="flex gap-3 mb-4">
                                        <div>
                                            <label className="block text-xs text-gray-500 mb-1">Data Início</label>
                                            <input type="date" value={dateStart} onChange={(e) => setDateStart(e.target.value)}
                                                className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                        <div>
                                            <label className="block text-xs text-gray-500 mb-1">Data Fim</label>
                                            <input type="date" value={dateEnd} onChange={(e) => setDateEnd(e.target.value)}
                                                className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                                        </div>
                                    </div>
                                    <Button variant="primary"
                                        onClick={() => handleSync('/movements/sync/range', { date_start: dateStart, date_end: dateEnd })}
                                        disabled={!cigamAvailable || !dateStart || !dateEnd}>
                                        Sincronizar Período
                                    </Button>
                                </div>
                            )}

                            {activeTab === 'types' && (
                                <div>
                                    <p className="text-sm text-gray-600 mb-4">
                                        Atualiza a tabela de tipos de movimentação a partir do CIGAM.
                                    </p>
                                    <Button variant="primary" onClick={() => handleSync('/movements/sync/types')} disabled={!cigamAvailable}>
                                        Sincronizar Tipos
                                    </Button>
                                </div>
                            )}
                        </div>

                        <div className="mt-6 flex justify-end">
                            <Button variant="secondary" onClick={handleClose}>Fechar</Button>
                        </div>
                    </>
                )}
            </div>
        </Modal>
    );
}

function StatItem({ label, value, color = 'text-gray-900' }) {
    return (
        <div className="bg-gray-50 rounded-lg p-3 text-center">
            <p className="text-xs text-gray-500">{label}</p>
            <p className={`text-lg font-semibold ${color}`}>{value}</p>
        </div>
    );
}
