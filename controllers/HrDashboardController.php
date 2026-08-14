<?php
namespace App\Controllers;

use App\Models\Employee;
use App\Models\AttendanceLog;
use App\Models\LeaveRequest;
use App\Models\RemoteWork;
use App\Models\Department;
use App\Helpers\AuditLogger;
use App\Helpers\Session;

class HrDashboardController extends BaseController
{
    private Employee $employeeModel;
    private AttendanceLog $attendanceModel;
    private LeaveRequest $leaveModel;
    private RemoteWork $remoteModel;
    private Department $departmentModel;

    public function __construct()
    {
        parent::__construct();
        $this->employeeModel   = new Employee();
        $this->attendanceModel = new AttendanceLog();
        $this->leaveModel      = new LeaveRequest();
        $this->remoteModel     = new RemoteWork();
        $this->departmentModel = new Department();
    }

    public function index(): void
    {
        $this->requirePermission('hr_dashboard', 'view');

        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');
        $monthEnd   = date('Y-m-t');

        $totalEmployees = $this->employeeModel->countActive();
        $onLeaveToday   = $this->countEmployeesOnLeave($today);
        $remoteToday    = $this->countRemoteToday($today);

        $stats = $this->attendanceModel->statsForDate($today);
        $stats['total_employees'] = $totalEmployees;
        $stats['absent'] = $this->attendanceModel->absentCount($today);

        $presentToday = (int) ($stats['present_today'] ?? 0);
        $attendanceRate = $totalEmployees > 0 ? round(($presentToday / $totalEmployees) * 100, 1) : 0;

        $lateToday = (int) ($stats['late'] ?? 0);
        $punctualityRate = $presentToday > 0 ? round((($presentToday - $lateToday) / $presentToday) * 100, 1) : 0;

        $overtimeHours = $this->getMonthlyOvertimeHours($monthStart, $monthEnd);

        $leaveBalance = $this->getLeaveBalanceSummary();

        $deptDistribution = $this->departmentModel->fetchAllRaw(
            'SELECT d.id, d.name, COUNT(e.id) AS count
             FROM departments d
             LEFT JOIN employees e ON e.department_id = d.id AND e.status = "active"
             GROUP BY d.id, d.name
             ORDER BY d.name ASC'
        );

        $recentActivities = $this->getRecentActivities();

        $this->render('hr_dashboard/index', [
            'pageTitle'         => 'Tableau de bord RH',
            'stats'             => $stats,
            'attendanceRate'    => $attendanceRate,
            'punctualityRate'   => $punctualityRate,
            'overtimeHours'     => $overtimeHours,
            'leaveBalance'      => $leaveBalance,
            'deptDistribution'  => $deptDistribution,
            'recentActivities'  => $recentActivities,
            'onLeaveToday'      => $onLeaveToday,
            'remoteToday'       => $remoteToday,
            'activeToday'       => $presentToday,
        ]);
    }

    private function countEmployeesOnLeave(string $date): int
    {
        $row = $this->leaveModel->fetchOneRaw(
            'SELECT COUNT(DISTINCT employee_id) AS cnt
             FROM leave_requests
             WHERE status = "approved"
               AND start_date <= :date
               AND end_date >= :date',
            [':date' => $date]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    private function countRemoteToday(string $date): int
    {
        $row = $this->remoteModel->fetchOneRaw(
            'SELECT COUNT(DISTINCT employee_id) AS cnt
             FROM remote_work_requests
             WHERE status = "approved"
               AND start_date <= :date
               AND end_date >= :date',
            [':date' => $date]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    private function getMonthlyOvertimeHours(string $startDate, string $endDate): float
    {
        $row = $this->attendanceModel->fetchOneRaw(
            'SELECT COALESCE(SUM(overtime_minutes), 0) AS total_minutes
             FROM attendance_logs
             WHERE attendance_date BETWEEN :start AND :end
               AND overtime_minutes > 0',
            [':start' => $startDate, ':end' => $endDate]
        );
        $minutes = (int) ($row['total_minutes'] ?? 0);
        return round($minutes / 60, 1);
    }

    private function getLeaveBalanceSummary(): array
    {
        $rows = $this->leaveModel->fetchAllRaw(
            'SELECT lr.leave_type, COUNT(*) AS count,
                    SUM(DATEDIFF(lr.end_date, lr.start_date) + 1) AS total_days
             FROM leave_requests lr
             WHERE lr.status = "approved"
               AND YEAR(lr.start_date) = YEAR(CURDATE())
             GROUP BY lr.leave_type'
        );

        $summary = [
            'vacation'  => ['label' => 'Congés payés', 'days' => 0, 'count' => 0],
            'sick'      => ['label' => 'Maladie', 'days' => 0, 'count' => 0],
            'personal'  => ['label' => 'Personnels', 'days' => 0, 'count' => 0],
            'maternity' => ['label' => 'Maternité', 'days' => 0, 'count' => 0],
            'paternity' => ['label' => 'Paternité', 'days' => 0, 'count' => 0],
            'other'     => ['label' => 'Autres', 'days' => 0, 'count' => 0],
        ];

        foreach ($rows as $r) {
            $type = $r['leave_type'] ?? 'other';
            if (isset($summary[$type])) {
                $summary[$type]['days']  = (int) $r['total_days'];
                $summary[$type]['count'] = (int) $r['count'];
            }
        }

        return $summary;
    }

    private function getRecentActivities(int $limit = 10): array
    {
        return $this->attendanceModel->db()->query(
            'SELECT al.action, al.module, al.description, al.created_at, al.user_name
             FROM audit_logs al
             WHERE al.module IN ("leaves", "overtime", "remote_work", "shifts", "attendance")
             ORDER BY al.created_at DESC
             LIMIT ' . (int) $limit
        )->fetchAll(\PDO::FETCH_ASSOC);
    }
}
