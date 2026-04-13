import { Head, router } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import {
    Cog6ToothIcon,
    ClockIcon,
    CalendarDaysIcon,
    SparklesIcon,
    PlusIcon,
    TrashIcon,
    ArrowPathIcon,
} from '@heroicons/react/24/outline';
import Button from '@/Components/Button';
import StatusBadge from '@/Components/Shared/StatusBadge';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import Checkbox from '@/Components/Checkbox';

/**
 * Admin page for per-department helpdesk settings. Combines three
 * sub-resources in one page (expediente, feriados, IA) keyed to a
 * selected department.
 *
 * Layout follows the project's admin page convention (see Permissions.jsx):
 *   - max-w-5xl container, padding responsivo
 *   - header com ícone inline ao lado do título
 *   - seletor de departamento em um card branco logo abaixo
 *   - conteúdo em cards bg-white shadow-sm rounded-lg
 *   - tipografia responsiva (text-xs sm:text-sm, text-xl sm:text-2xl)
 *
 * Each section has its own Save action that posts only its own fields
 * so partial saves survive network hiccups.
 */
export default function DepartmentSettings({
    departments = [],
    selectedDepartmentId,
    department,
    businessHours = [],
    holidays = [],
    defaultSchedule = {},
    weekdayLabels = {},
    promptPlaceholders = [],
}) {
    const [activeTab, setActiveTab] = useState('schedule'); // schedule | holidays | ai

    const handleDepartmentChange = (id) => {
        router.get(route('helpdesk.department-settings.index'), { department_id: id }, {
            preserveState: false,
        });
    };

    return (
        <>
            <Head title="Configurações do Helpdesk" />
            <div className="py-6 sm:py-12">
                <div className="max-w-5xl mx-auto px-3 sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-4 sm:mb-6">
                        <h1 className="text-xl sm:text-2xl font-bold text-gray-900 flex items-center gap-2">
                            <Cog6ToothIcon className="w-6 h-6 sm:w-7 sm:h-7 text-indigo-600 shrink-0" />
                            <span>Configurações do Helpdesk</span>
                        </h1>
                        <p className="text-xs sm:text-sm text-gray-500 mt-1">
                            Expediente, feriados e classificação por IA por departamento.
                        </p>
                    </div>

                    {/* Department selector */}
                    <div className="bg-white shadow-sm rounded-lg p-3 sm:p-4 mb-4 sm:mb-6">
                        <InputLabel value="Departamento" />
                        <select
                            className="mt-1 w-full md:w-1/2 border-gray-300 rounded-lg text-sm"
                            value={selectedDepartmentId || ''}
                            onChange={e => handleDepartmentChange(e.target.value)}
                        >
                            {departments.map(d => (
                                <option key={d.id} value={d.id}>
                                    {d.name}
                                    {!d.is_active && ' (inativo)'}
                                    {d.ai_classification_enabled && ' · IA'}
                                </option>
                            ))}
                        </select>
                    </div>

                    {/* Main panel */}
                    {!department ? (
                        <div className="bg-white shadow-sm rounded-lg px-4 sm:px-6 py-10 sm:py-12 text-center text-gray-500 text-sm">
                            Nenhum departamento disponível. Cadastre um departamento primeiro.
                        </div>
                    ) : (
                        <div className="bg-white shadow-sm rounded-lg overflow-hidden">
                            {/* Tabs header */}
                            <div className="border-b border-gray-200 px-4 sm:px-6">
                                <nav className="flex gap-1 sm:gap-4 -mb-px overflow-x-auto">
                                    <TabButton
                                        active={activeTab === 'schedule'}
                                        onClick={() => setActiveTab('schedule')}
                                        icon={ClockIcon}
                                        label="Expediente"
                                    />
                                    <TabButton
                                        active={activeTab === 'holidays'}
                                        onClick={() => setActiveTab('holidays')}
                                        icon={CalendarDaysIcon}
                                        label="Feriados"
                                        count={holidays.length}
                                    />
                                    <TabButton
                                        active={activeTab === 'ai'}
                                        onClick={() => setActiveTab('ai')}
                                        icon={SparklesIcon}
                                        label="IA"
                                        activeFlag={department.ai_classification_enabled}
                                    />
                                </nav>
                            </div>

                            <div className="p-4 sm:p-6">
                                {activeTab === 'schedule' && (
                                    <BusinessHoursTab
                                        department={department}
                                        businessHours={businessHours}
                                        weekdayLabels={weekdayLabels}
                                        defaultSchedule={defaultSchedule}
                                    />
                                )}
                                {activeTab === 'holidays' && (
                                    <HolidaysTab
                                        department={department}
                                        holidays={holidays}
                                    />
                                )}
                                {activeTab === 'ai' && (
                                    <AiTab
                                        department={department}
                                        promptPlaceholders={promptPlaceholders}
                                    />
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

function TabButton({ active, onClick, icon: Icon, label, count, activeFlag }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={`flex items-center gap-2 px-3 py-3 border-b-2 text-xs sm:text-sm font-medium transition whitespace-nowrap ${
                active
                    ? 'border-indigo-500 text-indigo-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
        >
            <Icon className="w-4 h-4 sm:w-5 sm:h-5" />
            {label}
            {count !== undefined && (
                <span className="text-[10px] sm:text-xs bg-gray-200 text-gray-700 px-1.5 rounded">{count}</span>
            )}
            {activeFlag && (
                <StatusBadge variant="success" className="text-[10px] sm:text-xs">ativo</StatusBadge>
            )}
        </button>
    );
}

// =============================================================
// Business Hours tab
// =============================================================
function BusinessHoursTab({ department, businessHours, weekdayLabels, defaultSchedule }) {
    const initial = useMemo(() => {
        const grouped = {};
        for (let w = 1; w <= 7; w++) grouped[w] = [];
        for (const row of businessHours) {
            grouped[row.weekday] = grouped[row.weekday] || [];
            grouped[row.weekday].push({ start_time: row.start_time, end_time: row.end_time });
        }
        return grouped;
    }, [businessHours]);

    const [schedule, setSchedule] = useState(initial);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});

    const addRange = (weekday) => {
        setSchedule(prev => ({
            ...prev,
            [weekday]: [...(prev[weekday] || []), { start_time: '08:00', end_time: '12:00' }],
        }));
    };

    const removeRange = (weekday, idx) => {
        setSchedule(prev => ({
            ...prev,
            [weekday]: prev[weekday].filter((_, i) => i !== idx),
        }));
    };

    const updateRange = (weekday, idx, field, value) => {
        setSchedule(prev => ({
            ...prev,
            [weekday]: prev[weekday].map((r, i) => (i === idx ? { ...r, [field]: value } : r)),
        }));
    };

    const loadDefault = () => {
        const next = {};
        for (let w = 1; w <= 7; w++) {
            const ranges = defaultSchedule[w] || [];
            next[w] = ranges.map(([s, e]) => ({ start_time: s, end_time: e }));
        }
        setSchedule(next);
    };

    const handleSave = () => {
        setProcessing(true);
        setErrors({});

        const ranges = [];
        for (const [weekday, dayRanges] of Object.entries(schedule)) {
            for (const r of dayRanges) {
                ranges.push({ weekday: Number(weekday), start_time: r.start_time, end_time: r.end_time });
            }
        }

        router.put(route('helpdesk.department-settings.business-hours.update', department.id), {
            ranges,
        }, {
            preserveScroll: true,
            preserveState: true,
            onError: (errs) => setErrors(errs),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <div>
            <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-4">
                <div>
                    <h2 className="text-base sm:text-lg font-semibold text-gray-900">Expediente semanal</h2>
                    <p className="text-xs sm:text-sm text-gray-500 mt-1">
                        Define a janela em que o SLA é consumido. Fora do expediente o cronômetro é pausado.
                    </p>
                </div>
                <Button variant="light" size="sm" icon={ArrowPathIcon} onClick={loadDefault}>
                    Carregar padrão
                </Button>
            </div>

            <div className="space-y-3">
                {[1, 2, 3, 4, 5, 6, 7].map(weekday => (
                    <div key={weekday} className="border border-gray-200 rounded-lg p-3">
                        <div className="flex items-center justify-between mb-2">
                            <span className="text-sm font-medium text-gray-900">{weekdayLabels[weekday]}</span>
                            <Button variant="light" size="xs" icon={PlusIcon} onClick={() => addRange(weekday)}>
                                Intervalo
                            </Button>
                        </div>
                        {(schedule[weekday] || []).length === 0 && (
                            <p className="text-xs text-gray-400 italic">Sem expediente neste dia</p>
                        )}
                        <div className="space-y-2">
                            {(schedule[weekday] || []).map((range, idx) => (
                                <div key={idx} className="flex items-center gap-2">
                                    <TextInput
                                        type="time"
                                        value={range.start_time}
                                        onChange={e => updateRange(weekday, idx, 'start_time', e.target.value)}
                                        className="w-28 sm:w-32 text-xs sm:text-sm"
                                    />
                                    <span className="text-xs text-gray-400">até</span>
                                    <TextInput
                                        type="time"
                                        value={range.end_time}
                                        onChange={e => updateRange(weekday, idx, 'end_time', e.target.value)}
                                        className="w-28 sm:w-32 text-xs sm:text-sm"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => removeRange(weekday, idx)}
                                        className="p-1 text-gray-400 hover:text-red-600"
                                        title="Remover"
                                    >
                                        <TrashIcon className="w-4 h-4" />
                                    </button>
                                    <InputError
                                        message={errors[`ranges.${rangeFlatIndex(schedule, weekday, idx)}.end_time`]}
                                    />
                                </div>
                            ))}
                        </div>
                    </div>
                ))}
            </div>

            <div className="mt-6 flex justify-end">
                <Button variant="primary" size="sm" onClick={handleSave} loading={processing}>
                    Salvar expediente
                </Button>
            </div>
        </div>
    );
}

function rangeFlatIndex(schedule, weekday, idx) {
    let flat = 0;
    for (let w = 1; w <= 7; w++) {
        if (w === Number(weekday)) return flat + idx;
        flat += (schedule[w] || []).length;
    }
    return flat;
}

// =============================================================
// Holidays tab
// =============================================================
function HolidaysTab({ department, holidays }) {
    const [form, setForm] = useState({ date: '', description: '' });
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});

    const handleAdd = (e) => {
        e?.preventDefault();
        if (!form.date) return;
        setProcessing(true);
        router.post(route('helpdesk.department-settings.holidays.store', department.id), form, {
            preserveScroll: true,
            onSuccess: () => setForm({ date: '', description: '' }),
            onError: (errs) => setErrors(errs),
            onFinish: () => setProcessing(false),
        });
    };

    const handleDelete = (holiday) => {
        if (!confirm(`Remover feriado ${holiday.date}?`)) return;
        router.delete(
            route('helpdesk.department-settings.holidays.destroy', [department.id, holiday.id]),
            { preserveScroll: true },
        );
    };

    return (
        <div>
            <div className="mb-4">
                <h2 className="text-base sm:text-lg font-semibold text-gray-900">Feriados</h2>
                <p className="text-xs sm:text-sm text-gray-500 mt-1">
                    Dias em que o SLA não é consumido para chamados deste departamento.
                </p>
            </div>

            {/* Add form */}
            <form onSubmit={handleAdd} className="bg-gray-50 rounded-lg p-3 mb-4">
                <div className="grid grid-cols-1 sm:grid-cols-[160px_1fr_auto] gap-2">
                    <div>
                        <InputLabel value="Data" />
                        <TextInput
                            type="date"
                            className="mt-1 w-full text-xs sm:text-sm"
                            value={form.date}
                            onChange={e => setForm(p => ({ ...p, date: e.target.value }))}
                        />
                        <InputError message={errors.date} />
                    </div>
                    <div>
                        <InputLabel value="Descrição (opcional)" />
                        <TextInput
                            className="mt-1 w-full text-xs sm:text-sm"
                            placeholder="Ex.: Natal"
                            value={form.description}
                            onChange={e => setForm(p => ({ ...p, description: e.target.value }))}
                        />
                        <InputError message={errors.description} />
                    </div>
                    <div className="flex items-end">
                        <Button variant="primary" size="sm" type="submit" loading={processing}>
                            Adicionar
                        </Button>
                    </div>
                </div>
            </form>

            {/* List */}
            {holidays.length === 0 ? (
                <div className="px-4 py-10 sm:py-12 text-center text-gray-500 text-sm">
                    Nenhum feriado cadastrado.
                </div>
            ) : (
                <>
                    {/* Desktop/tablet: table */}
                    <table className="hidden sm:table w-full text-sm">
                        <thead className="bg-gray-50 text-xs uppercase text-gray-500">
                            <tr>
                                <th className="px-4 py-2 text-left">Data</th>
                                <th className="px-4 py-2 text-left">Descrição</th>
                                <th className="px-4 py-2 text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                            {holidays.map(h => (
                                <tr key={h.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-2 font-mono text-gray-900">{h.date}</td>
                                    <td className="px-4 py-2 text-gray-700">{h.description || '—'}</td>
                                    <td className="px-4 py-2 text-right">
                                        <Button variant="danger" size="xs" icon={TrashIcon}
                                            onClick={() => handleDelete(h)}>
                                            <span className="hidden lg:inline">Remover</span>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>

                    {/* Mobile: card list */}
                    <ul className="sm:hidden divide-y divide-gray-100 border border-gray-200 rounded-lg">
                        {holidays.map(h => (
                            <li key={h.id} className="p-3 flex items-center justify-between gap-3">
                                <div className="min-w-0 flex-1">
                                    <div className="font-mono text-sm text-gray-900">{h.date}</div>
                                    <div className="text-xs text-gray-500 truncate">{h.description || '—'}</div>
                                </div>
                                <Button variant="danger" size="xs" icon={TrashIcon}
                                    onClick={() => handleDelete(h)} />
                            </li>
                        ))}
                    </ul>
                </>
            )}
        </div>
    );
}

// =============================================================
// AI tab
// =============================================================
function AiTab({ department, promptPlaceholders }) {
    const [form, setForm] = useState({
        ai_classification_enabled: department.ai_classification_enabled,
        ai_classification_prompt: department.ai_classification_prompt || '',
    });
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});

    const handleSave = () => {
        setProcessing(true);
        router.put(route('helpdesk.department-settings.ai.update', department.id), form, {
            preserveScroll: true,
            preserveState: true,
            onError: (errs) => setErrors(errs),
            onFinish: () => setProcessing(false),
        });
    };

    const insertPlaceholder = (placeholder) => {
        setForm(p => ({ ...p, ai_classification_prompt: (p.ai_classification_prompt || '') + placeholder }));
    };

    return (
        <div>
            <div className="mb-4">
                <h2 className="text-base sm:text-lg font-semibold text-gray-900">Classificação por IA</h2>
                <p className="text-xs sm:text-sm text-gray-500 mt-1">
                    Quando ativada, a IA classifica cada chamado em background e a sugestão aparece no painel do técnico.
                    Nunca substitui a escolha manual.
                </p>
            </div>

            <div className="space-y-5">
                <label className="flex items-start gap-3">
                    <Checkbox
                        checked={form.ai_classification_enabled}
                        onChange={e => setForm(p => ({ ...p, ai_classification_enabled: e.target.checked }))}
                    />
                    <span>
                        <span className="block text-sm font-medium text-gray-900">Habilitar classificação por IA</span>
                        <span className="block text-xs text-gray-500 mt-0.5">
                            Requer <code className="bg-gray-100 px-1 rounded">GROQ_API_KEY</code> e{' '}
                            <code className="bg-gray-100 px-1 rounded">HELPDESK_AI_CLASSIFIER=groq</code> no .env.
                        </span>
                    </span>
                </label>

                <div>
                    <InputLabel value="Prompt customizado (opcional)" />
                    <p className="text-xs text-gray-500 mt-1 mb-2">
                        Quando vazio, usa um prompt padrão genérico. Customize para incluir regras específicas do departamento.
                    </p>
                    <textarea
                        className="w-full border-gray-300 rounded-lg text-xs sm:text-sm font-mono"
                        rows={10}
                        value={form.ai_classification_prompt}
                        onChange={e => setForm(p => ({ ...p, ai_classification_prompt: e.target.value }))}
                        placeholder="Ex.: Você é um assistente do DP da rede Meia Sola..."
                    />
                    <InputError message={errors.ai_classification_prompt} />
                </div>

                <div>
                    <InputLabel value="Placeholders disponíveis (clique para inserir)" />
                    <div className="mt-2 flex flex-wrap gap-2">
                        {promptPlaceholders.map(p => (
                            <button
                                key={p.key}
                                type="button"
                                onClick={() => insertPlaceholder(p.key)}
                                title={p.description}
                            >
                                <StatusBadge variant="info" className="cursor-pointer hover:opacity-80 font-mono text-[10px] sm:text-xs">
                                    {p.key}
                                </StatusBadge>
                            </button>
                        ))}
                    </div>
                    <ul className="mt-3 text-xs text-gray-500 space-y-0.5">
                        {promptPlaceholders.map(p => (
                            <li key={p.key}>
                                <code className="bg-gray-100 px-1 rounded">{p.key}</code> — {p.description}
                            </li>
                        ))}
                    </ul>
                </div>

                <div className="flex justify-end pt-2">
                    <Button variant="primary" size="sm" onClick={handleSave} loading={processing}>
                        Salvar configuração
                    </Button>
                </div>
            </div>
        </div>
    );
}
