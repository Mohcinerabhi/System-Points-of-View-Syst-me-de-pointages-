<?php
namespace App\Models;

/**
 * Modèle ShiftAssignment (table `shift_assignments`)
 */
class ShiftAssignment extends BaseModel
{
    protected string $table = 'shift_assignments';
    protected array $fillable = ['shift_id', 'employee_id', 'start_date', 'end_date', 'notes'];

    public function getByEmployee(int $employeeId): array
    {
        return $this->where('employee_id = ?', [$employeeId]);
    }

    public function getByShift(int $shiftId): array
    {
        return $this->where('shift_id = ?', [$shiftId]);
    }
}
