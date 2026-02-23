import { useState, useEffect } from 'react';
import Modal from '@/Components/Modal';

const STATUS_COLORS = {
    pending: 'bg-gray-100 text-gray-800',
    running: 'bg-blue-100 text-blue-800',
    completed: 'bg-green-100 text-green-800',
    failed: 'bg-red-100 text-red-800',
    cancelled: 'bg-amber-100 text-amber-800',
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
                .then(data => {
                    setLogs(data.data || []);
                    setLoading(false);
                })
                .catch(() => setLoading(false));
        }
    }, [show]);

    const formatDuration = (log) => {
        if (!log.started_at || !log.completed_at) return '-';
        const start = new Date(log.started_at);
        const end = new Date(log.completed_at);
        const seconds = Math.round((end - start) / 1000);
        if (seconds < 60) return `${seconds}s`;
        const minutes = Math.floor(seconds / 60);
        return `${minutes}m ${seconds % 60}s`;
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="5xl" title="Histórico de Sincronizações">
            {loading ? (
                <div className="flex justify-center py-12">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-600"></div>
                </div>
            ) : (
                <div className="space-y-4">
                    {selectedLog ? (
                        <div className="space-y-4">
                            <button onClick={() => setSelectedLog(null)}
                                className="text-sm text-indigo-600 hover:text-indigo-800">&larr; Voltar</button>

                            <h3 className="text-lg font-semibold">Detalhes do Sync #{selectedLog.id}</h3>

                            <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                                <InfoCard label="Tipo" value={selectedLog.sync_type} />
                                <InfoCard label="Status" value={STATUS_LABELS[selectedLog.status] || selectedLog.status} />
                                <InfoCard label="Duração" value={formatDuration(selectedLog)} />
                                <InfoCard label="Usuário" value={selectedLog.started_by?.name || '-'} />
                                <InfoCard label="Total" value={selectedLog.total_records?.toLocaleString()} />
                                <InfoCard label="Processados" value={selectedLog.processed_records?.toLocaleString()} />
                                <InfoCard label="Inseridos" value={selectedLog.inserted_records?.toLocaleString()} />
                                <InfoCard label="Atualizados" value={selectedLog.updated_records?.toLocaleString()} />
                                <InfoCard label="Ignorados" value={selectedLog.skipped_records?.toLocaleString()} />
                                <InfoCard label="Erros" value={selectedLog.error_count?.toLocaleString()} />
                            </div>

                            {selectedLog.error_details && selectedLog.error_details.length > 0 && (
                                <div>
                                    <h4 className="text-sm font-semibold text-red-600 mb-2">Detalhes dos Erros</h4>
                                    <pre className="bg-red-50 border border-red-200 rounded-lg p-3 text-xs text-red-700 max-h-48 overflow-y-auto">
                                        {JSON.stringify(selectedLog.error_details, null, 2)}
                                    </pre>
                                </div>
                            )}
                        </div>
                    ) : (
                        <div className="border rounded-lg overflow-hidden">
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
                                        <tr>
                                            <td colSpan={8} className="px-4 py-8 text-center text-sm text-gray-500">
                                                Nenhum registro de sincronização encontrado.
                                            </td>
                                        </tr>
                                    ) : logs.map(log => (
                                        <tr key={log.id} onClick={() => setSelectedLog(log)}
                                            className="cursor-pointer hover:bg-gray-50">
                                            <td className="px-4 py-2 text-sm text-gray-900">
                                                {new Date(log.created_at).toLocaleString('pt-BR')}
                                            </td>
                                            <td className="px-4 py-2 text-sm text-gray-600">{log.sync_type}</td>
                                            <td className="px-4 py-2">
                                                <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${STATUS_COLORS[log.status] || ''}`}>
                                                    {STATUS_LABELS[log.status] || log.status}
                                                </span>
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

                    <div className="flex justify-end pt-4 border-t">
                        <button onClick={onClose}
                            className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                            Fechar
                        </button>
                    </div>
                </div>
            )}
        </Modal>
    );
}

function InfoCard({ label, value }) {
    return (
        <div className="bg-gray-50 rounded-lg p-3">
            <div className="text-xs text-gray-500">{label}</div>
            <div className="text-sm font-semibold text-gray-900">{value ?? '-'}</div>
        </div>
    );
}
