import { useEffect, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import {
    PaperClipIcon,
    PlusIcon,
    TrashIcon,
    PencilSquareIcon,
    PaperAirplaneIcon,
    XMarkIcon,
} from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';
import LoadingSpinner from '@/Components/Shared/LoadingSpinner';
import Button from '@/Components/Button';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import { maskMoney, parseMoney } from '@/Hooks/useMasks';

const COLOR_MAP = {
    success: 'success', warning: 'warning', info: 'info', danger: 'danger',
    purple: 'purple', gray: 'gray', orange: 'orange', teal: 'teal',
};

const emptyItemForm = {
    type_expense_id: '',
    expense_date: '',
    value: '',
    description: '',
    invoice_number: '',
    attachment: null,
};

export default function AccountabilityModal({
    show,
    onClose,
    expense,
    loading = false,
    typeExpenses = [],
    onReload,
    canManageAccountability = false,
    canApprove = false,
}) {
    const [itemForm, setItemForm] = useState(emptyItemForm);
    const [editingItemId, setEditingItemId] = useState(null);
    const [itemErrors, setItemErrors] = useState({});
    const [itemProcessing, setItemProcessing] = useState(false);

    const accStatus = expense?.accountability_status;
    const expenseStatus = expense?.status;
    const canEdit = canManageAccountability
        && expenseStatus === 'approved'
        && ['pending', 'in_progress', 'rejected'].includes(accStatus);

    const canSubmitForApproval = canEdit
        && accStatus === 'in_progress'
        && (expense?.items?.length ?? 0) > 0;

    const canApproveOrReject = canApprove && accStatus === 'submitted';

    // Reset ao abrir/trocar verba
    useEffect(() => {
        if (show) {
            setItemForm(emptyItemForm);
            setEditingItemId(null);
            setItemErrors({});
        }
    }, [show, expense?.ulid]);

    const setField = (key) => (e) => {
        const value = e?.target ? (key === 'attachment' ? e.target.files?.[0] || null : e.target.value) : e;
        setItemForm((p) => ({ ...p, [key]: value }));
    };

    const startEdit = (item) => {
        setEditingItemId(item.id);
        setItemForm({
            type_expense_id: item.type_expense?.id ?? '',
            expense_date: item.expense_date ?? '',
            // Aplica máscara BR no valor decimal vindo do backend
            value: item.value != null ? maskMoney(String(item.value).replace('.', ',')) : '',
            description: item.description ?? '',
            invoice_number: item.invoice_number ?? '',
            attachment: null,
        });
        setItemErrors({});
    };

    const cancelEdit = () => {
        setEditingItemId(null);
        setItemForm(emptyItemForm);
        setItemErrors({});
    };

    const submitItem = (e) => {
        e?.preventDefault?.();
        if (!expense) return;

        // Validações inline antes de bater no backend
        const localErrors = {};

        // Data dentro do intervalo da viagem
        if (itemForm.expense_date && expense.initial_date && expense.end_date) {
            if (itemForm.expense_date < expense.initial_date || itemForm.expense_date > expense.end_date) {
                localErrors.expense_date = `Data deve estar entre ${formatDate(expense.initial_date)} e ${formatDate(expense.end_date)}.`;
            }
        }

        // Valor parseado deve ser positivo
        const parsedValue = parseMoney(itemForm.value);
        if (!parsedValue || parsedValue <= 0) {
            localErrors.value = 'Informe um valor maior que zero.';
        }

        if (Object.keys(localErrors).length > 0) {
            setItemErrors(localErrors);
            return;
        }

        const formData = new FormData();
        formData.append('type_expense_id', itemForm.type_expense_id);
        formData.append('expense_date', itemForm.expense_date);
        formData.append('value', String(parsedValue)); // envia decimal puro
        formData.append('description', itemForm.description);
        if (itemForm.invoice_number) formData.append('invoice_number', itemForm.invoice_number);
        if (itemForm.attachment) formData.append('attachment', itemForm.attachment);

        setItemProcessing(true);
        setItemErrors({});

        const url = editingItemId
            ? route('travel-expenses.items.update', [expense.ulid, editingItemId])
            : route('travel-expenses.items.store', expense.ulid);

        router.post(url, formData, {
            forceFormData: true,
            preserveScroll: true,
            onError: (err) => setItemErrors(err),
            onSuccess: () => {
                setItemForm(emptyItemForm);
                setEditingItemId(null);
                onReload?.();
            },
            onFinish: () => setItemProcessing(false),
        });
    };

    const handleDeleteItem = (item) => {
        if (!confirm(`Remover item "${item.description}"?`)) return;
        router.delete(route('travel-expenses.items.destroy', [expense.ulid, item.id]), {
            preserveScroll: true,
            onSuccess: () => onReload?.(),
        });
    };

    const handleSubmitForApproval = () => {
        if (!confirm('Enviar prestação de contas para aprovação? Após enviar, não será possível adicionar novos itens.')) return;
        router.post(route('travel-expenses.transition', expense.ulid), {
            kind: 'accountability',
            to_status: 'submitted',
            note: 'Prestação enviada para aprovação',
        }, {
            preserveScroll: true,
            onSuccess: () => onReload?.(),
        });
    };

    const handleApproveAccountability = () => {
        if (!confirm('Aprovar a prestação de contas?')) return;
        router.post(route('travel-expenses.transition', expense.ulid), {
            kind: 'accountability',
            to_status: 'approved',
            note: 'Prestação aprovada',
        }, {
            preserveScroll: true,
            onSuccess: () => onReload?.(),
        });
    };

    const handleRejectAccountability = () => {
        const reason = prompt('Motivo da rejeição:');
        if (!reason || !reason.trim()) return;
        router.post(route('travel-expenses.transition', expense.ulid), {
            kind: 'accountability',
            to_status: 'rejected',
            note: reason,
        }, {
            preserveScroll: true,
            onSuccess: () => onReload?.(),
        });
    };

    const totalItems = useMemo(() => {
        return (expense?.items ?? []).reduce((acc, i) => acc + Number(i.value || 0), 0);
    }, [expense]);

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title="Prestação de Contas"
            subtitle={expense ? `${expense.origin} → ${expense.destination}` : ''}
            headerColor="bg-blue-700"
            headerIcon={<PaperClipIcon className="h-6 w-6" />}
            headerBadges={expense ? [
                { text: expense.accountability_status_label },
            ] : []}
            maxWidth="5xl"
            loading={loading}
            footer={(canSubmitForApproval || canApproveOrReject) ? (
                <StandardModal.Footer>
                    <button
                        type="button"
                        onClick={onClose}
                        className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                    >
                        Fechar
                    </button>
                    <div className="flex items-center gap-3 ml-auto">
                        {canSubmitForApproval && (
                            <Button
                                variant="primary"
                                size="md"
                                icon={PaperAirplaneIcon}
                                onClick={handleSubmitForApproval}
                            >
                                Enviar para aprovação
                            </Button>
                        )}
                        {canApproveOrReject && (
                            <>
                                <Button
                                    variant="danger"
                                    size="md"
                                    onClick={handleRejectAccountability}
                                >
                                    Rejeitar / Devolver
                                </Button>
                                <Button
                                    variant="success"
                                    size="md"
                                    onClick={handleApproveAccountability}
                                >
                                    Aprovar prestação
                                </Button>
                            </>
                        )}
                    </div>
                </StandardModal.Footer>
            ) : undefined}
        >
            {!expense ? (
                <div className="flex justify-center py-12"><LoadingSpinner /></div>
            ) : (
                <>
                    {/* Resumo financeiro */}
                    <StandardModal.Section title="Resumo">
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                            <StandardModal.InfoCard label="Verba" value={formatCurrency(expense.value)} highlight />
                            <StandardModal.InfoCard label="Prestado" value={formatCurrency(totalItems)} />
                            <StandardModal.InfoCard
                                label="Saldo"
                                value={formatCurrency(expense.value - totalItems)}
                                colorClass={(expense.value - totalItems) < 0 ? 'text-red-700' : 'text-green-700'}
                            />
                            <StandardModal.InfoCard label="Itens" value={String(expense.items?.length ?? 0)} />
                        </div>
                    </StandardModal.Section>

                    {/* Form de item */}
                    {canEdit && (
                        <StandardModal.Section title={editingItemId ? 'Editar item' : 'Adicionar item'}>
                            <form onSubmit={submitItem}>
                                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                    <div>
                                        <InputLabel htmlFor="type_expense_id" value="Tipo *" />
                                        <select
                                            id="type_expense_id"
                                            value={itemForm.type_expense_id}
                                            onChange={setField('type_expense_id')}
                                            className="w-full mt-1 rounded-md border-gray-300"
                                        >
                                            <option value="">— Selecione —</option>
                                            {typeExpenses.map((t) => (
                                                <option key={t.id} value={t.id}>{t.name}</option>
                                            ))}
                                        </select>
                                        <InputError message={itemErrors.type_expense_id} />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="expense_date" value="Data *" />
                                        <TextInput
                                            id="expense_date"
                                            type="date"
                                            value={itemForm.expense_date}
                                            onChange={setField('expense_date')}
                                            min={expense?.initial_date ?? undefined}
                                            max={expense?.end_date ?? undefined}
                                            className="w-full mt-1"
                                        />
                                        <p className="text-xs text-gray-500 mt-1">
                                            Período da viagem: {formatDate(expense?.initial_date)} a {formatDate(expense?.end_date)}
                                        </p>
                                        <InputError message={itemErrors.expense_date} />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="value" value="Valor (R$) *" />
                                        <TextInput
                                            id="value"
                                            type="text"
                                            inputMode="numeric"
                                            value={itemForm.value}
                                            onChange={(e) => setItemForm((p) => ({ ...p, value: maskMoney(e.target.value) }))}
                                            placeholder="0,00"
                                            className="w-full mt-1"
                                        />
                                        <InputError message={itemErrors.value} />
                                    </div>
                                    <div className="lg:col-span-2">
                                        <InputLabel htmlFor="description" value="Descrição *" />
                                        <TextInput
                                            id="description"
                                            value={itemForm.description}
                                            onChange={setField('description')}
                                            className="w-full mt-1"
                                            placeholder="Ex: Almoço em restaurante X"
                                        />
                                        <InputError message={itemErrors.description} />
                                    </div>
                                    <div>
                                        <InputLabel htmlFor="invoice_number" value="NF/Recibo (opcional)" />
                                        <TextInput
                                            id="invoice_number"
                                            value={itemForm.invoice_number}
                                            onChange={setField('invoice_number')}
                                            className="w-full mt-1"
                                            placeholder="Número"
                                        />
                                        <InputError message={itemErrors.invoice_number} />
                                    </div>
                                    <div className="lg:col-span-3">
                                        <InputLabel htmlFor="attachment" value="Comprovante (PDF, JPG, PNG ou WebP — até 5MB)" />
                                        <input
                                            id="attachment"
                                            type="file"
                                            accept="application/pdf,image/jpeg,image/png,image/webp"
                                            onChange={setField('attachment')}
                                            className="w-full mt-1 text-sm"
                                        />
                                        {itemForm.attachment && (
                                            <p className="text-xs text-gray-500 mt-1">
                                                {itemForm.attachment.name} · {(itemForm.attachment.size / 1024).toFixed(1)} KB
                                            </p>
                                        )}
                                        <InputError message={itemErrors.attachment} />
                                    </div>
                                </div>

                                <div className="mt-4 flex items-center justify-end gap-2">
                                    {editingItemId && (
                                        <Button type="button" variant="outline" size="sm" onClick={cancelEdit}>
                                            Cancelar
                                        </Button>
                                    )}
                                    <Button
                                        type="submit"
                                        variant="primary"
                                        size="sm"
                                        loading={itemProcessing}
                                        icon={editingItemId ? PencilSquareIcon : PlusIcon}
                                    >
                                        {editingItemId ? 'Salvar alterações' : 'Adicionar item'}
                                    </Button>
                                </div>
                            </form>
                        </StandardModal.Section>
                    )}

                    {/* Lista de itens */}
                    <StandardModal.Section title="Itens lançados">
                        {(expense.items?.length ?? 0) === 0 ? (
                            <div className="text-sm text-gray-500 text-center py-6 bg-gray-50 rounded">
                                Nenhum item lançado ainda.
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="min-w-full text-sm">
                                    <thead className="bg-gray-50 text-gray-600 uppercase text-xs">
                                        <tr>
                                            <th className="px-3 py-2 text-left">Data</th>
                                            <th className="px-3 py-2 text-left">Tipo</th>
                                            <th className="px-3 py-2 text-left">Descrição</th>
                                            <th className="px-3 py-2 text-left">NF</th>
                                            <th className="px-3 py-2 text-right">Valor</th>
                                            <th className="px-3 py-2 text-center">Comp.</th>
                                            {canEdit && <th className="px-3 py-2 text-right">Ações</th>}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200">
                                        {expense.items.map((item) => (
                                            <tr key={item.id}>
                                                <td className="px-3 py-2 whitespace-nowrap">{formatDate(item.expense_date)}</td>
                                                <td className="px-3 py-2 whitespace-nowrap">{item.type_expense?.name ?? '—'}</td>
                                                <td className="px-3 py-2">{item.description}</td>
                                                <td className="px-3 py-2">{item.invoice_number ?? '—'}</td>
                                                <td className="px-3 py-2 text-right tabular-nums">{formatCurrency(item.value)}</td>
                                                <td className="px-3 py-2 text-center">
                                                    {item.has_attachment ? (
                                                        <a
                                                            href={route('travel-expenses.items.download', [expense.ulid, item.id])}
                                                            className="text-indigo-600 hover:underline inline-flex items-center"
                                                            title={item.attachment_original_name}
                                                        >
                                                            <PaperClipIcon className="h-4 w-4" />
                                                        </a>
                                                    ) : (
                                                        <span className="text-gray-400">—</span>
                                                    )}
                                                </td>
                                                {canEdit && (
                                                    <td className="px-3 py-2 text-right whitespace-nowrap">
                                                        <button
                                                            type="button"
                                                            onClick={() => startEdit(item)}
                                                            className="inline-flex items-center text-amber-600 hover:text-amber-700 mr-2"
                                                            title="Editar"
                                                        >
                                                            <PencilSquareIcon className="h-4 w-4" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => handleDeleteItem(item)}
                                                            className="inline-flex items-center text-red-600 hover:text-red-700"
                                                            title="Remover"
                                                        >
                                                            <TrashIcon className="h-4 w-4" />
                                                        </button>
                                                    </td>
                                                )}
                                            </tr>
                                        ))}
                                    </tbody>
                                    <tfoot className="bg-gray-50 font-semibold">
                                        <tr>
                                            <td colSpan={4} className="px-3 py-2 text-right">Total prestado:</td>
                                            <td className="px-3 py-2 text-right tabular-nums">{formatCurrency(totalItems)}</td>
                                            <td colSpan={canEdit ? 2 : 1} />
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        )}

                        {expense.accountability_rejection_reason && (
                            <div className="mt-3 rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-800">
                                <strong>Devolvida:</strong> {expense.accountability_rejection_reason}
                            </div>
                        )}
                    </StandardModal.Section>

                </>
            )}
        </StandardModal>
    );
}

function formatDate(iso) {
    if (!iso) return '—';
    const d = new Date(`${iso}T00:00:00`);
    return d.toLocaleDateString('pt-BR');
}

function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value || 0);
}
