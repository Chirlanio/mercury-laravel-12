import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { ChartBarIcon, ClockIcon, CheckCircleIcon } from '@heroicons/react/24/outline';
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, PieChart, Pie, Cell, Legend } from 'recharts';
import StatisticsGrid from '@/Components/Shared/StatisticsGrid';
import Button from '@/Components/Button';

const COLORS = ['#4f46e5', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6', '#06b6d4'];

export default function Reports({ volumeByDay, slaCompliance, distributionByDepartment, averageResolutionTime, departments, filters }) {
    const statisticsCards = [
        { label: 'Taxa SLA', value: slaCompliance?.compliance_rate, format: 'percentage', icon: CheckCircleIcon, color: slaCompliance?.compliance_rate >= 80 ? 'green' : 'red' },
        { label: 'Dentro do SLA', value: slaCompliance?.within_sla, icon: CheckCircleIcon, color: 'green' },
        { label: 'SLA Violado', value: slaCompliance?.breached, icon: ClockIcon, color: 'red' },
        { label: 'Tempo Médio', value: `${averageResolutionTime}h`, icon: ClockIcon, color: 'blue' },
    ];

    return (
        <>
            <Head title="Relatórios Helpdesk" />
            <div className="py-12">
                <div className="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between mb-6">
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">Relatórios do Helpdesk</h1>
                            <p className="text-sm text-gray-500">Métricas e análises de chamados</p>
                        </div>
                        <Button variant="outline" onClick={() => window.history.back()}>Voltar</Button>
                    </div>

                    <StatisticsGrid cards={statisticsCards} className="mb-6" />

                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {/* Volume by Day */}
                        <div className="bg-white shadow-sm rounded-lg p-6">
                            <h3 className="text-sm font-semibold text-gray-900 mb-4">Volume de Chamados por Dia</h3>
                            {volumeByDay?.length > 0 ? (
                                <ResponsiveContainer width="100%" height={300}>
                                    <LineChart data={volumeByDay}>
                                        <CartesianGrid strokeDasharray="3 3" />
                                        <XAxis dataKey="date" tick={{ fontSize: 11 }} />
                                        <YAxis tick={{ fontSize: 11 }} />
                                        <Tooltip />
                                        <Line type="monotone" dataKey="count" stroke="#4f46e5" strokeWidth={2} dot={{ r: 3 }} name="Chamados" />
                                    </LineChart>
                                </ResponsiveContainer>
                            ) : (
                                <p className="text-sm text-gray-400 text-center py-12">Sem dados para o período.</p>
                            )}
                        </div>

                        {/* Distribution by Department */}
                        <div className="bg-white shadow-sm rounded-lg p-6">
                            <h3 className="text-sm font-semibold text-gray-900 mb-4">Distribuição por Departamento</h3>
                            {distributionByDepartment?.length > 0 ? (
                                <ResponsiveContainer width="100%" height={300}>
                                    <PieChart>
                                        <Pie data={distributionByDepartment} dataKey="count" nameKey="department" cx="50%" cy="50%"
                                            outerRadius={100} label={({ department, percent }) => `${department} ${(percent * 100).toFixed(0)}%`}>
                                            {distributionByDepartment.map((_, idx) => (
                                                <Cell key={idx} fill={COLORS[idx % COLORS.length]} />
                                            ))}
                                        </Pie>
                                        <Tooltip />
                                        <Legend />
                                    </PieChart>
                                </ResponsiveContainer>
                            ) : (
                                <p className="text-sm text-gray-400 text-center py-12">Sem dados para o período.</p>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
