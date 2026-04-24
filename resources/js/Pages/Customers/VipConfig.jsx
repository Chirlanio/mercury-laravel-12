import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import {
    AdjustmentsHorizontalIcon, ArrowLeftIcon, PlusIcon,
    InformationCircleIcon, CheckCircleIcon, ExclamationTriangleIcon,
} from '@heroicons/react/24/outline';
import { maskMoney, parseMoney } from '@/Hooks/useMasks';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import DataTable from '@/Components/DataTable';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';
import DeleteConfirmModal from '@/Components/Shared/DeleteConfirmModal';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';

const fmtCurrency = (v) => new Intl.NumberFormat('pt-BR', {
    style: 'currency', currency: 'BRL',
}).format(Number(v) || 0);

const TIER_LABEL = { black: 'Black', gold: 'Gold' };

export default function VipConfig({ configs, availableYears = [], can }) {
    const [yearForm, setYearForm] = useState(null); // null = closed; { year? } = open
    const [editing, setEditing] = useState(null); // null = closed; { ...config } = edit existing
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleting, setDeleting] = useState(false);

    // Agrupa configs por ano para indicar status (Black/Gold completo, parcial, vazio)
    const yearsStatus = useMemo(() => {
        const items = configs.data || configs;
        const byYear = {};
        items.forEach((c) => {
            byYear[c.year] = byYear[c.year] || { year: c.year, black: null, gold: null };
            byYear[c.year][c.tier] = c;
        });
        return Object.values(byYear).sort((a, b) => b.year - a.year);
    }, [configs]);

    // -------- Form: cadastrar par Black + Gold de um ano --------
    const yearForm_ = useForm({
        year: new Date().getFullYear(),
        black_min_revenue: '',
        gold_min_revenue: '',
        notes: '',
    });

    yearForm_.transform((payload) => ({
        ...payload,
        black_min_revenue: parseMoney(payload.black_min_revenue),
        gold_min_revenue: parseMoney(payload.gold_min_revenue),
    }));

    const openYearForm = (year = null) => {
        // Default: primeiro ano disponível sem régua, ou ano atual
        const incompleteYear = yearsStatus.find((y) => !y.black || !y.gold)?.year;
        const fallback = availableYears[0] ?? new Date().getFullYear();
        const targetYear = year ?? incompleteYear ?? fallback;

        const existing = yearsStatus.find((y) => y.year === targetYear);
        yearForm_.setData({
            year: targetYear,
            black_min_revenue: existing?.black ? maskMoney(Math.round(existing.black.min_revenue * 100)) : '',
            gold_min_revenue: existing?.gold ? maskMoney(Math.round(existing.gold.min_revenue * 100)) : '',
            notes: existing?.black?.notes || existing?.gold?.notes || '',
        });
        setYearForm({ year: targetYear });
    };

    const handleYearChange = (newYear) => {
        const y = Number(newYear);
        const existing = yearsStatus.find((s) => s.year === y);
        yearForm_.setData({
            ...yearForm_.data,
            year: y,
            black_min_revenue: existing?.black ? maskMoney(Math.round(existing.black.min_revenue * 100)) : '',
            gold_min_revenue: existing?.gold ? maskMoney(Math.round(existing.gold.min_revenue * 100)) : '',
            notes: existing?.black?.notes || existing?.gold?.notes || '',
        });
    };

    const handleYearSubmit = () => {
        yearForm_.post(route('customers.vip.config.store_year'), {
            preserveScroll: true,
            onSuccess: () => { setYearForm(null); yearForm_.reset(); },
        });
    };

    // -------- Form: editar individual (PATCH) --------
    const editForm = useForm({
        min_revenue: '',
        notes: '',
    });

    editForm.transform((payload) => ({
        ...payload,
        min_revenue: parseMoney(payload.min_revenue),
    }));

    const openEdit = (c) => {
        editForm.setData({
            min_revenue: maskMoney(Math.round(c.min_revenue * 100)),
            notes: c.notes || '',
        });
        setEditing(c);
    };

    const handleEditSubmit = () => {
        editForm.patch(route('customers.vip.config.update', editing.id), {
            preserveScroll: true,
            onSuccess: () => { setEditing(null); editForm.reset(); },
        });
    };

    const handleDeleteConfirm = () => {
        if (!deleteTarget) return;
        setDeleting(true);
        router.delete(route('customers.vip.config.destroy', deleteTarget.id), {
            preserveScroll: true,
            onFinish: () => {
                setDeleting(false);
                setDeleteTarget(null);
            },
        });
    };

    const columns = [
        {
            key: 'year',
            label: 'Lista',
            className: 'font-medium text-gray-900',
            render: (row) => (
                <div>
                    <div className="font-semibold">{row.year}</div>
                    <div className="text-xs text-gray-500">faturamento {row.year - 1}</div>
                </div>
            ),
        },
        {
            key: 'tier',
            label: 'Tier',
            render: (row) => (
                <StatusBadge color={row.tier === 'black' ? 'dark' : 'warning'}>
                    {TIER_LABEL[row.tier]}
                </StatusBadge>
            ),
        },
        {
            key: 'min_revenue',
            label: 'Faturamento mínimo',
            className: 'text-right font-mono',
            render: (row) => fmtCurrency(row.min_revenue),
        },
        {
            key: 'notes',
            label: 'Notas',
            className: 'text-gray-600 max-w-md truncate',
            render: (row) => row.notes || '—',
        },
        {
            key: 'actions',
            label: '',
            className: 'text-right',
            render: (row) => (
                <ActionButtons
                    onEdit={can.manage_config ? () => openEdit(row) : null}
                    onDelete={can.manage_config ? () => setDeleteTarget(row) : null}
                />
            ),
        },
    ];

    return (
        <>
            <Head title="MS Life - Limites" />

            <div className="py-6 sm:py-12">
                <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-center">
                        <div>
                            <h1 className="text-2xl sm:text-3xl font-bold text-gray-900 flex items-center gap-2">
                                <AdjustmentsHorizontalIcon className="h-7 w-7 text-indigo-600" />
                                Limites MS Life por ano
                            </h1>
                            <p className="mt-1 text-sm text-gray-600">
                                Faturamento mínimo líquido para entrar em cada tier. Cada Lista usa o
                                faturamento do ano <strong>anterior</strong> (Lista 2026 ↔ vendas de 2025).
                            </p>
                        </div>
                        <div className="flex gap-2 shrink-0">
                            <Link href={route('customers.vip.index')}>
                                <Button variant="outline" icon={ArrowLeftIcon}>
                                    <span className="hidden sm:inline">Voltar</span>
                                </Button>
                            </Link>
                            {can.manage_config && (
                                <Button variant="primary" onClick={() => openYearForm()} icon={PlusIcon}>
                                    <span className="hidden sm:inline">Cadastrar limites do ano</span>
                                </Button>
                            )}
                        </div>
                    </div>

                    {/* Status por ano — atalho visual */}
                    {yearsStatus.length > 0 && (
                        <div className="mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            {yearsStatus.map((y) => {
                                const complete = y.black && y.gold;
                                return (
                                    <div
                                        key={y.year}
                                        className={`rounded-lg border p-3 ${complete ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'}`}
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="flex-1">
                                                <div className="flex items-center gap-2">
                                                    {complete ? (
                                                        <CheckCircleIcon className="w-5 h-5 text-emerald-600" />
                                                    ) : (
                                                        <ExclamationTriangleIcon className="w-5 h-5 text-amber-600" />
                                                    )}
                                                    <span className="font-semibold text-gray-900">Lista {y.year}</span>
                                                </div>
                                                <div className="mt-1 text-xs text-gray-600">
                                                    apurada sobre faturamento {y.year - 1}
                                                </div>
                                                <div className="mt-2 text-xs space-y-0.5">
                                                    <div>
                                                        <span className="text-gray-700">Black:</span>{' '}
                                                        <span className={y.black ? 'font-mono font-medium text-gray-900' : 'text-amber-700 italic'}>
                                                            {y.black ? fmtCurrency(y.black.min_revenue) : 'não cadastrado'}
                                                        </span>
                                                    </div>
                                                    <div>
                                                        <span className="text-gray-700">Gold:</span>{' '}
                                                        <span className={y.gold ? 'font-mono font-medium text-gray-900' : 'text-amber-700 italic'}>
                                                            {y.gold ? fmtCurrency(y.gold.min_revenue) : 'não cadastrado'}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            {can.manage_config && (
                                                <button
                                                    type="button"
                                                    onClick={() => openYearForm(y.year)}
                                                    className="text-xs text-indigo-600 hover:text-indigo-800 hover:underline shrink-0"
                                                >
                                                    {complete ? 'Editar' : 'Completar'}
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}

                    <DataTable
                        data={configs}
                        columns={columns}
                        searchable={false}
                        emptyMessage="Nenhum limite configurado. Cadastre os valores mínimos por ano antes de rodar a classificação automática."
                    />
                </div>
            </div>

            {/* Modal: cadastrar/editar par Black + Gold de um ano */}
            <StandardModal
                show={yearForm !== null}
                onClose={() => setYearForm(null)}
                title={`Limites da Lista ${yearForm_.data.year ?? ''}`}
                subtitle={`Apurada sobre faturamento de ${Number(yearForm_.data.year) - 1}`}
                headerColor="bg-indigo-600"
                headerIcon={<AdjustmentsHorizontalIcon className="w-6 h-6" />}
                maxWidth="2xl"
                onSubmit={handleYearSubmit}
                footer={
                    <StandardModal.Footer
                        onCancel={() => setYearForm(null)}
                        onSubmit="submit"
                        submitLabel="Salvar limites"
                        processing={yearForm_.processing}
                    />
                }
            >
                <StandardModal.Section title="Ano da Lista VIP">
                    <div className="space-y-3">
                        <div>
                            <InputLabel value="Ano da Lista" />
                            <select
                                value={yearForm_.data.year}
                                onChange={(e) => handleYearChange(e.target.value)}
                                className="mt-1 block w-48 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                                {availableYears.map((y) => {
                                    const status = yearsStatus.find((s) => s.year === y);
                                    const tag = status?.black && status?.gold
                                        ? '· editar'
                                        : status?.black || status?.gold
                                            ? '· completar'
                                            : '· novo';
                                    return (
                                        <option key={y} value={y}>
                                            Lista {y} {tag}
                                        </option>
                                    );
                                })}
                            </select>
                            <p className="mt-1 text-xs text-gray-500">
                                Os limites serão aplicados sobre o faturamento de {Number(yearForm_.data.year) - 1}.
                            </p>
                            {yearForm_.errors.year && <p className="text-red-600 text-xs mt-1">{yearForm_.errors.year}</p>}
                        </div>
                    </div>
                </StandardModal.Section>

                <StandardModal.Section title="Faturamento mínimo">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <InputLabel>
                                <span className="inline-flex items-center gap-2">
                                    <StatusBadge color="dark" size="xs">Black</StatusBadge>
                                    <span>Limite mínimo</span>
                                </span>
                            </InputLabel>
                            <div className="relative mt-1">
                                <span className="absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-gray-500 pointer-events-none">R$</span>
                                <TextInput
                                    type="text"
                                    inputMode="numeric"
                                    value={yearForm_.data.black_min_revenue}
                                    onChange={(e) => yearForm_.setData('black_min_revenue', maskMoney(e.target.value))}
                                    placeholder="20.000,00"
                                    className="block w-full pl-10 text-right font-mono"
                                    required
                                />
                            </div>
                            {yearForm_.errors.black_min_revenue && (
                                <p className="text-red-600 text-xs mt-1">{yearForm_.errors.black_min_revenue}</p>
                            )}
                        </div>
                        <div>
                            <InputLabel>
                                <span className="inline-flex items-center gap-2">
                                    <StatusBadge color="warning" size="xs">Gold</StatusBadge>
                                    <span>Limite mínimo</span>
                                </span>
                            </InputLabel>
                            <div className="relative mt-1">
                                <span className="absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-gray-500 pointer-events-none">R$</span>
                                <TextInput
                                    type="text"
                                    inputMode="numeric"
                                    value={yearForm_.data.gold_min_revenue}
                                    onChange={(e) => yearForm_.setData('gold_min_revenue', maskMoney(e.target.value))}
                                    placeholder="8.000,00"
                                    className="block w-full pl-10 text-right font-mono"
                                    required
                                />
                            </div>
                            {yearForm_.errors.gold_min_revenue && (
                                <p className="text-red-600 text-xs mt-1">{yearForm_.errors.gold_min_revenue}</p>
                            )}
                        </div>
                    </div>
                    <p className="mt-2 text-xs text-gray-500">
                        Black deve ser maior ou igual ao Gold (régua mais alta = tier superior).
                    </p>
                </StandardModal.Section>

                <StandardModal.Section title="Como o cálculo funciona">
                    <div className="rounded-md bg-indigo-50 border border-indigo-100 p-3 flex gap-2">
                        <InformationCircleIcon className="w-5 h-5 text-indigo-600 shrink-0 mt-0.5" />
                        <div className="text-xs text-indigo-900 space-y-1">
                            <p>
                                <strong>Programa MS Life:</strong> apenas vendas em lojas da rede{' '}
                                <strong>Meia Sola</strong> contam. Outras redes do grupo (Arezzo, Schutz,
                                MS Off, etc.) são excluídas.
                            </p>
                            <p>
                                <strong>Faturamento líquido</strong> por CPF no ano de apuração:
                                soma das vendas (movimentos código <strong>2</strong>) menos as
                                devoluções (movimentos código <strong>6</strong> com entrada/saída <strong>E</strong>).
                            </p>
                            <p className="text-indigo-700">
                                Quem bater Black entra como Black; quem bater Gold mas não Black entra
                                como Gold; quem ficar abaixo de Gold não é sugerido. Curadoria manual
                                do Marketing sempre prevalece.
                            </p>
                        </div>
                    </div>
                </StandardModal.Section>

                <StandardModal.Section title="Notas (opcional)">
                    <textarea
                        value={yearForm_.data.notes}
                        onChange={(e) => yearForm_.setData('notes', e.target.value)}
                        rows={2}
                        maxLength={500}
                        placeholder="Contexto da régua aprovada pela direção…"
                        className="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                    />
                    {yearForm_.errors.notes && <p className="text-red-600 text-xs mt-1">{yearForm_.errors.notes}</p>}
                </StandardModal.Section>
            </StandardModal>

            {/* Modal: editar 1 entrada existente (PATCH) */}
            <StandardModal
                show={editing !== null}
                onClose={() => setEditing(null)}
                title={`Editar limite — ${editing?.year} ${TIER_LABEL[editing?.tier] ?? ''}`}
                subtitle={`Lista ${editing?.year} (faturamento ${editing?.year - 1})`}
                headerColor="bg-indigo-600"
                headerIcon={<AdjustmentsHorizontalIcon className="w-6 h-6" />}
                maxWidth="lg"
                onSubmit={handleEditSubmit}
                footer={
                    <StandardModal.Footer
                        onCancel={() => setEditing(null)}
                        onSubmit="submit"
                        submitLabel="Salvar"
                        processing={editForm.processing}
                    />
                }
            >
                <StandardModal.Section title="Valor">
                    <div>
                        <InputLabel value="Faturamento mínimo" />
                        <div className="relative mt-1">
                            <span className="absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-gray-500 pointer-events-none">R$</span>
                            <TextInput
                                type="text"
                                inputMode="numeric"
                                value={editForm.data.min_revenue}
                                onChange={(e) => editForm.setData('min_revenue', maskMoney(e.target.value))}
                                className="block w-full pl-10 text-right font-mono"
                                required
                            />
                        </div>
                        {editForm.errors.min_revenue && <p className="text-red-600 text-xs mt-1">{editForm.errors.min_revenue}</p>}
                    </div>
                    <div className="mt-3">
                        <InputLabel value="Notas (opcional)" />
                        <textarea
                            value={editForm.data.notes}
                            onChange={(e) => editForm.setData('notes', e.target.value)}
                            rows={2}
                            maxLength={500}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        />
                    </div>
                </StandardModal.Section>
            </StandardModal>

            <DeleteConfirmModal
                show={deleteTarget !== null}
                onClose={() => !deleting && setDeleteTarget(null)}
                onConfirm={handleDeleteConfirm}
                itemType="limite"
                itemName={deleteTarget ? `${TIER_LABEL[deleteTarget.tier]} da Lista ${deleteTarget.year}` : ''}
                details={deleteTarget ? [
                    { label: 'Lista', value: String(deleteTarget.year) },
                    { label: 'Tier', value: TIER_LABEL[deleteTarget.tier] },
                    { label: 'Faturamento mínimo', value: fmtCurrency(deleteTarget.min_revenue) },
                ] : []}
                warningMessage="A sugestão automática do ano ficará sem régua para esse tier até você cadastrar um novo valor. Curadorias já registradas não são afetadas."
                processing={deleting}
            />
        </>
    );
}
