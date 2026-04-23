import { useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import {
    UserIcon,
    ArchiveBoxIcon,
    CheckCircleIcon,
    PlusIcon,
    TrashIcon,
    MagnifyingGlassIcon,
    ExclamationTriangleIcon,
    UserPlusIcon,
    XMarkIcon,
} from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import Button from '@/Components/Button';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import ProductLookupInline from '@/Components/Shared/ProductLookupInline';
import { maskMoney, parseMoney, maskCpf } from '@/Hooks/useMasks';

/**
 * Wizard de criação de Consignação em 3 passos.
 *
 * Mobile-first: no mobile cada passo ocupa tela cheia via StandardModal
 * com maxWidth="2xl"; no desktop mostra progress no topo e permite
 * navegação entre passos.
 *
 * Passo 1: tipo + loja + consultor + destinatário (CPF/CNPJ)
 * Passo 2: NF de saída + itens (com ProductLookupInline para regra M8)
 * Passo 3: confirmação + notas + toggle "emitir agora" (draft → pending)
 */
export default function CreateConsignmentModal({
    show,
    onClose,
    typeOptions = {},
    selects = {},
    canOverrideLock = false,
    canEditReturnPeriod = false,
}) {
    const [step, setStep] = useState(1);
    const [employees, setEmployees] = useState([]);
    const [loadingEmployees, setLoadingEmployees] = useState(false);
    const [lookupState, setLookupState] = useState({ loading: false, error: null, notFound: false, orphans: [] });

    // Autocomplete de cliente (M12)
    const [customerQuery, setCustomerQuery] = useState('');
    const [customerResults, setCustomerResults] = useState([]);
    const [customerLoading, setCustomerLoading] = useState(false);
    const [showCustomerResults, setShowCustomerResults] = useState(false);
    const [selectedCustomerLabel, setSelectedCustomerLabel] = useState(null);

    const { data, setData, post, processing, errors, reset, setError, clearErrors } = useForm({
        type: 'cliente',
        store_id: '',
        employee_id: '',
        customer_id: null,
        recipient_name: '',
        recipient_document: '',
        recipient_phone: '',
        recipient_email: '',
        outbound_invoice_number: '',
        outbound_invoice_date: new Date().toISOString().slice(0, 10),
        return_period_days: 7,
        notes: '',
        items: [],
        issue_now: true,
        override_lock_reason: '',
    });

    useEffect(() => {
        if (!show) {
            setStep(1);
            setEmployees([]);
            setLookupState({ loading: false, error: null, notFound: false, orphans: [] });
            setCustomerQuery('');
            setCustomerResults([]);
            setShowCustomerResults(false);
            setSelectedCustomerLabel(null);
            reset();
            clearErrors();
        }
    }, [show]);

    // Autocomplete de cliente com debounce 300ms. Busca em name/cpf/
    // mobile/email/cigam_code. Ao selecionar, popula name/doc/phone/email.
    useEffect(() => {
        if (!show || selectedCustomerLabel) return;
        if (customerQuery.trim().length < 2) {
            setCustomerResults([]);
            setShowCustomerResults(false);
            return;
        }

        const handle = setTimeout(async () => {
            setCustomerLoading(true);
            try {
                const url = new URL(route('customers.lookup'), window.location.origin);
                url.searchParams.set('q', customerQuery.trim());
                url.searchParams.set('limit', '10');

                const response = await fetch(url.toString(), {
                    headers: { Accept: 'application/json' },
                });
                if (!response.ok) throw new Error();

                const json = await response.json();
                setCustomerResults(json.results || []);
                setShowCustomerResults(true);
            } catch {
                setCustomerResults([]);
                setShowCustomerResults(false);
            } finally {
                setCustomerLoading(false);
            }
        }, 300);

        return () => clearTimeout(handle);
    }, [show, customerQuery, selectedCustomerLabel]);

    const selectCustomer = (customer) => {
        setData((prev) => ({
            ...prev,
            customer_id: customer.id,
            recipient_name: customer.name,
            recipient_document: customer.formatted_cpf || customer.cpf || '',
            recipient_phone: customer.formatted_mobile || '',
            recipient_email: customer.email || '',
        }));
        setSelectedCustomerLabel(`${customer.name} · ${customer.formatted_cpf || customer.cigam_code}`);
        setCustomerQuery('');
        setCustomerResults([]);
        setShowCustomerResults(false);
    };

    const clearCustomerSelection = () => {
        setData((prev) => ({
            ...prev,
            customer_id: null,
            recipient_name: '',
            recipient_document: '',
            recipient_phone: '',
            recipient_email: '',
        }));
        setSelectedCustomerLabel(null);
        setCustomerQuery('');
    };

    // Carrega consultores quando a loja muda. Reseta employee_id
    // para evitar submeter ID de outra loja.
    useEffect(() => {
        if (!show || !data.store_id) {
            setEmployees([]);
            return;
        }

        let cancelled = false;
        setLoadingEmployees(true);

        fetch(route('consignments.lookup.employees', { store_id: data.store_id }), {
            headers: { Accept: 'application/json' },
        })
            .then((r) => (r.ok ? r.json() : Promise.reject()))
            .then((json) => {
                if (cancelled) return;
                setEmployees(json.employees || []);
                // Se o consultor previamente selecionado não está na nova loja, limpa
                if (data.employee_id && !(json.employees || []).some((e) => String(e.id) === String(data.employee_id))) {
                    setData('employee_id', '');
                }
            })
            .catch(() => {
                if (!cancelled) setEmployees([]);
            })
            .finally(() => {
                if (!cancelled) setLoadingEmployees(false);
            });

        return () => { cancelled = true; };
    }, [show, data.store_id]);

    const typeRequiresEmployee = data.type === 'cliente';

    const nextStep = () => {
        const errs = {};
        if (step === 1) {
            if (!data.type) errs.type = 'Selecione o tipo.';
            if (!data.store_id) errs.store_id = 'Selecione a loja.';
            if (typeRequiresEmployee && !data.employee_id) {
                errs.employee_id = 'Consignação para cliente exige consultor(a) responsável.';
            }
            if (!data.recipient_name?.trim()) errs.recipient_name = 'Informe o nome do destinatário.';
            const docDigits = (data.recipient_document || '').replace(/\D/g, '');
            if (docDigits && ![11, 14].includes(docDigits.length)) {
                errs.recipient_document = 'Documento deve ter 11 (CPF) ou 14 (CNPJ) dígitos.';
            }
        } else if (step === 2) {
            if (!data.outbound_invoice_number?.trim()) errs.outbound_invoice_number = 'Informe o número da NF.';
            if (!data.outbound_invoice_date) errs.outbound_invoice_date = 'Informe a data da NF.';
            if (!data.items.length) errs.items = 'Adicione pelo menos um item.';
            data.items.forEach((it, idx) => {
                if (!it.product_id) {
                    errs[`items.${idx}.product_id`] = 'Selecione o produto.';
                }
                if (!it.quantity || it.quantity < 1) {
                    errs[`items.${idx}.quantity`] = 'Quantidade inválida.';
                }
            });
        }

        if (Object.keys(errs).length) {
            Object.entries(errs).forEach(([k, v]) => setError(k, v));
            return;
        }

        clearErrors();
        setStep(step + 1);
    };

    const prevStep = () => {
        clearErrors();
        setStep(Math.max(1, step - 1));
    };

    const addItem = () => {
        setData('items', [
            ...data.items,
            {
                product_id: null,
                product_variant_id: null,
                reference: '',
                barcode: '',
                size_label: '',
                size_cigam_code: '',
                description: '',
                quantity: 1,
                unit_value: 0,
            },
        ]);
    };

    const updateItem = (idx, patch) => {
        const newItems = [...data.items];
        newItems[idx] = { ...newItems[idx], ...patch };
        setData('items', newItems);
    };

    const removeItem = (idx) => {
        setData('items', data.items.filter((_, i) => i !== idx));
    };

    /**
     * Busca a NF no CIGAM (movements code=20) pela combinação
     * loja + número + data e popula os itens automaticamente. Se
     * algum item não existe no catálogo, mostra como órfão (regra M8).
     */
    const lookupInvoice = async () => {
        const selectedStore = (selects.stores || []).find((s) => String(s.id) === String(data.store_id));
        if (!selectedStore) {
            setLookupState({ loading: false, error: 'Selecione a loja primeiro.', notFound: false, orphans: [] });
            return;
        }
        if (!data.outbound_invoice_number?.trim()) {
            setLookupState({ loading: false, error: 'Informe o número da NF.', notFound: false, orphans: [] });
            return;
        }
        if (!data.outbound_invoice_date) {
            setLookupState({ loading: false, error: 'Informe a data da NF.', notFound: false, orphans: [] });
            return;
        }

        setLookupState({ loading: true, error: null, notFound: false, orphans: [] });

        try {
            const url = new URL(route('consignments.lookup.outbound-invoice'), window.location.origin);
            url.searchParams.set('invoice_number', data.outbound_invoice_number.trim());
            url.searchParams.set('store_code', selectedStore.code);
            url.searchParams.set('movement_date', data.outbound_invoice_date);

            const response = await fetch(url.toString(), {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) throw new Error('Falha na busca');

            const payload = await response.json();

            if (!payload.found) {
                setLookupState({ loading: false, error: null, notFound: true, orphans: [] });
                return;
            }

            // Popula itens — substitui os atuais (caller pode adicionar manualmente depois)
            const resolved = (payload.items || []).map((it) => ({
                product_id: it.product_id,
                product_variant_id: it.product_variant_id,
                movement_id: it.movement_id,
                reference: it.reference,
                barcode: it.barcode,
                size_label: it.size_label,
                size_cigam_code: it.size_cigam_code,
                description: it.description,
                quantity: Math.max(1, Number(it.quantity || 1)),
                unit_value: Number(it.unit_value || 0),
            }));

            setData('items', resolved);
            setLookupState({
                loading: false,
                error: null,
                notFound: false,
                orphans: payload.orphan_items || [],
            });
        } catch (e) {
            setLookupState({
                loading: false,
                error: 'Erro ao buscar a NF. Tente novamente.',
                notFound: false,
                orphans: [],
            });
        }
    };

    const submit = () => {
        post(route('consignments.store'), {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    const HeaderIcon = step === 1 ? UserIcon : step === 2 ? ArchiveBoxIcon : CheckCircleIcon;
    const stepTitles = [
        'Destinatário e tipo',
        'Nota fiscal de saída e itens',
        'Revisar e confirmar',
    ];

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title={`Nova Consignação — Passo ${step} de 3`}
            subtitle={stepTitles[step - 1]}
            headerColor="bg-indigo-600"
            headerIcon={<HeaderIcon className="h-5 w-5" />}
            maxWidth="3xl"
            footer={
                <StandardModal.Footer>
                    <div className="flex-1" />
                    <Button variant="secondary" onClick={onClose} disabled={processing}>
                        Cancelar
                    </Button>
                    {step > 1 && (
                        <Button variant="secondary" onClick={prevStep} disabled={processing}>
                            Voltar
                        </Button>
                    )}
                    {step < 3 && (
                        <Button variant="primary" onClick={nextStep} disabled={processing}>
                            Avançar
                        </Button>
                    )}
                    {step === 3 && (
                        <Button variant="success" onClick={submit} disabled={processing}>
                            {processing ? 'Salvando…' : 'Criar consignação'}
                        </Button>
                    )}
                </StandardModal.Footer>
            }
        >
            {/* Progress bar */}
            <div className="mb-6 flex gap-2">
                {[1, 2, 3].map((n) => (
                    <div
                        key={n}
                        className={`flex-1 h-2 rounded-full ${n <= step ? 'bg-indigo-600' : 'bg-gray-200'}`}
                    />
                ))}
            </div>

            {/* Passo 1 — Destinatário */}
            {step === 1 && (
                <div className="space-y-4">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="Tipo *" />
                            <select
                                value={data.type}
                                onChange={(e) => setData('type', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 min-h-[44px]"
                            >
                                {Object.entries(typeOptions).map(([v, lbl]) => (
                                    <option key={v} value={v}>{lbl}</option>
                                ))}
                            </select>
                            <InputError message={errors.type} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Loja *" />
                            <select
                                value={data.store_id}
                                onChange={(e) => setData('store_id', e.target.value)}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 min-h-[44px]"
                            >
                                <option value="">Selecione…</option>
                                {(selects.stores || []).map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.code} — {s.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.store_id} className="mt-1" />
                        </div>
                    </div>

                    {typeRequiresEmployee && (
                        <div>
                            <InputLabel value="Consultor(a) responsável *" />
                            <select
                                value={data.employee_id}
                                onChange={(e) => setData('employee_id', e.target.value)}
                                disabled={!data.store_id || loadingEmployees}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 min-h-[44px] disabled:bg-gray-100 disabled:cursor-not-allowed"
                            >
                                <option value="">
                                    {!data.store_id
                                        ? 'Selecione a loja primeiro…'
                                        : loadingEmployees
                                            ? 'Carregando colaboradores…'
                                            : employees.length === 0
                                                ? 'Nenhum colaborador ativo nesta loja'
                                                : 'Selecione…'}
                                </option>
                                {employees.map((emp) => (
                                    <option key={emp.id} value={emp.id}>
                                        {emp.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.employee_id} className="mt-1" />
                            {data.store_id && !loadingEmployees && employees.length === 0 && (
                                <p className="mt-1 text-xs text-amber-700">
                                    Sem colaboradores ativos cadastrados para esta loja. Verifique o cadastro em Colaboradores.
                                </p>
                            )}
                        </div>
                    )}

                    {/* Autocomplete de cliente cadastrado (M12) */}
                    <div className="bg-indigo-50 border border-indigo-200 rounded-md p-3">
                        <div className="flex items-center justify-between gap-2 mb-1">
                            <InputLabel value="Cliente cadastrado (opcional)" className="!text-indigo-900" />
                            {selectedCustomerLabel && (
                                <button
                                    type="button"
                                    onClick={clearCustomerSelection}
                                    className="text-xs text-indigo-700 hover:text-indigo-900 flex items-center gap-1"
                                >
                                    <XMarkIcon className="w-3 h-3" />
                                    Limpar
                                </button>
                            )}
                        </div>

                        {selectedCustomerLabel ? (
                            <div className="bg-white border border-indigo-300 rounded-md px-3 py-2 flex items-center gap-2">
                                <UserPlusIcon className="w-5 h-5 text-indigo-600 shrink-0" />
                                <span className="text-sm text-gray-900 truncate">{selectedCustomerLabel}</span>
                            </div>
                        ) : (
                            <div className="relative">
                                <TextInput
                                    type="text"
                                    value={customerQuery}
                                    onChange={(e) => setCustomerQuery(e.target.value)}
                                    onFocus={() => customerResults.length > 0 && setShowCustomerResults(true)}
                                    onBlur={() => setTimeout(() => setShowCustomerResults(false), 200)}
                                    placeholder="Buscar por nome, CPF, e-mail ou telefone…"
                                    className="block w-full"
                                    inputMode="search"
                                />
                                {showCustomerResults && (
                                    <div className="absolute z-40 mt-1 w-full bg-white shadow-lg rounded-md border border-gray-200 max-h-64 overflow-y-auto">
                                        {customerLoading && (
                                            <div className="p-3 text-sm text-gray-500 text-center">Buscando…</div>
                                        )}
                                        {!customerLoading && customerResults.length === 0 && (
                                            <div className="p-3 text-sm text-gray-500 text-center">
                                                Nenhum cliente encontrado. Preencha os dados manualmente abaixo.
                                            </div>
                                        )}
                                        {!customerLoading && customerResults.map((cust) => (
                                            <button
                                                key={cust.id}
                                                type="button"
                                                onClick={() => selectCustomer(cust)}
                                                className="w-full text-left px-4 py-3 hover:bg-indigo-50 border-b border-gray-100 last:border-0 min-h-[44px]"
                                            >
                                                <div className="font-medium text-gray-900">{cust.name}</div>
                                                <div className="text-xs text-gray-500 flex flex-wrap gap-x-3">
                                                    {cust.formatted_cpf && <span>{cust.formatted_cpf}</span>}
                                                    {cust.primary_contact && <span>{cust.formatted_mobile}</span>}
                                                    {cust.email && <span className="truncate max-w-[200px]">{cust.email}</span>}
                                                </div>
                                            </button>
                                        ))}
                                    </div>
                                )}
                                <p className="mt-1 text-xs text-indigo-700">
                                    Selecione um cliente já sincronizado do CIGAM ou preencha manualmente.
                                </p>
                            </div>
                        )}
                    </div>

                    <div>
                        <InputLabel value="Nome do destinatário *" />
                        <TextInput
                            type="text"
                            value={data.recipient_name}
                            onChange={(e) => setData('recipient_name', e.target.value)}
                            maxLength={200}
                            className="mt-1 block w-full"
                        />
                        <InputError message={errors.recipient_name} className="mt-1" />
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="CPF / CNPJ" />
                            <TextInput
                                type="text"
                                value={data.recipient_document}
                                onChange={(e) => setData('recipient_document', maskCpf(e.target.value))}
                                placeholder="000.000.000-00"
                                className="mt-1 block w-full"
                                inputMode="numeric"
                            />
                            <InputError message={errors.recipient_document} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Telefone" />
                            <TextInput
                                type="tel"
                                value={data.recipient_phone}
                                onChange={(e) => setData('recipient_phone', e.target.value)}
                                placeholder="(00) 00000-0000"
                                className="mt-1 block w-full"
                                inputMode="tel"
                            />
                        </div>
                    </div>

                    <div>
                        <InputLabel value="E-mail" />
                        <TextInput
                            type="email"
                            value={data.recipient_email}
                            onChange={(e) => setData('recipient_email', e.target.value)}
                            className="mt-1 block w-full"
                            inputMode="email"
                        />
                    </div>

                    {canOverrideLock && (
                        <div className="bg-amber-50 border border-amber-200 rounded-md p-3">
                            <InputLabel value="Justificativa para ignorar bloqueio de inadimplência (opcional)" />
                            <TextInput
                                type="text"
                                value={data.override_lock_reason}
                                onChange={(e) => setData('override_lock_reason', e.target.value)}
                                placeholder="Ex: Cliente regularizou por fora"
                                className="mt-1 block w-full"
                            />
                            <p className="mt-1 text-xs text-amber-800">
                                Preencha apenas se o destinatário tem consignação em atraso e você precisa cadastrar mesmo assim.
                                A justificativa ficará no histórico.
                            </p>
                        </div>
                    )}
                </div>
            )}

            {/* Passo 2 — NF e itens */}
            {step === 2 && (
                <div className="space-y-4">
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <InputLabel value="Número da NF de saída *" />
                            <TextInput
                                type="text"
                                value={data.outbound_invoice_number}
                                onChange={(e) => setData('outbound_invoice_number', e.target.value)}
                                maxLength={20}
                                className="mt-1 block w-full"
                                inputMode="numeric"
                            />
                            <InputError message={errors.outbound_invoice_number} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Data da NF *" />
                            <TextInput
                                type="date"
                                value={data.outbound_invoice_date}
                                onChange={(e) => setData('outbound_invoice_date', e.target.value)}
                                className="mt-1 block w-full"
                            />
                            <InputError message={errors.outbound_invoice_date} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel value="Prazo de retorno (dias)" />
                            <TextInput
                                type="number"
                                min={1}
                                max={90}
                                value={data.return_period_days}
                                onChange={(e) => canEditReturnPeriod && setData('return_period_days', Number(e.target.value))}
                                readOnly={!canEditReturnPeriod}
                                disabled={!canEditReturnPeriod}
                                title={
                                    canEditReturnPeriod
                                        ? 'Ajuste o prazo de retorno em dias'
                                        : 'Alteração de prazo exige hierarquia Finance ou superior'
                                }
                                className={`mt-1 block w-full ${!canEditReturnPeriod ? 'bg-gray-100 cursor-not-allowed' : ''}`}
                                inputMode="numeric"
                            />
                            {!canEditReturnPeriod && (
                                <p className="mt-1 text-xs text-gray-500">
                                    Prazo padrão de 7 dias. Ajuste requer perfil Financeiro ou superior.
                                </p>
                            )}
                        </div>
                    </div>

                    {/* Auto-lookup — busca NF no CIGAM por loja+número+data */}
                    <div className="bg-indigo-50 border border-indigo-200 rounded-md p-3">
                        <div className="flex items-start gap-2 flex-wrap">
                            <div className="flex-1 min-w-0">
                                <div className="text-sm font-medium text-indigo-900">
                                    Buscar itens automaticamente
                                </div>
                                <p className="text-xs text-indigo-700 mt-0.5">
                                    Com loja, número e data preenchidos, o sistema busca a NF no CIGAM e preenche os itens.
                                </p>
                            </div>
                            <Button
                                variant="primary"
                                size="sm"
                                onClick={lookupInvoice}
                                disabled={lookupState.loading || !data.store_id || !data.outbound_invoice_number || !data.outbound_invoice_date}
                                icon={MagnifyingGlassIcon}
                            >
                                {lookupState.loading ? 'Buscando…' : 'Buscar NF'}
                            </Button>
                        </div>

                        {lookupState.error && (
                            <div className="mt-2 text-xs text-red-700 bg-red-50 border border-red-200 rounded p-2">
                                {lookupState.error}
                            </div>
                        )}
                        {lookupState.notFound && (
                            <div className="mt-2 text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded p-2 flex items-start gap-2">
                                <ExclamationTriangleIcon className="w-4 h-4 shrink-0 mt-0.5" />
                                <div>
                                    NF não encontrada com esta combinação de loja + número + data. Verifique os campos
                                    ou adicione os itens manualmente abaixo.
                                </div>
                            </div>
                        )}
                        {lookupState.orphans.length > 0 && (
                            <div className="mt-2 text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded p-2">
                                <strong>Atenção — {lookupState.orphans.length} item(ns) da NF não estão no catálogo:</strong>
                                <ul className="mt-1 list-disc list-inside">
                                    {lookupState.orphans.map((it, i) => (
                                        <li key={i}>
                                            {it.reference || it.barcode || 'Sem referência'}
                                            {it.size_label && ` · Tam. ${it.size_label}`}
                                            {' — '}
                                            {it.quantity} peça(s)
                                        </li>
                                    ))}
                                </ul>
                                <p className="mt-1">Cadastre os produtos faltantes no catálogo antes de prosseguir (regra M8).</p>
                            </div>
                        )}
                    </div>

                    <div className="border-t pt-4">
                        <div className="flex items-center justify-between mb-3">
                            <h3 className="font-medium text-gray-900">Itens da remessa *</h3>
                            <Button variant="primary" size="sm" onClick={addItem} icon={PlusIcon}>
                                Adicionar item
                            </Button>
                        </div>

                        {errors.items && (
                            <p className="text-sm text-red-600 mb-2">{errors.items}</p>
                        )}

                        {data.items.length === 0 ? (
                            <div className="text-center py-8 text-gray-500 text-sm border-2 border-dashed rounded-md">
                                Nenhum item ainda. Clique em "Adicionar item" ou "Buscar NF" para preencher automaticamente.
                            </div>
                        ) : (
                            <div className="space-y-3">
                                {/* Itens da NF — agrupados por referência em formato matriz (ref × tamanhos) */}
                                {(() => {
                                    const nfEntries = data.items
                                        .map((item, idx) => ({ item, idx }))
                                        .filter(({ item }) => Boolean(item.movement_id));

                                    if (nfEntries.length === 0) return null;

                                    // Agrupa por reference
                                    const groups = {};
                                    for (const entry of nfEntries) {
                                        const key = entry.item.reference || `__unknown_${entry.idx}`;
                                        if (!groups[key]) {
                                            groups[key] = {
                                                reference: entry.item.reference || '—',
                                                description: entry.item.description || '',
                                                unit_value: Number(entry.item.unit_value || 0),
                                                entries: [],
                                            };
                                        }
                                        groups[key].entries.push({
                                            idx: entry.idx,
                                            item: entry.item,
                                            sizeDisplay: entry.item.size_label
                                                || (entry.item.size_cigam_code ? entry.item.size_cigam_code.replace(/^U/, '') : '—'),
                                        });
                                    }

                                    // Ordena cada grupo por tamanho (numérico quando possível)
                                    Object.values(groups).forEach((g) => {
                                        g.entries.sort((a, b) => {
                                            const na = parseInt(a.sizeDisplay, 10);
                                            const nb = parseInt(b.sizeDisplay, 10);
                                            if (Number.isFinite(na) && Number.isFinite(nb)) return na - nb;
                                            return String(a.sizeDisplay).localeCompare(String(b.sizeDisplay));
                                        });
                                    });

                                    return Object.values(groups).map((group, groupIdx) => {
                                        const totalPieces = group.entries.reduce((s, e) => s + Number(e.item.quantity || 0), 0);
                                        const subtotal = group.entries.reduce(
                                            (s, e) => s + Number(e.item.quantity || 0) * Number(e.item.unit_value || 0),
                                            0,
                                        );

                                        return (
                                            <div
                                                key={`nf-${group.reference}-${groupIdx}`}
                                                className="border border-indigo-200 bg-indigo-50/40 rounded-md p-3"
                                            >
                                                {/* Header do grupo */}
                                                <div className="flex items-start justify-between gap-3 flex-wrap">
                                                    <div className="min-w-0 flex-1">
                                                        <div className="text-xs text-indigo-700 uppercase font-medium">
                                                            Da NF
                                                        </div>
                                                        <div className="mt-0.5 font-semibold text-gray-900 truncate">
                                                            {group.reference}
                                                        </div>
                                                        {group.description && (
                                                            <div className="text-xs text-gray-600 truncate">
                                                                {group.description}
                                                            </div>
                                                        )}
                                                    </div>
                                                    <div className="text-right shrink-0">
                                                        <div className="text-[10px] text-gray-500 uppercase">Unit.</div>
                                                        <div className="font-bold text-gray-900">
                                                            R$ {group.unit_value.toFixed(2).replace('.', ',')}
                                                        </div>
                                                    </div>
                                                </div>

                                                {/* Matriz tamanhos × qty */}
                                                <div className="mt-3 overflow-x-auto">
                                                    <table className="min-w-max border-collapse">
                                                        <thead>
                                                            <tr>
                                                                <th className="text-left pr-3 text-[10px] text-gray-600 uppercase font-medium">
                                                                    Tam.
                                                                </th>
                                                                {group.entries.map((e) => (
                                                                    <th
                                                                        key={`size-${e.idx}`}
                                                                        className="px-3 py-1.5 border border-indigo-200 bg-white text-sm font-semibold text-gray-900 min-w-[48px]"
                                                                    >
                                                                        {e.sizeDisplay}
                                                                    </th>
                                                                ))}
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <tr>
                                                                <td className="text-left pr-3 text-[10px] text-gray-600 uppercase font-medium">
                                                                    Qtd
                                                                </td>
                                                                {group.entries.map((e) => (
                                                                    <td
                                                                        key={`qty-${e.idx}`}
                                                                        className="px-3 py-2 border border-indigo-200 bg-white text-center text-sm font-bold text-gray-900"
                                                                    >
                                                                        {e.item.quantity}
                                                                    </td>
                                                                ))}
                                                            </tr>
                                                            <tr>
                                                                <td className="text-left pr-3 text-[10px] text-gray-400 uppercase font-medium">
                                                                    Ação
                                                                </td>
                                                                {group.entries.map((e) => (
                                                                    <td
                                                                        key={`action-${e.idx}`}
                                                                        className="px-2 py-1 border border-indigo-200 bg-white text-center"
                                                                    >
                                                                        <button
                                                                            type="button"
                                                                            onClick={() => removeItem(e.idx)}
                                                                            className="text-gray-300 hover:text-red-600 p-1"
                                                                            aria-label={`Remover tamanho ${e.sizeDisplay}`}
                                                                            title={`Excluir tamanho ${e.sizeDisplay} desta consignação`}
                                                                        >
                                                                            <TrashIcon className="w-4 h-4 inline" />
                                                                        </button>
                                                                    </td>
                                                                ))}
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>

                                                {/* Totais do grupo */}
                                                <div className="mt-3 flex justify-between items-center text-sm border-t border-indigo-100 pt-2">
                                                    <span className="text-gray-700">
                                                        <strong>{totalPieces}</strong> peça(s)
                                                    </span>
                                                    <span className="font-bold text-indigo-700">
                                                        Subtotal: R$ {subtotal.toFixed(2).replace('.', ',')}
                                                    </span>
                                                </div>
                                            </div>
                                        );
                                    });
                                })()}

                                {/* Itens manuais (sem movement_id) — editáveis, layout individual */}
                                {data.items.map((item, idx) => {
                                    if (item.movement_id) return null; // já renderizado na matriz

                                    return (
                                        <div key={`manual-${idx}`} className="border border-gray-200 rounded-md p-3 bg-gray-50 relative">
                                            <button
                                                type="button"
                                                onClick={() => removeItem(idx)}
                                                className="absolute top-2 right-2 text-gray-400 hover:text-red-600 p-1"
                                                aria-label="Remover item"
                                            >
                                                <TrashIcon className="w-5 h-5" />
                                            </button>

                                            <div className="pr-8">
                                                <ProductLookupInline
                                                    value={item.product_id ? item : null}
                                                    onChange={(selection) => {
                                                        if (selection) {
                                                            updateItem(idx, {
                                                                ...selection,
                                                                unit_value: selection.unit_value ?? item.unit_value,
                                                            });
                                                        } else {
                                                            updateItem(idx, {
                                                                product_id: null,
                                                                product_variant_id: null,
                                                                reference: '',
                                                                barcode: '',
                                                                size_cigam_code: '',
                                                            });
                                                        }
                                                    }}
                                                    lookupUrl={route('consignments.lookup.products')}
                                                    label={`Item ${idx + 1} · manual`}
                                                    error={errors[`items.${idx}.product_id`]}
                                                    required
                                                />

                                                <div className="mt-3 grid grid-cols-2 gap-3">
                                                    <div>
                                                        <InputLabel value="Quantidade *" />
                                                        <TextInput
                                                            type="number"
                                                            min={1}
                                                            value={item.quantity}
                                                            onChange={(e) => updateItem(idx, { quantity: Math.max(1, Number(e.target.value)) })}
                                                            className="mt-1 block w-full"
                                                            inputMode="numeric"
                                                        />
                                                        <InputError message={errors[`items.${idx}.quantity`]} className="mt-1" />
                                                    </div>
                                                    <div>
                                                        <InputLabel value="Valor unitário *" />
                                                        <TextInput
                                                            type="text"
                                                            value={maskMoney((item.unit_value ?? 0) * 100)}
                                                            onChange={(e) => updateItem(idx, { unit_value: parseMoney(e.target.value) })}
                                                            className="mt-1 block w-full"
                                                            inputMode="decimal"
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                </div>
            )}

            {/* Passo 3 — confirmação */}
            {step === 3 && (
                <div className="space-y-4">
                    <div className="bg-indigo-50 border border-indigo-200 rounded-md p-4">
                        <h3 className="font-medium text-indigo-900 mb-2">Resumo</h3>
                        <dl className="text-sm space-y-1">
                            <div className="flex justify-between">
                                <dt className="text-gray-600">Tipo:</dt>
                                <dd className="font-medium">{typeOptions[data.type] || data.type}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-600">Destinatário:</dt>
                                <dd className="font-medium">{data.recipient_name}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-600">NF de saída:</dt>
                                <dd className="font-medium">{data.outbound_invoice_number} — {data.outbound_invoice_date}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-600">Prazo de retorno:</dt>
                                <dd className="font-medium">{data.return_period_days} dias</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-gray-600">Itens:</dt>
                                <dd className="font-medium">{data.items.length} produto(s), {data.items.reduce((sum, i) => sum + Number(i.quantity || 0), 0)} peça(s)</dd>
                            </div>
                            <div className="flex justify-between border-t pt-1 mt-1">
                                <dt className="text-gray-600">Valor total:</dt>
                                <dd className="font-bold">
                                    R$ {data.items.reduce((sum, i) => sum + (Number(i.quantity) * Number(i.unit_value || 0)), 0).toFixed(2).replace('.', ',')}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <div>
                        <InputLabel value="Observações" />
                        <textarea
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                            rows={3}
                            maxLength={2000}
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Informações adicionais sobre esta consignação…"
                        />
                    </div>

                    <label className="flex items-start gap-2 text-sm text-gray-700">
                        <input
                            type="checkbox"
                            checked={data.issue_now}
                            onChange={(e) => setData('issue_now', e.target.checked)}
                            className="mt-0.5 rounded border-gray-300 w-5 h-5"
                        />
                        <span>
                            <strong>Emitir NF agora</strong> — consignação vai direto para status "Pendente".
                            Se desmarcar, fica como "Rascunho" e precisa ser emitida depois.
                        </span>
                    </label>

                    {Object.keys(errors).length > 0 && (
                        <div className="bg-red-50 border border-red-200 rounded-md p-3">
                            <p className="text-sm font-medium text-red-800">Erros encontrados:</p>
                            <ul className="mt-1 text-xs text-red-700 list-disc list-inside">
                                {Object.entries(errors).map(([k, v]) => (
                                    <li key={k}>{String(v)}</li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            )}
        </StandardModal>
    );
}
