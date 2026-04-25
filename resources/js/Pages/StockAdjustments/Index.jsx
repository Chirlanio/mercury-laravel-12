import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import axios from 'axios';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import Button from '@/Components/Button';
import ActionButtons from '@/Components/ActionButtons';
import StandardModal from '@/Components/StandardModal';
import PageHeader from '@/Components/Shared/PageHeader';
import StatusBadge from '@/Components/Shared/StatusBadge';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import DeleteConfirmModal from '@/Components/Shared/DeleteConfirmModal';
import EmptyState from '@/Components/Shared/EmptyState';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { formatDateTime } from '@/Utils/dateHelpers';
import {
    PlusIcon,
    MagnifyingGlassIcon,
    XMarkIcon,
    TrashIcon,
    EyeIcon,
    PencilSquareIcon,
    ArrowPathIcon,
    ClipboardDocumentListIcon,
    ClockIcon,
    CheckCircleIcon,
    ExclamationTriangleIcon,
    ArrowUpIcon,
    ArrowDownIcon,
    PaperClipIcon,
    ArrowDownTrayIcon,
    InboxIcon,
} from '@heroicons/react/24/outline';

const STATUS_VARIANT = {
    pending: 'warning',
    under_analysis: 'info',
    awaiting_response: 'orange',
    balance_transfer: 'purple',
    adjusted: 'success',
    no_adjustment: 'gray',
    cancelled: 'danger',
};

const DIRECTION_LABEL = { increase: 'Inclusão', decrease: 'Remoção' };
const DIRECTION_VARIANT = { increase: 'success', decrease: 'danger' };

const EMPTY_ITEM = () => ({
    reference: '',
    size: '',
    direction: 'increase',
    quantity: 1,
    current_stock: '',
    reason_id: '',
    notes: '',
});

export default function Index({
    adjustments,
    stores = [],
    reasons = [],
    filters = {},
    statusOptions = {},
    stats = {},
}) {
    const { hasPermission } = usePermissions();
    const { modals, openModal, closeModal } = useModalManager([
        'create',
        'detail',
        'edit',
        'transition',
    ]);

    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [storeFilter, setStoreFilter] = useState(filters.store_id || '');
    const [reasonFilter, setReasonFilter] = useState(filters.reason_id || '');
    const [directionFilter, setDirectionFilter] = useState(filters.direction || '');
    const [selectedId, setSelectedId] = useState(null);
    const [detailData, setDetailData] = useState(null);
    const [loadingDetail, setLoadingDetail] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState(null);
    const [deleting, setDeleting] = useState(false);
    const [bulkSelection, setBulkSelection] = useState([]);

    const applyFilters = () => {
        router.get(
            route('stock-adjustments.index'),
            {
                search: search || undefined,
                status: statusFilter || undefined,
                store_id: storeFilter || undefined,
                reason_id: reasonFilter || undefined,
                direction: directionFilter || undefined,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const clearFilters = () => {
        setSearch('');
        setStatusFilter('');
        setStoreFilter('');
        setReasonFilter('');
        setDirectionFilter('');
        router.get(route('stock-adjustments.index'), {}, { preserveState: true });
    };

    const hasActiveFilters =
        search || statusFilter || storeFilter || reasonFilter || directionFilter;

    const fetchDetail = async (id) => {
        setLoadingDetail(true);
        try {
            const { data } = await axios.get(route('stock-adjustments.show', id));
            setDetailData(data.adjustment);
            setSelectedId(id);
        } catch (e) {
            console.error(e);
            alert('Erro ao carregar detalhes do ajuste.');
        } finally {
            setLoadingDetail(false);
        }
    };

    const openDetail = async (id) => {
        await fetchDetail(id);
        openModal('detail');
    };

    const openEdit = async (id) => {
        await fetchDetail(id);
        openModal('edit');
    };

    const openTransition = async (id) => {
        await fetchDetail(id);
        openModal('transition');
    };

    const handleDelete = () => {
        if (!deleteTarget) return;
        setDeleting(true);
        router.delete(route('stock-adjustments.destroy', deleteTarget.id), {
            preserveScroll: true,
            onFinish: () => {
                setDeleting(false);
                setDeleteTarget(null);
            },
        });
    };

    const toggleBulk = (id) => {
        setBulkSelection((prev) =>
            prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id],
        );
    };

    const clearBulk = () => setBulkSelection([]);

    const statsCards = useMemo(
        () => [
            {
                label: 'Total',
                value: stats.total ?? 0,
                format: 'number',
                color: 'indigo',
                icon: ClipboardDocumentListIcon,
            },
            {
                label: 'Pendentes',
                value: stats.pending ?? 0,
                format: 'number',
                color: 'yellow',
                icon: ClockIcon,
                onClick: () => {
                    setStatusFilter('pending');
                    router.get(
                        route('stock-adjustments.index'),
                        { status: 'pending' },
                        { preserveState: true },
                    );
                },
                active: statusFilter === 'pending',
            },
            {
                label: 'Em Análise',
                value: stats.under_analysis ?? 0,
                format: 'number',
                color: 'blue',
                icon: ExclamationTriangleIcon,
            },
            {
                label: 'Aguardando Resposta',
                value: stats.awaiting_response ?? 0,
                format: 'number',
                color: 'orange',
                icon: ArrowPathIcon,
            },
            {
                label: 'Ajustados no Mês',
                value: stats.adjusted_month ?? 0,
                format: 'number',
                color: 'green',
                icon: CheckCircleIcon,
            },
        ],
        [stats, statusFilter],
    );

    return (
        <>
            <Head title="Ajustes de Estoque" />

            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <PageHeader
                        title="Ajustes de Estoque"
                        subtitle="Solicite inclusão ou remoção de saldo e acompanhe o ciclo de aprovação."
                        actions={[
                            {
                                type: 'download',
                                download: route('stock-adjustments.export', {
                                    status: statusFilter || undefined,
                                    store_id: storeFilter || undefined,
                                    reason_id: reasonFilter || undefined,
                                    direction: directionFilter || undefined,
                                }),
                            },
                            {
                                type: 'create',
                                label: 'Novo Ajuste',
                                onClick: () => openModal('create'),
                                visible: hasPermission(PERMISSIONS.CREATE_ADJUSTMENTS),
                            },
                        ]}
                    />

                    {/* Estatísticas */}
                    <div className="mb-6">
                        <StatisticsGrid cards={statsCards} cols={5} />
                    </div>

                    {/* Filtros */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="grid grid-cols-1 sm:grid-cols-6 gap-3 items-end">
                            <div className="relative sm:col-span-2">
                                <MagnifyingGlassIcon className="absolute left-3 top-2.5 h-5 w-5 text-gray-400" />
                                <input
                                    type="text"
                                    placeholder="Buscar por referência, observação..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                                    className="pl-10 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                />
                            </div>
                            <select
                                value={statusFilter}
                                onChange={(e) => setStatusFilter(e.target.value)}
                                className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            >
                                <option value="">Todos os Status</option>
                                {Object.entries(statusOptions).map(([key, label]) => (
                                    <option key={key} value={key}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                            <select
                                value={storeFilter}
                                onChange={(e) => setStoreFilter(e.target.value)}
                                className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            >
                                <option value="">Todas as Lojas</option>
                                {stores.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.code} - {s.name}
                                    </option>
                                ))}
                            </select>
                            <select
                                value={reasonFilter}
                                onChange={(e) => setReasonFilter(e.target.value)}
                                className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                            >
                                <option value="">Todos os Motivos</option>
                                {reasons.map((r) => (
                                    <option key={r.id} value={r.id}>
                                        {r.name}
                                    </option>
                                ))}
                            </select>
                            <div className="flex gap-2">
                                <Button
                                    variant="primary"
                                    size="sm"
                                    onClick={applyFilters}
                                    icon={MagnifyingGlassIcon}
                                >
                                    Filtrar
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={clearFilters}
                                    disabled={!hasActiveFilters}
                                    icon={XMarkIcon}
                                >
                                    Limpar
                                </Button>
                            </div>
                        </div>
                    </div>

                    {/* Barra de ações em lote */}
                    {bulkSelection.length > 0 && (
                        <div className="bg-indigo-50 border border-indigo-200 rounded-lg p-3 mb-4 flex items-center justify-between">
                            <span className="text-sm text-indigo-900">
                                {bulkSelection.length} ajuste(s) selecionado(s)
                            </span>
                            <div className="flex gap-2">
                                <BulkTransitionButton
                                    ids={bulkSelection}
                                    onDone={clearBulk}
                                />
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={clearBulk}
                                    icon={XMarkIcon}
                                >
                                    Limpar
                                </Button>
                            </div>
                        </div>
                    )}

                    {/* Tabela */}
                    <div className="bg-white shadow-sm rounded-lg overflow-hidden">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase w-8">
                                        <input
                                            type="checkbox"
                                            onChange={(e) =>
                                                setBulkSelection(
                                                    e.target.checked
                                                        ? (adjustments.data || []).map(
                                                              (a) => a.id,
                                                          )
                                                        : [],
                                                )
                                            }
                                            checked={
                                                bulkSelection.length > 0 &&
                                                bulkSelection.length ===
                                                    (adjustments.data || []).length
                                            }
                                            className="rounded border-gray-300"
                                        />
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        ID
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        Loja
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        Colaborador
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        Itens
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        Status
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        Criado por
                                    </th>
                                    <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                        Data
                                    </th>
                                    <th className="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">
                                        Ações
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {adjustments.data?.length > 0 ? (
                                    adjustments.data.map((adj) => (
                                        <tr key={adj.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-3">
                                                <input
                                                    type="checkbox"
                                                    checked={bulkSelection.includes(adj.id)}
                                                    onChange={() => toggleBulk(adj.id)}
                                                    className="rounded border-gray-300"
                                                />
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                                #{adj.id}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-700">
                                                {adj.store?.code} - {adj.store?.name || '-'}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                {adj.employee || '-'}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                {adj.items_count} item(s)
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                <StatusBadge
                                                    variant={STATUS_VARIANT[adj.status] || 'gray'}
                                                >
                                                    {adj.status_label}
                                                </StatusBadge>
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                {adj.created_by || '-'}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                {formatDateTime(adj.created_at)}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-right">
                                                <ActionButtons
                                                    onView={() => openDetail(adj.id)}
                                                    onEdit={
                                                        hasPermission(
                                                            PERMISSIONS.EDIT_ADJUSTMENTS,
                                                        ) && adj.status === 'pending'
                                                            ? () => openEdit(adj.id)
                                                            : undefined
                                                    }
                                                    onDelete={
                                                        hasPermission(
                                                            PERMISSIONS.DELETE_ADJUSTMENTS,
                                                        ) && adj.status === 'pending'
                                                            ? () => setDeleteTarget(adj)
                                                            : undefined
                                                    }
                                                >
                                                    {hasPermission(
                                                        PERMISSIONS.EDIT_ADJUSTMENTS,
                                                    ) && (
                                                        <ActionButtons.Custom
                                                            icon={ArrowPathIcon}
                                                            title="Alterar status"
                                                            onClick={() =>
                                                                openTransition(adj.id)
                                                            }
                                                        />
                                                    )}
                                                </ActionButtons>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan="9" className="px-6 py-12">
                                            <EmptyState
                                                icon={InboxIcon}
                                                title="Nenhum ajuste encontrado"
                                                description="Crie um novo ajuste de estoque para começar."
                                                compact
                                            />
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>

                        {adjustments.links && adjustments.last_page > 1 && (
                            <div className="px-6 py-3 border-t border-gray-200 flex justify-between items-center">
                                <span className="text-sm text-gray-700">
                                    Mostrando {adjustments.from} a {adjustments.to} de{' '}
                                    {adjustments.total} registros
                                </span>
                                <div className="flex space-x-1">
                                    {adjustments.links.map((link, i) => (
                                        <button
                                            key={i}
                                            onClick={() =>
                                                link.url &&
                                                router.get(link.url, {}, { preserveScroll: true })
                                            }
                                            disabled={!link.url}
                                            className={`px-3 py-1 text-sm rounded ${
                                                link.active
                                                    ? 'bg-indigo-600 text-white'
                                                    : link.url
                                                      ? 'bg-white text-gray-700 hover:bg-gray-50 border'
                                                      : 'bg-gray-100 text-gray-400 cursor-not-allowed'
                                            }`}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Modal: Criar */}
            {modals.create && (
                <AdjustmentFormModal
                    mode="create"
                    stores={stores}
                    onClose={() => closeModal('create')}
                />
            )}

            {/* Modal: Detalhe */}
            {modals.detail && (
                <DetailModal
                    data={detailData}
                    loading={loadingDetail}
                    onClose={() => closeModal('detail')}
                    onEdit={() => {
                        closeModal('detail');
                        openModal('edit');
                    }}
                    onTransition={() => {
                        closeModal('detail');
                        openModal('transition');
                    }}
                    canEdit={hasPermission(PERMISSIONS.EDIT_ADJUSTMENTS)}
                />
            )}

            {/* Modal: Editar */}
            {modals.edit && detailData && (
                <AdjustmentFormModal
                    mode="edit"
                    data={detailData}
                    stores={stores}
                    onClose={() => closeModal('edit')}
                />
            )}

            {/* Modal: Transição */}
            {modals.transition && detailData && (
                <TransitionModal
                    data={detailData}
                    onClose={() => closeModal('transition')}
                />
            )}

            {/* Modal: Delete */}
            <DeleteConfirmModal
                show={deleteTarget !== null}
                onClose={() => setDeleteTarget(null)}
                onConfirm={handleDelete}
                itemType="ajuste"
                itemName={deleteTarget ? `#${deleteTarget.id}` : ''}
                details={
                    deleteTarget
                        ? [
                              { label: 'Loja', value: deleteTarget.store?.name },
                              {
                                  label: 'Itens',
                                  value: `${deleteTarget.items_count} item(s)`,
                              },
                              { label: 'Status', value: deleteTarget.status_label },
                          ]
                        : []
                }
                warningMessage="Esta ação só é permitida em ajustes pendentes."
                processing={deleting}
            />
        </>
    );
}

/* ============================================================================
 * Modal: Criar / Editar — UX inspirada no formulário v1
 *
 * Fluxo:
 *   1. Loja → dispara fetch de consultoras daquela loja
 *   2. Consultora + Cliente + Observação (justificativa livre da loja)
 *   3. Somente com os 3 primeiros preenchidos, a busca de produtos é habilitada
 *   4. Busca por referência/descrição (debounced 300ms)
 *   5. Clica num resultado → carrega variantes e adiciona o card do produto
 *   6. Cada tamanho é um tile clicável. Selecionado, mostra:
 *        - toggle de direção (+ Inclusão / − Remoção) — cada tamanho só pode
 *          ter uma direção por vez
 *        - quantidade
 *        - estoque atual (opcional)
 *      Dentro do mesmo produto, tamanhos diferentes podem ter direções
 *      diferentes, mas o mesmo tamanho não mistura entrada e saída.
 *
 * O motivo/solução NÃO aparece aqui: é atribuição do backoffice, preenchido
 * na transição de status pelo financeiro. A loja só preenche observação.
 *
 * No submit, cada tamanho selecionado com quantidade > 0 vira 1 linha em
 * items[]: { reference, size, direction, quantity, current_stock }.
 * ========================================================================= */
function AdjustmentFormModal({ mode = 'create', data = null, stores, onClose }) {
    const isEdit = mode === 'edit';

    // Form state usando useForm do Inertia
    const form = useForm({
        store_id: data?.store?.id || '',
        employee_id: data?.employee?.id || '',
        client_name: data?.client_name || '',
        observation: data?.observation || '',
        items: [],
    });

    // Lista de produtos selecionados (estado agrupado — apenas para UI)
    // [{ reference, description, sizes: [{ size, selected, direction, quantity, current_stock }] }]
    const [selectedProducts, setSelectedProducts] = useState(() => {
        if (!isEdit || !data?.items?.length) return [];
        const groups = {};
        data.items.forEach((i) => {
            const ref = i.reference;
            const sizeName = i.size || 'UN';
            if (!groups[ref]) {
                groups[ref] = { reference: ref, description: '', sizesMap: {} };
            }
            // Um tamanho tem uma única direção; se vier duplicado, mantém a primeira
            if (!groups[ref].sizesMap[sizeName]) {
                groups[ref].sizesMap[sizeName] = {
                    size: sizeName,
                    selected: true,
                    direction: i.direction || 'increase',
                    quantity: i.quantity || 1,
                    current_stock: i.current_stock ?? '',
                };
            }
        });
        return Object.values(groups).map((g) => ({
            reference: g.reference,
            description: g.description,
            sizes: Object.values(g.sizesMap),
        }));
    });

    // Estado de busca de produtos
    const [employees, setEmployees] = useState([]);
    const [loadingEmployees, setLoadingEmployees] = useState(false);
    const [productSearch, setProductSearch] = useState('');
    const [productResults, setProductResults] = useState([]);
    const [showResults, setShowResults] = useState(false);
    const [searching, setSearching] = useState(false);
    const [loadingProduct, setLoadingProduct] = useState(false);

    // Carrega colaboradores quando a loja muda
    useEffect(() => {
        if (!form.data.store_id) {
            setEmployees([]);
            return;
        }
        setLoadingEmployees(true);
        axios
            .get(route('stock-adjustments.lookup.employees'), {
                params: { store_id: form.data.store_id },
            })
            .then((res) => setEmployees(res.data.employees || []))
            .catch(() => setEmployees([]))
            .finally(() => setLoadingEmployees(false));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [form.data.store_id]);

    // Debounce de busca de produtos
    useEffect(() => {
        if (productSearch.trim().length < 2) {
            setProductResults([]);
            return;
        }
        setSearching(true);
        const handler = setTimeout(() => {
            axios
                .get(route('stock-adjustments.lookup.products'), {
                    params: { term: productSearch.trim() },
                })
                .then((res) => {
                    setProductResults(res.data.products || []);
                    setShowResults(true);
                })
                .catch(() => setProductResults([]))
                .finally(() => setSearching(false));
        }, 300);
        return () => clearTimeout(handler);
    }, [productSearch]);

    // Usuário precisa preencher loja + consultora + cliente antes de buscar produtos
    const canSearchProducts =
        form.data.store_id && form.data.employee_id && form.data.client_name.trim().length > 0;

    const addProductFromSearch = async (result) => {
        // Evita duplicata
        if (selectedProducts.some((p) => p.reference === result.reference)) {
            setProductSearch('');
            setShowResults(false);
            return;
        }
        setLoadingProduct(true);
        try {
            const { data: res } = await axios.get(
                route('stock-adjustments.lookup.product-sizes', result.reference),
            );
            const product = res.product;
            setSelectedProducts((prev) => [
                ...prev,
                {
                    reference: product.reference,
                    description: product.description,
                    sizes: (product.sizes || []).map((s) => ({
                        size: s.size,
                        selected: product.is_single_size, // único tamanho já vem selecionado
                        direction: 'increase',
                        quantity: 1,
                        current_stock: s.stock ?? '',
                    })),
                },
            ]);
        } catch {
            alert('Erro ao carregar tamanhos do produto.');
        } finally {
            setLoadingProduct(false);
            setProductSearch('');
            setProductResults([]);
            setShowResults(false);
        }
    };

    const removeProduct = (ref) => {
        setSelectedProducts((prev) => prev.filter((p) => p.reference !== ref));
    };

    const toggleSize = (ref, sizeName) => {
        setSelectedProducts((prev) =>
            prev.map((p) =>
                p.reference === ref
                    ? {
                          ...p,
                          sizes: p.sizes.map((s) =>
                              s.size === sizeName ? { ...s, selected: !s.selected } : s,
                          ),
                      }
                    : p,
            ),
        );
    };

    const updateSize = (ref, sizeName, field, value) => {
        setSelectedProducts((prev) =>
            prev.map((p) =>
                p.reference === ref
                    ? {
                          ...p,
                          sizes: p.sizes.map((s) =>
                              s.size === sizeName ? { ...s, [field]: value } : s,
                          ),
                      }
                    : p,
            ),
        );
    };

    const handleSubmit = () => {
        // Flatten: 1 linha por tamanho selecionado com quantidade > 0
        const items = [];
        selectedProducts.forEach((p) => {
            p.sizes
                .filter((s) => s.selected && Number(s.quantity) > 0)
                .forEach((s) => {
                    items.push({
                        reference: p.reference,
                        size: s.size,
                        direction: s.direction,
                        quantity: Number(s.quantity),
                        current_stock: s.current_stock === '' ? null : Number(s.current_stock),
                    });
                });
        });

        if (items.length === 0) {
            alert('Selecione pelo menos um tamanho com quantidade maior que zero.');
            return;
        }

        const options = {
            onSuccess: () => {
                form.reset();
                setSelectedProducts([]);
                onClose();
            },
            preserveScroll: true,
        };

        // Aplica transform para injetar os items flattened no payload
        form.transform((d) => ({ ...d, items }));

        if (isEdit) {
            form.put(route('stock-adjustments.update', data.id), options);
        } else {
            form.post(route('stock-adjustments.store'), options);
        }
    };

    return (
        <StandardModal
            show={true}
            onClose={onClose}
            title={isEdit ? `Editar Ajuste #${data.id}` : 'Novo Ajuste de Estoque'}
            subtitle={
                isEdit
                    ? 'Atualize dados e itens do ajuste'
                    : 'Informe a loja, consultora, cliente e os produtos a ajustar'
            }
            headerColor="bg-indigo-600"
            headerIcon={
                isEdit ? (
                    <PencilSquareIcon className="h-5 w-5" />
                ) : (
                    <PlusIcon className="h-5 w-5" />
                )
            }
            maxWidth="5xl"
            footer={
                <StandardModal.Footer
                    onCancel={onClose}
                    onSubmit={handleSubmit}
                    submitLabel={isEdit ? 'Salvar Alterações' : 'Criar Ajuste'}
                    processing={form.processing}
                />
            }
        >
            {/* ============ Bloco 1 — Informações básicas ============ */}
            <StandardModal.Section title="Informações Básicas">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <InputLabel value="Loja *" />
                        <select
                            value={form.data.store_id}
                            onChange={(e) => {
                                form.setData('store_id', e.target.value);
                                form.setData('employee_id', ''); // reset employee
                            }}
                            disabled={isEdit}
                            className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-50"
                            required
                        >
                            <option value="">Selecione a loja</option>
                            {stores.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.code} - {s.name}
                                </option>
                            ))}
                        </select>
                        {form.errors.store_id && (
                            <p className="mt-1 text-xs text-red-600">{form.errors.store_id}</p>
                        )}
                    </div>

                    <div>
                        <InputLabel value="Consultora *" />
                        <select
                            value={form.data.employee_id}
                            onChange={(e) => form.setData('employee_id', e.target.value)}
                            disabled={!form.data.store_id || loadingEmployees}
                            className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-50"
                            required
                        >
                            <option value="">
                                {!form.data.store_id
                                    ? 'Selecione a loja primeiro'
                                    : loadingEmployees
                                      ? 'Carregando...'
                                      : employees.length === 0
                                        ? 'Nenhuma consultora na loja'
                                        : 'Selecione a consultora'}
                            </option>
                            {employees.map((e) => (
                                <option key={e.id} value={e.id}>
                                    {e.name}
                                </option>
                            ))}
                        </select>
                        {form.errors.employee_id && (
                            <p className="mt-1 text-xs text-red-600">{form.errors.employee_id}</p>
                        )}
                    </div>

                    <div>
                        <InputLabel value="Cliente *" />
                        <TextInput
                            value={form.data.client_name}
                            onChange={(e) => form.setData('client_name', e.target.value)}
                            className="mt-1 w-full"
                            placeholder="Nome do cliente"
                            maxLength={150}
                            required
                        />
                        {form.errors.client_name && (
                            <p className="mt-1 text-xs text-red-600">{form.errors.client_name}</p>
                        )}
                    </div>

                    <div>
                        <InputLabel value="Observação geral" />
                        <textarea
                            value={form.data.observation}
                            onChange={(e) => form.setData('observation', e.target.value)}
                            rows={1}
                            placeholder="Contexto complementar (opcional)"
                            className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        />
                    </div>
                </div>
            </StandardModal.Section>

            {/* ============ Bloco 2 — Busca de produto ============ */}
            <StandardModal.Section title="Adicionar Produtos">
                <div className="relative">
                    <MagnifyingGlassIcon className="absolute left-3 top-2.5 h-5 w-5 text-gray-400 pointer-events-none" />
                    <input
                        type="text"
                        value={productSearch}
                        onChange={(e) => setProductSearch(e.target.value)}
                        onFocus={() => productResults.length > 0 && setShowResults(true)}
                        disabled={!canSearchProducts || loadingProduct}
                        placeholder={
                            canSearchProducts
                                ? 'Buscar produto por referência ou descrição...'
                                : 'Preencha loja, consultora e cliente para buscar'
                        }
                        className="pl-10 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-50"
                    />
                    {(searching || loadingProduct) && (
                        <div className="absolute right-3 top-2.5 text-xs text-gray-500">
                            {loadingProduct ? 'Carregando produto...' : 'Buscando...'}
                        </div>
                    )}
                </div>

                {/* Lista inline — não fica absoluta para não ser cortada pelo overflow do modal */}
                {showResults && productResults.length > 0 && (
                    <div className="mt-2 w-full bg-white border border-gray-200 rounded-md shadow-sm max-h-80 overflow-y-auto divide-y divide-gray-100">
                        {productResults.map((p) => (
                            <button
                                key={p.id}
                                type="button"
                                onClick={() => addProductFromSearch(p)}
                                className="w-full text-left px-4 py-2 hover:bg-indigo-50 flex items-center gap-3"
                            >
                                {p.image && (
                                    <img
                                        src={p.image}
                                        alt=""
                                        className="w-8 h-8 object-cover rounded"
                                        onError={(e) => (e.target.style.display = 'none')}
                                    />
                                )}
                                <div className="flex-1 min-w-0">
                                    <div className="font-mono text-xs text-gray-500">
                                        {p.reference}
                                    </div>
                                    <div className="text-sm text-gray-800 truncate">
                                        {p.description}
                                    </div>
                                </div>
                            </button>
                        ))}
                    </div>
                )}

                {showResults &&
                    productResults.length === 0 &&
                    !searching &&
                    productSearch.length >= 2 && (
                        <div className="mt-2 w-full bg-white border border-gray-200 rounded-md px-4 py-3 text-sm text-gray-500">
                            Nenhum produto encontrado.
                        </div>
                    )}

                {/* ============ Bloco 3 — Produtos selecionados ============ */}
                {selectedProducts.length === 0 ? (
                    <div className="mt-4 text-center py-6 text-sm text-gray-400 italic border border-dashed border-gray-200 rounded-lg">
                        Nenhum produto adicionado. Use a busca acima.
                    </div>
                ) : (
                    <div className="mt-4 space-y-4">
                        {selectedProducts.map((product) => (
                            <ProductCard
                                key={product.reference}
                                product={product}
                                onRemove={() => removeProduct(product.reference)}
                                onToggleSize={(size) => toggleSize(product.reference, size)}
                                onUpdateSize={(size, field, value) =>
                                    updateSize(product.reference, size, field, value)
                                }
                            />
                        ))}
                    </div>
                )}
            </StandardModal.Section>
        </StandardModal>
    );
}

/* ============================================================================
 * Card de produto selecionado
 * ========================================================================= */
function ProductCard({ product, onRemove, onToggleSize, onUpdateSize }) {
    const selectedCount = product.sizes.filter((s) => s.selected).length;

    return (
        <div className="border border-gray-200 rounded-lg overflow-hidden bg-white">
            {/* Header */}
            <div className="bg-gray-50 px-4 py-2 flex items-center justify-between border-b border-gray-200">
                <div className="flex-1 min-w-0 flex items-baseline gap-2">
                    <span className="font-mono font-semibold text-indigo-700">
                        {product.reference}
                    </span>
                    <span className="text-sm text-gray-600 truncate">
                        {product.description}
                    </span>
                </div>
                <button
                    type="button"
                    onClick={onRemove}
                    className="text-red-500 hover:text-red-700 ml-2"
                    title="Remover produto"
                >
                    <TrashIcon className="h-4 w-4" />
                </button>
            </div>

            {/* Grade de tamanhos */}
            <div className="px-4 py-3">
                <p className="text-xs text-gray-500 mb-2">
                    Clique nos tamanhos para selecionar ({selectedCount}/{product.sizes.length}).
                    Para cada tamanho escolha inclusão (+) ou remoção (−).
                </p>
                {product.sizes.length === 0 ? (
                    <p className="text-sm text-gray-500 italic">
                        Este produto não possui variantes cadastradas.
                    </p>
                ) : (
                    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2">
                        {product.sizes.map((s) => (
                            <SizeTile
                                key={s.size}
                                size={s}
                                onToggle={() => onToggleSize(s.size)}
                                onUpdate={(field, value) => onUpdateSize(s.size, field, value)}
                            />
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}

/* ============================================================================
 * Tile de tamanho — clicável, com toggle de direção (entrada OU saída)
 * ========================================================================= */
function SizeTile({ size, onToggle, onUpdate }) {
    const stopClick = (e) => e.stopPropagation();

    if (!size.selected) {
        return (
            <button
                type="button"
                onClick={onToggle}
                className="rounded-md border-2 border-gray-200 bg-white hover:border-indigo-400 hover:bg-indigo-50 text-gray-800 py-3 text-center font-semibold text-sm transition select-none min-h-[44px]"
            >
                {size.size}
            </button>
        );
    }

    const isIncrease = size.direction === 'increase';
    const borderClass = isIncrease ? 'border-green-500' : 'border-red-500';
    const bgClass = isIncrease ? 'bg-green-50' : 'bg-red-50';
    const headerTextClass = isIncrease ? 'text-green-900' : 'text-red-900';
    const closeIconClass = isIncrease ? 'text-green-500' : 'text-red-500';

    return (
        <div className={`rounded-md border-2 ${borderClass} ${bgClass} p-2 select-none`}>
            {/* Header: tamanho + botão fechar */}
            <div
                className="flex items-center justify-between cursor-pointer mb-2"
                onClick={onToggle}
                title="Clique para desmarcar"
            >
                <span className={`font-semibold text-sm ${headerTextClass}`}>
                    {size.size}
                </span>
                <XMarkIcon className={`h-3.5 w-3.5 ${closeIconClass}`} />
            </div>

            {/* Corpo: toggle de direção + inputs */}
            <div className="space-y-1.5" onClick={stopClick}>
                {/* Toggle direção */}
                <div className="grid grid-cols-2 rounded border border-gray-300 overflow-hidden text-xs">
                    <button
                        type="button"
                        onClick={() => onUpdate('direction', 'increase')}
                        className={`py-1 flex items-center justify-center gap-0.5 transition ${
                            isIncrease
                                ? 'bg-green-600 text-white'
                                : 'bg-white text-gray-600 hover:bg-green-50'
                        }`}
                        title="Inclusão de saldo"
                    >
                        <ArrowUpIcon className="h-3 w-3" />
                    </button>
                    <button
                        type="button"
                        onClick={() => onUpdate('direction', 'decrease')}
                        className={`py-1 flex items-center justify-center gap-0.5 transition ${
                            !isIncrease
                                ? 'bg-red-600 text-white'
                                : 'bg-white text-gray-600 hover:bg-red-50'
                        }`}
                        title="Remoção de saldo"
                    >
                        <ArrowDownIcon className="h-3 w-3" />
                    </button>
                </div>

                {/* Inputs — todos w-full com a mesma classe para garantir largura idêntica */}
                <input
                    type="number"
                    min="1"
                    value={size.quantity}
                    onChange={(e) => onUpdate('quantity', e.target.value)}
                    className="block w-full text-center text-xs rounded border-gray-300 py-1 px-1 focus:border-indigo-500 focus:ring-indigo-500"
                    placeholder="Qtd."
                />
                <input
                    type="number"
                    value={size.current_stock}
                    onChange={(e) => onUpdate('current_stock', e.target.value)}
                    className="block w-full text-center text-xs rounded border-gray-300 py-1 px-1 text-gray-600 focus:border-indigo-500 focus:ring-indigo-500"
                    placeholder="Estoque"
                    title="Estoque atual do sistema (opcional)"
                />
            </div>
        </div>
    );
}

/* ============================================================================
 * Modal: Detalhe
 * ========================================================================= */
function DetailModal({ data, loading, onClose, onEdit, onTransition, canEdit }) {
    if (!data) {
        return (
            <StandardModal
                show={true}
                onClose={onClose}
                title="Detalhes do Ajuste"
                headerColor="bg-gray-700"
                loading={loading}
            >
                <div className="h-40" />
            </StandardModal>
        );
    }

    const historyItems = (data.status_history || []).map((h) => ({
        id: h.id,
        title: `${h.old_status_label} → ${h.new_status_label}`,
        subtitle: `${h.changed_by || 'Sistema'} — ${h.created_at}`,
        notes: h.notes,
        dotColor:
            h.new_status === 'adjusted'
                ? 'bg-green-500'
                : h.new_status === 'cancelled' || h.new_status === 'no_adjustment'
                  ? 'bg-red-500'
                  : 'bg-indigo-500',
    }));

    const canTransition =
        canEdit &&
        data.allowed_transitions &&
        Object.keys(data.allowed_transitions).length > 0;

    return (
        <StandardModal
            show={true}
            onClose={onClose}
            title={`Ajuste #${data.id}`}
            subtitle={`${data.store?.code || ''} - ${data.store?.name || ''}`}
            headerColor="bg-indigo-700"
            headerIcon={<EyeIcon className="h-5 w-5" />}
            headerBadges={[
                {
                    label: data.status_label,
                    color: STATUS_VARIANT[data.status] || 'gray',
                },
            ]}
            headerActions={
                <div className="flex gap-2">
                    {canEdit && data.status === 'pending' && (
                        <Button
                            variant="light"
                            size="sm"
                            onClick={onEdit}
                            icon={PencilSquareIcon}
                        >
                            Editar
                        </Button>
                    )}
                    {canTransition && (
                        <Button
                            variant="light"
                            size="sm"
                            onClick={onTransition}
                            icon={ArrowPathIcon}
                        >
                            Alterar Status
                        </Button>
                    )}
                </div>
            }
            maxWidth="5xl"
        >
            <StandardModal.Section title="Informações">
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <StandardModal.Field label="Loja" value={data.store?.name || '-'} />
                    <StandardModal.Field
                        label="Consultora"
                        value={data.employee?.name || '-'}
                    />
                    <StandardModal.Field label="Cliente" value={data.client_name || '-'} />
                    <StandardModal.Field label="Criado por" value={data.created_by} />
                    <StandardModal.Field label="Criado em" value={data.created_at} />
                    {data.observation && (
                        <div className="col-span-2 md:col-span-4">
                            <StandardModal.Field
                                label="Observação"
                                value={data.observation}
                            />
                        </div>
                    )}
                </div>
            </StandardModal.Section>

            <StandardModal.Section title={`Itens (${data.items?.length || 0})`}>
                <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-3 py-2 text-left font-medium text-gray-600">
                                    Referência
                                </th>
                                <th className="px-3 py-2 text-left font-medium text-gray-600">
                                    Tam.
                                </th>
                                <th className="px-3 py-2 text-left font-medium text-gray-600">
                                    Direção
                                </th>
                                <th className="px-3 py-2 text-right font-medium text-gray-600">
                                    Qtde
                                </th>
                                <th className="px-3 py-2 text-right font-medium text-gray-600">
                                    Estoque Atual
                                </th>
                                <th className="px-3 py-2 text-left font-medium text-gray-600">
                                    Motivo
                                </th>
                                <th className="px-3 py-2 text-left font-medium text-gray-600">
                                    Obs.
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200">
                            {data.items?.map((item) => (
                                <tr key={item.id}>
                                    <td className="px-3 py-2 font-mono text-xs">
                                        {item.reference}
                                    </td>
                                    <td className="px-3 py-2">{item.size || '-'}</td>
                                    <td className="px-3 py-2">
                                        <StatusBadge
                                            variant={DIRECTION_VARIANT[item.direction]}
                                            icon={
                                                item.direction === 'increase'
                                                    ? ArrowUpIcon
                                                    : ArrowDownIcon
                                            }
                                        >
                                            {DIRECTION_LABEL[item.direction]}
                                        </StatusBadge>
                                    </td>
                                    <td className="px-3 py-2 text-right font-semibold">
                                        {item.quantity}
                                    </td>
                                    <td className="px-3 py-2 text-right text-gray-500">
                                        {item.current_stock ?? '-'}
                                    </td>
                                    <td className="px-3 py-2 text-gray-700">
                                        {item.reason?.name || (
                                            <span className="text-gray-400 italic">
                                                não informado
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-3 py-2 text-gray-500 text-xs">
                                        {item.notes || '-'}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </StandardModal.Section>

            {data.nfs?.length > 0 && (
                <StandardModal.Section title="Notas Fiscais">
                    <div className="space-y-2">
                        {data.nfs.map((nf) => (
                            <div
                                key={nf.id}
                                className="flex items-center justify-between bg-gray-50 rounded p-2 text-sm"
                            >
                                <div>
                                    {nf.nf_entrada && (
                                        <span className="mr-4">
                                            <strong>NF Entrada:</strong> {nf.nf_entrada}
                                        </span>
                                    )}
                                    {nf.nf_saida && (
                                        <span>
                                            <strong>NF Saída:</strong> {nf.nf_saida}
                                        </span>
                                    )}
                                </div>
                                {nf.notes && (
                                    <span className="text-xs text-gray-500">{nf.notes}</span>
                                )}
                            </div>
                        ))}
                    </div>
                </StandardModal.Section>
            )}

            {data.attachments?.length > 0 && (
                <StandardModal.Section title="Anexos">
                    <ul className="space-y-2">
                        {data.attachments.map((att) => (
                            <li
                                key={att.id}
                                className="flex items-center justify-between bg-gray-50 rounded p-2 text-sm"
                            >
                                <div className="flex items-center gap-2">
                                    <PaperClipIcon className="h-4 w-4 text-gray-400" />
                                    <span>{att.original_filename}</span>
                                    <span className="text-xs text-gray-500">
                                        ({att.size_human})
                                    </span>
                                </div>
                                <a
                                    href={route('stock-adjustments.attachments.download', [
                                        data.id,
                                        att.id,
                                    ])}
                                    className="text-indigo-600 hover:text-indigo-800"
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <ArrowDownTrayIcon className="h-4 w-4" />
                                </a>
                            </li>
                        ))}
                    </ul>
                </StandardModal.Section>
            )}

            {historyItems.length > 0 && (
                <StandardModal.Section title="Histórico de Status">
                    <StandardModal.Timeline items={historyItems} />
                </StandardModal.Section>
            )}
        </StandardModal>
    );
}

/* ============================================================================
 * Modal: Transição de Status
 * ========================================================================= */
function TransitionModal({ data, onClose }) {
    const form = useForm({
        new_status: '',
        notes: '',
    });

    const transitions = data.allowed_transitions || {};

    const handleSubmit = () => {
        form.post(route('stock-adjustments.transition', data.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    };

    return (
        <StandardModal
            show={true}
            onClose={onClose}
            title={`Alterar status do ajuste #${data.id}`}
            subtitle={`Status atual: ${data.status_label}`}
            headerColor="bg-amber-600"
            headerIcon={<ArrowPathIcon className="h-5 w-5" />}
            maxWidth="md"
            onSubmit={handleSubmit}
            footer={
                <StandardModal.Footer
                    onCancel={onClose}
                    onSubmit="submit"
                    submitLabel="Atualizar Status"
                    processing={form.processing}
                />
            }
        >
            <div className="space-y-4">
                <div>
                    <InputLabel value="Novo status *" />
                    <select
                        value={form.data.new_status}
                        onChange={(e) => form.setData('new_status', e.target.value)}
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                        required
                    >
                        <option value="">Selecione o status</option>
                        {Object.entries(transitions).map(([key, label]) => (
                            <option key={key} value={key}>
                                {label}
                            </option>
                        ))}
                    </select>
                    {Object.keys(transitions).length === 0 && (
                        <p className="mt-1 text-xs text-amber-600">
                            Nenhuma transição disponível a partir deste status.
                        </p>
                    )}
                </div>
                <div>
                    <InputLabel value="Notas (opcional)" />
                    <textarea
                        value={form.data.notes}
                        onChange={(e) => form.setData('notes', e.target.value)}
                        rows={3}
                        placeholder="Justificativa ou observações sobre a mudança"
                        className="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                    />
                </div>
            </div>
        </StandardModal>
    );
}

/* ============================================================================
 * Botão de transição em lote
 * ========================================================================= */
function BulkTransitionButton({ ids, onDone }) {
    const [showSelect, setShowSelect] = useState(false);
    const [status, setStatus] = useState('');
    const [notes, setNotes] = useState('');
    const [processing, setProcessing] = useState(false);

    const submit = () => {
        if (!status) return;
        setProcessing(true);
        router.post(
            route('stock-adjustments.bulk-transition'),
            { ids, new_status: status, notes },
            {
                preserveScroll: true,
                onFinish: () => {
                    setProcessing(false);
                    setShowSelect(false);
                    setStatus('');
                    setNotes('');
                    onDone?.();
                },
            },
        );
    };

    if (!showSelect) {
        return (
            <Button
                variant="primary"
                size="sm"
                onClick={() => setShowSelect(true)}
                icon={ArrowPathIcon}
            >
                Alterar Status em Lote
            </Button>
        );
    }

    return (
        <div className="flex items-center gap-2">
            <select
                value={status}
                onChange={(e) => setStatus(e.target.value)}
                className="rounded-md border-gray-300 shadow-sm text-sm"
            >
                <option value="">Novo status...</option>
                <option value="under_analysis">Em Análise</option>
                <option value="awaiting_response">Aguardando Resposta</option>
                <option value="adjusted">Ajustado</option>
                <option value="no_adjustment">Sem Ajuste</option>
                <option value="cancelled">Cancelado</option>
            </select>
            <input
                type="text"
                placeholder="Notas..."
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                className="rounded-md border-gray-300 shadow-sm text-sm w-48"
            />
            <Button
                variant="primary"
                size="sm"
                onClick={submit}
                disabled={!status || processing}
                loading={processing}
            >
                Aplicar
            </Button>
            <Button
                variant="outline"
                size="sm"
                onClick={() => setShowSelect(false)}
                icon={XMarkIcon}
            />
        </div>
    );
}
