import { Head } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
    UserGroupIcon,
    ClockIcon,
    PlayIcon,
    PauseIcon,
    StopIcon,
    ArrowRightOnRectangleIcon,
    ArrowLeftOnRectangleIcon,
    ArrowUturnLeftIcon,
    ArrowsPointingOutIcon,
    ArrowsPointingInIcon,
    ArrowPathIcon,
    BuildingStorefrontIcon,
} from '@heroicons/react/24/outline';
import {
    DndContext,
    PointerSensor,
    TouchSensor,
    KeyboardSensor,
    useSensor,
    useSensors,
    useDroppable,
    closestCenter,
    DragOverlay,
} from '@dnd-kit/core';
import { SortableContext, verticalListSortingStrategy, useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import EmployeeAvatar from '@/Components/EmployeeAvatar';
import SelectOutcomeModal from './Partials/SelectOutcomeModal';
import SelectBreakTypeModal from './Partials/SelectBreakTypeModal';

// ───────────────────────────────────────────────────────────────────
// Definições dos painéis
// ───────────────────────────────────────────────────────────────────
const PANEL_DEFS = {
    queue:     { key: 'queue',     title: 'Na Fila',     color: 'bg-amber-50 border-amber-300',   headerColor: 'bg-amber-600' },
    attending: { key: 'attending', title: 'Atendendo',   color: 'bg-blue-50 border-blue-300',     headerColor: 'bg-blue-600' },
    on_break:  { key: 'on_break',  title: 'Em Pausa',    color: 'bg-purple-50 border-purple-300', headerColor: 'bg-purple-600' },
    available: { key: 'available', title: 'Disponível',  color: 'bg-gray-100 border-gray-300',    headerColor: 'bg-gray-700' },
};

// Mapa de transições permitidas: origem → destinos válidos via drag
const VALID_TRANSITIONS = {
    available: ['queue'],
    queue: ['available', 'attending', 'on_break'],
    attending: ['queue'],
    on_break: ['queue'],
};

// ───────────────────────────────────────────────────────────────────
// Página
// ───────────────────────────────────────────────────────────────────
export default function Index({
    storeCode: initialStoreCode,
    isStoreScoped,
    stores = [],
    breakTypes = [],
    outcomes = [],
    storeSetting,
    permissions = {},
}) {
    const [storeCode, setStoreCode] = useState(initialStoreCode);
    const [board, setBoard] = useState(null);
    const [loadError, setLoadError] = useState(null);
    const [isFullscreen, setIsFullscreen] = useState(false);
    const [activeDrag, setActiveDrag] = useState(null);
    const [tickNow, setTickNow] = useState(Date.now());
    const containerRef = useRef(null);

    // Modais
    const [outcomeModal, setOutcomeModal] = useState({ open: false, attendance: null });
    const [breakModal, setBreakModal] = useState({ open: false, employeeId: null });

    // ──────────────────────────────────────────────────────
    // Fetch board (silencioso — não passa pelo Inertia)
    // ──────────────────────────────────────────────────────
    const fetchBoard = useCallback(async () => {
        if (!storeCode) return;
        try {
            const res = await fetch(`${route('turn-list.board')}?store=${encodeURIComponent(storeCode)}`, {
                headers: { 'Accept': 'application/json' },
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const json = await res.json();
            setBoard(json.board);
            setLoadError(null);
        } catch (err) {
            console.error('Erro ao carregar board:', err);
            setLoadError('Falha ao atualizar painel. Verificando novamente em 30s.');
        }
    }, [storeCode]);

    // Initial + polling 30s
    useEffect(() => {
        fetchBoard();
        const id = setInterval(fetchBoard, 30000);
        return () => clearInterval(id);
    }, [fetchBoard]);

    // Tick 1Hz pra timers realtime
    useEffect(() => {
        const id = setInterval(() => setTickNow(Date.now()), 1000);
        return () => clearInterval(id);
    }, []);

    // Fullscreen API
    const toggleFullscreen = useCallback(() => {
        const el = containerRef.current;
        if (!document.fullscreenElement) {
            (el?.requestFullscreen || el?.webkitRequestFullscreen)?.call(el);
        } else {
            (document.exitFullscreen || document.webkitExitFullscreen)?.call(document);
        }
    }, []);

    useEffect(() => {
        const handler = () => setIsFullscreen(Boolean(document.fullscreenElement));
        document.addEventListener('fullscreenchange', handler);
        document.addEventListener('webkitfullscreenchange', handler);
        return () => {
            document.removeEventListener('fullscreenchange', handler);
            document.removeEventListener('webkitfullscreenchange', handler);
        };
    }, []);

    // ──────────────────────────────────────────────────────
    // Drag and Drop
    // ──────────────────────────────────────────────────────
    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 8 } }),
        useSensor(TouchSensor, { activationConstraint: { delay: 200, tolerance: 8 } }),
        useSensor(KeyboardSensor),
    );

    const handleDragStart = (event) => {
        const data = event.active.data.current;
        setActiveDrag(data);
    };

    const handleDragEnd = (event) => {
        const { active, over } = event;
        setActiveDrag(null);

        if (!over) return;

        const fromPanel = active.data.current?.panel;
        // over.data.current?.panel quando solta sobre um card; over.id começa com `panel:` quando solta na zona do painel
        const toPanel = over.data.current?.panel
            ?? (typeof over.id === 'string' && over.id.startsWith('panel:') ? over.id.slice('panel:'.length) : null);
        const employeeId = active.data.current?.employee_id;

        if (!fromPanel || !toPanel || !employeeId) return;

        // Reorder dentro da fila
        if (fromPanel === toPanel && fromPanel === 'queue') {
            const overEmployeeId = over.data.current?.employee_id;
            if (!overEmployeeId || overEmployeeId === employeeId) return;
            const overPosition = over.data.current?.position;
            if (!overPosition) return;
            doReorder(employeeId, overPosition);
            return;
        }

        // Cross-panel — valida transição
        if (fromPanel !== toPanel && VALID_TRANSITIONS[fromPanel]?.includes(toPanel)) {
            handlePanelTransition(employeeId, fromPanel, toPanel, active.data.current);
        }
    };

    // ──────────────────────────────────────────────────────
    // Ações de transição (axios — sem reload via Inertia)
    //
    // O backend devolve 204 No Content para XHR; após o POST chamamos
    // fetchBoard() pra atualizar o estado silenciosamente. Erros 422
    // (validação) retornam JSON e tbm disparam refetch pra refletir o
    // estado real da loja.
    // ──────────────────────────────────────────────────────
    const apiPost = useCallback(async (url, data = {}) => {
        try {
            await window.axios.post(url, data);
        } catch (err) {
            const status = err?.response?.status;
            const errors = err?.response?.data?.errors;
            if (status === 422 && errors) {
                const firstField = Object.keys(errors)[0];
                const msg = errors[firstField];
                setLoadError(Array.isArray(msg) ? msg[0] : String(msg));
            } else if (status && status !== 204) {
                console.error('Falha na ação:', err);
                setLoadError('Falha ao executar a ação. Atualizando painel.');
            }
        } finally {
            await fetchBoard();
        }
    }, [fetchBoard]);

    const handlePanelTransition = (employeeId, from, to, payload) => {
        if (from === 'available' && to === 'queue') {
            apiPost(route('turn-list.queue.enter'), { employee_id: employeeId, store_code: storeCode });
        } else if (from === 'queue' && to === 'available') {
            apiPost(route('turn-list.queue.leave'), { employee_id: employeeId, store_code: storeCode });
        } else if (from === 'queue' && to === 'attending') {
            apiPost(route('turn-list.attendances.start'), { employee_id: employeeId, store_code: storeCode });
        } else if (from === 'queue' && to === 'on_break') {
            setBreakModal({ open: true, employeeId });
        } else if (from === 'attending' && to === 'queue') {
            setOutcomeModal({ open: true, attendance: payload });
        } else if (from === 'on_break' && to === 'queue') {
            const breakId = payload?.break_id;
            if (breakId) {
                apiPost(route('turn-list.breaks.finish', breakId), {});
            }
        }
    };

    const doReorder = (employeeId, newPosition) => {
        apiPost(route('turn-list.queue.reorder'), {
            employee_id: employeeId,
            store_code: storeCode,
            new_position: newPosition,
        });
    };

    // ──────────────────────────────────────────────────────
    // Ações via botão (alternativa ao drag-and-drop)
    // ──────────────────────────────────────────────────────
    const cardActions = useMemo(() => ({
        enterQueue: (employeeId) =>
            apiPost(route('turn-list.queue.enter'), { employee_id: employeeId, store_code: storeCode }),
        leaveQueue: (employeeId) =>
            apiPost(route('turn-list.queue.leave'), { employee_id: employeeId, store_code: storeCode }),
        startAttendance: (employeeId) =>
            apiPost(route('turn-list.attendances.start'), { employee_id: employeeId, store_code: storeCode }),
        openBreakModal: (employeeId) => setBreakModal({ open: true, employeeId }),
        finishAttendance: (item) => setOutcomeModal({ open: true, attendance: item }),
        finishBreak: (breakId) => apiPost(route('turn-list.breaks.finish', breakId), {}),
    }), [storeCode, apiPost]);

    const onConfirmOutcome = ({ outcomeId, returnToQueue, notes }) => {
        const att = outcomeModal.attendance;
        if (!att?.attendance_ulid) return;
        apiPost(route('turn-list.attendances.finish', att.attendance_ulid), {
            outcome_id: outcomeId,
            return_to_queue: returnToQueue,
            notes,
        });
        setOutcomeModal({ open: false, attendance: null });
    };

    const onConfirmBreakType = ({ breakTypeId }) => {
        if (!breakModal.employeeId) return;
        apiPost(route('turn-list.breaks.start'), {
            employee_id: breakModal.employeeId,
            store_code: storeCode,
            break_type_id: breakTypeId,
        });
        setBreakModal({ open: false, employeeId: null });
    };

    const onChangeStore = (newCode) => {
        setStoreCode(newCode);
        const url = new URL(window.location.href);
        url.searchParams.set('store', newCode);
        window.history.replaceState({}, '', url.toString());
    };

    const onBreakItemsCount = board?.on_break?.length ?? 0;
    const showOnBreakPanel = onBreakItemsCount > 0;

    // ──────────────────────────────────────────────────────
    // Render
    // ──────────────────────────────────────────────────────
    return (
        <>
            <Head title="Lista da Vez" />

            <div ref={containerRef} className={`${isFullscreen ? 'fixed inset-0 z-50 overflow-auto bg-white' : ''}`}>
                <div className="py-4 sm:py-6 lg:py-8">
                    <div className="max-w-full mx-auto px-3 sm:px-6 lg:px-8">
                        {/* Header */}
                        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <div className="flex items-center gap-3">
                                <UserGroupIcon className="h-7 w-7 text-indigo-600 shrink-0" />
                                <div>
                                    <h1 className="text-xl sm:text-2xl font-bold text-gray-900">Lista da Vez</h1>
                                    <p className="text-xs sm:text-sm text-gray-500">
                                        {board ? (
                                            <>
                                                {board.counts.available} disp. · {board.counts.queue} fila · {board.counts.attending} atend. · {board.counts.on_break} pausa
                                            </>
                                        ) : 'Carregando…'}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                {/* Store selector — só quem tem MANAGE_TURN_LIST */}
                                {permissions.manage && stores.length > 1 && (
                                    <div className="flex items-center gap-1.5 text-sm">
                                        <BuildingStorefrontIcon className="h-4 w-4 text-gray-400" />
                                        <select
                                            value={storeCode ?? ''}
                                            onChange={(e) => onChangeStore(e.target.value)}
                                            className="rounded-md border-gray-300 text-sm py-1"
                                        >
                                            {stores.map((s) => (
                                                <option key={s.code} value={s.code}>{s.code} — {s.name}</option>
                                            ))}
                                        </select>
                                    </div>
                                )}
                                {/* Refresh manual */}
                                <button
                                    type="button"
                                    onClick={fetchBoard}
                                    className="inline-flex items-center gap-1 text-xs sm:text-sm text-gray-600 hover:text-gray-900 px-2 py-1.5 rounded-md hover:bg-gray-100"
                                    title="Atualizar"
                                >
                                    <ArrowPathIcon className="h-4 w-4" />
                                    <span className="hidden sm:inline">Atualizar</span>
                                </button>
                                {/* Fullscreen toggle */}
                                <button
                                    type="button"
                                    onClick={toggleFullscreen}
                                    className="inline-flex items-center gap-1 text-xs sm:text-sm text-white bg-indigo-600 hover:bg-indigo-700 px-3 py-1.5 rounded-md min-h-[44px]"
                                    title={isFullscreen ? 'Sair de tela cheia' : 'Tela cheia'}
                                >
                                    {isFullscreen ? <ArrowsPointingInIcon className="h-5 w-5" /> : <ArrowsPointingOutIcon className="h-5 w-5" />}
                                    <span className="hidden sm:inline">{isFullscreen ? 'Sair' : 'Tela cheia'}</span>
                                </button>
                            </div>
                        </div>

                        {loadError && (
                            <div className="mb-4 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-800">
                                {loadError}
                            </div>
                        )}

                        {/*
                          Layout:
                            • Topo (cols 2): Na Fila + Atendendo
                            • Meio (cols 1, condicional): Em Pausa — só se tiver alguém
                            • Base (cols 1): Disponível
                        */}
                        <DndContext
                            sensors={sensors}
                            collisionDetection={closestCenter}
                            onDragStart={handleDragStart}
                            onDragEnd={handleDragEnd}
                        >
                            <div className="space-y-3 sm:space-y-4">
                                {/* Topo: 2 painéis principais */}
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                                    <Panel
                                        panel={PANEL_DEFS.queue}
                                        items={board?.queue ?? []}
                                        tickNow={tickNow}
                                        actions={cardActions}
                                    />
                                    <Panel
                                        panel={PANEL_DEFS.attending}
                                        items={board?.attending ?? []}
                                        tickNow={tickNow}
                                        actions={cardActions}
                                    />
                                </div>

                                {/* Em Pausa — condicional */}
                                {showOnBreakPanel && (
                                    <Panel
                                        panel={PANEL_DEFS.on_break}
                                        items={board?.on_break ?? []}
                                        tickNow={tickNow}
                                        actions={cardActions}
                                        horizontal
                                    />
                                )}

                                {/* Disponível — sempre visível, embaixo */}
                                <Panel
                                    panel={PANEL_DEFS.available}
                                    items={board?.available ?? []}
                                    tickNow={tickNow}
                                    actions={cardActions}
                                    horizontal
                                />
                            </div>
                            <DragOverlay>
                                {activeDrag ? <CardOverlay item={activeDrag} /> : null}
                            </DragOverlay>
                        </DndContext>
                    </div>
                </div>
            </div>

            {/* Modais */}
            <SelectOutcomeModal
                show={outcomeModal.open}
                onClose={() => setOutcomeModal({ open: false, attendance: null })}
                attendance={outcomeModal.attendance}
                outcomes={outcomes}
                onConfirm={onConfirmOutcome}
            />
            <SelectBreakTypeModal
                show={breakModal.open}
                onClose={() => setBreakModal({ open: false, employeeId: null })}
                breakTypes={breakTypes}
                onConfirm={onConfirmBreakType}
            />
        </>
    );
}

// ───────────────────────────────────────────────────────────────────
// Painel — droppable com lista de cards
// `horizontal` exibe cards em grid wrap (Em Pausa e Disponível ficam
// horizontais por ocuparem largura total da tela).
// ───────────────────────────────────────────────────────────────────
function Panel({ panel, items, tickNow, actions, horizontal = false }) {
    const ids = items.map((it) => `${panel.key}-${it.employee_id}`);
    const { setNodeRef, isOver } = useDroppable({
        id: `panel:${panel.key}`,
        data: { panel: panel.key },
    });

    const containerCls = horizontal
        ? 'flex-1 p-2 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-2 overflow-y-auto'
        : 'flex-1 p-2 space-y-2 overflow-y-auto';

    return (
        <div
            ref={setNodeRef}
            className={`rounded-lg border-2 ${panel.color} overflow-hidden flex flex-col ${horizontal ? 'min-h-[160px]' : 'min-h-[240px]'} ${isOver ? 'ring-2 ring-indigo-400' : ''}`}
        >
            <div className={`${panel.headerColor} text-white px-3 py-2 flex items-center justify-between sticky top-0`}>
                <h2 className="text-sm sm:text-base font-semibold uppercase tracking-wide">{panel.title}</h2>
                <span className="bg-white/20 rounded-full px-2 py-0.5 text-xs font-bold">
                    {items.length}
                </span>
            </div>
            <SortableContext items={ids} strategy={verticalListSortingStrategy}>
                <div className={containerCls} data-panel={panel.key}>
                    {items.length === 0 ? (
                        <div className="text-xs text-gray-400 text-center py-6 col-span-full">Vazio — arraste consultoras pra cá</div>
                    ) : (
                        items.map((item) => (
                            <Card
                                key={`${panel.key}-${item.employee_id}`}
                                panel={panel}
                                item={item}
                                tickNow={tickNow}
                                actions={actions}
                            />
                        ))
                    )}
                </div>
            </SortableContext>
        </div>
    );
}

// ───────────────────────────────────────────────────────────────────
// Card sortable de consultora — drag handle apenas na área de avatar+nome.
// Botões de ação ficam fora do handle pra não conflitar com o drag.
// ───────────────────────────────────────────────────────────────────
function Card({ panel, item, tickNow, actions }) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
        id: `${panel.key}-${item.employee_id}`,
        data: {
            panel: panel.key,
            employee_id: item.employee_id,
            employee_name: item.employee_name,
            employee_initials: item.employee_initials,
            position: item.position,
            attendance_id: item.attendance_id,
            attendance_ulid: item.attendance_ulid,
            break_id: item.break_id,
            break_type: item.break_type,
            started_at: item.started_at,
            original_queue_position: item.original_queue_position,
        },
    });

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.4 : 1,
    };

    return (
        <div
            ref={setNodeRef}
            style={style}
            className="bg-white rounded-lg border border-gray-200 shadow-sm flex flex-col select-none min-h-[88px]"
        >
            {/* Drag handle: avatar + nome */}
            <div
                {...attributes}
                {...listeners}
                className="p-3 flex items-center gap-3 cursor-grab active:cursor-grabbing touch-none"
            >
                <EmployeeAvatar
                    name={item.employee_name}
                    initials={item.employee_initials}
                    size="md"
                />
                <div className="flex-1 min-w-0">
                    <div className="font-medium text-sm text-gray-900 truncate">
                        {item.employee_short_name || item.employee_name}
                    </div>
                    <CardSubtitle panel={panel} item={item} tickNow={tickNow} />
                </div>
                {panel.key === 'queue' && (
                    <div className="bg-amber-100 text-amber-800 text-xs font-bold px-2 py-1 rounded-full shrink-0">
                        #{item.position}
                    </div>
                )}
            </div>

            {/* Botões de ação — fora do drag handle. */}
            <CardActions panel={panel} item={item} actions={actions} />
        </div>
    );
}

// ───────────────────────────────────────────────────────────────────
// Botões de ação por painel (alternativa ao drag-and-drop)
// `onPointerDown stopPropagation` previne que o gesto inicie um drag.
// ───────────────────────────────────────────────────────────────────
function CardActions({ panel, item, actions }) {
    const stop = (e) => e.stopPropagation();

    if (panel.key === 'available') {
        return (
            <div className="flex gap-1 p-2 pt-0">
                <ActionBtn
                    onClick={() => actions.enterQueue(item.employee_id)}
                    onPointerDown={stop}
                    color="bg-amber-600 hover:bg-amber-700"
                    icon={<ArrowRightOnRectangleIcon className="h-4 w-4" />}
                    label="Entrar na fila"
                />
            </div>
        );
    }

    if (panel.key === 'queue') {
        return (
            <div className="grid grid-cols-3 gap-1 p-2 pt-0">
                <ActionBtn
                    onClick={() => actions.startAttendance(item.employee_id)}
                    onPointerDown={stop}
                    color="bg-blue-600 hover:bg-blue-700"
                    icon={<PlayIcon className="h-4 w-4" />}
                    label="Atender"
                />
                <ActionBtn
                    onClick={() => actions.openBreakModal(item.employee_id)}
                    onPointerDown={stop}
                    color="bg-purple-600 hover:bg-purple-700"
                    icon={<PauseIcon className="h-4 w-4" />}
                    label="Pausar"
                />
                <ActionBtn
                    onClick={() => actions.leaveQueue(item.employee_id)}
                    onPointerDown={stop}
                    color="bg-gray-500 hover:bg-gray-600"
                    icon={<ArrowLeftOnRectangleIcon className="h-4 w-4" />}
                    label="Sair"
                />
            </div>
        );
    }

    if (panel.key === 'attending') {
        return (
            <div className="flex gap-1 p-2 pt-0">
                <ActionBtn
                    onClick={() => actions.finishAttendance(item)}
                    onPointerDown={stop}
                    color="bg-blue-600 hover:bg-blue-700"
                    icon={<StopIcon className="h-4 w-4" />}
                    label="Finalizar"
                />
            </div>
        );
    }

    if (panel.key === 'on_break') {
        return (
            <div className="flex gap-1 p-2 pt-0">
                <ActionBtn
                    onClick={() => actions.finishBreak(item.break_id)}
                    onPointerDown={stop}
                    color="bg-purple-600 hover:bg-purple-700"
                    icon={<ArrowUturnLeftIcon className="h-4 w-4" />}
                    label="Voltar à fila"
                />
            </div>
        );
    }

    return null;
}

function ActionBtn({ onClick, onPointerDown, color, icon, label }) {
    return (
        <button
            type="button"
            onClick={onClick}
            onPointerDown={onPointerDown}
            className={`${color} text-white rounded-md text-xs font-medium px-2 py-1.5 inline-flex items-center justify-center gap-1 min-h-[36px] active:scale-95 transition`}
            title={label}
        >
            {icon}
            <span className="truncate">{label}</span>
        </button>
    );
}

function CardOverlay({ item }) {
    return (
        <div className="bg-white rounded-lg border-2 border-indigo-400 shadow-lg p-3 flex items-center gap-3 min-h-[88px] cursor-grabbing">
            <EmployeeAvatar name={item.employee_name} initials={item.employee_initials} size="md" />
            <div className="flex-1 min-w-0">
                <div className="font-medium text-sm text-gray-900 truncate">{item.employee_name}</div>
            </div>
        </div>
    );
}

// ───────────────────────────────────────────────────────────────────
// Subtítulo dinâmico — depende do painel
// ───────────────────────────────────────────────────────────────────
function CardSubtitle({ panel, item, tickNow }) {
    if (panel.key === 'queue') {
        return (
            <div className="text-xs text-gray-500 flex items-center gap-1">
                <ClockIcon className="h-3 w-3" />
                {formatElapsed(elapsedFromIso(item.entered_at, tickNow))}
            </div>
        );
    }

    if (panel.key === 'attending') {
        return (
            <div className="text-xs text-blue-700 font-medium flex items-center gap-1">
                <PlayIcon className="h-3 w-3" />
                {formatElapsed(elapsedFromIso(item.started_at, tickNow))}
            </div>
        );
    }

    if (panel.key === 'on_break') {
        const elapsed = elapsedFromIso(item.started_at, tickNow);
        const elapsedMin = Math.floor(elapsed / 60);
        const max = item.break_type?.max_duration_minutes ?? 0;
        const exceeded = max > 0 && elapsedMin > max;
        return (
            <div className={`text-xs flex items-center gap-1 ${exceeded ? 'text-red-700 font-bold' : 'text-purple-700'}`}>
                <PauseIcon className="h-3 w-3" />
                {item.break_type?.name ?? 'Pausa'} · {formatElapsed(elapsed)}
                {max > 0 && (
                    <span className="text-gray-400">/ {max}min</span>
                )}
            </div>
        );
    }

    return <div className="text-xs text-gray-400">Disponível</div>;
}

// ───────────────────────────────────────────────────────────────────
// Helpers
// ───────────────────────────────────────────────────────────────────
function elapsedFromIso(iso, tickNow) {
    if (!iso) return 0;
    return Math.max(0, Math.floor((tickNow - new Date(iso).getTime()) / 1000));
}

function formatElapsed(seconds) {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    if (h > 0) {
        return `${h}h ${String(m).padStart(2, '0')}m`;
    }
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}
