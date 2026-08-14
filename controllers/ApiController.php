<?php
/**
 * API Controller - AJAX endpoints for AttendPro dynamic features
 */

namespace App\Controllers;

use App\Helpers\Session;
use App\Helpers\Csrf;
use App\Helpers\Auth;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Department;
use App\Models\Notification;
use App\Helpers\NotificationHelper;

class ApiController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
    }

    /**
     * GET: Returns dashboard stats as JSON for auto-refresh.
     */
    public function dashboardStats(): void
    {
        $today = date('Y-m-d');
        $year  = (int) date('Y');

        $stats = (new AttendanceLog())->statsForDate($today);
        $stats['total_employees'] = (new Employee())->countActive();
        $stats['absent'] = (new AttendanceLog())->absentCount($today);

        $this->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'chart' => [
                    'monthly' => (new AttendanceLog())->getMonthlyChart($year),
                    'presence_daily' => array_slice((new AttendanceLog())->getMonthlyChart($year), -7),
                    'delays' => array_map(
                        static fn ($m) => ['label' => $m['label'], 'value' => $m['late']],
                        (new AttendanceLog())->getMonthlyChart($year)
                    ),
                    'dept_distribution' => (new Department())->getAttendanceStats($today),
                ],
            ],
        ]);
    }

    /**
     * GET: Returns recent notifications for the current user.
     */
    public function notifications(): void
    {
        $userId = Auth::id();

        $notifications = [];
        if ($userId) {
            $rows = (new Notification())->getUnread($userId, 20);
            foreach ($rows as $row) {
                $data = [];
                if (!empty($row['data'])) {
                    $data = json_decode($row['data'], true) ?: [];
                }

                $notifications[] = [
                    'id'      => (int) $row['id'],
                    'title'   => $row['title'],
                    'body'    => $row['message'] ?: 'Notification',
                    'type'    => $row['type'],
                    'data'    => $data,
                    'created' => $row['created_at'],
                ];
            }
        }

        $this->json([
            'success' => true,
            'data'    => $notifications,
            'count'   => count($notifications),
        ]);
    }

    /**
     * POST: Marks all notifications as read for the current user.
     */
    public function markNotificationsRead(): void
    {
        $userId = Auth::id();
        if ($userId) {
            (new Notification())->markAllAsRead($userId);
        }

        $this->json(['success' => true]);
    }

    /**
     * POST: Runs automatic notification checks (delays, missing punches, overtime).
     */
    public function runChecks(): void
    {
        try {
            NotificationHelper::runChecks();
            $this->json(['success' => true, 'message' => 'Vérifications exécutées']);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET: Returns employee suggestions for autocomplete.
     */
    public function searchEmployees(): void
    {
        $q = trim($this->getGet('q', ''));
        if (mb_strlen($q) < 2) {
            $this->json(['success' => true, 'data' => []]);
        }

        $sql = 'SELECT id, employee_code, first_name, last_name, badge_id
                FROM employees
                WHERE status = "active"
                  AND (first_name LIKE :q OR last_name LIKE :q OR employee_code LIKE :q OR badge_id LIKE :q)
                ORDER BY first_name ASC
                LIMIT 20';
        $stmt = (new Employee())->db()->prepare($sql);
        $stmt->execute([':q' => "%{$q}%"]);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $data = [];
        foreach ($results as $r) {
            $data[] = [
                'id' => (int) $r['id'],
                'text' => htmlspecialchars($r['first_name'] . ' ' . $r['last_name'] . ' (' . $r['employee_code'] . ')'),
            ];
        }

        $this->json(['success' => true, 'data' => $data]);
    }

    /**
     * GET: Returns live attendance status.
     */
    public function liveAttendance(): void
    {
        $today = date('Y-m-d');
        $sql = 'SELECT COUNT(*) AS cnt FROM attendance_logs WHERE attendance_date = :today AND type = "check_in"';
        $count = (int) (new AttendanceLog())->fetchOneRaw($sql, [':today' => $today])['cnt'];

        $this->json([
            'success' => true,
            'data' => [
                'present_today' => $count,
                'timestamp' => date('H:i:s'),
            ],
        ]);
    }
}
