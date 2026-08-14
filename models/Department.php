<?php
/**
 * Modèle Département (table `departments`)
 * Fichier: models/Department.php
 *
 * @property int         $id
 * @property string      $name
 * @property string|null $description
 * @property int|null    $schedule_id
 * @property int|null    $manager_id
 * @property string      $status
 * @property string      $created_at
 * @property string      $updated_at
 */

declare(strict_types=1);

namespace App\Models;

class Department extends BaseModel
{
    /**
     * @var string
     */
    protected string $table = 'departments';

    /**
     * @var string[]
     */
    protected array $fillable = [
        'name',
        'description',
        'schedule_id',
        'manager_id',
        'status',
    ];

    /**
     * Récupère l'horaire de travail par défaut du département.
     */
    public function getSchedule(): ?WorkSchedule
    {
        $scheduleId = $this->schedule_id;
        if ($scheduleId === null) {
            return null;
        }

        $row = $this->fetchOneRaw(
            'SELECT * FROM `work_schedules` WHERE `id` = :id LIMIT 1',
            [':id' => (int) $scheduleId]
        );

        if ($row === null) {
            return null;
        }

        $schedule = new WorkSchedule();
        $schedule->fill($row);

        return $schedule;
    }

    /**
     * Récupère le responsable (employé) du département.
     */
    public function getManager(): ?Employee
    {
        $managerId = $this->manager_id;
        if ($managerId === null) {
            return null;
        }

        $row = $this->fetchOneRaw(
            'SELECT * FROM `employees` WHERE `id` = :id LIMIT 1',
            [':id' => (int) $managerId]
        );

        if ($row === null) {
            return null;
        }

        $employee = new Employee();
        $employee->fill($row);

        return $employee;
    }

    /**
     * Compte le nombre total d'employés du département.
     */
    public function getEmployeeCount(): int
    {
        $id = $this->getKey();
        if ($id === null) {
            return 0;
        }

        $row = $this->fetchOneRaw(
            'SELECT COUNT(*) AS total FROM `employees` WHERE `department_id` = :id',
            [':id' => (int) $id]
        );

        return $row !== null ? (int) $row['total'] : 0;
    }

    /**
     * Récupère les employés actifs du département.
     *
     * @return Employee[]
     */
    public function getActiveEmployees(): array
    {
        $id = $this->getKey();
        if ($id === null) {
            return [];
        }

        $rows = $this->fetchAllRaw(
            "SELECT * FROM `employees`
             WHERE `department_id` = :id AND `status` = 'active'
             ORDER BY `last_name` ASC, `first_name` ASC",
            [':id' => (int) $id]
        );

        return array_map(static function (array $row): Employee {
            $employee = new Employee();
            $employee->fill($row);
            return $employee;
        }, $rows);
    }

    /**
     * Statistiques de présence par département pour une date donnée.
     *
     * Si l'instance est liée à un département, retourne les statistiques de ce
     * département (`total_employees`, `present`). Sinon, retourne la liste
     * agrégée des statistiques de tous les départements actifs.
     *
     * @param string $date Date (Y-m-d).
     * @return array<string, mixed>|array<int, array<string, mixed>>
     */
    public function getAttendanceStats(string $date): array
    {
        $id = $this->getKey();
        if ($id === null) {
            return $this->fetchAllRaw(
                "SELECT d.`id`, d.`name`,
                        COUNT(DISTINCT e.`id`) AS employee_count,
                        COUNT(DISTINCT al.`employee_id`) AS present_count
                 FROM `departments` d
                 LEFT JOIN `employees` e
                        ON e.`department_id` = d.`id` AND e.`status` = 'active'
                 LEFT JOIN `attendance_logs` al
                        ON al.`employee_id` = e.`id`
                       AND al.`attendance_date` = :date
                       AND al.`type` = 'check_in'
                 WHERE d.`status` = 'active'
                 GROUP BY d.`id`, d.`name`
                 ORDER BY d.`name` ASC",
                [':date' => $date]
            );
        }

        $row = $this->fetchOneRaw(
            "SELECT
                COUNT(DISTINCT e.`id`) AS total_employees,
                COUNT(DISTINCT al.`employee_id`) AS present
             FROM `employees` e
             LEFT JOIN `attendance_logs` al
                    ON al.`employee_id` = e.`id`
                   AND al.`attendance_date` = :date
                   AND al.`type` = 'check_in'
             WHERE e.`department_id` = :id AND e.`status` = 'active'",
            [':date' => $date, ':id' => (int) $id]
        );

        return [
            'total_employees' => $row !== null ? (int) $row['total_employees'] : 0,
            'present'         => $row !== null ? (int) $row['present'] : 0,
        ];
    }
}
