import { useState, useEffect, useMemo } from 'react';
import { router } from '@inertiajs/react';
import StandardModal from '@/Components/StandardModal';

const TYPE_LABELS = {
    dismissal: 'Desligamento', promotion: 'Promoção',
    transfer: 'Transferência', reactivation: 'Reativação',
};

const CONTRACT_TYPES = [
    { value: 'clt', label: 'CLT (Efetivo)' },
    { value: 'trial', label: 'Experiência' },
    { value: 'intern', label: 'Estagiário' },
    { value: 'apprentice', label: 'Aprendiz' },
];

const DISMISSAL_SUBTYPES = [
    { value: 'company_initiative', label: 'Iniciativa da Empresa' },
    { value: 'employee_resignation', label: 'Pedido de Demissão' },
    { value: 'trial_end', label: 'Término de Experiência' },
    { value: 'just_cause', label: 'Justa Causa' },
];

const EARLY_WARNING_OPTIONS = [
    { value: 'worked', label: 'Trabalhado' },
    { value: 'indemnified', label: 'Indenizado' },
    { value: 'dispensed', label: 'Dispensado' },
];

const ACCESS_FIELDS = [
    { field: 'access_power_bi', label: 'Power BI' }, { field: 'access_zznet', label: 'ZZNet' },
    { field: 'access_cigam', label: 'CIGAM' }, { field: 'access_camera', label: 'Câmeras' },
    { field: 'access_deskfy', label: 'Deskfy' }, { field: 'access_meu_atendimento', label: 'Meu Atendimento' },
    { field: 'access_dito', label: 'Dito' }, { field: 'access_notebook', label: 'Notebook' },
    { field: 'access_email_corporate', label: 'E-mail Corporativo' }, { field: 'access_parking_card', label: 'Cartão Estacionamento' },
    { field: 'access_parking_shopping', label: 'Estac. Shopping' }, { field: 'access_key_office', label: 'Chave Escritório' },
    { field: 'access_key_store', label: 'Chave Loja' }, { field: 'access_instagram', label: 'Instagram' },
];

const ACTIVATION_FIELDS = [
    { field: 'activate_it', label: 'Ativar TI' }, { field: 'activate_operation', label: 'Ativar Operações' },
    { field: 'deactivate_instagram', label: 'Desativar Instagram' }, { field: 'activate_hr', label: 'Ativar RH' },
];

export default function EditMovementModal({ show, onClose, movementId, selects }) {
    const [form, setForm] = useState({});
    const [movement, setMovement] = useState(null);
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (show && movementId) {
            setLoading(true);
            fetch(route('personnel-movements.edit', movementId))
                .then(res => res.json())
                .then(data => {
                    const m = data.movement;
                    setMovement(m);
                    setForm({
                        observation: m.observation || '',
                        requester_id: m.requester_id || '',
                        request_area_id: m.request_area_id || '',
                        // Dismissal
                        last_day_worked: m.last_day_worked?.split('T')[0] || '',
                        contact: m.contact || '',
                        email: m.email || '',
                        contract_type: m.contract_type || '',
                        dismissal_subtype: m.dismissal_subtype || '',
                        early_warning: m.early_warning || '',
                        fixed_fund: m.fixed_fund || '',
                        open_vacancy: m.open_vacancy || false,
                        reason_ids: m.reason_ids || [],
                        ...Object.fromEntries(ACCESS_FIELDS.map(f => [f.field, m[f.field] || false])),
                        ...Object.fromEntries(ACTIVATION_FIELDS.map(f => [f.field, m[f.field] || false])),
                        // Promotion
                        effective_date: m.effective_date?.split('T')[0] || '',
                        new_position_id: m.new_position_id || '',
                        // Transfer
                        destination_store_id: m.destination_store_id || '',
                        // Reactivation
                        reactivation_date: m.reactivation_date?.split('T')[0] || '',
                    });
                    setErrors({});
                    setLoading(false);
                })
                .catch(() => setLoading(false));
        }
    }, [show, movementId]);

    if (!show) return null;

    const type = movement?.type;

    const setField = (field, value) => {
        setForm(prev => ({ ...prev, [field]: value }));
        if (errors[field]) {
            setErrors(prev => { const n = { ...prev }; delete n[field]; return n; });
        }
    };

    const handleSubmit = (e) => {
        e?.preventDefault();
        setProcessing(true);
        setErrors({});

        router.put(route('personnel-movements.update', movementId), form, {
            onSuccess: () => {
                setProcessing(false);
                onClose();
            },
            onError: (errs) => {
                setProcessing(false);
                setErrors(errs);
            },
        });
    };

    const headerColor = {
        dismissal: 'bg-red-600', promotion: 'bg-purple-600',
        transfer: 'bg-blue-600', reactivation: 'bg-green-600',
    }[type] || 'bg-indigo-600';

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title={`Editar ${TYPE_LABELS[type] || 'Movimentação'} — ${movement?.employee?.name || ''}`}
            headerColor={headerColor}
            loading={loading}
            onSubmit={handleSubmit}
        >
            {movement && (
                <>
                    <div className="p-6 space-y-5 overflow-y-auto flex-1">
                        {/* Info fixa */}
                        <StandardModal.Section title="Identificação">
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <StandardModal.Field label="Funcionário" value={movement.employee?.name} />
                                <StandardModal.Field label="Loja" value={movement.store_id} />
                                <StandardModal.Field label="Tipo" value={TYPE_LABELS[type]} />
                            </div>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Solicitante</label>
                                    <select value={form.requester_id} onChange={e => setField('requester_id', e.target.value)}
                                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="">Selecione...</option>
                                        {(selects.employees || []).map(e => <option key={e.id} value={e.id}>{e.name}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Área Solicitante</label>
                                    <select value={form.request_area_id} onChange={e => setField('request_area_id', e.target.value)}
                                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="">Selecione...</option>
                                        {(selects.sectors || []).map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
                                    </select>
                                </div>
                            </div>
                        </StandardModal.Section>

                        {/* === DISMISSAL === */}
                        {type === 'dismissal' && (
                            <>
                                <StandardModal.Section title="Dados do Desligamento">
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Último Dia Trabalhado *</label>
                                            <input type="date" value={form.last_day_worked} onChange={e => setField('last_day_worked', e.target.value)}
                                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                            {errors.last_day_worked && <p className="mt-1 text-sm text-red-600">{errors.last_day_worked}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Contato Pessoal</label>
                                            <input type="text" value={form.contact} onChange={e => setField('contact', e.target.value)}
                                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="(11) 99999-9999" />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">E-mail Pessoal</label>
                                            <input type="email" value={form.email} onChange={e => setField('email', e.target.value)}
                                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                        </div>
                                    </div>
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-2">Tipo de Contrato *</label>
                                            <div className="space-y-1.5">
                                                {CONTRACT_TYPES.map(ct => (
                                                    <label key={ct.value} className="flex items-center gap-2">
                                                        <input type="radio" name="edit_contract_type" value={ct.value} checked={form.contract_type === ct.value}
                                                            onChange={e => setField('contract_type', e.target.value)} className="text-indigo-600 focus:ring-indigo-500" />
                                                        <span className="text-sm">{ct.label}</span>
                                                    </label>
                                                ))}
                                            </div>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-2">Tipo de Desligamento *</label>
                                            <div className="space-y-1.5">
                                                {DISMISSAL_SUBTYPES.map(ds => (
                                                    <label key={ds.value} className="flex items-center gap-2">
                                                        <input type="radio" name="edit_dismissal_subtype" value={ds.value} checked={form.dismissal_subtype === ds.value}
                                                            onChange={e => setField('dismissal_subtype', e.target.value)} className="text-indigo-600 focus:ring-indigo-500" />
                                                        <span className="text-sm">{ds.label}</span>
                                                    </label>
                                                ))}
                                            </div>
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-2">Aviso Prévio *</label>
                                            <div className="space-y-1.5">
                                                {EARLY_WARNING_OPTIONS.map(ew => (
                                                    <label key={ew.value} className="flex items-center gap-2">
                                                        <input type="radio" name="edit_early_warning" value={ew.value} checked={form.early_warning === ew.value}
                                                            onChange={e => setField('early_warning', e.target.value)} className="text-indigo-600 focus:ring-indigo-500" />
                                                        <span className="text-sm">{ew.label}</span>
                                                    </label>
                                                ))}
                                            </div>
                                        </div>
                                    </div>
                                </StandardModal.Section>

                                {(selects.dismissalReasons || []).length > 0 && (
                                    <StandardModal.Section title="Motivo do Desligamento">
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                                            {selects.dismissalReasons.map(r => (
                                                <label key={r.id} className="flex items-center gap-2 cursor-pointer">
                                                    <input type="radio" name="edit_dismissal_reason" value={r.id}
                                                        checked={(form.reason_ids || []).includes(r.id)}
                                                        onChange={() => setField('reason_ids', [r.id])}
                                                        className="text-indigo-600 focus:ring-indigo-500" />
                                                    <span className="text-sm">{r.name}</span>
                                                </label>
                                            ))}
                                        </div>
                                    </StandardModal.Section>
                                )}

                                <StandardModal.Section title="Informações Financeiras">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Fundo de Caixa (R$)</label>
                                            <input type="number" step="0.01" value={form.fixed_fund} onChange={e => setField('fixed_fund', e.target.value)}
                                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="0,00" />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Vaga de Substituição</label>
                                            <div className="mt-2">
                                                <label className="flex items-center gap-2 cursor-pointer">
                                                    <input type="checkbox" checked={form.open_vacancy || false} onChange={e => setField('open_vacancy', e.target.checked)}
                                                        className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                                    <span className="text-sm">Abrir vaga de substituição automaticamente</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </StandardModal.Section>

                                <StandardModal.Section title="Controle de Acessos">
                                    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                                        {ACCESS_FIELDS.map(({ field, label }) => (
                                            <label key={field} className="flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox" checked={form[field] || false} onChange={e => setField(field, e.target.checked)}
                                                    className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                                <span className="text-sm">{label}</span>
                                            </label>
                                        ))}
                                    </div>
                                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-3 pt-3 border-t">
                                        {ACTIVATION_FIELDS.map(({ field, label }) => (
                                            <label key={field} className="flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox" checked={form[field] || false} onChange={e => setField(field, e.target.checked)}
                                                    className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                                <span className="text-sm">{label}</span>
                                            </label>
                                        ))}
                                    </div>
                                </StandardModal.Section>
                            </>
                        )}

                        {/* === PROMOTION === */}
                        {type === 'promotion' && (
                            <StandardModal.Section title="Dados da Promoção">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Novo Cargo *</label>
                                        <select value={form.new_position_id} onChange={e => setField('new_position_id', e.target.value)}
                                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                            <option value="">Selecione...</option>
                                            {(selects.positions || []).map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
                                        </select>
                                        {errors.new_position_id && <p className="mt-1 text-sm text-red-600">{errors.new_position_id}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Data Efetiva *</label>
                                        <input type="date" value={form.effective_date} onChange={e => setField('effective_date', e.target.value)}
                                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                        {errors.effective_date && <p className="mt-1 text-sm text-red-600">{errors.effective_date}</p>}
                                    </div>
                                </div>
                            </StandardModal.Section>
                        )}

                        {/* === TRANSFER === */}
                        {type === 'transfer' && (
                            <StandardModal.Section title="Dados da Transferência">
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Loja Origem</label>
                                        <input type="text" value={movement.origin_store_id || ''} readOnly className="w-full rounded-md border-gray-300 bg-gray-50 sm:text-sm" />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Loja Destino *</label>
                                        <select value={form.destination_store_id} onChange={e => setField('destination_store_id', e.target.value)}
                                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                            <option value="">Selecione...</option>
                                            {(selects.stores || []).filter(s => s.code !== movement.origin_store_id).map(s => (
                                                <option key={s.id} value={s.code}>{s.code} - {s.name}</option>
                                            ))}
                                        </select>
                                        {errors.destination_store_id && <p className="mt-1 text-sm text-red-600">{errors.destination_store_id}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Data Efetiva *</label>
                                        <input type="date" value={form.effective_date} onChange={e => setField('effective_date', e.target.value)}
                                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                        {errors.effective_date && <p className="mt-1 text-sm text-red-600">{errors.effective_date}</p>}
                                    </div>
                                </div>
                            </StandardModal.Section>
                        )}

                        {/* === REACTIVATION === */}
                        {type === 'reactivation' && (
                            <StandardModal.Section title="Dados da Reativação">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Data de Reativação *</label>
                                        <input type="date" value={form.reactivation_date} onChange={e => setField('reactivation_date', e.target.value)}
                                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                        {errors.reactivation_date && <p className="mt-1 text-sm text-red-600">{errors.reactivation_date}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Novo Cargo (opcional)</label>
                                        <select value={form.new_position_id} onChange={e => setField('new_position_id', e.target.value)}
                                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                            <option value="">(Mantém cargo anterior)</option>
                                            {(selects.positions || []).map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
                                        </select>
                                    </div>
                                </div>
                            </StandardModal.Section>
                        )}

                        {/* Observation */}
                        <StandardModal.Section title="Observações">
                            <textarea value={form.observation} onChange={e => setField('observation', e.target.value)} rows={3}
                                maxLength={5000}
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                placeholder="Justificativa ou observações..." />
                            <p className="text-xs text-gray-400 mt-1 text-right">{(form.observation || '').length}/5000</p>
                        </StandardModal.Section>
                    </div>

                    <StandardModal.Footer
                        onCancel={onClose}
                        onSubmit={handleSubmit}
                        submitLabel="Salvar Alterações"
                        processing={processing}
                    />
                </>
            )}
        </StandardModal>
    );
}
