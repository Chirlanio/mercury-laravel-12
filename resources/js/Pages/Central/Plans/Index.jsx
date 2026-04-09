import { Head, useForm, router } from '@inertiajs/react';
import CentralLayout from '@/Layouts/CentralLayout';
import { useState } from 'react';
import { PlusIcon, PencilIcon, TrashIcon } from '@heroicons/react/24/outline';

function centsToDisplay(cents) {
    if (!cents) return '0,00';
    const str = String(cents).padStart(3, '0');
    const intPart = str.slice(0, -2).replace(/^0+(?=\d)/, '') || '0';
    const decPart = str.slice(-2);
    const formatted = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return `${formatted},${decPart}`;
}

function numberToDisplay(num) {
    return centsToDisplay(Math.round((num || 0) * 100));
}

function displayToCents(display) {
    return parseInt(display.replace(/\D/g, '') || '0', 10);
}

function centsToNumber(cents) {
    return cents / 100;
}

export default function Index({ plans, allModules, moduleLabels }) {
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [editingPlan, setEditingPlan] = useState(null);

    return (
        <CentralLayout title="Planos">
            <Head title="Planos - Mercury SaaS" />

            <div className="flex justify-between items-center mb-6">
                <p className="text-sm text-gray-500">{plans.length} planos cadastrados</p>
                <button
                    onClick={() => setShowCreateModal(true)}
                    className="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                >
                    <PlusIcon className="h-4 w-4" />
                    Novo Plano
                </button>
            </div>

            <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                {plans.map((plan) => (
                    <div key={plan.id} className={`bg-white shadow rounded-lg overflow-hidden ${!plan.is_active ? 'opacity-60' : ''}`}>
                        <div className="px-6 py-5 border-b border-gray-200">
                            <div className="flex items-center justify-between">
                                <h3 className="text-lg font-semibold text-gray-900">{plan.name}</h3>
                                <div className="flex gap-1">
                                    <button
                                        onClick={() => setEditingPlan(plan)}
                                        title="Editar"
                                        className="inline-flex items-center justify-center w-8 h-8 rounded-md bg-amber-100 text-amber-700 hover:bg-amber-200 transition-colors"
                                    >
                                        <PencilIcon className="h-4 w-4" />
                                    </button>
                                    {plan.tenants_count === 0 && (
                                        <button
                                            onClick={() => {
                                                if (confirm(`Excluir plano ${plan.name}?`)) {
                                                    router.delete(`/admin/plans/${plan.id}`);
                                                }
                                            }}
                                            title="Excluir"
                                            className="inline-flex items-center justify-center w-8 h-8 rounded-md bg-red-100 text-red-700 hover:bg-red-200 transition-colors"
                                        >
                                            <TrashIcon className="h-4 w-4" />
                                        </button>
                                    )}
                                </div>
                            </div>
                            {plan.description && (
                                <p className="mt-1 text-sm text-gray-500">{plan.description}</p>
                            )}
                        </div>

                        <div className="px-6 py-4 space-y-3">
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-500">Preço Mensal</span>
                                <span className="font-medium">
                                    {Number(plan.price_monthly) > 0
                                        ? `R$ ${numberToDisplay(plan.price_monthly)}`
                                        : 'A definir'}
                                </span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-500">Máx Usuários</span>
                                <span className="font-medium">{plan.max_users || 'Ilimitado'}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-500">Máx Lojas</span>
                                <span className="font-medium">{plan.max_stores || 'Ilimitado'}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-500">Storage</span>
                                <span className="font-medium">{plan.max_storage_mb > 0 ? `${(plan.max_storage_mb / 1024).toFixed(0)} GB` : 'Ilimitado'}</span>
                            </div>
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-500">Tenants</span>
                                <span className="font-medium">{plan.tenants_count}</span>
                            </div>
                        </div>

                        <div className="px-6 py-4 bg-gray-50 border-t">
                            <p className="text-xs font-medium text-gray-500 mb-2">
                                Módulos ({plan.enabled_modules?.length || 0}/{allModules.length})
                            </p>
                            <div className="flex flex-wrap gap-1">
                                {plan.enabled_modules?.slice(0, 8).map((mod) => (
                                    <span key={mod} className="inline-block rounded bg-indigo-50 px-1.5 py-0.5 text-xs text-indigo-700">
                                        {moduleLabels[mod] || mod}
                                    </span>
                                ))}
                                {plan.enabled_modules?.length > 8 && (
                                    <span className="inline-block rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-500">
                                        +{plan.enabled_modules.length - 8}
                                    </span>
                                )}
                            </div>
                        </div>
                    </div>
                ))}
            </div>

            {showCreateModal && (
                <PlanFormModal
                    allModules={allModules}
                    moduleLabels={moduleLabels}
                    onClose={() => setShowCreateModal(false)}
                />
            )}

            {editingPlan && (
                <PlanFormModal
                    plan={editingPlan}
                    allModules={allModules}
                    moduleLabels={moduleLabels}
                    onClose={() => setEditingPlan(null)}
                />
            )}
        </CentralLayout>
    );
}

function PlanFormModal({ plan, allModules, moduleLabels, onClose }) {
    const isEditing = !!plan;

    const { data, setData, post, put, processing, errors } = useForm({
        name: plan?.name || '',
        slug: plan?.slug || '',
        description: plan?.description || '',
        max_users: plan?.max_users ?? 10,
        max_stores: plan?.max_stores ?? 1,
        max_storage_mb: plan?.max_storage_mb ?? 5120,
        price_monthly: plan?.price_monthly ?? 0,
        price_yearly: plan?.price_yearly ?? 0,
        features: plan?.features || {},
        modules: plan?.enabled_modules || [],
        is_active: plan?.is_active ?? true,
    });

    const [monthlyCents, setMonthlyCents] = useState(Math.round((plan?.price_monthly ?? 0) * 100));
    const [yearlyCents, setYearlyCents] = useState(Math.round((plan?.price_yearly ?? 0) * 100));

    const handleCurrencyKeyDown = (currentCents, setCents, field) => (e) => {
        if (e.key === 'Backspace') {
            e.preventDefault();
            const newCents = Math.floor(currentCents / 10);
            setCents(newCents);
            setData(field, centsToNumber(newCents));
        } else if (/^\d$/.test(e.key)) {
            e.preventDefault();
            const newCents = currentCents * 10 + parseInt(e.key, 10);
            setCents(newCents);
            setData(field, centsToNumber(newCents));
        }
    };

    const toggleModule = (mod) => {
        setData('modules', data.modules.includes(mod)
            ? data.modules.filter(m => m !== mod)
            : [...data.modules, mod]
        );
    };

    const submit = (e) => {
        e.preventDefault();
        if (isEditing) {
            put(`/admin/plans/${plan.id}`, { onSuccess: onClose });
        } else {
            post('/admin/plans', { onSuccess: onClose });
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center">
            <div className="fixed inset-0 bg-gray-600/75" onClick={onClose} />
            <div className="relative bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] flex flex-col">
                <div className="px-6 py-4 border-b shrink-0">
                    <h3 className="text-lg font-semibold text-gray-900">
                        {isEditing ? `Editar: ${plan.name}` : 'Novo Plano'}
                    </h3>
                </div>
                <form onSubmit={submit} className="flex flex-col flex-1 min-h-0">
                    <div className="overflow-y-auto flex-1 p-6 space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Nome *</label>
                                <input type="text" value={data.name} onChange={(e) => setData('name', e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" required />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Slug *</label>
                                <input type="text" value={data.slug} onChange={(e) => setData('slug', e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" required disabled={isEditing} />
                            </div>
                            <div className="col-span-2">
                                <label className="block text-sm font-medium text-gray-700">Descrição</label>
                                <textarea value={data.description} onChange={(e) => setData('description', e.target.value)}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" rows="2" />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Máx Usuários (0=ilimitado)</label>
                                <input type="number" value={data.max_users} onChange={(e) => setData('max_users', parseInt(e.target.value))}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" min="0" />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Máx Lojas (0=ilimitado)</label>
                                <input type="number" value={data.max_stores} onChange={(e) => setData('max_stores', parseInt(e.target.value))}
                                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm" min="0" />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Preço Mensal</label>
                                <div className="mt-1 relative">
                                    <span className="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-500">R$</span>
                                    <input type="text" value={centsToDisplay(monthlyCents)}
                                        onKeyDown={handleCurrencyKeyDown(monthlyCents, setMonthlyCents, 'price_monthly')}
                                        onChange={() => {}}
                                        className="block w-full pl-9 pr-3 py-2 rounded-md border-gray-300 shadow-sm text-sm text-right"
                                        inputMode="numeric" />
                                </div>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700">Preço Anual</label>
                                <div className="mt-1 relative">
                                    <span className="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-500">R$</span>
                                    <input type="text" value={centsToDisplay(yearlyCents)}
                                        onKeyDown={handleCurrencyKeyDown(yearlyCents, setYearlyCents, 'price_yearly')}
                                        onChange={() => {}}
                                        className="block w-full pl-9 pr-3 py-2 rounded-md border-gray-300 shadow-sm text-sm text-right"
                                        inputMode="numeric" />
                                </div>
                            </div>
                        </div>

                        {/* Modules */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                Módulos ({data.modules.length}/{allModules.length})
                            </label>
                            <div className="grid grid-cols-3 gap-2 max-h-48 overflow-y-auto border rounded-md p-3">
                                {allModules.map((mod) => (
                                    <label key={mod} className="flex items-center gap-2 text-sm cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={data.modules.includes(mod)}
                                            onChange={() => toggleModule(mod)}
                                            className="rounded border-gray-300 text-indigo-600"
                                        />
                                        <span className="text-gray-700">{moduleLabels[mod] || mod}</span>
                                    </label>
                                ))}
                            </div>
                        </div>
                    </div>

                    <div className="px-6 py-4 border-t bg-gray-50 rounded-b-lg shrink-0 flex justify-end gap-3">
                        <button type="button" onClick={onClose}
                            className="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                            Cancelar
                        </button>
                        <button type="submit" disabled={processing}
                            className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700 disabled:opacity-50">
                            {processing ? 'Salvando...' : (isEditing ? 'Salvar' : 'Criar Plano')}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
