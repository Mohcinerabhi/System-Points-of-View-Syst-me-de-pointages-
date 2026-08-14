<?php
namespace App\Controllers;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Department;
use App\Helpers\Sanitize;
use App\Helpers\CsvHelper;
use App\Helpers\AuditLogger;

/**
 * Contrôleur des rapports de pointage.
 */
class ReportsController extends BaseController
{
    private AttendanceLog $logModel;
    private Employee $employeeModel;
    private Department $departmentModel;

    public function __construct()
    {
        parent::__construct();
        $this->logModel = new AttendanceLog();
        $this->employeeModel = new Employee();
        $this->departmentModel = new Department();
    }

    public function index(): void
    {
        $this->requirePermission('reports', 'view');

        $type = $this->getGet('type', 'monthly');
        $from = Sanitize::date($this->getGet('from'));
        $to = Sanitize::date($this->getGet('to'));
        $deptId = Sanitize::int($this->getGet('department_id'));
        $empId = Sanitize::int($this->getGet('employee_id'));

        $results = [];
        $reportType = $type;
        $filters = ['from' => $from, 'to' => $to, 'department_id' => $deptId, 'employee_id' => $empId, 'type' => $type];

        if ($from && $to && $from > $to) {
            $this->refreshDashboardStats();
            Session::flash('error', 'La date de début ne peut pas être postérieure à la date de fin.');
        } elseif ($from && $to && $from <= $to) {
            $conditions = [];
            if ($deptId > 0) {
                $conditions['e.department_id'] = $deptId;
            }
            if ($empId > 0) {
                $conditions['e.id'] = $empId;
            }

            $results = $this->buildReport($from, $to, $conditions);
        }

        $this->render('reports/index', [
            'pageTitle'   => 'Rapports',
            'departments' => $this->departmentModel->all('name ASC'),
            'employees'   => $this->employeeModel->all('first_name ASC'),
            'filters'     => $filters,
            'results'     => $results,
            'reportType'  => $reportType,
        ]);
    }

    public function daily(): void
    {
        $this->requirePermission('reports', 'view');
        $date = Sanitize::date($this->getGet('date'), date('Y-m-d'));
        $rows = $this->buildReport($date, $date);
        $this->render('reports/daily', [
            'pageTitle' => 'Rapport journalier',
            'date'      => $date,
            'rows'      => $rows,
        ]);
    }

    public function weekly(): void
    {
        $this->requirePermission('reports', 'view');
        $start = Sanitize::date($this->getGet('start_week'), date('Y-m-d', strtotime('monday this week')));
        $end = date('Y-m-d', strtotime($start . ' +6 days'));
        $rows = $this->buildReport($start, $end);
        $this->render('reports/weekly', [
            'pageTitle'  => 'Rapport hebdomadaire',
            'startWeek'  => $start,
            'endWeek'    => $end,
            'rows'       => $rows,
        ]);
    }

    public function monthly(): void
    {
        $this->requirePermission('reports', 'view');
        $month = Sanitize::int($this->getGet('month'), (int) date('m'));
        $year  = Sanitize::int($this->getGet('year'), (int) date('Y'));
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-t', strtotime($start));
        $rows = $this->buildReport($start, $end);
        $this->render('reports/monthly', [
            'pageTitle' => 'Rapport mensuel',
            'month'     => $month,
            'year'      => $year,
            'start'     => $start,
            'end'       => $end,
            'rows'      => $rows,
        ]);
    }

    public function custom(): void
    {
        $this->requirePermission('reports', 'view');
        $start = Sanitize::date($this->getGet('start'), date('Y-m-01'));
        $end = Sanitize::date($this->getGet('end'), date('Y-m-t'));
        $rows = $this->buildReport($start, $end);
        $this->render('reports/custom', [
            'pageTitle' => 'Rapport personnalisé',
            'start'     => $start,
            'end'       => $end,
            'rows'      => $rows,
        ]);
    }

    public function byDepartment(): void
    {
        $this->requirePermission('reports', 'view');
        $deptId = Sanitize::int($this->getGet('dept_id'));
        $start = Sanitize::date($this->getGet('start'), date('Y-m-01'));
        $end = Sanitize::date($this->getGet('end'), date('Y-m-t'));

        $rows = $this->buildReport($start, $end, ['e.department_id' => $deptId]);
        $department = $deptId ? $this->departmentModel->find($deptId) : null;

        $this->render('reports/by_department', [
            'pageTitle'  => 'Rapport par département',
            'department' => $department,
            'start'      => $start,
            'end'        => $end,
            'rows'       => $rows,
        ]);
    }

    public function byEmployee(): void
    {
        $this->requirePermission('reports', 'view');
        $empId = Sanitize::int($this->getGet('emp_id'));
        $start = Sanitize::date($this->getGet('start'), date('Y-m-01'));
        $end = Sanitize::date($this->getGet('end'), date('Y-m-t'));

        $rows = $this->buildReport($start, $end, ['e.id' => $empId]);

        $this->render('reports/by_employee', [
            'pageTitle' => 'Rapport par employé',
            'employee'  => $empId ? $this->employeeModel->find($empId) : null,
            'start'     => $start,
            'end'       => $end,
            'rows'      => $rows,
        ]);
    }

    /**
     * Export CSV / Excel / PDF d'un rapport (selon ?format=).
     */
    public function export(): void
    {
        $this->requirePermission('reports', 'view');
        $format = $this->getGet('format', 'csv');

        $start = Sanitize::date($this->getGet('start')) ?: Sanitize::date($this->getGet('from'), date('Y-m-01'));
        $end = Sanitize::date($this->getGet('end')) ?: Sanitize::date($this->getGet('to'), date('Y-m-t'));
        $deptId = Sanitize::int($this->getGet('department_id')) ?: Sanitize::int($this->getGet('dept_id'));
        $empId = Sanitize::int($this->getGet('employee_id')) ?: Sanitize::int($this->getGet('emp_id'));

        $conditions = [];
        if ($deptId) {
            $conditions['e.department_id'] = $deptId;
        }
        if ($empId) {
            $conditions['e.id'] = $empId;
        }
        $rows = $this->buildReport($start, $end, $conditions);

        $header = [
            'employee_code' => 'Matricule', 'name' => 'Nom', 'department' => 'Département',
            'days_present' => 'Jours présents', 'total_late' => 'Retards (min)',
            'total_early' => 'Départs anticipés (min)', 'total_overtime' => 'Heures sup. (min)',
            'total_work' => 'Travail (min)',
        ];

        if ($format === 'pdf') {
            $this->exportPdf($header, $rows, "rapport_{$start}_{$end}");
            return;
        }

        CsvHelper::export($header, $rows, "rapport_{$start}_{$end}.csv");
    }

    /**
     * Construit un rapport agrégé par employé sur une période.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildReport(string $start, string $end, array $extra = []): array
    {
        $extraWhere = [];
        $params = [$start, $end];
        foreach ($extra as $col => $val) {
            if ($val !== null && $val !== '') {
                $extraWhere[] = "{$col} = ?";
                $params[] = $val;
            }
        }
        $sqlWhere = $extraWhere ? ' AND ' . implode(' AND ', $extraWhere) : '';

        $sql = "SELECT e.id, e.employee_code, e.first_name, e.last_name,
                       CONCAT(e.first_name, ' ', e.last_name) AS name,
                       d.name AS department, d.name AS department_name,
                       COUNT(DISTINCT CASE WHEN daily.has_checkin = 1 THEN daily.attendance_date END) AS worked_days,
                       COUNT(DISTINCT CASE WHEN daily.has_checkin = 1 THEN daily.attendance_date END) AS days_present,
                       SUM(COALESCE(daily.late, 0)) AS late, SUM(COALESCE(daily.late, 0)) AS total_late,
                       SUM(COALESCE(daily.early, 0)) AS early_departure, SUM(COALESCE(daily.early, 0)) AS total_early,
                       SUM(COALESCE(daily.overtime, 0)) AS overtime, SUM(COALESCE(daily.overtime, 0)) AS total_overtime,
                       SUM(COALESCE(daily.work_duration, 0)) AS hours_worked, SUM(COALESCE(daily.work_duration, 0)) AS total_work
                FROM employees e
                LEFT JOIN departments d ON d.id = e.department_id
                LEFT JOIN (
                    SELECT al.employee_id, al.attendance_date,
                           MAX(al.late_minutes) AS late,
                           MAX(al.early_departure_minutes) AS early,
                           MAX(al.overtime_minutes) AS overtime,
                           MAX(al.work_duration_minutes) AS work_duration,
                           MAX(CASE WHEN al.type = 'check_in' THEN 1 ELSE 0 END) AS has_checkin
                    FROM attendance_logs al
                    WHERE al.attendance_date BETWEEN ? AND ?
                    GROUP BY al.employee_id, al.attendance_date
                ) daily ON daily.employee_id = e.id
                WHERE e.status = 'active'{$sqlWhere}
                GROUP BY e.id, e.employee_code, e.first_name, e.last_name, CONCAT(e.first_name, ' ', e.last_name), d.name
                ORDER BY e.first_name ASC, e.last_name ASC";

        $stmt = $this->logModel->db()->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $totalDays = max(1, (int) ceil((strtotime($end) - strtotime($start)) / 86400) + 1);

        foreach ($rows as &$r) {
            $worked = (int) ($r['worked_days'] ?? 0);
            $r['presence'] = $worked;
            $r['absence'] = max(0, $totalDays - $worked);
            $r['hours_worked'] = round(((float) ($r['hours_worked'] ?? 0)) / 60, 1);
            $r['overtime'] = round(((float) ($r['overtime'] ?? 0)) / 60, 1);
            $required = $worked * 8;
            $r['missing_hours'] = max(0, $required - $r['hours_worked']);
        }

        return $rows;
    }

    private function exportPdf(array $header, array $rows, string $filename): void
    {
        // Export simplifié en HTML imprimable (à remplacer par une lib PDF si besoin).
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html><head><meta charset="utf-8"><title>' . htmlspecialchars($filename) . '</title>
        <style>table{border-collapse:collapse;width:100%;font-size:12px}th,td{border:1px solid #ccc;padding:4px;text-align:left}</style></head><body>';
        echo '<h2>' . htmlspecialchars($filename) . '</h2><table><thead><tr>';
        foreach ($header as $h) {
            echo '<th>' . htmlspecialchars($h) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $r) {
            echo '<tr>';
            foreach ($header as $k => $h) {
                echo '<td>' . htmlspecialchars((string) ($r[$k] ?? '')) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table><script>window.onload=function(){window.print();}</script></body></html>';
        exit;
    }
}
