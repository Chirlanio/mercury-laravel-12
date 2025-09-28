import UserAvatar from '@/Components/UserAvatar';
import { ChartBarIcon } from '@heroicons/react/24/outline';

export default function TopUsers({ users = [], title = "Usuários Mais Ativos" }) {
    if (!users || users.length === 0) {
        return (
            <div className="bg-white shadow rounded-lg">
                <div className="px-4 py-5 sm:p-6">
                    <h3 className="text-lg leading-6 font-medium text-gray-900 mb-4">
                        {title}
                    </h3>
                    <div className="text-center py-6">
                        <ChartBarIcon className="mx-auto h-12 w-12 text-gray-400" />
                        <h3 className="mt-2 text-sm font-medium text-gray-900">Nenhum dado</h3>
                        <p className="mt-1 text-sm text-gray-500">
                            Ainda não há atividade suficiente para mostrar estatísticas.
                        </p>
                    </div>
                </div>
            </div>
        );
    }

    const maxActivity = Math.max(...users.map(user => user.activity_count));

    return (
        <div className="bg-white shadow rounded-lg">
            <div className="px-4 py-5 sm:p-6">
                <h3 className="text-lg leading-6 font-medium text-gray-900 mb-4">
                    {title}
                </h3>

                <div className="space-y-4">
                    {users.map((userStat, index) => {
                        const percentage = maxActivity > 0 ? (userStat.activity_count / maxActivity) * 100 : 0;

                        return (
                            <div key={userStat.user.id} className="flex items-center space-x-4">
                                {/* Ranking */}
                                <div className="flex-shrink-0 w-6 text-center">
                                    <span className={`text-sm font-bold ${
                                        index === 0 ? 'text-yellow-600' :
                                        index === 1 ? 'text-gray-500' :
                                        index === 2 ? 'text-yellow-800' :
                                        'text-gray-400'
                                    }`}>
                                        #{index + 1}
                                    </span>
                                </div>

                                {/* Avatar e Info */}
                                <div className="flex items-center space-x-3 flex-1 min-w-0">
                                    <UserAvatar
                                        user={userStat.user}
                                        size="md"
                                        showTooltip={true}
                                    />
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-medium text-gray-900 truncate">
                                            {userStat.user.name}
                                        </p>
                                        <p className="text-sm text-gray-500 truncate">
                                            {userStat.user.email}
                                        </p>
                                    </div>
                                </div>

                                {/* Barra de atividade */}
                                <div className="flex items-center space-x-3 flex-shrink-0 w-24">
                                    <div className="flex-1 bg-gray-200 rounded-full h-2">
                                        <div
                                            className={`h-2 rounded-full transition-all duration-500 ${
                                                index === 0 ? 'bg-yellow-500' :
                                                index === 1 ? 'bg-gray-400' :
                                                index === 2 ? 'bg-yellow-600' :
                                                'bg-blue-500'
                                            }`}
                                            style={{ width: `${percentage}%` }}
                                        />
                                    </div>
                                </div>

                                {/* Contagem */}
                                <div className="flex-shrink-0 w-12 text-right">
                                    <span className="text-sm font-semibold text-gray-900">
                                        {userStat.activity_count}
                                    </span>
                                </div>
                            </div>
                        );
                    })}
                </div>

                <div className="mt-6 text-center">
                    <p className="text-xs text-gray-500">
                        Baseado na atividade dos últimos 30 dias
                    </p>
                </div>
            </div>
        </div>
    );
}