import StandardModal from "@/Components/StandardModal";
import Button from "@/Components/Button";
import StatusBadge from "@/Components/Shared/StatusBadge";
import { PencilSquareIcon } from "@heroicons/react/24/outline";
import { formatDateTime } from '@/Utils/dateHelpers';

const NETWORK_VARIANT = {
    1: 'pink', 2: 'purple', 3: 'amber', 4: 'info',
    5: 'orange', 6: 'cyan', 7: 'gray', 8: 'rose',
};

export default function StoreViewModal({ show, onClose, store, onEdit }) {
    if (!store) return null;

    const headerBadges = [
        { text: store.is_active ? 'Ativa' : 'Inativa', className: store.is_active ? 'bg-emerald-500/20 text-white' : 'bg-red-500/20 text-white' },
        { text: store.network_name, className: 'bg-white/20 text-white' },
    ];

    const footerContent = (
        <>
            <Button variant="primary" size="sm" icon={PencilSquareIcon} onClick={() => onEdit(store)}>
                Editar
            </Button>
            <div className="flex-1" />
            <Button variant="outline" onClick={onClose}>Fechar</Button>
        </>
    );

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title={store.name}
            subtitle={`Código: ${store.code}`}
            headerColor="bg-blue-700"
            headerBadges={headerBadges}
            maxWidth="7xl"
            footer={<StandardModal.Footer>{footerContent}</StandardModal.Footer>}
        >
            {/* Informações da Empresa + Endereço */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <StandardModal.Section title="Informações da Empresa">
                    <div className="grid grid-cols-1 gap-3">
                        <StandardModal.Field label="Razão Social" value={store.company_name} />
                        <StandardModal.Field label="CNPJ" value={store.formatted_cnpj} mono />
                        <StandardModal.Field label="Inscrição Estadual" value={store.state_registration} />
                    </div>
                </StandardModal.Section>

                <StandardModal.Section title="Endereço">
                    <p className="text-sm text-gray-900">{store.address || '-'}</p>
                </StandardModal.Section>
            </div>

            {/* Gestão + Ordenação */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <StandardModal.Section title="Gestão">
                    <div className="grid grid-cols-1 gap-3">
                        <StandardModal.Field label="Gerente" value={store.manager?.short_name || store.manager?.name} />
                        <StandardModal.Field label="Supervisor" value={store.supervisor?.short_name || store.supervisor?.name} />
                    </div>
                </StandardModal.Section>

                <StandardModal.Section title="Ordenação">
                    <div className="grid grid-cols-2 gap-3">
                        <StandardModal.MiniField label="Ordem da Loja" value={store.store_order} />
                        <StandardModal.MiniField label="Ordem na Rede" value={store.network_order} />
                    </div>
                </StandardModal.Section>
            </div>

            {/* Funcionários */}
            <StandardModal.Section title={`Funcionários (${store.employees_count})`}>
                {store.employees && store.employees.length > 0 ? (
                    <div className="-mx-4 -mb-4 px-4 pb-4">
                        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
                            {store.employees.map((employee) => (
                                <div key={employee.id} className="flex items-center gap-2 bg-white rounded-lg p-2 shadow-sm border border-gray-100">
                                    <div className="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 font-semibold text-xs">
                                        {employee.short_name?.charAt(0) || employee.name?.charAt(0)}
                                    </div>
                                    <div className="overflow-hidden">
                                        <p className="text-xs font-medium text-gray-900 truncate">
                                            {employee.short_name || employee.name}
                                        </p>
                                        <p className="text-xs text-gray-500 truncate">
                                            {employee.position || 'Sem cargo'}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>
                        {store.employees_count > 10 && (
                            <p className="text-xs text-gray-500 mt-3 text-center">
                                Mostrando 10 de {store.employees_count} funcionários
                            </p>
                        )}
                    </div>
                ) : (
                    <p className="text-sm text-gray-500 text-center py-2">Nenhum funcionário cadastrado nesta loja.</p>
                )}
            </StandardModal.Section>

            {/* Timestamps */}
            <div className="flex justify-between text-xs text-gray-500 pt-2">
                <span>Criado em: {formatDateTime(store.created_at)}</span>
                <span>Atualizado em: {formatDateTime(store.updated_at)}</span>
            </div>
        </StandardModal>
    );
}
