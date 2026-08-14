<?php
/**
 * Modèle Employé (table `employees`)
 * Fichier: models/Employee.php
 *
 * @property int         $id
 * @property string      $employee_code
 * @property string      $first_name
 * @property string      $last_name
 * @property string      $phone
 * @property int|null    $department_id
 * @property string|null $hire_date
 * @property string|null $photo
 * @property string|null $badge_id
 * @property string|null $badge_code
 * @property string      $status
 * @property string      $registration_status
 * @property int|null    $hikvision_user_id
 * @property string      $created_at
 * @property string      $updated_at
 */

declare(strict_types=1);

namespace App\Models;

class Employee extends BaseModel
{
    /**
     * @var string
     */
    protected string $table = 'employees';

    /**
     * @var string[]
     */
    protected array $fillable = [
        'employee_code',
        'first_name',
        'last_name',
        'phone',
        'department_id',
        'hire_date',
        'photo',
        'badge_id',
        'badge_code',
        'status',
        'registration_status',
        'hikvision_user_id',
    ];

    /**
     * Récupère le département de l'employé.
     *
     * @return array<string, mixed>|null
     */
    public function getDepartment(): ?array
    {
        $departmentId = $this->department_id;
        if ($departmentId === null) {
            return null;
        }

        return $this->fetchOneRaw(
            'SELECT * FROM `departments` WHERE `id` = :id LIMIT 1',
            [':id' => (int) $departmentId]
        );
    }

    /**
     * Retourne le nom complet de l'employé.
     */
    public function getFullName(): string
    {
        return trim((string) $this->first_name . ' ' . (string) $this->last_name);
    }

    /**
     * Retourne l'URL web de la photo de profil d'un employé à partir de la
     * valeur stockée dans la colonne `photo`.
     *
     * Normalise les différents formats de stockage possibles : URL absolue
     * (http/https), chemin racine, chemin relatif complet (`uploads/...`),
     * préfixe `employees/` ou simple nom de fichier.
     *
     * @param string|null $photo Valeur stockée dans `employees.photo`.
     * @return string URL web relative, ou chaîne vide s'il n'y a pas de photo.
     */
    public static function photoUrl(?string $photo): string
    {
        $photo = trim((string) $photo);

        if ($photo === '') {
            return '';
        }

        // URL absolue (http/https)
        if (preg_match('#^https?://#i', $photo) === 1) {
            return $photo;
        }

        // Chemin absolu depuis la racine du site
        if (str_starts_with($photo, '/')) {
            return $photo;
        }

        // Chemin relatif web déjà complet
        if (str_starts_with($photo, 'uploads/')) {
            return $photo;
        }

        // Préfixe `employees/` (nom de fichier relatif au répertoire uploads)
        if (str_starts_with($photo, 'employees/')) {
            return 'uploads/' . $photo;
        }

        // Simple nom de fichier
        return 'uploads/employees/' . $photo;
    }

    /**
     * Génère la balise <img> (ou son remplaçant) d'une photo de profil.
     *
     * - Aucune photo stockée : affiche l'espace réservé bleu par défaut.
     * - Photo stockée mais fichier introuvable sur le disque : l'attribut
     *   `onerror` remplace l'image par un avatar SVG par défaut (aucune
     *   icône "image cassée").
     *
     * @param string|null $photo Valeur stockée dans `employees.photo`.
     * @param string      $class Classes CSS appliquées à la balise <img>.
     * @param string      $alt   Texte alternatif.
     * @return string HTML de la balise <img> ou du <span> de remplacement.
     */
    public static function photoTag(?string $photo, string $class = 'w-9 h-9 rounded-full object-cover', string $alt = 'photo'): string
    {
        $url = self::photoUrl($photo);

        if ($url === '') {
            return '<span class="inline-flex items-center justify-center '
                . htmlspecialchars($class, ENT_QUOTES)
                . ' bg-blue-100 text-white" style="width:36px;height:36px">'
                . '<i class="fas fa-user text-sm"></i></span>';
        }

        // Avatar SVG par défaut (utilisé en cas d'erreur de chargement).
        $defaultAvatar = 'data:image/svg+xml;base64,' . base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#cbd5e1">'
            . '<circle cx="12" cy="8" r="4"/>'
            . '<path d="M12 10c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>'
            . '</svg>'
        );

        $onerror = 'this.onerror=null;this.src=' . json_encode($defaultAvatar) . ';';

        return '<img src="' . htmlspecialchars($url, ENT_QUOTES) . '"'
            . ' class="' . htmlspecialchars($class, ENT_QUOTES) . '"'
            . ' alt="' . htmlspecialchars($alt, ENT_QUOTES) . '"'
            . ' onerror="' . htmlspecialchars($onerror, ENT_QUOTES) . '">';
    }

    /**
     * Retourne l'URL de la photo de l'employé (ou une photo par défaut).
     */
    public function getPhotoUrl(): string
    {
        $url = self::photoUrl($this->photo);
        if ($url !== '') {
            return $url;
        }

        return 'data:image/svg+xml;base64,' . base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#94a3b8">'
            . '<circle cx="12" cy="8" r="4"/>'
            . '<path d="M12 10c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>'
            . '</svg>'
        );
    }

    /**
     * Génère le SVG d'un avatar par défaut à partir des initiales d'un employé.
     *
     * Utilisé quand aucune photo n'est disponible (ex. le terminal ne fournit
     * pas de photo téléchargeable). Le SVG est autosuffisant et s'affiche
     * correctement dans une balise <img>.
     *
     * @param string $firstName Prénom.
     * @param string $lastName  Nom.
     * @return string Balise SVG complète.
     */
    public static function generateAvatarSvg(string $firstName, string $lastName): string
    {
        $first = mb_substr(trim((string) $firstName), 0, 1);
        $last = mb_substr(trim((string) $lastName), 0, 1);
        $initials = ($first !== '' ? mb_strtoupper($first) : '') . ($last !== '' ? mb_strtoupper($last) : '');
        if ($initials === '') {
            $initials = '?';
        }

        $bgColor = self::avatarColor($firstName . ' ' . $lastName);

        $pk = self::avatarPalette($bgColor);

        $esc = function (string $s): string {
            return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };

        return '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 80 80" role="img" aria-label="avatar">'
            . '<rect width="80" height="80" rx="16" fill="' . $pk['bg'] . '"/>'
            . '<text x="40" y="52" fill="' . $pk['fg'] . '" font-family="sans-serif" font-weight="700" font-size="32" text-anchor="middle" dominant-baseline="middle">'
            . $esc($initials) . '</text>'
            . '</svg>';
    }

    /**
     * Calcule une couleur d'arrière-plan HSLA stable à partir du nom.
     */
    private static function avatarColor(string $seed): string
    {
        $hue = (int) (crc32($seed) % 360);
        return 'hsl(' . $hue . ', 35%, 48%)';
    }

    /**
     * Dérive un texte contrasté (noir/blanc) compatible SVG à partir d'une couleur HSL.
     */
    private static function avatarPalette(string $hsl): array
    {
        preg_match('/hsl\(\s*(\d+)\s*,\s*(\d+(?:\.\d+)?)%\s*,\s*(\d+(?:\.\d+)?)%\s*\)/i', $hsl, $m);
        $h = isset($m[1]) ? (float) $m[1] : 0;
        $s = isset($m[2]) ? (float) $m[2] / 100 : 0.35;
        $l = isset($m[3]) ? (float) $m[3] / 100 : 0.48;
        $fg = ($l > 0.58) ? '#111827' : '#ffffff';
        return ['bg' => $hsl, 'fg' => $fg];
    }

    /**
     * Calcule les statistiques de présence sur une période.
     *
     * @param string $startDate Date de début (Y-m-d).
     * @param string $endDate   Date de fin (Y-m-d).
     * @return array<string, int>
     */
    public function getAttendanceStats(string $startDate, string $endDate): array
    {
        $id = $this->getKey();
        if ($id === null) {
            return $this->emptyStats();
        }

        $row = $this->fetchOneRaw(
            'SELECT
                COUNT(DISTINCT `attendance_date`)          AS days_present,
                COALESCE(SUM(`work_duration_minutes`), 0)  AS total_work_minutes,
                COALESCE(SUM(`late_minutes`), 0)           AS total_late_minutes,
                COALESCE(SUM(`early_departure_minutes`), 0) AS total_early_departure_minutes,
                COALESCE(SUM(`overtime_minutes`), 0)       AS total_overtime_minutes,
                COALESCE(SUM(`missing_minutes`), 0)        AS total_missing_minutes,
                COUNT(*)                                    AS total_logs
             FROM `attendance_logs`
             WHERE `employee_id` = :id
               AND `attendance_date` BETWEEN :start AND :end',
            [
                ':id'    => (int) $id,
                ':start' => $startDate,
                ':end'   => $endDate,
            ]
        );

        if ($row === null) {
            return $this->emptyStats();
        }

        return [
            'days_present'                 => (int) $row['days_present'],
            'total_work_minutes'           => (int) $row['total_work_minutes'],
            'total_late_minutes'           => (int) $row['total_late_minutes'],
            'total_early_departure_minutes' => (int) $row['total_early_departure_minutes'],
            'total_overtime_minutes'       => (int) $row['total_overtime_minutes'],
            'total_missing_minutes'        => (int) $row['total_missing_minutes'],
            'total_logs'                   => (int) $row['total_logs'],
        ];
    }

    /**
     * Récupère l'horaire de travail actif de l'employé.
     *
     * Cherche d'abord une affectation individuelle, sinon l'horaire du département.
     */
    public function getActiveSchedule(): ?WorkSchedule
    {
        $id = $this->getKey();
        if ($id === null) {
            return null;
        }

        $row = $this->fetchOneRaw(
            'SELECT ws.*
             FROM `schedule_assignments` sa
             INNER JOIN `work_schedules` ws ON ws.`id` = sa.`schedule_id`
             WHERE sa.`employee_id` = :id
               AND sa.`start_date` <= CURDATE()
               AND (sa.`end_date` IS NULL OR sa.`end_date` >= CURDATE())
               AND ws.`is_active` = 1
             ORDER BY sa.`start_date` DESC
             LIMIT 1',
            [':id' => (int) $id]
        );

        if ($row === null && $this->department_id !== null) {
            $row = $this->fetchOneRaw(
                'SELECT ws.*
                 FROM `departments` d
                 INNER JOIN `work_schedules` ws ON ws.`id` = d.`schedule_id`
                 WHERE d.`id` = :dept
                 LIMIT 1',
                [':dept' => (int) $this->department_id]
            );
        }

        if ($row === null) {
            return null;
        }

        $schedule = new WorkSchedule();
        $schedule->fill($row);

        return $schedule;
    }

    /**
     * Récupère le détail des présences pour un mois donné (une ligne par jour).
     *
     * @param int $year
     * @param int $month
     * @return array<int, array<string, mixed>>
     */
    public function getMonthlyAttendance(int $year, int $month): array
    {
        $id = $this->getKey();
        if ($id === null) {
            return [];
        }

        return $this->fetchAllRaw(
            "SELECT
                `attendance_date`,
                MIN(CASE WHEN `type` = 'check_in' THEN `attendance_time` END)  AS first_check_in,
                MAX(CASE WHEN `type` = 'check_out' THEN `attendance_time` END) AS last_check_out,
                COALESCE(SUM(`work_duration_minutes`), 0) AS work_minutes,
                COALESCE(SUM(`late_minutes`), 0)          AS late_minutes,
                COALESCE(SUM(`early_departure_minutes`), 0) AS early_departure_minutes,
                COALESCE(SUM(`overtime_minutes`), 0)      AS overtime_minutes,
                COALESCE(SUM(`missing_minutes`), 0)       AS missing_minutes,
                COUNT(*) AS total_logs
             FROM `attendance_logs`
             WHERE `employee_id` = :id
               AND YEAR(`attendance_date`) = :year
               AND MONTH(`attendance_date`) = :month
             GROUP BY `attendance_date`
             ORDER BY `attendance_date` ASC",
            [
                ':id'    => (int) $id,
                ':year'  => $year,
                ':month' => $month,
            ]
        );
    }

    /**
     * Récupère les pointages du jour pour l'employé.
     *
     * @return AttendanceLog[]
     */
    public function getTodayAttendance(): array
    {
        $id = $this->getKey();
        if ($id === null) {
            return [];
        }

        $rows = $this->fetchAllRaw(
            'SELECT * FROM `attendance_logs`
             WHERE `employee_id` = :id AND `attendance_date` = CURDATE()
             ORDER BY `attendance_time` ASC',
            [':id' => (int) $id]
        );

        return array_map(static function (array $row): AttendanceLog {
            $log = new AttendanceLog();
            $log->fill($row);
            return $log;
        }, $rows);
    }

    /**
     * Calcule la durée de travail du jour en minutes.
     *
     * Utilise la somme des `work_duration_minutes`, sinon l'écart entre le
     * premier pointage d'entrée et le dernier pointage de sortie.
     */
    public function getWorkDuration(): int
    {
        $id = $this->getKey();
        if ($id === null) {
            return 0;
        }

        $row = $this->fetchOneRaw(
            "SELECT
                COALESCE(SUM(`work_duration_minutes`), 0) AS work_minutes,
                MIN(CASE WHEN `type` = 'check_in' THEN `attendance_time` END)  AS first_in,
                MAX(CASE WHEN `type` = 'check_out' THEN `attendance_time` END) AS last_out
             FROM `attendance_logs`
             WHERE `employee_id` = :id AND `attendance_date` = CURDATE()",
            [':id' => (int) $id]
        );

        if ($row === null) {
            return 0;
        }

        $workMinutes = (int) ($row['work_minutes'] ?? 0);
        if ($workMinutes > 0) {
            return $workMinutes;
        }

        if (!empty($row['first_in']) && !empty($row['last_out'])) {
            $start = strtotime((string) $row['first_in']);
            $end   = strtotime((string) $row['last_out']);
            if ($start !== false && $end !== false && $end > $start) {
                return (int) round(($end - $start) / 60);
            }
        }

        return 0;
    }

    /**
     * Recherche un employé par son matricule (employee_code).
     */
    public function findByCode(string $code): ?static
    {
        $row = $this->findBy('employee_code', $code);
        if ($row === null) {
            return null;
        }

        $employee = new static();
        $employee->fill($row);

        return $employee;
    }

    /**
     * Compte le nombre d'employés actifs.
     */
    public function countActive(): int
    {
        return $this->count(['status' => 'active']);
    }

    /**
     * Récupère l'historique de pointage d'un employé.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAttendanceHistory(int $employeeId, int $limit = 50): array
    {
        $limit = max(1, $limit);

        return $this->fetchAllRaw(
            "SELECT *
             FROM `attendance_logs`
             WHERE `employee_id` = :id
             ORDER BY `attendance_date` DESC, `attendance_time` DESC
             LIMIT {$limit}",
            [':id' => (int) $employeeId]
        );
    }

    /**
     * Bascule le statut d'un employé entre actif et inactif.
     */
    public function toggleStatus(int $id): bool
    {
        $row = $this->find($id);
        if ($row === null) {
            return false;
        }

        $newStatus = ($row['status'] ?? '') === 'active' ? 'inactive' : 'active';

        return $this->update($id, ['status' => $newStatus]);
    }

    /**
     * Met à jour les informations de badge / terminal d'un employé.
     */
    public function assignTerminal(int $id, array $data): bool
    {
        $allowed = ['badge_id', 'badge_code', 'hikvision_user_id', 'registration_status'];
        $filtered = array_intersect_key($data, array_flip($allowed));

        if ($filtered === []) {
            return false;
        }

        return $this->update($id, $filtered);
    }

    /**
     * Vérifie si l'employé a des pointages enregistrés.
     */
    public function hasAttendanceLogs(int $id): bool
    {
        $row = $this->fetchOneRaw(
            'SELECT COUNT(*) AS cnt FROM `attendance_logs` WHERE `employee_id` = :id LIMIT 1',
            [':id' => $id]
        );

        return (int) ($row['cnt'] ?? 0) > 0;
    }

    /**
     * Retourne une structure de statistiques vide.
     *
     * @return array<string, int>
     */
    private function emptyStats(): array
    {
        return [
            'days_present'                 => 0,
            'total_work_minutes'           => 0,
            'total_late_minutes'           => 0,
            'total_early_departure_minutes' => 0,
            'total_overtime_minutes'       => 0,
            'total_missing_minutes'        => 0,
            'total_logs'                   => 0,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function paginate(int $page = 1, int $perPage = 20, array $conditions = [], array $searchColumns = [], ?string $search = null, string $orderBy = ''): array
    {
        $params = [];
        $where = [];

        foreach ($conditions as $col => $val) {
            if ($val !== null && $val !== '') {
                $where[] = "`{$this->table}`.`{$col}` = ?";
                $params[] = $val;
            }
        }

        if ($search !== null && $search !== '' && !empty($searchColumns)) {
            $like = [];
            foreach ($searchColumns as $col) {
                $like[] = "`{$this->table}`.`{$col}` LIKE ?";
                $params[] = '%' . $search . '%';
            }
            $where[] = '(' . implode(' OR ', $like) . ')';
        }

        $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $countSql = "SELECT COUNT(*) FROM `{$this->table}`{$sqlWhere}";
        $countStmt = $this->db()->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT `{$this->table}`.*, `d`.`name` AS `department_name`
                FROM `{$this->table}`
                LEFT JOIN `departments` `d` ON `d`.`id` = `{$this->table}`.`department_id`
                {$sqlWhere}";

        if ($orderBy !== '') {
            $sql .= ' ORDER BY ' . $this->escapeOrderBy($orderBy);
        }

        $offset = max(0, ($page - 1) * $perPage);
        $sql .= " LIMIT {$perPage} OFFSET {$offset}";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return [
            'data'  => $data,
            'total' => $total,
        ];
    }
}
