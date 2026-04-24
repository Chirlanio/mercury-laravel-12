import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import {
    AdjustmentsHorizontalIcon, ArrowLeftIcon, PlusIcon, TrashIcon, PencilSquareIcon,
} from '@heroicons/react/24/outline';
import { useConfirm } from '@/Hooks/useConfirm';
import Button from '@/Components/Button';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';
import EmptyState from '@/Components/Shared/EmptyState';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';

const fmtCurrency = (v) => new Intl.NumberFormat('pt-BR', {
    style: 'currency', currency: 'BRL',
}).format(Number(v) || 0);

export default function VipConfig({ configs, can }) {
    const { confirm, ConfirmDialogComponent } = useConfirm();
    const [editing, setEditing] = useState(null); // null = closed, {} = new, { ...config } = edit
    const isNew = editing && !editing.id;

    const { data, setData, post, patch, processing, reset, errors } = useForm({
        year: new Date().getFullYear(),
        tier: 'black',
        min_revenue: '',
        notes: '',
    });

    const openNew = () => {
        reset();
        setData({ year: new Date().getFullYear(), tier: 'black', min_revenue: '', notes: '' });
        setEditing({});
    };

    const openEdit = (c) => {
        setData({
            year: c.year,
            tier: c.tier,
            min_revenue: String(c.min_revenue),
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

    const handleDelete = async (c) => {
        const ok = await confirm({
            title: 'Remover threshold?',
            message: `Threshold de ${c.tier.toUpperCase()} em ${c.year} (${fmtCurrency(c.min_revenue)}) será excluído.`,
            confirmText: 'Sim, remover',
            type: 'danger',
        });
        if (!ok) return;
        router.delete(route('customers.vip.config.destroy', c.id), { preserveScroll: true });
    };

    return (
        <>
            <Head title="Thresholds VIP" />

            <div className="py-6 sm:py-12">
                <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-center">
                        <div>
                            <h1 className="text-2xl sm:text-3xl font-bold text-gray-900 flex items-center gap-2">
                                <AdjustmentsHorizontalIcon className="h-7 w-7 text-indigo-600" />
                                Thresholds VIP por ano
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
                                    <span className="hidden sm:inline">Novo threshold</span>
                                </Button>
                            )}
                        </div>
                    </div>

                    <div className="bg-white rounded-lg shadow-sm overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ano</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tier</th>
                                        <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Faturamento mínimo</th>
                                        <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Notas</th>
                                        <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200">
                                    {configs.length > 0 ? configs.map((c) => (
                                        <tr key={c.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-3 text-sm text-gray-900 font-medium">{c.year}</td>
                                            <td className="px-4 py-3">
                                                <StatusBadge color={c.tier === 'black' ? 'dark' : 'warning'}>
                                                    {c.tier === 'black' ? 'Black' : 'Gold'}
                                                </StatusBadge>
                                            </td>
                                            <td className="px-4 py-3 text-right font-mono text-sm text-gray-900">
                                                {fmtCurrency(c.min_revenue)}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-gray-600 max-w-md truncate" title={c.notes}>
                                                {c.notes || '—'}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                {can.manage_config && (
                                                    <div className="flex gap-1 justify-end">
                                                        <button onClick={() => openEdit(c)} className="p-1 text-gray-600 hover:text-gray-900" title="Editar">
                                                            <PencilSquareIcon className="w-5 h-5" />
                                                        </button>
                                                        <button onClick={() => handleDelete(c)} className="p-1 text-red-600 hover:text-red-800" title="Excluir">
                                                            <TrashIcon className="w-5 h-5" />
                                                        </button>
                                                    </div>
                                                )}
                                            </td>
                                        </tr>
                                    )) : (
                                        <tr>
                                            <td colSpan={5} className="p-8">
                                                <EmptyState
                                                    title="Nenhum threshold configurado"
                                                    description="Cadastre os valores mínimos por ano antes de rodar a classificação automática."
                                                    icon={AdjustmentsHorizontalIcon}
                                                />
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <StandardModal
                show={editing !== null}
                onClose={() => setEditing(null)}
                title={isNew ? 'Novo threshold' : `Editar threshold — ${editing?.year} ${editing?.tier?.toUpperCase()}`}
                headerColor="bg-indigo-600"
                headerIcon={<AdjustmentsHorizontalIcon className="w-6 h-6" />}
                maxWidth="lg"
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
                            <InputLabel value="Faturamento mínimo (R$)" />
                            <TextInput
                                type="number"
                                step="0.01"
                                min="0"
                                value={data.min_revenue}
                                onChange={(e) => setData('min_revenue', e.target.value)}
                                placeholder="Ex: 15000.00"
                                className="mt-1 block w-full"
                                required
                            />
                            {errors.min_revenue && <p className="text-red-600 text-xs mt-1">{errors.min_revenue}</p>}
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

            <ConfirmDialogComponent />
        </>
    );
}
