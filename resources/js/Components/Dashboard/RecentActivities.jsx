import UserAvatar from '@/Components/UserAvatar';
import {
    ArrowRightOnRectangleIcon,
    ArrowLeftOnRectangleIcon,
    PlusIcon,
    PencilIcon,
    TrashIcon,
    EyeIcon,
    ExclamationTriangleIcon,
    CommandLineIcon
} from '@heroicons/react/24/outline';

export default function RecentActivities({ activities = [], title = "Atividades Recentes" }) {
    const getActionIcon = (action) => {
        const iconClass = "h-4 w-4";

        switch (action) {
            case 'login':
                return <ArrowRightOnRectangleIcon className={`${iconClass} text-green-500`} />;
            case 'logout':
                return <ArrowLeftOnRectangleIcon className={`${iconClass} text-gray-500`} />;
            case 'create':
                return <PlusIcon className={`${iconClass} text-blue-500`} />;
            case 'update':
                return <PencilIcon className={`${iconClass} text-yellow-500`} />;
            case 'delete':
                return <TrashIcon className={`${iconClass} text-red-500`} />;
            case 'access':
                return <EyeIcon className={`${iconClass} text-indigo-500`} />;
            case 'access_denied':
                return <ExclamationTriangleIcon className={`${iconClass} text-red-600`} />;
            default:
                return <CommandLineIcon className={`${iconClass} text-gray-400`} />;
        }
    };

    const getActionColor = (action) => {
        switch (action) {
            case 'login':
                return 'bg-green-100 border-green-200';
            case 'logout':
                return 'bg-gray-100 border-gray-200';
            case 'create':
                return 'bg-blue-100 border-blue-200';
            case 'update':
                return 'bg-yellow-100 border-yellow-200';
            case 'delete':
                return 'bg-red-100 border-red-200';
            case 'access':
                return 'bg-indigo-100 border-indigo-200';
            case 'access_denied':
                return 'bg-red-100 border-red-300';
            default:
                return 'bg-gray-100 border-gray-200';
        }
    };

    if (!activities || activities.length === 0) {
        return (
            <div className="bg-white shadow rounded-lg">
                <div className="px-4 py-5 sm:p-6">
                    <h3 className="text-lg leading-6 font-medium text-gray-900 mb-4">
                        {title}
                    </h3>
                    <div className="text-center py-6">
                        <CommandLineIcon className="mx-auto h-12 w-12 text-gray-400" />
                        <h3 className="mt-2 text-sm font-medium text-gray-900">Nenhuma atividade</h3>
                        <p className="mt-1 text-sm text-gray-500">
                            Ainda não há atividades registradas no sistema.
                        </p>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="bg-white shadow rounded-lg">
            <div className="px-4 py-5 sm:p-6">
                <h3 className="text-lg leading-6 font-medium text-gray-900 mb-4">
                    {title}
                </h3>

                <div className="flow-root">
                    <ul className="-mb-8">
                        {activities.map((activity, activityIdx) => (
                            <li key={activity.id}>
                                <div className="relative pb-8">
                                    {activityIdx !== activities.length - 1 ? (
                                        <span
                                            className="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200"
                                            aria-hidden="true"
                                        />
                                    ) : null}
                                    <div className="relative flex space-x-3">
                                        <div>
                                            <span className={`h-8 w-8 rounded-full border-2 flex items-center justify-center ring-8 ring-white ${getActionColor(activity.action)}`}>
                                                {getActionIcon(activity.action)}
                                            </span>
                                        </div>
                                        <div className="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                            <div>
                                                <div className="flex items-center space-x-2">
                                                    {activity.user && (
                                                        <UserAvatar
                                                            user={activity.user}
                                                            size="sm"
                                                            showTooltip={true}
                                                        />
                                                    )}
                                                    <div>
                                                        <p className="text-sm text-gray-900">
                                                            {activity.user ? (
                                                                <span className="font-medium">
                                                                    {activity.user.name}
                                                                </span>
                                                            ) : (
                                                                <span className="font-medium text-gray-500">
                                                                    Sistema
                                                                </span>
                                                            )}
                                                        </p>
                                                        <p className="text-sm text-gray-500">
                                                            {activity.description}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="text-right text-sm whitespace-nowrap text-gray-500">
                                                <time dateTime={activity.created_at}>
                                                    {activity.time_ago}
                                                </time>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                </div>

                <div className="mt-6">
                    <a
                        href="/activity-logs"
                        className="w-full flex justify-center items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-colors"
                    >
                        Ver todas as atividades
                    </a>
                </div>
            </div>
        </div>
    );
}