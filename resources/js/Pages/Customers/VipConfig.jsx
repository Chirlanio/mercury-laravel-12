import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import {
    AdjustmentsHorizontalIcon, ArrowLeftIcon, PlusIcon,
    InformationCircleIcon,
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

export default function VipConfig({ configs, can }) {
    const [editing, setEditing] = useState(null); // null = closed, {} = new, { ...config } = edit
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleting, setDeleting] = useState(false);
    const isNew = editing && !editing.id;

    const { data, setData, post, patch, processing, reset, errors, transform } = useForm({
        year: new Date().getFullYear(),
        tier: 'black',
        min_revenue: '',
        notes: '',
    });

    transform((payload) => ({
        ...payload,
        min_revenue: parseMoney(payload.min_revenue),
    }));

    const handleRevenueChange = (e) => {
        setData('min_revenue', maskMoney(e.target.value));
    };

    const openNew = () => {
        reset();
        setData({
            year: new Date().getFullYear(),
            tier: 'black',
            min_revenue: '',
            notes: '',
        });
        setEditing({});
    };

    const openEdit = (c) => {
        setData({
            year: c.year,
            tier: c.tier,
            min_revenue: maskMoney(Math.round(c.min_revenue * 100)),
            notes: c.notes || '',
        });
        setEditing(c);
    };

    const handleSubmit = () => {
        const options = {
            preserveScroll: true,
            onSuccess: () => { setEditing(null); reset(); },
        };
        if (isNew) {
            post(route('customers.vip.config.store'), options);
        } else {
            patch(route('customers.vip.config.update', editing.id), options);
        }
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
            label: 'Ano',
            className: 'font-medium text-gray-900',
            render: (row) => row.year,
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
                                Faturamento mínimo líquido para entrar em cada tier. Usado pela sugestão automática.
                                Curadoria manual sempre sobrescreve.
                            </p>
                        </div>
                        <div className="flex gap-2 shrink-0">
                            <Link href={route('customers.vip.index')}>
                                <Button variant="outline" icon={ArrowLeftIcon}>
                                    <span className="hidden sm:inline">Voltar</span>
                                </Button>
                            </Link>
                            {can.manage_config && (
                                <Button variant="primary" onClick={openNew} icon={PlusIcon}>
                                    <span className="hidden sm:inline">Novo Limite</span>
                                </Button>
                            )}
                        </div>
                    </div>

                    <DataTable
                        data={configs}
                        columns={columns}
                        searchable={false}
                        emptyMessage="Nenhum limite configurado. Cadastre os valores mínimos por ano antes de rodar a classificação automática."
                    />
                </div>
            </div>

            <StandardModal
                show={editing !== null}
                onClose={() => setEditing(null)}
                title={isNew ? 'Novo limite' : `Editar limite — ${editing?.year} ${TIER_LABEL[editing?.tier] ?? ''}`}
                headerColor="bg-indigo-600"
                headerIcon={<AdjustmentsHorizontalIcon className="w-6 h-6" />}
                maxWidth="2xl"
                onSubmit={handleSubmit}
                footer={
                    <StandardModal.Footer
                        onCancel={() => setEditing(null)}
                        onSubmit="submit"
                        submitLabel={isNew ? 'Criar' : 'Salvar'}
                        processing={processing}
                    />
                }
            >
                <StandardModal.Section title="Valores">
                    <div className="space-y-3">
                        {isNew && (
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <InputLabel value="Ano" />
                                    <TextInput
                                        type="number"
                                        min="2020"
                                        max="2100"
                                        value={data.year}
                                        onChange={(e) => setData('year', e.target.value)}
                                        className="mt-1 block w-full"
                                        required
                                    />
                                    {errors.year && <p className="text-red-600 text-xs mt-1">{errors.year}</p>}
                                </div>
                                <div>
                                    <InputLabel value="Tier" />
                                    <select
                                        value={data.tier}
                                        onChange={(e) => setData('tier', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >
                                        <option value="black">Black</option>
                                        <option value="gold">Gold</option>
                                    </select>
                                    {errors.tier && <p className="text-red-600 text-xs mt-1">{errors.tier}</p>}
                                </div>
                            </div>
                        )}
                        <div>
                            <InputLabel value="Faturamento mínimo" />
                            <div className="relative mt-1">
                                <span className="absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-gray-500 pointer-events-none">
                                    R$
                                </span>
                                <TextInput
                                    type="text"
                                    inputMode="numeric"
                                    value={data.min_revenue}
                                    onChange={handleRevenueChange}
                                    placeholder="15.000,00"
                                    className="block w-full pl-10 text-right font-mono"
                                    required
                                />
                            </div>
                            {errors.min_revenue && <p className="text-red-600 text-xs mt-1">{errors.min_revenue}</p>}
                        </div>

                        <div className="rounded-md bg-indigo-50 border border-indigo-100 p-3 flex gap-2">
                            <InformationCircleIcon className="w-5 h-5 text-indigo-600 shrink-0 mt-0.5" />
                            <div className="text-xs text-indigo-900 space-y-1">
                                <p>
                                    <strong>Programa MS Life:</strong> considera apenas vendas nas lojas da
                                    rede <strong>Meia Sola</strong>. Outras redes do grupo (Arezzo, Schutz,
                                    MS Off, etc.) são excluídas do cálculo.
                                </p>
                                <p>
                                    <strong>Período de apuração:</strong> 01/jan a 31/dez
                                    de <strong>{Number(data.year) - 1}</strong> (ano anterior à lista).
                                    O limite cadastrado para {data.year} será aplicado sobre o faturamento
                                    de {Number(data.year) - 1}.
                                </p>
                                <p>
                                    <strong>Cálculo do faturamento líquido:</strong> soma das vendas
                                    (movimentos código <strong>2</strong>) menos as devoluções
                                    (movimentos código <strong>6</strong> com entrada/saída <strong>E</strong>),
                                    agrupado pelo CPF do cliente.
                                </p>
                                <p className="text-indigo-700">
                                    Clientes cujo total bater este valor serão sugeridos automaticamente para o tier.
                                    Curadoria manual do Marketing sempre prevalece.
                                </p>
                            </div>
                        </div>

                        <div>
                            <InputLabel value="Notas (opcional)" />
                            <textarea
                                value={data.notes}
                                onChange={(e) => setData('notes', e.target.value)}
                                rows={2}
                                maxLength={500}
                                placeholder="Contexto da régua aprovada pela direção…"
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                            />
                            {errors.notes && <p className="text-red-600 text-xs mt-1">{errors.notes}</p>}
                        </div>
                    </div>
                </StandardModal.Section>
            </StandardModal>

            <DeleteConfirmModal
                show={deleteTarget !== null}
                onClose={() => !deleting && setDeleteTarget(null)}
                onConfirm={handleDeleteConfirm}
                itemType="threshold"
                itemName={deleteTarget ? `${TIER_LABEL[deleteTarget.tier]} ${deleteTarget.year}` : ''}
                details={deleteTarget ? [
                    { label: 'Ano', value: String(deleteTarget.year) },
                    { label: 'Tier', value: TIER_LABEL[deleteTarget.tier] },
                    { label: 'Faturamento mínimo', value: fmtCurrency(deleteTarget.min_revenue) },
                ] : []}
                warningMessage="A sugestão automática do ano ficará sem régua para esse tier até você cadastrar um novo valor. Curadorias já registradas não são afetadas."
                processing={deleting}
            />
        </>
    );
}
