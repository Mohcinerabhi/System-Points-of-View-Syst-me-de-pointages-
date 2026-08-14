<?php
/**
 * Modèle Demande de congé (table `leave_requests`)
 * Fichier: models/LeaveRequest.php
 *
 * @property int         $id
 * @property int         $employee_id
 * @property string      $leave_type
 * @property string      $start_date
 * @property string      $end_date
 * @property string|null $reason
 * @property string      $status
 * @property int|null    $approved_by
 * @property string|null $rejection_reason
 * @property string      $created_at
 * @property string      $updated_at
 */

declare(strict_types=1);

namespace App\Models;

class LeaveRequest extends BaseModel
{
    /**
     * @var string
     */
    protected string $table = 'leave_requests';

    /**
     * @var string[]
     */
    protected array $fillable = [
        'employee_id',
        'leave_type',
        'start_date',
        'end_date',
        'reason',
        'status',
        'approved_by',
        'rejection_reason',
    ];

    /**
     * Récupère l'employé demandeur.
     */
    public function getEmployee(): ?Employee
    {
        $employeeId = $this->employee_id;
        if ($employeeId === null) {
            return null;
        }

        $row = $this->fetchOneRaw(
            'SELECT * FROM `employees` WHERE `id` = :id LIMIT 1',
            [':id' => (int) $employeeId]
        );

        if ($row === null) {
            return null;
        }

        $employee = new Employee();
        $employee->fill($row);

        return $employee;
    }

    /**
     * Récupère une page de demandes de congé avec le nom de l'employé.
     *
     * Effectue une jointure avec la table `employees` afin de renvoyer
     * `first_name`, `last_name` et `employee_code` avec chaque enregistrement.
     *
     * @param array<string, mixed> $conditions   Conditions WHERE (colonne => valeur).
     * @param array<string>        $searchColumns Colonnes recherchées (préfixées éventuellement).
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function paginateWithEmployee(
        int $page = 1,
        int $perPage = 20,
        array $conditions = [],
        array $searchColumns = [],
        ?string $search = null,
        string $orderBy = 'lr.created_at DESC'
    ): array {
        $where   = [];
        $params  = [];

        foreach ($conditions as $col => $val) {
            if ($val !== null && $val !== '') {
                $where[] = "`lr.{$col}` = ?";
                $params[] = $val;
            }
        }

        if ($search !== null && $search !== '' && !empty($searchColumns)) {
            $like   = [];
            foreach ($searchColumns as $col) {
                $like[]   = "`{$col}` LIKE ?";
                $params[] = '%' . $search . '%';
            }
            $where[] = '(' . implode(' OR ', $like) . ')';
        }

        $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $countSql = "SELECT COUNT(*) FROM `{$this->table}` lr
                     LEFT JOIN `employees` e ON e.id = lr.employee_id
                     {$sqlWhere}";
        $countStmt = $this->db()->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT lr.*, e.first_name, e.last_name, e.employee_code
                FROM `{$this->table}` lr
                LEFT JOIN `employees` e ON e.id = lr.employee_id
                {$sqlWhere}";
        $sql .= ' ORDER BY ' . $this->escapeOrderBy($orderBy);

        $offset = max(0, ($page - 1) * $perPage);
        $sql    .= " LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'data'  => $data ?: [],
            'total' => $total,
        ];
    }

    /**
     * Récupère l'utilisateur ayant approuvé la demande.
     *
     * @return array<string, mixed>|null
     */
    public function getApprover(): ?array
    {
        $approverId = $this->approved_by;
        if ($approverId === null) {
            return null;
        }

        return $this->fetchOneRaw(
            'SELECT * FROM `users` WHERE `id` = :id LIMIT 1',
            [':id' => (int) $approverId]
        );
    }

    /**
     * Calcule la durée du congé en jours (inclusifs).
     */
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

    /**
     * Indique si la demande est approuvée.
     */
    public function isApproved(): bool
    {
        return strtolower((string) $this->status) === 'approved';
    }

    /**
     * Indique si la demande est rejetée.
     */
    public function isRejected(): bool
    {
        return strtolower((string) $this->status) === 'rejected';
    }

    /**
     * Indique si la demande est en attente.
     */
    public function isPending(): bool
    {
        return strtolower((string) $this->status) === 'pending';
    }

    /**
     * Approuve la demande de congé.
     */
    public function approve(int $approvedBy): bool
    {
        return $this->update($this->getKey(), [
            'status'      => 'approved',
            'approved_by' => $approvedBy,
        ]);
    }

    /**
     * Rejette la demande de congé.
     */
    public function reject(int $approvedBy, ?string $reason = null): bool
    {
        return $this->update($this->getKey(), [
            'status'           => 'rejected',
            'approved_by'      => $approvedBy,
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Annule la demande de congé.
     */
    public function cancel(): bool
    {
        return $this->update($this->getKey(), ['status' => 'cancelled']);
    }
}
