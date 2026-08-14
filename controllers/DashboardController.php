<?php
namespace App\Controllers;

use App\Models\Employee;
use App\Models\AttendanceLog;
use App\Helpers\Paginator;

/**
 * Contrôleur du tableau de bord (statistiques).
 */
class DashboardController extends BaseController
{
    private Employee $employeeModel;
    private AttendanceLog $attendanceModel;

    public function __construct()
    {
        parent::__construct();
        $this->employeeModel = new Employee();
        $this->attendanceModel = new AttendanceLog();
    }

    public function index(): void
    {
        $this->requireAuth();

        $today = date('Y-m-d');
        $year  = (int) date('Y');

        $stats = $this->attendanceModel->statsForDate($today);
        $stats['total_employees'] = $this->employeeModel->countActive();
        $stats['absent'] = $this->attendanceModel->absentCount($today);

        $recentLogs = $this->attendanceModel->getRecent(15);
        $departmentStatsRaw = (new \App\Models\Department())->getAttendanceStats($today);
        $departmentStats = is_array($departmentStatsRaw) ? $departmentStatsRaw : [];
        $deptDistribution = array_map(static function (array $row): array {
            return [
                'name' => $row['name'] ?? '-',
                'count' => (int) ($row['employee_count'] ?? 0),
            ];
        }, $departmentStats);
        $monthlyChart = $this->attendanceModel->getMonthlyChart($year);
        $presenceDaily = $this->attendanceModel->getDailyPresence(7);
        $delaysDaily = $this->attendanceModel->getDailyDelays(7);

        $chart = [
            'monthly' => $monthlyChart,
            'presence_daily' => $presenceDaily,
            'delays' => $delaysDaily,
            'dept_distribution' => $deptDistribution,
        ];

        $this->render('dashboard/index', [
            'pageTitle'        => 'Tableau de bord',
            'stats'            => $stats,
            'recentLogs'       => $recentLogs,
            'departments'      => $departmentStats,
            'chart'            => $chart,
            'today'            => $today,
        ]);
    }

    /**
     * AJAX: returns current dashboard stats as JSON.
     */
    public function ajaxStats(): void
    {
        $this->requireAuth();

        $today = date('Y-m-d');
        $year  = (int) date('Y');

        $stats = $this->attendanceModel->statsForDate($today);
        $stats['total_employees'] = $this->employeeModel->countActive();
        $stats['absent'] = $this->attendanceModel->absentCount($today);

        $monthlyChart = $this->attendanceModel->getMonthlyChart($year);
        $presenceDaily = $this->attendanceModel->getDailyPresence(7);
        $delaysDaily = $this->attendanceModel->getDailyDelays(7);
        $departmentStatsRaw = (new \App\Models\Department())->getAttendanceStats($today);
        $departmentStats = is_array($departmentStatsRaw) ? $departmentStatsRaw : [];
        $deptDistribution = array_map(static function (array $row): array {
            return [
                'name' => $row['name'] ?? '-',
                'count' => (int) ($row['employee_count'] ?? 0),
            ];
        }, $departmentStats);

        $this->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'chart' => [
                    'monthly' => $monthlyChart,
                    'presence_daily' => $presenceDaily,
                    'delays' => $delaysDaily,
                    'dept_distribution' => $deptDistribution,
                ],
            ],
        ]);
    }
}
