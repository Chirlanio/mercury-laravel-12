import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'react-toastify';
import {
    PaperAirplaneIcon,
    PlusIcon,
    SparklesIcon,
    ChatBubbleLeftRightIcon,
    UserCircleIcon,
    DocumentArrowUpIcon,
    DocumentTextIcon,
    HandThumbUpIcon,
    HandThumbDownIcon,
} from '@heroicons/react/24/outline';
import {
    HandThumbUpIcon as HandThumbUpSolid,
    HandThumbDownIcon as HandThumbDownSolid,
} from '@heroicons/react/24/solid';
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
    taneiaApi = { chat_url: '', tenant_id: 'default' },
}) {
    const { props } = usePage();
    const currentUser = props.auth?.user;
    const { hasPermission } = usePermissions();
    const canSend = hasPermission(PERMISSIONS.SEND_TANEIA_MESSAGES);
    const canManage = hasPermission(PERMISSIONS.MANAGE_TANEIA);

    const [conversations, setConversations] = useState(initialConversations);
    const [activeId, setActiveId] = useState(initialActiveId);
    const [messages, setMessages] = useState(initialMessages);
    const [draft, setDraft] = useState('');
    const [sending, setSending] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState(null);

    const messagesEndRef = useRef(null);
    const inputRef = useRef(null);
    const fileInputRef = useRef(null);

    // Sync props → state when Inertia navigates between conversations
    useEffect(() => {
        setConversations(initialConversations);
    }, [initialConversations]);

    useEffect(() => {
        setActiveId(initialActiveId);
        setMessages(initialMessages);
    }, [initialActiveId, initialMessages]);

    // Auto-scroll apenas quando:
    //   1) novas mensagens foram adicionadas (count aumentou), ou
    //   2) a bolha streaming da assistente esta crescendo.
    // NAO rolar em edicoes de mensagens existentes (ex: mudanca de rating).
    const scrollTrigger = useMemo(() => {
        const streamingContent = messages.find((m) => m._streaming)?.content?.length ?? 0;
        return `${messages.length}:${streamingContent}:${sending ? 1 : 0}`;
    }, [messages, sending]);

    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [scrollTrigger]);

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

    const createConversationJson = async () => {
        const response = await fetch(route('taneia.store'), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({}),
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const data = await response.json();
        return data.conversation;
    };

    const handleSend = async (e) => {
        e?.preventDefault();
        const content = draft.trim();
        if (!content || sending || !canSend) return;

        setError(null);
        setSending(true);

        let targetId = activeId;

        // Sem conversa ativa: cria uma via JSON e ja envia a mensagem no
        // mesmo fluxo (sem double-click nem reload intermediario).
        if (!targetId) {
            try {
                const created = await createConversationJson();
                targetId = created.id;
                setActiveId(created.id);
                setConversations((prev) => [created, ...prev]);
                window.history.replaceState({}, '', route('taneia.show', created.id));
            } catch (err) {
                setSending(false);
                setError('Não foi possível criar a conversa.');
                return;
            }
        }

        // Monta o historico para enviar ao LLM, pulando qualquer bolha
        // otimista/streaming que ainda nao foi persistida.
        const historyForLlm = messages
            .filter((m) => !m._pending && !m._streaming && m.content)
            .map((m) => ({ role: m.role, content: m.content }));
        historyForLlm.push({ role: 'user', content });

        // Otimistas: mensagem do usuario + bolha de streaming da TaneIA
        const optimisticUserId = `tmp-user-${Date.now()}`;
        const streamingAssistantId = `tmp-assistant-${Date.now()}`;
        setMessages((prev) => [
            ...prev,
            {
                id: optimisticUserId,
                role: 'user',
                content,
                created_at: new Date().toISOString(),
                _pending: true,
            },
            {
                id: streamingAssistantId,
                role: 'assistant',
                content: '',
                created_at: new Date().toISOString(),
                _streaming: true,
            },
        ]);
        setDraft('');

        let accumulated = '';
        let accumulatedSources = [];

        try {
            // 1) STREAMING direto ao FastAPI. CORS liberado no microservico.
            //    Sem cookies — `credentials: 'omit'` evita que o navegador
            //    bloqueie a request por causa do allow_origins="*".
            const streamRes = await fetch(taneiaApi.chat_url, {
                method: 'POST',
                credentials: 'omit',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'text/event-stream',
                    'X-Tenant-Id': taneiaApi.tenant_id,
                },
                body: JSON.stringify({
                    conversation_id: String(targetId),
                    messages: historyForLlm,
                }),
            });

            if (!streamRes.ok || !streamRes.body) {
                throw new Error(`Streaming HTTP ${streamRes.status}`);
            }

            const reader = streamRes.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';
            let streamError = null;

            while (true) {
                const { value, done } = await reader.read();
                if (done) break;
                buffer += decoder.decode(value, { stream: true });

                let sep;
                while ((sep = buffer.indexOf('\n\n')) !== -1) {
                    const frame = buffer.slice(0, sep);
                    buffer = buffer.slice(sep + 2);

                    const dataLine = frame
                        .split('\n')
                        .find((l) => l.startsWith('data: '));
                    if (!dataLine) continue;

                    let payload;
                    try {
                        payload = JSON.parse(dataLine.slice(6));
                    } catch {
                        continue;
                    }

                    if (payload.content !== undefined) {
                        accumulated += payload.content;
                        // Atualiza somente a bolha streaming; autoscroll dispara
                        // via useEffect que observa `messages`.
                        setMessages((prev) =>
                            prev.map((m) =>
                                m.id === streamingAssistantId
                                    ? { ...m, content: accumulated }
                                    : m,
                            ),
                        );
                    } else if (Array.isArray(payload.sources)) {
                        accumulatedSources = payload.sources;
                        setMessages((prev) =>
                            prev.map((m) =>
                                m.id === streamingAssistantId
                                    ? { ...m, sources: payload.sources }
                                    : m,
                            ),
                        );
                    } else if (payload.error) {
                        streamError = payload.error;
                    } else if (payload.done) {
                        // Fim do stream — loop interno sai naturalmente.
                    }
                }
            }

            if (streamError) throw new Error(streamError);
            if (!accumulated.trim()) throw new Error('Resposta vazia do modelo.');

            // 2) SAVE silencioso no Laravel. Envia o par (pergunta, resposta)
            //    para persistir atomicamente e gerar titulo na primeira mensagem.
            const saveRes = await fetch(route('taneia.send-message', targetId), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    user_content: content,
                    assistant_content: accumulated,
                    sources: accumulatedSources.length > 0 ? accumulatedSources : null,
                }),
            });

            if (!saveRes.ok) throw new Error('Falha ao salvar no historico.');
            const data = await saveRes.json();

            // 3) SUBSTITUI otimistas pelos registros reais do banco.
            //    Sources agora sao persistidas — virao no data.assistant_message.
            setMessages((prev) =>
                prev.map((m) => {
                    if (m.id === optimisticUserId) return data.user_message;
                    if (m.id === streamingAssistantId) {
                        return { ...data.assistant_message, _streaming: false };
                    }
                    return m;
                }),
            );

            if (data.conversation) {
                setConversations((prev) => {
                    const others = prev.filter((c) => c.id !== data.conversation.id);
                    return [data.conversation, ...others];
                });
            }
        } catch (err) {
            setError('Desculpe, estou com dificuldades de conexao agora. Tente novamente.');
            // Remove otimistas e restaura o draft para o usuario reenviar.
            setMessages((prev) =>
                prev.filter(
                    (m) => m.id !== optimisticUserId && m.id !== streamingAssistantId,
                ),
            );
            setDraft(content);
        } finally {
            setSending(false);
            setTimeout(() => inputRef.current?.focus(), 0);
        }
    };

    const handlePickFile = () => {
        if (!canManage || uploading) return;
        fileInputRef.current?.click();
    };

    const handleUpload = async (e) => {
        const file = e.target.files?.[0];
        // Reseta o input para permitir reenviar o mesmo arquivo caso necessario
        if (fileInputRef.current) fileInputRef.current.value = '';
        if (!file) return;

        const name = file.name.toLowerCase();
        if (!/\.(pdf|csv|xlsx)$/.test(name)) {
            toast.error('Apenas PDF, CSV ou XLSX sao suportados.');
            return;
        }

        setUploading(true);
        const formData = new FormData();
        formData.append('file', file);

        try {
            const response = await fetch(route('taneia.documents.upload'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(data.message || `HTTP ${response.status}`);
            }

            const meta = data.document || {};
            const summary =
                meta.mode === 'pandas'
                    ? `planilha com ${meta.rows ?? '?'} linhas e ${meta.cols ?? '?'} colunas`
                    : `${meta.pages ?? '?'} pagina(s), ${meta.chunks_indexed ?? '?'} chunks`;
            toast.success(`"${meta.filename || file.name}" processado — ${summary}.`);
        } catch (err) {
            toast.error(err.message || 'Falha ao enviar o documento.');
        } finally {
            setUploading(false);
        }
    };

    const handleRate = async (messageId, newRating) => {
        // Otimista: atualiza state imediatamente, reverte em caso de erro.
        let previous = null;
        setMessages((prev) =>
            prev.map((m) => {
                if (m.id !== messageId) return m;
                previous = m.rating ?? null;
                return { ...m, rating: newRating };
            }),
        );

        try {
            const res = await fetch(route('taneia.messages.rate', messageId), {
                method: 'PATCH',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ rating: newRating }),
            });
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
        } catch {
            // Rollback
            setMessages((prev) =>
                prev.map((m) => (m.id === messageId ? { ...m, rating: previous } : m)),
            );
            toast.error('Falha ao registrar avaliacao.');
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
                                            {messages.map((message) => {
                                                // Enquanto a bolha streaming estiver vazia, escondemos ela —
                                                // o TypingBubble (dots) abaixo assume o papel de indicador.
                                                if (message._streaming && !message.content) return null;
                                                return (
                                                    <MessageBubble
                                                        key={message.id}
                                                        message={message}
                                                        currentUser={currentUser}
                                                        onRate={canSend ? handleRate : null}
                                                    />
                                                );
                                            })}
                                            {sending && messages.some((m) => m._streaming && !m.content) && (
                                                <TypingBubble />
                                            )}
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
                                        {canManage && (
                                            <>
                                                <button
                                                    type="button"
                                                    onClick={handlePickFile}
                                                    disabled={uploading || sending}
                                                    title={uploading ? 'Processando arquivo...' : 'Anexar PDF, CSV ou XLSX'}
                                                    className="shrink-0 inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 hover:text-indigo-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                                >
                                                    {uploading ? (
                                                        <span className="w-4 h-4 border-2 border-indigo-600 border-t-transparent rounded-full animate-spin" />
                                                    ) : (
                                                        <DocumentArrowUpIcon className="w-5 h-5" />
                                                    )}
                                                </button>
                                                <input
                                                    ref={fileInputRef}
                                                    type="file"
                                                    accept=".pdf,.csv,.xlsx,application/pdf,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                                    onChange={handleUpload}
                                                    className="hidden"
                                                />
                                            </>
                                        )}
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

function MessageBubble({ message, currentUser, onRate }) {
    const isUser = message.role === 'user';
    const canRate = !isUser && !message._streaming && !String(message.id).startsWith('tmp-') && onRate;

    const toggleRate = (value) => {
        // Clicar no rating ativo remove (toggle para null); senao aplica.
        const next = message.rating === value ? null : value;
        onRate(message.id, next);
    };

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
                {!isUser && Array.isArray(message.sources) && message.sources.length > 0 && (
                    <SourcePills sources={message.sources} />
                )}
                <div className="flex items-center gap-2 mt-1">
                    <span className="text-[11px] text-gray-400">
                        {formatTimestamp(message.created_at)}
                    </span>
                    {canRate && (
                        <div className="flex items-center gap-0.5">
                            <button
                                type="button"
                                onClick={() => toggleRate(1)}
                                title="Boa resposta"
                                className={`p-1 rounded hover:bg-gray-100 transition-colors ${
                                    message.rating === 1 ? 'text-green-600' : 'text-gray-400 hover:text-green-600'
                                }`}
                            >
                                {message.rating === 1 ? (
                                    <HandThumbUpSolid className="w-3.5 h-3.5" />
                                ) : (
                                    <HandThumbUpIcon className="w-3.5 h-3.5" />
                                )}
                            </button>
                            <button
                                type="button"
                                onClick={() => toggleRate(-1)}
                                title="Resposta ruim"
                                className={`p-1 rounded hover:bg-gray-100 transition-colors ${
                                    message.rating === -1 ? 'text-red-600' : 'text-gray-400 hover:text-red-600'
                                }`}
                            >
                                {message.rating === -1 ? (
                                    <HandThumbDownSolid className="w-3.5 h-3.5" />
                                ) : (
                                    <HandThumbDownIcon className="w-3.5 h-3.5" />
                                )}
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

function SourcePills({ sources }) {
    // Dedup defensivo no cliente caso o backend repita algum par (arquivo, pagina).
    const unique = [];
    const seen = new Set();
    for (const s of sources) {
        const key = `${s.filename}::${s.page}`;
        if (seen.has(key)) continue;
        seen.add(key);
        unique.push(s);
    }

    return (
        <div className="mt-2 flex flex-wrap gap-1.5 max-w-[85%]">
            <span className="text-[11px] text-gray-500 self-center mr-1">Fontes:</span>
            {unique.map((source, idx) => (
                <span
                    key={`${source.filename}-${source.page}-${idx}`}
                    className="inline-flex items-center gap-1 px-2 py-0.5 text-[11px] font-medium bg-purple-50 text-purple-700 border border-purple-200 rounded-full"
                    title={`${source.filename} — pagina ${source.page}`}
                >
                    <DocumentTextIcon className="w-3 h-3" />
                    <span className="truncate max-w-[180px]">{source.filename}</span>
                    {source.page ? (
                        <span className="text-purple-500">· p.{source.page}</span>
                    ) : null}
                </span>
            ))}
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
