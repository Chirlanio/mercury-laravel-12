import { Head, router } from '@inertiajs/react';
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
} from '@heroicons/react/24/outline';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import useModalManager from '@/Hooks/useModalManager';
import useEcho from '@/Hooks/useEcho';
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
    const [typingUsers, setTypingUsers] = useState({});
    const [tab, setTab] = useState('direct');
    const [loadingMessages, setLoadingMessages] = useState(false);
    const messagesEndRef = useRef(null);
    const messageContainerRef = useRef(null);
    const typingTimeoutRef = useRef(null);

    // Auto-scroll to bottom on new messages
    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages.length]);

    // Real-time new messages via Echo
    useEcho(
        activeId ? `conversation.${activeId}` : null,
        '.message.new',
        (data) => {
            if (data.sender_id !== window.auth?.id) {
                setMessages(prev => [...prev, data.message]);
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
            if (data.user_id !== window.auth?.id) {
                setTypingUsers(prev => ({ ...prev, [data.user_id]: data.is_typing ? data.user_name : null }));
                if (data.is_typing) {
                    setTimeout(() => setTypingUsers(prev => ({ ...prev, [data.user_id]: null })), 3000);
                }
            }
        },
        !!activeId,
    );

    const activeTypingNames = Object.values(typingUsers).filter(Boolean);

    const openConversation = (conversationId) => {
        if (conversationId === activeId) return;
        router.get(route('chat.show', conversationId), {}, { preserveState: true });
    };

    const handleSendMessage = async (e) => {
        e.preventDefault();
        if (!newMessage.trim() && !replyTo || !activeId || sending) return;
        setSending(true);

        router.post(route('chat.send-message', activeId), {
            content: newMessage,
            message_type: 'text',
            reply_to_message_id: replyTo?.id || null,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setNewMessage('');
                setReplyTo(null);
            },
            onFinish: () => setSending(false),
        });
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

    return (
        <>
            <Head title="Chat" />
            <div className="h-[calc(100vh-4rem)] flex bg-white rounded-lg shadow-sm overflow-hidden mx-4 my-4">
                {/* Sidebar: Conversations List */}
                <div className="w-80 border-r border-gray-200 flex flex-col flex-shrink-0">
                    {/* Header */}
                    <div className="p-4 border-b border-gray-200">
                        <div className="flex items-center justify-between mb-3">
                            <h2 className="text-lg font-bold text-gray-900">Chat</h2>
                            <div className="flex gap-1">
                                {canSend && (
                                    <Button variant="outline" size="xs" icon={PlusIcon} iconOnly
                                        onClick={() => openModal('newConversation')} title="Nova conversa" />
                                )}
                                {canCreateGroups && (
                                    <Button variant="outline" size="xs" icon={UserGroupIcon} iconOnly
                                        onClick={() => openModal('createGroup')} title="Novo grupo" />
                                )}
                                {canSendBroadcasts && (
                                    <Button variant="outline" size="xs" icon={MegaphoneIcon} iconOnly
                                        onClick={() => openModal('createBroadcast')} title="Novo comunicado" />
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
                                className={`flex-1 py-2 text-xs font-medium ${tab === t.key ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-500 hover:text-gray-700'}`}>
                                {t.label}
                            </button>
                        ))}
                    </div>

                    {/* Conversation List */}
                    <div className="flex-1 overflow-y-auto">
                        {tab !== 'broadcasts' && (tab === 'direct' ? directConversations : groupConversations).map(conv => (
                            <button key={conv.id} onClick={() => openConversation(conv.id)}
                                className={`w-full p-3 flex items-center gap-3 text-left hover:bg-gray-50 border-b border-gray-100 ${activeId === conv.id ? 'bg-indigo-50' : ''}`}>
                                <div className="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center flex-shrink-0">
                                    {conv.type === 'group'
                                        ? <UserGroupIcon className="w-5 h-5 text-indigo-600" />
                                        : <span className="text-sm font-bold text-indigo-600">{conv.title?.[0]?.toUpperCase() || '?'}</span>}
                                </div>
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm font-medium text-gray-900 truncate">{conv.title}</span>
                                        {conv.latest_message && <span className="text-xs text-gray-400">{conv.latest_message.created_at}</span>}
                                    </div>
                                    <p className="text-xs text-gray-500 truncate">
                                        {conv.latest_message
                                            ? (conv.latest_message.is_file ? '📎 Arquivo' : conv.latest_message.content)
                                            : 'Sem mensagens'}
                                    </p>
                                </div>
                                {conv.unread_count > 0 && (
                                    <span className="bg-indigo-600 text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center flex-shrink-0">
                                        {conv.unread_count > 9 ? '9+' : conv.unread_count}
                                    </span>
                                )}
                            </button>
                        ))}
                        {tab !== 'broadcasts' && (tab === 'direct' ? directConversations : groupConversations).length === 0 && (
                            <div className="p-6 text-center text-sm text-gray-400">
                                {tab === 'direct' ? 'Nenhuma conversa' : 'Nenhum grupo'}
                            </div>
                        )}
                        {tab === 'broadcasts' && (
                            <div className="p-4 text-center text-sm text-gray-400">
                                Comunicados serão exibidos aqui.
                            </div>
                        )}
                    </div>
                </div>

                {/* Main: Message Thread */}
                <div className="flex-1 flex flex-col">
                    {activeId ? (
                        <>
                            {/* Messages */}
                            <div ref={messageContainerRef} onScroll={handleScroll}
                                className="flex-1 overflow-y-auto p-4 space-y-1">
                                {loadingMessages && <div className="text-center text-xs text-gray-400 py-2">Carregando...</div>}
                                {Object.entries(messagesByDate).map(([date, msgs]) => (
                                    <div key={date}>
                                        <div className="text-center my-3">
                                            <span className="text-xs bg-gray-100 text-gray-500 px-3 py-1 rounded-full">{date}</span>
                                        </div>
                                        {msgs.map(msg => {
                                            const isOwn = msg.sender_id === window.auth?.id;
                                            return (
                                                <div key={msg.id} className={`flex mb-2 ${isOwn ? 'justify-end' : 'justify-start'}`}>
                                                    <div className={`max-w-md px-3 py-2 rounded-xl ${isOwn ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-900'}`}>
                                                        {!isOwn && <div className="text-xs font-semibold mb-0.5 text-indigo-600">{msg.sender_name}</div>}
                                                        {msg.reply_to && (
                                                            <div className={`text-xs mb-1 px-2 py-1 rounded border-l-2 ${isOwn ? 'bg-indigo-500 border-indigo-300' : 'bg-gray-200 border-gray-400'}`}>
                                                                <strong>{msg.reply_to.sender_name}:</strong> {msg.reply_to.content?.substring(0, 80)}
                                                            </div>
                                                        )}
                                                        {msg.message_type === 'image' && msg.file_url && (
                                                            <img src={msg.file_url} alt="" className="max-w-xs rounded-lg mb-1" />
                                                        )}
                                                        {msg.message_type === 'file' && msg.file_url && (
                                                            <a href={msg.file_url} target="_blank" rel="noopener"
                                                                className={`flex items-center gap-1 text-xs underline ${isOwn ? 'text-indigo-200' : 'text-indigo-600'}`}>
                                                                <PaperClipIcon className="w-3 h-3" /> {msg.file_name}
                                                            </a>
                                                        )}
                                                        {msg.content && <p className="text-sm whitespace-pre-wrap break-words">{msg.content}</p>}
                                                        <div className={`text-xs mt-0.5 ${isOwn ? 'text-indigo-200' : 'text-gray-400'} flex items-center justify-between gap-2`}>
                                                            <span>{msg.created_at}</span>
                                                            {!isOwn && canSend && (
                                                                <button onClick={() => setReplyTo(msg)} className="hover:underline">Responder</button>
                                                            )}
                                                        </div>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                ))}
                                <div ref={messagesEndRef} />
                            </div>

                            {/* Typing indicator */}
                            {activeTypingNames.length > 0 && (
                                <div className="px-4 py-1 text-xs text-gray-500 italic">
                                    {activeTypingNames.join(', ')} {activeTypingNames.length === 1 ? 'está' : 'estão'} digitando...
                                </div>
                            )}

                            {/* Reply preview */}
                            {replyTo && (
                                <div className="px-4 py-2 bg-gray-50 border-t flex items-center gap-2">
                                    <ArrowUturnLeftIcon className="w-4 h-4 text-gray-400" />
                                    <div className="flex-1 text-xs text-gray-600 truncate">
                                        <strong>{replyTo.sender_name}:</strong> {replyTo.content?.substring(0, 100)}
                                    </div>
                                    <button onClick={() => setReplyTo(null)}><XMarkIcon className="w-4 h-4 text-gray-400" /></button>
                                </div>
                            )}

                            {/* Input */}
                            {canSend && (
                                <form onSubmit={handleSendMessage} className="p-3 border-t border-gray-200 flex items-end gap-2">
                                    <label className="cursor-pointer p-2 hover:bg-gray-100 rounded-lg">
                                        <PaperClipIcon className="w-5 h-5 text-gray-500" />
                                        <input type="file" className="hidden" onChange={handleFileUpload} />
                                    </label>
                                    <textarea
                                        className="flex-1 border border-gray-300 rounded-xl px-3 py-2 text-sm resize-none focus:ring-indigo-500 focus:border-indigo-500"
                                        rows={1} placeholder="Digite uma mensagem..."
                                        value={newMessage}
                                        onChange={e => { setNewMessage(e.target.value); handleTyping(); }}
                                        onKeyDown={e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); handleSendMessage(e); } }}
                                    />
                                    <Button variant="primary" size="sm" icon={PaperAirplaneIcon} iconOnly
                                        loading={sending} disabled={!newMessage.trim()} type="submit" />
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
            <StandardModal show={modals.newConversation} onClose={() => closeModal('newConversation')}
                title="Nova Conversa" headerColor="bg-indigo-600" maxWidth="md">
                <StandardModal.Section title="Selecionar Usuário">
                    <div className="space-y-1 max-h-60 overflow-y-auto">
                        {(users || []).map(user => (
                            <button key={user.id} onClick={() => {
                                closeModal('newConversation');
                                router.post(route('chat.create-direct'), { user_id: user.id });
                            }}
                                className="w-full p-2 flex items-center gap-3 rounded-lg hover:bg-gray-50 text-left">
                                <div className="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center">
                                    <span className="text-xs font-bold text-indigo-600">{user.name?.[0]?.toUpperCase()}</span>
                                </div>
                                <span className="text-sm text-gray-900">{user.name}</span>
                            </button>
                        ))}
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
        </>
    );
}
