import { Head, useForm, router } from '@inertiajs/react';
import CentralLayout from '@/Layouts/CentralLayout';
import { useState } from 'react';
import {
    PlusIcon,
    PencilIcon,
    TrashIcon,
    CheckIcon,
    ShieldCheckIcon,
} from '@heroicons/react/24/outline';

export default function Index({ roles, permissionGroups }) {
    const [showCreateRole, setShowCreateRole] = useState(false);
    const [editingRole, setEditingRole] = useState(null);
    const [editingPermissions, setEditingPermissions] = useState(null);

    return (
        <CentralLayout title="Roles & Permissões">
            <Head title="Roles & Permissões - Mercury SaaS" />

            {/* Roles Section */}
            <div className="mb-8">
                <div className="flex justify-between items-center mb-4">
                    <div>
                        <h2 className="text-base font-semibold text-gray-900">Roles</h2>
                        <p className="text-sm text-gray-500">Perfis de acesso e suas permissões na plataforma.</p>
                    </div>
                    <button
                        onClick={() => setShowCreateRole(true)}
                        className="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700"
                    >
                        <PlusIcon className="h-4 w-4" /> Nova Role
                    </button>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {roles.map((role) => (
                        <div key={role.id} className={`bg-white shadow rounded-lg overflow-hidden ${!role.is_active ? 'opacity-50' : ''}`}>
                            <div className="px-5 py-4">
                                <div className="flex items-center justify-between mb-2">
                                    <div className="flex items-center gap-2">
                                        <span className={`inline-flex items-center justify-center w-8 h-8 rounded-full text-white text-xs font-bold ${
                                            role.hierarchy_level >= 4 ? 'bg-purple-600' :
                                            role.hierarchy_level >= 3 ? 'bg-indigo-600' :
                                            role.hierarchy_level >= 2 ? 'bg-blue-500' :
                                            'bg-gray-400'
                                        }`}>
                                            {role.hierarchy_level}
                                        </span>
                                        <div>
                                            <h3 className="text-sm font-semibold text-gray-900">{role.label}</h3>
                                            <p className="text-xs text-gray-400 font-mono">{role.name}</p>
                                        </div>
                                    </div>
                                </div>

                                <div className="flex items-center justify-between mt-3">
                                    <span className="text-xs text-gray-500">
                                        {role.permissions_count} permissões
                                    </span>
                                    <div className="flex gap-1">
                                        <button
                                            onClick={() => setEditingPermissions(role)}
                                            title="Gerenciar permissões"
                                            className="inline-flex items-center justify-center w-8 h-8 rounded-md bg-indigo-100 text-indigo-700 hover:bg-indigo-200 transition-colors"
                                        >
                                            <ShieldCheckIcon className="h-4 w-4" />
                                        </button>
                                        <button
                                            onClick={() => setEditingRole(role)}
                                            title="Editar role"
                                            className="inline-flex items-center justify-center w-8 h-8 rounded-md bg-amber-100 text-amber-700 hover:bg-amber-200 transition-colors"
                                        >
                                            <PencilIcon className="h-4 w-4" />
                                        </button>
                                        {!role.is_system && (
                                            <button
                                                onClick={() => {
                                                    if (confirm(`Excluir role "${role.label}"?`)) {
                                                        router.delete(`/admin/roles/${role.id}`);
                                                    }
                                                }}
                                                title="Excluir role"
                                                className="inline-flex items-center justify-center w-8 h-8 rounded-md bg-red-100 text-red-700 hover:bg-red-200 transition-colors"
                                            >
                                                <TrashIcon className="h-4 w-4" />
                                            </button>
                                        )}
                                    </div>
                                </div>
                            </div>

                            {/* Permission bar */}
                            <div className="h-1.5 bg-gray-100">
                                <div
                                    className={`h-1.5 ${
                                        role.hierarchy_level >= 4 ? 'bg-purple-500' :
                                        role.hierarchy_level >= 3 ? 'bg-indigo-500' :
                                        role.hierarchy_level >= 2 ? 'bg-blue-400' :
                                        'bg-gray-300'
                                    }`}
                                    style={{
                                        width: `${permissionGroups.reduce((acc, g) => acc + g.permissions.length, 0) > 0
                                            ? (role.permissions_count / permissionGroups.reduce((acc, g) => acc + g.permissions.length, 0)) * 100
                                            : 0}%`
                                    }}
                                />
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {/* Permission Matrix (read-only overview) */}
            <div>
                <h2 className="text-base font-semibold text-gray-900 mb-1">Matriz de Permissões</h2>
                <p className="text-sm text-gray-500 mb-4">
                    Visão geral de todas as permissões por role. Clique no botão <ShieldCheckIcon className="inline h-4 w-4 text-indigo-600" /> de uma role para editar.
                </p>

                <div className="space-y-4">
                    {permissionGroups.map((group) => (
                        <div key={group.name} className="bg-white shadow rounded-lg overflow-hidden">
                            <div className="px-4 py-3 bg-gray-50 border-b">
                                <h3 className="text-sm font-semibold text-gray-900">{group.label}</h3>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="min-w-full">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 w-1/3">Permissão</th>
                                            {roles.map((role) => (
                                                <th key={role.id} className="px-2 py-2 text-center text-xs font-medium text-gray-500 whitespace-nowrap">
                                                    {role.label}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {group.permissions.map((perm) => (
                                            <tr key={perm.id} className="hover:bg-gray-50">
                                                <td className="px-4 py-2">
                                                    <div className="text-sm text-gray-700">{perm.label}</div>
                                                    <div className="text-xs text-gray-400 font-mono">{perm.slug}</div>
                                                </td>
                                                {roles.map((role) => {
                                                    const has = role.permission_ids.includes(perm.id);
                                                    return (
                                                        <td key={role.id} className="px-2 py-2 text-center">
                                                            {has ? (
                                                                <CheckIcon className="h-4 w-4 text-green-500 mx-auto" />
                                                            ) : (
                                                                <span className="text-gray-200">-</span>
                                                            )}
                                                        </td>
                                                    );
                                                })}
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {/* Modals */}
            {showCreateRole && <RoleFormModal onClose={() => setShowCreateRole(false)} />}
            {editingRole && <RoleFormModal role={editingRole} onClose={() => setEditingRole(null)} />}
            {editingPermissions && (
                <PermissionMatrixModal
                    role={editingPermissions}
                    permissionGroups={permissionGroups}
                    onClose={() => setEditingPermissions(null)}
                />
            )}
        </CentralLayout>
    );
}

// =================== ROLE FORM MODAL ===================

function RoleFormModal({ role, onClose }) {
    const isEditing = !!role;
    const { data, setData, post, put, processing, errors } = useForm({
        name: role?.name || '',
        label: role?.label || '',
        hierarchy_level: role?.hierarchy_level ?? 1,
        is_active: role?.is_active ?? true,
    });

    const submit = (e) => {
        e.preventDefault();
        if (isEditing) {
            put(`/admin/roles/${role.id}`, { onSuccess: onClose });
        } else {
            post('/admin/roles', { onSuccess: onClose });
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center">
            <div className="fixed inset-0 bg-gray-600/75" onClick={onClose} />
            <div className="relative bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
                <div className="px-6 py-4 border-b">
                    <h3 className="text-lg font-semibold text-gray-900">
                        {isEditing ? `Editar: ${role.label}` : 'Nova Role'}
                    </h3>
                    <p className="mt-1 text-sm text-gray-500">
                        {isEditing
                            ? 'Altere as propriedades da role. O slug não pode ser alterado em roles do sistema.'
                            : 'Crie um novo perfil de acesso customizado para a plataforma.'}
                    </p>
                </div>
                <form onSubmit={submit} className="p-6 space-y-4">
                    {!isEditing && (
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Slug *</label>
                            <p className="text-xs text-gray-400 mb-1">Identificador unico. Apenas letras, números e underscores.</p>
                            <input
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, ''))}
                                className="block w-full rounded-md border-gray-300 shadow-sm text-sm font-mono"
                                placeholder="Ex: gerente_loja"
                                required
                            />
                            {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
                        </div>
                    )}
                    <div>
                        <label className="block text-sm font-medium text-gray-700">Nome de Exibição *</label>
                        <p className="text-xs text-gray-400 mb-1">Nome amigável exibido no sistema.</p>
                        <input
                            type="text"
                            value={data.label}
                            onChange={(e) => setData('label', e.target.value)}
                            className="block w-full rounded-md border-gray-300 shadow-sm text-sm"
                            placeholder="Ex: Gerente de Loja"
                            required
                        />
                        {errors.label && <p className="mt-1 text-xs text-red-600">{errors.label}</p>}
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700">Nível Hierárquico *</label>
                        <p className="text-xs text-gray-400 mb-1">
                            Quanto maior o nível, mais poder. Super Admin = 4, Admin = 3, Suporte = 2, Usuário = 1.
                        </p>
                        <input
                            type="number"
                            value={data.hierarchy_level}
                            onChange={(e) => setData('hierarchy_level', parseInt(e.target.value) || 0)}
                            className="block w-full rounded-md border-gray-300 shadow-sm text-sm"
                            min="0"
                            max="10"
                            required
                        />
                    </div>

                    {isEditing && (
                        <label className="flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                checked={data.is_active}
                                onChange={(e) => setData('is_active', e.target.checked)}
                                className="rounded border-gray-300 text-indigo-600"
                            />
                            <span className="text-sm text-gray-700">Role ativa</span>
                        </label>
                    )}

                    <div className="flex justify-end gap-3 pt-4 border-t">
                        <button type="button" onClick={onClose} className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200">
                            Cancelar
                        </button>
                        <button type="submit" disabled={processing} className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50">
                            {processing ? 'Salvando...' : (isEditing ? 'Salvar' : 'Criar Role')}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}

// =================== PERMISSION MATRIX MODAL ===================

function PermissionMatrixModal({ role, permissionGroups, onClose }) {
    const [selectedIds, setSelectedIds] = useState(new Set(role.permission_ids));
    const { put, processing } = useForm({});

    const totalPerms = permissionGroups.reduce((acc, g) => acc + g.permissions.length, 0);

    const toggle = (id) => {
        setSelectedIds(prev => {
            const next = new Set(prev);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    };

    const toggleGroup = (group) => {
        const groupIds = group.permissions.map(p => p.id);
        const allSelected = groupIds.every(id => selectedIds.has(id));

        setSelectedIds(prev => {
            const next = new Set(prev);
            groupIds.forEach(id => {
                if (allSelected) {
                    next.delete(id);
                } else {
                    next.add(id);
                }
            });
            return next;
        });
    };

    const selectAll = () => {
        const allIds = permissionGroups.flatMap(g => g.permissions.map(p => p.id));
        setSelectedIds(new Set(allIds));
    };

    const selectNone = () => {
        setSelectedIds(new Set());
    };

    const submit = () => {
        router.put(`/admin/roles/${role.id}/permissions`, {
            permission_ids: [...selectedIds],
        }, {
            onSuccess: onClose,
        });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center">
            <div className="fixed inset-0 bg-gray-600/75" onClick={onClose} />
            <div className="relative bg-white rounded-lg shadow-xl max-w-3xl w-full mx-4 max-h-[90vh] flex flex-col">
                <div className="px-6 py-4 border-b flex-shrink-0">
                    <h3 className="text-lg font-semibold text-gray-900">
                        Permissões: {role.label}
                    </h3>
                    <div className="flex items-center justify-between mt-2">
                        <p className="text-sm text-gray-500">
                            {selectedIds.size} de {totalPerms} permissões selecionadas
                        </p>
                        <div className="flex gap-2">
                            <button type="button" onClick={selectAll} className="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                Selecionar tudo
                            </button>
                            <span className="text-gray-300">|</span>
                            <button type="button" onClick={selectNone} className="text-xs text-gray-500 hover:text-gray-700 font-medium">
                                Limpar
                            </button>
                        </div>
                    </div>
                </div>

                <div className="overflow-y-auto flex-1 p-6 space-y-5">
                    {permissionGroups.map((group) => {
                        const groupIds = group.permissions.map(p => p.id);
                        const selectedCount = groupIds.filter(id => selectedIds.has(id)).length;
                        const allSelected = selectedCount === groupIds.length;

                        return (
                            <div key={group.name}>
                                <div className="flex items-center justify-between mb-2">
                                    <label className="flex items-center gap-2 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={allSelected}
                                            onChange={() => toggleGroup(group)}
                                            className="rounded border-gray-300 text-indigo-600"
                                            ref={el => { if (el) el.indeterminate = selectedCount > 0 && !allSelected; }}
                                        />
                                        <span className="text-sm font-semibold text-gray-900">{group.label}</span>
                                    </label>
                                    <span className="text-xs text-gray-400">{selectedCount}/{groupIds.length}</span>
                                </div>
                                <div className="grid grid-cols-2 gap-x-4 gap-y-1 pl-6">
                                    {group.permissions.map((perm) => (
                                        <label key={perm.id} className="flex items-start gap-2 cursor-pointer py-1">
                                            <input
                                                type="checkbox"
                                                checked={selectedIds.has(perm.id)}
                                                onChange={() => toggle(perm.id)}
                                                className="rounded border-gray-300 text-indigo-600 mt-0.5"
                                            />
                                            <div>
                                                <span className="text-sm text-gray-700">{perm.label}</span>
                                                {perm.description && (
                                                    <p className="text-xs text-gray-400 leading-tight">{perm.description}</p>
                                                )}
                                            </div>
                                        </label>
                                    ))}
                                </div>
                            </div>
                        );
                    })}
                </div>

                <div className="px-6 py-4 border-t flex-shrink-0 flex justify-end gap-3">
                    <button type="button" onClick={onClose} className="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200">
                        Cancelar
                    </button>
                    <button
                        onClick={submit}
                        disabled={processing}
                        className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50"
                    >
                        {processing ? 'Salvando...' : 'Salvar Permissões'}
                    </button>
                </div>
            </div>
        </div>
    );
}
