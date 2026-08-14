<?php
/**
 * Modèle Jour férié (table `holidays`)
 * Fichier: models/Holiday.php
 *
 * @property int         $id
 * @property string      $name
 * @property string      $date
 * @property string      $type
 * @property int         $is_recurring
 * @property string|null $notes
 * @property string      $created_at
 * @property string      $updated_at
 */

declare(strict_types=1);

namespace App\Models;

class Holiday extends BaseModel
{
    /**
     * @var string
     */
    protected string $table = 'holidays';

    /**
     * @var string[]
     */
    protected array $fillable = [
        'name',
        'date',
        'type',
        'is_recurring',
        'notes',
    ];

    /**
     * Indique si le jour férié tombe aujourd'hui.
     *
     * Prend en compte les jours fériés récurrents (comparaison jour/mois).
     */
    public function isToday(): bool
    {
        $date = (string) ($this->date ?? '');
        if ($date === '') {
            return false;
        }

        $today = date('Y-m-d');

        if ($date === $today) {
            return true;
        }

        if ((int) $this->is_recurring === 1) {
            $ts = strtotime($date);
            return $ts !== false && date('m-d', $ts) === date('m-d');
        }

        return false;
    }

    /**
     * Indique si le jour férié est compris dans une plage de dates.
     *
     * @param string $start Date de début (Y-m-d).
     * @param string $end   Date de fin (Y-m-d).
     */
    public function isInRange(string $start, string $end): bool
    {
        $date = (string) ($this->date ?? '');
        if ($date === '') {
            return false;
        }

        $startTs = strtotime($start);
        $endTs   = strtotime($end);
        $dateTs  = strtotime($date);
        if ($startTs === false || $endTs === false || $dateTs === false) {
            return false;
        }

        if ((int) $this->is_recurring === 1) {
            // Vérifie chaque année couverte par la plage.
            $startYear = (int) date('Y', $startTs);
            $endYear   = (int) date('Y', $endTs);
            for ($year = $startYear; $year <= $endYear; $year++) {
                $occurrence = strtotime($year . '-' . date('m-d', $dateTs));
                if ($occurrence !== false && $occurrence >= $startTs && $occurrence <= $endTs) {
                    return true;
                }
            }
            return false;
        }

        return $dateTs >= $startTs && $dateTs <= $endTs;
    }

    /**
     * Retourne la (les) date(s) du jour férié pour une année donnée.
     *
     * @param int $year
     * @return string[] Liste de dates au format Y-m-d.
     */
    public function getRecurringDates(int $year): array
    {
        $date = (string) ($this->date ?? '');
        if ($date === '') {
            return [];
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return [];
        }

        if ((int) $this->is_recurring === 1) {
            return [$year . '-' . date('m-d', $timestamp)];
        }

        // Non récurrent : retourne la date uniquement si elle correspond à l'année.
        return (int) date('Y', $timestamp) === $year ? [date('Y-m-d', $timestamp)] : [];
    }

    /**
     * Vérifie si une date donnée est un jour férié.
     *
     * @param string $date Date à tester (Y-m-d).
     */
    public function isHoliday(string $date): bool
    {
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return false;
        }

        $monthDay = date('m-d', $timestamp);

        $row = $this->fetchOneRaw(
            'SELECT COUNT(*) AS total FROM `holidays`
             WHERE `date` = :date
                OR (`is_recurring` = 1 AND DATE_FORMAT(`date`, "%m-%d") = :md)',
            [':date' => date('Y-m-d', $timestamp), ':md' => $monthDay]
        );

        return $row !== null && (int) $row['total'] > 0;
    }
}
