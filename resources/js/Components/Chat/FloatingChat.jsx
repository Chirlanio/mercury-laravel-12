import { useState, useEffect, useRef, useCallback } from 'react';
import { router, usePage } from '@inertiajs/react';
import {
    ChatBubbleLeftRightIcon,
    PaperAirplaneIcon,
    XMarkIcon,
    MinusIcon,
    ArrowLeftIcon,
    ArrowTopRightOnSquareIcon,
    UserGroupIcon,
} from '@heroicons/react/24/outline';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useTenant from '@/Hooks/useTenant';
import useEcho from '@/Hooks/useEcho';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

/**
 * Floating chat widget mounted globally in AuthenticatedLayout.
 *
 * - Hidden when the user is already on the /chat page (full chat is visible).
 * - Hidden when user lacks VIEW_CHAT permission or tenant doesn't have the chat module.
 * - On mobile (<md), clicking the bubble navigates to /chat instead of opening the panel.
 */
export default function FloatingChat() {
    const { hasPermission } = usePermissions();
    const { hasModule } = useTenant();
    const { url, props } = usePage();
    const currentUserId = props.auth?.user?.id;

    const canView = hasPermission(PERMISSIONS.VIEW_CHAT);
    const canSend = hasPermission(PERMISSIONS.SEND_CHAT_MESSAGES);
    const hasChatModule = hasModule('chat');

    // Hide the widget when the user is already in the chat page.
    const isOnChatPage = url?.startsWith('/chat');

    const [isOpen, setIsOpen] = useState(false);
    const [conversations, setConversations] = useState([]);
    const [activeId, setActiveId] = useState(null);
    const [messages, setMessages] = useState([]);
    const [newMessage, setNewMessage] = useState('');
    const [sending, setSending] = useState(false);
    const [loading, setLoading] = useState(false);
    const [unreadTotal, setUnreadTotal] = useState(0);
    const messagesEndRef = useRef(null);

    const active = conversations.find(c => c.id === activeId) || null;

    const fetchConversations = useCallback(async () => {
        try {
            const res = await fetch(route('chat.conversations-json'), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) return;
            const data = await res.json();
            setConversations(data.conversations || []);
        } catch { /* ignore */ }
    }, []);

    const fetchUnread = useCallback(async () => {
        try {
            const res = await fetch(route('chat.unread-counts'), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) return;
            const data = await res.json();
            setUnreadTotal(data.total || 0);
        } catch { /* ignore */ }
    }, []);

    const fetchMessages = useCallback(async (conversationId) => {
        setLoading(true);
        try {
            const res = await fetch(route('chat.load-messages', conversationId), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) return;
            const data = await res.json();
            setMessages(data.messages || []);
        } catch { /* ignore */ } finally {
            setLoading(false);
        }
    }, []);

    // Poll unread counts every 30s for the badge (also runs once on mount).
    useEffect(() => {
        if (!canView || !hasChatModule || isOnChatPage) return;
        fetchUnread();
        const interval = setInterval(fetchUnread, 30000);
        return () => clearInterval(interval);
    }, [canView, hasChatModule, isOnChatPage, fetchUnread]);

    // Load conversations when opening the panel.
    useEffect(() => {
        if (isOpen) fetchConversations();
    }, [isOpen, fetchConversations]);

    // Load messages when entering a thread.
    useEffect(() => {
        if (activeId) fetchMessages(activeId);
        else setMessages([]);
    }, [activeId, fetchMessages]);

    // Scroll to bottom on new messages.
    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages.length]);

    // Mark as read when thread is opened.
    useEffect(() => {
        if (!activeId) return;
        fetch(route('chat.mark-read', activeId), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        }).then(() => {
            setConversations(prev => prev.map(c => c.id === activeId ? { ...c, unread_count: 0 } : c));
            fetchUnread();
        }).catch(() => {});
    }, [activeId, fetchUnread]);

    // Real-time: incoming messages on the active thread.
    useEcho(
        activeId ? `conversation.${activeId}` : null,
        '.message.new',
        (data) => {
            if (data.sender_id !== currentUserId) {
                setMessages(prev => prev.some(m => m.id === data.message.id) ? prev : [...prev, data.message]);
                fetch(route('chat.mark-read', activeId), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                }).catch(() => {});
            }
        },
        !!activeId && isOpen,
    );

    // Real-time: message deletion on the active thread.
    useEcho(
        activeId ? `conversation.${activeId}` : null,
        '.message.deleted',
        (data) => {
            setMessages(prev => prev.filter(m => m.id !== data.message_id));
        },
        !!activeId && isOpen,
    );

    // Global unread bump: when any conversation receives a message we're not viewing,
    // refresh the conversation list + unread count. Cheap fallback without per-conversation
    // channels (the user widget should still reflect activity across all chats).
    useEffect(() => {
        if (!isOpen) return;
        const interval = setInterval(fetchConversations, 15000);
        return () => clearInterval(interval);
    }, [isOpen, fetchConversations]);

    const handleBubbleClick = () => {
        // On mobile, redirect to full chat page instead of opening the floating panel.
        if (window.matchMedia('(max-width: 767px)').matches) {
            router.visit(route('chat.index'));
            return;
        }
        setIsOpen(true);
    };

    const handleSend = async (e) => {
        e.preventDefault();
        if (!newMessage.trim() || !activeId || sending) return;
        setSending(true);

        const content = newMessage;
        const tempId = `temp-${Date.now()}`;
        const now = new Date();
        const optimistic = {
            id: tempId,
            conversation_id: activeId,
            sender_id: currentUserId,
            sender_name: props.auth?.user?.name || 'Você',
            content,
            message_type: 'text',
            created_at: now.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }),
            _pending: true,
        };
        setMessages(prev => [...prev, optimistic]);
        setNewMessage('');

        try {
            const res = await fetch(route('chat.send-message', activeId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ content, message_type: 'text' }),
            });
            if (!res.ok) {
                setMessages(prev => prev.filter(m => m.id !== tempId));
                setNewMessage(content);
            } else {
                const data = await res.json();
                if (data?.message) {
                    setMessages(prev => prev.map(m => m.id === tempId ? data.message : m));
                } else {
                    setMessages(prev => prev.map(m => m.id === tempId ? { ...m, _pending: false } : m));
                }
            }
        } catch {
            setMessages(prev => prev.filter(m => m.id !== tempId));
            setNewMessage(content);
        } finally {
            setSending(false);
        }
    };

    if (!canView || !hasChatModule || isOnChatPage) return null;

    return (
        <>
            {/* Floating bubble (hidden when panel is open on desktop) */}
            {!isOpen && (
                <button
                    type="button"
                    onClick={handleBubbleClick}
                    className="fixed bottom-4 right-4 z-40 w-14 h-14 rounded-full bg-indigo-600 text-white shadow-lg hover:bg-indigo-700 active:bg-indigo-800 flex items-center justify-center transition-colors"
                    aria-label="Abrir chat"
                    title="Abrir chat"
                >
                    <ChatBubbleLeftRightIcon className="w-7 h-7" />
                    {unreadTotal > 0 && (
                        <span className="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full min-w-[1.25rem] h-5 px-1 flex items-center justify-center ring-2 ring-white">
                            {unreadTotal > 99 ? '99+' : unreadTotal}
                        </span>
                    )}
                </button>
            )}

            {/* Panel (desktop only — on mobile the bubble navigates to /chat) */}
            {isOpen && (
                <div className="hidden md:flex fixed bottom-4 right-4 z-40 w-[360px] h-[520px] bg-white rounded-xl shadow-2xl border border-gray-200 flex-col overflow-hidden">
                    {/* Header */}
                    <div className="flex items-center justify-between px-4 py-3 bg-indigo-600 text-white">
                        <div className="flex items-center gap-2 min-w-0">
                            {activeId && (
                                <button
                                    type="button"
                                    onClick={() => setActiveId(null)}
                                    className="w-7 h-7 flex items-center justify-center rounded-full hover:bg-indigo-500"
                                    aria-label="Voltar"
                                >
                                    <ArrowLeftIcon className="w-4 h-4" />
                                </button>
                            )}
                            <h3 className="text-sm font-semibold truncate">
                                {active ? active.title : 'Mensagens'}
                            </h3>
                        </div>
                        <div className="flex items-center gap-1">
                            <button
                                type="button"
                                onClick={() => router.visit(route('chat.index'))}
                                className="w-7 h-7 flex items-center justify-center rounded-full hover:bg-indigo-500"
                                title="Abrir chat completo"
                                aria-label="Abrir chat completo"
                            >
                                <ArrowTopRightOnSquareIcon className="w-4 h-4" />
                            </button>
                            <button
                                type="button"
                                onClick={() => setIsOpen(false)}
                                className="w-7 h-7 flex items-center justify-center rounded-full hover:bg-indigo-500"
                                title="Minimizar"
                                aria-label="Minimizar"
                            >
                                <MinusIcon className="w-4 h-4" />
                            </button>
                            <button
                                type="button"
                                onClick={() => { setIsOpen(false); setActiveId(null); }}
                                className="w-7 h-7 flex items-center justify-center rounded-full hover:bg-indigo-500"
                                title="Fechar"
                                aria-label="Fechar"
                            >
                                <XMarkIcon className="w-4 h-4" />
                            </button>
                        </div>
                    </div>

                    {/* Body */}
                    {!activeId ? (
                        <div className="flex-1 overflow-y-auto">
                            {conversations.length === 0 ? (
                                <div className="p-6 text-center text-sm text-gray-400">Nenhuma conversa</div>
                            ) : (
                                conversations.map(conv => (
                                    <button
                                        key={conv.id}
                                        onClick={() => setActiveId(conv.id)}
                                        className="w-full p-3 flex items-center gap-3 text-left hover:bg-gray-50 border-b border-gray-100"
                                    >
                                        <div className="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                                            {conv.type === 'group'
                                                ? <UserGroupIcon className="w-5 h-5 text-indigo-600" />
                                                : <span className="text-sm font-bold text-indigo-600">{conv.title?.[0]?.toUpperCase() || '?'}</span>}
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <div className="flex items-center justify-between gap-2">
                                                <span className="text-sm font-medium text-gray-900 truncate">{conv.title}</span>
                                                {conv.latest_message && (
                                                    <span className="text-[10px] text-gray-400 flex-shrink-0">
                                                        {conv.latest_message.created_at}
                                                    </span>
                                                )}
                                            </div>
                                            <p className="text-xs text-gray-500 truncate mt-0.5">
                                                {conv.latest_message
                                                    ? (conv.latest_message.is_file ? '📎 Arquivo' : conv.latest_message.content)
                                                    : 'Sem mensagens'}
                                            </p>
                                        </div>
                                        {conv.unread_count > 0 && (
                                            <span className="bg-indigo-600 text-white text-[10px] font-bold rounded-full min-w-[1.25rem] h-5 px-1 flex items-center justify-center flex-shrink-0">
                                                {conv.unread_count > 99 ? '99+' : conv.unread_count}
                                            </span>
                                        )}
                                    </button>
                                ))
                            )}
                        </div>
                    ) : (
                        <>
                            <div className="flex-1 overflow-y-auto p-3 space-y-1 bg-gray-50">
                                {loading && <div className="text-center text-xs text-gray-400 py-2">Carregando...</div>}
                                {messages.map(msg => {
                                    const isOwn = msg.sender_id === currentUserId;
                                    return (
                                        <div key={msg.id} className={`flex mb-1 ${isOwn ? 'justify-end' : 'justify-start'}`}>
                                            <div className={`max-w-[80%] px-3 py-2 rounded-2xl shadow-sm text-sm ${isOwn ? 'bg-indigo-600 text-white rounded-br-sm' : 'bg-white text-gray-900 rounded-bl-sm'} ${msg._pending ? 'opacity-60' : ''}`}>
                                                {!isOwn && <div className="text-[10px] font-semibold mb-0.5 text-indigo-600">{msg.sender_name}</div>}
                                                {msg.content && <p className="whitespace-pre-wrap break-words">{msg.content}</p>}
                                                {msg.message_type === 'image' && msg.file_url && (
                                                    <img src={msg.file_url} alt="" className="max-w-full rounded-lg mt-1" />
                                                )}
                                                {msg.message_type === 'file' && msg.file_url && (
                                                    <a href={msg.file_url} target="_blank" rel="noopener"
                                                        className={`text-xs underline ${isOwn ? 'text-indigo-200' : 'text-indigo-600'}`}>
                                                        📎 {msg.file_name}
                                                    </a>
                                                )}
                                                <div className={`text-[10px] mt-0.5 ${isOwn ? 'text-indigo-200' : 'text-gray-400'}`}>
                                                    {msg.created_at}
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                                <div ref={messagesEndRef} />
                            </div>
                            {canSend && (
                                <form onSubmit={handleSend} className="p-2 border-t border-gray-200 bg-white flex items-end gap-2">
                                    <input
                                        type="text"
                                        className="flex-1 border border-gray-300 rounded-full px-3 py-2 text-sm focus:ring-indigo-500 focus:border-indigo-500"
                                        placeholder="Mensagem..."
                                        value={newMessage}
                                        onChange={e => setNewMessage(e.target.value)}
                                    />
                                    <button
                                        type="submit"
                                        disabled={!newMessage.trim() || sending}
                                        className="w-9 h-9 flex items-center justify-center rounded-full bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50 flex-shrink-0"
                                        aria-label="Enviar"
                                    >
                                        <PaperAirplaneIcon className="w-4 h-4" />
                                    </button>
                                </form>
                            )}
                        </>
                    )}
                </div>
            )}
        </>
    );
}
