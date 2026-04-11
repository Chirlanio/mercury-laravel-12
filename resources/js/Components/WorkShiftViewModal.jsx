import StandardModal from '@/Components/StandardModal';
import {
    CalendarDaysIcon,
    ClockIcon,
    PencilSquareIcon,
    UserIcon,
} from '@heroicons/react/24/outline';

export default function WorkShiftViewModal({ show, onClose, workShift, onEdit }) {
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

        return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}h`;
    };

    return (
        <StandardModal
            show={show}
            onClose={onClose}
            title="Detalhes da Jornada"
            subtitle={workShift?.date}
            headerColor="bg-gray-800"
            headerIcon={<CalendarDaysIcon className="h-6 w-6" />}
            maxWidth="2xl"
            footer={
                <StandardModal.Footer onCancel={onClose}>
                    {onEdit && (
                        <button
                            onClick={() => onEdit(workShift)}
                            className="inline-flex items-center px-4 py-2 bg-yellow-600 text-white text-sm font-bold rounded-lg hover:bg-yellow-700 transition"
                        >
                            <PencilSquareIcon className="w-4 h-4 mr-2" />
                            Editar Jornada
                        </button>
                    )}
                </StandardModal.Footer>
            }
        >
            {workShift && (
                <div className="space-y-6">
                    <div className="flex items-center gap-4 bg-gray-50 p-4 rounded-xl border border-gray-100">
                        <div className="h-14 w-14 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600">
                            <UserIcon className="h-8 w-8" />
                        </div>
                        <div>
                            <h3 className="text-lg font-bold text-gray-900">{workShift.employee_name}</h3>
                            <div className="mt-1">
                                <span className={`inline-flex px-2.5 py-0.5 text-xs font-bold uppercase rounded-full ${getTypeColor(workShift.type)}`}>
                                    {workShift.type_label}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <StandardModal.Section title="Informações" icon={<CalendarDaysIcon className="h-4 w-4" />}>
                            <div className="space-y-3">
                                <StandardModal.Field label="Data" value={workShift.date} />
                                <StandardModal.Field label="Tipo" value={workShift.type_label} />
                            </div>
                        </StandardModal.Section>

                        <StandardModal.Section title="Horários" icon={<ClockIcon className="h-4 w-4" />}>
                            <div className="space-y-3">
                                <div className="grid grid-cols-2 gap-4">
                                    <StandardModal.Field label="Início" value={workShift.start_time} />
                                    <StandardModal.Field label="Término" value={workShift.end_time} />
                                </div>
                                <StandardModal.Field 
                                    label="Duração Total" 
                                    value={calculateDuration(workShift.start_time, workShift.end_time)} 
                                    highlight
                                />
                            </div>
                        </StandardModal.Section>
                    </div>
                </div>
            )}
        </StandardModal>
    );
}
