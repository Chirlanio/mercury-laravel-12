import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
    PaperAirplaneIcon,
    PlusIcon,
    SparklesIcon,
    ChatBubbleLeftRightIcon,
    UserCircleIcon,
} from '@heroicons/react/24/outline';
import { usePermissions, PERMISSIONS } from '@/Hooks/usePermissions';
import Button from '@/Components/Button';
import EmptyState from '@/Components/Shared/EmptyState';

const csrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

function formatTimestamp(value) {
    if (!value) return '';
    try {
        return new Date(value).toLocaleString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch (e) {
        return '';
    }
}

export default function Index({
    conversations: initialConversations = [],
    activeConversationId: initialActiveId = null,
    messages: initialMessages = [],
}) {
    const { props } = usePage();
    const currentUser = props.auth?.user;
    const { hasPermission } = usePermissions();
    const canSend = hasPermission(PERMISSIONS.SEND_TANEIA_MESSAGES);

    const [conversations, setConversations] = useState(initialConversations);
    const [activeId, setActiveId] = useState(initialActiveId);
    const [messages, setMessages] = useState(initialMessages);
    const [draft, setDraft] = useState('');
    const [sending, setSending] = useState(false);
    const [error, setError] = useState(null);

    const messagesEndRef = useRef(null);
    const inputRef = useRef(null);

    // Sync props → state when Inertia navigates between conversations
    useEffect(() => {
        setConversations(initialConversations);
    }, [initialConversations]);

    useEffect(() => {
        setActiveId(initialActiveId);
        setMessages(initialMessages);
    }, [initialActiveId, initialMessages]);

    // Auto-scroll on new messages / sending state change
    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages, sending]);

    const activeConversation = useMemo(
        () => conversations.find((c) => c.id === activeId) || null,
        [conversations, activeId],
    );

    const handleSelectConversation = (conversationId) => {
        if (conversationId === activeId) return;
        router.visit(route('taneia.show', conversationId), {
            preserveState: false,
            preserveScroll: false,
        });
    };

    const handleNewConversation = () => {
        if (!canSend) return;
        router.post(
            route('taneia.store'),
            {},
            {
                preserveScroll: false,
                onError: () => setError('Não foi possível criar a conversa.'),
            },
        );
    };

    const handleSend = async (e) => {
        e?.preventDefault();
        const content = draft.trim();
        if (!content || sending || !canSend) return;

        setError(null);

        // If no active conversation, create one first, then the user will
        // be redirected to it. We simply ask for a new conversation and
        // let the user re-send. Keeping this path explicit avoids a double
        // round-trip on the first message.
        if (!activeId) {
            handleNewConversation();
            return;
        }

        setSending(true);

        // Optimistic append of the user's message
        const optimistic = {
            id: `tmp-${Date.now()}`,
            role: 'user',
            content,
            created_at: new Date().toISOString(),
            _pending: true,
        };
        setMessages((prev) => [...prev, optimistic]);
        setDraft('');

        try {
            const response = await fetch(
                route('taneia.send-message', activeId),
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ content }),
                },
            );

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();

            setMessages((prev) => {
                const withoutOptimistic = prev.filter((m) => m.id !== optimistic.id);
                return [...withoutOptimistic, data.user_message, data.assistant_message];
            });

            // Bump the conversation to the top of the sidebar with updated title
            if (data.conversation) {
                setConversations((prev) => {
                    const others = prev.filter((c) => c.id !== data.conversation.id);
                    return [
                        {
                            id: data.conversation.id,
                            title: data.conversation.title,
                            updated_at: data.conversation.updated_at,
                        },
                        ...others,
                    ];
                });
            }
        } catch (err) {
            setError('Falha ao enviar a mensagem. Tente novamente.');
            // Roll back the optimistic message and restore the draft
            setMessages((prev) => prev.filter((m) => m.id !== optimistic.id));
            setDraft(content);
        } finally {
            setSending(false);
            // Return focus to the input
            setTimeout(() => inputRef.current?.focus(), 0);
        }
    };

    const handleKeyDown = (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSend(e);
        }
    };

    return (
        <>
            <Head title="TaneIA" />
            <div className="py-6">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="bg-white shadow-sm rounded-lg overflow-hidden">
                        <div className="flex h-[calc(100vh-12rem)] min-h-[500px]">
                            {/* Sidebar — Conversation history */}
                            <aside className="w-72 border-r border-gray-200 flex flex-col bg-gray-50">
                                <div className="p-4 border-b border-gray-200 bg-white">
                                    <div className="flex items-center gap-2 mb-3">
                                        <SparklesIcon className="w-6 h-6 text-indigo-600" />
                                        <h2 className="text-lg font-semibold text-gray-900">TaneIA</h2>
                                    </div>
                                    {canSend && (
                                        <Button
                                            variant="primary"
                                            size="sm"
                                            icon={PlusIcon}
                                            onClick={handleNewConversation}
                                            className="w-full justify-center"
                                        >
                                            Nova conversa
                                        </Button>
                                    )}
                                </div>

                                <div className="flex-1 overflow-y-auto">
                                    {conversations.length === 0 ? (
                                        <div className="p-4 text-center text-sm text-gray-500">
                                            Nenhuma conversa ainda.
                                        </div>
                                    ) : (
                                        <ul className="divide-y divide-gray-200">
                                            {conversations.map((conversation) => {
                                                const isActive = conversation.id === activeId;
                                                return (
                                                    <li key={conversation.id}>
                                                        <button
                                                            type="button"
                                                            onClick={() => handleSelectConversation(conversation.id)}
                                                            className={`w-full text-left px-4 py-3 transition-colors flex items-start gap-2 ${
                                                                isActive
                                                                    ? 'bg-indigo-50 border-l-4 border-indigo-600'
                                                                    : 'hover:bg-gray-100 border-l-4 border-transparent'
                                                            }`}
                                                        >
                                                            <ChatBubbleLeftRightIcon className="w-5 h-5 text-gray-400 mt-0.5 shrink-0" />
                                                            <div className="flex-1 min-w-0">
                                                                <p className="text-sm font-medium text-gray-900 truncate">
                                                                    {conversation.title || 'Nova conversa'}
                                                                </p>
                                                                <p className="text-xs text-gray-500 mt-0.5">
                                                                    {formatTimestamp(conversation.updated_at)}
                                                                </p>
                                                            </div>
                                                        </button>
                                                    </li>
                                                );
                                            })}
                                        </ul>
                                    )}
                                </div>
                            </aside>

                            {/* Main — messages */}
                            <main className="flex-1 flex flex-col">
                                {/* Header */}
                                <header className="px-6 py-4 border-b border-gray-200 bg-white flex items-center justify-between">
                                    <div>
                                        <h3 className="text-base font-semibold text-gray-900">
                                            {activeConversation?.title || 'Fale com a TaneIA'}
                                        </h3>
                                        <p className="text-xs text-gray-500 mt-0.5">
                                            Sua assistente virtual de inteligência artificial
                                        </p>
                                    </div>
                                    {sending && (
                                        <div className="flex items-center gap-2 text-xs text-indigo-600">
                                            <span className="relative flex h-2 w-2">
                                                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-indigo-400 opacity-75" />
                                                <span className="relative inline-flex rounded-full h-2 w-2 bg-indigo-500" />
                                            </span>
                                            TaneIA está processando...
                                        </div>
                                    )}
                                </header>

                                {/* Messages */}
                                <div className="flex-1 overflow-y-auto px-6 py-4 bg-gradient-to-b from-gray-50 to-white">
                                    {messages.length === 0 && !sending ? (
                                        <div className="h-full flex items-center justify-center">
                                            <EmptyState
                                                icon={SparklesIcon}
                                                title="Comece uma conversa"
                                                description={
                                                    activeId
                                                        ? 'Digite uma mensagem abaixo para conversar com a TaneIA.'
                                                        : 'Crie uma nova conversa ou escolha uma no histórico.'
                                                }
                                            />
                                        </div>
                                    ) : (
                                        <div className="space-y-4 max-w-3xl mx-auto">
                                            {messages.map((message) => (
                                                <MessageBubble
                                                    key={message.id}
                                                    message={message}
                                                    currentUser={currentUser}
                                                />
                                            ))}
                                            {sending && <TypingBubble />}
                                            <div ref={messagesEndRef} />
                                        </div>
                                    )}
                                </div>

                                {/* Input */}
                                <div className="border-t border-gray-200 bg-white px-6 py-4">
                                    {error && (
                                        <div className="mb-2 px-3 py-2 bg-red-50 border border-red-200 rounded text-sm text-red-700">
                                            {error}
                                        </div>
                                    )}
                                    {!canSend && (
                                        <div className="mb-2 px-3 py-2 bg-amber-50 border border-amber-200 rounded text-sm text-amber-800">
                                            Você tem permissão apenas para visualizar o histórico da TaneIA.
                                        </div>
                                    )}
                                    <form onSubmit={handleSend} className="flex items-end gap-2">
                                        <textarea
                                            ref={inputRef}
                                            rows={1}
                                            value={draft}
                                            onChange={(e) => setDraft(e.target.value)}
                                            onKeyDown={handleKeyDown}
                                            disabled={sending || !canSend}
                                            placeholder={
                                                !canSend
                                                    ? 'Sem permissão para enviar mensagens'
                                                    : sending
                                                        ? 'Aguardando resposta da TaneIA...'
                                                        : 'Pergunte qualquer coisa à TaneIA... (Enter para enviar, Shift+Enter para nova linha)'
                                            }
                                            className="flex-1 resize-none rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm disabled:bg-gray-100 disabled:text-gray-500"
                                            style={{ maxHeight: '120px' }}
                                        />
                                        <Button
                                            type="submit"
                                            variant="primary"
                                            size="md"
                                            icon={PaperAirplaneIcon}
                                            loading={sending}
                                            disabled={sending || !draft.trim() || !canSend}
                                        >
                                            Enviar
                                        </Button>
                                    </form>
                                </div>
                            </main>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

function MessageBubble({ message, currentUser }) {
    const isUser = message.role === 'user';

    return (
        <div className={`flex items-start gap-3 ${isUser ? 'flex-row-reverse' : ''}`}>
            <div
                className={`shrink-0 w-9 h-9 rounded-full flex items-center justify-center ${
                    isUser ? 'bg-indigo-600 text-white' : 'bg-purple-100 text-purple-600'
                }`}
            >
                {isUser ? (
                    <UserCircleIcon className="w-7 h-7" />
                ) : (
                    <SparklesIcon className="w-5 h-5" />
                )}
            </div>
            <div className={`flex-1 min-w-0 ${isUser ? 'flex flex-col items-end' : ''}`}>
                <div className="text-xs font-medium text-gray-600 mb-1">
                    {isUser ? currentUser?.name || 'Você' : 'TaneIA'}
                </div>
                <div
                    className={`inline-block px-4 py-2.5 rounded-2xl max-w-[85%] whitespace-pre-wrap break-words text-sm leading-relaxed ${
                        isUser
                            ? 'bg-indigo-600 text-white rounded-tr-sm'
                            : 'bg-white border border-gray-200 text-gray-800 rounded-tl-sm shadow-sm'
                    } ${message._pending ? 'opacity-70' : ''}`}
                >
                    {message.content}
                </div>
                <div className="text-[11px] text-gray-400 mt-1">
                    {formatTimestamp(message.created_at)}
                </div>
            </div>
        </div>
    );
}

function TypingBubble() {
    return (
        <div className="flex items-start gap-3">
            <div className="shrink-0 w-9 h-9 rounded-full flex items-center justify-center bg-purple-100 text-purple-600">
                <SparklesIcon className="w-5 h-5" />
            </div>
            <div>
                <div className="text-xs font-medium text-gray-600 mb-1">TaneIA</div>
                <div className="inline-flex gap-1 px-4 py-3 bg-white border border-gray-200 rounded-2xl rounded-tl-sm shadow-sm">
                    <span className="w-2 h-2 bg-gray-400 rounded-full animate-bounce [animation-delay:-0.3s]" />
                    <span className="w-2 h-2 bg-gray-400 rounded-full animate-bounce [animation-delay:-0.15s]" />
                    <span className="w-2 h-2 bg-gray-400 rounded-full animate-bounce" />
                </div>
            </div>
        </div>
    );
}
