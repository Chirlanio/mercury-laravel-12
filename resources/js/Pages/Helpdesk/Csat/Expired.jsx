import { Head } from '@inertiajs/react';
import { ClockIcon, XCircleIcon } from '@heroicons/react/24/outline';

export default function Expired({ reason = 'expired', expires_at }) {
    const isExpired = reason === 'expired';

    return (
        <>
            <Head title="Link indisponível" />
            <div className="min-h-screen bg-gradient-to-b from-gray-50 to-white flex items-center justify-center px-4 py-8">
                <div className="max-w-md w-full bg-white rounded-xl shadow-lg p-6 sm:p-8 text-center">
                    <div className={`inline-flex items-center justify-center w-16 h-16 rounded-full mb-4 ${
                        isExpired ? 'bg-orange-100' : 'bg-red-100'
                    }`}>
                        {isExpired
                            ? <ClockIcon className="w-10 h-10 text-orange-600" />
                            : <XCircleIcon className="w-10 h-10 text-red-600" />}
                    </div>
                    <h1 className="text-xl sm:text-2xl font-bold text-gray-900">
                        {isExpired ? 'Link expirado' : 'Link inválido'}
                    </h1>
                    <p className="text-sm text-gray-500 mt-2">
                        {isExpired
                            ? `O prazo para avaliar este atendimento expirou em ${expires_at || 'alguns dias atrás'}.`
                            : 'Não foi possível localizar este link de avaliação. Ele pode ter sido enviado há muito tempo ou estar incorreto.'}
                    </p>
                    <p className="text-xs text-gray-400 mt-4">
                        Se você acredita que precisa avaliar este atendimento, entre em contato com o suporte.
                    </p>
                </div>
            </div>
        </>
    );
}
