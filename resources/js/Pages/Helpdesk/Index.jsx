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
    ArrowDownTrayIcon,
    ChartBarIcon,
    ListBulletIcon,
    GlobeAltIcon,
    ChatBubbleLeftRightIcon,
    EnvelopeIcon,
    CodeBracketIcon,
    ArrowUpTrayIcon,
} from '@heroicons/react/24/outline';

/**
 * Compact badge showing which channel a ticket originated from. The source
 * field comes from the backend as one of: web, whatsapp, email, api, import.
 */
const SOURCE_ICONS = {
    web: GlobeAltIcon,
    whatsapp: ChatBubbleLeftRightIcon,
    email: EnvelopeIcon,
    api: CodeBracketIcon,
    import: ArrowUpTrayIcon,
};

const SOURCE_COLORS = {
    web: 'text-gray-500 bg-gray-100',
    whatsapp: 'text-green-700 bg-green-100',
    email: 'text-blue-700 bg-blue-100',
    api: 'text-purple-700 bg-purple-100',
    import: 'text-orange-700 bg-orange-100',
};

function SourceBadge({ source = 'web', label = 'Web' }) {
    const Icon = SOURCE_ICONS[source] || GlobeAltIcon;
    const color = SOURCE_COLORS[source] || SOURCE_COLORS.web;
    return (
        <span
            className={`inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium ${color}`}
            title={`Origem: ${label}`}
        >
            <Icon className="w-3 h-3" />
            {label}
        </span>
    );
}
import {
    LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip,
    ResponsiveContainer, PieChart, Pie, Cell, Legend,
} from 'recharts';
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

const CHART_COLORS = ['#4f46e5', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6', '#06b6d4'];

function ReportsPanel({ reports, departments, filters, onFilterChange, onApplyFilter }) {
    const sla = reports?.slaCompliance;
    const reportCards = [
        {
            label: 'Taxa SLA',
            value: sla?.compliance_rate ?? 0,
            format: 'percentage',
            icon: CheckCircleIcon,
            color: (sla?.compliance_rate ?? 0) >= 80 ? 'green' : 'red',
        },
        { label: 'Dentro do SLA', value: sla?.within_sla ?? 0, icon: CheckCircleIcon, color: 'green' },
        { label: 'SLA Violado', value: sla?.breached ?? 0, icon: ClockIcon, color: 'red' },
        { label: 'Tempo Médio', value: `${reports?.averageResolutionTime ?? 0}h`, icon: ClockIcon, color: 'blue' },
    ];

    return (
        <div>
            {/* 1. KPI cards first (consistent with tickets tab) */}
            <StatisticsGrid cards={reportCards} className="mb-4 sm:mb-6" />

            {/* 2. Scoped filters (department + date range). Mobile: stacked.
                   Tablet: 2 cols. Desktop: 4 cols. */}
            <div className="bg-white shadow-sm rounded-lg p-3 sm:p-4 mb-4 sm:mb-6">
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div>
                        <label className="text-xs font-medium text-gray-600">Departamento</label>
                        <select className="mt-1 w-full border-gray-300 rounded-lg text-sm"
                            value={filters.department_id}
                            onChange={e => onFilterChange(p => ({ ...p, department_id: e.target.value }))}>
                            <option value="">Todos</option>
                            {departments.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="text-xs font-medium text-gray-600">De</label>
                        <input type="date" className="mt-1 w-full border-gray-300 rounded-lg text-sm"
                            value={filters.date_from}
                            onChange={e => onFilterChange(p => ({ ...p, date_from: e.target.value }))} />
                    </div>
                    <div>
                        <label className="text-xs font-medium text-gray-600">Até</label>
                        <input type="date" className="mt-1 w-full border-gray-300 rounded-lg text-sm"
                            value={filters.date_to}
                            onChange={e => onFilterChange(p => ({ ...p, date_to: e.target.value }))} />
                    </div>
                    <div className="flex items-end">
                        <Button variant="primary" size="sm" className="w-full" onClick={onApplyFilter}>
                            Aplicar
                        </Button>
                    </div>
                </div>
            </div>

            {/* 3. Charts: stacked on mobile, side-by-side on large screens */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
                <div className="bg-white shadow-sm rounded-lg p-4 sm:p-6">
                    <h3 className="text-sm font-semibold text-gray-900 mb-3 sm:mb-4">Volume de Chamados por Dia</h3>
                    {reports?.volumeByDay?.length > 0 ? (
                        <ResponsiveContainer width="100%" height={260}>
                            <LineChart data={reports.volumeByDay} margin={{ top: 5, right: 10, left: -20, bottom: 5 }}>
                                <CartesianGrid strokeDasharray="3 3" />
                                <XAxis dataKey="date" tick={{ fontSize: 10 }} />
                                <YAxis tick={{ fontSize: 10 }} />
                                <Tooltip />
                                <Line type="monotone" dataKey="count" stroke="#4f46e5" strokeWidth={2} dot={{ r: 3 }} name="Chamados" />
                            </LineChart>
                        </ResponsiveContainer>
                    ) : (
                        <p className="text-sm text-gray-400 text-center py-12">Sem dados para o período.</p>
                    )}
                </div>

                <div className="bg-white shadow-sm rounded-lg p-4 sm:p-6">
                    <h3 className="text-sm font-semibold text-gray-900 mb-3 sm:mb-4">Distribuição por Departamento</h3>
                    {reports?.distributionByDepartment?.length > 0 ? (
                        <ResponsiveContainer width="100%" height={260}>
                            <PieChart>
                                <Pie data={reports.distributionByDepartment} dataKey="count" nameKey="department"
                                    cx="50%" cy="50%" outerRadius="75%"
                                    label={({ department, percent }) => `${department} ${(percent * 100).toFixed(0)}%`}>
                                    {reports.distributionByDepartment.map((_, idx) => (
                                        <Cell key={idx} fill={CHART_COLORS[idx % CHART_COLORS.length]} />
                                    ))}
                                </Pie>
                                <Tooltip />
                                <Legend wrapperStyle={{ fontSize: '11px' }} />
                            </PieChart>
                        </ResponsiveContainer>
                    ) : (
                        <p className="text-sm text-gray-400 text-center py-12">Sem dados para o período.</p>
                    )}
                </div>
            </div>
        </div>
    );
}

export default function Index({
    tickets, filters, statusOptions, priorityOptions, sourceOptions = {}, departments, stores,
    activeTab = 'tickets', canViewReports = false, reports = null,
}) {
    const { hasPermission } = usePermissions();
    const { modals, selected, openModal, closeModal } = useModalManager(['create', 'detail', 'assign', 'priority']);
    const canCreate = hasPermission(PERMISSIONS.CREATE_TICKETS);
    const canManage = hasPermission(PERMISSIONS.MANAGE_TICKETS);

    const [stats, setStats] = useState(null);
    const [statsLoading, setStatsLoading] = useState(true);
    const [showFilters, setShowFilters] = useState(false);
    const [showExportMenu, setShowExportMenu] = useState(false);
    const [selectedIds, setSelectedIds] = useState([]);
    const [bulkProcessing, setBulkProcessing] = useState(false);
    const [localFilters, setLocalFilters] = useState({
        search: filters?.search || '', status: filters?.status || '',
        priority: filters?.priority || '', department_id: filters?.department_id || '',
        source: filters?.source || '',
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
    const [technicians, setTechnicians] = useState([]);
    const [selectedTechnicianId, setSelectedTechnicianId] = useState('');
    const [selectedPriority, setSelectedPriority] = useState(2);
    const [actionProcessing, setActionProcessing] = useState(false);
    const [actionError, setActionError] = useState('');

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

    const buildFilterParams = (extra = {}) => {
        const params = Object.fromEntries(Object.entries(localFilters).filter(([, v]) => v));
        return { ...params, ...extra };
    };

    const handleFilter = () => {
        const params = buildFilterParams(activeTab === 'reports' ? { tab: 'reports' } : {});
        router.get(route('helpdesk.index'), params, { preserveState: true });
    };

    const handleTabChange = (tab) => {
        if (tab === activeTab) return;
        const params = buildFilterParams(tab === 'reports' ? { tab: 'reports' } : {});
        router.get(route('helpdesk.index'), params, { preserveState: true, preserveScroll: true });
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

    const openAssignModal = () => {
        if (!detailData?.ticket?.department_id) return;
        setActionError('');
        setSelectedTechnicianId(detailData.ticket.technician_id || '');
        fetch(route('helpdesk.technicians', detailData.ticket.department_id), { headers: { 'Accept': 'application/json' } })
            .then(res => res.json())
            .then(data => setTechnicians(data))
            .catch(() => setTechnicians([]));
        openModal('assign', selected);
    };

    const openPriorityModal = () => {
        setActionError('');
        setSelectedPriority(detailData?.ticket?.priority || 2);
        openModal('priority', selected);
    };

    const handleAssignSubmit = async () => {
        if (!selectedTechnicianId || !selected) return;
        setActionProcessing(true);
        setActionError('');
        try {
            const res = await fetch(route('helpdesk.assign', selected.id), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body: JSON.stringify({ technician_id: Number(selectedTechnicianId) }),
            });
            if (!res.ok) {
                const body = await res.json().catch(() => ({}));
                throw new Error(body.message || body.error || 'Falha ao atribuir técnico.');
            }
            closeModal('assign');
            loadDetail(selected.id);
        } catch (err) {
            setActionError(err.message);
        } finally {
            setActionProcessing(false);
        }
    };

    const handlePrioritySubmit = async () => {
        if (!selected) return;
        setActionProcessing(true);
        setActionError('');
        try {
            const res = await fetch(route('helpdesk.change-priority', selected.id), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body: JSON.stringify({ priority: Number(selectedPriority) }),
            });
            if (!res.ok) {
                const body = await res.json().catch(() => ({}));
                throw new Error(body.message || body.error || 'Falha ao alterar prioridade.');
            }
            closeModal('priority');
            loadDetail(selected.id);
        } catch (err) {
            setActionError(err.message);
        } finally {
            setActionProcessing(false);
        }
    };

    const handleBulkAction = async (action, extraData = {}) => {
        if (selectedIds.length === 0) return;
        const confirmMsg = {
            delete: `Excluir ${selectedIds.length} chamado(s)?`,
            status: `Alterar status de ${selectedIds.length} chamado(s) para "${extraData.status}"?`,
            assign: `Atribuir ${selectedIds.length} chamado(s)?`,
        }[action];
        if (!confirm(confirmMsg)) return;

        setBulkProcessing(true);
        try {
            const res = await fetch(route('helpdesk.bulk'), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
                body: JSON.stringify({ action, ticket_ids: selectedIds, ...extraData }),
            });
            const body = await res.json();
            alert(body.message + (body.errors?.length ? `\n\nErros:\n${body.errors.join('\n')}` : ''));
            setSelectedIds([]);
            router.reload({ only: ['tickets'] });
        } catch (err) {
            alert('Falha: ' + err.message);
        } finally {
            setBulkProcessing(false);
        }
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
        { field: 'id', label: '#', sortable: true },
        {
            field: 'title',
            label: 'Título',
            sortable: true,
            render: (row) => (
                <div className="flex items-center gap-2">
                    <SourceBadge source={row.source} label={row.source_label} />
                    <span className="truncate">{row.title}</span>
                </div>
            ),
        },
        { field: 'requester_name', label: 'Solicitante' },
        { field: 'department_name', label: 'Departamento' },
        { field: 'priority_label', label: 'Prioridade', sortable: true, render: (row) => <StatusBadge variant={row.priority_color}>{row.priority_label}</StatusBadge> },
        { field: 'status_label', label: 'Status', sortable: true, render: (row) => (
            <div className="flex items-center gap-1">
                <StatusBadge variant={row.status_color}>{row.status_label}</StatusBadge>
                {row.is_overdue && <ExclamationTriangleIcon className="w-4 h-4 text-red-500" title="SLA vencido" />}
            </div>
        )},
        { field: 'technician_name', label: 'Técnico' },
        { field: 'created_at', label: 'Criado', sortable: true },
    ];

    return (
        <>
            <Head title="Helpdesk" />
            <div className="py-6 sm:py-12">
                <div className="max-w-full mx-auto px-3 sm:px-6 lg:px-8">
                    {/* Header — stacks on mobile, inline on sm+ */}
                    <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4 sm:mb-6">
                        <div>
                            <h1 className="text-xl sm:text-2xl font-bold text-gray-900">Helpdesk</h1>
                            <p className="text-xs sm:text-sm text-gray-500">Gerenciamento de chamados</p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            {activeTab === 'tickets' && (
                                <div className="relative inline-block">
                                    <Button variant="outline" icon={ArrowDownTrayIcon} onClick={() => setShowExportMenu(v => !v)}>
                                        Exportar
                                    </Button>
                                    {showExportMenu && (
                                        <div className="absolute right-0 mt-1 w-40 bg-white shadow-lg rounded-md border z-10">
                                            {['csv', 'xlsx', 'pdf'].map(fmt => (
                                                <a key={fmt}
                                                    href={`${route('helpdesk.export.' + fmt)}?${new URLSearchParams(Object.fromEntries(Object.entries(localFilters).filter(([, v]) => v))).toString()}`}
                                                    className="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                                    onClick={() => setShowExportMenu(false)}>
                                                    {fmt.toUpperCase()}
                                                </a>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            )}
                            {canCreate && (
                                <Button variant="primary" icon={PlusIcon} onClick={() => openModal('create')}>
                                    <span className="hidden sm:inline">Novo Chamado</span>
                                    <span className="sm:hidden">Novo</span>
                                </Button>
                            )}
                        </div>
                    </div>

                    {/* Tabs — horizontal scroll on very small widths.
                        overflow-y must be explicit or the -mb-px below leaks into a
                        vertical scrollbar (CSS spec: non-visible overflow-x promotes
                        overflow-y to auto). */}
                    <div className="border-b border-gray-200 mb-4 sm:mb-6 -mx-3 px-3 sm:mx-0 sm:px-0 overflow-x-auto overflow-y-hidden">
                        <nav className="-mb-px flex gap-4 sm:gap-6 whitespace-nowrap" aria-label="Tabs">
                            <button
                                onClick={() => handleTabChange('tickets')}
                                className={`flex items-center gap-2 py-3 px-1 border-b-2 text-sm font-medium transition-colors ${
                                    activeTab === 'tickets'
                                        ? 'border-indigo-600 text-indigo-600'
                                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                }`}
                            >
                                <ListBulletIcon className="w-5 h-5" />
                                Chamados
                            </button>
                            {canViewReports && (
                                <button
                                    onClick={() => handleTabChange('reports')}
                                    className={`flex items-center gap-2 py-3 px-1 border-b-2 text-sm font-medium transition-colors ${
                                        activeTab === 'reports'
                                            ? 'border-indigo-600 text-indigo-600'
                                            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                                    }`}
                                >
                                    <ChartBarIcon className="w-5 h-5" />
                                    Relatórios
                                </button>
                            )}
                        </nav>
                    </div>

                    {activeTab === 'tickets' && (
                    <>
                    {/* Statistics */}
                    <StatisticsGrid cards={statisticsCards} loading={statsLoading} className="mb-4 sm:mb-6" />

                    {/* Filters — search field stacks above buttons on mobile */}
                    <div className="bg-white shadow-sm rounded-lg p-3 sm:p-4 mb-4 sm:mb-6">
                        <div className="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3">
                            <TextInput className="w-full sm:flex-1" placeholder="Buscar por título ou #ID..."
                                value={localFilters.search} onChange={e => setLocalFilters(p => ({ ...p, search: e.target.value }))}
                                onKeyDown={e => e.key === 'Enter' && handleFilter()} />
                            <div className="flex gap-2">
                                <Button variant="primary" onClick={handleFilter} className="flex-1 sm:flex-initial">Buscar</Button>
                                <Button variant="outline" icon={FunnelIcon} onClick={() => setShowFilters(!showFilters)} />
                            </div>
                        </div>
                        {showFilters && (
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mt-3 pt-3 border-t">
                                <select className="w-full border-gray-300 rounded-lg text-sm" value={localFilters.status}
                                    onChange={e => setLocalFilters(p => ({ ...p, status: e.target.value }))}>
                                    <option value="">Todos os status</option>
                                    {Object.entries(statusOptions).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                                </select>
                                <select className="w-full border-gray-300 rounded-lg text-sm" value={localFilters.priority}
                                    onChange={e => setLocalFilters(p => ({ ...p, priority: e.target.value }))}>
                                    <option value="">Todas as prioridades</option>
                                    {Object.entries(priorityOptions).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                                </select>
                                <select className="w-full border-gray-300 rounded-lg text-sm" value={localFilters.department_id}
                                    onChange={e => setLocalFilters(p => ({ ...p, department_id: e.target.value }))}>
                                    <option value="">Todos os departamentos</option>
                                    {departments.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
                                </select>
                                <select className="w-full border-gray-300 rounded-lg text-sm" value={localFilters.source}
                                    onChange={e => setLocalFilters(p => ({ ...p, source: e.target.value }))}>
                                    <option value="">Todos os canais</option>
                                    {Object.entries(sourceOptions).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                                </select>
                                <div className="flex gap-2 lg:col-span-4">
                                    <Button variant="primary" size="sm" className="flex-1 sm:flex-initial" onClick={handleFilter}>Filtrar</Button>
                                    <Button variant="light" size="sm" onClick={() => { setLocalFilters({ search: '', status: '', priority: '', department_id: '', source: '', date_from: '', date_to: '' }); router.get(route('helpdesk.index')); }}>Limpar</Button>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Bulk action bar — stacks on mobile */}
                    {canManage && selectedIds.length > 0 && (
                        <div className="bg-indigo-50 border border-indigo-200 rounded-lg p-3 mb-3 flex flex-col sm:flex-row sm:items-center gap-2">
                            <span className="text-sm font-medium text-indigo-900">
                                {selectedIds.length} selecionado(s)
                            </span>
                            <div className="hidden sm:block flex-1" />
                            <div className="flex flex-wrap gap-2 items-center">
                                <select className="text-xs border-indigo-300 rounded flex-1 sm:flex-initial min-w-0"
                                    disabled={bulkProcessing}
                                    onChange={e => {
                                        if (e.target.value) {
                                            handleBulkAction('status', { status: e.target.value });
                                            e.target.value = '';
                                        }
                                    }}>
                                    <option value="">Alterar status para...</option>
                                    {Object.entries(statusOptions).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                                </select>
                                <Button variant="danger" size="sm" disabled={bulkProcessing}
                                    onClick={() => handleBulkAction('delete')}>
                                    Excluir
                                </Button>
                                <Button variant="light" size="sm" onClick={() => setSelectedIds([])}>
                                    Limpar
                                </Button>
                            </div>
                        </div>
                    )}

                    {/* Table — horizontal scroll wrapper for mobile */}
                    <div className="overflow-x-auto -mx-3 sm:mx-0">
                        <div className="min-w-full inline-block align-middle">
                            <DataTable data={tickets} columns={columns}
                                selectable={canManage}
                                selectedIds={selectedIds}
                                onSelectionChange={setSelectedIds}
                                onView={(row) => { openModal('detail', row); loadDetail(row.id); }}
                                onDelete={canManage ? (row) => { if (confirm('Excluir chamado #' + row.id + '?')) router.delete(route('helpdesk.destroy', row.id)); } : undefined}
                            />
                        </div>
                    </div>
                    </>
                    )}

                    {activeTab === 'reports' && canViewReports && (
                        <ReportsPanel
                            reports={reports}
                            departments={departments}
                            filters={localFilters}
                            onFilterChange={setLocalFilters}
                            onApplyFilter={handleFilter}
                        />
                    )}
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
                                <Button key="assign" variant="info" size="sm" onClick={openAssignModal}>
                                    Atribuir Técnico
                                </Button>,
                                <Button key="priority" variant="warning" size="sm" onClick={openPriorityModal}>
                                    Mudar Prioridade
                                </Button>,
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
                                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
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
                                <div className="mt-3 p-3 bg-gray-50 rounded-lg text-sm text-gray-700 whitespace-pre-wrap break-words">
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
                                            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 mb-1">
                                                <span className="font-medium text-gray-900 break-words">
                                                    {interaction.user_name}
                                                    {interaction.is_internal && <StatusBadge variant="warning" className="ml-1">Nota Interna</StatusBadge>}
                                                </span>
                                                <span className="text-xs text-gray-400 shrink-0">{interaction.created_at}</span>
                                            </div>
                                            {interaction.type === 'comment' && <p className="text-gray-700 whitespace-pre-wrap break-words">{interaction.comment}</p>}
                                            {interaction.type === 'status_change' && (
                                                <p className="text-gray-500 italic">
                                                    Status: <StatusBadge variant="gray">{interaction.old_value || 'Novo'}</StatusBadge>
                                                    {' → '}<StatusBadge variant="info">{interaction.new_value}</StatusBadge>
                                                    {interaction.comment && <span className="block mt-1 text-gray-600 break-words">{interaction.comment}</span>}
                                                </p>
                                            )}
                                            {interaction.type === 'assignment' && (
                                                <p className="text-gray-500 italic break-words">{interaction.comment}</p>
                                            )}
                                            {interaction.type === 'priority_change' && (
                                                <p className="text-gray-500 italic">
                                                    Prioridade: {interaction.old_value} → {interaction.new_value}
                                                </p>
                                            )}
                                            {interaction.attachments?.length > 0 && (
                                                <div className="mt-1 flex flex-wrap gap-2">
                                                    {interaction.attachments.map(a => (
                                                        <a key={a.id} href={a.url} target="_blank" rel="noopener" className="text-xs text-indigo-600 underline break-all">{a.name} ({a.size})</a>
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
                                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                            <label className="flex items-center gap-2 text-xs text-gray-500 cursor-pointer">
                                                <input type="checkbox" className="rounded border-gray-300 text-indigo-600"
                                                    checked={commentInternal} onChange={e => setCommentInternal(e.target.checked)} />
                                                Nota interna (não visível ao solicitante)
                                            </label>
                                            <Button variant="primary" size="sm" onClick={handleAddComment} disabled={!commentText.trim()} className="w-full sm:w-auto">
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

            {/* Assign Technician Modal */}
            <StandardModal show={modals.assign} onClose={() => closeModal('assign')}
                title="Atribuir Técnico" headerColor="bg-sky-600" headerIcon={<LifebuoyIcon className="h-5 w-5" />}
                maxWidth="md" errorMessage={actionError}
                footer={<StandardModal.Footer onCancel={() => closeModal('assign')}
                    onSubmit={handleAssignSubmit} submitLabel="Atribuir"
                    processing={actionProcessing} />}>
                <StandardModal.Section title="Selecionar Técnico">
                    <div>
                        <InputLabel value="Técnico *" />
                        <select className="w-full mt-1 border-gray-300 rounded-lg text-sm"
                            value={selectedTechnicianId}
                            onChange={e => setSelectedTechnicianId(e.target.value)}>
                            <option value="">Selecione...</option>
                            {technicians.map(t => (
                                <option key={t.id} value={t.id}>
                                    {t.name} ({t.level === 'manager' ? 'Gerente' : 'Técnico'})
                                </option>
                            ))}
                        </select>
                        {technicians.length === 0 && (
                            <p className="mt-2 text-xs text-gray-500">
                                Nenhum técnico cadastrado neste departamento.
                            </p>
                        )}
                    </div>
                </StandardModal.Section>
            </StandardModal>

            {/* Change Priority Modal */}
            <StandardModal show={modals.priority} onClose={() => closeModal('priority')}
                title="Alterar Prioridade" headerColor="bg-amber-600" headerIcon={<ExclamationTriangleIcon className="h-5 w-5" />}
                maxWidth="md" errorMessage={actionError}
                footer={<StandardModal.Footer onCancel={() => closeModal('priority')}
                    onSubmit={handlePrioritySubmit} submitLabel="Alterar"
                    processing={actionProcessing} />}>
                <StandardModal.Section title="Nova Prioridade">
                    <div>
                        <InputLabel value="Prioridade *" />
                        <select className="w-full mt-1 border-gray-300 rounded-lg text-sm"
                            value={selectedPriority}
                            onChange={e => setSelectedPriority(parseInt(e.target.value))}>
                            {Object.entries(priorityOptions).map(([k, v]) => (
                                <option key={k} value={k}>{v}</option>
                            ))}
                        </select>
                        <p className="mt-2 text-xs text-gray-500">
                            A mudança de prioridade recalcula o prazo de SLA do chamado.
                        </p>
                    </div>
                </StandardModal.Section>
            </StandardModal>
        </>
    );
}
