<?php
namespace App\Models;

/**
 * Modèle OvertimeRequest (table `overtime_requests`)
 */
class OvertimeRequest extends BaseModel
{
    protected string $table = 'overtime_requests';
    protected array $fillable = [
        'employee_id', 'requested_date', 'start_time', 'end_time',
        'estimated_hours', 'reason', 'status', 'approved_by', 'rejection_reason', 'notes',
    ];

    public function getEmployee(): ?Employee
    {
        $employeeId = $this->employee_id;
        if ($employeeId === null) {
            return null;
        }
        $row = $this->fetchOneRaw('SELECT * FROM `employees` WHERE `id` = :id LIMIT 1', [':id' => (int) $employeeId]);
        if ($row === null) {
            return null;
        }
        $employee = new Employee();
        $employee->fill($row);
        return $employee;
    }

    public function getApprover(): ?array
    {
        $approverId = $this->approved_by;
        if ($approverId === null) {
            return null;
        }
        return $this->fetchOneRaw('SELECT * FROM `users` WHERE `id` = :id LIMIT 1', [':id' => (int) $approverId]);
    }

    public function isPending(): bool
    {
        return strtolower((string) $this->status) === 'pending';
    }

    public function isApproved(): bool
    {
        return strtolower((string) $this->status) === 'approved';
    }

    public function isRejected(): bool
    {
        return strtolower((string) $this->status) === 'rejected';
    }

    public function approve(int $approvedBy): bool
    {
        return $this->update($this->getKey(), [
            'status'      => 'approved',
            'approved_by' => $approvedBy,
        ]);
    }

    public function reject(int $approvedBy, ?string $reason = null): bool
    {
        return $this->update($this->getKey(), [
            'status'           => 'rejected',
            'approved_by'      => $approvedBy,
            'rejection_reason' => $reason,
        ]);
    }
}
