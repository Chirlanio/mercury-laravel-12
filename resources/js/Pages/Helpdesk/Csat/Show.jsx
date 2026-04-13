import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { StarIcon as StarSolid } from '@heroicons/react/24/solid';
import { StarIcon as StarOutline, LifebuoyIcon } from '@heroicons/react/24/outline';
import Button from '@/Components/Button';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';

/**
 * Public CSAT form. Unauthenticated — accessed via signed URL only.
 * Minimal UI with 5-star rating + optional comment. No layout wrapper
 * because this page must render even when the requester isn't logged in.
 */
export default function Show({ token, ticket, expires_at }) {
    const [rating, setRating] = useState(0);
    const [hover, setHover] = useState(0);
    const [comment, setComment] = useState('');
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});

    const submit = () => {
        if (!rating) {
            setErrors({ rating: 'Escolha uma nota.' });
            return;
        }
        setProcessing(true);
        setErrors({});

        // The signed URL is on the GET endpoint; we need to re-use the
        // same signature for POST. Laravel's URL::temporarySignedRoute
        // signs the entire URL (including the token path param), and the
        // `signed` middleware validates POST too using the same signature
        // from the query string. We grab the query from window.location.
        const url = new URL(window.location.href);
        const search = url.search; // keeps ?signature=... &expires=...

        router.post(`/helpdesk/csat/${token}${search}`, { rating, comment }, {
            onError: (errs) => setErrors(errs),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <>
            <Head title="Avalie seu atendimento" />
            <div className="min-h-screen bg-gradient-to-b from-indigo-50 to-white flex items-center justify-center px-4 py-8">
                <div className="max-w-md w-full bg-white rounded-xl shadow-lg p-6 sm:p-8">
                    <div className="text-center mb-6">
                        <div className="inline-flex items-center justify-center w-14 h-14 rounded-full bg-indigo-100 mb-3">
                            <LifebuoyIcon className="w-8 h-8 text-indigo-600" />
                        </div>
                        <h1 className="text-xl sm:text-2xl font-bold text-gray-900">
                            Como foi seu atendimento?
                        </h1>
                        {ticket?.id && (
                            <p className="text-sm text-gray-500 mt-1">
                                Chamado #{ticket.id}{ticket.title ? ` — ${ticket.title}` : ''}
                            </p>
                        )}
                    </div>

                    {/* 5-star rating */}
                    <div className="mb-4">
                        <InputLabel value="Sua nota" className="text-center block" />
                        <div className="flex justify-center gap-1 sm:gap-2 mt-2">
                            {[1, 2, 3, 4, 5].map(n => {
                                const filled = (hover || rating) >= n;
                                const Icon = filled ? StarSolid : StarOutline;
                                return (
                                    <button
                                        key={n}
                                        type="button"
                                        onClick={() => setRating(n)}
                                        onMouseEnter={() => setHover(n)}
                                        onMouseLeave={() => setHover(0)}
                                        className="p-1 transition-transform hover:scale-110 focus:outline-none"
                                        aria-label={`${n} estrela${n > 1 ? 's' : ''}`}
                                    >
                                        <Icon className={`w-10 h-10 sm:w-12 sm:h-12 ${filled ? 'text-yellow-400' : 'text-gray-300'}`} />
                                    </button>
                                );
                            })}
                        </div>
                        <p className="text-center text-xs text-gray-500 mt-2">
                            {rating === 0 && 'Clique nas estrelas para avaliar'}
                            {rating === 1 && 'Muito insatisfeito'}
                            {rating === 2 && 'Insatisfeito'}
                            {rating === 3 && 'Neutro'}
                            {rating === 4 && 'Satisfeito'}
                            {rating === 5 && 'Muito satisfeito'}
                        </p>
                        <InputError message={errors.rating} className="text-center" />
                    </div>

                    {/* Optional comment */}
                    <div className="mb-4">
                        <InputLabel value="Comentário (opcional)" />
                        <textarea
                            className="mt-1 w-full border-gray-300 rounded-lg text-sm"
                            rows={3}
                            value={comment}
                            onChange={e => setComment(e.target.value)}
                            placeholder="Conte-nos mais sobre sua experiência..."
                            maxLength={1000}
                        />
                        <InputError message={errors.comment} />
                    </div>

                    <Button variant="primary" onClick={submit} loading={processing} className="w-full">
                        Enviar avaliação
                    </Button>

                    <p className="mt-4 text-xs text-gray-400 text-center">
                        Link válido até {expires_at}.
                    </p>
                </div>
            </div>
        </>
    );
}
