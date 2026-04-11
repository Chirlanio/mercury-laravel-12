import { useState, useEffect, useMemo } from 'react';
import { useForm } from '@inertiajs/react';
import StandardModal from '@/Components/StandardModal';

const TYPE_OPTIONS = [
    { value: 'dismissal', label: 'Desligamento', icon: '🔴', desc: 'Desligamento de funcionário' },
    { value: 'promotion', label: 'Promoção', icon: '🟣', desc: 'Promoção de cargo' },
    { value: 'transfer', label: 'Transferência', icon: '🔵', desc: 'Transferência entre lojas' },
    { value: 'reactivation', label: 'Reativação', icon: '🟢', desc: 'Reativação de funcionário' },
];

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

export default function CreateMovementModal({ show, onClose, selects }) {
    const [selectedType, setSelectedType] = useState('');
    const [selectedStoreId, setSelectedStoreId] = useState('');
    const [integrationData, setIntegrationData] = useState(null);

    const form = useForm({
        type: '', employee_id: '', store_id: '', observation: '',
        requester_id: '', request_area_id: '',
        // Dismissal
        last_day_worked: '', contact: '', email: '',
        contract_type: '', dismissal_subtype: '', early_warning: '',
        fixed_fund: '', open_vacancy: false, reason_ids: [],
        access_power_bi: false, access_zznet: false, access_cigam: false, access_camera: false,
        access_deskfy: false, access_meu_atendimento: false, access_dito: false, access_notebook: false,
        access_email_corporate: false, access_parking_card: false, access_parking_shopping: false,
        access_key_office: false, access_key_store: false, access_instagram: false,
        activate_it: false, activate_operation: false, deactivate_instagram: false, activate_hr: false,
        // Promotion
        effective_date: '', new_position_id: '',
        // Transfer
        origin_store_id: '', destination_store_id: '',
        // Reactivation
        reactivation_date: '',
    });

    // Filter employees by selected store
    const filteredEmployees = useMemo(() => {
        if (selectedType === 'reactivation') {
            return selectedStoreId
                ? (selects.inactiveEmployees || []).filter(e => e.store_id === selectedStoreId)
                : (selects.inactiveEmployees || []);
        }
        return selectedStoreId
            ? (selects.employees || []).filter(e => e.store_id === selectedStoreId)
            : (selects.employees || []);
    }, [selectedStoreId, selectedType, selects]);

    const handleTypeSelect = (type) => {
        setSelectedType(type);
        form.setData('type', type);
        setIntegrationData(null);
        setSelectedStoreId('');
    };

    const handleStoreChange = (storeCode) => {
        setSelectedStoreId(storeCode);
        form.setData(prev => ({
            ...prev,
            store_id: storeCode,
            employee_id: '',
            origin_store_id: selectedType === 'transfer' ? storeCode : prev.origin_store_id,
        }));
        setIntegrationData(null);
    };

    const handleEmployeeChange = (employeeId) => {
        form.setData('employee_id', employeeId);
        // Fetch integration data for dismissals
        if (selectedType === 'dismissal' && employeeId) {
            fetch(route('personnel-movements.integration-data', employeeId))
                .then(res => res.json())
                .then(data => setIntegrationData(data))
                .catch(() => {});
        }
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        form.post(route('personnel-movements.store'), {
            onSuccess: () => {
                handleClose();
            },
        });
    };

    const handleClose = () => {
        form.reset();
        setSelectedType('');
        setSelectedStoreId('');
        setIntegrationData(null);
        onClose();
    };

    const toggleReason = (id) => {
        const current = form.data.reason_ids || [];
        const updated = current.includes(id) ? current.filter(r => r !== id) : [...current, id];
        form.setData('reason_ids', updated);
    };

    if (!show) return null;

    return (
        <StandardModal
            show={show}
            onClose={handleClose}
            title="Nova Movimentação de Pessoal"
            headerColor="bg-indigo-600"
            onSubmit={handleSubmit}
        >
            <div className="p-6 space-y-5">
                {/* Type selector */}
                {!selectedType && (
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-3">Tipo de Movimentação</label>
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                            {TYPE_OPTIONS.map(opt => (
                                <button
                                    key={opt.value}
                                    type="button"
                                    onClick={() => handleTypeSelect(opt.value)}
                                    className="p-4 border-2 border-gray-200 rounded-lg hover:border-indigo-500 hover:bg-indigo-50 transition text-center"
                                >
                                    <div className="text-2xl mb-1">{opt.icon}</div>
                                    <div className="font-medium text-sm">{opt.label}</div>
                                    <div className="text-xs text-gray-500 mt-1">{opt.desc}</div>
                                </button>
                            ))}
                        </div>
                    </div>
                )}

                {selectedType && (
                    <>
                        {/* Type indicator + back */}
                        <div className="flex items-center gap-2 mb-2">
                            <button type="button" onClick={() => { setSelectedType(''); setSelectedStoreId(''); form.reset(); }} className="text-sm text-indigo-600 hover:underline">
                                ← Alterar tipo
                            </button>
                            <span className="text-sm font-medium text-gray-700">
                                {TYPE_OPTIONS.find(t => t.value === selectedType)?.icon} {TYPE_OPTIONS.find(t => t.value === selectedType)?.label}
                            </span>
                        </div>

                        {/* Common fields: Store FIRST, then Employee */}
                        <StandardModal.Section title="Identificação">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Loja *</label>
                                    <select value={selectedStoreId} onChange={e => handleStoreChange(e.target.value)}
                                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="">Selecione a loja...</option>
                                        {(selects.stores || []).map(s => <option key={s.id} value={s.code}>{s.code} - {s.name}</option>)}
                                    </select>
                                    {form.errors.store_id && <p className="mt-1 text-sm text-red-600">{form.errors.store_id}</p>}
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Funcionário *</label>
                                    <select value={form.data.employee_id} onChange={e => handleEmployeeChange(e.target.value)}
                                        disabled={!selectedStoreId}
                                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-100">
                                        <option value="">{selectedStoreId ? 'Selecione o funcionário...' : 'Selecione a loja primeiro'}</option>
                                        {filteredEmployees.map(e => <option key={e.id} value={e.id}>{e.name}</option>)}
                                    </select>
                                    {form.errors.employee_id && <p className="mt-1 text-sm text-red-600">{form.errors.employee_id}</p>}
                                </div>
                            </div>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Solicitante</label>
                                    <select value={form.data.requester_id} onChange={e => form.setData('requester_id', e.target.value)}
                                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="">Selecione...</option>
                                        {(selects.employees || []).map(e => <option key={e.id} value={e.id}>{e.name}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Área Solicitante</label>
                                    <select value={form.data.request_area_id} onChange={e => form.setData('request_area_id', e.target.value)}
                                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option value="">Selecione...</option>
                                        {(selects.sectors || []).map(s => <option key={s.id} value={s.id}>{s.name}</option>)}
                                    </select>
                                </div>
                            </div>
                        </StandardModal.Section>

                        {/* === DISMISSAL FIELDS === */}
                        {selectedType === 'dismissal' && (
                            <>
                                <StandardModal.Section title="Dados do Desligamento">
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Último Dia Trabalhado *</label>
                                            <input type="date" value={form.data.last_day_worked} onChange={e => form.setData('last_day_worked', e.target.value)}
                                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                            {form.errors.last_day_worked && <p className="mt-1 text-sm text-red-600">{form.errors.last_day_worked}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Contato Pessoal</label>
                                            <input type="text" value={form.data.contact} onChange={e => form.setData('contact', e.target.value)}
                                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="(11) 99999-9999" />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">E-mail Pessoal</label>
                                            <input type="email" value={form.data.email} onChange={e => form.setData('email', e.target.value)}
                                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-2">Tipo de Contrato *</label>
                                            <div className="space-y-1.5">
                                                {CONTRACT_TYPES.map(ct => (
                                                    <label key={ct.value} className="flex items-center gap-2">
                                                        <input type="radio" name="contract_type" value={ct.value} checked={form.data.contract_type === ct.value}
                                                            onChange={e => form.setData('contract_type', e.target.value)} className="text-indigo-600 focus:ring-indigo-500" />
                                                        <span className="text-sm">{ct.label}</span>
                                                    </label>
                                                ))}
                                            </div>
                                            {form.errors.contract_type && <p className="mt-1 text-sm text-red-600">{form.errors.contract_type}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-2">Tipo de Desligamento *</label>
                                            <div className="space-y-1.5">
                                                {DISMISSAL_SUBTYPES.map(ds => (
                                                    <label key={ds.value} className="flex items-center gap-2">
                                                        <input type="radio" name="dismissal_subtype" value={ds.value} checked={form.data.dismissal_subtype === ds.value}
                                                            onChange={e => form.setData('dismissal_subtype', e.target.value)} className="text-indigo-600 focus:ring-indigo-500" />
                                                        <span className="text-sm">{ds.label}</span>
                                                    </label>
                                                ))}
                                            </div>
                                            {form.errors.dismissal_subtype && <p className="mt-1 text-sm text-red-600">{form.errors.dismissal_subtype}</p>}
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-2">Aviso Prévio *</label>
                                            <div className="space-y-1.5">
                                                {EARLY_WARNING_OPTIONS.map(ew => (
                                                    <label key={ew.value} className="flex items-center gap-2">
                                                        <input type="radio" name="early_warning" value={ew.value} checked={form.data.early_warning === ew.value}
                                                            onChange={e => form.setData('early_warning', e.target.value)} className="text-indigo-600 focus:ring-indigo-500" />
                                                        <span className="text-sm">{ew.label}</span>
                                                    </label>
                                                ))}
                                            </div>
                                            {form.errors.early_warning && <p className="mt-1 text-sm text-red-600">{form.errors.early_warning}</p>}
                                        </div>
                                    </div>
                                </StandardModal.Section>

                                {/* Motivos do Desligamento */}
                                {(selects.dismissalReasons || []).length > 0 && (
                                    <StandardModal.Section title="Motivo do Desligamento">
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                                            {selects.dismissalReasons.map(r => (
                                                <label key={r.id} className="flex items-center gap-2 cursor-pointer">
                                                    <input type="radio" name="dismissal_reason" value={r.id}
                                                        checked={(form.data.reason_ids || []).includes(r.id)}
                                                        onChange={() => form.setData('reason_ids', [r.id])}
                                                        className="text-indigo-600 focus:ring-indigo-500" />
                                                    <span className="text-sm">{r.name}</span>
                                                </label>
                                            ))}
                                        </div>
                                    </StandardModal.Section>
                                )}

                                {/* Integration data */}
                                {integrationData && (
                                    <StandardModal.Section title="Dados Integrados (automático)">
                                        <div className="grid grid-cols-3 gap-4">
                                            <div className="bg-yellow-50 rounded-lg p-3 text-center">
                                                <div className="text-2xl font-bold text-yellow-700">{integrationData.fouls}</div>
                                                <div className="text-xs text-yellow-600">Faltas</div>
                                            </div>
                                            <div className="bg-blue-50 rounded-lg p-3 text-center">
                                                <div className="text-2xl font-bold text-blue-700">{integrationData.days_off}</div>
                                                <div className="text-xs text-blue-600">Folgas Pendentes</div>
                                            </div>
                                            <div className="bg-purple-50 rounded-lg p-3 text-center">
                                                <div className="text-2xl font-bold text-purple-700">{integrationData.overtime_hours}</div>
                                                <div className="text-xs text-purple-600">Horas Extras Não Pagas</div>
                                            </div>
                                        </div>
                                    </StandardModal.Section>
                                )}

                                <StandardModal.Section title="Informações Financeiras">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Fundo de Caixa (R$)</label>
                                            <input type="number" step="0.01" value={form.data.fixed_fund} onChange={e => form.setData('fixed_fund', e.target.value)}
                                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="0,00" />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-1">Vaga de Substituição</label>
                                            <div className="mt-2">
                                                <label className="flex items-center gap-2 cursor-pointer">
                                                    <input type="checkbox" checked={form.data.open_vacancy} onChange={e => form.setData('open_vacancy', e.target.checked)}
                                                        className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                                    <span className="text-sm">Abrir vaga de substituição automaticamente</span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </StandardModal.Section>

                                {/* Access control */}
                                <StandardModal.Section title="Controle de Acessos">
                                    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                                        {ACCESS_FIELDS.map(({ field, label }) => (
                                            <label key={field} className="flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox" checked={form.data[field] || false} onChange={e => form.setData(field, e.target.checked)}
                                                    className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                                <span className="text-sm">{label}</span>
                                            </label>
                                        ))}
                                    </div>
                                    <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-3 pt-3 border-t">
                                        {ACTIVATION_FIELDS.map(({ field, label }) => (
                                            <label key={field} className="flex items-center gap-2 cursor-pointer">
                                                <input type="checkbox" checked={form.data[field] || false} onChange={e => form.setData(field, e.target.checked)}
                                                    className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                                <span className="text-sm">{label}</span>
                                            </label>
                                        ))}
                                    </div>
                                </StandardModal.Section>
                            </>
                        )}

                        {/* === PROMOTION FIELDS === */}
                        {selectedType === 'promotion' && (
                            <StandardModal.Section title="Dados da Promoção">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Novo Cargo *</label>
                                        <select value={form.data.new_position_id} onChange={e => form.setData('new_position_id', e.target.value)}
                                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                            <option value="">Selecione...</option>
                                            {(selects.positions || []).map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
                                        </select>
                                        {form.errors.new_position_id && <p className="mt-1 text-sm text-red-600">{form.errors.new_position_id}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Data Efetiva *</label>
                                        <input type="date" value={form.data.effective_date} onChange={e => form.setData('effective_date', e.target.value)}
                                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                        {form.errors.effective_date && <p className="mt-1 text-sm text-red-600">{form.errors.effective_date}</p>}
                                    </div>
                                </div>
                            </StandardModal.Section>
                        )}

                        {/* === TRANSFER FIELDS === */}
                        {selectedType === 'transfer' && (
                            <StandardModal.Section title="Dados da Transferência">
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Loja Origem</label>
                                        <input type="text" value={selectedStoreId} readOnly className="w-full rounded-md border-gray-300 bg-gray-50 sm:text-sm" />
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Loja Destino *</label>
                                        <select value={form.data.destination_store_id} onChange={e => form.setData('destination_store_id', e.target.value)}
                                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                            <option value="">Selecione...</option>
                                            {(selects.stores || []).filter(s => s.code !== selectedStoreId).map(s => (
                                                <option key={s.id} value={s.code}>{s.code} - {s.name}</option>
                                            ))}
                                        </select>
                                        {form.errors.destination_store_id && <p className="mt-1 text-sm text-red-600">{form.errors.destination_store_id}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Data Efetiva *</label>
                                        <input type="date" value={form.data.effective_date} onChange={e => form.setData('effective_date', e.target.value)}
                                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                        {form.errors.effective_date && <p className="mt-1 text-sm text-red-600">{form.errors.effective_date}</p>}
                                    </div>
                                </div>
                            </StandardModal.Section>
                        )}

                        {/* === REACTIVATION FIELDS === */}
                        {selectedType === 'reactivation' && (
                            <StandardModal.Section title="Dados da Reativação">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Data de Reativação *</label>
                                        <input type="date" value={form.data.reactivation_date} onChange={e => form.setData('reactivation_date', e.target.value)}
                                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                        {form.errors.reactivation_date && <p className="mt-1 text-sm text-red-600">{form.errors.reactivation_date}</p>}
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">Novo Cargo (opcional)</label>
                                        <select value={form.data.new_position_id} onChange={e => form.setData('new_position_id', e.target.value)}
                                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                            <option value="">(Mantém cargo anterior)</option>
                                            {(selects.positions || []).map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
                                        </select>
                                    </div>
                                </div>
                            </StandardModal.Section>
                        )}

                        {/* Observation (common) */}
                        <StandardModal.Section title="Observações">
                            <textarea value={form.data.observation} onChange={e => form.setData('observation', e.target.value)} rows={3}
                                maxLength={5000}
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                placeholder="Justificativa ou observações..." />
                            <p className="text-xs text-gray-400 mt-1 text-right">{(form.data.observation || '').length}/5000</p>
                        </StandardModal.Section>
                    </>
                )}
            </div>

            {selectedType && (
                <StandardModal.Footer
                    onCancel={handleClose}
                    onSubmit={handleSubmit}
                    submitLabel="Criar Movimentação"
                    processing={form.processing}
                    submitDisabled={!form.data.employee_id || !selectedStoreId}
                />
            )}
        </StandardModal>
    );
}
