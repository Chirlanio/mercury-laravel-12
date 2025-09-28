import {
    ExclamationTriangleIcon,
    InformationCircleIcon,
    CheckCircleIcon,
    XCircleIcon
} from '@heroicons/react/24/outline';

export default function AlertCard({ alerts = [] }) {
    if (!alerts || alerts.length === 0) {
        return null;
    }

    const getAlertIcon = (type) => {
        switch (type) {
            case 'success':
                return <CheckCircleIcon className="h-5 w-5 text-green-400" />;
            case 'warning':
                return <ExclamationTriangleIcon className="h-5 w-5 text-yellow-400" />;
            case 'error':
                return <XCircleIcon className="h-5 w-5 text-red-400" />;
            case 'info':
            default:
                return <InformationCircleIcon className="h-5 w-5 text-blue-400" />;
        }
    };

    const getAlertColors = (type) => {
        switch (type) {
            case 'success':
                return {
                    bg: 'bg-green-50',
                    border: 'border-green-200',
                    title: 'text-green-800',
                    message: 'text-green-700',
                    button: 'bg-green-100 text-green-800 hover:bg-green-200'
                };
            case 'warning':
                return {
                    bg: 'bg-yellow-50',
                    border: 'border-yellow-200',
                    title: 'text-yellow-800',
                    message: 'text-yellow-700',
                    button: 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200'
                };
            case 'error':
                return {
                    bg: 'bg-red-50',
                    border: 'border-red-200',
                    title: 'text-red-800',
                    message: 'text-red-700',
                    button: 'bg-red-100 text-red-800 hover:bg-red-200'
                };
            case 'info':
            default:
                return {
                    bg: 'bg-blue-50',
                    border: 'border-blue-200',
                    title: 'text-blue-800',
                    message: 'text-blue-700',
                    button: 'bg-blue-100 text-blue-800 hover:bg-blue-200'
                };
        }
    };

    return (
        <div className="space-y-4">
            {alerts.map((alert, index) => {
                const colors = getAlertColors(alert.type);

                return (
                    <div
                        key={index}
                        className={`rounded-md border p-4 ${colors.bg} ${colors.border}`}
                    >
                        <div className="flex">
                            <div className="flex-shrink-0">
                                {getAlertIcon(alert.type)}
                            </div>
                            <div className="ml-3 flex-1">
                                <h3 className={`text-sm font-medium ${colors.title}`}>
                                    {alert.title}
                                </h3>
                                <div className={`mt-2 text-sm ${colors.message}`}>
                                    <p>{alert.message}</p>
                                </div>
                                {alert.action_url && alert.action_text && (
                                    <div className="mt-4">
                                        <div className="-mx-2 -my-1.5 flex">
                                            <a
                                                href={alert.action_url}
                                                className={`rounded-md px-2 py-1.5 text-sm font-medium transition-colors ${colors.button}`}
                                            >
                                                {alert.action_text}
                                            </a>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}