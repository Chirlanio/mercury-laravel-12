import { useState, useEffect } from 'react';
import { router } from '@inertiajs/react';
import { UserIcon, PencilIcon, CheckIcon, XMarkIcon } from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';
import Button from '@/Components/Button';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import { usePermissions } from '@/Hooks/usePermissions';
import { maskMoney, parseMoney } from '@/Hooks/useMasks';

/**
 * Modal de detalhes do cliente — read-only. Mostra dados pessoais,
 * contato, endereço, limites de consignação (M20) + as últimas 20
 * consignações.
 */
export default function CustomerDetailModal({ show, onClose, customer }) {
    const { hasPermission } = usePermissions();
    const canManageLimits = hasPermission('consignments.manage');

    const [editingLimits, setEditingLimits] = useState(false);
    const [maxItems, setMaxItems] = useState('');
    const [maxValue, setMaxValue] = useState('');
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (customer) {
            setMaxItems(customer.consignment_max_items ?? '');
            setMaxValue(customer.consignment_max_value != null
                ? maskMoney(customer.consignment_max_value.toFixed(2).replace('.', ','))
                : '');
            setEditingLimits(false);
        }
    }, [customer?.id]);

    if (!customer) return null;

    const c = customer;

    const saveLimits = () => {
        setSaving(true);
        const payload = {
            consignment_max_items: maxItems !== '' && Number(maxItems) > 0 ? Number(maxItems) : null,
            consignment_max_value: maxValue !== '' ? parseMoney(maxValue) : null,
        };
        router.patch(route('customers.consignment-limits.update', c.id), payload, {
            preserveScroll: true,
            onFinish: () => setSaving(false),
            onSuccess: () => setEditingLimits(false),
        });
    };

    const fullAddress = [
        c.address,
        c.number,
        c.complement,
        c.neighborhood,
    ].filter(Boolean).join(', ');

    const cityLine = [
        c.city,
        c.state,
    ].filter(Boolean).join(' / ');

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title={c.name}
            subtitle={c.cigam_code ? `CIGAM #${c.cigam_code}` : null}
            headerColor="bg-indigo-600"
            headerIcon={<UserIcon className="h-5 w-5" />}
            maxWidth="5xl"
            headerBadges={[
                { text: c.is_active ? 'Ativo' : 'Inativo', className: c.is_active ? 'bg-white/20 text-white' : 'bg-white/10 text-white/70' },
            ]}
        >
            <div className="space-y-4">
                <StandardModal.Section title="Dados pessoais">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <StandardModal.Field label="Nome" value={c.name} />
                        <StandardModal.Field label="CPF/CNPJ" value={c.formatted_cpf || '—'} />
                        <StandardModal.Field label="Tipo" value={c.person_type === 'J' ? 'Jurídica' : c.person_type === 'F' ? 'Física' : '—'} />
                        <StandardModal.Field label="Sexo" value={c.gender === 'M' ? 'Masculino' : c.gender === 'F' ? 'Feminino' : '—'} />
                        <StandardModal.Field label="Nascimento" value={c.birth_date ? new Date(c.birth_date).toLocaleDateString('pt-BR') : '—'} />
                        <StandardModal.Field label="Cadastrado em" value={c.registered_at ? new Date(c.registered_at).toLocaleDateString('pt-BR') : '—'} />
                    </div>
                </StandardModal.Section>

                <StandardModal.Section title="Contato">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <StandardModal.Field label="Celular" value={c.formatted_mobile || '—'} />
                        <StandardModal.Field label="Telefone fixo" value={c.phone || '—'} />
                        <StandardModal.Field label="E-mail" value={c.email || '—'} />
                    </div>
                </StandardModal.Section>

                <StandardModal.Section title="Endereço">
                    <div className="text-sm text-gray-700">
                        <div>{fullAddress || '—'}</div>
                        {cityLine && <div className="mt-0.5">{cityLine}</div>}
                        {c.zipcode && <div className="mt-0.5 text-gray-500">CEP {c.zipcode}</div>}
                    </div>
                </StandardModal.Section>

                <StandardModal.Section title="Limites de consignação">
                    {!editingLimits ? (
                        <div className="text-sm text-gray-700">
                            <div className="flex items-start justify-between gap-2">
                                <div className="flex-1">
                                    {(c.consignment_max_items || c.consignment_max_value) ? (
                                        <div className="space-y-1">
                                            {c.consignment_max_items && (
                                                <div>Máx. peças em aberto: <strong>{c.consignment_max_items}</strong></div>
                                            )}
                                            {c.consignment_max_value && (
                                                <div>
                                                    Máx. valor em aberto: <strong>R$ {Number(c.consignment_max_value).toFixed(2).replace('.', ',')}</strong>
                                                </div>
                                            )}
                                            <div className="text-xs text-gray-500 mt-1">
                                                Limites aplicados somando consignações em aberto ao tentar criar uma nova.
                                                Override via permissão OVERRIDE_CONSIGNMENT_LOCK + justificativa.
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="text-gray-500 italic">
                                            Sem limite configurado — cliente pode ter qualquer quantidade/valor em aberto.
                                        </div>
                                    )}
                                </div>
                                {canManageLimits && (
                                    <button
                                        type="button"
                                        onClick={() => setEditingLimits(true)}
                                        className="shrink-0 text-xs text-indigo-600 hover:text-indigo-700 inline-flex items-center gap-1"
                                    >
                                        <PencilIcon className="w-3.5 h-3.5" />
                                        Editar
                                    </button>
                                )}
                            </div>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <InputLabel value="Máx. peças em aberto" />
                                    <TextInput
                                        type="number"
                                        min="1"
                                        max="1000"
                                        value={maxItems}
                                        onChange={(e) => setMaxItems(e.target.value)}
                                        placeholder="Deixe em branco = sem limite"
                                        className="mt-1 block w-full"
                                    />
                                </div>
                                <div>
                                    <InputLabel value="Máx. valor em aberto (R$)" />
                                    <TextInput
                                        type="text"
                                        value={maxValue}
                                        onChange={(e) => setMaxValue(maskMoney(e.target.value))}
                                        placeholder="Deixe em branco = sem limite"
                                        className="mt-1 block w-full"
                                    />
                                </div>
                            </div>
                            <div className="flex gap-2 justify-end">
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    onClick={() => setEditingLimits(false)}
                                    disabled={saving}
                                    icon={XMarkIcon}
                                >
                                    Cancelar
                                </Button>
                                <Button
                                    variant="primary"
                                    size="sm"
                                    onClick={saveLimits}
                                    disabled={saving}
                                    icon={CheckIcon}
                                >
                                    {saving ? 'Salvando…' : 'Salvar limites'}
                                </Button>
                            </div>
                        </div>
                    )}
                </StandardModal.Section>

                <StandardModal.Section title={`Consignações (${c.consignments?.length ?? 0})`}>
                    {(!c.consignments || c.consignments.length === 0) ? (
                        <p className="text-sm text-gray-500">Nenhuma consignação registrada para este cliente.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full text-sm">
                                <thead className="bg-gray-50 text-xs text-gray-600 uppercase">
                                    <tr>
                                        <th className="px-3 py-2 text-left">#</th>
                                        <th className="px-3 py-2 text-left">Tipo</th>
                                        <th className="px-3 py-2 text-left">NF Saída</th>
                                        <th className="px-3 py-2 text-right">Valor</th>
                                        <th className="px-3 py-2 text-left">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {c.consignments.map((cons) => (
                                        <tr key={cons.id}>
                                            <td className="px-3 py-2 font-mono text-xs">#{cons.id}</td>
                                            <td className="px-3 py-2">{cons.type_label}</td>
                                            <td className="px-3 py-2">
                                                {cons.outbound_invoice_number}
                                                {cons.outbound_invoice_date && (
                                                    <span className="text-xs text-gray-500 ml-1">
                                                        ({new Date(cons.outbound_invoice_date).toLocaleDateString('pt-BR')})
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                R$ {Number(cons.outbound_total_value).toFixed(2).replace('.', ',')}
                                            </td>
                                            <td className="px-3 py-2">
                                                <StatusBadge color={cons.status === 'completed' ? 'success' : cons.status === 'overdue' ? 'danger' : 'info'}>
                                                    {cons.status_label}
                                                </StatusBadge>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </StandardModal.Section>

                {c.synced_at && (
                    <p className="text-xs text-gray-500 text-center">
                        Última sincronização: {new Date(c.synced_at).toLocaleString('pt-BR')}
                    </p>
                )}
            </div>
        </StandardModal>
    );
}
