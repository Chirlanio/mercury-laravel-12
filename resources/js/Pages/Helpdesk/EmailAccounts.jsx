import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import {
    PlusIcon,
    PencilSquareIcon,
    TrashIcon,
    BoltIcon,
    CheckCircleIcon,
    XCircleIcon,
    EnvelopeIcon,
    ArrowLeftIcon,
} from '@heroicons/react/24/outline';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Button from '@/Components/Button';
import DataTable from '@/Components/DataTable';
import ActionButtons from '@/Components/ActionButtons';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';
import EmptyState from '@/Components/Shared/EmptyState';
import DeleteConfirmModal from '@/Components/Shared/DeleteConfirmModal';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import { toast } from 'react-toastify';

/**
 * Admin page for managing IMAP mailboxes polled by helpdesk:imap-fetch.
 *
 * Each row is one mailbox. On save we encrypt the password server-side and
 * never send it back to the client — the table only shows whether a
 * password is set. "Testar" connects and reports success/failure inline.
 */
export default function EmailAccounts({ accounts = [], departments = [], defaults = {}, encryptionOptions = [] }) {
    const [showForm, setShowForm] = useState(false);
    const [editingId, setEditingId] = useState(null);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [testingId, setTestingId] = useState(null);
    const [testResults, setTestResults] = useState({});

    const form = useForm({
        label: '',
        email_address: '',
        department_id: '',
        host: '',
        port: defaults.port ?? 993,
        encryption: defaults.encryption ?? 'ssl',
        username: '',
        password: '',
        processed_folder: defaults.processed_folder ?? 'INBOX.Processados',
        validate_cert: defaults.validate_cert ?? true,
        active: true,
    });

    const openCreate = () => {
        setEditingId(null);
        form.reset();
        form.setData({
            label: '',
            email_address: '',
            department_id: departments[0]?.id ?? '',
            host: 'imap.hostinger.com',
            port: defaults.port ?? 993,
            encryption: defaults.encryption ?? 'ssl',
            username: '',
            password: '',
            processed_folder: defaults.processed_folder ?? 'INBOX.Processados',
            validate_cert: defaults.validate_cert ?? true,
            active: true,
        });
        form.clearErrors();
        setShowForm(true);
    };

    const openEdit = (account) => {
        setEditingId(account.id);
        form.setData({
            label: account.label ?? '',
            email_address: account.email_address ?? '',
            department_id: account.department_id ?? '',
            host: account.host ?? '',
            port: account.port ?? 993,
            encryption: account.encryption ?? 'ssl',
            username: account.username ?? '',
            password: '', // blank = keep current
            processed_folder: account.processed_folder ?? 'INBOX.Processados',
            validate_cert: account.validate_cert ?? true,
            active: account.active ?? true,
        });
        form.clearErrors();
        setShowForm(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const onSuccess = () => {
            setShowForm(false);
            form.reset();
        };
        if (editingId) {
            form.put(route('helpdesk.email-accounts.update', editingId), { onSuccess, preserveScroll: true });
        } else {
            form.post(route('helpdesk.email-accounts.store'), { onSuccess, preserveScroll: true });
        }
    };

    const handleDelete = () => {
        if (!deleteTarget) return;
        router.delete(route('helpdesk.email-accounts.destroy', deleteTarget.id), {
            preserveScroll: true,
            onSuccess: () => setDeleteTarget(null),
        });
    };

    const handleTest = async (accountId) => {
        setTestingId(accountId);
        try {
            const response = await fetch(route('helpdesk.email-accounts.test', accountId), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                },
            });
            const data = await response.json();
            setTestResults((prev) => ({ ...prev, [accountId]: data }));
            if (data.ok) {
                toast.success(data.message || 'Conexão bem-sucedida.');
            } else {
                toast.error(data.message || 'Falha na conexão.');
            }
        } catch (err) {
            toast.error('Erro ao testar: ' + err.message);
        } finally {
            setTestingId(null);
        }
    };

    const columns = [
        {
            key: 'label',
            label: 'Nome',
            render: (account) => (
                <div className="flex items-center gap-2">
                    <EnvelopeIcon className="w-4 h-4 text-indigo-500 shrink-0" />
                    <div>
                        <div className="font-medium text-gray-900">{account.label || account.email_address}</div>
                        <div className="text-xs text-gray-500">{account.email_address}</div>
                    </div>
                </div>
            ),
        },
        {
            key: 'department',
            label: 'Departamento',
            render: (account) => {
                const dept = departments.find((d) => d.id === account.department_id);
                return <span className="text-sm text-gray-700">{dept?.name || '—'}</span>;
            },
        },
        {
            key: 'host',
            label: 'Servidor',
            render: (account) => (
                <div className="text-xs font-mono text-gray-700">
                    {account.host}:{account.port}
                    <div className="text-gray-400 uppercase">{account.encryption || 'none'}</div>
                </div>
            ),
        },
        {
            key: 'active',
            label: 'Status',
            render: (account) => (
                <StatusBadge
                    variant={account.active ? 'success' : 'gray'}
                    dot
                >
                    {account.active ? 'Ativa' : 'Inativa'}
                </StatusBadge>
            ),
        },
        {
            key: 'test',
            label: 'Conexão',
            render: (account) => {
                const result = testResults[account.id];
                return (
                    <div className="flex items-center gap-2">
                        <Button
                            size="xs"
                            variant="outline"
                            icon={BoltIcon}
                            loading={testingId === account.id}
                            onClick={() => handleTest(account.id)}
                        >
                            Testar
                        </Button>
                        {result && (
                            result.ok ? (
                                <CheckCircleIcon className="w-5 h-5 text-green-500" title={result.message} />
                            ) : (
                                <XCircleIcon className="w-5 h-5 text-red-500" title={result.message} />
                            )
                        )}
                    </div>
                );
            },
        },
        {
            key: 'actions',
            label: 'Ações',
            render: (account) => (
                <ActionButtons
                    onEdit={() => openEdit(account)}
                    onDelete={() => setDeleteTarget(account)}
                />
            ),
        },
    ];

    return (
        <AuthenticatedLayout>
            <Head title="Contas de E-mail — Helpdesk" />

            <div className="py-12">
                <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="flex items-center justify-between mb-6">
                        <div className="flex items-center gap-3">
                            <a
                                href={route('helpdesk.index')}
                                className="text-gray-400 hover:text-gray-600"
                                title="Voltar para o helpdesk"
                            >
                                <ArrowLeftIcon className="w-5 h-5" />
                            </a>
                            <div>
                                <h1 className="text-xl font-semibold text-gray-900">Contas de E-mail</h1>
                                <p className="text-sm text-gray-500">
                                    Caixas IMAP que o Mercury monitora a cada minuto para converter e-mails em chamados.
                                </p>
                            </div>
                        </div>
                        <Button variant="primary" icon={PlusIcon} onClick={openCreate}>
                            Nova conta
                        </Button>
                    </div>

                    {/* Hostinger hint card */}
                    <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 text-sm text-blue-900">
                        <div className="font-semibold mb-1">Configuração Hostinger</div>
                        <div className="font-mono text-xs">
                            Servidor: <span className="bg-blue-100 px-1 rounded">imap.hostinger.com</span> ·
                            Porta: <span className="bg-blue-100 px-1 rounded">993</span> ·
                            Criptografia: <span className="bg-blue-100 px-1 rounded">SSL</span> ·
                            Usuário: <span className="bg-blue-100 px-1 rounded">seu e-mail completo</span>
                        </div>
                    </div>

                    {/* Table */}
                    {accounts.length > 0 ? (
                        <div className="bg-white shadow-sm rounded-lg overflow-hidden">
                            <DataTable
                                data={accounts}
                                columns={columns}
                                rowKey="id"
                                pagination={false}
                                searchable={false}
                            />
                        </div>
                    ) : (
                        <EmptyState
                            icon={EnvelopeIcon}
                            title="Nenhuma conta cadastrada"
                            description="Adicione a primeira caixa IMAP para começar a receber chamados por e-mail."
                            action={
                                <Button variant="primary" icon={PlusIcon} onClick={openCreate}>
                                    Nova conta
                                </Button>
                            }
                        />
                    )}
                </div>
            </div>

            {/* Create/Edit modal */}
            <StandardModal
                show={showForm}
                onClose={() => setShowForm(false)}
                title={editingId ? 'Editar conta' : 'Nova conta de e-mail'}
                headerColor="bg-indigo-600"
                headerIcon={EnvelopeIcon}
                maxWidth="2xl"
                onSubmit={handleSubmit}
                footer={
                    <StandardModal.Footer
                        onCancel={() => setShowForm(false)}
                        onSubmit="submit"
                        submitLabel={editingId ? 'Salvar' : 'Criar'}
                        processing={form.processing}
                    />
                }
            >
                <StandardModal.Section title="Identificação">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel htmlFor="label" value="Rótulo" />
                            <TextInput
                                id="label"
                                value={form.data.label}
                                onChange={(e) => form.setData('label', e.target.value)}
                                placeholder="Ex: TI, RH, Suporte"
                                className="mt-1 block w-full"
                            />
                            <InputError message={form.errors.label} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel htmlFor="department_id" value="Departamento" />
                            <select
                                id="department_id"
                                value={form.data.department_id}
                                onChange={(e) => form.setData('department_id', e.target.value)}
                                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                            >
                                <option value="">Selecione...</option>
                                {departments.map((d) => (
                                    <option key={d.id} value={d.id}>
                                        {d.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.department_id} className="mt-1" />
                        </div>
                        <div className="sm:col-span-2">
                            <InputLabel htmlFor="email_address" value="Endereço de e-mail" />
                            <TextInput
                                id="email_address"
                                type="email"
                                value={form.data.email_address}
                                onChange={(e) => form.setData('email_address', e.target.value)}
                                placeholder="ti@seudominio.com.br"
                                className="mt-1 block w-full"
                            />
                            <InputError message={form.errors.email_address} className="mt-1" />
                        </div>
                    </div>
                </StandardModal.Section>

                <StandardModal.Section title="Conexão IMAP">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div className="sm:col-span-2">
                            <InputLabel htmlFor="host" value="Servidor IMAP" />
                            <TextInput
                                id="host"
                                value={form.data.host}
                                onChange={(e) => form.setData('host', e.target.value)}
                                placeholder="imap.hostinger.com"
                                className="mt-1 block w-full"
                            />
                            <InputError message={form.errors.host} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel htmlFor="port" value="Porta" />
                            <TextInput
                                id="port"
                                type="number"
                                value={form.data.port}
                                onChange={(e) => form.setData('port', e.target.value)}
                                className="mt-1 block w-full"
                            />
                            <InputError message={form.errors.port} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel htmlFor="encryption" value="Criptografia" />
                            <select
                                id="encryption"
                                value={form.data.encryption}
                                onChange={(e) => form.setData('encryption', e.target.value)}
                                className="mt-1 block w-full border-gray-300 rounded-md shadow-sm"
                            >
                                {encryptionOptions.map((opt) => (
                                    <option key={opt.value} value={opt.value}>
                                        {opt.label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.encryption} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel htmlFor="username" value="Usuário IMAP" />
                            <TextInput
                                id="username"
                                value={form.data.username}
                                onChange={(e) => form.setData('username', e.target.value)}
                                placeholder="Normalmente o próprio e-mail"
                                className="mt-1 block w-full"
                            />
                            <InputError message={form.errors.username} className="mt-1" />
                        </div>
                        <div>
                            <InputLabel htmlFor="password" value={editingId ? 'Senha (deixe em branco para manter)' : 'Senha'} />
                            <TextInput
                                id="password"
                                type="password"
                                value={form.data.password}
                                onChange={(e) => form.setData('password', e.target.value)}
                                placeholder={editingId ? '•••••••• (não alterar)' : 'Senha da caixa IMAP'}
                                className="mt-1 block w-full"
                                autoComplete="new-password"
                            />
                            <InputError message={form.errors.password} className="mt-1" />
                        </div>
                        <div className="sm:col-span-2">
                            <InputLabel htmlFor="processed_folder" value="Pasta de processados" />
                            <TextInput
                                id="processed_folder"
                                value={form.data.processed_folder}
                                onChange={(e) => form.setData('processed_folder', e.target.value)}
                                className="mt-1 block w-full font-mono text-sm"
                            />
                            <p className="text-xs text-gray-500 mt-1">
                                Pasta para onde o e-mail é movido após virar chamado. Criada automaticamente se não existir.
                            </p>
                            <InputError message={form.errors.processed_folder} className="mt-1" />
                        </div>
                        <div className="sm:col-span-2 flex items-center gap-6">
                            <label className="flex items-center gap-2 text-sm text-gray-700">
                                <input
                                    type="checkbox"
                                    checked={form.data.validate_cert}
                                    onChange={(e) => form.setData('validate_cert', e.target.checked)}
                                    className="rounded border-gray-300"
                                />
                                Validar certificado SSL
                            </label>
                            <label className="flex items-center gap-2 text-sm text-gray-700">
                                <input
                                    type="checkbox"
                                    checked={form.data.active}
                                    onChange={(e) => form.setData('active', e.target.checked)}
                                    className="rounded border-gray-300"
                                />
                                Conta ativa
                            </label>
                        </div>
                    </div>
                </StandardModal.Section>
            </StandardModal>

            {/* Delete confirmation */}
            <DeleteConfirmModal
                show={deleteTarget !== null}
                onClose={() => setDeleteTarget(null)}
                onConfirm={handleDelete}
                itemType="conta de e-mail"
                itemName={deleteTarget?.label || deleteTarget?.email_address}
                details={
                    deleteTarget
                        ? [
                              { label: 'E-mail', value: deleteTarget.email_address },
                              { label: 'Servidor', value: `${deleteTarget.host}:${deleteTarget.port}` },
                          ]
                        : []
                }
                warningMessage="Os chamados já criados a partir desta conta serão preservados — apenas a configuração de sincronização será removida."
            />
        </AuthenticatedLayout>
    );
}
