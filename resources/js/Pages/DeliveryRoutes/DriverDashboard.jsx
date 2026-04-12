import { Head, router } from '@inertiajs/react';
import { useState, useMemo, useEffect, useRef } from 'react';
import {
    TruckIcon,
    MapPinIcon,
    PhoneIcon,
    CheckCircleIcon,
    ArrowPathIcon,
    ArrowLeftIcon,
    MapIcon,
} from '@heroicons/react/24/outline';
import { MapContainer, TileLayer, Marker, Polyline, Popup, useMap } from 'react-leaflet';
import L from 'leaflet';
import { toast, ToastContainer } from 'react-toastify';
import 'react-toastify/dist/ReactToastify.css';
import Button from '@/Components/Button';
import StatusBadge from '@/Components/Shared/StatusBadge';
import TextInput from '@/Components/TextInput';

function FitBounds({ points }) {
    const map = useMap();
    const fitted = useRef(false);
    useEffect(() => {
        if (points.length > 0 && !fitted.current) {
            fitted.current = true;
            setTimeout(() => {
                map.invalidateSize();
                const bounds = L.latLngBounds(points.map(p => [p.lat, p.lng]));
                map.fitBounds(bounds, { padding: [30, 30] });
            }, 200);
        }
    }, [points, map]);
    return null;
}

function makeMarkerIcon(sequence, color) {
    return L.divIcon({
        className: '',
        html: `<div style="background:${color};color:white;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:bold;box-shadow:0 2px 6px rgba(0,0,0,0.3);border:2px solid white">${sequence}</div>`,
        iconSize: [28, 28],
        iconAnchor: [14, 14],
    });
}

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

function DriverDashboard({ route: routeData, items: initialItems, history: initialHistory, driverName, returnReasons = [] }) {
    const [items, setItems] = useState(initialItems || []);
    const [completing, setCompleting] = useState({});
    const [receivedBy, setReceivedBy] = useState({});
    const [returnReasonId, setReturnReasonId] = useState({});
    const [showReturnConfirm, setShowReturnConfirm] = useState(null);
    const [showHistory, setShowHistory] = useState(false);
    const [showMap, setShowMap] = useState(false);
    const history = initialHistory || [];

    // A4: Sync local state when Inertia reloads props
    useEffect(() => { setItems(initialItems || []); }, [initialItems]);

    // A4: Auto-refresh every 45s when there's an active route
    useEffect(() => {
        if (!routeData) return;
        const interval = setInterval(() => {
            router.reload({ only: ['route', 'items', 'history'], preserveState: true });
        }, 45000);
        return () => clearInterval(interval);
    }, [routeData?.id]);

    // D6: GPS location reporting when route is in_route
    useEffect(() => {
        if (!routeData || routeData.status !== 'in_route') return;
        if (!navigator.geolocation) return;

        const sendLocation = (position) => {
            fetch(route('driver-location.store'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                },
                body: JSON.stringify({
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                    speed: position.coords.speed ? position.coords.speed * 3.6 : null,
                    heading: position.coords.heading,
                    accuracy: position.coords.accuracy,
                }),
            }).catch(() => {});
        };

        navigator.geolocation.getCurrentPosition(sendLocation, () => {}, { enableHighAccuracy: true });

        const watchId = navigator.geolocation.watchPosition(sendLocation, () => {}, {
            enableHighAccuracy: true,
            maximumAge: 30000,
        });

        const intervalId = setInterval(() => {
            navigator.geolocation.getCurrentPosition(sendLocation, () => {}, { enableHighAccuracy: true });
        }, 30000);

        return () => {
            navigator.geolocation.clearWatch(watchId);
            clearInterval(intervalId);
        };
    }, [routeData?.status]);

    const geoItems = useMemo(() => items.filter(i => i.lat && i.lng), [items]);

    // Pendentes primeiro (ordem de sequência), entregues depois
    const sortedItems = useMemo(() =>
        [...items].sort((a, b) => a.is_delivered === b.is_delivered ? a.sequence - b.sequence : a.is_delivered ? 1 : -1),
    [items]);

    const deliveredCount = items.filter(i => i.is_delivered).length;
    const totalCount = items.length;
    const progressPercent = totalCount > 0 ? Math.round((deliveredCount / totalCount) * 100) : 0;

    const today = new Date().toLocaleDateString('pt-BR', { weekday: 'long', day: 'numeric', month: 'long' });

    const handleComplete = async (itemId, status, extraData = {}) => {
        if (!routeData) return;
        setCompleting(prev => ({ ...prev, [itemId]: true }));

        try {
            const response = await fetch(
                route('driver-routes.complete-item', { deliveryRoute: routeData.id, item: itemId }),
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({
                        status,
                        received_by: receivedBy[itemId] || null,
                        ...extraData,
                    }),
                }
            );

            if (response.ok) {
                setItems(prev => prev.map(i =>
                    i.id === itemId
                        ? { ...i, is_delivered: true, delivery_status: status, delivery_status_label: status === 'delivered' ? 'Entregue' : 'Devolvido', delivered_at: new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) }
                        : i
                ));
                toast.success(status === 'delivered' ? 'Entrega confirmada!' : 'Devolução registrada.');
            } else {
                const data = await response.json().catch(() => ({}));
                toast.error(data.error || 'Erro ao processar a entrega.');
            }
        } catch {
            toast.error('Erro de conexão. Tente novamente.');
        } finally {
            setCompleting(prev => ({ ...prev, [itemId]: false }));
        }
    };

    const mapsUrl = (address) => `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(address)}`;

    return (
        <>
            <Head title="Painel do Motorista" />
            <ToastContainer position="top-center" autoClose={4000} hideProgressBar newestOnTop />

            <div className="min-h-screen bg-gray-50">
                {/* Header */}
                <div className="bg-indigo-600 text-white px-4 py-5 sm:px-6">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <a href={route('dashboard')}
                                className="p-1.5 rounded-lg bg-white/10 hover:bg-white/20 transition-colors inline-flex">
                                <ArrowLeftIcon className="w-5 h-5" />
                            </a>
                            <div>
                                <h1 className="text-lg font-bold">{driverName}</h1>
                                <p className="text-sm text-indigo-200 capitalize">{today}</p>
                            </div>
                        </div>
                        <TruckIcon className="w-8 h-8 text-indigo-300" />
                    </div>
                </div>

                <div className="px-4 py-4 sm:px-6 max-w-2xl mx-auto">
                    {!routeData ? (
                        /* Sem rota */
                        <div className="text-center py-16">
                            <TruckIcon className="w-16 h-16 text-gray-300 mx-auto mb-4" />
                            <h2 className="text-lg font-semibold text-gray-600">Nenhuma rota para hoje</h2>
                            <p className="text-gray-400 mt-1">Aguarde a atribuição de uma rota.</p>
                        </div>
                    ) : (
                        <>
                            {/* Route Card */}
                            <div className="bg-white rounded-xl shadow-sm p-4 mb-4">
                                <div className="flex items-center justify-between mb-3">
                                    <div>
                                        <span className="text-xs text-gray-500">Rota</span>
                                        <h2 className="text-lg font-bold text-gray-900">{routeData.route_number}</h2>
                                    </div>
                                    <StatusBadge variant={routeData.status === 'in_route' ? 'info' : routeData.status === 'completed' ? 'success' : 'warning'}>
                                        {routeData.status_label}
                                    </StatusBadge>
                                </div>
                                {/* Progress */}
                                <div className="flex items-center justify-between text-xs text-gray-500 mb-1">
                                    <span>Progresso</span>
                                    <span className="font-medium">{deliveredCount}/{totalCount} entregas</span>
                                </div>
                                <div className="w-full bg-gray-200 rounded-full h-2.5">
                                    <div className={`h-2.5 rounded-full transition-all duration-500 ${progressPercent === 100 ? 'bg-green-500' : 'bg-indigo-500'}`}
                                        style={{ width: `${progressPercent}%` }} />
                                </div>
                            </div>

                            {/* Map Toggle + Map */}
                            {geoItems.length >= 2 && (
                                <div className="mb-4">
                                    <button onClick={() => setShowMap(!showMap)}
                                        className="w-full bg-white rounded-xl shadow-sm p-3 flex items-center justify-center gap-2 text-sm font-medium text-indigo-600 hover:bg-indigo-50 transition-colors">
                                        <MapIcon className="w-5 h-5" />
                                        {showMap ? 'Ocultar Mapa' : 'Ver Mapa da Rota'}
                                    </button>
                                    {showMap && (
                                        <div className="mt-2 rounded-xl overflow-hidden shadow-sm border border-gray-200" style={{ height: '280px' }}>
                                            <MapContainer center={[geoItems[0].lat, geoItems[0].lng]} zoom={12}
                                                style={{ height: '100%', width: '100%' }} scrollWheelZoom={false} dragging={true} zoomControl={false}>
                                                <TileLayer url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                                                    attribution='&copy; OpenStreetMap' />
                                                <FitBounds points={geoItems} />
                                                {geoItems.map((item) => {
                                                    const color = item.is_delivered
                                                        ? (item.delivery_status === 'delivered' ? '#22c55e' : '#f97316')
                                                        : '#4f46e5';
                                                    return (
                                                        <Marker key={item.id} position={[item.lat, item.lng]}
                                                            icon={makeMarkerIcon(item.sequence, color)}>
                                                            <Popup>
                                                                <strong>{item.sequence}.</strong> {item.client_name}
                                                                <br /><span style={{ fontSize: '11px' }}>{item.address}</span>
                                                            </Popup>
                                                        </Marker>
                                                    );
                                                })}
                                                <Polyline
                                                    positions={geoItems.map(i => [i.lat, i.lng])}
                                                    color="#6366f1" weight={3} opacity={0.6} dashArray="8,6" />
                                            </MapContainer>
                                        </div>
                                    )}
                                </div>
                            )}

                            {/* Delivery Items */}
                            <div className="space-y-3">
                                {sortedItems.map(item => (
                                    <div key={item.id} className={`bg-white rounded-xl shadow-sm overflow-hidden border-l-4 ${
                                        item.is_delivered
                                            ? item.delivery_status === 'delivered' ? 'border-green-500' : 'border-orange-500'
                                            : 'border-indigo-500'
                                    }`}>
                                        <div className="p-4">
                                            {/* Header */}
                                            <div className="flex items-start justify-between mb-2">
                                                <div className="flex-1">
                                                    <div className="flex items-center gap-2">
                                                        <span className="text-xs font-bold text-gray-400">{item.sequence}.</span>
                                                        <h3 className="text-base font-semibold text-gray-900">{item.client_name}</h3>
                                                    </div>
                                                    {item.address && (
                                                        <p className="text-sm text-gray-500 mt-0.5 flex items-start gap-1">
                                                            <MapPinIcon className="w-4 h-4 flex-shrink-0 mt-0.5" />
                                                            {item.address}
                                                        </p>
                                                    )}
                                                </div>
                                                {item.is_delivered && (
                                                    <StatusBadge variant={item.delivery_status === 'delivered' ? 'success' : 'orange'}>
                                                        {item.delivery_status_label}
                                                    </StatusBadge>
                                                )}
                                            </div>

                                            {/* Info badges */}
                                            <div className="flex flex-wrap items-center gap-2 mb-3">
                                                {item.contact_phone && (
                                                    <a href={`tel:${item.contact_phone}`} className="inline-flex items-center gap-1 text-xs text-blue-600 bg-blue-50 px-2 py-1 rounded-md">
                                                        <PhoneIcon className="w-3.5 h-3.5" /> {item.contact_phone}
                                                    </a>
                                                )}
                                                {item.needs_card_machine && <StatusBadge variant="warning">Maquininha</StatusBadge>}
                                                {item.is_exchange && <StatusBadge variant="info">Troca</StatusBadge>}
                                                {item.is_gift && <StatusBadge variant="purple">Presente</StatusBadge>}
                                                {item.sale_value && (
                                                    <span className="text-xs text-gray-500">R$ {Number(item.sale_value).toFixed(2).replace('.', ',')}</span>
                                                )}
                                            </div>

                                            {item.is_delivered ? (
                                                /* Completed info */
                                                <div className="text-xs text-gray-500">
                                                    {item.delivered_at && <span>Às {item.delivered_at}</span>}
                                                    {item.received_by && <span> · Recebido por: {item.received_by}</span>}
                                                </div>
                                            ) : (
                                                /* Action area */
                                                <>
                                                    {/* Maps link */}
                                                    {item.address && (
                                                        <a href={mapsUrl(item.address)} target="_blank" rel="noopener"
                                                            className="block w-full text-center py-2 mb-3 rounded-lg border border-blue-200 text-sm text-blue-600 bg-blue-50">
                                                            <MapPinIcon className="w-4 h-4 inline mr-1" /> Abrir no Maps
                                                        </a>
                                                    )}

                                                    {/* Received by */}
                                                    <div className="mb-3">
                                                        <TextInput className="block w-full text-sm"
                                                            placeholder="Recebido por (nome)"
                                                            value={receivedBy[item.id] || ''}
                                                            onChange={e => setReceivedBy(prev => ({ ...prev, [item.id]: e.target.value }))} />
                                                    </div>

                                                    {/* Action buttons */}
                                                    {showReturnConfirm === item.id ? (
                                                        <div className="space-y-2">
                                                            <select
                                                                className="block w-full text-sm border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                                                                value={returnReasonId[item.id] || ''}
                                                                onChange={e => setReturnReasonId(prev => ({ ...prev, [item.id]: e.target.value }))}
                                                            >
                                                                <option value="">Selecione o motivo...</option>
                                                                {returnReasons.map(r => (
                                                                    <option key={r.id} value={r.id}>{r.code} - {r.name}</option>
                                                                ))}
                                                            </select>
                                                            <div className="grid grid-cols-2 gap-2">
                                                                <Button variant="danger" className="w-full py-2" icon={ArrowPathIcon}
                                                                    loading={completing[item.id]}
                                                                    disabled={!returnReasonId[item.id]}
                                                                    onClick={() => {
                                                                        handleComplete(item.id, 'returned', { return_reason_id: returnReasonId[item.id] });
                                                                        setShowReturnConfirm(null);
                                                                    }}>
                                                                    Confirmar
                                                                </Button>
                                                                <Button variant="light" className="w-full py-2"
                                                                    onClick={() => setShowReturnConfirm(null)}>
                                                                    Cancelar
                                                                </Button>
                                                            </div>
                                                        </div>
                                                    ) : (
                                                        <div className="grid grid-cols-2 gap-2">
                                                            <Button variant="success" className="w-full py-3" icon={CheckCircleIcon}
                                                                loading={completing[item.id]}
                                                                onClick={() => handleComplete(item.id, 'delivered')}>
                                                                Entregue
                                                            </Button>
                                                            <Button variant="danger" className="w-full py-3" icon={ArrowPathIcon}
                                                                loading={completing[item.id]}
                                                                onClick={() => setShowReturnConfirm(item.id)}>
                                                                Devolver
                                                            </Button>
                                                        </div>
                                                    )}
                                                </>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>

                            {/* Summary footer */}
                            {progressPercent === 100 && (
                                <div className="mt-6 text-center py-6 bg-green-50 rounded-xl">
                                    <CheckCircleIcon className="w-12 h-12 text-green-500 mx-auto mb-2" />
                                    <h3 className="text-lg font-bold text-green-700">Rota concluída!</h3>
                                    <p className="text-sm text-green-600">Todas as {totalCount} entregas foram processadas.</p>
                                </div>
                            )}
                        </>
                    )}

                    {/* Link para Minhas Entregas */}
                    <div className="mt-6">
                        <a href={route('my-deliveries.index')}
                            className="block w-full bg-white rounded-xl shadow-sm p-4 text-center text-sm font-medium text-indigo-600 hover:bg-indigo-50 transition-colors">
                            Ver todas as minhas entregas e rotas
                        </a>
                    </div>
                </div>
            </div>
        </>
    );
}

// Layout standalone (sem AuthenticatedLayout)
DriverDashboard.layout = (page) => page;

export default DriverDashboard;
