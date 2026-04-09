import { useState, useEffect } from 'react';
import Modal from '@/Components/Modal';
import Button from '@/Components/Button';

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

    const csrfToken = () =>
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    const fetchData = async () => {
        if (!audit) return;
        setLoading(true);

        try {
            const res = await fetch(route('stock-audits.teams', audit.id), {
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
            });
            const json = await res.json();
            setMembers(json.members || []);
            setUsers(json.users || []);
        } catch {
            // Silently handle
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (show && audit) {
            fetchData();
        }
    }, [show, audit]);

    const resetForm = () => {
        setUserId('');
        setRole('contador');
        setIsThirdParty(false);
        setExternalName('');
        setExternalDocument('');
        setErrors({});
    };

    const handleAdd = async (e) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        const body = {
            action: 'add',
            role,
            is_third_party: isThirdParty,
        };

        if (isThirdParty) {
            body.external_staff_name = externalName;
            body.external_staff_document = externalDocument;
        } else {
            body.user_id = userId;
        }

        try {
            const res = await fetch(route('stock-audits.teams', audit.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify(body),
            });

            const json = await res.json();

            if (!res.ok) {
                setErrors(json.errors || { general: json.message || 'Erro ao adicionar membro.' });
                return;
            }

            resetForm();
            fetchData();
            onSuccess?.();
        } catch {
            setErrors({ general: 'Erro de conexao. Tente novamente.' });
        } finally {
            setProcessing(false);
        }
    };

    const handleRemove = async (memberId) => {
        try {
            const res = await fetch(route('stock-audits.teams', audit.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    action: 'remove',
                    member_id: memberId,
                }),
            });

            if (res.ok) {
                fetchData();
                onSuccess?.();
            }
        } catch {
            // Silently handle
        }
    };

    const handleClose = () => {
        resetForm();
        setMembers([]);
        onClose();
    };

    return (
        <Modal show={show} onClose={handleClose} maxWidth="2xl">
            <div className="flex flex-col max-h-[80vh]">
                {/* Header */}
                <div className="flex items-center justify-between px-6 py-4 bg-indigo-600 text-white flex-shrink-0 rounded-t-lg">
                    <h2 className="text-lg font-semibold">Equipe da Auditoria</h2>
                    <button onClick={handleClose} className="text-white/80 hover:text-white transition">
                        <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {/* Body */}
                <div className="flex-1 overflow-y-auto p-6 space-y-6">
                    {errors.general && (
                        <div className="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700">
                            {errors.general}
                        </div>
                    )}

                    {/* Current Members */}
                    <section>
                        <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-3">
                            Membros Atuais
                        </h3>

                        {loading && (
                            <div className="flex items-center justify-center py-8">
                                <div className="animate-spin rounded-full h-6 w-6 border-2 border-indigo-600 border-t-transparent"></div>
                                <span className="ml-2 text-sm text-gray-500">Carregando...</span>
                            </div>
                        )}

                        {!loading && members.length === 0 && (
                            <div className="text-center text-gray-500 py-6 bg-gray-50 rounded-lg">
                                Nenhum membro adicionado.
                            </div>
                        )}

                        {!loading && members.length > 0 && (
                            <div className="space-y-2">
                                {members.map((member) => (
                                    <div
                                        key={member.id}
                                        className="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
                                    >
                                        <div className="flex items-center gap-3">
                                            <div>
                                                <p className="text-sm font-medium text-gray-900">
                                                    {member.user?.name || member.external_staff_name || '-'}
                                                </p>
                                                <div className="flex items-center gap-2">
                                                    <span className="text-xs bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded">
                                                        {member.role}
                                                    </span>
                                                    {member.is_third_party && (
                                                        <span className="text-xs bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded">
                                                            Terceiro
                                                        </span>
                                                    )}
                                                    {member.external_staff_document && (
                                                        <span className="text-xs text-gray-500">
                                                            Doc: {member.external_staff_document}
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => handleRemove(member.id)}
                                            className="text-red-500 hover:text-red-700 transition p-1"
                                            title="Remover membro"
                                        >
                                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </section>

                    {/* Add Member Form */}
                    <section className="border-t border-gray-200 pt-5">
                        <h3 className="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-3">
                            Adicionar Membro
                        </h3>

                        <form onSubmit={handleAdd} className="space-y-4">
                            {/* Third party toggle */}
                            <label className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={isThirdParty}
                                    onChange={(e) => setIsThirdParty(e.target.checked)}
                                    className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <span className="text-sm text-gray-700">Terceiro (externo)</span>
                            </label>

                            {!isThirdParty ? (
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                        Usuario <span className="text-red-500">*</span>
                                    </label>
                                    <select
                                        value={userId}
                                        onChange={(e) => setUserId(e.target.value)}
                                        className={`w-full rounded-md border shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm ${
                                            errors.user_id ? 'border-red-300' : 'border-gray-300'
                                        }`}
                                    >
                                        <option value="">Selecione um usuario...</option>
                                        {users.map((u) => (
                                            <option key={u.id} value={u.id}>{u.name}</option>
                                        ))}
                                    </select>
                                    {errors.user_id && <p className="mt-1 text-sm text-red-600">{errors.user_id}</p>}
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Nome do Terceiro <span className="text-red-500">*</span>
                                        </label>
                                        <input
                                            type="text"
                                            value={externalName}
                                            onChange={(e) => setExternalName(e.target.value)}
                                            className={`w-full rounded-md border shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm ${
                                                errors.external_staff_name ? 'border-red-300' : 'border-gray-300'
                                            }`}
                                            placeholder="Nome completo"
                                        />
                                        {errors.external_staff_name && (
                                            <p className="mt-1 text-sm text-red-600">{errors.external_staff_name}</p>
                                        )}
                                    </div>
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-1">
                                            Documento
                                        </label>
                                        <input
                                            type="text"
                                            value={externalDocument}
                                            onChange={(e) => setExternalDocument(e.target.value)}
                                            className="w-full rounded-md border border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                            placeholder="CPF ou documento de identificacao"
                                        />
                                        {errors.external_staff_document && (
                                            <p className="mt-1 text-sm text-red-600">{errors.external_staff_document}</p>
                                        )}
                                    </div>
                                </div>
                            )}

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Funcao <span className="text-red-500">*</span>
                                </label>
                                <select
                                    value={role}
                                    onChange={(e) => setRole(e.target.value)}
                                    className={`w-full rounded-md border shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm ${
                                        errors.role ? 'border-red-300' : 'border-gray-300'
                                    }`}
                                >
                                    {TEAM_ROLES.map((r) => (
                                        <option key={r.value} value={r.value}>{r.label}</option>
                                    ))}
                                </select>
                                {errors.role && <p className="mt-1 text-sm text-red-600">{errors.role}</p>}
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" variant="primary" size="sm" disabled={processing} loading={processing}>
                                    {processing ? 'Adicionando...' : 'Adicionar Membro'}
                                </Button>
                            </div>
                        </form>
                    </section>
                </div>

                {/* Footer */}
                <div className="flex justify-end px-6 py-4 border-t border-gray-200 bg-gray-50 flex-shrink-0">
                    <Button variant="secondary" onClick={handleClose}>
                        Fechar
                    </Button>
                </div>
            </div>
        </Modal>
    );
}
