import { Head, router, usePage } from '@inertiajs/react';
import { useState, useEffect, useRef, useCallback } from 'react';
import {
    ChatBubbleLeftRightIcon,
    PaperAirplaneIcon,
    MagnifyingGlassIcon,
    PlusIcon,
    UserGroupIcon,
    MegaphoneIcon,
    PaperClipIcon,
    XMarkIcon,
    ArrowUturnLeftIcon,
    ArrowLeftIcon,
    TrashIcon,
    PencilSquareIcon,
    CheckIcon,
} from '@heroicons/react/24/outline';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import useEcho from '@/Hooks/useEcho';
import { useConfirm } from '@/Hooks/useConfirm';
import Button from '@/Components/Button';
import StandardModal from '@/Components/StandardModal';
import StatusBadge from '@/Components/Shared/StatusBadge';
import EmptyState from '@/Components/Shared/EmptyState';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

export default function Index({ conversations: initialConversations, activeConversationId, messages: initialMessages, users }) {
    const { hasPermission } = usePermissions();
    const { modals, openModal, closeModal } = useModalManager(['newConversation', 'createGroup', 'createBroadcast']);
    const { confirm, ConfirmDialogComponent } = useConfirm();
    const { props } = usePage();
    const currentUserId = props.auth?.user?.id;
    const canSend = hasPermission(PERMISSIONS.SEND_CHAT_MESSAGES);
    const canCreateGroups = hasPermission(PERMISSIONS.CREATE_CHAT_GROUPS);
    const canSendBroadcasts = hasPermission(PERMISSIONS.SEND_BROADCASTS);

    const [conversations, setConversations] = useState(initialConversations?.data || []);
    const [activeId, setActiveId] = useState(activeConversationId);
    const [messages, setMessages] = useState(initialMessages || []);
    const [newMessage, setNewMessage] = useState('');
    const [sending, setSending] = useState(false);
    const [searchQuery, setSearchQuery] = useState('');
    const [replyTo, setReplyTo] = useState(null);
    const [editingMessage, setEditingMessage] = useState(null); // { id, content }
    const [typingUsers, setTypingUsers] = useState({});
    const [tab, setTab] = useState('direct');
    const [loadingMessages, setLoadingMessages] = useState(false);
    const [userSearch, setUserSearch] = useState('');
    const messagesEndRef = useRef(null);
    const messageContainerRef = useRef(null);
    const typingTimeoutRef = useRef(null);

    // Sync state when Inertia props change (after redirects, polling reloads)
    useEffect(() => {
        setConversations(initialConversations?.data || []);
    }, [initialConversations]);

    useEffect(() => {
        setActiveId(activeConversationId);
    }, [activeConversationId]);

    useEffect(() => {
        setMessages(initialMessages || []);
    }, [initialMessages]);

    // Auto-scroll to bottom on new messages
    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages.length]);

    // Helper: atualiza a lista de conversas localmente (sem refetch ao servidor).
    // Move a conversa para o topo, atualiza latest_message e ajusta unread_count.
    const updateConversationLatest = useCallback((conversationId, msg, { isOwn, isActive }) => {
        setConversations(prev => {
            const idx = prev.findIndex(c => c.id === conversationId);
            if (idx === -1) return prev;
            const conv = prev[idx];
            const updated = {
                ...conv,
                latest_message: {
                    content: msg.content,
                    sender_name: msg.sender_name,
                    is_file: msg.message_type !== 'text',
                    created_at: 'agora',
                },
                unread_count: isOwn || isActive ? 0 : (conv.unread_count || 0) + 1,
            };
            const next = [...prev];
            next.splice(idx, 1);
            return [updated, ...next];
        });
    }, []);

    // Polling fallback: só roda em intervalo agressivo se o Echo não estiver conectado.
    // Com Echo ativo, mantém um poll lento (30s) apenas como rede de segurança.
    useEffect(() => {
        if (!activeId) return;

        const pollMessages = async () => {
            try {
                const res = await fetch(route('chat.load-messages', activeId), {
                    headers: { 'Accept': 'application/json' },
                });
                if (!res.ok) return;
                const data = await res.json();
                const newMessages = data.messages || [];
                setMessages(prev => {
                    if (newMessages.length === prev.length) {
                        const lastPrev = prev[prev.length - 1];
                        const lastNew = newMessages[newMessages.length - 1];
                        if (lastPrev?.id === lastNew?.id) return prev;
                    }
                    return newMessages;
                });
            } catch { /* ignore */ }
        };

        const isEchoUp = window.Echo?.connector?.pusher?.connection?.state === 'connected';
        const interval = setInterval(pollMessages, isEchoUp ? 30000 : 4000);
        return () => clearInterval(interval);
    }, [activeId]);

    // Real-time new messages via Echo
    useEcho(
        activeId ? `conversation.${activeId}` : null,
        '.message.new',
        (data) => {
            if (data.sender_id !== currentUserId) {
                // Evita duplicação caso o evento chegue após um poll de fallback
                setMessages(prev => prev.some(m => m.id === data.message.id)
                    ? prev
                    : [...prev, data.message]);
                updateConversationLatest(data.conversation_id, {
                    content: data.message.content,
                    sender_name: data.sender_name,
                    message_type: data.message.message_type,
                }, { isOwn: false, isActive: true });
                // Mark as read
                fetch(route('chat.mark-read', activeId), {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                }).catch(() => {});
            }
        },
        !!activeId,
    );

    // Typing indicators via Echo
    useEcho(
        activeId ? `conversation.${activeId}` : null,
        '.typing',
        (data) => {
            if (data.user_id !== currentUserId) {
                setTypingUsers(prev => ({ ...prev, [data.user_id]: data.is_typing ? data.user_name : null }));
                if (data.is_typing) {
                    setTimeout(() => setTypingUsers(prev => ({ ...prev, [data.user_id]: null })), 3000);
                }
            }
        },
        !!activeId,
    );

    // Real-time message deletion via Echo
    useEcho(
        activeId ? `conversation.${activeId}` : null,
        '.message.deleted',
        (data) => {
            setMessages(prev => prev.filter(m => m.id !== data.message_id));
        },
        !!activeId,
    );

    // Real-time message edition via Echo
    useEcho(
        activeId ? `conversation.${activeId}` : null,
        '.message.edited',
        (data) => {
            setMessages(prev => prev.map(m => m.id === data.message_id
                ? { ...m, content: data.content, edited_at: data.edited_at, is_edited: true }
                : m));
        },
        !!activeId,
    );

    const activeTypingNames = Object.values(typingUsers).filter(Boolean);

    const handleDeleteMessage = async (message) => {
        const ok = await confirm({
            title: 'Apagar mensagem',
            message: 'Tem certeza que deseja apagar esta mensagem? Essa ação não pode ser desfeita.',
            confirmText: 'Apagar',
            cancelText: 'Cancelar',
            type: 'danger',
        });
        if (!ok) return;

        // Optimistic removal
        const snapshot = messages;
        setMessages(prev => prev.filter(m => m.id !== message.id));
        if (replyTo?.id === message.id) setReplyTo(null);

        try {
            const res = await fetch(route('chat.delete-message', message.id), {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (!res.ok) {
                // Revert on failure
                setMessages(snapshot);
            }
        } catch {
            setMessages(snapshot);
        }
    };

    const handleStartEdit = (message) => {
        setEditingMessage({ id: message.id, content: message.content || '' });
    };

    const handleCancelEdit = () => setEditingMessage(null);

    const handleSaveEdit = async () => {
        if (!editingMessage) return;
        const trimmed = (editingMessage.content || '').trim();
        if (!trimmed) return;

        const messageId = editingMessage.id;
        const originalMessage = messages.find(m => m.id === messageId);
        if (!originalMessage || trimmed === originalMessage.content) {
            setEditingMessage(null);
            return;
        }

        // Optimistic update
        const nowIso = new Date().toISOString();
        setMessages(prev => prev.map(m => m.id === messageId
            ? { ...m, content: trimmed, edited_at: nowIso, is_edited: true }
            : m));
        setEditingMessage(null);

        try {
            const res = await fetch(route('chat.edit-message', messageId), {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ content: trimmed }),
            });
            if (!res.ok) {
                // Revert on failure
                setMessages(prev => prev.map(m => m.id === messageId ? originalMessage : m));
            } else {
                const data = await res.json();
                if (data?.message) {
                    setMessages(prev => prev.map(m => m.id === messageId ? data.message : m));
                }
            }
        } catch {
            setMessages(prev => prev.map(m => m.id === messageId ? originalMessage : m));
        }
    };

    const openConversation = (conversationId) => {
        if (conversationId === activeId) return;
        router.get(route('chat.show', conversationId), {}, { preserveState: true });
    };

    const handleSendMessage = async (e) => {
        e.preventDefault();
        if (!newMessage.trim() || !activeId || sending) return;
        setSending(true);

        const contentToSend = newMessage;
        const replyToSend = replyTo;

        // Optimistic update: adiciona a mensagem imediatamente
        const tempId = `temp-${Date.now()}`;
        const now = new Date();
        const optimisticMessage = {
            id: tempId,
            conversation_id: activeId,
            sender_id: currentUserId,
            sender_name: props.auth?.user?.name || 'Você',
            content: contentToSend,
            message_type: 'text',
            file_url: null,
            file_name: null,
            file_size: null,
            reply_to: replyToSend ? {
                id: replyToSend.id,
                content: replyToSend.content,
                sender_name: replyToSend.sender_name,
            } : null,
            created_at: now.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }),
            created_at_full: now.toLocaleString('pt-BR'),
            created_at_date: now.toISOString().split('T')[0],
            _pending: true,
        };
        setMessages(prev => [...prev, optimisticMessage]);
        setNewMessage('');
        setReplyTo(null);

        try {
            const res = await fetch(route('chat.send-message', activeId), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    content: contentToSend,
                    message_type: 'text',
                    reply_to_message_id: replyToSend?.id || null,
                }),
            });

            if (!res.ok) {
                // Revert optimistic on failure
                setMessages(prev => prev.filter(m => m.id !== tempId));
                setNewMessage(contentToSend);
                setReplyTo(replyToSend);
            } else {
                // Substitui a mensagem otimista pela real (com ID, timestamp e file_url do servidor),
                // sem refetch: economiza um round-trip e evita flicker.
                const data = await res.json();
                if (data?.message) {
                    setMessages(prev => prev.map(m => m.id === tempId ? data.message : m));
                    updateConversationLatest(activeId, {
                        content: data.message.content,
                        sender_name: data.message.sender_name,
                        message_type: data.message.message_type,
                    }, { isOwn: true, isActive: true });
                } else {
                    // Fallback: marca otimista como confirmada (remove _pending)
                    setMessages(prev => prev.map(m => m.id === tempId ? { ...m, _pending: false } : m));
                }
            }
        } catch {
            setMessages(prev => prev.filter(m => m.id !== tempId));
            setNewMessage(contentToSend);
            setReplyTo(replyToSend);
        } finally {
            setSending(false);
        }
    };

    const handleTyping = () => {
        if (!activeId || !canSend) return;
        if (typingTimeoutRef.current) clearTimeout(typingTimeoutRef.current);
        fetch(route('chat.typing', activeId), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
            body: JSON.stringify({ is_typing: true }),
        }).catch(() => {});
        typingTimeoutRef.current = setTimeout(() => {
            fetch(route('chat.typing', activeId), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                body: JSON.stringify({ is_typing: false }),
            }).catch(() => {});
        }, 2000);
    };

    const handleFileUpload = async (e) => {
        const file = e.target.files[0];
        if (!file || !activeId) return;
        const formData = new FormData();
        formData.append('file', file);
        try {
            const res = await fetch(route('chat.upload'), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken(), 'Accept': 'application/json' },
                body: formData,
            });
            if (res.ok) {
                const data = await res.json();
                router.post(route('chat.send-message', activeId), {
                    content: null,
                    message_type: file.type.startsWith('image/') ? 'image' : 'file',
                    file_path: data.path,
                    file_name: data.name,
                    file_size: data.size,
                    file_mime: data.mime,
                }, { preserveScroll: true });
            }
        } catch { /* ignore */ }
        e.target.value = '';
    };

    const loadOlderMessages = async () => {
        if (!activeId || loadingMessages || !messages.length) return;
        setLoadingMessages(true);
        try {
            const res = await fetch(route('chat.load-messages', { conversation: activeId, before_id: messages[0]?.id }), {
                headers: { 'Accept': 'application/json' },
            });
            if (res.ok) {
                const data = await res.json();
                if (data.messages?.length) {
                    setMessages(prev => [...data.messages, ...prev]);
                }
            }
        } catch { /* ignore */ } finally {
            setLoadingMessages(false);
        }
    };

    const handleScroll = (e) => {
        if (e.target.scrollTop === 0) loadOlderMessages();
    };

    const filteredConversations = searchQuery
        ? conversations.filter(c => c.title?.toLowerCase().includes(searchQuery.toLowerCase()))
        : conversations;

    const directConversations = filteredConversations.filter(c => c.type === 'direct');
    const groupConversations = filteredConversations.filter(c => c.type === 'group');

    // Group messages by date
    const messagesByDate = messages.reduce((groups, msg) => {
        const date = msg.created_at_date || 'today';
        if (!groups[date]) groups[date] = [];
        groups[date].push(msg);
        return groups;
    }, {});

    // Nome exibido na conversa ativa (para header mobile)
    const activeConversation = conversations.find(c => c.id === activeId);

    return (
        <>
            <Head title="Chat" />
            <div className="flex bg-white md:rounded-lg md:shadow-sm overflow-hidden md:mx-4 md:my-4 h-full md:h-[calc(100%-2rem)]">
                {/* Sidebar: Conversations List
                    Mobile: ocupa tela inteira quando não há conversa ativa; escondida quando há
                    Desktop: sempre visível com largura fixa */}
                <div className={`${activeId ? 'hidden md:flex' : 'flex'} w-full md:w-80 border-r border-gray-200 flex-col flex-shrink-0`}>
                    {/* Header */}
                    <div className="p-4 border-b border-gray-200">
                        <div className="flex items-center justify-between mb-3">
                            <h2 className="text-lg font-bold text-gray-900">Chat</h2>
                            <div className="flex gap-2">
                                {canSend && (
                                    <button type="button" onClick={() => openModal('newConversation')}
                                        className="w-10 h-10 flex items-center justify-center rounded-full hover:bg-indigo-50 text-indigo-600 transition-colors"
                                        title="Nova conversa" aria-label="Nova conversa">
                                        <PlusIcon className="w-5 h-5" />
                                    </button>
                                )}
                                {canCreateGroups && (
                                    <button type="button" onClick={() => openModal('createGroup')}
                                        className="w-10 h-10 flex items-center justify-center rounded-full hover:bg-indigo-50 text-indigo-600 transition-colors"
                                        title="Novo grupo" aria-label="Novo grupo">
                                        <UserGroupIcon className="w-5 h-5" />
                                    </button>
                                )}
                                {canSendBroadcasts && (
                                    <button type="button" onClick={() => openModal('createBroadcast')}
                                        className="w-10 h-10 flex items-center justify-center rounded-full hover:bg-indigo-50 text-indigo-600 transition-colors"
                                        title="Novo comunicado" aria-label="Novo comunicado">
                                        <MegaphoneIcon className="w-5 h-5" />
                                    </button>
                                )}
                            </div>
                        </div>
                        <TextInput className="w-full text-sm" placeholder="Buscar..."
                            value={searchQuery} onChange={e => setSearchQuery(e.target.value)} />
                    </div>

                    {/* Tabs */}
                    <div className="flex border-b border-gray-200">
                        {[{ key: 'direct', label: 'Diretas' }, { key: 'group', label: 'Grupos' }, { key: 'broadcasts', label: 'Comunicados' }].map(t => (
                            <button key={t.key} onClick={() => setTab(t.key)}
                                className={`flex-1 py-3 text-xs font-medium transition-colors ${tab === t.key ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-500 hover:text-gray-700'}`}>
                                {t.label}
                            </button>
                        ))}
                    </div>

                    {/* Conversation List */}
                    <div className="flex-1 overflow-y-auto">
                        {tab !== 'broadcasts' && (tab === 'direct' ? directConversations : groupConversations).map(conv => (
                            <button key={conv.id} onClick={() => openConversation(conv.id)}
                                className={`w-full p-4 min-h-[4rem] flex items-center gap-3 text-left hover:bg-gray-50 active:bg-gray-100 border-b border-gray-100 transition-colors ${activeId === conv.id ? 'bg-indigo-50' : ''}`}>
                                <div className="w-12 h-12 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                                    {conv.type === 'group'
                                        ? <UserGroupIcon className="w-6 h-6 text-indigo-600" />
                                        : <span className="text-base font-bold text-indigo-600">{conv.title?.[0]?.toUpperCase() || '?'}</span>}
                                </div>
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="text-sm font-medium text-gray-900 truncate">{conv.title}</span>
                                        {conv.latest_message && <span className="text-xs text-gray-400 flex-shrink-0">{conv.latest_message.created_at}</span>}
                                    </div>
                                    <p className="text-xs text-gray-500 truncate mt-0.5">
                                        {conv.latest_message
                                            ? (conv.latest_message.is_file ? '📎 Arquivo' : conv.latest_message.content)
                                            : 'Sem mensagens'}
                                    </p>
                                </div>
                                {conv.unread_count > 0 && (
                                    <span className="bg-indigo-600 text-white text-xs font-bold rounded-full min-w-[1.5rem] h-6 px-1.5 flex items-center justify-center flex-shrink-0">
                                        {conv.unread_count > 99 ? '99+' : conv.unread_count}
                                    </span>
                                )}
                            </button>
                        ))}
                        {tab !== 'broadcasts' && (tab === 'direct' ? directConversations : groupConversations).length === 0 && (
                            <div className="p-8 text-center text-sm text-gray-400">
                                {tab === 'direct' ? 'Nenhuma conversa' : 'Nenhum grupo'}
                            </div>
                        )}
                        {tab === 'broadcasts' && (
                            <div className="p-8 text-center text-sm text-gray-400">
                                Comunicados serão exibidos aqui.
                            </div>
                        )}
                    </div>
                </div>

                {/* Main: Message Thread
                    Mobile: ocupa tela inteira quando há conversa ativa; escondida no estado inicial
                    Desktop: sempre visível */}
                <div className={`${activeId ? 'flex' : 'hidden md:flex'} flex-1 flex-col min-w-0`}>
                    {activeId ? (
                        <>
                            {/* Thread header (mobile shows back button) */}
                            <div className="flex items-center gap-3 p-3 border-b border-gray-200 bg-white">
                                <button type="button" onClick={() => setActiveId(null)}
                                    className="md:hidden w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-100 active:bg-gray-200"
                                    aria-label="Voltar">
                                    <ArrowLeftIcon className="w-5 h-5 text-gray-700" />
                                </button>
                                <div className="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                                    {activeConversation?.type === 'group'
                                        ? <UserGroupIcon className="w-5 h-5 text-indigo-600" />
                                        : <span className="text-sm font-bold text-indigo-600">{activeConversation?.title?.[0]?.toUpperCase() || '?'}</span>}
                                </div>
                                <div className="flex-1 min-w-0">
                                    <h3 className="text-sm font-semibold text-gray-900 truncate">{activeConversation?.title || 'Conversa'}</h3>
                                    {activeTypingNames.length > 0 && (
                                        <p className="text-xs text-indigo-600 italic truncate">digitando...</p>
                                    )}
                                </div>
                            </div>

                            {/* Messages */}
                            <div ref={messageContainerRef} onScroll={handleScroll}
                                className="flex-1 overflow-y-auto p-3 md:p-4 space-y-1 bg-gray-50">
                                {loadingMessages && <div className="text-center text-xs text-gray-400 py-2">Carregando...</div>}
                                {Object.entries(messagesByDate).map(([date, msgs]) => (
                                    <div key={date}>
                                        <div className="text-center my-3">
                                            <span className="text-xs bg-white text-gray-500 px-3 py-1 rounded-full shadow-sm">{date}</span>
                                        </div>
                                        {msgs.map(msg => {
                                            const isOwn = msg.sender_id === currentUserId;
                                            return (
                                                <div key={msg.id} className={`flex mb-2 ${isOwn ? 'justify-end' : 'justify-start'}`}>
                                                    <div className={`max-w-[85%] md:max-w-md px-3 py-2 rounded-2xl shadow-sm ${isOwn ? 'bg-indigo-600 text-white rounded-br-sm' : 'bg-white text-gray-900 rounded-bl-sm'} ${msg._pending ? 'opacity-60' : ''}`}>
                                                        {!isOwn && <div className="text-xs font-semibold mb-0.5 text-indigo-600">{msg.sender_name}</div>}
                                                        {msg.reply_to && (
                                                            <div className={`text-xs mb-1 px-2 py-1 rounded border-l-2 ${isOwn ? 'bg-indigo-500 border-indigo-300' : 'bg-gray-100 border-gray-400'}`}>
                                                                <strong>{msg.reply_to.sender_name}:</strong> {msg.reply_to.content?.substring(0, 80)}
                                                            </div>
                                                        )}
                                                        {msg.message_type === 'image' && msg.file_url && (
                                                            <img src={msg.file_url} alt="" className="max-w-full rounded-lg mb-1" />
                                                        )}
                                                        {msg.message_type === 'file' && msg.file_url && (
                                                            <a href={msg.file_url} target="_blank" rel="noopener"
                                                                className={`flex items-center gap-1 text-xs underline ${isOwn ? 'text-indigo-200' : 'text-indigo-600'}`}>
                                                                <PaperClipIcon className="w-3 h-3" /> {msg.file_name}
                                                            </a>
                                                        )}
                                                        {editingMessage?.id === msg.id ? (
                                                            <div className="space-y-2">
                                                                <textarea
                                                                    value={editingMessage.content}
                                                                    onChange={e => setEditingMessage({ ...editingMessage, content: e.target.value })}
                                                                    onKeyDown={e => {
                                                                        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); handleSaveEdit(); }
                                                                        if (e.key === 'Escape') { e.preventDefault(); handleCancelEdit(); }
                                                                    }}
                                                                    autoFocus
                                                                    rows={2}
                                                                    className={`w-full text-sm rounded-lg p-2 resize-none focus:outline-none ${isOwn ? 'bg-indigo-500 text-white placeholder-indigo-200 border border-indigo-300' : 'bg-white text-gray-900 border border-gray-300'}`}
                                                                />
                                                                <div className="flex items-center justify-end gap-2">
                                                                    <button
                                                                        onClick={handleCancelEdit}
                                                                        className={`text-xs px-2 py-1 rounded hover:underline ${isOwn ? 'text-indigo-100' : 'text-gray-500'}`}
                                                                    >
                                                                        Cancelar
                                                                    </button>
                                                                    <button
                                                                        onClick={handleSaveEdit}
                                                                        disabled={!editingMessage.content?.trim()}
                                                                        className={`flex items-center gap-1 text-xs px-2 py-1 rounded disabled:opacity-50 ${isOwn ? 'bg-white text-indigo-700 hover:bg-indigo-50' : 'bg-indigo-600 text-white hover:bg-indigo-700'}`}
                                                                    >
                                                                        <CheckIcon className="w-3 h-3" /> Salvar
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        ) : (
                                                            msg.content && <p className="text-sm whitespace-pre-wrap break-words">{msg.content}</p>
                                                        )}
                                                        {editingMessage?.id !== msg.id && (
                                                            <div className={`text-xs mt-0.5 ${isOwn ? 'text-indigo-200' : 'text-gray-400'} flex items-center justify-between gap-2`}>
                                                                <span className="flex items-center gap-1">
                                                                    {msg.created_at}
                                                                    {msg.is_edited && <span className="italic" title="Mensagem editada">(editado)</span>}
                                                                </span>
                                                                <div className="flex items-center gap-2">
                                                                    {!isOwn && canSend && (
                                                                        <button onClick={() => setReplyTo(msg)} className="hover:underline">Responder</button>
                                                                    )}
                                                                    {isOwn && !msg._pending && msg.message_type === 'text' && (
                                                                        <button
                                                                            onClick={() => handleStartEdit(msg)}
                                                                            className="hover:text-white transition-colors"
                                                                            title="Editar mensagem"
                                                                            aria-label="Editar mensagem"
                                                                        >
                                                                            <PencilSquareIcon className="w-3.5 h-3.5" />
                                                                        </button>
                                                                    )}
                                                                    {isOwn && !msg._pending && (
                                                                        <button
                                                                            onClick={() => handleDeleteMessage(msg)}
                                                                            className="hover:text-white transition-colors"
                                                                            title="Apagar mensagem"
                                                                            aria-label="Apagar mensagem"
                                                                        >
                                                                            <TrashIcon className="w-3.5 h-3.5" />
                                                                        </button>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                ))}
                                <div ref={messagesEndRef} />
                            </div>

                            {/* Reply preview */}
                            {replyTo && (
                                <div className="px-4 py-2 bg-gray-50 border-t flex items-center gap-2">
                                    <ArrowUturnLeftIcon className="w-4 h-4 text-gray-400 flex-shrink-0" />
                                    <div className="flex-1 text-xs text-gray-600 truncate">
                                        <strong>{replyTo.sender_name}:</strong> {replyTo.content?.substring(0, 100)}
                                    </div>
                                    <button onClick={() => setReplyTo(null)}
                                        className="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-200 flex-shrink-0">
                                        <XMarkIcon className="w-4 h-4 text-gray-400" />
                                    </button>
                                </div>
                            )}

                            {/* Input */}
                            {canSend && (
                                <form onSubmit={handleSendMessage}
                                    className="p-2 md:p-3 border-t border-gray-200 bg-white flex items-end gap-2 pb-[max(0.5rem,env(safe-area-inset-bottom))] md:pb-[max(0.75rem,env(safe-area-inset-bottom))]">
                                    <label className="cursor-pointer w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-100 active:bg-gray-200 flex-shrink-0">
                                        <PaperClipIcon className="w-5 h-5 text-gray-500" />
                                        <input type="file" className="hidden" onChange={handleFileUpload} />
                                    </label>
                                    <textarea
                                        className="flex-1 border border-gray-300 rounded-2xl px-4 py-2 text-sm resize-none focus:ring-indigo-500 focus:border-indigo-500 max-h-32"
                                        rows={1} placeholder="Digite uma mensagem..."
                                        value={newMessage}
                                        onChange={e => { setNewMessage(e.target.value); handleTyping(); }}
                                        onKeyDown={e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); handleSendMessage(e); } }}
                                    />
                                    <button type="submit" disabled={!newMessage.trim() || sending}
                                        className="w-10 h-10 flex items-center justify-center rounded-full bg-indigo-600 text-white hover:bg-indigo-700 active:bg-indigo-800 disabled:opacity-50 disabled:cursor-not-allowed flex-shrink-0 transition-colors"
                                        aria-label="Enviar">
                                        <PaperAirplaneIcon className="w-5 h-5" />
                                    </button>
                                </form>
                            )}
                        </>
                    ) : (
                        <div className="flex-1 flex items-center justify-center">
                            <EmptyState icon={ChatBubbleLeftRightIcon} title="Selecione uma conversa"
                                description="Escolha uma conversa na lista ou inicie uma nova." />
                        </div>
                    )}
                </div>
            </div>

            {/* New Conversation Modal */}
            <StandardModal show={modals.newConversation} onClose={() => { closeModal('newConversation'); setUserSearch(''); }}
                title="Nova Conversa" headerColor="bg-indigo-600" maxWidth="md">
                <StandardModal.Section title="Selecionar Usuário">
                    <div className="relative mb-3">
                        <MagnifyingGlassIcon className="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                        <input
                            type="text"
                            autoFocus
                            placeholder="Buscar usuário por nome..."
                            value={userSearch}
                            onChange={e => setUserSearch(e.target.value)}
                            className="w-full pl-9 pr-3 py-2 text-sm border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        {userSearch && (
                            <button type="button" onClick={() => setUserSearch('')}
                                className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <XMarkIcon className="w-4 h-4" />
                            </button>
                        )}
                    </div>
                    <div className="space-y-1 max-h-60 overflow-y-auto">
                        {(() => {
                            const filtered = (users || []).filter(u =>
                                !userSearch || u.name?.toLowerCase().includes(userSearch.toLowerCase())
                            );
                            if (filtered.length === 0) {
                                return (
                                    <div className="text-center text-sm text-gray-400 py-6">
                                        {userSearch ? 'Nenhum usuário encontrado' : 'Nenhum usuário disponível'}
                                    </div>
                                );
                            }
                            return filtered.map(user => (
                                <button key={user.id} onClick={() => {
                                    closeModal('newConversation');
                                    setUserSearch('');
                                    router.post(route('chat.create-direct'), { user_id: user.id });
                                }}
                                    className="w-full p-2 flex items-center gap-3 rounded-lg hover:bg-gray-50 text-left">
                                    <div className="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                                        <span className="text-xs font-bold text-indigo-600">{user.name?.[0]?.toUpperCase()}</span>
                                    </div>
                                    <span className="text-sm text-gray-900">{user.name}</span>
                                </button>
                            ));
                        })()}
                    </div>
                </StandardModal.Section>
            </StandardModal>

            {/* Create Group Modal */}
            <StandardModal show={modals.createGroup} onClose={() => closeModal('createGroup')}
                title="Novo Grupo" headerColor="bg-indigo-600" maxWidth="md"
                onSubmit={(e) => {
                    e.preventDefault();
                    const form = new FormData(e.target);
                    const selectedMembers = Array.from(e.target.querySelectorAll('input[name="member_ids[]"]:checked')).map(el => el.value);
                    router.post(route('chat.groups.store'), {
                        name: form.get('name'),
                        description: form.get('description'),
                        member_ids: selectedMembers,
                    });
                    closeModal('createGroup');
                }}
                footer={<StandardModal.Footer onCancel={() => closeModal('createGroup')} onSubmit="submit" submitLabel="Criar Grupo" />}>
                <StandardModal.Section title="Dados do Grupo">
                    <div className="space-y-3">
                        <div>
                            <InputLabel value="Nome *" />
                            <TextInput name="name" className="w-full mt-1" required placeholder="Nome do grupo..." />
                        </div>
                        <div>
                            <InputLabel value="Descrição" />
                            <textarea name="description" className="w-full mt-1 border-gray-300 rounded-lg text-sm" rows={2} placeholder="Descrição opcional..." />
                        </div>
                    </div>
                </StandardModal.Section>
                <StandardModal.Section title="Membros">
                    <div className="space-y-1 max-h-40 overflow-y-auto">
                        {(users || []).map(user => (
                            <label key={user.id} className="flex items-center gap-2 p-1 hover:bg-gray-50 rounded cursor-pointer">
                                <input type="checkbox" name="member_ids[]" value={user.id} className="rounded border-gray-300 text-indigo-600" />
                                <span className="text-sm">{user.name}</span>
                            </label>
                        ))}
                    </div>
                </StandardModal.Section>
            </StandardModal>

            <ConfirmDialogComponent />
        </>
    );
}
