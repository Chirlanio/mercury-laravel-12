import { useState, useEffect, useRef, useCallback } from 'react';
import StandardModal from '@/Components/StandardModal';
import Button from '@/Components/Button';
import StatusBadge from '@/Components/Shared/StatusBadge';
import { ArrowPathIcon, XMarkIcon } from '@heroicons/react/24/outline';

const PHASE_LABELS = {
    lookups: 'Sincronizando Tabelas Auxiliares',
    products: 'Sincronizando Produtos',
    prices: 'Atualizando Preços',
};

const SYNC_TYPES = [
    { value: 'full', label: 'Completa', description: 'Sincroniza todo o catálogo de produtos' },
    { value: 'incremental', label: 'Incremental', description: 'Apenas novos e alterados desde a última sync' },
    { value: 'by_period', label: 'Por Período', description: 'Sincroniza produtos criados ou alterados em um período específico' },
    { value: 'lookups_only', label: 'Apenas Tabelas Auxiliares', description: 'Sincroniza marcas, categorias, cores etc.' },
    { value: 'prices_only', label: 'Apenas Preços', description: 'Atualiza preços de venda e custo' },
];

export default function ProductSyncModal({ show, onClose, onCompleted, onStarted, activeSyncLog }) {
    const [phase, setPhase] = useState('configuring');
    const [syncType, setSyncType] = useState('full');
    const [dateStart, setDateStart] = useState('');
    const [dateEnd, setDateEnd] = useState('');
    const [logId, setLogId] = useState(null);
    const [progress, setProgress] = useState({ total: 0, processed: 0, inserted: 0, updated: 0, skipped: 0, errors: 0 });
    const [lookupProgress, setLookupProgress] = useState({ total: 0, processed: 0, current: null });
    const [priceProgress, setPriceProgress] = useState({ total: 0, processed: 0 });
    const [currentPhase, setCurrentPhase] = useState(null);
    const [errorMessage, setErrorMessage] = useState('');
    const pollingRef = useRef(null);

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    const fetchJson = async (url, options = {}) => {
        const res = await fetch(url, {
            ...options,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), ...options.headers },
        });
        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            throw new Error(data.error || data.message || `HTTP ${res.status}`);
        }
        return res.json();
    };

    const stopPolling = useCallback(() => {
        if (pollingRef.current) { clearInterval(pollingRef.current); pollingRef.current = null; }
    }, []);

    const updateFromLog = useCallback((log) => {
        setLogId(log.id);
        setProgress({ total: log.total_records || 0, processed: log.processed_records || 0, inserted: log.inserted_records || 0, updated: log.updated_records || 0, skipped: log.skipped_records || 0, errors: log.error_count || 0 });
        setLookupProgress({ total: log.lookup_total || 0, processed: log.lookup_processed || 0, current: log.lookup_current || null });
        setPriceProgress({ total: log.price_total || 0, processed: log.price_processed || 0 });
        setCurrentPhase(log.current_phase);

        if (log.status === 'completed') { setPhase('completed'); setErrorMessage(''); return true; }
        if (log.status === 'failed') {
            setPhase('error');
            const details = log.error_details;
            setErrorMessage(Array.isArray(details) ? details[details.length - 1] : 'Erro durante a sincronização.');
            return true;
        }
        if (log.status === 'cancelled') { setPhase('cancelled'); return true; }
        return false;
    }, []);

    const startPolling = useCallback((id) => {
        stopPolling();
        setPhase('running');
        pollingRef.current = setInterval(async () => {
            try {
                const data = await fetchJson(`/products/sync/status/${id}`);
                if (updateFromLog(data)) { stopPolling(); onCompleted?.(); }
            } catch {}
        }, 2000);
    }, [stopPolling, updateFromLog, onCompleted]);

    useEffect(() => {
        if (show && activeSyncLog?.status === 'running') {
            updateFromLog(activeSyncLog);
            startPolling(activeSyncLog.id);
        }
    }, [show, activeSyncLog]);

    useEffect(() => () => stopPolling(), [stopPolling]);

    const startSync = async () => {
        setErrorMessage('');
        setProgress({ total: 0, processed: 0, inserted: 0, updated: 0, skipped: 0, errors: 0 });
        try {
            setPhase('running');
            setCurrentPhase('initializing');
            const body = { type: syncType };
            if (syncType === 'by_period') { body.date_start = dateStart; body.date_end = dateEnd; }
            const initData = await fetchJson('/products/sync/init', { method: 'POST', body: JSON.stringify(body) });
            setLogId(initData.log.id);
            setCurrentPhase(initData.log.current_phase || 'initializing');
            setProgress(prev => ({ ...prev, total: initData.log.total_records || 0 }));
            onStarted?.(initData.log);
            startPolling(initData.log.id);
        } catch (err) {
            setPhase('error');
            setErrorMessage(err.message || 'Erro ao iniciar sincronização.');
        }
    };

    const handleCancel = async () => {
        stopPolling();
        if (logId) { try { await fetchJson('/products/sync/cancel', { method: 'POST', body: JSON.stringify({ log_id: logId }) }); } catch {} }
        setPhase('cancelled');
        onCompleted?.();
    };

    const handleClose = () => {
        stopPolling();
        setPhase('configuring'); setSyncType('full'); setDateStart(''); setDateEnd('');
        setLogId(null); setProgress({ total: 0, processed: 0, inserted: 0, updated: 0, skipped: 0, errors: 0 });
        setLookupProgress({ total: 0, processed: 0, current: null }); setPriceProgress({ total: 0, processed: 0 });
        setCurrentPhase(null); setErrorMessage('');
        onClose();
    };

    const isRunning = phase === 'running';
    const progressPercent = progress.total > 0 ? Math.round((progress.processed / progress.total) * 100) : 0;

    const getPhaseLabel = () => {
        if (currentPhase && PHASE_LABELS[currentPhase]) return PHASE_LABELS[currentPhase];
        if (currentPhase === 'initializing') return 'Inicializando...';
        if (phase === 'running') return 'Sincronizando...';
        if (phase === 'completed') return 'Sincronização concluída com sucesso!';
        if (phase === 'error') return 'Erro';
        if (phase === 'cancelled') return 'Cancelado';
        return '';
    };

    const headerColor = phase === 'completed' ? 'bg-green-600' : phase === 'error' ? 'bg-red-600' : isRunning ? 'bg-blue-600' : 'bg-indigo-600';

    const footerContent = (
        <>
            {isRunning && <Button variant="danger" size="sm" icon={XMarkIcon} onClick={handleCancel}>Cancelar Sincronização</Button>}
            <div className="flex-1" />
            <Button variant="outline" onClick={handleClose}>{isRunning ? 'Minimizar' : 'Fechar'}</Button>
        </>
    );

    return (
        <StandardModal
            show={show}
            onClose={handleClose}
            title="Sincronizar Produtos"
            headerColor={headerColor}
            headerIcon={<ArrowPathIcon className={`h-5 w-5 ${isRunning ? 'animate-spin' : ''}`} />}
            maxWidth="2xl"
            errorMessage={errorMessage}
            footer={<StandardModal.Footer>{footerContent}</StandardModal.Footer>}
        >
            {/* Config phase */}
            {phase === 'configuring' && (
                <>
                    <StandardModal.Section title="Tipo de Sincronização">
                        <div className="space-y-3 -mx-4 -mb-4 px-4 pb-4">
                            {SYNC_TYPES.map(t => (
                                <label key={t.value} className={`flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition-colors ${
                                    syncType === t.value ? 'border-indigo-300 bg-indigo-50' : 'border-gray-200 hover:bg-gray-50'
                                }`}>
                                    <input type="radio" name="syncType" value={t.value} checked={syncType === t.value}
                                        onChange={() => setSyncType(t.value)} className="mt-0.5 text-indigo-600 focus:ring-indigo-500" />
                                    <div>
                                        <div className="text-sm font-medium text-gray-900">{t.label}</div>
                                        <div className="text-xs text-gray-500">{t.description}</div>
                                    </div>
                                </label>
                            ))}
                        </div>
                    </StandardModal.Section>

                    {syncType === 'by_period' && (
                        <div className="flex gap-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                            <div className="flex-1">
                                <label className="block text-xs text-gray-500 mb-1">Data Início</label>
                                <input type="date" value={dateStart} onChange={(e) => setDateStart(e.target.value)}
                                    className="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                            <div className="flex-1">
                                <label className="block text-xs text-gray-500 mb-1">Data Fim</label>
                                <input type="date" value={dateEnd} onChange={(e) => setDateEnd(e.target.value)}
                                    className="w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                        </div>
                    )}

                    <div className="flex justify-end">
                        <Button variant="primary" icon={ArrowPathIcon} onClick={startSync}
                            disabled={syncType === 'by_period' && (!dateStart || !dateEnd)}>
                            Iniciar Sincronização
                        </Button>
                    </div>
                </>
            )}

            {/* Running / Complete / Error */}
            {phase !== 'configuring' && (
                <>
                    <div className="text-center">
                        <StatusBadge
                            variant={phase === 'completed' ? 'success' : phase === 'error' ? 'danger' : phase === 'cancelled' ? 'gray' : 'info'}
                            size="lg"
                        >
                            {isRunning && <div className="animate-spin rounded-full h-3.5 w-3.5 border-2 border-current border-t-transparent mr-1" />}
                            {getPhaseLabel()}
                        </StatusBadge>
                    </div>

                    {isRunning && (
                        <div className="bg-blue-50 border border-blue-200 rounded-lg p-3">
                            <p className="text-xs text-blue-700">A sincronização está rodando em background. Você pode fechar este modal e continuar usando o sistema.</p>
                        </div>
                    )}

                    {/* Lookup progress bar */}
                    {currentPhase === 'lookups' && lookupProgress.total > 0 && (
                        <ProgressBar
                            label={lookupProgress.current ? `Sincronizando: ${lookupProgress.current}` : `${lookupProgress.processed} / ${lookupProgress.total} tabelas`}
                            current={lookupProgress.processed}
                            total={lookupProgress.total}
                            color="bg-amber-500"
                        />
                    )}

                    {/* Price progress bar */}
                    {currentPhase === 'prices' && priceProgress.total > 0 && (
                        <ProgressBar
                            label="Atualizando preços"
                            current={priceProgress.processed}
                            total={priceProgress.total}
                            color={phase === 'completed' ? 'bg-green-500' : 'bg-purple-500'}
                        />
                    )}

                    {/* Product progress bar */}
                    {progress.total > 0 && currentPhase !== 'lookups' && currentPhase !== 'prices' && (
                        <ProgressBar
                            label={`${progress.processed.toLocaleString()} / ${progress.total.toLocaleString()} produtos`}
                            current={progress.processed}
                            total={progress.total}
                            color={phase === 'completed' ? 'bg-green-500' : phase === 'error' ? 'bg-red-500' : 'bg-indigo-600'}
                        />
                    )}

                    {/* Stats */}
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <StandardModal.MiniField label="Inseridos" value={progress.inserted.toLocaleString()} />
                        <StandardModal.MiniField label="Atualizados" value={progress.updated.toLocaleString()} />
                        <StandardModal.MiniField label="Ignorados" value={progress.skipped.toLocaleString()} />
                        <StandardModal.MiniField label="Erros" value={progress.errors.toLocaleString()} />
                    </div>
                </>
            )}
        </StandardModal>
    );
}

function ProgressBar({ label, current, total, color }) {
    const percent = total > 0 ? Math.round((current / total) * 100) : 0;
    return (
        <div>
            <div className="flex justify-between text-xs text-gray-500 mb-1">
                <span>{label}</span>
                <span>{current.toLocaleString()} / {total.toLocaleString()} ({percent}%)</span>
            </div>
            <div className="w-full bg-gray-200 rounded-full h-3">
                <div className={`h-3 rounded-full transition-all duration-300 ${color}`}
                    style={{ width: `${Math.min(percent, 100)}%` }} />
            </div>
        </div>
    );
}
