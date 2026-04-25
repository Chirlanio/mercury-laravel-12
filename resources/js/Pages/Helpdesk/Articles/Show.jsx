import { Head, router, Link } from '@inertiajs/react';
import { useState } from 'react';
import {
    BookOpenIcon,
    HandThumbUpIcon,
    HandThumbDownIcon,
    EyeIcon,
} from '@heroicons/react/24/outline';
import Button from '@/Components/Button';
import PageHeader from '@/Components/Shared/PageHeader';

/**
 * Public view of a Knowledge Base article. Pre-rendered HTML comes from
 * the server (league/commonmark on save) so we can dangerouslySetInnerHTML
 * without a client-side parser.
 */
export default function Show({ article }) {
    const [feedbackSent, setFeedbackSent] = useState(null);

    const sendFeedback = (helpful) => {
        router.post(route('helpdesk.articles.feedback', article.id), { helpful }, {
            preserveScroll: true,
            onSuccess: () => setFeedbackSent(helpful ? 'helpful' : 'not_helpful'),
        });
    };

    return (
        <>
            <Head title={article.title} />
            <div className="py-6 sm:py-12">
                <div className="max-w-3xl mx-auto px-3 sm:px-6 lg:px-8">
                    <PageHeader
                        title={article.title}
                        icon={BookOpenIcon}
                        subtitle={article.summary || null}
                        actions={[
                            { type: 'back', label: 'Voltar ao Helpdesk', href: route('helpdesk.index') },
                        ]}
                    />

                    {/* Metadados do artigo */}
                    <div className="flex flex-wrap items-center gap-3 mb-4 sm:mb-6 text-xs text-gray-500">
                        {article.department_name && (
                            <span>Departamento: <strong>{article.department_name}</strong></span>
                        )}
                        {article.category_name && (
                            <span>Categoria: <strong>{article.category_name}</strong></span>
                        )}
                        {article.author_name && (
                            <span>Autor: <strong>{article.author_name}</strong></span>
                        )}
                        {article.updated_at && (
                            <span>Atualizado em <strong>{article.updated_at}</strong></span>
                        )}
                        <span className="flex items-center gap-1">
                            <EyeIcon className="w-3 h-3" /> {article.view_count}
                        </span>
                    </div>

                    {/* Content */}
                    <article className="bg-white shadow-sm rounded-lg p-4 sm:p-6 lg:p-8 prose prose-sm sm:prose max-w-none"
                        dangerouslySetInnerHTML={{ __html: article.content_html || '' }} />

                    {/* Feedback */}
                    <div className="bg-white shadow-sm rounded-lg p-4 sm:p-6 mt-4 sm:mt-6">
                        {feedbackSent ? (
                            <p className="text-sm text-gray-600 text-center">
                                ✓ Obrigado pelo feedback — ele ajuda a melhorar a base de conhecimento.
                            </p>
                        ) : (
                            <div className="flex flex-col sm:flex-row items-center justify-between gap-3">
                                <span className="text-sm text-gray-700">Este artigo foi útil?</span>
                                <div className="flex gap-2">
                                    <Button variant="outline" size="sm" icon={HandThumbUpIcon}
                                        onClick={() => sendFeedback(true)}>
                                        Sim ({article.helpful_count})
                                    </Button>
                                    <Button variant="outline" size="sm" icon={HandThumbDownIcon}
                                        onClick={() => sendFeedback(false)}>
                                        Não ({article.not_helpful_count})
                                    </Button>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
