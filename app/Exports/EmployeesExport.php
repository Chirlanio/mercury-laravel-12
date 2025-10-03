<?php

namespace App\Exports;

use App\Models\Employee;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Facades\Request;

class EmployeesExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $query = Employee::query()
            ->with(['employeeStatus', 'position', 'store'])
            ->select([
                'id', 'name', 'short_name', 'cpf',
                'admission_date', 'dismissal_date', 'position_id', 'store_id',
                'birth_date', 'level', 'status_id', 'is_pcd', 'is_apprentice'
            ]);

        // Aplicar filtros
        if (!empty($this->filters['search'])) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->filters['search']}%")
                  ->orWhere('short_name', 'like', "%{$this->filters['search']}%");
            });
        }

        if (!empty($this->filters['store'])) {
            $query->where('store_id', $this->filters['store']);
        }

        if (!empty($this->filters['status'])) {
            $query->where('status_id', $this->filters['status']);
        }

        $sortField = $this->filters['sort'] ?? 'name';
        $sortDirection = $this->filters['direction'] ?? 'asc';

        $allowedSortFields = ['name', 'admission_date', 'level'];
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nome',
            'Nome Curto',
            'CPF',
            'Data de Admissão',
            'Data de Demissão',
            'Cargo',
            'Nível',
            'Loja',
            'Status',
            'Data de Nascimento',
            'Idade',
            'Tempo de Serviço',
            'PCD',
            'Aprendiz',
        ];
    }

    public function map($employee): array
    {
        return [
            $employee->id,
            $employee->name,
            $employee->short_name,
            $employee->formatted_cpf ?? $employee->cpf,
            $employee->admission_date?->format('d/m/Y'),
            $employee->dismissal_date?->format('d/m/Y'),
            $employee->position?->name ?? 'Não informado',
            $employee->level,
            $employee->store?->name ?? $employee->store_id,
            $employee->employeeStatus?->description_name ?? 'Não informado',
            $employee->birth_date?->format('d/m/Y'),
            $employee->birth_date ? $employee->age : null,
            $employee->years_of_service,
            $employee->is_pcd ? 'Sim' : 'Não',
            $employee->is_apprentice ? 'Sim' : 'Não',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
