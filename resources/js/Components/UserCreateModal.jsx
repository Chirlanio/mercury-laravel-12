import StandardModal from '@/Components/StandardModal';
import InputError from '@/Components/InputError';
import ImageUpload from '@/Components/ImageUpload';
import { useForm } from '@inertiajs/react';
import { UserPlusIcon } from '@heroicons/react/24/outline';

export default function UserCreateModal({ show, onClose, roles = {}, stores = [] }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        nickname: '',
        email: '',
        username: '',
        password: '',
        password_confirmation: '',
        role: 'user',
        avatar: null,
        store_id: '',
        status_id: '1',
    });

    const handleSubmit = () => {
        post(route('users.store'), {
            onSuccess: () => { reset(); onClose(); },
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
            title="Novo Usuário"
            headerIcon={<UserPlusIcon className="h-5 w-5" />}
            headerColor="bg-indigo-600"
            maxWidth="7xl"
            onSubmit={handleSubmit}
            footer={
                <StandardModal.Footer
                    onCancel={handleClose}
                    onSubmit="submit"
                    submitLabel={processing ? 'Criando...' : 'Criar Usuário'}
                    processing={processing}
                />
            }
        >
            {/* Foto */}
            <StandardModal.Section title="Foto do Usuário" icon="📸">
                <ImageUpload
                    label="Foto (Opcional)"
                    value={data.avatar}
                    onChange={(file) => setData('avatar', file)}
                    error={errors.avatar}
                    maxSize={2}
                    className="mb-0"
                />
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
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Senha *</label>
                        <input type="password" value={data.password} onChange={e => setData('password', e.target.value)}
                            required placeholder="Senha temporária" autoComplete="new-password"
                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                        <InputError message={errors.password} className="mt-1" />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Confirmar Senha *</label>
                        <input type="password" value={data.password_confirmation} onChange={e => setData('password_confirmation', e.target.value)}
                            required placeholder="Confirme a senha" autoComplete="new-password"
                            className="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                        <InputError message={errors.password_confirmation} className="mt-1" />
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

                <div className="bg-gray-50 p-3 rounded-lg mt-4">
                    <p className="text-xs text-gray-600">
                        O e-mail será verificado automaticamente e o usuário receberá acesso imediato ao sistema.
                    </p>
                </div>
            </StandardModal.Section>
        </StandardModal>
    );
}
