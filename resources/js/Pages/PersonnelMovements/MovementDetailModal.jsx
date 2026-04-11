import { useState, useEffect } from 'react';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';
import DismissalFollowUpSection from './DismissalFollowUpSection';

const ACCESS_LABELS = {
    access_power_bi: 'Power BI', access_zznet: 'ZZNet', access_cigam: 'CIGAM',
    access_camera: 'Câmeras', access_deskfy: 'Deskfy', access_meu_atendimento: 'Meu Atendimento',
    access_dito: 'Dito', access_notebook: 'Notebook', access_email_corporate: 'E-mail Corporativo',
    access_parking_card: 'Cartão Estacionamento', access_parking_shopping: 'Estacionamento Shopping',
    access_key_office: 'Chave Escritório', access_key_store: 'Chave Loja', access_instagram: 'Instagram',
};

const ACTIVATION_LABELS = {
    activate_it: 'Ativar TI', activate_operation: 'Ativar Operações',
    deactivate_instagram: 'Desativar Instagram', activate_hr: 'Ativar RH',
};

const TYPE_HEADER_COLORS = {
    dismissal: 'bg-red-600', promotion: 'bg-purple-600',
    transfer: 'bg-blue-600', reactivation: 'bg-green-600',
};

export default function MovementDetailModal({ show, onClose, movementId, canEdit = false }) {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (show && movementId) {
            setLoading(true);
            fetch(route('personnel-movements.show', movementId))
                .then(res => res.json())
                .then(json => { setData(json.movement); setLoading(false); })
                .catch(() => setLoading(false));
        } else {
            setData(null);
        }
    }, [show, movementId]);

    if (!show) return null;

    const m = data;

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title={m ? `${m.type_label} — ${m.employee?.name || ''}` : 'Carregando...'}
            headerColor={m ? (TYPE_HEADER_COLORS[m.type] || 'bg-gray-600') : 'bg-gray-600'}
            loading={loading}
        >
            {m && (
                <div className="p-4 space-y-6">
                    {/* General Info */}
                    <StandardModal.Section title="Informações Gerais">
                        <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
                            <StandardModal.Field label="Funcionário" value={m.employee?.name} />
                            <StandardModal.Field label="CPF" value={m.employee?.cpf} />
                            <StandardModal.Field label="Cargo" value={m.employee?.position} />
                            <StandardModal.Field label="Loja" value={m.store_name} />
                            <StandardModal.Field label="Data Efetiva" value={m.effective_date} />
                            <StandardModal.Field label="Admissão" value={m.employee?.admission_date} />
                            <StandardModal.Field label="Solicitante" value={m.requester_name} />
                            <StandardModal.Field label="Área" value={m.request_area} />
                            <StandardModal.Field label="Criado por" value={m.created_by} />
                        </div>
                        {m.observation && (
                            <div className="mt-3">
                                <label className="block text-xs font-medium text-gray-500">Observação</label>
                                <p className="text-sm text-gray-700 whitespace-pre-wrap">{m.observation}</p>
                            </div>
                        )}
                    </StandardModal.Section>

                    {/* Dismissal-specific */}
                    {m.type === 'dismissal' && (
                        <>
                            <StandardModal.Section title="Dados do Desligamento">
                                <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
                                    <StandardModal.Field label="Contato" value={m.contact} />
                                    <StandardModal.Field label="E-mail" value={m.email} />
                                    <StandardModal.Field label="Tipo Contrato" value={m.contract_type_label} />
                                    <StandardModal.Field label="Tipo Desligamento" value={m.dismissal_subtype_label} />
                                    <StandardModal.Field label="Aviso Prévio" value={m.early_warning_label} />
                                    <StandardModal.Field label="Último Dia" value={m.last_day_worked} />
                                    <StandardModal.Field label="Faltas" value={m.fouls} />
                                    <StandardModal.Field label="Folgas Pendentes" value={m.days_off} />
                                    <StandardModal.Field label="Horas Extras Não Pagas" value={m.overtime_hours} />
                                    <StandardModal.Field label="Fundo de Caixa" value={m.fixed_fund ? `R$ ${m.fixed_fund}` : '—'} />
                                    <StandardModal.Field label="Abrir Vaga" value={m.open_vacancy ? 'Sim' : 'Não'} />
                                </div>
                                {m.reasons?.length > 0 && (
                                    <div className="mt-3">
                                        <label className="block text-xs font-medium text-gray-500 mb-1">Motivos</label>
                                        <div className="flex flex-wrap gap-1">
                                            {m.reasons.map(r => (
                                                <StatusBadge key={r.id} variant="gray" size="sm">{r.name}</StatusBadge>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </StandardModal.Section>

                            <StandardModal.Section title="Controle de Acessos">
                                <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                    {Object.entries(m.access_fields || {}).map(([key, val]) => (
                                        <div key={key} className="flex items-center gap-2">
                                            <span className={`w-2 h-2 rounded-full ${val ? 'bg-green-500' : 'bg-gray-300'}`} />
                                            <span className="text-xs text-gray-700">{ACCESS_LABELS[key] || key}</span>
                                        </div>
                                    ))}
                                </div>
                                <div className="grid grid-cols-2 sm:grid-cols-3 gap-2 mt-3 pt-3 border-t">
                                    {Object.entries(m.activation_fields || {}).map(([key, val]) => (
                                        <div key={key} className="flex items-center gap-2">
                                            <span className={`w-2 h-2 rounded-full ${val ? 'bg-green-500' : 'bg-gray-300'}`} />
                                            <span className="text-xs text-gray-700">{ACTIVATION_LABELS[key] || key}</span>
                                        </div>
                                    ))}
                                </div>
                            </StandardModal.Section>

                            <StandardModal.Section title="Follow-up">
                                <DismissalFollowUpSection
                                    movementId={m.id}
                                    followUp={m.follow_up}
                                    editable={canEdit && m.status !== 'completed' && m.status !== 'cancelled'}
                                />
                            </StandardModal.Section>
                        </>
                    )}

                    {/* Promotion-specific */}
                    {m.type === 'promotion' && (
                        <StandardModal.Section title="Dados da Promoção">
                            <div className="grid grid-cols-2 gap-3">
                                <StandardModal.Field label="Novo Cargo" value={m.new_position_name} />
                                <StandardModal.Field label="Data Efetiva" value={m.effective_date} />
                            </div>
                        </StandardModal.Section>
                    )}

                    {/* Transfer-specific */}
                    {m.type === 'transfer' && (
                        <StandardModal.Section title="Dados da Transferência">
                            <div className="grid grid-cols-2 gap-3">
                                <StandardModal.Field label="Loja Origem" value={m.origin_store_name} />
                                <StandardModal.Field label="Loja Destino" value={m.destination_store_name} />
                                <StandardModal.Field label="Data Efetiva" value={m.effective_date} />
                            </div>
                        </StandardModal.Section>
                    )}

                    {/* Reactivation-specific */}
                    {m.type === 'reactivation' && (
                        <StandardModal.Section title="Dados da Reativação">
                            <div className="grid grid-cols-2 gap-3">
                                <StandardModal.Field label="Data Reativação" value={m.reactivation_date} />
                                <StandardModal.Field label="Novo Cargo" value={m.new_position_name || '(mantém atual)'} />
                            </div>
                        </StandardModal.Section>
                    )}

                    {/* Files */}
                    {m.files?.length > 0 && (
                        <StandardModal.Section title="Arquivos">
                            <div className="space-y-1">
                                {m.files.map(f => (
                                    <a key={f.id} href={f.file_path} target="_blank" rel="noopener noreferrer"
                                       className="flex items-center gap-2 text-sm text-indigo-600 hover:underline">
                                        <span>{f.file_name}</span>
                                        <span className="text-xs text-gray-400">({(f.file_size / 1024).toFixed(0)} KB)</span>
                                    </a>
                                ))}
                            </div>
                        </StandardModal.Section>
                    )}

                    {/* Timeline */}
                    {m.status_history?.length > 0 && (
                        <StandardModal.Section title="Histórico">
                            <StandardModal.Timeline
                                items={m.status_history.map(h => ({
                                    title: `${h.old_status_label || 'Criação'} → ${h.new_status_label}`,
                                    description: h.notes || '',
                                    meta: `${h.changed_by || ''} — ${h.created_at}`,
                                }))}
                            />
                        </StandardModal.Section>
                    )}
                </div>
            )}
        </StandardModal>
    );
}
