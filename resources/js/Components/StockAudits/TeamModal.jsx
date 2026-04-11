import { useState, useEffect } from 'react';
import StandardModal from '@/Components/StandardModal';
import Button from '@/Components/Button';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import Checkbox from '@/Components/Checkbox';
import StatusBadge from '@/Components/Shared/StatusBadge';
import { UsersIcon, TrashIcon, PlusIcon } from '@heroicons/react/24/outline';

const TEAM_ROLES = [
    { value: 'contador', label: 'Contador' },
    { value: 'conferente', label: 'Conferente' },
    { value: 'auditor', label: 'Auditor' },
    { value: 'supervisor', label: 'Supervisor' },
];

export default function TeamModal({ show, onClose, audit, onSuccess }) {
    const [members, setMembers] = useState([]);
    const [users, setUsers] = useState([]);
    const [loading, setLoading] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});
    const [userId, setUserId] = useState('');
    const [role, setRole] = useState('contador');
    const [isThirdParty, setIsThirdParty] = useState(false);
    const [externalName, setExternalName] = useState('');
    const [externalDocument, setExternalDocument] = useState('');

    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content;
    const fetchData = async () => {
        if (!audit) return;
        setLoading(true);
        try {
            const res = await fetch(route('stock-audits.teams', audit.id), { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() } });
            const json = await res.json();
            setMembers(json.members || []); setUsers(json.users || []);
        } catch {} finally { setLoading(false); }
    };

    useEffect(() => { if (show && audit) fetchData(); }, [show, audit]);

    const resetForm = () => { setUserId(''); setRole('contador'); setIsThirdParty(false); setExternalName(''); setExternalDocument(''); setErrors({}); };

    const handleAdd = async () => {
        setProcessing(true); setErrors({});
        const body = { action: 'add', role, is_third_party: isThirdParty };
        if (isThirdParty) { body.external_staff_name = externalName; body.external_staff_document = externalDocument; }
        else { body.user_id = userId; }
        try {
            const res = await fetch(route('stock-audits.teams', audit.id), {
                method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body: JSON.stringify(body),
            });
            const json = await res.json();
            if (!res.ok) { setErrors(json.errors || { general: json.message }); return; }
            resetForm(); fetchData(); onSuccess?.();
        } catch { setErrors({ general: 'Erro de conexão.' }); } finally { setProcessing(false); }
    };

    const handleRemove = async (memberId) => {
        try {
            const res = await fetch(route('stock-audits.teams', audit.id), {
                method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body: JSON.stringify({ action: 'remove', member_id: memberId }),
            });
            if (res.ok) { fetchData(); onSuccess?.(); }
        } catch {}
    };

    const handleClose = () => { resetForm(); setMembers([]); onClose(); };

    return (
        <StandardModal show={show} onClose={handleClose} title="Equipe da Auditoria"
            headerColor="bg-indigo-600" headerIcon={<UsersIcon className="h-5 w-5" />}
            errorMessage={errors.general} maxWidth="2xl"
            footer={<StandardModal.Footer onCancel={handleClose} cancelLabel="Fechar" />}>

            {/* Membros Atuais */}
            <StandardModal.Section title="Membros Atuais">
                {loading ? (
                    <div className="flex items-center justify-center py-6">
                        <div className="animate-spin rounded-full h-6 w-6 border-2 border-indigo-600 border-t-transparent" />
                    </div>
                ) : members.length === 0 ? (
                    <p className="text-center text-gray-500 py-4">Nenhum membro adicionado.</p>
                ) : (
                    <div className="space-y-2 -mx-4 -mb-4 px-4 pb-4">
                        {members.map((m) => (
                            <div key={m.id} className="flex items-center justify-between p-3 bg-white rounded-lg border border-gray-100">
                                <div>
                                    <p className="text-sm font-medium text-gray-900">{m.user?.name || m.external_staff_name || '-'}</p>
                                    <div className="flex items-center gap-2 mt-0.5">
                                        <StatusBadge variant="indigo" size="sm">{m.role}</StatusBadge>
                                        {m.is_third_party && <StatusBadge variant="warning" size="sm">Terceiro</StatusBadge>}
                                        {m.external_staff_document && <span className="text-xs text-gray-500">Doc: {m.external_staff_document}</span>}
                                    </div>
                                </div>
                                <button onClick={() => handleRemove(m.id)} className="text-red-500 hover:text-red-700 p-1">
                                    <TrashIcon className="h-4 w-4" />
                                </button>
                            </div>
                        ))}
                    </div>
                )}
            </StandardModal.Section>

            {/* Adicionar Membro */}
            <StandardModal.Section title="Adicionar Membro">
                <div className="space-y-4 -mx-4 -mb-4 px-4 pb-4">
                    <div className="flex items-center gap-2">
                        <Checkbox checked={isThirdParty} onChange={(e) => setIsThirdParty(e.target.checked)} />
                        <span className="text-sm text-gray-700">Terceiro (externo)</span>
                    </div>

                    {!isThirdParty ? (
                        <div>
                            <InputLabel value="Usuário *" />
                            <select value={userId} onChange={(e) => setUserId(e.target.value)}
                                className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">Selecione...</option>
                                {users.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
                            </select>
                            <InputError message={errors.user_id} className="mt-1" />
                        </div>
                    ) : (
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <InputLabel value="Nome *" />
                                <TextInput className="mt-1 w-full" value={externalName} onChange={(e) => setExternalName(e.target.value)} placeholder="Nome completo" />
                                <InputError message={errors.external_staff_name} className="mt-1" />
                            </div>
                            <div>
                                <InputLabel value="Documento" />
                                <TextInput className="mt-1 w-full" value={externalDocument} onChange={(e) => setExternalDocument(e.target.value)} placeholder="CPF ou documento" />
                            </div>
                        </div>
                    )}

                    <div>
                        <InputLabel value="Função *" />
                        <select value={role} onChange={(e) => setRole(e.target.value)}
                            className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            {TEAM_ROLES.map(r => <option key={r.value} value={r.value}>{r.label}</option>)}
                        </select>
                        <InputError message={errors.role} className="mt-1" />
                    </div>

                    <div className="flex justify-end">
                        <Button variant="primary" size="sm" icon={PlusIcon} onClick={handleAdd} loading={processing}>
                            Adicionar Membro
                        </Button>
                    </div>
                </div>
            </StandardModal.Section>
        </StandardModal>
    );
}
