import Modal from '@/Components/Modal';
import Button from '@/Components/Button';

export default function WorkShiftViewModal({ isOpen, onClose, workShift, onEdit }) {
    const getTypeColor = (type) => {
        const colors = {
            'abertura': 'bg-blue-100 text-blue-800',
            'fechamento': 'bg-purple-100 text-purple-800',
            'integral': 'bg-green-100 text-green-800',
            'compensar': 'bg-orange-100 text-orange-800',
        };
        return colors[type] || 'bg-gray-100 text-gray-800';
    };

    const calculateDuration = (startTime, endTime) => {
        if (!startTime || !endTime) return 'N/A';

        const [startHour, startMinute] = startTime.split(':').map(Number);
        const [endHour, endMinute] = endTime.split(':').map(Number);

        const startInMinutes = startHour * 60 + startMinute;
        const endInMinutes = endHour * 60 + endMinute;

        const diffInMinutes = endInMinutes - startInMinutes;
        const hours = Math.floor(diffInMinutes / 60);
        const minutes = diffInMinutes % 60;

        return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
    };

    const handleEdit = () => {
        if (onEdit && workShift) {
            onEdit(workShift);
        }
    };

    return (
        <Modal show={isOpen} onClose={onClose} title="Detalhes da Jornada" maxWidth="85vw">
            {workShift && <div className="space-y-6">
                {/* Cabeçalho com informações do funcionário */}
                <div className="flex items-center space-x-4">
                    <div className="flex-shrink-0">
                        <div className="h-16 w-16 rounded-full bg-indigo-100 flex items-center justify-center">
                            <svg className="h-8 w-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                    </div>
                    <div className="flex-1">
                        <h3 className="text-xl font-semibold text-gray-900">{workShift.employee_name}</h3>
                        <p className="text-gray-600">{workShift.date}</p>
                        <div className="mt-2">
                            <span className={`inline-flex px-3 py-1 text-sm font-semibold rounded-full ${getTypeColor(workShift.type)}`}>
                                {workShift.type_label}
                            </span>
                        </div>
                    </div>
                </div>

                {/* Informações detalhadas */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="bg-gray-50 p-4 rounded-lg">
                        <h4 className="text-sm font-medium text-gray-900 mb-3">
                            Informações da Data
                        </h4>
                        <div className="space-y-2 text-sm">
                            <div>
                                <span className="font-medium text-gray-600">Data:</span>
                                <span className="ml-2 text-gray-900">{workShift.date}</span>
                            </div>
                            <div>
                                <span className="font-medium text-gray-600">Tipo de Jornada:</span>
                                <span className="ml-2 text-gray-900">{workShift.type_label}</span>
                            </div>
                        </div>
                    </div>

                    <div className="bg-gray-50 p-4 rounded-lg">
                        <h4 className="text-sm font-medium text-gray-900 mb-3">
                            Horários
                        </h4>
                        <div className="space-y-2 text-sm">
                            <div>
                                <span className="font-medium text-gray-600">Hora de Início:</span>
                                <span className="ml-2 text-gray-900">{workShift.start_time}</span>
                            </div>
                            <div>
                                <span className="font-medium text-gray-600">Hora de Término:</span>
                                <span className="ml-2 text-gray-900">{workShift.end_time}</span>
                            </div>
                            <div>
                                <span className="font-medium text-gray-600">Duração:</span>
                                <span className="ml-2 text-gray-900 font-semibold">
                                    {calculateDuration(workShift.start_time, workShift.end_time)}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Ações disponíveis */}
                {onEdit && (
                    <div className="bg-blue-50 p-4 rounded-lg">
                        <h4 className="text-sm font-medium text-blue-900 mb-3">
                            Ações Disponíveis
                        </h4>
                        <div className="flex flex-wrap gap-3">
                            <button
                                onClick={handleEdit}
                                className="inline-flex items-center justify-center px-4 py-2 bg-yellow-600 text-white text-sm font-medium rounded-md hover:bg-yellow-700 transition-colors border border-yellow-600 hover:border-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500"
                            >
                                <svg className="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                Editar Jornada
                            </button>
                        </div>
                    </div>
                )}

                <div className="flex justify-end">
                    <button
                        type="button"
                        onClick={onClose}
                        className="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded transition-colors"
                    >
                        Fechar
                    </button>
                </div>
            </div>}
        </Modal>
    );
}
