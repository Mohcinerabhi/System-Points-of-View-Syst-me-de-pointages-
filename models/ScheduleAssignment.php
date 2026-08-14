<?php
namespace App\Models;

/**
 * Modèle Affectation d'horaire (schedule_assignments).
 */
class ScheduleAssignment extends BaseModel
{
    protected string $table = 'schedule_assignments';
    protected string $primaryKey = 'id';
    protected array $fillable = ['schedule_id', 'employee_id', 'start_date', 'end_date', 'notes'];

    public function getByEmployee(int $employeeId): array
    {
        return $this->where('employee_id = ?', [$employeeId]);
    }

    public function getBySchedule(int $scheduleId): array
    {
        return $this->where('schedule_id = ?', [$scheduleId]);
    }
}
