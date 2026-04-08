import { Head, useForm, router } from '@inertiajs/react';
import CentralLayout from '@/Layouts/CentralLayout';
import { useState } from 'react';
import {
    PlusIcon,
    PencilIcon,
    TrashIcon,
    CheckCircleIcon,
    XCircleIcon,
} from '@heroicons/react/24/outline';
import * as OutlineIcons from '@heroicons/react/24/outline';

// Curated icon set for module selection — grouped by category
const ICON_OPTIONS = [
    // Navigation & Layout
    'HomeIcon', 'HomeModernIcon', 'Bars3Icon', 'Squares2X2Icon', 'RectangleGroupIcon', 'ViewColumnsIcon', 'TableCellsIcon',
    // Users & People
    'UserIcon', 'UserGroupIcon', 'UsersIcon', 'UserPlusIcon', 'UserCircleIcon', 'IdentificationIcon',
    // Commerce & Finance
    'CurrencyDollarIcon', 'BanknotesIcon', 'CreditCardIcon', 'ShoppingCartIcon', 'ShoppingBagIcon', 'ReceiptPercentIcon', 'WalletIcon',
    // Products & Inventory
    'CubeIcon', 'CubeTransparentIcon', 'ArchiveBoxIcon', 'TagIcon', 'GiftIcon',
    // Buildings & Stores
    'BuildingStorefrontIcon', 'BuildingOfficeIcon', 'BuildingOffice2Icon', 'BuildingLibraryIcon',
    // Documents & Files
    'DocumentIcon', 'DocumentTextIcon', 'DocumentChartBarIcon', 'DocumentCheckIcon', 'ClipboardDocumentListIcon', 'ClipboardDocumentCheckIcon', 'ClipboardIcon', 'NewspaperIcon',
    // Calendar & Time
    'CalendarIcon', 'CalendarDaysIcon', 'ClockIcon',
    // Charts & Analytics
    'ChartBarIcon', 'ChartPieIcon', 'ChartBarSquareIcon', 'PresentationChartLineIcon', 'PresentationChartBarIcon', 'ArrowTrendingUpIcon',
    // Transport & Logistics
    'TruckIcon', 'ArrowsRightLeftIcon', 'ArrowPathIcon',
    // Settings & Tools
    'CogIcon', 'Cog6ToothIcon', 'WrenchIcon', 'WrenchScrewdriverIcon', 'AdjustmentsHorizontalIcon',
    // Security & Access
    'ShieldCheckIcon', 'ShieldExclamationIcon', 'LockClosedIcon', 'KeyIcon', 'FingerPrintIcon',
    // Communication
    'ChatBubbleLeftRightIcon', 'EnvelopeIcon', 'MegaphoneIcon', 'BellIcon', 'PhoneIcon',
    // Status & Indicators
    'SignalIcon', 'CheckBadgeIcon', 'ExclamationTriangleIcon', 'InformationCircleIcon', 'FlagIcon',
    // Visual & Design
    'SwatchIcon', 'PaintBrushIcon', 'SparklesIcon', 'EyeIcon', 'PhotoIcon',
    // Connections & Integration
    'LinkIcon', 'GlobeAltIcon', 'CloudArrowUpIcon', 'ServerIcon', 'CircleStackIcon', 'CommandLineIcon',
    // Misc
    'LightBulbIcon', 'BeakerIcon', 'AcademicCapIcon', 'BookOpenIcon', 'PuzzlePieceIcon', 'RocketLaunchIcon', 'FireIcon', 'BoltIcon', 'StarIcon', 'HeartIcon', 'MapPinIcon', 'ScaleIcon',
];

function IconComponent({ name, className }) {
    const Icon = OutlineIcons[name];
    if (!Icon) return null;
    return <Icon className={className} />;
}

export default function Index({ modules }) {
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [editingModule, setEditingModule] = useState(null);

    const activeCount = modules.filter(m => m.is_active).length;

    return (
        <CentralLayout title="Módulos">
            <Head title="Módulos - Mercury SaaS" />

            <div className="flex justify-between items-center mb-6">
                <p className="text-sm text-gray-500">
                    {modules.length} módulos ({activeCount} ativos)
                </p>
                <button
                    onClick={() => setShowCreateModal(true)}
                    className="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                >
                    <PlusIcon className="h-4 w-4" />
                    Novo Módulo
                </button>
            </div>

            <div className="bg-white shadow rounded-lg overflow-hidden">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Módulo</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Slug</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rotas</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Planos</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                        {modules.map((mod) => (
                            <tr key={mod.id} className={!mod.is_active ? 'opacity-50 bg-gray-50' : ''}>
                                <td className="px-6 py-4">
                                    <div className="flex items-center gap-3">
                                        {mod.icon && (
                                            <span className="flex-shrink-0 text-gray-400">
                                                <IconComponent name={mod.icon} className="h-5 w-5" />
                                            </span>
                                        )}
                                        <div>
                                            <div className="text-sm font-medium text-gray-900">{mod.name}</div>
                                            {mod.description && (
                                                <div className="text-xs text-gray-500 truncate max-w-xs">{mod.description}</div>
                                            )}
                                        </div>
                                    </div>
                                </td>
                                <td className="px-6 py-4">
                                    <span className="inline-flex items-center rounded bg-gray-100 px-2 py-0.5 text-xs font-mono text-gray-700">
                                        {mod.slug}
                                    </span>
                                </td>
                                <td className="px-6 py-4">
                                    <div className="flex flex-wrap gap-1">
                                        {mod.routes?.map((route, i) => (
                                            <span key={i} className="inline-block rounded bg-indigo-50 px-1.5 py-0.5 text-xs text-indigo-700">
                                                {route}
                                            </span>
                                        ))}
                                    </div>
                                </td>
                                <td className="px-6 py-4 text-sm text-gray-500">{mod.plans_count}</td>
                                <td className="px-6 py-4">
                                    {mod.is_active ? (
                                        <span className="inline-flex items-center gap-1 text-xs text-green-700">
                                            <CheckCircleIcon className="h-4 w-4" /> Ativo
                                        </span>
                                    ) : (
                                        <span className="inline-flex items-center gap-1 text-xs text-red-500">
                                            <XCircleIcon className="h-4 w-4" /> Inativo
                                        </span>
                                    )}
                                </td>
                                <td className="px-6 py-4 text-right">
                                    <div className="flex justify-end gap-1">
                                        <button
                                            onClick={() => setEditingModule(mod)}
                                            title="Editar"
                                            className="inline-flex items-center justify-center w-8 h-8 rounded-md bg-amber-100 text-amber-700 hover:bg-amber-200 transition-colors"
                                        >
                                            <PencilIcon className="h-4 w-4" />
                                        </button>
                                        {mod.plans_count === 0 && (
                                            <button
                                                onClick={() => {
                                                    if (confirm(`Excluir módulo "${mod.name}"?`)) {
                                                        router.delete(`/admin/modules/${mod.id}`);
                                                    }
                                                }}
                                                title="Excluir"
                                                className="inline-flex items-center justify-center w-8 h-8 rounded-md bg-red-100 text-red-700 hover:bg-red-200 transition-colors"
                                            >
                                                <TrashIcon className="h-4 w-4" />
                                            </button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {modules.length === 0 && (
                            <tr>
                                <td colSpan={6} className="px-6 py-8 text-center text-sm text-gray-500">
                                    Nenhum módulo cadastrado.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {showCreateModal && (
                <ModuleFormModal onClose={() => setShowCreateModal(false)} />
            )}

            {editingModule && (
                <ModuleFormModal module={editingModule} onClose={() => setEditingModule(null)} />
            )}
        </CentralLayout>
    );
}

function ModuleFormModal({ module, onClose }) {
    const isEditing = !!module;

    const { data, setData, post, put, processing, errors } = useForm({
        name: module?.name || '',
        slug: module?.slug || '',
        description: module?.description || '',
        icon: module?.icon || '',
        routes: module?.routes || [],
        dependencies: module?.dependencies || [],
        is_active: module?.is_active ?? true,
    });

    const [routeInput, setRouteInput] = useState('');
    const [depInput, setDepInput] = useState('');
    const [showIconPicker, setShowIconPicker] = useState(false);
    const [iconSearch, setIconSearch] = useState('');

    const filteredIcons = iconSearch
        ? ICON_OPTIONS.filter(name => name.toLowerCase().includes(iconSearch.toLowerCase()))
        : ICON_OPTIONS;

    const addRoute = () => {
        const value = routeInput.trim();
        if (value && !data.routes.includes(value)) {
            setData('routes', [...data.routes, value]);
            setRouteInput('');
        }
    };

    const removeRoute = (route) => {
        setData('routes', data.routes.filter(r => r !== route));
    };

    const addDep = () => {
        const value = depInput.trim();
        if (value && !data.dependencies.includes(value)) {
            setData('dependencies', [...data.dependencies, value]);
            setDepInput('');
        }
    };

    const removeDep = (dep) => {
        setData('dependencies', data.dependencies.filter(d => d !== dep));
    };

    const submit = (e) => {
        e.preventDefault();
        if (isEditing) {
            put(`/admin/modules/${module.id}`, { onSuccess: onClose });
        } else {
            post('/admin/modules', { onSuccess: onClose });
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center">
            <div className="fixed inset-0 bg-gray-600/75" onClick={onClose} />
            <div className="relative bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div className="px-6 py-4 border-b">
                    <h3 className="text-lg font-semibold text-gray-900">
                        {isEditing ? `Editar: ${module.name}` : 'Novo Módulo'}
                    </h3>
                    <p className="mt-1 text-sm text-gray-500">
                        {isEditing
                            ? 'Altere as configurações do módulo. O slug não pode ser alterado.'
                            : 'Cadastre um novo módulo para a plataforma. Após criado, ele poderá ser atribuído aos planos.'}
                    </p>
                </div>

                <form onSubmit={submit} className="p-6 space-y-5">
                    {/* Name & Slug */}
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Nome *</label>
                            <p className="text-xs text-gray-400 mb-1">Nome de exibição do módulo no sistema.</p>
                            <input
                                type="text"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className="block w-full rounded-md border-gray-300 shadow-sm text-sm"
                                placeholder="Ex: Vendas"
                                required
                            />
                            {errors.name && <p className="mt-1 text-xs text-red-600">{errors.name}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700">Slug *</label>
                            <p className="text-xs text-gray-400 mb-1">Identificador unico (sem espaços, minúsculo).</p>
                            <input
                                type="text"
                                value={data.slug}
                                onChange={(e) => setData('slug', e.target.value.toLowerCase().replace(/[^a-z0-9_-]/g, ''))}
                                className="block w-full rounded-md border-gray-300 shadow-sm text-sm font-mono"
                                placeholder="Ex: sales"
                                required
                                disabled={isEditing}
                            />
                            {errors.slug && <p className="mt-1 text-xs text-red-600">{errors.slug}</p>}
                        </div>
                    </div>

                    {/* Description */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700">Descricao</label>
                        <p className="text-xs text-gray-400 mb-1">Explique brevemente o que este módulo faz.</p>
                        <textarea
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            className="block w-full rounded-md border-gray-300 shadow-sm text-sm"
                            placeholder="Ex: Registro e consulta de vendas por loja e período."
                            rows="2"
                        />
                    </div>

                    {/* Icon Picker */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700">Icone</label>
                        <p className="text-xs text-gray-400 mb-2">Icone exibido no menu lateral. Biblioteca: Heroicons (outline).</p>

                        <div className="flex items-center gap-3 mb-2">
                            {data.icon && (
                                <span className="flex items-center justify-center w-10 h-10 rounded-lg bg-indigo-50 border border-indigo-200">
                                    <IconComponent name={data.icon} className="h-6 w-6 text-indigo-600" />
                                </span>
                            )}
                            <button
                                type="button"
                                onClick={() => setShowIconPicker(!showIconPicker)}
                                className="px-3 py-2 text-sm font-medium text-indigo-600 border border-indigo-300 rounded-md hover:bg-indigo-50"
                            >
                                {data.icon ? `${data.icon}` : 'Selecionar icone'}
                            </button>
                            {data.icon && (
                                <button
                                    type="button"
                                    onClick={() => setData('icon', '')}
                                    className="text-xs text-gray-400 hover:text-red-500"
                                >
                                    Remover
                                </button>
                            )}
                        </div>

                        {showIconPicker && (
                            <div className="border rounded-lg p-3 bg-gray-50">
                                <input
                                    type="text"
                                    value={iconSearch}
                                    onChange={(e) => setIconSearch(e.target.value)}
                                    className="block w-full rounded-md border-gray-300 shadow-sm text-sm mb-3"
                                    placeholder="Buscar icone... (ex: chart, user, document)"
                                />
                                <div className="grid grid-cols-10 gap-1 max-h-48 overflow-y-auto">
                                    {filteredIcons.map((iconName) => (
                                        <button
                                            key={iconName}
                                            type="button"
                                            onClick={() => { setData('icon', iconName); setShowIconPicker(false); setIconSearch(''); }}
                                            title={iconName.replace('Icon', '')}
                                            className={`flex items-center justify-center w-9 h-9 rounded-md transition-colors ${
                                                data.icon === iconName
                                                    ? 'bg-indigo-100 ring-2 ring-indigo-500'
                                                    : 'hover:bg-white hover:shadow-sm'
                                            }`}
                                        >
                                            <IconComponent name={iconName} className="h-5 w-5 text-gray-600" />
                                        </button>
                                    ))}
                                    {filteredIcons.length === 0 && (
                                        <p className="col-span-10 text-xs text-gray-400 text-center py-2">
                                            Nenhum icone encontrado.
                                        </p>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Routes */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700">Rotas</label>
                        <p className="text-xs text-gray-400 mb-2">
                            Padrões de rota que este módulo controla. Use <code className="bg-gray-100 px-1 rounded">*</code> como curinga.
                            Ex: <code className="bg-gray-100 px-1 rounded">sales.*</code> cobre todas as rotas de vendas.
                        </p>
                        <div className="flex gap-2 mb-2">
                            <input
                                type="text"
                                value={routeInput}
                                onChange={(e) => setRouteInput(e.target.value)}
                                onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); addRoute(); } }}
                                className="block w-full rounded-md border-gray-300 shadow-sm text-sm font-mono"
                                placeholder="Digite e pressione Enter ou +"
                            />
                            <button
                                type="button"
                                onClick={addRoute}
                                className="px-3 py-2 text-sm font-medium text-indigo-600 border border-indigo-300 rounded-md hover:bg-indigo-50"
                            >
                                +
                            </button>
                        </div>
                        {data.routes.length > 0 ? (
                            <div className="flex flex-wrap gap-1">
                                {data.routes.map((route, i) => (
                                    <span
                                        key={i}
                                        className="inline-flex items-center gap-1 rounded bg-indigo-50 px-2 py-1 text-xs font-mono text-indigo-700"
                                    >
                                        {route}
                                        <button type="button" onClick={() => removeRoute(route)} className="text-indigo-400 hover:text-red-500 ml-0.5">
                                            &times;
                                        </button>
                                    </span>
                                ))}
                            </div>
                        ) : (
                            <p className="text-xs text-gray-400 italic">Nenhuma rota adicionada.</p>
                        )}
                    </div>

                    {/* Dependencies */}
                    <div>
                        <label className="block text-sm font-medium text-gray-700">Dependencias</label>
                        <p className="text-xs text-gray-400 mb-2">
                            Slugs de módulos que precisam estar ativos para este funcionar. Ex: o módulo
                            de <code className="bg-gray-100 px-1 rounded">transfers</code> pode depender
                            de <code className="bg-gray-100 px-1 rounded">stores</code>.
                        </p>
                        <div className="flex gap-2 mb-2">
                            <input
                                type="text"
                                value={depInput}
                                onChange={(e) => setDepInput(e.target.value)}
                                onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); addDep(); } }}
                                className="block w-full rounded-md border-gray-300 shadow-sm text-sm font-mono"
                                placeholder="Digite o slug e pressione Enter ou +"
                            />
                            <button
                                type="button"
                                onClick={addDep}
                                className="px-3 py-2 text-sm font-medium text-indigo-600 border border-indigo-300 rounded-md hover:bg-indigo-50"
                            >
                                +
                            </button>
                        </div>
                        {data.dependencies.length > 0 ? (
                            <div className="flex flex-wrap gap-1">
                                {data.dependencies.map((dep, i) => (
                                    <span
                                        key={i}
                                        className="inline-flex items-center gap-1 rounded bg-yellow-50 px-2 py-1 text-xs font-mono text-yellow-700"
                                    >
                                        {dep}
                                        <button type="button" onClick={() => removeDep(dep)} className="text-yellow-400 hover:text-red-500 ml-0.5">
                                            &times;
                                        </button>
                                    </span>
                                ))}
                            </div>
                        ) : (
                            <p className="text-xs text-gray-400 italic">Nenhuma dependência.</p>
                        )}
                    </div>

                    {/* Active toggle (edit only) */}
                    {isEditing && (
                        <div className="rounded-lg border border-gray-200 p-4 bg-gray-50">
                            <label className="flex items-center gap-3 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={data.is_active}
                                    onChange={(e) => setData('is_active', e.target.checked)}
                                    className="rounded border-gray-300 text-indigo-600 h-4 w-4"
                                />
                                <div>
                                    <span className="text-sm font-medium text-gray-700">Módulo ativo</span>
                                    <p className="text-xs text-gray-400">
                                        Módulos inativos não aparecem na lista de módulos disponíveis para planos e não podem ser acessados pelos tenants.
                                    </p>
                                </div>
                            </label>
                        </div>
                    )}

                    {/* Actions */}
                    <div className="flex justify-end gap-3 pt-4 border-t">
                        <button
                            type="button"
                            onClick={onClose}
                            className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50"
                        >
                            {processing ? 'Salvando...' : (isEditing ? 'Salvar' : 'Criar Módulo')}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
