import { useState, useEffect, useRef, useCallback } from 'react';
import Modal from '@/Components/Modal';

const PHASE_LABELS = {
    lookups: 'Sincronizando Tabelas Auxiliares',
    products: 'Sincronizando Produtos',
    prices: 'Atualizando Preços',
};

const SYNC_TYPES = [
    { value: 'full', label: 'Completa', description: 'Sincroniza todo o catálogo de produtos' },
    { value: 'incremental', label: 'Incremental', description: 'Apenas novos e alterados desde a última sync' },
    { value: 'lookups_only', label: 'Apenas Tabelas Auxiliares', description: 'Sincroniza marcas, categorias, cores etc.' },
    { value: 'prices_only', label: 'Apenas Preços', description: 'Atualiza preços de venda e custo' },
];

export default function ProductSyncModal({ show, onClose, onCompleted, activeSyncLog }) {
    const [phase, setPhase] = useState('configuring');
    const [syncType, setSyncType] = useState('full');
    const [logId, setLogId] = useState(null);
    const [progress, setProgress] = useState({ total: 0, processed: 0, inserted: 0, updated: 0, skipped: 0, errors: 0 });
    const [currentPhase, setCurrentPhase] = useState(null);
    const [errorMessage, setErrorMessage] = useState('');
    const pollingRef = useRef(null);

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    const fetchJson = async (url, options = {}) => {
        const res = await fetch(url, {
            ...options,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                ...options.headers,
            },
        });
        if (!res.ok) {
            const data = await res.json().catch(() => ({}));
            throw new Error(data.error || data.message || `HTTP ${res.status}`);
        }
        return res.json();
    };

    const stopPolling = useCallback(() => {
        if (pollingRef.current) {
            clearInterval(pollingRef.current);
            pollingRef.current = null;
        }
    }, []);

    const updateFromLog = useCallback((log) => {
        setLogId(log.id);
        setProgress({
            total: log.total_records || 0,
            processed: log.processed_records || 0,
            inserted: log.inserted_records || 0,
            updated: log.updated_records || 0,
            skipped: log.skipped_records || 0,
            errors: log.error_count || 0,
        });
        setCurrentPhase(log.current_phase);

        if (log.status === 'completed') {
            setPhase('completed');
            setErrorMessage('');
            return true;
        } else if (log.status === 'failed') {
            setPhase('error');
            const details = log.error_details;
            setErrorMessage(Array.isArray(details) ? details[details.length - 1] : 'Erro durante a sincronização.');
            return true;
        } else if (log.status === 'cancelled') {
            setPhase('cancelled');
            return true;
        }
        return false;
    }, []);

    const startPolling = useCallback((id) => {
        stopPolling();
        setPhase('running');

        pollingRef.current = setInterval(async () => {
            try {
                const data = await fetchJson(`/products/sync/status/${id}`);
                const done = updateFromLog(data);
                if (done) {
                    stopPolling();
                    onCompleted && onCompleted();
                }
            } catch {
                // Silently retry on next interval
            }
        }, 2000);
    }, [stopPolling, updateFromLog, onCompleted]);

    // If there's an active sync when modal opens, resume polling
    useEffect(() => {
        if (show && activeSyncLog && activeSyncLog.status === 'running') {
            updateFromLog(activeSyncLog);
            startPolling(activeSyncLog.id);
        }
    }, [show, activeSyncLog]);

    // Cleanup polling on unmount or close
    useEffect(() => {
        return () => stopPolling();
    }, [stopPolling]);

    const startSync = async () => {
        setErrorMessage('');
        setProgress({ total: 0, processed: 0, inserted: 0, updated: 0, skipped: 0, errors: 0 });

        try {
            if (syncType === 'lookups_only') {
                setPhase('running');
                setCurrentPhase('lookups');
                const data = await fetchJson('/products/sync/init', {
                    method: 'POST',
                    body: JSON.stringify({ type: 'lookups_only' }),
                });
                setPhase('completed');
                setCurrentPhase(null);
                onCompleted && onCompleted();
                return;
            }

            setPhase('running');
            setCurrentPhase('initializing');

            const initData = await fetchJson('/products/sync/init', {
                method: 'POST',
                body: JSON.stringify({ type: syncType }),
            });

            const log = initData.log;
            setLogId(log.id);
            setProgress(prev => ({ ...prev, total: log.total_records || 0 }));

            // Job is dispatched on the server, start polling
            startPolling(log.id);

        } catch (err) {
            setPhase('error');
            setErrorMessage(err.message || 'Erro ao iniciar sincronização.');
        }
    };

    const handleCancel = async () => {
        stopPolling();
        if (logId) {
            try {
                await fetchJson('/products/sync/cancel', {
                    method: 'POST',
                    body: JSON.stringify({ log_id: logId }),
                });
            } catch {}
        }
        setPhase('cancelled');
    };

    const handleClose = () => {
        stopPolling();
        setPhase('configuring');
        setSyncType('full');
        setLogId(null);
        setProgress({ total: 0, processed: 0, inserted: 0, updated: 0, skipped: 0, errors: 0 });
        setCurrentPhase(null);
        setErrorMessage('');
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

    return (
        <Modal show={show} onClose={handleClose} closeable={true} maxWidth="2xl" title="Sincronizar Produtos">
            <div className="space-y-6">
                {/* Config phase */}
                {phase === 'configuring' && (
                    <>
                        <div className="space-y-3">
                            {SYNC_TYPES.map(t => (
                                <label key={t.value} className={`flex items-start gap-3 p-3 rounded-lg border cursor-pointer transition-colors ${
                                    syncType === t.value ? 'border-indigo-300 bg-indigo-50' : 'border-gray-200 hover:bg-gray-50'
                                }`}>
                                    <input type="radio" name="syncType" value={t.value} checked={syncType === t.value}
                                        onChange={() => setSyncType(t.value)}
                                        className="mt-0.5 text-indigo-600 focus:ring-indigo-500" />
                                    <div>
                                        <div className="text-sm font-medium text-gray-900">{t.label}</div>
                                        <div className="text-xs text-gray-500">{t.description}</div>
                                    </div>
                                </label>
                            ))}
                        </div>

                        <div className="flex justify-end gap-3">
                            <button onClick={handleClose}
                                className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                Cancelar
                            </button>
                            <button onClick={startSync}
                                className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">
                                Iniciar Sincronização
                            </button>
                        </div>
                    </>
                )}

                {/* Running / Complete / Error */}
                {phase !== 'configuring' && (
                    <>
                        {/* Phase indicator */}
                        <div className="text-center">
                            <div className={`inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-medium ${
                                phase === 'completed' ? 'bg-green-100 text-green-800' :
                                phase === 'error' ? 'bg-red-100 text-red-800' :
                                phase === 'cancelled' ? 'bg-gray-100 text-gray-800' :
                                'bg-blue-100 text-blue-800'
                            }`}>
                                {isRunning && <div className="animate-spin rounded-full h-4 w-4 border-2 border-current border-t-transparent"></div>}
                                {getPhaseLabel()}
                            </div>
                        </div>

                        {/* Background info */}
                        {isRunning && (
                            <div className="bg-blue-50 border border-blue-200 rounded-lg p-3">
                                <p className="text-xs text-blue-700">
                                    A sincronização está rodando em background. Você pode fechar este modal e continuar usando o sistema.
                                </p>
                            </div>
                        )}

                        {/* Progress bar */}
                        {progress.total > 0 && (
                            <div>
                                <div className="flex justify-between text-xs text-gray-500 mb-1">
                                    <span>{progress.processed.toLocaleString()} / {progress.total.toLocaleString()} produtos</span>
                                    <span>{progressPercent}%</span>
                                </div>
                                <div className="w-full bg-gray-200 rounded-full h-3">
                                    <div className={`h-3 rounded-full transition-all duration-300 ${
                                        phase === 'completed' ? 'bg-green-500' :
                                        phase === 'error' ? 'bg-red-500' :
                                        'bg-indigo-600'
                                    }`} style={{ width: `${Math.min(progressPercent, 100)}%` }}></div>
                                </div>
                            </div>
                        )}

                        {/* Stats */}
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <StatCard label="Inseridos" value={progress.inserted} color="text-green-600" />
                            <StatCard label="Atualizados" value={progress.updated} color="text-blue-600" />
                            <StatCard label="Ignorados" value={progress.skipped} color="text-gray-600" />
                            <StatCard label="Erros" value={progress.errors} color="text-red-600" />
                        </div>

                        {/* Error message */}
                        {errorMessage && (
                            <div className="bg-red-50 border border-red-200 rounded-lg p-3">
                                <p className="text-sm text-red-700">{errorMessage}</p>
                            </div>
                        )}

                        {/* Actions */}
                        <div className="flex justify-end gap-3">
                            {isRunning && (
                                <button onClick={handleCancel}
                                    className="px-4 py-2 text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100">
                                    Cancelar Sincronização
                                </button>
                            )}
                            <button onClick={handleClose}
                                className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                                {isRunning ? 'Minimizar' : 'Fechar'}
                            </button>
                        </div>
                    </>
                )}
            </div>
        </Modal>
    );
}

function StatCard({ label, value, color }) {
    return (
        <div className="bg-gray-50 rounded-lg p-3 text-center">
            <div className={`text-xl font-bold ${color}`}>{value.toLocaleString()}</div>
            <div className="text-xs text-gray-500">{label}</div>
        </div>
    );
}
