import { Head, router } from "@inertiajs/react";
import { useState } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import EmployeeModal from "@/Components/EmployeeModal";
import EmployeeCreateModal from "@/Components/EmployeeCreateModal";
import DataTable from "@/Components/DataTable";
import EmployeeAvatar from "@/Components/EmployeeAvatar";
import Button from "@/Components/Button";

export default function Index({ auth, employees, positions, stores, filters }) {
    // Debug: Log the received props
    console.log('Index received props:', { positions, stores, positionsLength: positions?.length, storesLength: stores?.length });

    const [selectedEmployeeId, setSelectedEmployeeId] = useState(null);
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [isCreateModalOpen, setIsCreateModalOpen] = useState(false);

    const viewEmployee = (employee) => {
        setSelectedEmployeeId(employee.id);
        setIsModalOpen(true);
    };

    const closeModal = () => {
        setIsModalOpen(false);
        setSelectedEmployeeId(null);
    };

    const openCreateModal = () => {
        setIsCreateModalOpen(true);
    };

    const closeCreateModal = () => {
        setIsCreateModalOpen(false);
    };

    const handleEmployeeCreated = () => {
        // Recarregar a página para mostrar o novo funcionário
        router.reload();
    };

    // Definição das colunas da tabela
    const columns = [
        {
            label: "Funcionário",
            field: "name",
            sortable: true,
            render: (employee) => (
                <div className="flex items-center">
                    <div className="flex-shrink-0 mr-4">
                        <EmployeeAvatar employee={employee} size="md" />
                    </div>
                    <div>
                        <div className="text-sm font-medium text-gray-900">
                            {employee.name}
                        </div>
                        <div className="text-sm text-gray-500">
                            {employee.short_name}
                        </div>
                    </div>
                </div>
            )
        },
        {
            label: "Cargo/Nível",
            field: "position",
            sortable: false,
            render: (employee) => (
                <div>
                    <div className="text-sm text-gray-900">
                        {employee.position || 'Não informado'}
                    </div>
                    {employee.level && (
                        <div className="text-sm text-gray-500">
                            Nível: {employee.level}
                        </div>
                    )}
                </div>
            )
        },
        {
            label: "Admissão",
            field: "admission_date",
            sortable: true,
            render: (employee) => (
                <div>
                    <div className="text-sm text-gray-900">
                        {employee.admission_date || 'Não informado'}
                    </div>
                    {employee.years_of_service !== null && (
                        <div className="text-sm text-gray-500">
                            {employee.years_of_service} {employee.years_of_service === 1 ? 'ano' : 'anos'}
                        </div>
                    )}
                </div>
            )
        },
        {
            label: "Status",
            field: "status",
            sortable: false,
            render: (employee) => (
                <span className={`inline-flex px-2 text-xs font-semibold rounded-full ${
                    employee.is_active
                        ? 'bg-green-100 text-green-800'
                        : 'bg-red-100 text-red-800'
                }`}>
                    {employee.status}
                </span>
            )
        },
        {
            label: "Características",
            field: "characteristics",
            sortable: false,
            render: (employee) => (
                <div className="flex flex-wrap gap-1">
                    {employee.is_pcd && (
                        <span className="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                            PcD
                        </span>
                    )}
                    {employee.is_apprentice && (
                        <span className="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800">
                            Aprendiz
                        </span>
                    )}
                    {!employee.is_pcd && !employee.is_apprentice && (
                        <span className="text-xs text-gray-400">—</span>
                    )}
                </div>
            )
        },
        {
            label: "Ações",
            field: "actions",
            sortable: false,
            render: (employee) => (
                <Button
                    onClick={(e) => {
                        e.stopPropagation();
                        viewEmployee(employee);
                    }}
                    variant="secondary"
                    size="sm"
                    iconOnly={true}
                    icon={({ className }) => (
                        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    )}
                    id="view-employee-button"
                    title="Visualizar detalhes do funcionário"
                />
            )
        }
    ];

    return (
        <AuthenticatedLayout user={auth.user}>
            <Head title="Funcionários" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6">
                        <div className="flex justify-between items-center">
                            <div>
                                <h1 className="text-3xl font-bold text-gray-900">
                                    Funcionários
                                </h1>
                                <p className="mt-1 text-sm text-gray-600">
                                    Gerencie e visualize informações dos funcionários
                                </p>
                            </div>
                            <div>
                                <Button
                                    variant="primary"
                                    onClick={openCreateModal}
                                    icon={({ className }) => (
                                        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                        </svg>
                                    )}
                                >
                                    Novo Funcionário
                                </Button>
                            </div>
                        </div>
                    </div>

                    {/* DataTable */}
                    <DataTable
                        data={employees}
                        columns={columns}
                        searchPlaceholder="Pesquisar funcionários por nome..."
                        emptyMessage="Nenhum funcionário encontrado"
                        onRowClick={viewEmployee}
                        perPageOptions={[15, 25, 50, 100]}
                    />
                </div>
            </div>

            {/* Employee View Modal */}
            <EmployeeModal
                show={isModalOpen}
                onClose={closeModal}
                employeeId={selectedEmployeeId}
            />

            {/* Employee Create Modal */}
            <EmployeeCreateModal
                show={isCreateModalOpen}
                onClose={closeCreateModal}
                onSuccess={handleEmployeeCreated}
                positions={positions}
                stores={stores}
            />
        </AuthenticatedLayout>
    );
}
