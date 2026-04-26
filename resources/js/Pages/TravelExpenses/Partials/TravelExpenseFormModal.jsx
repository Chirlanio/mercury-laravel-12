import { useEffect, useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { PaperAirplaneIcon } from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import { maskCpf } from '@/Hooks/useMasks';

const emptyForm = {
    employee_id: '',
    store_code: '',
    origin: '',
    destination: '',
    initial_date: '',
    end_date: '',
    daily_rate: '',
    description: '',
    client_name: '',
    cpf: '',
    bank_id: '',
    bank_branch: '',
    bank_account: '',
    pix_type_id: '',
    pix_key: '',
    internal_notes: '',
};

/**
 * Modal de criação/edição de verba. Mesmo componente atende create e edit
 * (mode="create"|"edit"). Em edit, recebe `expense` e prefiu os campos.
 */
export default function TravelExpenseFormModal({
    show,
    onClose,
    mode = 'create',
    expense = null,
    selects = {},
    isStoreScoped = false,
    scopedStoreCode = null,
    defaultDailyRate = 100,
    canManage = false,
}) {
    const [form, setForm] = useState(emptyForm);
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);
    const [autoSubmit, setAutoSubmit] = useState(false);

    // Reset/preenchimento quando abre
    useEffect(() => {
        if (!show) return;
        if (mode === 'edit' && expense) {
            setForm({
                employee_id: expense.employee?.id ?? '',
                store_code: expense.store?.code ?? expense.store_code ?? '',
                origin: expense.origin ?? '',
                destination: expense.destination ?? '',
                initial_date: expense.initial_date ?? '',
                end_date: expense.end_date ?? '',
                daily_rate: expense.daily_rate ?? defaultDailyRate,
                description: expense.description ?? '',
                client_name: expense.client_name ?? '',
                cpf: expense.masked_cpf ?? '',
                bank_id: expense.bank?.id ?? '',
                bank_branch: expense.bank_branch ?? '',
                bank_account: expense.bank_account ?? '',
                pix_type_id: expense.pix_type?.id ?? '',
                pix_key: expense.pix_key ?? '',
                internal_notes: expense.internal_notes ?? '',
            });
        } else {
            setForm({
                ...emptyForm,
                store_code: isStoreScoped && scopedStoreCode ? scopedStoreCode : '',
                daily_rate: defaultDailyRate,
            });
        }
        setErrors({});
        setAutoSubmit(false);
    }, [show, mode, expense, defaultDailyRate, isStoreScoped, scopedStoreCode]);

    // Cálculo preview do valor
    const valuePreview = useMemo(() => {
        const rate = parseFloat(form.daily_rate) || 0;
        if (!form.initial_date || !form.end_date) return 0;
        const start = new Date(`${form.initial_date}T00:00:00`);
        const end = new Date(`${form.end_date}T00:00:00`);
        if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end < start) return 0;
        const days = Math.floor((end - start) / 86400000) + 1;
        return rate * days;
    }, [form.daily_rate, form.initial_date, form.end_date]);

    const daysPreview = useMemo(() => {
        if (!form.initial_date || !form.end_date) return 0;
        const start = new Date(`${form.initial_date}T00:00:00`);
        const end = new Date(`${form.end_date}T00:00:00`);
        if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end < start) return 0;
        return Math.floor((end - start) / 86400000) + 1;
    }, [form.initial_date, form.end_date]);

    const setField = (key) => (e) => {
        const value = e?.target ? e.target.value : e;
        setForm((p) => ({ ...p, [key]: value }));
    };

    const handleSubmit = (e) => {
        e?.preventDefault?.();
        setProcessing(true);
        setErrors({});

        const payload = {
            ...form,
            employee_id: form.employee_id || null,
            bank_id: form.bank_id || null,
            pix_type_id: form.pix_type_id || null,
            daily_rate: form.daily_rate || defaultDailyRate,
            auto_submit: mode === 'create' ? autoSubmit : undefined,
        };

        const url = mode === 'create'
            ? route('travel-expenses.store')
            : route('travel-expenses.update', expense.ulid);
        const method = mode === 'create' ? 'post' : 'put';

        router[method](url, payload, {
            preserveScroll: true,
            onError: (err) => setErrors(err),
            onSuccess: () => onClose?.(),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title={mode === 'create' ? 'Nova Verba de Viagem' : 'Editar Verba de Viagem'}
            subtitle={mode === 'create' ? 'Preencha os dados da viagem e do pagamento' : `${expense?.origin ?? ''} → ${expense?.destination ?? ''}`}
            headerColor="bg-indigo-600"
            headerIcon={<PaperAirplaneIcon className="h-6 w-6" />}
            maxWidth="3xl"
            onSubmit={handleSubmit}
            footer={(
                <StandardModal.Footer
                    onCancel={onClose}
                    onSubmit="submit"
                    submitLabel={mode === 'create' && autoSubmit ? 'Salvar e Enviar' : 'Salvar'}
                    processing={processing}
                >
                    {mode === 'create' && (
                        <label className="inline-flex items-center text-sm text-gray-700 gap-2 mr-auto">
                            <input
                                type="checkbox"
                                checked={autoSubmit}
                                onChange={(e) => setAutoSubmit(e.target.checked)}
                                className="rounded border-gray-300"
                            />
                            Enviar para aprovação imediatamente
                        </label>
                    )}
                </StandardModal.Footer>
            )}
        >
            <StandardModal.Section title="Dados da Viagem">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <InputLabel htmlFor="employee_id" value="Beneficiado *" />
                        <select
                            id="employee_id"
                            value={form.employee_id}
                            onChange={setField('employee_id')}
                            className="w-full mt-1 rounded-md border-gray-300 text-sm"
                        >
                            <option value="">Selecione o beneficiado</option>
                            {(selects.employees || []).map((emp) => (
                                <option key={emp.id} value={emp.id}>{emp.name}</option>
                            ))}
                        </select>
                        <InputError message={errors.employee_id} />
                    </div>
                    <div>
                        <InputLabel htmlFor="store_code" value="Loja *" />
                        <select
                            id="store_code"
                            value={form.store_code}
                            onChange={setField('store_code')}
                            disabled={isStoreScoped && !canManage}
                            className="w-full mt-1 rounded-md border-gray-300 text-sm disabled:bg-gray-100"
                        >
                            <option value="">Selecione a loja</option>
                            {(selects.stores || []).map((s) => (
                                <option key={s.id} value={s.code}>{s.code} — {s.name}</option>
                            ))}
                        </select>
                        <InputError message={errors.store_code} />
                    </div>
                    <div>
                        <InputLabel htmlFor="origin" value="Origem *" />
                        <TextInput
                            id="origin"
                            value={form.origin}
                            onChange={setField('origin')}
                            className="w-full mt-1"
                            placeholder="Ex: Fortaleza"
                        />
                        <InputError message={errors.origin} />
                    </div>
                    <div>
                        <InputLabel htmlFor="destination" value="Destino *" />
                        <TextInput
                            id="destination"
                            value={form.destination}
                            onChange={setField('destination')}
                            className="w-full mt-1"
                            placeholder="Ex: Recife"
                        />
                        <InputError message={errors.destination} />
                    </div>
                    <div>
                        <InputLabel htmlFor="initial_date" value="Data de saída *" />
                        <TextInput
                            id="initial_date"
                            type="date"
                            value={form.initial_date}
                            onChange={setField('initial_date')}
                            className="w-full mt-1"
                        />
                        <InputError message={errors.initial_date} />
                    </div>
                    <div>
                        <InputLabel htmlFor="end_date" value="Data de retorno *" />
                        <TextInput
                            id="end_date"
                            type="date"
                            value={form.end_date}
                            onChange={setField('end_date')}
                            className="w-full mt-1"
                        />
                        <InputError message={errors.end_date} />
                    </div>
                    <div>
                        <InputLabel htmlFor="daily_rate" value="Diária (R$)" />
                        <TextInput
                            id="daily_rate"
                            type="number"
                            step="0.01"
                            min="0"
                            value={form.daily_rate}
                            onChange={setField('daily_rate')}
                            className="w-full mt-1"
                        />
                        <InputError message={errors.daily_rate} />
                    </div>
                    <div className="flex flex-col justify-end">
                        <div className="rounded-md bg-indigo-50 border border-indigo-200 px-3 py-2 text-sm">
                            <div className="text-gray-700">
                                {daysPreview} {daysPreview === 1 ? 'dia' : 'dias'}
                            </div>
                            <div className="text-indigo-900 font-semibold tabular-nums">
                                {new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(valuePreview)}
                            </div>
                        </div>
                    </div>
                    <div className="md:col-span-2">
                        <InputLabel htmlFor="description" value="Justificativa / Descrição da viagem *" />
                        <textarea
                            id="description"
                            rows={3}
                            value={form.description}
                            onChange={setField('description')}
                            className="w-full mt-1 rounded-md border-gray-300 text-sm"
                            placeholder="Motivo da viagem, agenda, expectativa de resultado..."
                        />
                        <InputError message={errors.description} />
                    </div>
                    <div className="md:col-span-2">
                        <InputLabel htmlFor="client_name" value="Nome do cliente / contato (opcional)" />
                        <TextInput
                            id="client_name"
                            value={form.client_name}
                            onChange={setField('client_name')}
                            className="w-full mt-1"
                            placeholder="Apenas se for visita externa"
                        />
                    </div>
                </div>
            </StandardModal.Section>

            <StandardModal.Section title="Dados de Pagamento (informe ao menos uma forma)">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="md:col-span-2">
                        <InputLabel htmlFor="cpf" value="CPF do beneficiado (opcional)" />
                        <TextInput
                            id="cpf"
                            value={form.cpf}
                            onChange={(e) => setField('cpf')(maskCpf(e.target.value))}
                            className="w-full mt-1"
                            placeholder="000.000.000-00"
                            maxLength={14}
                        />
                        <InputError message={errors.cpf} />
                    </div>
                </div>

                <div className="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="md:col-span-2">
                        <h4 className="text-sm font-semibold text-gray-700">Conta bancária</h4>
                    </div>
                    <div>
                        <InputLabel htmlFor="bank_id" value="Banco" />
                        <select
                            id="bank_id"
                            value={form.bank_id}
                            onChange={setField('bank_id')}
                            className="w-full mt-1 rounded-md border-gray-300 text-sm"
                        >
                            <option value="">— Selecione —</option>
                            {(selects.banks || []).map((b) => (
                                <option key={b.id} value={b.id}>{b.cod_bank ? `${b.cod_bank} — ` : ''}{b.bank_name}</option>
                            ))}
                        </select>
                        <InputError message={errors.bank_id} />
                    </div>
                    <div>
                        <InputLabel htmlFor="bank_branch" value="Agência" />
                        <TextInput
                            id="bank_branch"
                            value={form.bank_branch}
                            onChange={setField('bank_branch')}
                            className="w-full mt-1"
                            placeholder="0000"
                        />
                        <InputError message={errors.bank_branch} />
                    </div>
                    <div>
                        <InputLabel htmlFor="bank_account" value="Conta corrente" />
                        <TextInput
                            id="bank_account"
                            value={form.bank_account}
                            onChange={setField('bank_account')}
                            className="w-full mt-1"
                            placeholder="00000-0"
                        />
                        <InputError message={errors.bank_account} />
                    </div>
                </div>

                <div className="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div className="md:col-span-2">
                        <h4 className="text-sm font-semibold text-gray-700">Chave PIX</h4>
                    </div>
                    <div>
                        <InputLabel htmlFor="pix_type_id" value="Tipo de chave" />
                        <select
                            id="pix_type_id"
                            value={form.pix_type_id}
                            onChange={setField('pix_type_id')}
                            className="w-full mt-1 rounded-md border-gray-300 text-sm"
                        >
                            <option value="">— Selecione —</option>
                            {(selects.pixTypes || []).map((t) => (
                                <option key={t.id} value={t.id}>{t.name}</option>
                            ))}
                        </select>
                        <InputError message={errors.pix_type_id} />
                    </div>
                    <div>
                        <InputLabel htmlFor="pix_key" value="Chave PIX" />
                        <TextInput
                            id="pix_key"
                            value={form.pix_key}
                            onChange={setField('pix_key')}
                            className="w-full mt-1"
                        />
                        <InputError message={errors.pix_key} />
                    </div>
                </div>
            </StandardModal.Section>

            {canManage && (
                <StandardModal.Section title="Notas Internas (apenas Financeiro)">
                    <textarea
                        rows={2}
                        value={form.internal_notes}
                        onChange={setField('internal_notes')}
                        className="w-full rounded-md border-gray-300 text-sm"
                        placeholder="Observações internas — não visíveis ao solicitante"
                    />
                    <InputError message={errors.internal_notes} />
                </StandardModal.Section>
            )}
        </StandardModal>
    );
}
