import { useState, useEffect } from 'react';
import axios from 'axios';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';
import Button from '@/Components/Button';
import { ArrowPathIcon } from '@heroicons/react/24/outline';

const TYPE_LABELS = { auto: 'Automático', today: 'Hoje', range: 'Período', types: 'Tipos' };
const STATUS_VARIANT = { completed: 'success', failed: 'danger', running: 'info' };
const STATUS_LABEL = { completed: 'Concluído', failed: 'Falha', running: 'Executando' };

function SummaryList({ title, data }) {
    const entries = Object.entries(data || {}).sort((a, b) => b[1] - a[1]).slice(0, 10);
    if (entries.length === 0) return null;
    return (
        <div className="bg-gray-50 rounded p-2">
            <p className="text-[10px] font-semibold text-gray-400 uppercase mb-1">{title}</p>
            <div className="space-y-0.5 text-xs">
                {entries.map(([k, v]) => (
                    <div key={k} className="flex justify-between">
                        <span className="text-gray-600 truncate">{k}</span>
                        <span className="text-gray-900 font-medium ml-2">{new Intl.NumberFormat('pt-BR').format(v)}</span>
                    </div>
                ))}
            </div>
        </div>
    );
}

export default function SyncLogsModal({ show, onClose }) {
    const [loading, setLoading] = useState(true);
    const [data, setData] = useState(null);
    const [expandedId, setExpandedId] = useState(null);

    useEffect(() => {
        if (show) fetchLogs();
    }, [show]);

    const fetchLogs = async () => {
        setLoading(true);
        try { setData((await axios.get('/movements/sync-logs')).data); }
        catch { setData(null); }
        finally { setLoading(false); }
    };

    const fmt = (val) => new Intl.NumberFormat('pt-BR').format(val || 0);
    const fmtSeconds = (s) => {
        if (s === null || s === undefined) return '-';
        if (s === 0) return '< 1s';
        const min = Math.floor(s / 60);
        return min > 0 ? `${min}m ${s % 60}s` : `${s}s`;
    };

    const summary = data?.summary;
    const logs = data?.logs || [];

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title="Histórico de Sincronizações"
            headerColor="bg-gray-700"
            loading={loading}
            errorMessage={!loading && !data ? 'Erro ao carregar histórico.' : null}
            maxWidth="6xl"
            headerActions={!loading && !data && (
                <Button variant="outline" size="xs" onClick={fetchLogs} icon={ArrowPathIcon}
                    className="text-white border-white/30 hover:bg-white/10">Tentar novamente</Button>
            )}
            footer={data && <StandardModal.Footer onCancel={onClose} cancelLabel="Fechar" />}
        >
            {data && (
                <>
                    {/* Resumo */}
                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <StandardModal.MiniField label="Última Sincronização"
                            value={summary?.last_sync_at || 'Nunca'} />
                        <StandardModal.MiniField label="Última Data Sincronizada"
                            value={summary?.last_movement_date || '-'} />
                        <StandardModal.MiniField label="Total de Registros"
                            value={fmt(summary?.total_movements)} />
                        <div className="bg-gray-50 rounded p-2">
                            <p className="text-[10px] font-semibold text-gray-400 uppercase">Sincronizações</p>
                            <p className="text-sm text-gray-900 mt-0.5">{fmt(summary?.total_syncs)} total</p>
                            <p className={`text-xs mt-0.5 ${summary?.failed_syncs > 0 ? 'text-red-500' : 'text-emerald-500'}`}>
                                {summary?.failed_syncs > 0 ? `${summary.failed_syncs} com falha` : 'Nenhuma falha'}
                            </p>
                        </div>
                    </div>

                    {/* Tabela de Logs */}
                    <StandardModal.Section title="Registros">
                        <div className="overflow-x-auto max-h-[400px] overflow-y-auto -mx-4 -mb-4">
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
                                        <tr><td colSpan={8} className="px-6 py-8 text-center text-gray-500 text-sm">Nenhuma sincronização registrada.</td></tr>
                                    ) : logs.map((log) => (
                                        <tr key={log.id} className="hover:bg-gray-50 cursor-pointer" onClick={() => setExpandedId(expandedId === log.id ? null : log.id)}>
                                            <td className="px-3 py-2 text-sm text-gray-900 whitespace-nowrap">{log.started_at}</td>
                                            <td className="px-3 py-2 text-sm text-gray-600 whitespace-nowrap">{TYPE_LABELS[log.sync_type] || log.sync_type}</td>
                                            <td className="px-3 py-2 text-center">
                                                <StatusBadge variant={STATUS_VARIANT[log.status] || 'danger'} size="sm">
                                                    {STATUS_LABEL[log.status] || log.status}
                                                </StatusBadge>
                                            </td>
                                            <td className="px-3 py-2 text-sm text-gray-500 whitespace-nowrap">
                                                {log.date_range_start && log.date_range_end ? `${log.date_range_start} - ${log.date_range_end}` : '-'}
                                            </td>
                                            <td className="px-3 py-2 text-sm text-right text-emerald-600 font-medium whitespace-nowrap">{fmt(log.inserted_records)}</td>
                                            <td className="px-3 py-2 text-sm text-right text-amber-600 whitespace-nowrap">{fmt(log.deleted_records)}</td>
                                            <td className="px-3 py-2 text-sm text-right text-gray-500 whitespace-nowrap">{fmtSeconds(log.elapsed_seconds)}</td>
                                            <td className="px-3 py-2 text-sm text-gray-500 whitespace-nowrap truncate max-w-[120px]" title={log.started_by}>{log.started_by}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </StandardModal.Section>

                    {/* Detalhe expandido */}
                    {expandedId && (() => {
                        const log = logs.find(l => l.id === expandedId);
                        if (!log) return null;
                        const delSummary = log.deletion_summary;
                        const errRecords = log.error_records || [];
                        return (
                            <StandardModal.Section title={`Detalhes - Sync #${log.id}`}>
                                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                    <StandardModal.MiniField label="Total" value={fmt(log.total_records)} />
                                    <StandardModal.MiniField label="Processados" value={fmt(log.processed_records)} />
                                    <StandardModal.MiniField label="Inseridos" value={fmt(log.inserted_records)} />
                                    <StandardModal.MiniField label="Removidos" value={fmt(log.deleted_records)} />
                                    <StandardModal.MiniField label="Ignorados" value={fmt(log.skipped_records)} />
                                    <StandardModal.MiniField label="Erros" value={fmt(log.error_count)} />
                                    <StandardModal.MiniField label="Início" value={log.started_at || '-'} />
                                    <StandardModal.MiniField label="Fim" value={log.completed_at || '-'} />
                                </div>

                                {log.error_message && (
                                    <div className="mt-3 p-2 bg-red-50 border border-red-200 rounded text-sm text-red-700">
                                        <strong>Erro fatal:</strong> {log.error_message}
                                    </div>
                                )}

                                {delSummary && delSummary.total > 0 && (
                                    <div className="mt-4">
                                        <h4 className="text-xs font-semibold text-gray-500 uppercase mb-2">
                                            Remoções ({fmt(delSummary.total)} registros)
                                        </h4>
                                        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                                            <SummaryList title="Por Loja" data={delSummary.by_store} />
                                            <SummaryList title="Por Data" data={delSummary.by_date} />
                                            <SummaryList title="Por Tipo" data={delSummary.by_movement_code} />
                                        </div>
                                    </div>
                                )}

                                {errRecords.length > 0 && (
                                    <div className="mt-4">
                                        <h4 className="text-xs font-semibold text-gray-500 uppercase mb-2">
                                            Registros com Erro ({errRecords.length}{log.error_truncated > 0 ? ` + ${log.error_truncated} truncados` : ''})
                                        </h4>
                                        <div className="max-h-48 overflow-y-auto border border-red-200 rounded bg-red-50">
                                            <table className="min-w-full text-xs">
                                                <thead className="bg-red-100 sticky top-0">
                                                    <tr>
                                                        <th className="px-2 py-1 text-left">Data</th>
                                                        <th className="px-2 py-1 text-left">Loja</th>
                                                        <th className="px-2 py-1 text-left">NF</th>
                                                        <th className="px-2 py-1 text-left">Barcode</th>
                                                        <th className="px-2 py-1 text-left">Mensagem</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {errRecords.map((err, i) => (
                                                        <tr key={i} className="border-t border-red-100">
                                                            <td className="px-2 py-1 whitespace-nowrap">{err.record_date || '-'}</td>
                                                            <td className="px-2 py-1 whitespace-nowrap">{err.store || '-'}</td>
                                                            <td className="px-2 py-1 whitespace-nowrap">{err.invoice || '-'}</td>
                                                            <td className="px-2 py-1 whitespace-nowrap">{err.barcode || '-'}</td>
                                                            <td className="px-2 py-1 text-red-700">{err.message}</td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                )}
                            </StandardModal.Section>
                        );
                    })()}
                </>
            )}
        </StandardModal>
    );
}
