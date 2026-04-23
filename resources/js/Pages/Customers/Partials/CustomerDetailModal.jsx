import { UserIcon } from '@heroicons/react/24/outline';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';

/**
 * Modal de detalhes do cliente — read-only. Mostra dados pessoais,
 * contato, endereço + as últimas 20 consignações.
 */
export default function CustomerDetailModal({ show, onClose, customer }) {
    if (!customer) return null;

    const c = customer;

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
            maxWidth="3xl"
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
