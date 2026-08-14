<?php
namespace App\Models;

/**
 * Modèle RemoteWork (table `remote_work_requests`)
 */
class RemoteWork extends BaseModel
{
    protected string $table = 'remote_work_requests';
    protected array $fillable = [
        'employee_id', 'request_date', 'start_date', 'end_date',
        'reason', 'status', 'approved_by', 'rejection_reason', 'notes',
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

    public function isCancelled(): bool
    {
        return strtolower((string) $this->status) === 'cancelled';
    }

    public function getDurationDays(): int
    {
        $start = (string) ($this->start_date ?? '');
        $end   = (string) ($this->end_date ?? '');
        if ($start === '' || $end === '') {
            return 0;
        }
        $startTs = strtotime($start);
        $endTs   = strtotime($end);
        if ($startTs === false || $endTs === false || $endTs < $startTs) {
            return 0;
        }
        return (int) floor(($endTs - $startTs) / 86400) + 1;
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

    public function cancel(): bool
    {
        return $this->update($this->getKey(), ['status' => 'cancelled']);
    }
}
