import { Head, router } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import {
    LifebuoyIcon,
    PlusIcon,
    FunnelIcon,
    XMarkIcon,
    ClockIcon,
    ExclamationTriangleIcon,
    CheckCircleIcon,
} from '@heroicons/react/24/outline';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import DataTable from '@/Components/DataTable';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

export default function Index({ tickets, filters, statusOptions, priorityOptions, departments, stores }) {
    const { hasPermission } = usePermissions();
    const { modals, selected, openModal, closeModal } = useModalManager(['create', 'detail', 'transition', 'assign']);
    const canCreate = hasPermission(PERMISSIONS.CREATE_TICKETS);
    const canManage = hasPermission(PERMISSIONS.MANAGE_TICKETS);

    const [stats, setStats] = useState(null);
    const [statsLoading, setStatsLoading] = useState(true);
    const [showFilters, setShowFilters] = useState(false);
    const [localFilters, setLocalFilters] = useState({
        search: filters?.search || '', status: filters?.status || '',
        priority: filters?.priority || '', department_id: filters?.department_id || '',
        date_from: filters?.date_from || '', date_to: filters?.date_to || '',
    });
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});
    const [formData, setFormData] = useState({ department_id: '', category_id: '', store_id: '', title: '', description: '', priority: 2 });
    const [categories, setCategories] = useState([]);
    const [detailData, setDetailData] = useState(null);
    const [detailLoading, setDetailLoading] = useState(false);
    const [commentText, setCommentText] = useState('');
    const [commentInternal, setCommentInternal] = useState(false);

    // Statistics
    useEffect(() => {
        fetch(route('helpdesk.statistics'), { headers: { 'Accept': 'application/json' } })
            .then(res => res.json())
            .then(data => { setStats(data); setStatsLoading(false); })
            .catch(() => setStatsLoading(false));
    }, []);

    const statisticsCards = [
        { label: 'Total', value: stats?.total, icon: LifebuoyIcon, color: 'indigo' },
        { label: 'Abertos', value: stats?.open, icon: ClockIcon, color: 'blue' },
        { label: 'Em Andamento', value: stats?.in_progress, icon: LifebuoyIcon, color: 'yellow' },
        { label: 'Pendentes', value: stats?.pending, icon: ExclamationTriangleIcon, color: 'orange' },
        { label: 'Resolvidos', value: stats?.resolved, icon: CheckCircleIcon, color: 'green' },
        { label: 'SLA Vencido', value: stats?.overdue, icon: ExclamationTriangleIcon, color: 'red' },
    ];

    // Load categories when department changes
    useEffect(() => {
        if (formData.department_id) {
            fetch(route('helpdesk.categories', formData.department_id), { headers: { 'Accept': 'application/json' } })
                .then(res => res.json())
                .then(data => setCategories(data))
                .catch(() => setCategories([]));
        } else {
            setCategories([]);
        }
    }, [formData.department_id]);

    const handleFilter = () => {
        router.get(route('helpdesk.index'), Object.fromEntries(Object.entries(localFilters).filter(([, v]) => v)), { preserveState: true });
    };

    const handleCreate = (e) => {
        e.preventDefault();
        setProcessing(true);
        router.post(route('helpdesk.store'), formData, {
            onSuccess: () => { closeModal('create'); setFormData({ department_id: '', category_id: '', store_id: '', title: '', description: '', priority: 2 }); },
            onError: (e) => setErrors(e),
            onFinish: () => setProcessing(false),
        });
    };

    const loadDetail = (ticketId) => {
        setDetailLoading(true);
        fetch(route('helpdesk.show', ticketId), { headers: { 'Accept': 'application/json' } })
            .then(res => res.json())
            .then(data => { setDetailData(data); setDetailLoading(false); })
            .catch(() => setDetailLoading(false));
    };

    const handleTransition = async (ticketId, newStatus) => {
        const res = await fetch(route('helpdesk.transition', ticketId), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify({ status: newStatus }),
        });
        if (res.ok) loadDetail(ticketId);
    };

    const handleAddComment = async () => {
        if (!commentText.trim() || !selected) return;
        await fetch(route('helpdesk.add-comment', selected.id), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
            body: JSON.stringify({ comment: commentText, is_internal: commentInternal }),
        });
        setCommentText('');
        setCommentInternal(false);
        loadDetail(selected.id);
    };

    const columns = [
        { key: 'id', label: '#', sortable: true },
        { key: 'title', label: 'Título', sortable: true },
        { key: 'requester_name', label: 'Solicitante' },
        { key: 'department_name', label: 'Departamento' },
        { key: 'priority_label', label: 'Prioridade', sortable: true, render: (row) => <StatusBadge variant={row.priority_color}>{row.priority_label}</StatusBadge> },
        { key: 'status_label', label: 'Status', sortable: true, render: (row) => (
            <div className="flex items-center gap-1">
                <StatusBadge variant={row.status_color}>{row.status_label}</StatusBadge>
                {row.is_overdue && <ExclamationTriangleIcon className="w-4 h-4 text-red-500" title="SLA vencido" />}
            </div>
        )},
        { key: 'technician_name', label: 'Técnico' },
        { key: 'created_at', label: 'Criado', sortable: true },
    ];

    return (
        <>
            <Head title="Helpdesk" />
            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="flex items-center justify-between mb-6">
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">Helpdesk</h1>
                            <p className="text-sm text-gray-500">Gerenciamento de chamados</p>
                        </div>
                        <div className="flex gap-2">
                            {hasPermission(PERMISSIONS.VIEW_HD_REPORTS) && (
                                <Button variant="outline" onClick={() => router.visit(route('helpdesk-reports.index'))}>Relatórios</Button>
                            )}
                            {canCreate && (
                                <Button variant="primary" icon={PlusIcon} onClick={() => openModal('create')}>Novo Chamado</Button>
                            )}
                        </div>
                    </div>

                    {/* Statistics */}
                    <StatisticsGrid cards={statisticsCards} loading={statsLoading} className="mb-6" />

                    {/* Filters */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="flex items-center gap-3">
                            <TextInput className="flex-1" placeholder="Buscar por título ou #ID..."
                                value={localFilters.search} onChange={e => setLocalFilters(p => ({ ...p, search: e.target.value }))}
                                onKeyDown={e => e.key === 'Enter' && handleFilter()} />
                            <Button variant="primary" onClick={handleFilter}>Buscar</Button>
                            <Button variant="outline" icon={FunnelIcon} onClick={() => setShowFilters(!showFilters)} />
                        </div>
                        {showFilters && (
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-3 mt-3 pt-3 border-t">
                                <select className="border-gray-300 rounded-lg text-sm" value={localFilters.status}
                                    onChange={e => setLocalFilters(p => ({ ...p, status: e.target.value }))}>
                                    <option value="">Todos os status</option>
                                    {Object.entries(statusOptions).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                                </select>
                                <select className="border-gray-300 rounded-lg text-sm" value={localFilters.priority}
                                    onChange={e => setLocalFilters(p => ({ ...p, priority: e.target.value }))}>
                                    <option value="">Todas as prioridades</option>
                                    {Object.entries(priorityOptions).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                                </select>
                                <select className="border-gray-300 rounded-lg text-sm" value={localFilters.department_id}
                                    onChange={e => setLocalFilters(p => ({ ...p, department_id: e.target.value }))}>
                                    <option value="">Todos os departamentos</option>
                                    {departments.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
                                </select>
                                <div className="flex gap-2">
                                    <Button variant="primary" size="sm" className="flex-1" onClick={handleFilter}>Filtrar</Button>
                                    <Button variant="light" size="sm" onClick={() => { setLocalFilters({ search: '', status: '', priority: '', department_id: '', date_from: '', date_to: '' }); router.get(route('helpdesk.index')); }}>Limpar</Button>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Table */}
                    <DataTable data={tickets} columns={columns}
                        onView={(row) => { openModal('detail', row); loadDetail(row.id); }}
                        onDelete={canManage ? (row) => { if (confirm('Excluir chamado #' + row.id + '?')) router.delete(route('helpdesk.destroy', row.id)); } : undefined}
                    />
                </div>
            </div>

            {/* Create Modal */}
            <StandardModal show={modals.create} onClose={() => closeModal('create')}
                title="Novo Chamado" headerColor="bg-indigo-600" headerIcon={<LifebuoyIcon className="h-5 w-5" />}
                maxWidth="2xl" onSubmit={handleCreate}
                footer={<StandardModal.Footer onCancel={() => closeModal('create')} onSubmit="submit" submitLabel="Criar Chamado" processing={processing} />}>
                <StandardModal.Section title="Dados do Chamado">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="Departamento *" />
                            <select className="w-full mt-1 border-gray-300 rounded-lg text-sm" value={formData.department_id}
                                onChange={e => setFormData(p => ({ ...p, department_id: e.target.value, category_id: '' }))}>
                                <option value="">Selecione...</option>
                                {departments.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
                            </select>
                            <InputError message={errors.department_id} />
                        </div>
                        <div>
                            <InputLabel value="Categoria" />
                            <select className="w-full mt-1 border-gray-300 rounded-lg text-sm" value={formData.category_id}
                                onChange={e => setFormData(p => ({ ...p, category_id: e.target.value }))}>
                                <option value="">Selecione...</option>
                                {categories.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Prioridade" />
                            <select className="w-full mt-1 border-gray-300 rounded-lg text-sm" value={formData.priority}
                                onChange={e => setFormData(p => ({ ...p, priority: parseInt(e.target.value) }))}>
                                {Object.entries(priorityOptions).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Loja (opcional)" />
                            <select className="w-full mt-1 border-gray-300 rounded-lg text-sm" value={formData.store_id}
                                onChange={e => setFormData(p => ({ ...p, store_id: e.target.value }))}>
                                <option value="">Todas</option>
                                {stores.map(s => <option key={s.id} value={s.code}>{s.name}</option>)}
                            </select>
                        </div>
                    </div>
                    <div className="mt-4">
                        <InputLabel value="Título *" />
                        <TextInput className="w-full mt-1" value={formData.title} onChange={e => setFormData(p => ({ ...p, title: e.target.value }))} />
                        <InputError message={errors.title} />
                    </div>
                    <div className="mt-4">
                        <InputLabel value="Descrição *" />
                        <textarea className="w-full mt-1 border-gray-300 rounded-lg text-sm" rows={4} value={formData.description}
                            onChange={e => setFormData(p => ({ ...p, description: e.target.value }))} />
                        <InputError message={errors.description} />
                    </div>
                </StandardModal.Section>
            </StandardModal>

            {/* Detail Modal */}
            {selected && modals.detail && (
                <StandardModal show={modals.detail} onClose={() => { closeModal('detail'); setDetailData(null); }}
                    title={detailData?.ticket ? `#${detailData.ticket.id} - ${detailData.ticket.title}` : 'Carregando...'}
                    subtitle={detailData?.ticket?.department_name}
                    headerColor="bg-gray-700" headerIcon={<LifebuoyIcon className="h-5 w-5" />}
                    headerBadges={detailData?.ticket ? [
                        { text: detailData.ticket.status_label, className: 'bg-white/20 text-white' },
                        { text: detailData.ticket.priority_label, className: 'bg-white/10 text-white' },
                    ] : []}
                    maxWidth="4xl" loading={detailLoading}
                    footer={
                        <StandardModal.Footer onCancel={() => { closeModal('detail'); setDetailData(null); }} cancelLabel="Fechar"
                            extraButtons={canManage && detailData?.ticket ? [
                                ...Object.entries(detailData.ticket.transition_labels || {}).map(([status, label]) => (
                                    <Button key={status} variant={status === 'cancelled' ? 'danger' : 'outline'} size="sm"
                                        onClick={() => handleTransition(selected.id, status)}>
                                        {label}
                                    </Button>
                                )),
                            ] : []}
                        />
                    }>
                    {detailData?.ticket && (
                        <>
                            <StandardModal.Section title="Informações">
                                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <StandardModal.Field label="Solicitante" value={detailData.ticket.requester_name} />
                                    <StandardModal.Field label="Técnico" value={detailData.ticket.technician_name || 'Não atribuído'} />
                                    <StandardModal.Field label="Categoria" value={detailData.ticket.category_name || '-'} />
                                    <StandardModal.Field label="SLA" value={
                                        detailData.ticket.is_overdue
                                            ? 'VENCIDO'
                                            : detailData.ticket.sla_remaining_hours !== null
                                                ? `${detailData.ticket.sla_remaining_hours}h restantes`
                                                : '-'
                                    } />
                                </div>
                                <div className="mt-3 p-3 bg-gray-50 rounded-lg text-sm text-gray-700 whitespace-pre-wrap">
                                    {detailData.ticket.description}
                                </div>
                            </StandardModal.Section>

                            {/* Interactions Timeline */}
                            <StandardModal.Section title={`Interações (${detailData.interactions?.length || 0})`}>
                                <div className="space-y-3 max-h-60 overflow-y-auto">
                                    {(detailData.interactions || []).map(interaction => (
                                        <div key={interaction.id} className={`p-3 rounded-lg border text-sm ${
                                            interaction.type === 'comment'
                                                ? interaction.is_internal ? 'bg-yellow-50 border-yellow-200' : 'bg-white border-gray-200'
                                                : 'bg-gray-50 border-gray-200'
                                        }`}>
                                            <div className="flex items-center justify-between mb-1">
                                                <span className="font-medium text-gray-900">
                                                    {interaction.user_name}
                                                    {interaction.is_internal && <StatusBadge variant="warning" className="ml-1">Nota Interna</StatusBadge>}
                                                </span>
                                                <span className="text-xs text-gray-400">{interaction.created_at}</span>
                                            </div>
                                            {interaction.type === 'comment' && <p className="text-gray-700 whitespace-pre-wrap">{interaction.comment}</p>}
                                            {interaction.type === 'status_change' && (
                                                <p className="text-gray-500 italic">
                                                    Status: <StatusBadge variant="gray">{interaction.old_value || 'Novo'}</StatusBadge>
                                                    {' → '}<StatusBadge variant="info">{interaction.new_value}</StatusBadge>
                                                    {interaction.comment && <span className="block mt-1 text-gray-600">{interaction.comment}</span>}
                                                </p>
                                            )}
                                            {interaction.type === 'assignment' && (
                                                <p className="text-gray-500 italic">{interaction.comment}</p>
                                            )}
                                            {interaction.type === 'priority_change' && (
                                                <p className="text-gray-500 italic">
                                                    Prioridade: {interaction.old_value} → {interaction.new_value}
                                                </p>
                                            )}
                                            {interaction.attachments?.length > 0 && (
                                                <div className="mt-1 flex gap-2">
                                                    {interaction.attachments.map(a => (
                                                        <a key={a.id} href={a.url} target="_blank" rel="noopener" className="text-xs text-indigo-600 underline">{a.name} ({a.size})</a>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                    ))}
                                </div>

                                {/* Add Comment */}
                                {canCreate && (
                                    <div className="mt-3 pt-3 border-t space-y-2">
                                        <textarea className="w-full border-gray-300 rounded-lg text-sm" rows={2}
                                            placeholder="Adicionar comentário..."
                                            value={commentText} onChange={e => setCommentText(e.target.value)} />
                                        <div className="flex items-center justify-between">
                                            <label className="flex items-center gap-2 text-xs text-gray-500 cursor-pointer">
                                                <input type="checkbox" className="rounded border-gray-300 text-indigo-600"
                                                    checked={commentInternal} onChange={e => setCommentInternal(e.target.checked)} />
                                                Nota interna (não visível ao solicitante)
                                            </label>
                                            <Button variant="primary" size="sm" onClick={handleAddComment} disabled={!commentText.trim()}>
                                                Comentar
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </StandardModal.Section>
                        </>
                    )}
                </StandardModal>
            )}
        </>
    );
}
