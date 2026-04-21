import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    ArrowLeftIcon,
    ArrowUturnLeftIcon,
    ExclamationTriangleIcon,
    TrashIcon,
    InformationCircleIcon,
} from '@heroicons/react/24/outline';
import Button from '@/Components/Button';
import EmptyState from '@/Components/Shared/EmptyState';
import StandardModal from '@/Components/StandardModal';
import { usePermissions } from '@/Hooks/usePermissions';

const BRL = (v) =>
    new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(v || 0));

export default function Trash({ trashed = [] }) {
    const { isSuperAdmin } = usePermissions();
    const canForceDelete = isSuperAdmin();
    const [restoreTarget, setRestoreTarget] = useState(null);
    const [forceTarget, setForceTarget] = useState(null);
    const [processing, setProcessing] = useState(false);

    const handleRestore = () => {
        if (!restoreTarget) return;
        setProcessing(true);
        router.post(route('budgets.restore', restoreTarget.id), {}, {
            onFinish: () => { setProcessing(false); setRestoreTarget(null); },
        });
    };

    const handleForceDelete = () => {
        if (!forceTarget) return;
        setProcessing(true);
        router.delete(route('budgets.force-delete', forceTarget.id), {
            onFinish: () => { setProcessing(false); setForceTarget(null); },
        });
    };

    return (
        <>
            <Head title="Lixeira de orçamentos" />

            <div className="py-8">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="mb-6 flex justify-between items-start">
                        <div>
                            <Link href={route('budgets.index')}
                                className="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1 mb-2">
                                <ArrowLeftIcon className="w-4 h-4" /> Voltar para orçamentos
                            </Link>
                            <h1 className="text-3xl font-bold text-gray-900 flex items-center gap-2">
                                <TrashIcon className="w-7 h-7 text-gray-500" />
                                Lixeira
                            </h1>
                            <p className="mt-1 text-sm text-gray-600">
                                Orçamentos excluídos (soft-delete). Podem ser restaurados ou apagados definitivamente.
                            </p>
                        </div>
                    </div>

                    <div className="mb-4 bg-blue-50 border border-blue-200 rounded-lg p-4 flex items-start gap-3">
                        <InformationCircleIcon className="h-5 w-5 text-blue-600 shrink-0 mt-0.5" />
                        <div className="text-sm text-blue-900">
                            <p><strong>Restaurar</strong> remove a exclusão mas <strong>não torna a versão ativa</strong> — ela fica inativa.
                            Para reativar, faça upload de uma nova versão que a substitua (ou use o mesmo arquivo em "Novo orçamento").</p>
                            {canForceDelete ? (
                                <p className="mt-1"><strong>Excluir definitivamente</strong> apaga fisicamente o registro + itens + histórico. Ação irreversível.</p>
                            ) : (
                                <p className="mt-1 text-xs">Exclusão definitiva é restrita a super admin.</p>
                            )}
                        </div>
                    </div>

                    {trashed.length === 0 ? (
                        <EmptyState
                            title="Lixeira vazia"
                            description="Nenhum orçamento foi excluído recentemente."
                        />
                    ) : (
                        <div className="bg-white shadow rounded-lg overflow-hidden">
                            <table className="min-w-full text-sm">
                                <thead className="bg-gray-50">
                                    <tr className="text-xs uppercase text-gray-500">
                                        <th className="px-4 py-3 text-left">Orçamento</th>
                                        <th className="px-4 py-3 text-left">Área</th>
                                        <th className="px-4 py-3 text-right">Total Anual</th>
                                        <th className="px-4 py-3 text-right">Itens</th>
                                        <th className="px-4 py-3 text-left">Excluído em</th>
                                        <th className="px-4 py-3 text-left">Motivo</th>
                                        <th className="px-4 py-3 text-right w-32">Ações</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200">
                                    {trashed.map((t) => (
                                        <tr key={t.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-3">
                                                <p className="font-medium text-gray-900">
                                                    {t.scope_label} · {t.year}
                                                </p>
                                                <p className="text-xs text-gray-500">
                                                    v{t.version_label} · {t.upload_type === 'novo' ? 'Novo' : 'Ajuste'}
                                                </p>
                                                <p className="text-xs text-gray-500">
                                                    criado {t.created_at} por {t.created_by || '—'}
                                                </p>
                                            </td>
                                            <td className="px-4 py-3 text-gray-700">{t.area?.name || '—'}</td>
                                            <td className="px-4 py-3 text-right font-mono text-gray-900">{BRL(t.total_year)}</td>
                                            <td className="px-4 py-3 text-right text-gray-600">{t.items_count}</td>
                                            <td className="px-4 py-3 text-gray-700">
                                                <p>{t.deleted_at}</p>
                                                <p className="text-xs text-gray-500">por {t.deleted_by || '—'}</p>
                                            </td>
                                            <td className="px-4 py-3 text-gray-600 max-w-sm">
                                                <p className="truncate" title={t.deleted_reason}>{t.deleted_reason || '—'}</p>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex justify-end gap-1">
                                                    <button
                                                        onClick={() => setRestoreTarget(t)}
                                                        className="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-green-700 bg-green-100 rounded hover:bg-green-200"
                                                        title="Restaurar"
                                                    >
                                                        <ArrowUturnLeftIcon className="w-3.5 h-3.5" />
                                                        Restaurar
                                                    </button>
                                                    {canForceDelete && (
                                                        <button
                                                            onClick={() => setForceTarget(t)}
                                                            className="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-red-700 bg-red-100 rounded hover:bg-red-200"
                                                            title="Excluir definitivamente"
                                                        >
                                                            <TrashIcon className="w-3.5 h-3.5" />
                                                            Apagar
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            </div>

            {/* Restore Modal */}
            <StandardModal
                show={restoreTarget !== null}
                onClose={() => setRestoreTarget(null)}
                title="Restaurar versão de orçamento"
                subtitle={restoreTarget ? `${restoreTarget.scope_label} ${restoreTarget.year} · v${restoreTarget.version_label}` : ''}
                headerColor="bg-green-600"
                headerIcon={<ArrowUturnLeftIcon className="h-6 w-6" />}
                maxWidth="md"
                footer={
                    <StandardModal.Footer
                        onCancel={() => setRestoreTarget(null)}
                        onSubmit={handleRestore}
                        submitLabel="Restaurar"
                        submitColor="bg-green-600 hover:bg-green-700"
                        processing={processing}
                    />
                }
            >
                <div className="space-y-3 text-sm">
                    <p className="text-gray-700">A versão volta a ser visível na listagem, mas <strong>não é reativada automaticamente</strong>.</p>
                    <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-yellow-900">
                        <p>Para torná-la a versão ativa de <strong>{restoreTarget?.scope_label} {restoreTarget?.year}</strong>, faça um novo upload do orçamento (que desativa a anterior automaticamente).</p>
                    </div>
                </div>
            </StandardModal>

            {/* Force Delete Modal */}
            <StandardModal
                show={forceTarget !== null}
                onClose={() => setForceTarget(null)}
                title="Excluir definitivamente"
                subtitle={forceTarget ? `${forceTarget.scope_label} ${forceTarget.year} · v${forceTarget.version_label}` : ''}
                headerColor="bg-red-600"
                headerIcon={<ExclamationTriangleIcon className="h-6 w-6" />}
                maxWidth="md"
                footer={
                    <StandardModal.Footer
                        onCancel={() => setForceTarget(null)}
                        onSubmit={handleForceDelete}
                        submitLabel="Apagar permanentemente"
                        submitColor="bg-red-600 hover:bg-red-700"
                        processing={processing}
                    />
                }
            >
                <div className="space-y-3 text-sm">
                    <div className="bg-red-50 border border-red-200 rounded-lg p-4 flex items-start gap-2 text-red-900">
                        <ExclamationTriangleIcon className="h-5 w-5 text-red-600 shrink-0 mt-0.5" />
                        <div>
                            <p className="font-semibold mb-1">Ação irreversível.</p>
                            <ul className="text-xs space-y-0.5 list-disc list-inside">
                                <li>{forceTarget?.items_count} linhas do orçamento serão apagadas</li>
                                <li>O histórico de transições desta versão será apagado</li>
                                <li>Não há como desfazer nem via banco de dados</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </StandardModal>
        </>
    );
}
