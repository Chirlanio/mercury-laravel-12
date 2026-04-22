import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import {
    ExclamationTriangleIcon,
    DocumentTextIcon,
    TableCellsIcon,
} from '@heroicons/react/24/outline';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import KpiCards from '@/Components/DRE/KpiCards';
import MatrixTable from '@/Components/DRE/MatrixTable';
import DrillModal from '@/Components/DRE/DrillModal';
import ChartsPanel from '@/Components/DRE/ChartsPanel';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import { yearMonthsBetween } from '@/lib/dre';

/**
 * Tela principal da DRE gerencial.
 *
 * Substitui o stub do prompt 7. UI completa com filtros sincronizados via
 * URL, seletor de escopo (Geral/Rede/Loja), KPIs, matriz mensal com sticky
 * headers e drill modal.
 *
 * Convenções:
 *   - Filtros vivem na URL (preserveState + preserveScroll).
 *   - Drill em state local (useState), sem URL.
 *   - Acentuação completa PT-BR.
 *   - Nunca localStorage/sessionStorage.
 */
export default function Matrix({
    filters,
    matrix,
    kpis,
    availableStores,
    availableNetworks,
    availableBudgetVersions,
    closedPeriods,
}) {
    const [drillTarget, setDrillTarget] = useState(null);
    const { hasPermission } = usePermissions();
    const canExport = hasPermission(PERMISSIONS.EXPORT_DRE);

    const closedYearMonths = useMemo(() => {
        const activeClosings = (closedPeriods || []).filter((p) => p.is_active);
        if (activeClosings.length === 0) return [];

        const latest = activeClosings[0].closed_up_to_date;
        if (!latest) return [];

        // Gera YMs fechados = desde início do filtro até o último fechamento.
        return yearMonthsBetween(filters?.start_date, latest);
    }, [closedPeriods, filters?.start_date]);

    const applyFilter = (patch) => {
        const next = { ...filters, ...patch };
        router.get(route('dre.matrix.show'), cleanFilter(next), {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleCellClick = (line, yearMonth) => {
        setDrillTarget({ line, yearMonth });
    };

    return (
        <AuthenticatedLayout>
            <Head title="DRE Gerencial" />

            <div className="py-6">
                <div className="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
                    {/* Header */}
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-semibold text-gray-900">
                                DRE Gerencial
                            </h1>
                            <p className="text-sm text-gray-600 mt-1">
                                Demonstração do Resultado do Exercício — realizado × orçado × ano anterior.
                            </p>
                        </div>
                        {canExport && (
                            <div className="flex items-center gap-2">
                                <a
                                    href={buildExportUrl('dre.matrix.export.xlsx', filters)}
                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                                    title="Exportar XLSX"
                                >
                                    <TableCellsIcon className="h-4 w-4" />
                                    XLSX
                                </a>
                                <a
                                    href={buildExportUrl('dre.matrix.export.pdf', filters)}
                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                                    title="Exportar PDF"
                                >
                                    <DocumentTextIcon className="h-4 w-4" />
                                    PDF
                                </a>
                            </div>
                        )}
                    </div>

                    {/* Seletor de escopo */}
                    <ScopeSelector
                        scope={filters?.scope}
                        onChange={(scope) => applyFilter({ scope, store_ids: [], network_ids: [] })}
                    />

                    {/* Filtros */}
                    <FiltersBar
                        filters={filters}
                        availableStores={availableStores}
                        availableNetworks={availableNetworks}
                        availableBudgetVersions={availableBudgetVersions}
                        onApply={applyFilter}
                    />

                    {/* Banner de período fechado */}
                    {closedYearMonths.length > 0 && (
                        <ClosedPeriodBanner
                            yearMonths={closedYearMonths}
                            latest={closedPeriods?.[0]}
                        />
                    )}

                    {/* KPIs */}
                    <KpiCards kpis={kpis} />

                    {/* Tabs */}
                    <Tabs
                        filters={filters}
                        matrix={matrix}
                        closedYearMonths={closedYearMonths}
                        onCellClick={handleCellClick}
                    />
                </div>
            </div>

            <DrillModal
                show={drillTarget !== null}
                onClose={() => setDrillTarget(null)}
                line={drillTarget?.line}
                yearMonth={drillTarget?.yearMonth}
                filter={filters}
            />
        </AuthenticatedLayout>
    );
}

// ---------------------------------------------------------------------
// Scope selector
// ---------------------------------------------------------------------

function ScopeSelector({ scope = 'general', onChange }) {
    const options = [
        { value: 'general', label: 'Geral' },
        { value: 'network', label: 'Por Rede' },
        { value: 'store', label: 'Por Loja' },
    ];

    return (
        <div className="bg-white shadow-sm rounded-lg p-3 flex items-center gap-2">
            <span className="text-xs font-medium text-gray-600 mr-2">Visão:</span>
            {options.map((opt) => (
                <button
                    key={opt.value}
                    type="button"
                    onClick={() => onChange(opt.value)}
                    className={`px-3 py-1 text-sm rounded-md transition ${
                        scope === opt.value
                            ? 'bg-indigo-600 text-white'
                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
                    }`}
                >
                    {opt.label}
                </button>
            ))}
        </div>
    );
}

// ---------------------------------------------------------------------
// Filters bar
// ---------------------------------------------------------------------

function FiltersBar({
    filters,
    availableStores = [],
    availableNetworks = [],
    availableBudgetVersions = [],
    onApply,
}) {
    const [draft, setDraft] = useState({
        start_date: filters?.start_date ?? '',
        end_date: filters?.end_date ?? '',
        budget_version: filters?.budget_version ?? '',
        compare_previous_year: filters?.compare_previous_year ?? true,
        include_unclassified: filters?.include_unclassified ?? true,
        store_ids: filters?.store_ids ?? [],
        network_ids: filters?.network_ids ?? [],
    });

    const toggleMultiId = (key, id) => {
        const current = new Set(draft[key] || []);
        if (current.has(id)) current.delete(id);
        else current.add(id);
        setDraft({ ...draft, [key]: Array.from(current) });
    };

    return (
        <div className="bg-white shadow-sm rounded-lg p-4 space-y-3">
            <div className="grid grid-cols-1 md:grid-cols-5 gap-3">
                <div>
                    <label className="block text-xs font-medium text-gray-600 mb-1">
                        Data inicial
                    </label>
                    <input
                        type="date"
                        value={draft.start_date}
                        onChange={(e) => setDraft({ ...draft, start_date: e.target.value })}
                        className="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm w-full text-sm"
                    />
                </div>
                <div>
                    <label className="block text-xs font-medium text-gray-600 mb-1">
                        Data final
                    </label>
                    <input
                        type="date"
                        value={draft.end_date}
                        onChange={(e) => setDraft({ ...draft, end_date: e.target.value })}
                        className="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm w-full text-sm"
                    />
                </div>
                <div>
                    <label className="block text-xs font-medium text-gray-600 mb-1">
                        Versão Orçado
                    </label>
                    <select
                        value={draft.budget_version}
                        onChange={(e) => setDraft({ ...draft, budget_version: e.target.value })}
                        className="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm w-full text-sm"
                    >
                        <option value="">Todas</option>
                        {(availableBudgetVersions || []).map((v) => (
                            <option key={v} value={v}>
                                {v}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="flex items-end gap-2">
                    <button
                        type="button"
                        onClick={() => onApply(draft)}
                        className="px-4 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700"
                    >
                        Aplicar
                    </button>
                </div>
            </div>

            <div className="flex items-center gap-6">
                <label className="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input
                        type="checkbox"
                        checked={!!draft.compare_previous_year}
                        onChange={(e) =>
                            setDraft({ ...draft, compare_previous_year: e.target.checked })
                        }
                        className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    Comparar com ano anterior
                </label>
                <label className="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input
                        type="checkbox"
                        checked={!!draft.include_unclassified}
                        onChange={(e) =>
                            setDraft({ ...draft, include_unclassified: e.target.checked })
                        }
                        className="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    Mostrar contas não classificadas
                </label>
            </div>

            {filters?.scope === 'store' && (
                <ScopeMultiSelect
                    label="Lojas"
                    options={availableStores.map((s) => ({
                        id: s.id,
                        label: `${s.code} — ${s.name}`,
                    }))}
                    selectedIds={draft.store_ids}
                    onToggle={(id) => toggleMultiId('store_ids', id)}
                />
            )}
            {filters?.scope === 'network' && (
                <ScopeMultiSelect
                    label="Redes"
                    options={availableNetworks.map((n) => ({ id: n.id, label: n.name }))}
                    selectedIds={draft.network_ids}
                    onToggle={(id) => toggleMultiId('network_ids', id)}
                />
            )}
        </div>
    );
}

function ScopeMultiSelect({ label, options = [], selectedIds = [], onToggle }) {
    const selected = new Set(selectedIds);

    return (
        <div>
            <label className="block text-xs font-medium text-gray-600 mb-2">{label}</label>
            <div className="flex flex-wrap gap-2 max-h-32 overflow-y-auto">
                {options.map((opt) => {
                    const active = selected.has(opt.id);
                    return (
                        <button
                            key={opt.id}
                            type="button"
                            onClick={() => onToggle(opt.id)}
                            className={`px-3 py-1 text-xs rounded-full border transition ${
                                active
                                    ? 'bg-indigo-600 text-white border-indigo-600'
                                    : 'bg-white text-gray-700 border-gray-300 hover:border-indigo-400'
                            }`}
                        >
                            {opt.label}
                        </button>
                    );
                })}
                {options.length === 0 && (
                    <span className="text-xs text-gray-400">Nenhuma opção disponível.</span>
                )}
            </div>
        </div>
    );
}

// ---------------------------------------------------------------------
// Closed period banner
// ---------------------------------------------------------------------

function ClosedPeriodBanner({ yearMonths = [], latest }) {
    const firstYm = yearMonths[0];
    const lastYm = yearMonths[yearMonths.length - 1];
    const range = firstYm === lastYm ? firstYm : `${firstYm} → ${lastYm}`;

    return (
        <div className="rounded-md border-l-4 border-amber-400 bg-amber-50 p-3 flex items-start gap-3">
            <ExclamationTriangleIcon className="h-5 w-5 text-amber-500 mt-0.5 flex-shrink-0" />
            <div className="text-sm text-amber-800">
                <strong>Período parcialmente fechado:</strong> os meses {range} estão
                congelados em snapshot imutável. Lançamentos novos aparecem como pendências
                na tela de reabertura.
                {latest?.closed_up_to_date && (
                    <span className="block text-xs text-amber-700 mt-1">
                        Último fechamento ativo: {latest.closed_up_to_date}.
                    </span>
                )}
            </div>
        </div>
    );
}

// ---------------------------------------------------------------------
// Tabs
// ---------------------------------------------------------------------

function Tabs({ filters, matrix, closedYearMonths, onCellClick }) {
    const [active, setActive] = useState('monthly');

    const tabs = [
        { key: 'monthly', label: 'Matriz Mensal' },
        { key: 'yearly', label: 'Consolidado do Ano' },
        { key: 'charts', label: 'Gráficos' },
    ];

    return (
        <div>
            <div className="border-b border-gray-200 mb-4 flex gap-4">
                {tabs.map((t) => (
                    <button
                        key={t.key}
                        type="button"
                        onClick={() => setActive(t.key)}
                        className={`px-3 py-2 text-sm font-medium border-b-2 -mb-px transition ${
                            active === t.key
                                ? 'text-indigo-600 border-indigo-600'
                                : 'text-gray-500 border-transparent hover:text-gray-700 hover:border-gray-300'
                        }`}
                    >
                        {t.label}
                    </button>
                ))}
            </div>

            {active === 'monthly' && (
                <MatrixTable
                    lines={matrix?.lines || []}
                    filters={filters}
                    closedYearMonths={closedYearMonths}
                    onCellClick={onCellClick}
                />
            )}

            {active === 'yearly' && (
                <div className="bg-white shadow-sm rounded-lg p-6 text-sm text-gray-600">
                    Visão consolidada anual será acrescentada no prompt 13 (polimento + gráficos).
                </div>
            )}

            {active === 'charts' && (
                <ChartsPanel matrix={matrix} filter={filters} />
            )}
        </div>
    );
}

// ---------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------

function cleanFilter(filter) {
    // Remove keys vazias para manter a URL compacta.
    const out = {};
    for (const [k, v] of Object.entries(filter)) {
        if (v === null || v === undefined || v === '') continue;
        if (Array.isArray(v) && v.length === 0) continue;
        out[k] = v;
    }
    return out;
}

function buildExportUrl(routeName, filters) {
    return route(routeName, cleanFilter(filters || {}));
}
