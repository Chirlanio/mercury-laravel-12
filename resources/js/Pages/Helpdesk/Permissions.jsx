import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    UserGroupIcon,
    PlusIcon,
    TrashIcon,
    ShieldCheckIcon,
} from '@heroicons/react/24/outline';
import Button from '@/Components/Button';
import StandardModal from '@/Components/StandardModal';
import PageHeader from '@/Components/Shared/PageHeader';
import StatusBadge from '@/Components/Shared/StatusBadge';
import InputLabel from '@/Components/InputLabel';
import useModalManager from '@/Hooks/useModalManager';

export default function Permissions({ departments, selectedDepartmentId, permissions, availableUsers }) {
    const { modals, openModal, closeModal } = useModalManager(['add']);
    const [formData, setFormData] = useState({ user_id: '', level: 'technician' });
    const [processing, setProcessing] = useState(false);

    const currentDepartment = departments.find(d => d.id === selectedDepartmentId);

    const handleDepartmentChange = (id) => {
        router.get(route('helpdesk.permissions.index'), { department_id: id }, { preserveState: true });
    };

    const handleAdd = (e) => {
        e?.preventDefault();
        if (!formData.user_id) return;
        setProcessing(true);
        router.post(route('helpdesk.permissions.store'), {
            department_id: selectedDepartmentId,
            user_id: Number(formData.user_id),
            level: formData.level,
        }, {
            onSuccess: () => {
                closeModal('add');
                setFormData({ user_id: '', level: 'technician' });
            },
            onFinish: () => setProcessing(false),
        });
    };

    const handleLevelChange = (userId, newLevel) => {
        router.put(route('helpdesk.permissions.update', [selectedDepartmentId, userId]),
            { level: newLevel }, { preserveScroll: true });
    };

    const handleRemove = (userId, userName) => {
        if (!confirm(`Remover permissão de ${userName}?`)) return;
        router.delete(route('helpdesk.permissions.destroy', [selectedDepartmentId, userId]), {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Permissões Helpdesk" />
            <div className="py-6 sm:py-12">
                <div className="max-w-full mx-auto px-3 sm:px-6 lg:px-8">
                    <PageHeader
                        title="Permissões do Helpdesk"
                        icon={ShieldCheckIcon}
                        subtitle="Atribua técnicos e gerentes aos departamentos do helpdesk."
                        actions={[
                            { type: 'back', href: route('helpdesk.index') },
                        ]}
                    />

                    {/* Department selector */}
                    <div className="bg-white shadow-sm rounded-lg p-3 sm:p-4 mb-4 sm:mb-6">
                        <InputLabel value="Departamento" />
                        <select
                            className="mt-1 w-full md:w-1/2 border-gray-300 rounded-lg text-sm"
                            value={selectedDepartmentId || ''}
                            onChange={e => handleDepartmentChange(e.target.value)}
                        >
                            {departments.map(d => (
                                <option key={d.id} value={d.id}>
                                    {d.name}{!d.is_active && ' (inativo)'}
                                </option>
                            ))}
                        </select>
                    </div>

                    {/* Permissions list */}
                    {currentDepartment && (
                        <div className="bg-white shadow-sm rounded-lg overflow-hidden">
                            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 px-4 sm:px-6 py-3 sm:py-4 border-b">
                                <div className="flex items-center gap-2 min-w-0">
                                    <UserGroupIcon className="w-5 h-5 text-gray-500 shrink-0" />
                                    <h2 className="font-semibold text-gray-900 text-sm sm:text-base truncate">
                                        {currentDepartment.name} · {permissions.length} usuário(s)
                                    </h2>
                                </div>
                                <Button variant="primary" icon={PlusIcon} size="sm"
                                    onClick={() => openModal('add')}
                                    className="w-full sm:w-auto">
                                    Adicionar
                                </Button>
                            </div>

                            {permissions.length === 0 ? (
                                <div className="px-4 sm:px-6 py-10 sm:py-12 text-center text-gray-500 text-sm">
                                    Nenhum usuário vinculado a este departamento ainda.
                                </div>
                            ) : (
                                <>
                                    {/* Desktop/tablet: table view */}
                                    <table className="hidden sm:table w-full text-sm">
                                        <thead className="bg-gray-50 text-xs uppercase text-gray-500">
                                            <tr>
                                                <th className="px-4 lg:px-6 py-3 text-left">Usuário</th>
                                                <th className="px-4 lg:px-6 py-3 text-left hidden md:table-cell">E-mail</th>
                                                <th className="px-4 lg:px-6 py-3 text-left">Nível</th>
                                                <th className="px-4 lg:px-6 py-3 text-right">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-gray-100">
                                            {permissions.map(p => (
                                                <tr key={p.user_id} className="hover:bg-gray-50">
                                                    <td className="px-4 lg:px-6 py-3 font-medium text-gray-900">
                                                        {p.user_name}
                                                        <span className="block md:hidden text-xs text-gray-500 font-normal truncate">{p.user_email}</span>
                                                    </td>
                                                    <td className="px-4 lg:px-6 py-3 text-gray-600 hidden md:table-cell truncate max-w-xs">{p.user_email}</td>
                                                    <td className="px-4 lg:px-6 py-3">
                                                        <select
                                                            className="text-xs border-gray-300 rounded"
                                                            value={p.level}
                                                            onChange={e => handleLevelChange(p.user_id, e.target.value)}
                                                        >
                                                            <option value="technician">Técnico</option>
                                                            <option value="manager">Gerente</option>
                                                        </select>
                                                    </td>
                                                    <td className="px-4 lg:px-6 py-3 text-right">
                                                        <Button variant="danger" size="xs" icon={TrashIcon}
                                                            onClick={() => handleRemove(p.user_id, p.user_name)}>
                                                            <span className="hidden lg:inline">Remover</span>
                                                        </Button>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>

                                    {/* Mobile: card list */}
                                    <ul className="sm:hidden divide-y divide-gray-100">
                                        {permissions.map(p => (
                                            <li key={p.user_id} className="p-4">
                                                <div className="flex items-start justify-between gap-3">
                                                    <div className="min-w-0 flex-1">
                                                        <div className="font-medium text-gray-900 truncate">{p.user_name}</div>
                                                        <div className="text-xs text-gray-500 truncate">{p.user_email}</div>
                                                        {p.level === 'manager' && (
                                                            <StatusBadge variant="purple" className="mt-1">Gerente</StatusBadge>
                                                        )}
                                                    </div>
                                                    <Button variant="danger" size="xs" icon={TrashIcon}
                                                        onClick={() => handleRemove(p.user_id, p.user_name)} />
                                                </div>
                                                <select
                                                    className="mt-3 w-full text-xs border-gray-300 rounded"
                                                    value={p.level}
                                                    onChange={e => handleLevelChange(p.user_id, e.target.value)}
                                                >
                                                    <option value="technician">Técnico</option>
                                                    <option value="manager">Gerente</option>
                                                </select>
                                            </li>
                                        ))}
                                    </ul>
                                </>
                            )}
                        </div>
                    )}
                </div>
            </div>

            {/* Add User Modal */}
            <StandardModal show={modals.add} onClose={() => closeModal('add')}
                title="Adicionar Usuário ao Departamento"
                headerColor="bg-indigo-600"
                headerIcon={<UserGroupIcon className="h-5 w-5" />}
                maxWidth="md"
                onSubmit={handleAdd}
                footer={<StandardModal.Footer onCancel={() => closeModal('add')}
                    onSubmit="submit" submitLabel="Adicionar" processing={processing} />}>
                <StandardModal.Section title="Dados">
                    <div className="space-y-4">
                        <div>
                            <InputLabel value="Usuário *" />
                            <select className="mt-1 w-full border-gray-300 rounded-lg text-sm"
                                value={formData.user_id}
                                onChange={e => setFormData(p => ({ ...p, user_id: e.target.value }))}>
                                <option value="">Selecione...</option>
                                {availableUsers.map(u => (
                                    <option key={u.id} value={u.id}>{u.name} ({u.email})</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Nível *" />
                            <select className="mt-1 w-full border-gray-300 rounded-lg text-sm"
                                value={formData.level}
                                onChange={e => setFormData(p => ({ ...p, level: e.target.value }))}>
                                <option value="technician">Técnico</option>
                                <option value="manager">Gerente</option>
                            </select>
                            <p className="mt-1 text-xs text-gray-500">
                                Técnicos podem atribuir e transicionar chamados. Gerentes também podem excluir.
                            </p>
                        </div>
                    </div>
                </StandardModal.Section>
            </StandardModal>
        </>
    );
}
