<?php

namespace App\Services;

use App\Models\Absence;
use App\Models\OvertimeRecord;

class PersonnelMovementIntegrationService
{
    /**
     * Get count of unjustified absences (faltas não justificadas).
     */
    public function getEmployeePendingAbsences(int $employeeId): int
    {
        return Absence::active()
            ->unjustified()
            ->forEmployee($employeeId)
            ->count();
    }

    /**
     * Get count of approved overtime records (folgas pendentes).
     * Each approved overtime record represents a compensatory day off owed.
     */
    public function getEmployeeDaysOff(int $employeeId): int
    {
        return OvertimeRecord::active()
            ->forEmployee($employeeId)
            ->where('status', 'approved')
            ->count();
    }

    /**
     * Get total unpaid overtime hours (pending + approved, not closed) as HH:MM string.
     */
    public function getEmployeePendingOvertimeHours(int $employeeId): string
    {
        $totalHours = OvertimeRecord::active()
            ->forEmployee($employeeId)
            ->whereIn('status', ['pending', 'approved'])
            ->sum('hours');

        $hours = (int) floor($totalHours);
        $minutes = (int) round(($totalHours - $hours) * 60);

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    /**
     * Get all integration data for an employee at once.
     */
    public function getEmployeeIntegrationData(int $employeeId): array
    {
        return [
            'fouls' => $this->getEmployeePendingAbsences($employeeId),
            'days_off' => $this->getEmployeeDaysOff($employeeId),
            'overtime_hours' => $this->getEmployeePendingOvertimeHours($employeeId),
        ];
    }
}
