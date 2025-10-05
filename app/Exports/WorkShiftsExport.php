<?php

namespace App\Exports;

use App\Models\WorkShift;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class WorkShiftsExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $query = WorkShift::query()
            ->with(['employee.store'])
            ->select([
                'id', 'employee_id', 'date', 'start_time', 'end_time', 'type'
            ]);

        // Aplicar filtro de funcionários
        if (!empty($this->filters['employee_ids']) && is_array($this->filters['employee_ids'])) {
            $query->whereIn('employee_id', $this->filters['employee_ids']);
        }

        // Aplicar filtro de lojas
        if (!empty($this->filters['store_ids']) && is_array($this->filters['store_ids'])) {
            $query->whereHas('employee', function ($q) {
                $q->whereIn('store_id', $this->filters['store_ids']);
            });
        }

        // Aplicar filtro de tipos
        if (!empty($this->filters['type_ids']) && is_array($this->filters['type_ids'])) {
            $query->whereIn('type', $this->filters['type_ids']);
        }

        // Aplicar filtro de período
        if (!empty($this->filters['start_date'])) {
            $query->where('date', '>=', $this->filters['start_date']);
        }

        if (!empty($this->filters['end_date'])) {
            $query->where('date', '<=', $this->filters['end_date']);
        }

        // Ordenar por data e hora de início
        $query->orderBy('date', 'desc')
              ->orderBy('start_time', 'asc');

        return $query;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Funcionário',
            'Loja',
            'Data',
            'Hora Início',
            'Hora Término',
            'Tipo de Jornada',
            'Duração (horas)',
        ];
    }

    public function map($workShift): array
    {
        // Calcular duração em horas
        $duration = '';
        if ($workShift->start_time && $workShift->end_time) {
            $start = \Carbon\Carbon::parse($workShift->start_time);
            $end = \Carbon\Carbon::parse($workShift->end_time);
            $diffInMinutes = $start->diffInMinutes($end);
            $hours = floor($diffInMinutes / 60);
            $minutes = $diffInMinutes % 60;

            // Se for compensação, adiciona sinal negativo
            $prefix = $workShift->type === 'compensar' ? '-' : '';
            $duration = $prefix . sprintf('%02d:%02d', $hours, $minutes);
        }

        return [
            $workShift->id,
            $workShift->employee?->name ?? 'N/A',
            $workShift->employee?->store?->name ?? $workShift->employee?->store_id ?? 'N/A',
            $workShift->date?->format('d/m/Y'),
            substr($workShift->start_time, 0, 5), // HH:mm
            substr($workShift->end_time, 0, 5), // HH:mm
            ucfirst($workShift->type ?? ''),
            $duration,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
