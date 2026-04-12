import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    TruckIcon,
    MapPinIcon,
    PhoneIcon,
    CheckCircleIcon,
    ArrowPathIcon,
    ArrowLeftIcon,
} from '@heroicons/react/24/outline';
import Button from '@/Components/Button';
import StatusBadge from '@/Components/Shared/StatusBadge';
import TextInput from '@/Components/TextInput';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

function DriverDashboard({ route: routeData, items: initialItems, history: initialHistory, driverName }) {
    const [items, setItems] = useState(initialItems || []);
    const [completing, setCompleting] = useState({});
    const [receivedBy, setReceivedBy] = useState({});
    const [notification, setNotification] = useState(null);
    const [showHistory, setShowHistory] = useState(false);
    const history = initialHistory || [];

    const deliveredCount = items.filter(i => i.is_delivered).length;
    const totalCount = items.length;
    const progressPercent = totalCount > 0 ? Math.round((deliveredCount / totalCount) * 100) : 0;

    const today = new Date().toLocaleDateString('pt-BR', { weekday: 'long', day: 'numeric', month: 'long' });

    const showNotification = (message) => {
        setNotification(message);
        setTimeout(() => setNotification(null), 4000);
    };

    const handleComplete = async (itemId, status) => {
        if (!routeData) return;
        setCompleting(prev => ({ ...prev, [itemId]: true }));

        try {
            const response = await fetch(
                route('delivery-routes.complete-item', { deliveryRoute: routeData.id, item: itemId }),
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
                    }),
                }
            );

            if (response.ok) {
                setItems(prev => prev.map(i =>
                    i.id === itemId
                        ? { ...i, is_delivered: true, delivery_status: status, delivery_status_label: status === 'delivered' ? 'Entregue' : 'Devolvido', delivered_at: new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) }
                        : i
                ));
                showNotification(status === 'delivered' ? 'Entrega confirmada!' : 'Devolução registrada.');
            }
        } catch { /* ignore */ } finally {
            setCompleting(prev => ({ ...prev, [itemId]: false }));
        }
    };

    const mapsUrl = (address) => `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(address)}`;

    return (
        <>
            <Head title="Painel do Motorista" />

            {/* Notification */}
            {notification && (
                <div className="fixed top-4 left-1/2 -translate-x-1/2 z-50">
                    <div className="bg-green-600 text-white px-6 py-3 rounded-xl shadow-lg flex items-center gap-2">
                        <CheckCircleIcon className="w-5 h-5" />
                        <span className="text-sm font-medium">{notification}</span>
                    </div>
                </div>
            )}

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

                            {/* Delivery Items */}
                            <div className="space-y-3">
                                {items.map(item => (
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
                                                    <div className="grid grid-cols-2 gap-2">
                                                        <Button variant="success" className="w-full py-3" icon={CheckCircleIcon}
                                                            loading={completing[item.id]}
                                                            onClick={() => handleComplete(item.id, 'delivered')}>
                                                            Entregue
                                                        </Button>
                                                        <Button variant="danger" className="w-full py-3" icon={ArrowPathIcon}
                                                            loading={completing[item.id]}
                                                            onClick={() => handleComplete(item.id, 'returned')}>
                                                            Devolver
                                                        </Button>
                                                    </div>
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
