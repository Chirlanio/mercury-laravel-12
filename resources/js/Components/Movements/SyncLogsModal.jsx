import { useState, useEffect } from 'react';
import axios from 'axios';
import Modal from '@/Components/Modal';
import Button from '@/Components/Button';

const TYPE_LABELS = {
    auto: 'Automático',
    today: 'Hoje',
    range: 'Período',
    types: 'Tipos',
};

const STATUS_CONFIG = {
    completed: { label: 'Concluído', bg: 'bg-emerald-100', text: 'text-emerald-800' },
    failed: { label: 'Falha', bg: 'bg-red-100', text: 'text-red-800' },
    running: { label: 'Executando', bg: 'bg-blue-100', text: 'text-blue-800' },
};

export default function SyncLogsModal({ isOpen, onClose }) {
    const [loading, setLoading] = useState(true);
    const [data, setData] = useState(null);
    const [expandedId, setExpandedId] = useState(null);

    useEffect(() => {
        if (isOpen) {
            fetchLogs();
        }
    }, [isOpen]);

    const fetchLogs = async () => {
        setLoading(true);
        try {
            const { data } = await axios.get('/movements/sync-logs');
            setData(data);
        } catch {
            setData(null);
        } finally {
            setLoading(false);
        }
    };

    const fmt = (val) => new Intl.NumberFormat('pt-BR').format(val || 0);
    const fmtSeconds = (s) => {
        if (s === null || s === undefined) return '-';
        if (s === 0) return '< 1s';
        const min = Math.floor(s / 60);
        const sec = s % 60;
        return min > 0 ? `${min}m ${sec}s` : `${sec}s`;
    };

    const summary = data?.summary;
    const logs = data?.logs || [];

    return (
        <Modal show={isOpen} onClose={onClose} maxWidth="6xl">
            <div className="p-6">
                <div className="flex items-center justify-between mb-5">
                    <h2 className="text-lg font-semibold text-gray-900">Histórico de Sincronizações</h2>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
                        <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {loading && (
                    <div className="flex items-center justify-center py-12">
                        <div className="animate-spin rounded-full h-8 w-8 border-2 border-indigo-600 border-t-transparent" />
                    </div>
                )}

                {!loading && data && (
                    <>
                        {/* Summary Cards */}
                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
                            <SummaryCard
                                label="Última Sincronização"
                                value={summary?.last_sync_at || 'Nunca'}
                                sub={summary?.last_sync_type ? TYPE_LABELS[summary.last_sync_type] || summary.last_sync_type : null}
                            />
                            <SummaryCard
                                label="Última Data Sincronizada"
                                value={summary?.last_movement_date || '-'}
                            />
                            <SummaryCard
                                label="Total de Registros"
                                value={fmt(summary?.total_movements)}
                            />
                            <SummaryCard
                                label="Sincronizações"
                                value={`${fmt(summary?.total_syncs)} total`}
                                sub={summary?.failed_syncs > 0 ? `${summary.failed_syncs} com falha` : 'Nenhuma falha'}
                                subColor={summary?.failed_syncs > 0 ? 'text-red-500' : 'text-emerald-500'}
                            />
                        </div>

                        {/* Logs Table */}
                        <div className="border rounded-lg overflow-hidden">
                            <div className="overflow-x-auto max-h-[400px] overflow-y-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50 sticky top-0">
                                        <tr>
                                            <th className="px-3 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Data/Hora</th>
                                            <th className="px-3 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                            <th className="px-3 py-2.5 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                                            <th className="px-3 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Período</th>
                                            <th className="px-3 py-2.5 text-right text-xs font-medium text-gray-500 uppercase">Inseridos</th>
                                            <th className="px-3 py-2.5 text-right text-xs font-medium text-gray-500 uppercase">Removidos</th>
                                            <th className="px-3 py-2.5 text-right text-xs font-medium text-gray-500 uppercase">Tempo</th>
                                            <th className="px-3 py-2.5 text-left text-xs font-medium text-gray-500 uppercase">Usuário</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {logs.length === 0 ? (
                                            <tr>
                                                <td colSpan={8} className="px-6 py-8 text-center text-gray-500 text-sm">
                                                    Nenhuma sincronização registrada.
                                                </td>
                                            </tr>
                                        ) : logs.map((log) => {
                                            const statusCfg = STATUS_CONFIG[log.status] || STATUS_CONFIG.failed;
                                            const isExpanded = expandedId === log.id;

                                            return (
                                                <tr key={log.id}
                                                    className="hover:bg-gray-50 cursor-pointer"
                                                    onClick={() => setExpandedId(isExpanded ? null : log.id)}
                                                >
                                                    <td className="px-3 py-2 text-sm text-gray-900 whitespace-nowrap">
                                                        {log.started_at}
                                                    </td>
                                                    <td className="px-3 py-2 text-sm text-gray-600 whitespace-nowrap">
                                                        {TYPE_LABELS[log.sync_type] || log.sync_type}
                                                    </td>
                                                    <td className="px-3 py-2 text-center">
                                                        <span className={`inline-flex px-2 py-0.5 rounded text-xs font-medium ${statusCfg.bg} ${statusCfg.text}`}>
                                                            {statusCfg.label}
                                                        </span>
                                                    </td>
                                                    <td className="px-3 py-2 text-sm text-gray-500 whitespace-nowrap">
                                                        {log.date_range_start && log.date_range_end
                                                            ? `${log.date_range_start} - ${log.date_range_end}`
                                                            : '-'}
                                                    </td>
                                                    <td className="px-3 py-2 text-sm text-right text-emerald-600 font-medium whitespace-nowrap">
                                                        {fmt(log.inserted_records)}
                                                    </td>
                                                    <td className="px-3 py-2 text-sm text-right text-amber-600 whitespace-nowrap">
                                                        {fmt(log.deleted_records)}
                                                    </td>
                                                    <td className="px-3 py-2 text-sm text-right text-gray-500 whitespace-nowrap">
                                                        {fmtSeconds(log.elapsed_seconds)}
                                                    </td>
                                                    <td className="px-3 py-2 text-sm text-gray-500 whitespace-nowrap truncate max-w-[120px]" title={log.started_by}>
                                                        {log.started_by}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {/* Expanded Detail */}
                        {expandedId && (() => {
                            const log = logs.find(l => l.id === expandedId);
                            if (!log) return null;

                            return (
                                <div className="mt-4 p-4 bg-gray-50 rounded-lg border">
                                    <h3 className="text-sm font-medium text-gray-700 mb-3">
                                        Detalhes - Sync #{log.id}
                                    </h3>
                                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                                        <Detail label="Total" value={fmt(log.total_records)} />
                                        <Detail label="Processados" value={fmt(log.processed_records)} />
                                        <Detail label="Inseridos" value={fmt(log.inserted_records)} />
                                        <Detail label="Removidos" value={fmt(log.deleted_records)} />
                                        <Detail label="Ignorados" value={fmt(log.skipped_records)} />
                                        <Detail label="Erros" value={fmt(log.error_count)} />
                                        <Detail label="Início" value={log.started_at || '-'} />
                                        <Detail label="Fim" value={log.completed_at || '-'} />
                                    </div>
                                    {log.error_message && (
                                        <div className="mt-3 p-2 bg-red-50 border border-red-200 rounded text-sm text-red-700">
                                            {log.error_message}
                                        </div>
                                    )}
                                </div>
                            );
                        })()}

                        <div className="mt-4 flex justify-end">
                            <Button variant="secondary" size="sm" onClick={onClose}>Fechar</Button>
                        </div>
                    </>
                )}

                {!loading && !data && (
                    <div className="text-center py-8">
                        <p className="text-sm text-gray-500">Erro ao carregar histórico.</p>
                        <Button variant="secondary" size="sm" onClick={fetchLogs} className="mt-3">Tentar novamente</Button>
                    </div>
                )}
            </div>
        </Modal>
    );
}

function SummaryCard({ label, value, sub, subColor = 'text-gray-400' }) {
    return (
        <div className="bg-gray-50 rounded-lg p-3">
            <p className="text-xs text-gray-500 mb-1">{label}</p>
            <p className="text-sm font-semibold text-gray-900">{value}</p>
            {sub && <p className={`text-xs mt-0.5 ${subColor}`}>{sub}</p>}
        </div>
    );
}

function Detail({ label, value }) {
    return (
        <div>
            <span className="text-gray-500">{label}:</span>{' '}
            <span className="text-gray-900 font-medium">{value}</span>
        </div>
    );
}
