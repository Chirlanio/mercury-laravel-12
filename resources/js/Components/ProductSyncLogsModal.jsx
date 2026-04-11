import { useState, useEffect } from 'react';
import StandardModal from '@/Components/StandardModal';
import Button from '@/Components/Button';
import StatusBadge from '@/Components/Shared/StatusBadge';
import { ArrowLeftIcon } from '@heroicons/react/24/outline';

const STATUS_VARIANT = {
    pending: 'gray',
    running: 'info',
    completed: 'success',
    failed: 'danger',
    cancelled: 'warning',
};

const STATUS_LABELS = {
    pending: 'Pendente',
    running: 'Em Execução',
    completed: 'Concluído',
    failed: 'Falhou',
    cancelled: 'Cancelado',
};

export default function ProductSyncLogsModal({ show, onClose }) {
    const [logs, setLogs] = useState([]);
    const [loading, setLoading] = useState(false);
    const [selectedLog, setSelectedLog] = useState(null);

    useEffect(() => {
        if (show) {
            setLoading(true);
            fetch('/products/sync/logs')
                .then(res => res.json())
                .then(data => { setLogs(data.data || []); setLoading(false); })
                .catch(() => setLoading(false));
        }
    }, [show]);

    const formatDuration = (log) => {
        if (!log.started_at || !log.completed_at) return '-';
        const seconds = Math.round((new Date(log.completed_at) - new Date(log.started_at)) / 1000);
        return seconds < 60 ? `${seconds}s` : `${Math.floor(seconds / 60)}m ${seconds % 60}s`;
    };

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title={selectedLog ? `Detalhes do Sync #${selectedLog.id}` : 'Histórico de Sincronizações'}
            headerColor="bg-gray-700"
            loading={loading}
            maxWidth="5xl"
            headerActions={selectedLog && (
                <Button variant="outline" size="xs" onClick={() => setSelectedLog(null)} icon={ArrowLeftIcon}
                    className="text-white border-white/30 hover:bg-white/10">
                    Voltar
                </Button>
            )}
            footer={<StandardModal.Footer onCancel={onClose} cancelLabel="Fechar" />}
        >
            {selectedLog ? (
                <>
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <StandardModal.MiniField label="Tipo" value={selectedLog.sync_type} />
                        <div className="bg-gray-50 rounded p-2">
                            <p className="text-[10px] font-semibold text-gray-400 uppercase">Status</p>
                            <div className="mt-0.5">
                                <StatusBadge variant={STATUS_VARIANT[selectedLog.status] || 'gray'}>
                                    {STATUS_LABELS[selectedLog.status] || selectedLog.status}
                                </StatusBadge>
                            </div>
                        </div>
                        <StandardModal.MiniField label="Duração" value={formatDuration(selectedLog)} />
                        <StandardModal.MiniField label="Usuário" value={selectedLog.started_by?.name || '-'} />
                        <StandardModal.MiniField label="Total" value={selectedLog.total_records?.toLocaleString()} />
                        <StandardModal.MiniField label="Processados" value={selectedLog.processed_records?.toLocaleString()} />
                        <StandardModal.MiniField label="Inseridos" value={selectedLog.inserted_records?.toLocaleString()} />
                        <StandardModal.MiniField label="Atualizados" value={selectedLog.updated_records?.toLocaleString()} />
                        <StandardModal.MiniField label="Ignorados" value={selectedLog.skipped_records?.toLocaleString()} />
                        <StandardModal.MiniField label="Erros" value={selectedLog.error_count?.toLocaleString()} />
                    </div>

                    {selectedLog.error_details?.length > 0 && (
                        <StandardModal.Section title="Detalhes dos Erros">
                            <pre className="bg-red-50 border border-red-200 rounded-lg p-3 text-xs text-red-700 max-h-48 overflow-y-auto -mx-4 -mb-4">
                                {JSON.stringify(selectedLog.error_details, null, 2)}
                            </pre>
                        </StandardModal.Section>
                    )}
                </>
            ) : (
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                                <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                                <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                <th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                                <th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Inseridos</th>
                                <th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Atualizados</th>
                                <th className="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Erros</th>
                                <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Duração</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {logs.length === 0 ? (
                                <tr><td colSpan={8} className="px-4 py-8 text-center text-sm text-gray-500">Nenhum registro de sincronização encontrado.</td></tr>
                            ) : logs.map(log => (
                                <tr key={log.id} onClick={() => setSelectedLog(log)} className="cursor-pointer hover:bg-gray-50">
                                    <td className="px-4 py-2 text-sm text-gray-900">{new Date(log.created_at).toLocaleString('pt-BR')}</td>
                                    <td className="px-4 py-2 text-sm text-gray-600">{log.sync_type}</td>
                                    <td className="px-4 py-2">
                                        <StatusBadge variant={STATUS_VARIANT[log.status] || 'gray'}>
                                            {STATUS_LABELS[log.status] || log.status}
                                        </StatusBadge>
                                    </td>
                                    <td className="px-4 py-2 text-sm text-gray-900 text-right">{log.total_records?.toLocaleString()}</td>
                                    <td className="px-4 py-2 text-sm text-green-600 text-right">{log.inserted_records?.toLocaleString()}</td>
                                    <td className="px-4 py-2 text-sm text-blue-600 text-right">{log.updated_records?.toLocaleString()}</td>
                                    <td className="px-4 py-2 text-sm text-red-600 text-right">{log.error_count?.toLocaleString()}</td>
                                    <td className="px-4 py-2 text-sm text-gray-600">{formatDuration(log)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </StandardModal>
    );
}
