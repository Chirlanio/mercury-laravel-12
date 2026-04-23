import { useState, useEffect, useRef, useCallback } from 'react';
import { Link, usePage } from '@inertiajs/react';
import {
    BellIcon,
    ChatBubbleLeftRightIcon,
    CheckIcon,
} from '@heroicons/react/24/outline';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useTenant from '@/Hooks/useTenant';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

const POLL_INTERVAL_MS = 30000;

function Badge({ count }) {
    if (!count || count <= 0) return null;
    const label = count > 99 ? '99+' : String(count);
    return (
        <span className="absolute -top-1 -right-1 inline-flex items-center justify-center min-w-[1.125rem] h-[1.125rem] px-1 text-[10px] font-bold leading-none text-white bg-red-500 rounded-full ring-2 ring-white">
            {label}
        </span>
    );
}

function IconButton({ onClick, title, children, as = 'button', href = null }) {
    const baseClass = 'relative inline-flex items-center justify-center w-10 h-10 rounded-full text-gray-500 hover:text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors';

    if (as === 'link' && href) {
        return (
            <Link href={href} title={title} className={baseClass}>
                {children}
            </Link>
        );
    }

    return (
        <button type="button" onClick={onClick} title={title} className={baseClass}>
            {children}
        </button>
    );
}

function ChatBell() {
    const { hasPermission } = usePermissions();
    const { hasModule } = useTenant();
    const { url } = usePage();

    const canView = hasPermission(PERMISSIONS.VIEW_CHAT);
    const hasChat = hasModule('chat');
    const isOnChatPage = url?.startsWith('/chat');

    const [unread, setUnread] = useState(0);

    useEffect(() => {
        if (!canView || !hasChat) return;

        let cancelled = false;
        const fetchUnread = async () => {
            try {
                const res = await fetch(route('chat.unread-counts'), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) return;
                const data = await res.json();
                if (!cancelled) setUnread(data.total || 0);
            } catch { /* ignore */ }
        };

        fetchUnread();
        const id = setInterval(fetchUnread, POLL_INTERVAL_MS);
        return () => { cancelled = true; clearInterval(id); };
    }, [canView, hasChat]);

    if (!canView || !hasChat || isOnChatPage) return null;

    return (
        <IconButton as="link" href={route('chat.index')} title={unread > 0 ? `${unread} mensagem(ns) não lida(s)` : 'Mensagens'}>
            <ChatBubbleLeftRightIcon className="w-5 h-5" />
            <Badge count={unread} />
        </IconButton>
    );
}

function NotificationsBell() {
    const [open, setOpen] = useState(false);
    const [unread, setUnread] = useState(0);
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(false);
    const [markingAll, setMarkingAll] = useState(false);
    const dropdownRef = useRef(null);
    const buttonRef = useRef(null);

    const fetchUnread = useCallback(async () => {
        try {
            const res = await fetch(route('notifications.unread-count'), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) return;
            const data = await res.json();
            setUnread(data.count || 0);
        } catch { /* ignore */ }
    }, []);

    const fetchRecent = useCallback(async () => {
        setLoading(true);
        try {
            const res = await fetch(route('notifications.recent'), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) return;
            const data = await res.json();
            setItems(data.notifications || []);
            setUnread(data.unread_count || 0);
        } catch { /* ignore */ } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        fetchUnread();
        const id = setInterval(fetchUnread, POLL_INTERVAL_MS);
        return () => clearInterval(id);
    }, [fetchUnread]);

    useEffect(() => {
        if (open) fetchRecent();
    }, [open, fetchRecent]);

    useEffect(() => {
        if (!open) return;
        const handleClickOutside = (e) => {
            if (
                dropdownRef.current && !dropdownRef.current.contains(e.target) &&
                buttonRef.current && !buttonRef.current.contains(e.target)
            ) {
                setOpen(false);
            }
        };
        const handleEscape = (e) => { if (e.key === 'Escape') setOpen(false); };

        document.addEventListener('mousedown', handleClickOutside);
        document.addEventListener('keydown', handleEscape);
        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
            document.removeEventListener('keydown', handleEscape);
        };
    }, [open]);

    const markAsRead = async (id) => {
        // Optimista
        setItems((prev) => prev.map((n) => (n.id === id && !n.read_at ? { ...n, read_at: new Date().toISOString() } : n)));
        setUnread((prev) => Math.max(0, prev - 1));

        try {
            await fetch(route('notifications.mark-read', id), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
        } catch { /* ignore; próximo poll reconcilia */ }
    };

    const handleItemClick = (item) => {
        if (!item.read_at) markAsRead(item.id);
        if (item.url) window.location.href = item.url;
    };

    const markAllAsRead = async () => {
        if (markingAll || unread === 0) return;
        setMarkingAll(true);
        setItems((prev) => prev.map((n) => (n.read_at ? n : { ...n, read_at: new Date().toISOString() })));
        setUnread(0);

        try {
            await fetch(route('notifications.mark-all-read'), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
        } catch { /* ignore */ } finally {
            setMarkingAll(false);
        }
    };

    return (
        <div className="relative">
            <div ref={buttonRef}>
                <IconButton
                    onClick={() => setOpen((v) => !v)}
                    title={unread > 0 ? `${unread} notificação(ões) não lida(s)` : 'Notificações'}
                >
                    <BellIcon className="w-5 h-5" />
                    <Badge count={unread} />
                </IconButton>
            </div>

            {open && (
                <div
                    ref={dropdownRef}
                    className="absolute right-0 mt-2 w-96 max-w-[calc(100vw-2rem)] bg-white rounded-lg shadow-lg ring-1 ring-black ring-opacity-5 z-50 overflow-hidden"
                >
                    <div className="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                        <h3 className="text-sm font-semibold text-gray-900">Notificações</h3>
                        {unread > 0 && (
                            <button
                                type="button"
                                onClick={markAllAsRead}
                                disabled={markingAll}
                                className="inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800 disabled:opacity-50"
                            >
                                <CheckIcon className="w-3.5 h-3.5" />
                                Marcar todas como lidas
                            </button>
                        )}
                    </div>

                    <div className="max-h-96 overflow-y-auto">
                        {loading && items.length === 0 && (
                            <div className="px-4 py-8 text-center text-sm text-gray-500">Carregando...</div>
                        )}

                        {!loading && items.length === 0 && (
                            <div className="px-4 py-10 text-center">
                                <BellIcon className="w-10 h-10 mx-auto text-gray-300" />
                                <p className="mt-2 text-sm text-gray-500">Você não tem notificações.</p>
                            </div>
                        )}

                        {items.map((item) => (
                            <button
                                key={item.id}
                                type="button"
                                onClick={() => handleItemClick(item)}
                                className={`w-full text-left px-4 py-3 border-b border-gray-50 last:border-b-0 hover:bg-gray-50 transition-colors flex gap-3 ${
                                    item.read_at ? '' : 'bg-indigo-50/40'
                                }`}
                            >
                                <div className="flex-shrink-0 mt-1">
                                    <span
                                        className={`inline-block w-2 h-2 rounded-full ${
                                            item.read_at ? 'bg-gray-300' : 'bg-indigo-500'
                                        }`}
                                    />
                                </div>
                                <div className="flex-1 min-w-0">
                                    <p className={`text-sm ${item.read_at ? 'text-gray-600' : 'font-semibold text-gray-900'}`}>
                                        {item.title}
                                    </p>
                                    {item.message && (
                                        <p className="mt-0.5 text-xs text-gray-500 line-clamp-2">{item.message}</p>
                                    )}
                                    <p className="mt-1 text-xs text-gray-400">{item.created_at_human}</p>
                                </div>
                            </button>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

/**
 * Ações do header: sino de notificações e ícone de chat (quando aplicável).
 * Oculta tudo em contexto central (rotas não existem).
 */
export default function HeaderActions() {
    const { props } = usePage();
    if (props.isCentral) return null;

    return (
        <div className="flex items-center gap-1">
            <ChatBell />
            <NotificationsBell />
        </div>
    );
}
