<?php

namespace App\Exports;

use App\Models\TrainingCourse;
use App\Models\TrainingCourseEnrollment;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TrainingReportExport implements FromCollection, WithHeadings, WithStyles, WithTitle
{
    use Exportable;

    public function __construct(
        protected string $type = 'overview',
        protected array $filters = [],
    ) {}

    public function title(): string
    {
        return match ($this->type) {
            'by-employee' => 'Por Colaborador',
            'by-store' => 'Por Loja',
            'by-course' => 'Por Curso',
            default => 'Visão Geral',
        };
    }

    public function headings(): array
    {
        return match ($this->type) {
            'by-employee' => ['Colaborador', 'Inscrições', 'Conclusões'],
            'by-store' => ['Loja', 'Colaboradores Treinados', 'Inscrições', 'Conclusões', 'Taxa de Conclusão (%)'],
            'by-course' => ['Curso', 'Inscritos', 'Concluídos', 'Desistências', 'Taxa de Conclusão (%)'],
            default => ['Métrica', 'Valor'],
        };
    }

    public function collection()
    {
        return match ($this->type) {
            'by-employee' => $this->byEmployee(),
            'by-store' => $this->byStore(),
            'by-course' => $this->byCourse(),
            default => $this->overview(),
        };
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    private function overview(): \Illuminate\Support\Collection
    {
        $total = TrainingCourse::active()->count();
        $published = TrainingCourse::active()->published()->count();
        $enrollments = TrainingCourseEnrollment::count();
        $completions = TrainingCourseEnrollment::completed()->count();

        return collect([
            ['Total de Cursos', $total],
            ['Cursos Publicados', $published],
            ['Total de Inscrições', $enrollments],
            ['Total de Conclusões', $completions],
            ['Taxa de Conclusão (%)', $enrollments > 0 ? round(($completions / $enrollments) * 100, 1) : 0],
        ]);
    }

    private function byEmployee(): \Illuminate\Support\Collection
    {
        return TrainingCourseEnrollment::with('user')
            ->selectRaw('user_id, COUNT(*) as total, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completions', ['completed'])
            ->groupBy('user_id')
            ->get()
            ->map(fn ($r) => [
                $r->user?->name ?? '-',
                $r->total,
                $r->completions,
            ]);
    }

    private function byStore(): \Illuminate\Support\Collection
    {
        return TrainingCourseEnrollment::join('users', 'users.id', '=', 'training_course_enrollments.user_id')
            ->join('stores', 'stores.code', '=', 'users.store_id')
            ->selectRaw(
                'stores.name, COUNT(DISTINCT users.id) as employees, COUNT(*) as total,
                 SUM(CASE WHEN training_course_enrollments.status = ? THEN 1 ELSE 0 END) as completions',
                ['completed']
            )
            ->groupBy('stores.name')
            ->get()
            ->map(fn ($r) => [
                $r->name,
                $r->employees,
                $r->total,
                $r->completions,
                $r->total > 0 ? round(($r->completions / $r->total) * 100, 1) : 0,
            ]);
    }

    private function byCourse(): \Illuminate\Support\Collection
    {
        return TrainingCourse::active()
            ->withCount([
                'enrollments',
                'enrollments as completed_count' => fn ($q) => $q->completed(),
                'enrollments as dropped_count' => fn ($q) => $q->forStatus(TrainingCourseEnrollment::STATUS_DROPPED),
            ])
            ->get()
            ->map(fn ($c) => [
                $c->title,
                $c->enrollments_count,
                $c->completed_count,
                $c->dropped_count,
                $c->enrollments_count > 0 ? round(($c->completed_count / $c->enrollments_count) * 100, 1) : 0,
            ]);
    }
}
