<?php
/**
 * Modèle Horaire de travail (table `work_schedules`)
 * Fichier: models/WorkSchedule.php
 *
 * @property int         $id
 * @property string      $name
 * @property string|null $description
 * @property string      $type
 * @property string|null $monday_start
 * @property string|null $monday_end
 * @property string|null $tuesday_start
 * @property string|null $tuesday_end
 * @property string|null $wednesday_start
 * @property string|null $wednesday_end
 * @property string|null $thursday_start
 * @property string|null $thursday_end
 * @property string|null $friday_start
 * @property string|null $friday_end
 * @property string|null $saturday_start
 * @property string|null $saturday_end
 * @property string|null $sunday_start
 * @property string|null $sunday_end
 * @property string|null $break_start
 * @property string|null $break_end
 * @property int         $late_tolerance_minutes
 * @property int         $early_departure_tolerance_minutes
 * @property float       $required_work_hours
 * @property int         $is_active
 * @property string      $created_at
 * @property string      $updated_at
 */

declare(strict_types=1);

namespace App\Models;

use InvalidArgumentException;

class WorkSchedule extends BaseModel
{
    /**
     * @var string
     */
    protected string $table = 'work_schedules';

    /**
     * @var string[]
     */
    protected array $fillable = [
        'name',
        'description',
        'type',
        'monday_start',
        'monday_end',
        'tuesday_start',
        'tuesday_end',
        'wednesday_start',
        'wednesday_end',
        'thursday_start',
        'thursday_end',
        'friday_start',
        'friday_end',
        'saturday_start',
        'saturday_end',
        'sunday_start',
        'sunday_end',
        'break_start',
        'break_end',
        'late_tolerance_minutes',
        'early_departure_tolerance_minutes',
        'required_work_hours',
        'is_active',
    ];

    /**
     * Jours valides de la semaine.
     *
     * @var string[]
     */
    private const DAYS = [
        'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
    ];

    /**
     * Retourne l'horaire d'un jour donné.
     *
     * @param string $dayName Nom du jour (anglais, ex: "monday").
     * @return array{start: string|null, end: string|null}
     *
     * @throws InvalidArgumentException Si le jour est invalide.
     */
    public function getDaySchedule(string $dayName): array
    {
        $day = strtolower(trim($dayName));

        if (!in_array($day, self::DAYS, true)) {
            throw new InvalidArgumentException("Jour invalide: {$dayName}");
        }

        return [
            'start' => $this->attributes[$day . '_start'] ?? null,
            'end'   => $this->attributes[$day . '_end'] ?? null,
        ];
    }

    /**
     * Indique si l'horaire est de type flexible.
     */
    public function isFlexible(): bool
    {
        return strtolower((string) $this->type) === 'flexible';
    }

    /**
     * Indique si l'horaire est actif.
     */
    public function isActive(): bool
    {
        return (int) $this->is_active === 1;
    }

    /**
     * Récupère les employés actuellement affectés à cet horaire.
     *
     * @return Employee[]
     */
    public function getAssignedEmployees(): array
    {
        $id = $this->getKey();
        if ($id === null) {
            return [];
        }

        $rows = $this->fetchAllRaw(
            'SELECT e.*
             FROM `schedule_assignments` sa
             INNER JOIN `employees` e ON e.`id` = sa.`employee_id`
             WHERE sa.`schedule_id` = :id
               AND sa.`start_date` <= CURDATE()
               AND (sa.`end_date` IS NULL OR sa.`end_date` >= CURDATE())
             ORDER BY e.`last_name` ASC, e.`first_name` ASC',
            [':id' => (int) $id]
        );

        return array_map(static function (array $row): Employee {
            $employee = new Employee();
            $employee->fill($row);
            return $employee;
        }, $rows);
    }
}
