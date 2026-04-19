import { useState, useMemo } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import {
    CloudArrowUpIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    XCircleIcon,
    ArrowRightIcon,
    ArrowLeftIcon,
    DocumentMagnifyingGlassIcon,
} from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import Button from '@/Components/Button';

const BRL = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

const FK_TYPE_LABELS = {
    accounting_class: 'Conta contábil',
    management_class: 'Conta gerencial',
    cost_center: 'Centro de custo',
    store: 'Loja',
};

const FK_TYPE_ICONS = {
    accounting_class: '📘',
    management_class: '📗',
    cost_center: '🏢',
    store: '🏬',
};

/**
 * Wizard de upload em 3 passos:
 *   1. upload     — seleciona arquivo xlsx e pede preview
 *   2. reconcile  — mostra diagnóstico + dropdowns para mapear códigos ausentes
 *   3. confirm    — dados do header (year/scope/type) + submit
 */
export default function BudgetUploadWizard({ show, onClose, enums = {}, selects = {} }) {
    const [step, setStep] = useState('upload');
    const [file, setFile] = useState(null);
    const [preview, setPreview] = useState(null);
    const [mapping, setMapping] = useState({
        accounting_class: {},
        management_class: {},
        cost_center: {},
        store: {},
    });
    const [header, setHeader] = useState({
        year: new Date().getFullYear() + 1,
        scope_label: '',
        upload_type: 'novo',
        notes: '',
    });
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);
    const [serverMessage, setServerMessage] = useState(null);

    const reset = () => {
        setStep('upload');
        setFile(null);
        setPreview(null);
        setMapping({
            accounting_class: {},
            management_class: {},
            cost_center: {},
            store: {},
        });
        setHeader({
            year: new Date().getFullYear() + 1,
            scope_label: '',
            upload_type: 'novo',
            notes: '',
        });
        setErrors({});
        setProcessing(false);
        setServerMessage(null);
    };

    const handleClose = () => {
        reset();
        onClose();
    };

    // ------------------------------------------------------------------
    // Step 1 — file upload + preview request
    // ------------------------------------------------------------------
    const handleAnalyze = async () => {
        if (!file) return;
        setProcessing(true);
        setServerMessage(null);
        try {
            const formData = new FormData();
            formData.append('file', file);
            const { data } = await axios.post(route('budgets.preview'), formData);
            setPreview(data);

            // Pre-seleciona sugestão #1 (melhor fuzzy) para cada code ausente
            const defaults = {
                accounting_class: {},
                management_class: {},
                cost_center: {},
                store: {},
            };
            Object.entries(data.unresolved_summary || {}).forEach(([type, list]) => {
                list.forEach((entry) => {
                    if (entry.suggestions && entry.suggestions.length > 0) {
                        defaults[type][entry.code] = entry.suggestions[0].id;
                    }
                });
            });
            setMapping(defaults);
            setStep('reconcile');
        } catch (e) {
            setServerMessage(e.response?.data?.message || 'Falha ao processar a planilha.');
        } finally {
            setProcessing(false);
        }
    };

    // ------------------------------------------------------------------
    // Mapping changes
    // ------------------------------------------------------------------
    const updateMapping = (type, code, id) => {
        setMapping((prev) => ({
            ...prev,
            [type]: { ...prev[type], [code]: id ? parseInt(id) : null },
        }));
    };

    const unresolvedCount = useMemo(() => {
        if (!preview) return 0;
        let count = 0;
        Object.entries(preview.unresolved_summary || {}).forEach(([type, list]) => {
            list.forEach((entry) => {
                if (!mapping[type]?.[entry.code]) count++;
            });
        });
        return count;
    }, [preview, mapping]);

    const effectiveImportableRows = useMemo(() => {
        if (!preview) return 0;
        // valid rows + pending rows whose ALL FKs agora têm mapping
        let importable = preview.valid_rows;
        preview.rows.forEach((row) => {
            if (row.status !== 'needs_reconciliation') return;
            const allResolved = Object.entries(row.unresolved || {}).every(
                ([type, code]) => mapping[type]?.[code]
            );
            if (allResolved) importable++;
        });
        return importable;
    }, [preview, mapping]);

    // ------------------------------------------------------------------
    // Step 3 — confirm + submit
    // ------------------------------------------------------------------
    const handleConfirm = () => {
        if (!file) return;
        setProcessing(true);
        setErrors({});

        const formData = new FormData();
        formData.append('file', file);
        formData.append('year', header.year);
        formData.append('scope_label', header.scope_label);
        formData.append('upload_type', header.upload_type);
        if (header.notes) formData.append('notes', header.notes);

        // Serializa mapping como array aninhado: mapping[accounting_class][CODE-X]=42
        Object.entries(mapping).forEach(([type, codes]) => {
            Object.entries(codes).forEach(([code, id]) => {
                if (id) {
                    formData.append(`mapping[${type}][${code}]`, id);
                }
            });
        });

        router.post(route('budgets.import'), formData, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                handleClose();
            },
            onError: (serverErrors) => {
                setErrors(serverErrors);
                setProcessing(false);
            },
            onFinish: () => setProcessing(false),
        });
    };

    // ------------------------------------------------------------------
    // Footer por step
    // ------------------------------------------------------------------
    const renderFooter = () => {
        if (step === 'upload') {
            return (
                <div className="flex justify-end gap-2 w-full">
                    <Button variant="secondary" onClick={handleClose}>
                        Cancelar
                    </Button>
                    <Button
                        variant="primary"
                        onClick={handleAnalyze}
                        disabled={!file || processing}
                        loading={processing}
                        icon={DocumentMagnifyingGlassIcon}
                    >
                        Analisar planilha
                    </Button>
                </div>
            );
        }

        if (step === 'reconcile') {
            return (
                <div className="flex justify-between items-center gap-2 w-full">
                    <Button variant="secondary" onClick={() => setStep('upload')} icon={ArrowLeftIcon}>
                        Voltar
                    </Button>
                    <div className="text-sm text-gray-600">
                        <span className="font-semibold text-indigo-700">
                            {effectiveImportableRows}
                        </span>{' '}
                        de {preview?.total_rows} linhas importáveis
                        {unresolvedCount > 0 && (
                            <span className="ml-2 text-amber-600">
                                ({unresolvedCount} códigos sem mapeamento)
                            </span>
                        )}
                    </div>
                    <Button
                        variant="primary"
                        onClick={() => setStep('confirm')}
                        disabled={effectiveImportableRows === 0}
                        icon={ArrowRightIcon}
                    >
                        Continuar
                    </Button>
                </div>
            );
        }

        // confirm
        return (
            <div className="flex justify-between items-center gap-2 w-full">
                <Button variant="secondary" onClick={() => setStep('reconcile')} icon={ArrowLeftIcon}>
                    Voltar
                </Button>
                <Button
                    variant="success"
                    onClick={handleConfirm}
                    disabled={processing || !header.scope_label || !header.year}
                    loading={processing}
                    icon={CloudArrowUpIcon}
                >
                    Importar {effectiveImportableRows} linhas
                </Button>
            </div>
        );
    };

    // ------------------------------------------------------------------
    // Render
    // ------------------------------------------------------------------
    return (
        <StandardModal
            show={show}
            onClose={handleClose}
            title="Importar orçamento"
            subtitle={
                step === 'upload' ? 'Selecione a planilha'
                : step === 'reconcile' ? 'Reconcilie códigos ausentes'
                : 'Confirme e importe'
            }
            headerColor="bg-indigo-700"
            headerIcon={CloudArrowUpIcon}
            maxWidth="6xl"
            footer={renderFooter()}
        >
            {/* ========== STEP 1 — Upload ========== */}
            {step === 'upload' && (
                <>
                    <StandardModal.Section title="Arquivo do orçamento">
                        <div className="space-y-3">
                            <input
                                type="file"
                                accept=".xlsx,.xls,.csv"
                                onChange={(e) => {
                                    setFile(e.target.files?.[0] || null);
                                    setServerMessage(null);
                                }}
                                className="block w-full text-sm text-gray-900 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                            />

                            {file && (
                                <div className="text-xs text-gray-600">
                                    <CheckCircleIcon className="inline w-4 h-4 text-green-600 mr-1" />
                                    {file.name} ({Math.round(file.size / 1024)} KB)
                                </div>
                            )}

                            {serverMessage && (
                                <div className="bg-red-50 border border-red-200 rounded p-3 text-sm text-red-700 flex items-start gap-2">
                                    <ExclamationTriangleIcon className="w-5 h-5 shrink-0 mt-0.5" />
                                    <span>{serverMessage}</span>
                                </div>
                            )}
                        </div>
                    </StandardModal.Section>

                    <StandardModal.Section title="Cabeçalhos aceitos">
                        <div className="bg-gray-50 rounded p-3 text-xs text-gray-700 space-y-1.5">
                            <p>
                                <strong>Códigos (obrigatórios):</strong>{' '}
                                <code>codigo_contabil</code>, <code>codigo_gerencial</code>,{' '}
                                <code>codigo_centro_custo</code>
                            </p>
                            <p>
                                <strong>Loja (opcional):</strong> <code>codigo_loja</code>
                            </p>
                            <p>
                                <strong>Texto (opcional):</strong> <code>fornecedor</code>,{' '}
                                <code>justificativa</code>, <code>descricao_conta</code>,{' '}
                                <code>descricao_classe</code>
                            </p>
                            <p>
                                <strong>Valores mensais:</strong>{' '}
                                <code>jan, fev, ..., dez</code> ou{' '}
                                <code>janeiro, fevereiro, ...</code> ou <code>01-12</code>.
                                Formato BR aceito (<code>1.234,56</code>).
                            </p>
                        </div>
                    </StandardModal.Section>
                </>
            )}

            {/* ========== STEP 2 — Reconcile ========== */}
            {step === 'reconcile' && preview && (
                <ReconcileStep
                    preview={preview}
                    mapping={mapping}
                    updateMapping={updateMapping}
                />
            )}

            {/* ========== STEP 3 — Confirm ========== */}
            {step === 'confirm' && (
                <ConfirmStep
                    header={header}
                    setHeader={setHeader}
                    preview={preview}
                    effectiveImportableRows={effectiveImportableRows}
                    enums={enums}
                    errors={errors}
                />
            )}
        </StandardModal>
    );
}

// ============================================================
// STEP 2 — Reconcile
// ============================================================
function ReconcileStep({ preview, mapping, updateMapping }) {
    const hasUnresolved = preview.needs_reconciliation > 0;
    const hasRejected = preview.rejected_rows > 0;

    return (
        <>
            <StandardModal.Section title="Resumo da análise">
                <div className="grid grid-cols-4 gap-3">
                    <SummaryCard
                        label="Linhas totais"
                        value={preview.total_rows}
                        color="gray"
                    />
                    <SummaryCard
                        label="Válidas"
                        value={preview.valid_rows}
                        color="green"
                        icon={CheckCircleIcon}
                    />
                    <SummaryCard
                        label="Pendentes"
                        value={preview.needs_reconciliation}
                        color="amber"
                        icon={ExclamationTriangleIcon}
                    />
                    <SummaryCard
                        label="Rejeitadas"
                        value={preview.rejected_rows}
                        color="red"
                        icon={XCircleIcon}
                    />
                </div>

                <div className="mt-3 bg-indigo-50 border border-indigo-200 rounded p-3 text-sm text-indigo-900">
                    <strong>Total previsto:</strong>{' '}
                    {BRL.format(preview.totals?.grand_total || 0)}{' '}
                    <span className="text-xs text-indigo-600 ml-2">
                        (considera apenas linhas válidas, pendentes ainda não somadas)
                    </span>
                </div>
            </StandardModal.Section>

            {hasUnresolved && (
                <StandardModal.Section title="Códigos ausentes no cadastro">
                    <p className="text-xs text-gray-600 mb-3">
                        Para cada código da planilha que não foi encontrado no cadastro,
                        escolha um registro existente para mapear. Códigos sem mapeamento
                        farão as linhas correspondentes serem ignoradas no import.
                    </p>

                    {Object.entries(preview.unresolved_summary || {}).map(([type, list]) => {
                        if (list.length === 0) return null;
                        return (
                            <div key={type} className="mb-4">
                                <h4 className="text-sm font-semibold text-gray-800 mb-2 flex items-center gap-2">
                                    <span>{FK_TYPE_ICONS[type]}</span>
                                    <span>{FK_TYPE_LABELS[type]}</span>
                                    <span className="text-xs font-normal text-gray-500">
                                        ({list.length} código{list.length > 1 ? 's' : ''} ausente{list.length > 1 ? 's' : ''})
                                    </span>
                                </h4>
                                <div className="border border-gray-200 rounded divide-y divide-gray-200">
                                    {list.map((entry) => (
                                        <UnresolvedRow
                                            key={entry.code}
                                            type={type}
                                            entry={entry}
                                            selected={mapping[type]?.[entry.code] || ''}
                                            onChange={(id) => updateMapping(type, entry.code, id)}
                                        />
                                    ))}
                                </div>
                            </div>
                        );
                    })}
                </StandardModal.Section>
            )}

            {hasRejected && (
                <StandardModal.Section title={`Linhas rejeitadas (${preview.rejected_rows})`}>
                    <p className="text-xs text-gray-600 mb-2">
                        Essas linhas não passarão para o import. Corrija na planilha e
                        faça novo upload se necessário.
                    </p>
                    <div className="max-h-48 overflow-y-auto border border-gray-200 rounded">
                        <table className="min-w-full text-xs">
                            <thead className="bg-gray-100 sticky top-0">
                                <tr>
                                    <th className="px-2 py-1.5 text-left font-medium">Linha</th>
                                    <th className="px-2 py-1.5 text-left font-medium">Motivos</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200">
                                {preview.rows
                                    .filter((r) => r.status === 'rejected')
                                    .map((row, i) => (
                                        <tr key={i}>
                                            <td className="px-2 py-1.5 font-mono">{row.row_number}</td>
                                            <td className="px-2 py-1.5 text-red-700">
                                                {row.errors.join('; ')}
                                            </td>
                                        </tr>
                                    ))}
                            </tbody>
                        </table>
                    </div>
                </StandardModal.Section>
            )}

            {!hasUnresolved && !hasRejected && (
                <StandardModal.Section title="Tudo certo">
                    <div className="bg-green-50 border border-green-200 rounded p-4 text-sm text-green-800 flex items-start gap-2">
                        <CheckCircleIcon className="w-5 h-5 shrink-0 mt-0.5" />
                        <div>
                            <strong>Todas as linhas estão prontas para importar.</strong>
                            <p className="text-xs mt-1">
                                Nenhum código ausente ou erro estrutural. Clique em
                                "Continuar" para confirmar o upload.
                            </p>
                        </div>
                    </div>
                </StandardModal.Section>
            )}
        </>
    );
}

function UnresolvedRow({ type, entry, selected, onChange }) {
    const hasSuggestions = entry.suggestions && entry.suggestions.length > 0;

    return (
        <div className="p-3 flex items-center gap-3">
            <div className="flex-shrink-0 w-48">
                <code className="font-mono text-sm text-gray-900 bg-gray-100 px-2 py-0.5 rounded">
                    {entry.code}
                </code>
                <span className="block text-xs text-gray-500 mt-0.5">
                    {entry.row_count} linha{entry.row_count > 1 ? 's' : ''}
                </span>
            </div>

            <div className="flex-1">
                {hasSuggestions ? (
                    <>
                        <select
                            value={selected}
                            onChange={(e) => onChange(e.target.value)}
                            className="w-full rounded-md border-gray-300 shadow-sm text-sm"
                        >
                            <option value="">— Ignorar (linhas serão puladas) —</option>
                            {entry.suggestions.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.code} · {s.name} (distância {s.distance})
                                </option>
                            ))}
                        </select>
                        <p className="text-xs text-gray-500 mt-1">
                            Sugestões por similaridade do código. Melhor candidato
                            pré-selecionado.
                        </p>
                    </>
                ) : (
                    <>
                        <div className="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded p-2">
                            Sem sugestões próximas. Cadastre o item em{' '}
                            {FK_TYPE_LABELS[type]} antes ou deixe para pular as linhas.
                        </div>
                    </>
                )}
            </div>
        </div>
    );
}

function SummaryCard({ label, value, color, icon: Icon }) {
    const colors = {
        gray: 'bg-gray-50 border-gray-200 text-gray-700',
        green: 'bg-green-50 border-green-200 text-green-700',
        amber: 'bg-amber-50 border-amber-200 text-amber-800',
        red: 'bg-red-50 border-red-200 text-red-700',
    };

    return (
        <div className={`rounded p-3 border ${colors[color]}`}>
            <div className="flex items-center gap-1.5">
                {Icon && <Icon className="w-4 h-4" />}
                <p className="text-xs uppercase font-semibold">{label}</p>
            </div>
            <p className="text-2xl font-bold mt-1">{value}</p>
        </div>
    );
}

// ============================================================
// STEP 3 — Confirm
// ============================================================
function ConfirmStep({ header, setHeader, preview, effectiveImportableRows, enums, errors }) {
    return (
        <>
            <StandardModal.Section title="Confirmação do import">
                <div className="bg-indigo-50 border border-indigo-200 rounded p-4 mb-4">
                    <div className="grid grid-cols-3 gap-4 text-sm">
                        <div>
                            <p className="text-xs uppercase font-semibold text-indigo-700">
                                Linhas a importar
                            </p>
                            <p className="text-2xl font-bold text-indigo-900">
                                {effectiveImportableRows}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs uppercase font-semibold text-indigo-700">
                                Total previsto
                            </p>
                            <p className="text-lg font-bold text-indigo-900 font-mono">
                                {BRL.format(preview?.totals?.grand_total || 0)}
                            </p>
                            <p className="text-xs text-indigo-600">(apenas linhas válidas)</p>
                        </div>
                        <div>
                            <p className="text-xs uppercase font-semibold text-indigo-700">
                                Linhas que serão puladas
                            </p>
                            <p className="text-2xl font-bold text-amber-700">
                                {(preview?.total_rows || 0) - effectiveImportableRows}
                            </p>
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Ano *
                        </label>
                        <input
                            type="number"
                            value={header.year}
                            onChange={(e) =>
                                setHeader({ ...header, year: parseInt(e.target.value) || '' })
                            }
                            min={2000}
                            max={2100}
                            className="w-full rounded-md border-gray-300 shadow-sm"
                        />
                        {errors.year && <p className="mt-1 text-xs text-red-600">{errors.year}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Escopo *
                        </label>
                        <input
                            type="text"
                            value={header.scope_label}
                            onChange={(e) => setHeader({ ...header, scope_label: e.target.value })}
                            placeholder="Ex: Administrativo, TI, Geral"
                            maxLength={100}
                            className="w-full rounded-md border-gray-300 shadow-sm"
                        />
                        {errors.scope_label && (
                            <p className="mt-1 text-xs text-red-600">{errors.scope_label}</p>
                        )}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Tipo *
                        </label>
                        <select
                            value={header.upload_type}
                            onChange={(e) => setHeader({ ...header, upload_type: e.target.value })}
                            className="w-full rounded-md border-gray-300 shadow-sm"
                        >
                            {Object.entries(enums.uploadTypes || { novo: 'Novo orçamento', ajuste: 'Ajuste' }).map(
                                ([k, v]) => (
                                    <option key={k} value={k}>{v}</option>
                                )
                            )}
                        </select>
                        {errors.upload_type && (
                            <p className="mt-1 text-xs text-red-600">{errors.upload_type}</p>
                        )}
                        <p className="text-xs text-gray-500 mt-1">
                            {header.upload_type === 'novo'
                                ? 'Incrementa versão principal (1.0 → 2.0)'
                                : 'Incrementa sub-versão (1.0 → 1.01)'}
                        </p>
                    </div>
                </div>

                <div className="mt-4">
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                        Observações
                    </label>
                    <textarea
                        value={header.notes}
                        onChange={(e) => setHeader({ ...header, notes: e.target.value })}
                        rows={2}
                        maxLength={2000}
                        className="w-full rounded-md border-gray-300 shadow-sm"
                        placeholder="Contexto opcional sobre este upload..."
                    />
                </div>

                {errors.file && (
                    <div className="mt-3 bg-red-50 border border-red-200 rounded p-3 text-sm text-red-700 flex items-start gap-2">
                        <ExclamationTriangleIcon className="w-5 h-5 shrink-0 mt-0.5" />
                        <span>{errors.file}</span>
                    </div>
                )}
            </StandardModal.Section>

            <StandardModal.Section title="Atenção">
                <div className="bg-amber-50 border border-amber-200 rounded p-3 text-sm text-amber-800 flex items-start gap-2">
                    <ExclamationTriangleIcon className="w-5 h-5 shrink-0 mt-0.5" />
                    <div>
                        <p className="font-medium mb-1">Se já existe versão ativa para este ano + escopo:</p>
                        <ul className="text-xs space-y-0.5 list-disc list-inside">
                            <li>
                                Tipo <strong>"Novo orçamento"</strong>: incrementa major, desativa a anterior automaticamente
                            </li>
                            <li>
                                Tipo <strong>"Ajuste"</strong>: incrementa minor, desativa a anterior automaticamente
                            </li>
                            <li>
                                Primeiro upload para o ano: sempre começa em <strong>1.0</strong>
                            </li>
                        </ul>
                    </div>
                </div>
            </StandardModal.Section>
        </>
    );
}
