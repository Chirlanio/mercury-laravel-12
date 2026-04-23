import { useEffect, useState } from 'react';
import {
    ClockIcon,
    CheckCircleIcon,
    XCircleIcon,
    PlayCircleIcon,
    CloudArrowDownIcon,
} from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';

const STATUS_MAP = {
    running: { label: 'Em execução', color: 'info', icon: PlayCircleIcon },
    completed: { label: 'Concluído', color: 'success', icon: CheckCircleIcon },
    cancelled: { label: 'Cancelado', color: 'gray', icon: XCircleIcon },
    failed: { label: 'Falhou', color: 'danger', icon: XCircleIcon },
    pending: { label: 'Pendente', color: 'warning', icon: ClockIcon },
};

/**
 * Modal — histórico das últimas 30 sincronizações do CIGAM.
 *
 * Mostra: status, duração, contagens (inseridos/atualizados/pulados/erros)
 * e quem disparou (manual vs schedule). Atualiza on-demand via fetch.
 */
export default function SyncHistoryModal({ show, onClose }) {
    const [logs, setLogs] = useState([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!show) return;

        let cancelled = false;
        setLoading(true);

        fetch(route('customers.sync-history'), {
            headers: { Accept: 'application/json' },
        })
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then((json) => {
                if (!cancelled) setLogs(json.logs || []);
            })
            .catch(() => {
                if (!cancelled) setLogs([]);
            })
            .finally(() => {
                if (!cancelled) setLoading(false);
            });

        return () => { cancelled = true; };
    }, [show]);

    const formatDateTime = (iso) =>
        iso ? new Date(iso).toLocaleString('pt-BR') : '—';

    const formatDuration = (seconds) => {
        if (!seconds && seconds !== 0) return '—';
        if (seconds < 60) return `${seconds}s`;
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return secs > 0 ? `${mins}min ${secs}s` : `${mins}min`;
    };

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title="Histórico de sincronizações"
            subtitle="Últimas 30 execuções — manual e agendada"
            headerColor="bg-indigo-600"
            headerIcon={<CloudArrowDownIcon className="h-5 w-5" />}
            maxWidth="4xl"
        >
            {loading ? (
                <div className="py-12 text-center text-gray-500">
                    Carregando histórico…
                </div>
            ) : logs.length === 0 ? (
                <div className="py-12 text-center text-gray-500">
                    Nenhuma sincronização registrada ainda. Clique em "Sincronizar" na listagem
                    para disparar a primeira execução.
                </div>
            ) : (
                <div className="space-y-3">
                    {logs.map((log) => {
                        const meta = STATUS_MAP[log.status] ?? STATUS_MAP.pending;
                        const Icon = meta.icon;
                        const errored = log.error_count > 0;

                        return (
                            <div
                                key={log.id}
                                className={`border rounded-md p-3 sm:p-4 ${
                                    errored ? 'border-amber-300 bg-amber-50'
                                        : log.status === 'failed' ? 'border-red-300 bg-red-50'
                                        : 'border-gray-200 bg-white'
                                }`}
                            >
                                <div className="flex items-start justify-between gap-3 flex-wrap">
                                    <div className="flex items-start gap-2 min-w-0">
                                        <Icon className={`w-5 h-5 shrink-0 mt-0.5 ${
                                            log.status === 'completed' ? 'text-green-600'
                                                : log.status === 'failed' ? 'text-red-600'
                                                : log.status === 'running' ? 'text-blue-600'
                                                : 'text-gray-400'
                                        }`} />
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-2 flex-wrap">
                                                <span className="font-medium text-gray-900">
                                                    Sync #{log.id}
                                                </span>
                                                <StatusBadge color={meta.color}>{meta.label}</StatusBadge>
                                                <span className="text-xs text-gray-500">
                                                    {log.triggered === 'manual' ? 'Manual' : 'Agendado'}
                                                </span>
                                            </div>
                                            <div className="text-xs text-gray-600 mt-1">
                                                Iniciado: {formatDateTime(log.started_at)}
                                                {log.started_by && ` · por ${log.started_by}`}
                                            </div>
                                            {log.completed_at && (
                                                <div className="text-xs text-gray-600">
                                                    Finalizado: {formatDateTime(log.completed_at)} · Duração {formatDuration(log.duration_seconds)}
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>

                                {/* Contagens — grid responsivo */}
                                <div className="mt-3 grid grid-cols-2 sm:grid-cols-5 gap-2 text-xs">
                                    <StatCell label="Total" value={log.total_records} color="gray" />
                                    <StatCell label="Inseridos" value={log.inserted_records} color="green" />
                                    <StatCell label="Atualizados" value={log.updated_records} color="blue" />
                                    <StatCell label="Pulados" value={log.skipped_records} color="gray" />
                                    <StatCell
                                        label="Erros"
                                        value={log.error_count}
                                        color={log.error_count > 0 ? 'red' : 'gray'}
                                    />
                                </div>

                                {log.status === 'running' && log.total_records > 0 && (
                                    <div className="mt-3">
                                        <div className="text-xs text-gray-600 mb-1">
                                            Progresso: {log.processed_records} / {log.total_records}
                                        </div>
                                        <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
                                            <div
                                                className="h-full bg-blue-500 transition-all"
                                                style={{
                                                    width: `${Math.min(100, Math.round((log.processed_records / log.total_records) * 100))}%`,
                                                }}
                                            />
                                        </div>
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}
        </StandardModal>
    );
}

function StatCell({ label, value, color = 'gray' }) {
    const colors = {
        gray: 'bg-gray-100 text-gray-700',
        green: 'bg-green-50 text-green-700',
        blue: 'bg-blue-50 text-blue-700',
        red: 'bg-red-50 text-red-700',
    };

    return (
        <div className={`rounded-md px-2 py-1.5 text-center ${colors[color] || colors.gray}`}>
            <div className="font-bold text-sm">{Number(value || 0).toLocaleString('pt-BR')}</div>
            <div className="text-[10px] uppercase tracking-wide opacity-75">{label}</div>
        </div>
    );
}
