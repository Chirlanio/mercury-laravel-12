import { Head } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import {
    ChartBarIcon,
    AcademicCapIcon,
    UserGroupIcon,
    BuildingStorefrontIcon,
    BookOpenIcon,
} from '@heroicons/react/24/outline';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import Button from '@/Components/Button';

const REPORT_TYPES = [
    { key: 'overview', label: 'Visão Geral', icon: ChartBarIcon },
    { key: 'by-course', label: 'Por Curso', icon: BookOpenIcon },
    { key: 'by-employee', label: 'Por Funcionário', icon: UserGroupIcon },
];

export default function Reports() {
    const [activeType, setActiveType] = useState('overview');
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');

    const fetchReport = (type) => {
        setLoading(true);
        const params = new URLSearchParams({ type });
        if (dateFrom) params.append('date_from', dateFrom);
        if (dateTo) params.append('date_to', dateTo);

        fetch(`${route('training-reports.index')}?${params}`)
            .then(res => res.json())
            .then(result => {
                setData(result);
                setLoading(false);
            })
            .catch(() => setLoading(false));
    };

    useEffect(() => {
        fetchReport(activeType);
    }, [activeType]);

    const overviewCards = data && activeType === 'overview' ? [
        { label: 'Total Cursos', value: data.total_courses ?? 0, icon: BookOpenIcon, color: 'indigo' },
        { label: 'Publicados', value: data.published_courses ?? 0, icon: AcademicCapIcon, color: 'green' },
        { label: 'Inscrições', value: data.total_enrollments ?? 0, icon: UserGroupIcon, color: 'blue' },
        { label: 'Conclusões', value: data.total_completions ?? 0, icon: AcademicCapIcon, color: 'emerald' },
        { label: 'Taxa de Conclusão', value: data.completion_rate ?? 0, format: 'percentage', icon: ChartBarIcon, color: 'purple' },
        { label: 'Horas Totais', value: data.total_hours ?? 0, format: 'number', icon: AcademicCapIcon, color: 'orange' },
    ] : [];

    return (
        <>
            <Head title="Relatórios de Treinamento" />
            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <h1 className="text-2xl font-bold text-gray-900 mb-6">Relatórios de Treinamento</h1>

                    {/* Tabs */}
                    <div className="flex items-center gap-2 mb-6">
                        {REPORT_TYPES.map(type => (
                            <Button
                                key={type.key}
                                variant={activeType === type.key ? 'primary' : 'outline'}
                                size="sm"
                                icon={type.icon}
                                onClick={() => setActiveType(type.key)}
                            >
                                {type.label}
                            </Button>
                        ))}
                    </div>

                    {/* Date filters */}
                    <div className="bg-white shadow-sm rounded-lg p-4 mb-6">
                        <div className="flex items-center gap-4">
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">De</label>
                                <input type="date" value={dateFrom} onChange={e => setDateFrom(e.target.value)}
                                    className="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                            <div>
                                <label className="block text-xs font-medium text-gray-700 mb-1">Ate</label>
                                <input type="date" value={dateTo} onChange={e => setDateTo(e.target.value)}
                                    className="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            </div>
                            <div className="pt-5">
                                <Button variant="primary" size="sm" onClick={() => fetchReport(activeType)}>
                                    Filtrar
                                </Button>
                            </div>
                        </div>
                    </div>

                    {/* Overview Stats */}
                    {activeType === 'overview' && <StatisticsGrid cards={overviewCards} loading={loading} />}

                    {/* By Course Table */}
                    {activeType === 'by-course' && !loading && Array.isArray(data) && (
                        <div className="bg-white shadow-sm rounded-lg overflow-hidden">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Curso</th>
                                        <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Inscritos</th>
                                        <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Concluídos</th>
                                        <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Desistências</th>
                                        <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Taxa</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200">
                                    {data.map((row, i) => (
                                        <tr key={i}>
                                            <td className="px-6 py-4 text-sm text-gray-900">{row.title}</td>
                                            <td className="px-6 py-4 text-sm text-center text-gray-600">{row.enrolled}</td>
                                            <td className="px-6 py-4 text-sm text-center text-green-600 font-medium">{row.completed}</td>
                                            <td className="px-6 py-4 text-sm text-center text-red-600">{row.dropped}</td>
                                            <td className="px-6 py-4 text-sm text-center font-medium">{row.completion_rate}%</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {/* By Employee Table */}
                    {activeType === 'by-employee' && !loading && Array.isArray(data) && (
                        <div className="bg-white shadow-sm rounded-lg overflow-hidden">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Funcionário</th>
                                        <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Inscrições</th>
                                        <th className="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Conclusões</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200">
                                    {data.map((row, i) => (
                                        <tr key={i}>
                                            <td className="px-6 py-4 text-sm text-gray-900">{row.user_name}</td>
                                            <td className="px-6 py-4 text-sm text-center text-gray-600">{row.total_enrollments}</td>
                                            <td className="px-6 py-4 text-sm text-center text-green-600 font-medium">{row.completions}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {loading && (
                        <div className="text-center py-12 text-gray-500">Carregando...</div>
                    )}
                </div>
            </div>
        </>
    );
}
