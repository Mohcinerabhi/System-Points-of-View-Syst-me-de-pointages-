<?php
namespace App\Controllers;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Helpers\Paginator;
use App\Helpers\Sanitize;
use App\Helpers\AuditLogger;
use App\Helpers\Session;
use App\Helpers\Logger;
use App\Helpers\NotificationHelper;

/**
 * Contrôleur des journaux de pointage.
 */
class AttendanceController extends BaseController
{
    private AttendanceLog $logModel;
    private Employee $employeeModel;

    public function __construct()
    {
        parent::__construct();
        $this->logModel = new AttendanceLog();
        $this->employeeModel = new Employee();
    }

    /**
     * Liste des pointages avec filtres date / employé / type.
     */
    public function index(): void
    {
        $this->requirePermission('attendance', 'view');

        $page    = Sanitize::int($this->getGet('page'), 1);
        $perPage = Sanitize::int($this->getGet('per_page'), 20);
        $date    = Sanitize::date($this->getGet('date'));
        $empId   = Sanitize::int($this->getGet('employee_id'));
        $deptId  = Sanitize::int($this->getGet('department_id'));
        $type    = $this->getGet('type');

        $filters = [
            'date'         => $date,
            'employee_id'  => $empId,
            'department_id'=> $deptId,
            'type'         => $type,
        ];

        $result = $this->logModel->getDailySummaries($filters, $page, $perPage);
        $logs = $result['data'] ?? [];
        $total = $result['total'] ?? 0;

        $paginator = new Paginator($total, $page, $perPage);

        $this->render('attendance/index', [
            'pageTitle'   => __('attendance'),
            'logs'        => $logs,
            'employees'   => $this->employeeModel->all('first_name ASC'),
            'departments' => $this->employeeModel->db()->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll(\PDO::FETCH_ASSOC),
            'pagination'  => $paginator->toArray(),
            'filters'     => ['date' => $date, 'employee_id' => $empId, 'department_id' => $deptId, 'type' => $type],
        ]);
    }

    /**
     * Ajout manuel d'un pointage.
     */
    public function create(): void
    {
        $this->requirePermission('attendance', 'create');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $errors = $this->validate(['employee_id', 'attendance_date', 'attendance_time', 'type']);
            if ($errors === true) {
                try {
                    $this->logModel->create([
                        'employee_id'     => Sanitize::int($this->getPost('employee_id')),
                        'attendance_date' => Sanitize::date($this->getPost('attendance_date'), date('Y-m-d')),
                        'attendance_time' => Sanitize::time($this->getPost('attendance_time')),
                        'type'            => $this->getPost('type'),
                        'source'          => 'manual',
                        'terminal_ip'     => $_SERVER['REMOTE_ADDR'] ?? '',
                        'notes'           => $this->getPost('notes'),
                    ]);
                    $this->triggerNotifications($this->getPost('type'), [
                        'employee_id' => Sanitize::int($this->getPost('employee_id')),
                        'attendance_date' => Sanitize::date($this->getPost('attendance_date'), date('Y-m-d')),
                        'late_minutes' => 0,
                        'work_duration_minutes' => 0,
                    ]);
                    AuditLogger::log('create', 'attendance', 'Pointage manuel ajouté', null);
                    Session::set('flash_success', 'Pointage ajouté.');
                    $this->refreshDashboardStats();
                    $this->redirect($this->url('attendance', 'index'));
                } catch (\Throwable $e) {
                    Logger::exception($e, 'Failed to create attendance log');
                    Session::set('flash_error', 'Erreur lors de l\'ajout du pointage : ' . $e->getMessage());
                }
            }
            $this->render('attendance/create', [
                'pageTitle' => 'Ajouter un pointage',
                'errors'    => is_array($errors) ? $errors : [],
                'employees' => $this->employeeModel->all('first_name ASC'),
                'input'     => $_POST,
            ]);
            return;
        }

        $this->render('attendance/create', [
            'pageTitle' => 'Ajouter un pointage',
            'employees' => $this->employeeModel->all('first_name ASC'),
        ]);
    }

    /**
     * Mise à jour d'un pointage.
     */
    public function update(): void
    {
        $this->requirePermission('attendance', 'edit');
        $id = Sanitize::int($this->getGet('id'));
        $log = $this->logModel->find($id);
        if (!$log) {
            Session::set('flash_error', 'Pointage introuvable.');
            $this->redirect($this->url('attendance', 'index'));
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $errors = $this->validate(['attendance_date', 'attendance_time', 'type']);
            if ($errors === true) {
                $old = $log;
                $this->logModel->update($id, [
                    'attendance_date' => Sanitize::date($this->getPost('attendance_date')),
                    'attendance_time' => Sanitize::time($this->getPost('attendance_time')),
                    'type'            => $this->getPost('type'),
                    'notes'           => $this->getPost('notes'),
                ]);
                AuditLogger::log('update', 'attendance', 'Pointage modifié', $id, $old, $_POST);
                Session::set('flash_success', 'Pointage mis à jour.');
                $this->redirect($this->url('attendance', 'index'));
            }
        }

        $this->render('attendance/update', [
            'pageTitle' => 'Modifier un pointage',
            'log'       => $log,
            'employees' => $this->employeeModel->all('first_name ASC'),
        ]);
    }

    /**
     * Suppression d'un pointage.
     */
    public function delete(): void
    {
        $this->requirePermission('attendance', 'delete');
        $id = Sanitize::int($this->getGet('id'));
        if ($this->logModel->find($id)) {
            try {
                $this->logModel->delete($id);
                AuditLogger::log('delete', 'attendance', 'Pointage supprimé', $id);
                Session::set('flash_success', 'Pointage supprimé.');
                $this->refreshDashboardStats();
            } catch (\Throwable $e) {
                Logger::exception($e, 'Failed to delete attendance log');
                Session::set('flash_error', 'Erreur lors de la suppression : ' . $e->getMessage());
            }
        } else {
            Session::set('flash_error', 'Pointage introuvable.');
        }
        $this->redirect($this->url('attendance', 'index'));
    }

    /**
     * Détail d'un pointage.
     */
    public function view(): void
    {
        $this->requirePermission('attendance', 'view');
        $id = Sanitize::int($this->getGet('id'));
        $log = $this->logModel->find($id);

        if (!$log) {
            Session::set('flash_error', 'Pointage introuvable.');
            $this->redirect($this->url('attendance', 'index'));
        }

        $employee = $this->employeeModel->find($log['employee_id']);

        $this->render('attendance/view', [
            'pageTitle' => 'Détail du pointage',
            'log'       => $log,
            'employee'  => $employee,
        ]);
    }

    /**
     * Calendrier de présence mensuel.
     */
    public function calendar(): void
    {
        $this->requirePermission('attendance', 'view');
        $year  = Sanitize::int($this->getGet('year'), (int) date('Y'));
        $month = Sanitize::int($this->getGet('month'), (int) date('n'));

        $holidayModel = new Holiday();
        $totalEmployees = $this->employeeModel->countActive();

        $rows = $this->logModel->getCalendarData($year, $month);
        $attendanceMap = [];
        foreach ($rows as $r) {
            $attendanceMap[$r['attendance_date']] = [
                'employees'    => (int) $r['employees'],
                'late_minutes' => (int) $r['late_minutes'],
            ];
        }

        $monthStart = mktime(0, 0, 0, $month, 1, $year);
        $monthEnd   = mktime(23, 59, 59, $month, (int) date('t', $monthStart), $year);
        $startDate  = date('Y-m-d', $monthStart);
        $endDate    = date('Y-m-d', $monthEnd);

        $holidays = $holidayModel->fetchAllRaw(
            'SELECT `date`, `name` FROM `holidays` WHERE `date` BETWEEN :start AND :end',
            [':start' => $startDate, ':end' => $endDate]
        );
        $holidayMap = [];
        foreach ($holidays as $h) {
            $holidayMap[$h['date']] = $h['name'];
        }

        $leaves = (new LeaveRequest())->fetchAllRaw(
            'SELECT `start_date`, `end_date` FROM `leave_requests` WHERE `status` = :status AND `start_date` <= :end AND `end_date` >= :start',
            [':status' => 'approved', ':start' => $startDate, ':end' => $endDate]
        );
        $leaveDates = [];
        foreach ($leaves as $l) {
            $current = $l['start_date'];
            while ($current <= $l['end_date']) {
                $leaveDates[$current] = true;
                $current = date('Y-m-d', strtotime($current . ' +1 day'));
            }
        }

        $firstDayOfWeek = (int) date('N', $monthStart);
        $daysInMonth = (int) date('t', $monthStart);
        $calendar = [];
        $summary = ['present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0, 'holiday' => 0, 'early' => 0];

        $currentDate = date('Y-m-d', strtotime('-' . ($firstDayOfWeek - 1) . ' days', $monthStart));
        for ($week = 0; $week < 6; $week++) {
            $weekCells = [];
            for ($d = 0; $d < 7; $d++) {
                $dayNum = (int) date('j', strtotime($currentDate));
                $dateStr = $currentDate;
                $isCurrentMonth = (int) date('n', strtotime($currentDate)) === $month;

                if (!$isCurrentMonth) {
                    $weekCells[] = ['day' => null, 'date' => null, 'status' => null, 'count' => 0, 'details' => ''];
                } else {
                    $att = $attendanceMap[$dateStr] ?? null;
                    $isHoliday = isset($holidayMap[$dateStr]);
                    $isLeave = isset($leaveDates[$dateStr]);

                    if ($isHoliday) {
                        $status = 'holiday';
                        $summary['holiday']++;
                        $details = '<p><strong>Jour férié :</strong> ' . htmlspecialchars($holidayMap[$dateStr]) . '</p>';
                    } elseif ($isLeave) {
                        $status = 'leave';
                        $summary['leave']++;
                        $details = '<p><strong>Congé</strong></p>';
                    } elseif ($att && $att['employees'] > 0) {
                        if ($att['late_minutes'] > 0) {
                            $status = 'late';
                            $summary['late']++;
                        } else {
                            $status = 'present';
                            $summary['present']++;
                        }
                        $details = '<p>' . $att['employees'] . ' pointage(s)</p>';
                        if ($att['late_minutes'] > 0) {
                            $details .= '<p>' . $att['late_minutes'] . ' retard(s)</p>';
                        }
                    } else {
                        $status = 'absent';
                        $summary['absent']++;
                        $details = '<p class="text-muted">Aucun pointage.</p>';
                    }

                    $weekCells[] = [
                        'day' => $dayNum,
                        'date' => $dateStr,
                        'status' => $status,
                        'count' => $att ? $att['employees'] : 0,
                        'details' => $details,
                    ];
                }
                $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
            }
            $calendar[] = $weekCells;
        }

        $monthName = date('F Y', mktime(0, 0, 0, $month, 1, $year));
        $prevMonth = $month - 1;
        $prevYear = $year;
        if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
        $nextMonth = $month + 1;
        $nextYear = $year;
        if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }
        $prevUrl = 'index.php?controller=attendance&action=calendar&month=' . $prevMonth . '&year=' . $prevYear;
        $nextUrl = 'index.php?controller=attendance&action=calendar&month=' . $nextMonth . '&year=' . $nextYear;

        $this->render('attendance/calendar', [
            'pageTitle' => 'Calendrier de présence',
            'year'      => $year,
            'month'     => $month,
            'monthName' => $monthName,
            'calendar'  => $calendar,
            'summary'   => $summary,
            'prevUrl'   => $prevUrl,
            'nextUrl'   => $nextUrl,
        ]);
    }

    /**
     * Pointages du jour.
     */
    public function today(): void
    {
        $this->requirePermission('attendance', 'view');
        $date = Sanitize::date($this->getGet('date'), date('Y-m-d'));
        $stats = $this->logModel->statsForDate($date);
        $logs = $this->logModel->getRecent(50);

        $this->render('attendance/today', [
            'pageTitle' => 'Pointages du jour',
            'date'      => $date,
            'stats'     => $stats,
            'logs'      => $logs,
        ]);
    }

    /**
     * Formulaire de pointage manuel (check-in / check-out).
     */
    public function manualEntry(): void
    {
        $this->requirePermission('attendance', 'create');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $errors = $this->validate(['employee_id', 'type']);
            if ($errors === true) {
                try {
                    $this->logModel->create([
                        'employee_id'     => Sanitize::int($this->getPost('employee_id')),
                        'attendance_date' => date('Y-m-d'),
                        'attendance_time' => date('H:i:s'),
                        'type'            => $this->getPost('type'),
                        'source'          => 'manual',
                        'terminal_ip'     => $_SERVER['REMOTE_ADDR'] ?? '',
                        'notes'           => $this->getPost('notes'),
                    ]);
                    $this->triggerNotifications($this->getPost('type'), [
                        'employee_id' => Sanitize::int($this->getPost('employee_id')),
                        'attendance_date' => date('Y-m-d'),
                        'late_minutes' => 0,
                        'work_duration_minutes' => 0,
                    ]);
                    AuditLogger::log('manual_entry', 'attendance', 'Saisie manuelle', null);
                    Session::set('flash_success', 'Pointage enregistré.');
                    $this->refreshDashboardStats();
                    $this->redirect($this->url('attendance', 'today'));
                } catch (\Throwable $e) {
                    Logger::exception($e, 'Failed to create manual attendance entry');
                    Session::set('flash_error', 'Erreur lors de l\'enregistrement : ' . $e->getMessage());
                }
            }
        }

        $this->render('attendance/manual_entry', [
            'pageTitle' => 'Saisie manuelle',
            'employees' => $this->employeeModel->all('first_name ASC'),
        ]);
    }

    /**
     * Déclenche les notifications automatiques liées aux pointages.
     */
    private function triggerNotifications(string $type, array $log): void
    {
        if ($type === 'check_in') {
            $lateMinutes = (int) ($log['late_minutes'] ?? 0);
            if ($lateMinutes > 0) {
                $employee = $this->employeeModel->find((int) $log['employee_id']);
                if ($employee) {
                    $message = sprintf(
                        '%s %s (%s) a %d minutes de retard aujourd\'hui.',
                        $employee['first_name'],
                        $employee['last_name'],
                        $employee['employee_code'],
                        $lateMinutes
                    );

                    NotificationHelper::sendToManagers(
                        \App\Models\Notification::TYPE_DELAY,
                        'Retard détecté',
                        $message,
                        ['employee_id' => (int) $log['employee_id'], 'late_minutes' => $lateMinutes]
                    );
                }
            }
        }

        if ($type === 'check_out') {
            $workMinutes = (int) ($log['work_duration_minutes'] ?? 0);
            if ($workMinutes > 8 * 60) {
                $employee = $this->employeeModel->find((int) $log['employee_id']);
                if ($employee) {
                    $overtimeMinutes = $workMinutes - 8 * 60;
                    $hours = (int) floor($overtimeMinutes / 60);
                    $mins = $overtimeMinutes % 60;
                    $overtimeText = $hours > 0 && $mins > 0 ? "{$hours}h {$mins}min" : ($hours > 0 ? "{$hours}h" : "{$mins}min");

                    $message = sprintf(
                        '%s %s (%s) a effectué %s d\'heures supplémentaires aujourd\'hui.',
                        $employee['first_name'],
                        $employee['last_name'],
                        $employee['employee_code'],
                        $overtimeText
                    );

                    NotificationHelper::sendToManagers(
                        \App\Models\Notification::TYPE_OVERTIME,
                        'Heures supplémentaires',
                        $message,
                        ['employee_id' => (int) $log['employee_id'], 'overtime_minutes' => $overtimeMinutes]
                    );
                }
            }
        }
    }
}
