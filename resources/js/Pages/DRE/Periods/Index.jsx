import { Head, useForm, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import axios from 'axios';
import {
    LockClosedIcon,
    LockOpenIcon,
    ArrowPathIcon,
    ExclamationTriangleIcon,
    CheckCircleIcon,
} from '@heroicons/react/24/outline';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Button from '@/Components/Button';
import DataTable from '@/Components/DataTable';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';
import LoadingSpinner from '@/Components/Shared/LoadingSpinner';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';

/**
 * Tela de fechamentos de período da DRE.
 *
 * Permite fechar o próximo período (confirmar via modal) ou reabrir o
 * último fechamento ativo (com preview dos diffs e justificativa
 * obrigatória). Decisões visuais:
 *   - Sem paginação: limite 50 no backend — histórico recente é o que importa.
 *   - "Ativo" = reopened_at NULL.
 *   - Preview dos diffs carregado via axios no clique de reabrir; evita re-render
 *     da página inteira antes do usuário confirmar.
 */
export default function Index({ periods, lastActiveId, lastClosedUpTo }) {
    const [closeModalOpen, setCloseModalOpen] = useState(false);
    const [reopenTarget, setReopenTarget] = useState(null);
    const [preview, setPreview] = useState(null);
    const [previewLoading, setPreviewLoading] = useState(false);

    const closeForm = useForm({
        closed_up_to_date: defaultNextDate(lastClosedUpTo),
        notes: '',
    });

    const reopenForm = useForm({
        reason: '',
    });

    const handleClose = (e) => {
        e.preventDefault();
        closeForm.post(route('dre.periods.store'), {
            preserveScroll: true,
            onSuccess: () => {
                setCloseModalOpen(false);
                closeForm.reset();
            },
        });
    };

    const startReopen = async (period) => {
        setReopenTarget(period);
        setPreviewLoading(true);
        setPreview(null);

        try {
            const { data } = await axios.get(route('dre.periods.preview', period.id));
            setPreview(data);
        } catch (err) {
            setPreview({ diffs: [], diffs_count: 0, _error: err.message });
        } finally {
            setPreviewLoading(false);
        }
    };

    const submitReopen = (e) => {
        e.preventDefault();
        if (!reopenTarget) return;
        reopenForm.patch(route('dre.periods.reopen', reopenTarget.id), {
            preserveScroll: true,
            onSuccess: () => {
                setReopenTarget(null);
                setPreview(null);
                reopenForm.reset();
            },
        });
    };

    const columns = useMemo(
        () => [
            {
                key: 'closed_up_to_date',
                label: 'Fechado até',
                render: (p) => (
                    <span className="font-mono text-sm">
                        {p.closed_up_to_date ? formatDate(p.closed_up_to_date) : '—'}
                    </span>
                ),
            },
            {
                key: 'status',
                label: 'Status',
                render: (p) =>
                    p.is_active ? (
                        <StatusBadge variant="success" icon={<LockClosedIcon className="h-3 w-3" />}>
                            Ativo
                        </StatusBadge>
                    ) : (
                        <StatusBadge variant="warning" icon={<LockOpenIcon className="h-3 w-3" />}>
                            Reaberto
                        </StatusBadge>
                    ),
            },
            {
                key: 'closed_by',
                label: 'Fechado por',
                render: (p) => (
                    <div>
                        <div className="text-sm">{p.closed_by ?? '—'}</div>
                        <div className="text-xs text-gray-500">
                            {p.closed_at ? formatDateTime(p.closed_at) : '—'}
                        </div>
                    </div>
                ),
            },
            {
                key: 'reopened',
                label: 'Reabertura',
                render: (p) =>
                    p.reopened_at ? (
                        <div className="text-xs">
                            <div>{p.reopened_by}</div>
                            <div className="text-gray-500">{formatDateTime(p.reopened_at)}</div>
                            <div className="text-gray-600 italic truncate max-w-xs" title={p.reopen_reason}>
                                {p.reopen_reason}
                            </div>
                        </div>
                    ) : (
                        <span className="text-gray-400 text-xs">—</span>
                    ),
            },
            {
                key: 'actions',
                label: 'Ações',
                render: (p) =>
                    p.is_active && p.id === lastActiveId ? (
                        <Button
                            variant="outline"
                            size="xs"
                            icon={<LockOpenIcon className="h-3 w-3" />}
                            onClick={() => startReopen(p)}
                        >
                            Reabrir
                        </Button>
                    ) : (
                        <span className="text-gray-300 text-xs">—</span>
                    ),
            },
        ],
        [lastActiveId],
    );

    return (
        <AuthenticatedLayout>
            <Head title="Fechamentos DRE" />

            <div className="py-10">
                <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">Fechamentos DRE</h1>
                            <p className="text-sm text-gray-600 mt-1">
                                Fechar um período gera um snapshot imutável. Lançamentos retroativos
                                continuam sendo projetados, mas não alteram a matriz do mês fechado —
                                a tela continua mostrando o que foi "assinado" naquela data.
                            </p>
                            {lastClosedUpTo && (
                                <p className="text-sm mt-1">
                                    <strong>Último fechamento ativo:</strong>{' '}
                                    <span className="font-mono">{formatDate(lastClosedUpTo)}</span>
                                </p>
                            )}
                        </div>
                        <Button
                            variant="primary"
                            icon={<LockClosedIcon className="h-4 w-4" />}
                            onClick={() => setCloseModalOpen(true)}
                        >
                            Fechar período
                        </Button>
                    </div>

                    <DataTable columns={columns} data={periods} emptyMessage="Nenhum fechamento ainda." />
                </div>
            </div>

            {/* Modal — Fechar */}
            <StandardModal
                show={closeModalOpen}
                onClose={() => setCloseModalOpen(false)}
                title="Fechar período"
                headerColor="bg-indigo-600"
                headerIcon={<LockClosedIcon className="h-5 w-5 text-white" />}
                onSubmit={handleClose}
                footer={
                    <StandardModal.Footer
                        onCancel={() => setCloseModalOpen(false)}
                        onSubmit="submit"
                        submitLabel="Fechar"
                        processing={closeForm.processing}
                    />
                }
            >
                <StandardModal.Section title="Data do fechamento">
                    <p className="text-sm text-gray-700 mb-3">
                        Isso criará snapshots imutáveis até a data informada. Você pode reabrir depois,
                        mas a reabertura exige justificativa.
                    </p>
                    <div>
                        <InputLabel htmlFor="closed_up_to_date" value="Fechado até (inclusivo)" />
                        <TextInput
                            id="closed_up_to_date"
                            type="date"
                            className="mt-1 block w-full"
                            value={closeForm.data.closed_up_to_date}
                            onChange={(e) => closeForm.setData('closed_up_to_date', e.target.value)}
                        />
                        <InputError message={closeForm.errors.closed_up_to_date} className="mt-1" />
                    </div>
                    <div className="mt-3">
                        <InputLabel htmlFor="notes" value="Notas (opcional)" />
                        <textarea
                            id="notes"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            rows={2}
                            maxLength={1000}
                            value={closeForm.data.notes}
                            onChange={(e) => closeForm.setData('notes', e.target.value)}
                        />
                        <InputError message={closeForm.errors.notes} className="mt-1" />
                    </div>
                </StandardModal.Section>
            </StandardModal>

            {/* Modal — Reabrir */}
            <StandardModal
                show={reopenTarget !== null}
                onClose={() => {
                    setReopenTarget(null);
                    setPreview(null);
                    reopenForm.reset();
                }}
                title="Reabrir fechamento"
                subtitle={reopenTarget ? `Período até ${formatDate(reopenTarget.closed_up_to_date)}` : ''}
                headerColor="bg-amber-600"
                headerIcon={<LockOpenIcon className="h-5 w-5 text-white" />}
                onSubmit={submitReopen}
                footer={
                    <StandardModal.Footer
                        onCancel={() => {
                            setReopenTarget(null);
                            setPreview(null);
                            reopenForm.reset();
                        }}
                        onSubmit="submit"
                        submitLabel="Confirmar reabertura"
                        processing={reopenForm.processing}
                    />
                }
            >
                <StandardModal.Section title="Diferenças desde o fechamento">
                    {previewLoading && (
                        <div className="flex justify-center py-6">
                            <LoadingSpinner />
                        </div>
                    )}

                    {!previewLoading && preview && (
                        <>
                            {preview.diffs_count === 0 ? (
                                <div className="flex items-center gap-2 rounded-md bg-emerald-50 border border-emerald-200 p-3 text-sm text-emerald-800">
                                    <CheckCircleIcon className="h-5 w-5" />
                                    <span>
                                        Nenhuma diferença — a matriz live bate com o snapshot. Reabrir
                                        aqui é seguro.
                                    </span>
                                </div>
                            ) : (
                                <>
                                    <div className="flex items-start gap-2 rounded-md bg-amber-50 border border-amber-200 p-3 text-sm text-amber-800 mb-3">
                                        <ExclamationTriangleIcon className="h-5 w-5 flex-shrink-0 mt-0.5" />
                                        <span>
                                            Encontradas <strong>{preview.diffs_count}</strong> diferenças entre
                                            o snapshot e a matriz atual. Após reabrir, o valor live vira oficial
                                            até um novo fechamento.
                                        </span>
                                    </div>

                                    <div className="max-h-80 overflow-y-auto rounded border border-gray-200">
                                        <table className="min-w-full text-xs">
                                            <thead className="bg-gray-50 sticky top-0">
                                                <tr>
                                                    <th className="px-3 py-2 text-left">Escopo</th>
                                                    <th className="px-3 py-2 text-left">Período</th>
                                                    <th className="px-3 py-2 text-left">Linha</th>
                                                    <th className="px-3 py-2 text-right">Snapshot</th>
                                                    <th className="px-3 py-2 text-right">Atual</th>
                                                    <th className="px-3 py-2 text-right">Δ</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-gray-100">
                                                {preview.diffs.slice(0, 50).map((d, idx) => (
                                                    <tr key={idx}>
                                                        <td className="px-3 py-1 font-mono">
                                                            {d.scope}
                                                            {d.scope_id ? ` #${d.scope_id}` : ''}
                                                        </td>
                                                        <td className="px-3 py-1 font-mono">{d.year_month}</td>
                                                        <td className="px-3 py-1">
                                                            <span className="font-mono text-[10px] text-gray-500 mr-1">
                                                                {d.line_code}
                                                            </span>
                                                            {d.line_name}
                                                        </td>
                                                        <td className="px-3 py-1 text-right tabular-nums">
                                                            {formatBrl(d.snapshot_actual)}
                                                        </td>
                                                        <td className="px-3 py-1 text-right tabular-nums">
                                                            {formatBrl(d.current_actual)}
                                                        </td>
                                                        <td
                                                            className={`px-3 py-1 text-right tabular-nums font-semibold ${
                                                                Number(d.delta) >= 0
                                                                    ? 'text-emerald-700'
                                                                    : 'text-red-700'
                                                            }`}
                                                        >
                                                            {formatBrl(d.delta)}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                        {preview.diffs.length > 50 && (
                                            <div className="p-2 text-xs text-gray-500 bg-gray-50">
                                                + {preview.diffs.length - 50} diferenças adicionais (não exibidas).
                                            </div>
                                        )}
                                    </div>
                                </>
                            )}
                        </>
                    )}

                    {!previewLoading && !preview && (
                        <p className="text-sm text-gray-500">Carregando preview…</p>
                    )}
                </StandardModal.Section>

                <StandardModal.Section title="Justificativa (obrigatória)">
                    <textarea
                        id="reason"
                        rows={3}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring-amber-500 text-sm"
                        minLength={10}
                        maxLength={500}
                        placeholder="Ex: erro de lançamento contábil identificado via conciliação, valor incorreto em OP #1234, etc."
                        value={reopenForm.data.reason}
                        onChange={(e) => reopenForm.setData('reason', e.target.value)}
                    />
                    <InputError message={reopenForm.errors.reason} className="mt-1" />
                    <p className="mt-1 text-xs text-gray-500">
                        Mínimo 10 caracteres. Stakeholders com MANAGE_DRE_PERIODS recebem notificação com esta justificativa e os diffs.
                    </p>
                </StandardModal.Section>
            </StandardModal>
        </AuthenticatedLayout>
    );
}

// -----------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------

function defaultNextDate(lastClosedUpTo) {
    if (!lastClosedUpTo) {
        const now = new Date();
        now.setMonth(now.getMonth() - 1);
        return new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().slice(0, 10);
    }
    const [y, m] = lastClosedUpTo.split('-').map((s) => parseInt(s, 10));
    // Próximo fim de mês.
    const nextMonth = m === 12 ? { y: y + 1, m: 1 } : { y, m: m + 1 };
    return new Date(nextMonth.y, nextMonth.m, 0).toISOString().slice(0, 10);
}

function formatDate(iso) {
    if (!iso) return '—';
    const [y, m, d] = iso.slice(0, 10).split('-');
    return `${d}/${m}/${y}`;
}

function formatDateTime(iso) {
    if (!iso) return '—';
    const dt = new Date(iso);
    if (Number.isNaN(dt.getTime())) return iso;
    return dt.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
}

function formatBrl(v) {
    const n = Number(v ?? 0);
    return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}
