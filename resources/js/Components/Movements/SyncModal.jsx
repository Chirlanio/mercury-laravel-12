import { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import StandardModal from '@/Components/StandardModal';
import Button from '@/Components/Button';
import StatusBadge from '@/Components/Shared/StatusBadge';
import { ArrowPathIcon, CheckCircleIcon, XCircleIcon } from '@heroicons/react/24/outline';

const TABS = [
    { key: 'auto', label: 'Automático' },
    { key: 'today', label: 'Hoje' },
    { key: 'month', label: 'Por Mês' },
    { key: 'range', label: 'Por Período' },
    { key: 'types', label: 'Tipos' },
];

export default function SyncModal({ show, onClose, cigamAvailable, cigamUnavailableReason }) {
    const [activeTab, setActiveTab] = useState('auto');
    const [month, setMonth] = useState(new Date().getMonth() + 1);
    const [year, setYear] = useState(new Date().getFullYear());
    const [dateStart, setDateStart] = useState('');
    const [dateEnd, setDateEnd] = useState('');
    const [syncState, setSyncState] = useState('idle');
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

    useEffect(() => () => stopTimer(), []);

    const startTimer = () => {
        setElapsed(0);
        const start = Date.now();
        timerRef.current = setInterval(() => setElapsed(Math.floor((Date.now() - start) / 1000)), 1000);
    };
    const stopTimer = () => { if (timerRef.current) { clearInterval(timerRef.current); timerRef.current = null; } };

    const handleSync = async (route, data = {}) => {
        setSyncState('syncing'); setResult(null); startTimer();
        try {
            const response = await axios.post(route, data, { timeout: 0 });
            stopTimer(); setResult(response.data);
            setSyncState(response.data.status === 'completed' ? 'done' : 'error');
        } catch (err) {
            stopTimer(); setSyncState('error');
            const msg = err.response?.data?.error_details?.message || err.response?.data?.message || 'Erro ao executar sincronização.';
            setResult({ status: 'failed', error_details: { message: msg } });
        }
    };

    const handleClose = () => {
        stopTimer();
        const shouldRefresh = syncState === 'done';
        if (syncState !== 'syncing') { setSyncState('idle'); setResult(null); setElapsed(0); }
        onClose(shouldRefresh);
    };

    const handleNewSync = () => { setSyncState('idle'); setResult(null); setElapsed(0); };

    const isSyncing = syncState === 'syncing';
    const isFinished = syncState === 'done' || syncState === 'error';
    const fmt = (val) => new Intl.NumberFormat('pt-BR').format(val || 0);
    const fmtSeconds = (s) => {
        if (!s) return '0s';
        const min = Math.floor(s / 60);
        return min > 0 ? `${min}m ${s % 60}s` : `${s}s`;
    };

    const headerColor = isSyncing ? 'bg-blue-600' : syncState === 'done' ? 'bg-green-600' : syncState === 'error' ? 'bg-red-600' : 'bg-indigo-600';

    const footerContent = (
        <>
            {isFinished && <Button variant="primary" size="sm" icon={ArrowPathIcon} onClick={handleNewSync}>Nova Sincronização</Button>}
            <div className="flex-1" />
            <Button variant="outline" onClick={handleClose} disabled={isSyncing}>
                {isSyncing ? 'Aguarde...' : 'Fechar'}
            </Button>
        </>
    );

    return (
        <StandardModal
            show={show}
            onClose={handleClose}
            title="Sincronizar Movimentações"
            headerColor={headerColor}
            headerIcon={<ArrowPathIcon className={`h-5 w-5 ${isSyncing ? 'animate-spin' : ''}`} />}
            maxWidth="lg"
            footer={<StandardModal.Footer>{footerContent}</StandardModal.Footer>}
        >
            {!cigamAvailable && syncState === 'idle' && (
                <div className="p-3 bg-red-50 border border-red-200 rounded-md">
                    <p className="text-sm text-red-700">{cigamUnavailableReason || 'CIGAM indisponível'}</p>
                </div>
            )}

            {/* Sincronizando / Resultado */}
            {(isSyncing || isFinished) && (
                <div className="space-y-4">
                    <div className="flex items-center justify-between">
                        <StatusBadge
                            variant={isSyncing ? 'info' : syncState === 'done' ? 'success' : 'danger'}
                            size="lg"
                            icon={isSyncing ? undefined : syncState === 'done' ? CheckCircleIcon : XCircleIcon}
                        >
                            {isSyncing ? 'Sincronizando...' : syncState === 'done' ? 'Concluído' : 'Falha'}
                        </StatusBadge>
                        <span className="text-xs text-gray-500">
                            {fmtSeconds(isFinished && result?.elapsed_seconds ? result.elapsed_seconds : elapsed)}
                        </span>
                    </div>

                    <div className="w-full bg-gray-200 rounded-full h-3 overflow-hidden">
                        {isSyncing ? (
                            <div className="h-3 rounded-full bg-indigo-600 animate-progress-indeterminate" />
                        ) : (
                            <div className={`h-3 rounded-full transition-all duration-500 w-full ${syncState === 'error' ? 'bg-red-500' : 'bg-emerald-500'}`} />
                        )}
                    </div>

                    {isSyncing && <p className="text-sm text-gray-500 text-center">Importando dados do CIGAM. Isso pode levar alguns minutos...</p>}

                    {result?.date_range_start && (
                        <p className="text-xs text-gray-500">Período: {result.date_range_start} a {result.date_range_end}</p>
                    )}

                    {isFinished && result?.total_records > 0 && (
                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <StandardModal.MiniField label="Total" value={fmt(result.total_records)} />
                            <StandardModal.MiniField label="Processados" value={fmt(result.processed_records)} />
                            <StandardModal.MiniField label="Inseridos" value={fmt(result.inserted_records)} />
                            <StandardModal.MiniField label="Removidos" value={fmt(result.deleted_records)} />
                            {result.skipped_records > 0 && <StandardModal.MiniField label="Ignorados" value={fmt(result.skipped_records)} />}
                            {result.error_count > 0 && <StandardModal.MiniField label="Erros" value={fmt(result.error_count)} />}
                        </div>
                    )}

                    {syncState === 'error' && result?.error_details?.message && (
                        <div className="p-3 bg-red-50 border border-red-200 rounded-md">
                            <p className="text-sm text-red-700">{result.error_details.message}</p>
                        </div>
                    )}

                    {syncState === 'done' && result?.completed_at && (
                        <p className="text-xs text-gray-500">Finalizado em: {result.completed_at}</p>
                    )}
                </div>
            )}

            {/* Abas de configuração (idle) */}
            {syncState === 'idle' && (
                <>
                    <div className="flex border-b overflow-x-auto -mx-6 px-6">
                        {TABS.map(tab => (
                            <button key={tab.key} onClick={() => setActiveTab(tab.key)}
                                className={`px-4 py-2 text-sm font-medium whitespace-nowrap border-b-2 transition-colors ${
                                    activeTab === tab.key ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'
                                }`}>
                                {tab.label}
                            </button>
                        ))}
                    </div>

                    <div className="min-h-[120px]">
                        {activeTab === 'auto' && (
                            <div>
                                <p className="text-sm text-gray-600 mb-4">Sincroniza automaticamente desde a última data registrada até ontem. Registros existentes são substituídos.</p>
                                <Button variant="primary" onClick={() => handleSync('/movements/sync/auto')} disabled={!cigamAvailable} icon={ArrowPathIcon}>Sincronizar</Button>
                            </div>
                        )}
                        {activeTab === 'today' && (
                            <div>
                                <p className="text-sm text-gray-600 mb-4">Busca apenas novos registros de hoje (incremental). Não remove dados existentes.</p>
                                <Button variant="primary" onClick={() => handleSync('/movements/sync/today')} disabled={!cigamAvailable} icon={ArrowPathIcon}>Sincronizar Hoje</Button>
                            </div>
                        )}
                        {activeTab === 'month' && (
                            <div>
                                <p className="text-sm text-gray-600 mb-4">Re-sincroniza todos os registros do mês selecionado.</p>
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
                                <Button variant="primary" onClick={() => handleSync('/movements/sync/month', { month, year })} disabled={!cigamAvailable} icon={ArrowPathIcon}>Sincronizar Mês</Button>
                            </div>
                        )}
                        {activeTab === 'range' && (
                            <div>
                                <p className="text-sm text-gray-600 mb-4">Re-sincroniza um período específico (máximo 180 dias).</p>
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
                                <Button variant="primary" onClick={() => handleSync('/movements/sync/range', { date_start: dateStart, date_end: dateEnd })}
                                    disabled={!cigamAvailable || !dateStart || !dateEnd} icon={ArrowPathIcon}>Sincronizar Período</Button>
                            </div>
                        )}
                        {activeTab === 'types' && (
                            <div>
                                <p className="text-sm text-gray-600 mb-4">Atualiza a tabela de tipos de movimentação a partir do CIGAM.</p>
                                <Button variant="primary" onClick={() => handleSync('/movements/sync/types')} disabled={!cigamAvailable} icon={ArrowPathIcon}>Sincronizar Tipos</Button>
                            </div>
                        )}
                    </div>
                </>
            )}
        </StandardModal>
    );
}
