import StandardModal from '@/Components/StandardModal';
import InputError from '@/Components/InputError';
import ImageUpload from '@/Components/ImageUpload';
import UserAvatar from '@/Components/UserAvatar';
import { useForm, router } from '@inertiajs/react';
import { useEffect } from 'react';
import { PencilSquareIcon, TrashIcon } from '@heroicons/react/24/outline';

export default function UserEditModal({ show, onClose, user, roles = {}, stores = [] }) {
    const { data, setData, processing, errors, reset } = useForm({
        name: '',
        nickname: '',
        email: '',
        username: '',
        role: 'user',
        avatar: null,
        remove_avatar: false,
        store_id: '',
        status_id: '1',
    });

    useEffect(() => {
        if (user && show) {
            setData({
                name: user.name || '',
                nickname: user.nickname || '',
                email: user.email || '',
                username: user.username || '',
                role: user.role || 'user',
                avatar: null,
                remove_avatar: false,
                store_id: user.store_id || '',
                status_id: user.status_id || '1',
            });
        } else if (!show) {
            reset();
        }
    }, [user, show]);

    const handleSubmit = () => {
        const postData = { ...data, _method: 'put' };
        if (!(postData.avatar instanceof File)) {
            delete postData.avatar;
        }

        router.post(route('users.update', user.id), postData, {
            forceFormData: true,
            onSuccess: () => onClose(),
        });
    };

    const handleClose = () => {
        reset();
        onClose();
    };

    return (
        <StandardModal
            show={show}
            onClose={handleClose}
            title="Editar Usuário"
            subtitle={user?.name}
            headerIcon={<PencilSquareIcon className="h-5 w-5" />}
            headerColor="bg-indigo-600"
            maxWidth="7xl"
            onSubmit={handleSubmit}
            footer={
                <StandardModal.Footer
                    onCancel={handleClose}
                    onSubmit="submit"
                    submitLabel={processing ? 'Salvando...' : 'Salvar Alterações'}
                    processing={processing}
                />
            }
        >
            {user && <>
                {/* Info do usuário */}
                <div className="flex items-center justify-between bg-gray-50 rounded-lg p-3">
                    <div className="text-xs text-gray-600 space-y-0.5">
                        <p><span className="font-medium">Criado em:</span> {new Date(user.created_at).toLocaleDateString('pt-BR')}</p>
                        <p><span className="font-medium">Email verificado:</span> {user.email_verified_at ? 'Sim' : 'Não'}</p>
                    </div>
                </div>

                {/* Foto */}
                <StandardModal.Section title="Foto do Usuário" icon="📸">
                    <div className="flex items-center gap-4 mb-4">
                        <UserAvatar user={user} size="lg" />
                        <div>
                            <p className="text-sm font-medium text-gray-900">Foto atual</p>
                            <p className="text-xs text-gray-500">
                                {user.avatar ? 'Foto personalizada' : 'Avatar padrão com iniciais'}
                            </p>
                        </div>
                    </div>

                    <ImageUpload
                        label="Nova Foto (Opcional)"
                        value={data.avatar}
                        onChange={(file) => setData('avatar', file)}
                        error={errors.avatar}
                        maxSize={2}
                        className="mb-0"
                    />

                    {user.avatar && (
                        <div className="flex items-center justify-between p-2 bg-gray-50 rounded border border-gray-200 mt-3">
                            <span className="text-xs text-gray-600">Remover foto atual</span>
                            <button type="button" onClick={() => setData('remove_avatar', !data.remove_avatar)}
                                className={`flex items-center gap-1 px-2 py-1 rounded text-xs font-medium transition-colors ${
                                    data.remove_avatar ? 'bg-red-100 text-red-700 hover:bg-red-200' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                                }`}>
                                <TrashIcon className="h-3 w-3" />
                                {data.remove_avatar ? 'Cancelar remoção' : 'Remover foto'}
                            </button>
                        </div>
                    )}
                </StandardModal.Section>

                {/* Dados Pessoais */}
                <StandardModal.Section title="Dados Pessoais" icon="👤">
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div className="lg:col-span-2">
                            <label className="block text-sm font-medium text-gray-700 mb-1">Nome Completo *</label>
                            <input type="text" value={data.name} onChange={e => setData('name', e.target.value)}
                                required autoFocus placeholder="Digite o nome completo"
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            <InputError message={errors.name} className="mt-1" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Apelido</label>
                            <input type="text" value={data.nickname} onChange={e => setData('nickname', e.target.value)}
                                placeholder="Apelido (opcional)"
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            <InputError message={errors.nickname} className="mt-1" />
                        </div>
                        <div className="lg:col-span-2">
                            <label className="block text-sm font-medium text-gray-700 mb-1">E-mail *</label>
                            <input type="email" value={data.email} onChange={e => setData('email', e.target.value)}
                                required placeholder="Digite o e-mail"
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            <InputError message={errors.email} className="mt-1" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Nome de Usuário (Login)</label>
                            <input type="text" value={data.username} onChange={e => setData('username', e.target.value)}
                                placeholder="Login (opcional)"
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            <InputError message={errors.username} className="mt-1" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Loja *</label>
                            <select value={data.store_id} onChange={e => setData('store_id', e.target.value)} required
                                className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">Selecione uma loja</option>
                                {stores.map(s => <option key={s.code} value={s.code}>{s.name}</option>)}
                            </select>
                            <InputError message={errors.store_id} className="mt-1" />
                        </div>
                    </div>
                </StandardModal.Section>

                {/* Nível de Acesso */}
                <StandardModal.Section title="Nível de Acesso" icon="🔑">
                    <div className="grid grid-cols-2 gap-2">
                        {Object.entries(roles).map(([value, label]) => (
                            <label key={value}
                                className={`flex items-center p-3 border rounded-lg cursor-pointer transition-colors ${
                                    data.role === value ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300'
                                }`}>
                                <input type="radio" name="role" value={value} checked={data.role === value}
                                    onChange={e => setData('role', e.target.value)} className="sr-only" />
                                <div className={`w-2.5 h-2.5 rounded-full mr-2.5 ${data.role === value ? 'bg-indigo-600' : 'bg-gray-300'}`} />
                                <span className="text-sm font-medium text-gray-900">{label}</span>
                            </label>
                        ))}
                    </div>
                    <InputError message={errors.role} className="mt-2" />

                    <div className="bg-blue-50 p-3 rounded-lg mt-4">
                        <p className="text-xs text-blue-700">
                            Alterações de nível de acesso são aplicadas imediatamente.
                        </p>
                    </div>
                </StandardModal.Section>
            </>}
        </StandardModal>
    );
}
