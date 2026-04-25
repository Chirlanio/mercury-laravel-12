import { Head, router, Link } from '@inertiajs/react';
import { useState, useMemo } from 'react';
import {
    BookOpenIcon,
    EyeIcon,
    PencilSquareIcon,
    CheckIcon,
} from '@heroicons/react/24/outline';
import Button from '@/Components/Button';
import PageHeader from '@/Components/Shared/PageHeader';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import Checkbox from '@/Components/Checkbox';

/**
 * KB article editor. Markdown is written in a plain textarea on the
 * left and rendered on the right in a live-preview pane using a naive
 * client-side parser (same subset as commonmark: headings, bold/italic,
 * links, lists, code). The server re-renders with league/commonmark on
 * save, so the final output may differ slightly — this is just a
 * convenience preview.
 */
export default function Edit({ article, departments = [], categories = [] }) {
    const isNew = !article;

    const [data, setData] = useState({
        title: article?.title || '',
        summary: article?.summary || '',
        content_md: article?.content_md || '',
        department_id: article?.department_id || '',
        category_id: article?.category_id || '',
        is_published: article?.is_published || false,
    });
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});
    const [showPreview, setShowPreview] = useState(false);

    const availableCategories = useMemo(
        () => categories.filter(c => !data.department_id || c.department_id === Number(data.department_id)),
        [categories, data.department_id],
    );

    const previewHtml = useMemo(() => renderMarkdownPreview(data.content_md), [data.content_md]);

    const handleSave = () => {
        setProcessing(true);
        setErrors({});

        const payload = {
            ...data,
            category_id: data.category_id || null,
            department_id: data.department_id || null,
        };

        const opts = {
            preserveScroll: true,
            onError: (errs) => setErrors(errs),
            onFinish: () => setProcessing(false),
        };

        if (isNew) {
            router.post(route('helpdesk.articles.store'), payload, opts);
        } else {
            router.put(route('helpdesk.articles.update', article.id), payload, opts);
        }
    };

    return (
        <>
            <Head title={isNew ? 'Novo artigo' : `Editar: ${article.title}`} />
            <div className="py-6 sm:py-12">
                <div className="max-w-full mx-auto px-3 sm:px-6 lg:px-8">
                    <PageHeader
                        title={isNew ? 'Novo artigo' : 'Editar artigo'}
                        icon={BookOpenIcon}
                        actions={[
                            { type: 'back', label: 'Voltar para a lista', href: route('helpdesk.articles.index') },
                            {
                                label: showPreview ? 'Editar' : 'Pré-visualizar',
                                icon: showPreview ? PencilSquareIcon : EyeIcon,
                                variant: 'info-soft',
                                onClick: () => setShowPreview(v => !v),
                            },
                            {
                                type: 'create',
                                label: 'Salvar',
                                icon: CheckIcon,
                                onClick: handleSave,
                                loading: processing,
                            },
                        ]}
                    />
                    {/* Metadata card */}
                    <div className="bg-white shadow-sm rounded-lg p-3 sm:p-4 mb-4 sm:mb-6">
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div className="sm:col-span-2">
                                <InputLabel value="Título *" />
                                <TextInput
                                    className="mt-1 w-full"
                                    value={data.title}
                                    onChange={e => setData(p => ({ ...p, title: e.target.value }))}
                                    placeholder="Ex.: Como solicitar férias"
                                />
                                <InputError message={errors.title} />
                            </div>
                            <div className="sm:col-span-2">
                                <InputLabel value="Resumo (opcional)" />
                                <TextInput
                                    className="mt-1 w-full"
                                    value={data.summary}
                                    onChange={e => setData(p => ({ ...p, summary: e.target.value }))}
                                    placeholder="Frase curta mostrada na busca"
                                />
                                <InputError message={errors.summary} />
                            </div>
                            <div>
                                <InputLabel value="Departamento (opcional)" />
                                <select
                                    className="mt-1 w-full border-gray-300 rounded-lg text-sm"
                                    value={data.department_id}
                                    onChange={e => setData(p => ({ ...p, department_id: e.target.value, category_id: '' }))}
                                >
                                    <option value="">Qualquer</option>
                                    {departments.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
                                </select>
                                <InputError message={errors.department_id} />
                            </div>
                            <div>
                                <InputLabel value="Categoria (opcional)" />
                                <select
                                    className="mt-1 w-full border-gray-300 rounded-lg text-sm"
                                    value={data.category_id || ''}
                                    onChange={e => setData(p => ({ ...p, category_id: e.target.value }))}
                                    disabled={!data.department_id}
                                >
                                    <option value="">Qualquer</option>
                                    {availableCategories.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                                </select>
                                <InputError message={errors.category_id} />
                            </div>
                            <label className="flex items-center gap-2 sm:col-span-2 mt-2">
                                <Checkbox
                                    checked={data.is_published}
                                    onChange={e => setData(p => ({ ...p, is_published: e.target.checked }))}
                                />
                                <span className="text-sm text-gray-700">
                                    Publicado — visível para todos os usuários
                                </span>
                            </label>
                        </div>
                    </div>

                    {/* Editor / Preview */}
                    <div className="bg-white shadow-sm rounded-lg overflow-hidden">
                        <div className="px-4 sm:px-6 py-3 border-b border-gray-200 flex items-center justify-between">
                            <h2 className="text-sm sm:text-base font-semibold text-gray-900">
                                {showPreview ? 'Pré-visualização' : 'Conteúdo (Markdown)'}
                            </h2>
                            <span className="text-xs text-gray-400">
                                {data.content_md.length} caracteres
                            </span>
                        </div>
                        <div className="p-4 sm:p-6">
                            {showPreview ? (
                                <div className="prose prose-sm sm:prose max-w-none"
                                    dangerouslySetInnerHTML={{ __html: previewHtml }} />
                            ) : (
                                <>
                                    <textarea
                                        className="w-full border-gray-300 rounded-lg text-xs sm:text-sm font-mono"
                                        rows={20}
                                        value={data.content_md}
                                        onChange={e => setData(p => ({ ...p, content_md: e.target.value }))}
                                        placeholder={'# Título do artigo\n\nEscreva o conteúdo em markdown.\n\n## Subtítulo\n\n- item 1\n- item 2\n\n**negrito** e *itálico*.'}
                                    />
                                    <InputError message={errors.content_md} />
                                    <p className="mt-2 text-xs text-gray-500">
                                        Suporta: headings (#), bold (**), italic (*), listas, links, code blocks. Rendered
                                        com league/commonmark no backend — HTML inline é escapado por segurança.
                                    </p>
                                </>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

/**
 * Minimal client-side markdown preview. Intentionally naive — just enough
 * to show headings, bold, italic, lists, and links while editing. The
 * authoritative render happens server-side via league/commonmark on save.
 *
 * The output is also escaped defensively — we never trust raw HTML in the
 * editor preview because the same textarea content will be parsed by
 * commonmark on save and stored as pre-rendered HTML.
 */
function renderMarkdownPreview(md = '') {
    if (!md) return '<p class="text-gray-400 italic">Preview aparecerá aqui…</p>';

    // Escape HTML first
    let html = md
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    // Code blocks (```...```)
    html = html.replace(/```([\s\S]*?)```/g, (_, code) =>
        `<pre class="bg-gray-100 rounded p-3 text-xs overflow-x-auto"><code>${code}</code></pre>`);

    // Inline code
    html = html.replace(/`([^`]+)`/g, '<code class="bg-gray-100 rounded px-1 text-xs">$1</code>');

    // Headings
    html = html.replace(/^###### (.+)$/gm, '<h6 class="text-sm font-bold mt-3">$1</h6>');
    html = html.replace(/^##### (.+)$/gm, '<h5 class="text-sm font-bold mt-3">$1</h5>');
    html = html.replace(/^#### (.+)$/gm, '<h4 class="text-base font-bold mt-3">$1</h4>');
    html = html.replace(/^### (.+)$/gm, '<h3 class="text-lg font-bold mt-4">$1</h3>');
    html = html.replace(/^## (.+)$/gm, '<h2 class="text-xl font-bold mt-4">$1</h2>');
    html = html.replace(/^# (.+)$/gm, '<h1 class="text-2xl font-bold mt-4">$1</h1>');

    // Bold & italic
    html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/\*([^*]+)\*/g, '<em>$1</em>');

    // Links [text](url)
    html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" class="text-indigo-600 underline">$1</a>');

    // Unordered lists
    html = html.replace(/^(?:- .+\n?)+/gm, (match) => {
        const items = match.trim().split('\n').map(l => l.replace(/^- /, '')).map(i => `<li>${i}</li>`).join('');
        return `<ul class="list-disc pl-5 my-2">${items}</ul>`;
    });

    // Ordered lists
    html = html.replace(/^(?:\d+\. .+\n?)+/gm, (match) => {
        const items = match.trim().split('\n').map(l => l.replace(/^\d+\. /, '')).map(i => `<li>${i}</li>`).join('');
        return `<ol class="list-decimal pl-5 my-2">${items}</ol>`;
    });

    // Paragraphs — wrap remaining text separated by blank lines
    html = html.split(/\n{2,}/).map(block => {
        const b = block.trim();
        if (b.startsWith('<h') || b.startsWith('<ul') || b.startsWith('<ol') || b.startsWith('<pre')) return b;
        if (!b) return '';
        return `<p class="my-2">${b.replace(/\n/g, '<br>')}</p>`;
    }).join('\n');

    return html;
}
