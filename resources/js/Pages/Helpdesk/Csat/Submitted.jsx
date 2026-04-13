import { Head } from '@inertiajs/react';
import { StarIcon as StarSolid } from '@heroicons/react/24/solid';
import { StarIcon as StarOutline, CheckCircleIcon } from '@heroicons/react/24/outline';

export default function Submitted({ rating, already_submitted = false }) {
    return (
        <>
            <Head title="Avaliação enviada" />
            <div className="min-h-screen bg-gradient-to-b from-indigo-50 to-white flex items-center justify-center px-4 py-8">
                <div className="max-w-md w-full bg-white rounded-xl shadow-lg p-6 sm:p-8 text-center">
                    <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 mb-4">
                        <CheckCircleIcon className="w-10 h-10 text-green-600" />
                    </div>
                    <h1 className="text-xl sm:text-2xl font-bold text-gray-900">
                        {already_submitted ? 'Você já avaliou este chamado' : 'Obrigado pela avaliação!'}
                    </h1>
                    <p className="text-sm text-gray-500 mt-2">
                        {already_submitted
                            ? 'Sua avaliação anterior foi registrada.'
                            : 'Sua resposta foi registrada e ajuda a melhorar nosso atendimento.'}
                    </p>

                    {rating && (
                        <div className="flex justify-center gap-1 mt-4">
                            {[1, 2, 3, 4, 5].map(n => {
                                const filled = rating >= n;
                                const Icon = filled ? StarSolid : StarOutline;
                                return (
                                    <Icon
                                        key={n}
                                        className={`w-8 h-8 ${filled ? 'text-yellow-400' : 'text-gray-300'}`}
                                    />
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
