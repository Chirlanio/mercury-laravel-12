import { CheckCircleIcon, ExclamationTriangleIcon, DocumentTextIcon } from '@heroicons/react/24/outline';

/**
 * Painel de resultado pós-import.
 *
 * Usado em `Pages/DRE/Imports/{Actuals,Budgets,Chart}.jsx`. Mostra 4
 * contadores (lidas, criadas, atualizadas opcional, puladas) + tabela de
 * erros PT-BR.
 *
 * Props:
 *   - report: DreImportReport.toArray() — {dry_run, total_read, created,
 *     updated, skipped, errors[], budget_version?}
 *   - title: titulo do card.
 *   - showUpdated: mostra coluna de "Atualizadas" (só faz sentido em fluxos
 *     com upsert — actuals via external_id e chart).
 */
export default function ImportReportPanel({ report, title, showUpdated = false }) {
    if (!report) return null;

    const hasErrors = Array.isArray(report.errors) && report.errors.length > 0;
    const succeeded = !hasErrors && (report.created > 0 || report.updated > 0);

    return (
        <div className={`bg-white border rounded-lg p-6 space-y-4 ${
            hasErrors ? 'border-amber-300' : succeeded ? 'border-emerald-300' : 'border-gray-200'
        }`}>
            <div className="flex items-center gap-3">
                {hasErrors ? (
                    <ExclamationTriangleIcon className="h-6 w-6 text-amber-500" />
                ) : succeeded ? (
                    <CheckCircleIcon className="h-6 w-6 text-emerald-500" />
                ) : (
                    <DocumentTextIcon className="h-6 w-6 text-gray-400" />
                )}
                <h2 className="text-lg font-semibold text-gray-900">{title}</h2>
                {report.dry_run && (
                    <span className="text-xs font-medium rounded-full bg-amber-100 text-amber-800 px-2 py-0.5">
                        dry-run
                    </span>
                )}
                {report.budget_version && (
                    <span className="text-xs font-medium rounded-full bg-emerald-100 text-emerald-800 px-2 py-0.5 font-mono">
                        {report.budget_version}
                    </span>
                )}
            </div>

            <div className={`grid ${showUpdated ? 'grid-cols-4' : 'grid-cols-3'} gap-4`}>
                <Counter label="Linhas lidas" value={report.total_read} />
                <Counter label="Criadas" value={report.created} color="emerald" />
                {showUpdated && (
                    <Counter label="Atualizadas" value={report.updated} color="indigo" />
                )}
                <Counter label="Puladas" value={report.skipped} color={report.skipped > 0 ? 'amber' : 'gray'} />
            </div>

            {hasErrors && (
                <div>
                    <h3 className="text-sm font-semibold text-gray-900 mb-2">
                        Erros ({report.errors.length})
                    </h3>
                    <div className="max-h-96 overflow-y-auto rounded border border-gray-200">
                        <table className="min-w-full text-sm">
                            <thead className="bg-gray-50 sticky top-0">
                                <tr>
                                    <th className="px-3 py-2 text-left text-xs font-semibold text-gray-600">Mensagem</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100">
                                {report.errors.map((msg, idx) => (
                                    <tr key={idx} className="hover:bg-amber-50/40">
                                        <td className="px-3 py-2 text-gray-700">{msg}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}
        </div>
    );
}

function Counter({ label, value, color = 'gray' }) {
    const colorMap = {
        gray: 'text-gray-700',
        emerald: 'text-emerald-600',
        indigo: 'text-indigo-600',
        amber: 'text-amber-600',
    };
    return (
        <div className="rounded-md bg-gray-50 p-3 text-center">
            <div className={`text-2xl font-bold tabular-nums ${colorMap[color]}`}>{value ?? 0}</div>
            <div className="text-xs text-gray-500 mt-1">{label}</div>
        </div>
    );
}
