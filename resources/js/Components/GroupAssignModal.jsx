import Modal from '@/Components/Modal';
import Button from '@/Components/Button';
import { useState } from 'react';
import { router } from '@inertiajs/react';

export default function GroupAssignModal({
    show = false,
    onClose,
    selectedIds = [],
    routeName = '',
    groups = [],
}) {
    const [groupId, setGroupId] = useState('');
    const [saving, setSaving] = useState(false);

    const handleSubmit = () => {
        setSaving(true);
        router.post(route(routeName + '.assign-group'), {
            ids: selectedIds,
            group_id: groupId || null,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setSaving(false);
                setGroupId('');
                onClose(true);
            },
            onError: () => setSaving(false),
        });
    };

    const handleClose = () => {
        setGroupId('');
        onClose(false);
    };

    return (
        <Modal show={show} onClose={handleClose} maxWidth="lg" title="Atribuir Grupo">
            <div className="space-y-6">
                <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div className="flex">
                        <svg className="w-5 h-5 text-blue-600 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <div className="text-sm text-blue-800">
                            <p><strong>{selectedIds.length}</strong> registro(s) selecionado(s).</p>
                            <p className="mt-1">Selecione um grupo para atribuir ou deixe vazio para remover do grupo atual.</p>
                        </div>
                    </div>
                </div>

                <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">
                        Grupo
                    </label>
                    <select
                        value={groupId}
                        onChange={(e) => setGroupId(e.target.value)}
                        className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">Sem grupo (remover)</option>
                        {groups.map(group => (
                            <option key={group.id} value={group.id}>
                                {group.name}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="flex justify-end space-x-3 pt-4 border-t">
                    <Button variant="secondary" onClick={handleClose} disabled={saving}>
                        Cancelar
                    </Button>
                    <Button
                        variant="primary"
                        onClick={handleSubmit}
                        disabled={saving || selectedIds.length === 0}
                    >
                        {saving ? 'Salvando...' : 'Atribuir Grupo'}
                    </Button>
                </div>
            </div>
        </Modal>
    );
}
