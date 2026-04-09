import { Head, useForm, router } from '@inertiajs/react';
import CentralLayout from '@/Layouts/CentralLayout';
import { useState } from 'react';
import {
    PlusIcon,
    PencilIcon,
    TrashIcon,
    CheckCircleIcon,
    XCircleIcon,
    ChevronRightIcon,
    MagnifyingGlassIcon,
} from '@heroicons/react/24/outline';

const TABS = [
    { key: 'menus', label: 'Menus' },
    { key: 'pages', label: 'Páginas' },
    { key: 'groups', label: 'Grupos' },
    { key: 'defaults', label: 'Permissões Padrão' },
];

const MENU_TYPES = {
    main: 'Principal',
    hr: 'RH',
    utility: 'Utilitário',
    system: 'Sistema',
};

// Curated Font Awesome icons for navigation (grouped by category)
const FA_ICONS = [
    // Navigation
    'fas fa-home', 'fas fa-tachometer-alt', 'fas fa-bars', 'fas fa-th', 'fas fa-th-large', 'fas fa-columns', 'fas fa-compass',
    // Users & People
    'fas fa-user', 'fas fa-users', 'fas fa-user-plus', 'fas fa-user-cog', 'fas fa-users-cog', 'fas fa-id-badge', 'fas fa-id-card', 'fas fa-address-card', 'fas fa-user-tie', 'fas fa-user-shield', 'fas fa-user-times',
    // Commerce & Finance
    'fas fa-dollar-sign', 'fas fa-money-bill-wave', 'fas fa-credit-card', 'fas fa-shopping-cart', 'fas fa-shopping-bag', 'fas fa-cash-register', 'fas fa-receipt', 'fas fa-wallet', 'fas fa-coins', 'fas fa-chart-line', 'fas fa-chart-bar', 'fas fa-chart-pie', 'fas fa-chart-area',
    // Products & Inventory
    'fas fa-box', 'fas fa-boxes', 'fas fa-cube', 'fas fa-cubes', 'fas fa-tag', 'fas fa-tags', 'fas fa-barcode', 'fas fa-qrcode',
    // Buildings & Stores
    'fas fa-store', 'fas fa-building', 'fas fa-warehouse', 'fas fa-industry', 'fas fa-landmark',
    // Documents & Files
    'fas fa-file', 'fas fa-file-alt', 'fas fa-file-medical', 'fas fa-file-invoice', 'fas fa-file-signature', 'fas fa-clipboard', 'fas fa-clipboard-check', 'fas fa-clipboard-list', 'fas fa-newspaper', 'fas fa-book', 'fas fa-book-open',
    // Calendar & Time
    'fas fa-calendar', 'fas fa-calendar-alt', 'fas fa-calendar-check', 'fas fa-calendar-day', 'fas fa-clock', 'fas fa-hourglass', 'fas fa-business-time', 'fas fa-stopwatch',
    // Transport & Logistics
    'fas fa-truck', 'fas fa-shipping-fast', 'fas fa-exchange-alt', 'fas fa-route', 'fas fa-map-location-dot', 'fas fa-map-marked-alt',
    // Settings & Tools
    'fas fa-cog', 'fas fa-cogs', 'fas fa-wrench', 'fas fa-tools', 'fas fa-sliders-h', 'fas fa-hammer',
    // Security & Access
    'fas fa-shield-alt', 'fas fa-lock', 'fas fa-key', 'fas fa-fingerprint', 'fas fa-user-lock',
    // Communication
    'fas fa-envelope', 'fas fa-envelope-open-text', 'fas fa-comment', 'fas fa-comments', 'fas fa-headset', 'fas fa-phone', 'fas fa-bell',
    // Status & Indicators
    'fas fa-check', 'fas fa-check-circle', 'fas fa-times-circle', 'fas fa-exclamation-triangle', 'fas fa-info-circle', 'fas fa-flag', 'fas fa-star', 'fas fa-bullseye',
    // Visual & Design
    'fas fa-palette', 'fas fa-paint-brush', 'fas fa-eye', 'fas fa-image',
    // Connections & Tech
    'fas fa-link', 'fas fa-globe', 'fas fa-wifi', 'fas fa-signal', 'fas fa-server', 'fas fa-database', 'fas fa-code',
    // Misc
    'fas fa-lightbulb', 'fas fa-graduation-cap', 'fas fa-video', 'fas fa-tasks', 'fas fa-history', 'fas fa-arrows-alt', 'fas fa-sign-out-alt', 'fas fa-question-circle', 'fas fa-rocket', 'fas fa-fire', 'fas fa-bolt', 'fas fa-heart', 'fas fa-map-pin', 'fas fa-balance-scale', 'fas fa-diagram-project',
];

export default function Index({ menus, pages, pageGroups, defaults, allRoles, allModules, tab }) {
    const [activeTab, setActiveTab] = useState(tab || 'menus');

    return (
        <CentralLayout title="Navegação">
            <Head title="Navegação - Mercury SaaS" />

            {/* Tabs */}
            <div className="border-b border-gray-200 mb-6">
                <nav className="-mb-px flex gap-6">
                    {TABS.map((t) => (
                        <button
                            key={t.key}
                            onClick={() => setActiveTab(t.key)}
                            className={`pb-3 px-1 text-sm font-medium border-b-2 transition-colors ${
                                activeTab === t.key
                                    ? 'border-indigo-500 text-indigo-600'
                                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
                            }`}
                        >
                            {t.label}
                        </button>
                    ))}
                </nav>
            </div>

            {activeTab === 'menus' && <MenusTab menus={menus} />}
            {activeTab === 'pages' && <PagesTab pages={pages} pageGroups={pageGroups} allModules={allModules} />}
            {activeTab === 'groups' && <GroupsTab pageGroups={pageGroups} />}
            {activeTab === 'defaults' && <DefaultsTab menus={menus} pages={pages} defaults={defaults} allRoles={allRoles} />}
        </CentralLayout>
    );
}

// =================== FA ICON PICKER ===================

function FaIconPicker({ value, onChange }) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');

    const filtered = search
        ? FA_ICONS.filter(icon => icon.toLowerCase().includes(search.toLowerCase()))
        : FA_ICONS;

    return (
        <div>
            <div className="flex items-center gap-3 mb-2">
                {value && (
                    <span className="flex items-center justify-center w-10 h-10 rounded-lg bg-indigo-50 border border-indigo-200">
                        <i className={`${value} text-indigo-600`} />
                    </span>
                )}
                <button
                    type="button"
                    onClick={() => setOpen(!open)}
                    className="px-3 py-2 text-sm font-medium text-indigo-600 border border-indigo-300 rounded-md hover:bg-indigo-50"
                >
                    {value || 'Selecionar icone'}
                </button>
                {value && (
                    <button type="button" onClick={() => onChange('')} className="text-xs text-gray-400 hover:text-red-500">
                        Remover
                    </button>
                )}
            </div>

            {open && (
                <div className="border rounded-lg p-3 bg-gray-50">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="block w-full rounded-md border-gray-300 shadow-sm text-sm mb-3"
                        placeholder="Buscar icone... (ex: user, chart, file)"
                    />
                    <div className="grid grid-cols-10 gap-1 max-h-48 overflow-y-auto">
                        {filtered.map((iconClass) => (
                            <button
                                key={iconClass}
                                type="button"
                                onClick={() => { onChange(iconClass); setOpen(false); setSearch(''); }}
                                title={iconClass}
                                className={`flex items-center justify-center w-9 h-9 rounded-md transition-colors ${
                                    value === iconClass
                                        ? 'bg-indigo-100 ring-2 ring-indigo-500'
                                        : 'hover:bg-white hover:shadow-sm'
                                }`}
                            >
                                <i className={`${iconClass} text-gray-600 text-sm`} />
                            </button>
                        ))}
                        {filtered.length === 0 && (
                            <p className="col-span-10 text-xs text-gray-400 text-center py-2">Nenhum icone encontrado.</p>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

// =================== MENUS TAB ===================

function MenusTab({ menus }) {
    const [showCreate, setShowCreate] = useState(false);
    const [editing, setEditing] = useState(null);

    return (
        <div>
            <div className="flex justify-between items-center mb-4">
                <p className="text-sm text-gray-500">{menus.length} menus principais</p>
                <BtnCreate onClick={() => setShowCreate(true)}>Novo Menu</BtnCreate>
            </div>

            <div className="bg-white shadow rounded-lg divide-y">
                {menus.map((menu) => (
                    <div key={menu.id}>
                        <div className="px-6 py-4 flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                {menu.icon && (
                                    <span className="flex items-center justify-center w-8 h-8 rounded bg-gray-100 text-gray-500">
                                        <i className={`${menu.icon} text-sm`} />
                                    </span>
                                )}
                                <div>
                                    <div className="flex items-center gap-2">
                                        <span className="text-sm font-medium text-gray-900">{menu.name}</span>
                                        <span className={`inline-flex rounded px-1.5 py-0.5 text-xs font-medium ${
                                            menu.type === 'system' ? 'bg-red-50 text-red-700' :
                                            menu.type === 'hr' ? 'bg-blue-50 text-blue-700' :
                                            menu.type === 'utility' ? 'bg-yellow-50 text-yellow-700' :
                                            'bg-gray-50 text-gray-700'
                                        }`}>{MENU_TYPES[menu.type]}</span>
                                        {!menu.is_active && <span className="text-xs text-red-400 bg-red-50 px-1.5 py-0.5 rounded">inativo</span>}
                                    </div>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <span className="text-xs text-gray-400">{menu.pages_count} páginas</span>
                                <div className="flex gap-1">
                                    <BtnEdit onClick={() => setEditing(menu)} />
                                    {menu.pages_count === 0 && menu.children.length === 0 && (
                                        <BtnDelete onClick={() => { if (confirm(`Excluir menu "${menu.name}"?`)) router.delete(`/admin/navigation/menus/${menu.id}`); }} />
                                    )}
                                </div>
                            </div>
                        </div>
                        {menu.children.length > 0 && (
                            <div className="bg-gray-50 pl-16 pr-4 divide-y divide-gray-100">
                                {menu.children.map((child) => (
                                    <div key={child.id} className="py-3 flex items-center justify-between gap-3">
                                        <div className="flex items-center gap-2 min-w-0">
                                            <ChevronRightIcon className="h-3 w-3 text-gray-300 flex-shrink-0" />
                                            {child.icon && <i className={`${child.icon} text-xs text-gray-400 flex-shrink-0`} />}
                                            <span className="text-sm text-gray-700">{child.name}</span>
                                        </div>
                                        <div className="flex-shrink-0">
                                            <BtnEdit onClick={() => setEditing({...child, parent_id: menu.id})} />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                ))}
            </div>

            {showCreate && <MenuFormModal parentMenus={menus} onClose={() => setShowCreate(false)} />}
            {editing && <MenuFormModal menu={editing} parentMenus={menus} onClose={() => setEditing(null)} />}
        </div>
    );
}

function MenuFormModal({ menu, parentMenus, onClose }) {
    const isEditing = !!menu;
    const { data, setData, post, put, processing, errors } = useForm({
        name: menu?.name || '',
        icon: menu?.icon || '',
        type: menu?.type || 'main',
        parent_id: menu?.parent_id || '',
        is_active: menu?.is_active ?? true,
    });

    const submit = (e) => {
        e.preventDefault();
        const payload = { ...data, parent_id: data.parent_id || null };
        if (isEditing) {
            put(`/admin/navigation/menus/${menu.id}`, { data: payload, onSuccess: onClose });
        } else {
            post('/admin/navigation/menus', { data: payload, onSuccess: onClose });
        }
    };

    return (
        <Modal title={isEditing ? `Editar: ${menu.name}` : 'Novo Menu'} onClose={onClose}>
            <form onSubmit={submit} className="flex flex-col flex-1 min-h-0">
                <div className="p-6 space-y-4 overflow-y-auto flex-1">
                    <Field label="Nome *" help="Nome exibido no menu lateral do tenant.">
                        <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} className="block w-full rounded-md border-gray-300 shadow-sm text-sm" required />
                        {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
                    </Field>

                    <Field label="Ícone" help="Ícone Font Awesome exibido no menu lateral.">
                        <FaIconPicker value={data.icon} onChange={(v) => setData('icon', v)} />
                    </Field>

                    <div className="grid grid-cols-2 gap-4">
                        <Field label="Tipo" help="Categoria do menu para agrupamento.">
                            <select value={data.type} onChange={(e) => setData('type', e.target.value)} className="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                {Object.entries(MENU_TYPES).map(([v, l]) => <option key={v} value={v}>{l}</option>)}
                            </select>
                        </Field>
                        {!isEditing && (
                            <Field label="Menu Pai" help="Selecione para criar submenu.">
                                <select value={data.parent_id} onChange={(e) => setData('parent_id', e.target.value)} className="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="">Nenhum (menu principal)</option>
                                    {parentMenus.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
                                </select>
                            </Field>
                        )}
                    </div>

                    {isEditing && (
                        <label className="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="rounded border-gray-300 text-indigo-600" />
                            <span className="text-sm text-gray-700">Menu ativo</span>
                        </label>
                    )}
                </div>
                <ModalActions onClose={onClose} processing={processing} isEditing={isEditing} />
            </form>
        </Modal>
    );
}

// =================== PAGES TAB ===================

function PagesTab({ pages, pageGroups, allModules }) {
    const [showCreate, setShowCreate] = useState(false);
    const [editing, setEditing] = useState(null);
    const [search, setSearch] = useState('');

    const filtered = search
        ? pages.filter(p => p.page_name.toLowerCase().includes(search.toLowerCase()) || (p.route && p.route.toLowerCase().includes(search.toLowerCase())))
        : pages;

    return (
        <div>
            <div className="flex justify-between items-center mb-4 gap-4">
                <div className="relative w-72">
                    <MagnifyingGlassIcon className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                    <input type="text" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Buscar por nome ou rota..." className="pl-9 rounded-md border-gray-300 shadow-sm text-sm w-full" />
                </div>
                <BtnCreate onClick={() => setShowCreate(true)}>Nova Página</BtnCreate>
            </div>

            <div className="bg-white shadow rounded-lg overflow-hidden">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Página</th>
                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rota</th>
                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Módulo</th>
                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Grupo</th>
                            <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                        {filtered.map((page) => (
                            <tr key={page.id} className={!page.is_active ? 'opacity-50' : ''}>
                                <td className="px-4 py-3">
                                    <div className="flex items-center gap-2">
                                        {page.icon && <i className={`${page.icon} text-xs text-gray-400`} />}
                                        <span className="text-sm font-medium text-gray-900">{page.page_name}</span>
                                    </div>
                                </td>
                                <td className="px-4 py-3"><span className="text-xs font-mono text-gray-500 bg-gray-50 px-1.5 py-0.5 rounded">{page.route || '-'}</span></td>
                                <td className="px-4 py-3">{page.module ? <span className="text-xs bg-indigo-50 text-indigo-700 px-1.5 py-0.5 rounded">{page.module.name}</span> : <span className="text-xs text-gray-400">-</span>}</td>
                                <td className="px-4 py-3 text-xs text-gray-500">{page.page_group || '-'}</td>
                                <td className="px-4 py-3">
                                    <div className="flex gap-2">
                                        {page.is_active
                                            ? <span className="inline-flex items-center gap-1 text-xs text-green-700"><CheckCircleIcon className="h-3.5 w-3.5" /> Ativa</span>
                                            : <span className="inline-flex items-center gap-1 text-xs text-red-500"><XCircleIcon className="h-3.5 w-3.5" /> Inativa</span>
                                        }
                                        {page.is_public && <span className="text-xs bg-green-50 text-green-700 px-1.5 py-0.5 rounded">pública</span>}
                                    </div>
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <div className="flex justify-end gap-1">
                                        <BtnEdit onClick={() => setEditing(page)} />
                                        <BtnDelete onClick={() => { if (confirm(`Excluir "${page.page_name}"?`)) router.delete(`/admin/navigation/pages/${page.id}`); }} />
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {filtered.length === 0 && (
                            <tr><td colSpan={6} className="px-4 py-8 text-center text-sm text-gray-500">Nenhuma página encontrada.</td></tr>
                        )}
                    </tbody>
                </table>
            </div>

            {showCreate && <PageFormModal pageGroups={pageGroups} allModules={allModules} onClose={() => setShowCreate(false)} />}
            {editing && <PageFormModal page={editing} pageGroups={pageGroups} allModules={allModules} onClose={() => setEditing(null)} />}
        </div>
    );
}

function PageFormModal({ page, pageGroups, allModules, onClose }) {
    const isEditing = !!page;
    const { data, setData, post, put, processing, errors } = useForm({
        page_name: page?.page_name || '',
        route: page?.route || '',
        icon: page?.icon || '',
        notes: page?.notes || '',
        is_public: page?.is_public ?? false,
        is_active: page?.is_active ?? true,
        central_page_group_id: page?.page_group ? pageGroups.find(g => g.name === page.page_group)?.id || '' : '',
        central_module_id: page?.module?.id || '',
    });

    const submit = (e) => {
        e.preventDefault();
        const payload = {
            ...data,
            central_page_group_id: data.central_page_group_id || null,
            central_module_id: data.central_module_id || null,
        };
        if (isEditing) {
            put(`/admin/navigation/pages/${page.id}`, { data: payload, onSuccess: onClose });
        } else {
            post('/admin/navigation/pages', { data: payload, onSuccess: onClose });
        }
    };

    return (
        <Modal title={isEditing ? `Editar: ${page.page_name}` : 'Nova Página'} onClose={onClose}>
            <form onSubmit={submit} className="flex flex-col flex-1 min-h-0">
                <div className="p-6 space-y-4 overflow-y-auto flex-1">
                    <div className="grid grid-cols-2 gap-4">
                        <Field label="Nome *" help="Nome de exibição da página.">
                            <input type="text" value={data.page_name} onChange={(e) => setData('page_name', e.target.value)} className="block w-full rounded-md border-gray-300 shadow-sm text-sm" required />
                        </Field>
                        <Field label="Rota" help="Rota Laravel da página (ex: /sales).">
                            <input type="text" value={data.route} onChange={(e) => setData('route', e.target.value)} className="block w-full rounded-md border-gray-300 shadow-sm text-sm font-mono" placeholder="/exemplo" />
                        </Field>
                    </div>

                    <Field label="Ícone" help="Ícone Font Awesome exibido ao lado do nome da página.">
                        <FaIconPicker value={data.icon} onChange={(v) => setData('icon', v)} />
                    </Field>

                    <div className="grid grid-cols-2 gap-4">
                        <Field label="Módulo" help="Módulo ao qual esta página pertence. Páginas sem módulo aparecem para todos os planos.">
                            <select value={data.central_module_id} onChange={(e) => setData('central_module_id', e.target.value)} className="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="">Nenhum</option>
                                {allModules.map((m) => <option key={m.id} value={m.id}>{m.name}</option>)}
                            </select>
                        </Field>
                        <Field label="Grupo" help="Tipo de ação da página (Listar, Cadastrar, etc.).">
                            <select value={data.central_page_group_id} onChange={(e) => setData('central_page_group_id', e.target.value)} className="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="">Nenhum</option>
                                {pageGroups.map((g) => <option key={g.id} value={g.id}>{g.name}</option>)}
                            </select>
                        </Field>
                    </div>

                    <Field label="Notas" help="Observações internas sobre a página (não exibidas ao usuário).">
                        <textarea value={data.notes} onChange={(e) => setData('notes', e.target.value)} className="block w-full rounded-md border-gray-300 shadow-sm text-sm" rows="2" />
                    </Field>

                    <div className="flex gap-6">
                        <label className="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="rounded border-gray-300 text-indigo-600" />
                            <span className="text-sm text-gray-700">Ativa</span>
                        </label>
                        <label className="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" checked={data.is_public} onChange={(e) => setData('is_public', e.target.checked)} className="rounded border-gray-300 text-indigo-600" />
                            <span className="text-sm text-gray-700">Pública (acesso sem login)</span>
                        </label>
                    </div>
                </div>
                <ModalActions onClose={onClose} processing={processing} isEditing={isEditing} />
            </form>
        </Modal>
    );
}

// =================== GROUPS TAB ===================

function GroupsTab({ pageGroups }) {
    const { data, setData, post, processing, reset } = useForm({ name: '' });

    const submit = (e) => {
        e.preventDefault();
        post('/admin/navigation/page-groups', { onSuccess: () => reset() });
    };

    return (
        <div className="max-w-lg">
            <p className="text-sm text-gray-500 mb-4">
                Grupos categorizam as páginas por tipo de ação (Listar, Cadastrar, Editar, etc.).
            </p>
            <form onSubmit={submit} className="flex gap-2 mb-6">
                <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="Nome do novo grupo" className="block w-full rounded-md border-gray-300 shadow-sm text-sm" required />
                <button type="submit" disabled={processing} className="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50 whitespace-nowrap">
                    <PlusIcon className="h-4 w-4" /> Criar
                </button>
            </form>

            <div className="bg-white shadow rounded-lg divide-y">
                {pageGroups.map((group) => (
                    <div key={group.id} className="px-4 py-3 flex items-center justify-between">
                        <div>
                            <span className="text-sm font-medium text-gray-900">{group.name}</span>
                            <span className="ml-2 text-xs text-gray-400">{group.pages_count} páginas</span>
                        </div>
                        {group.pages_count === 0 && (
                            <BtnDelete onClick={() => { if (confirm(`Excluir "${group.name}"?`)) router.delete(`/admin/navigation/page-groups/${group.id}`); }} />
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
}

// =================== DEFAULTS TAB ===================

function DefaultsTab({ menus, pages, defaults, allRoles }) {
    const roleEntries = Object.entries(allRoles);

    const menuPages = menus.filter(m => {
        const menuDefaults = defaults[m.id];
        return menuDefaults && Object.keys(menuDefaults).length > 0;
    });

    return (
        <div>
            <p className="text-sm text-gray-500 mb-4">
                Matriz de permissões padrão: define quais páginas cada role pode acessar ao provisionar um novo tenant.
                Alterações aqui afetam apenas <strong>novos tenants</strong> — tenants existentes mantêm suas configurações.
            </p>

            <div className="space-y-6">
                {menuPages.map((menu) => {
                    const menuDefaults = defaults[menu.id] || {};
                    const pageIds = [...new Set(Object.values(menuDefaults).flat().map(d => d.central_page_id))];

                    return (
                        <div key={menu.id} className="bg-white shadow rounded-lg overflow-hidden">
                            <div className="px-4 py-3 bg-gray-50 border-b flex items-center gap-2">
                                {menu.icon && <i className={`${menu.icon} text-sm text-gray-400`} />}
                                <h4 className="text-sm font-semibold text-gray-900">{menu.name}</h4>
                                <span className="text-xs text-gray-400">({pageIds.length} páginas)</span>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="min-w-full">
                                    <thead>
                                        <tr className="border-b">
                                            <th className="px-4 py-2 text-left text-xs font-medium text-gray-500">Página</th>
                                            {roleEntries.map(([value, label]) => (
                                                <th key={value} className="px-3 py-2 text-center text-xs font-medium text-gray-500">{label}</th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {pageIds.map((pageId) => {
                                            const pageName = pages.find(p => p.id === pageId)?.page_name || `#${pageId}`;
                                            return (
                                                <tr key={pageId}>
                                                    <td className="px-4 py-2 text-sm text-gray-700">{pageName}</td>
                                                    {roleEntries.map(([roleSlug]) => {
                                                        const roleDefaults = menuDefaults[roleSlug] || [];
                                                        const hasAccess = roleDefaults.some(d => d.central_page_id === pageId && d.permission);
                                                        return (
                                                            <td key={roleSlug} className="px-3 py-2 text-center">
                                                                {hasAccess ? (
                                                                    <CheckCircleIcon className="h-4 w-4 text-green-500 mx-auto" />
                                                                ) : (
                                                                    <span className="text-gray-200">-</span>
                                                                )}
                                                            </td>
                                                        );
                                                    })}
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    );
                })}

                {menuPages.length === 0 && (
                    <div className="bg-white shadow rounded-lg p-8 text-center text-sm text-gray-500">
                        Nenhuma permissão padrão configurada. Execute o seeder CentralNavigationSeeder para popular os dados iniciais.
                    </div>
                )}
            </div>
        </div>
    );
}

// =================== SHARED COMPONENTS ===================

function Modal({ title, onClose, children }) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center">
            <div className="fixed inset-0 bg-gray-600/75" onClick={onClose} />
            <div className="relative bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] flex flex-col">
                <div className="px-6 py-4 border-b shrink-0">
                    <h3 className="text-lg font-semibold text-gray-900">{title}</h3>
                </div>
                <div className="flex-1 min-h-0 flex flex-col">{children}</div>
            </div>
        </div>
    );
}

function Field({ label, help, children }) {
    return (
        <div>
            <label className="block text-sm font-medium text-gray-700">{label}</label>
            {help && <p className="text-xs text-gray-400 mb-1">{help}</p>}
            {children}
        </div>
    );
}

function ModalActions({ onClose, processing, isEditing }) {
    return (
        <div className="flex justify-end gap-3 px-6 py-4 border-t bg-gray-50 rounded-b-lg shrink-0">
            <button type="button" onClick={onClose} className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                Cancelar
            </button>
            <button type="submit" disabled={processing} className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50">
                {processing ? 'Salvando...' : (isEditing ? 'Salvar' : 'Criar')}
            </button>
        </div>
    );
}

// Action Buttons with colored backgrounds
function BtnCreate({ onClick, children }) {
    return (
        <button onClick={onClick} className="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">
            <PlusIcon className="h-4 w-4" /> {children}
        </button>
    );
}

function BtnEdit({ onClick }) {
    return (
        <button onClick={onClick} title="Editar" className="inline-flex items-center justify-center w-8 h-8 rounded-md bg-amber-100 text-amber-700 hover:bg-amber-200 transition-colors">
            <PencilIcon className="h-4 w-4" />
        </button>
    );
}

function BtnDelete({ onClick }) {
    return (
        <button onClick={onClick} title="Excluir" className="inline-flex items-center justify-center w-8 h-8 rounded-md bg-red-100 text-red-700 hover:bg-red-200 transition-colors">
            <TrashIcon className="h-4 w-4" />
        </button>
    );
}
