<?php
namespace App\Controllers;

use App\Models\AttendanceLog;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\Employee;
use App\Helpers\Sanitize;

class PayrollApiController extends BaseController
{
    private string $apiKeyHeader = 'X-API-Key';

    public function __construct()
    {
        parent::__construct();
    }

    public function attendanceSummary(): void
    {
        $this->requireApiKey();

        $from = Sanitize::date($this->getGet('from', date('Y-m-01')));
        $to   = Sanitize::date($this->getGet('to', date('Y-m-t')));
        $empId = Sanitize::int($this->getGet('employee_id'));

        $where = ['al.attendance_date BETWEEN :from AND :to'];
        $params = [':from' => $from, ':to' => $to];

        if ($empId > 0) {
            $where[] = 'al.employee_id = :employee_id';
            $params[':employee_id'] = $empId;
        }

        $sqlWhere = 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT e.id AS employee_id, e.employee_code, e.first_name, e.last_name,
                       COUNT(DISTINCT al.attendance_date) AS days_present,
                       COALESCE(SUM(al.work_duration_minutes), 0) AS total_work_minutes,
                       COALESCE(SUM(al.late_minutes), 0) AS total_late_minutes,
                       COALESCE(SUM(al.overtime_minutes), 0) AS total_overtime_minutes
                FROM attendance_logs al
                JOIN employees e ON e.id = al.employee_id
                {$sqlWhere}
                GROUP BY e.id, e.employee_code, e.first_name, e.last_name
                ORDER BY e.last_name ASC, e.first_name ASC";

        $stmt = (new AttendanceLog())->db()->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->json([
            'success' => true,
            'data'    => $rows,
            'meta'    => ['from' => $from, 'to' => $to, 'employee_id' => $empId > 0 ? $empId : null],
        ]);
    }

    public function overtimeSummary(): void
    {
        $this->requireApiKey();

        $from = Sanitize::date($this->getGet('from', date('Y-m-01')));
        $to   = Sanitize::date($this->getGet('to', date('Y-m-t')));
        $empId = Sanitize::int($this->getGet('employee_id'));

        $where = ['al.attendance_date BETWEEN :from AND :to', 'al.overtime_minutes > 0'];
        $params = [':from' => $from, ':to' => $to];

        if ($empId > 0) {
            $where[] = 'al.employee_id = :employee_id';
            $params[':employee_id'] = $empId;
        }

        $sqlWhere = 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT e.id AS employee_id, e.employee_code, e.first_name, e.last_name,
                       COUNT(DISTINCT al.attendance_date) AS overtime_days,
                       COALESCE(SUM(al.overtime_minutes), 0) AS total_overtime_minutes,
                       COALESCE(SUM(al.overtime_minutes) / 60.0, 0) AS total_overtime_hours
                FROM attendance_logs al
                JOIN employees e ON e.id = al.employee_id
                {$sqlWhere}
                GROUP BY e.id, e.employee_code, e.first_name, e.last_name
                ORDER BY total_overtime_minutes DESC";

        $stmt = (new AttendanceLog())->db()->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->json([
            'success' => true,
            'data'    => $rows,
            'meta'    => ['from' => $from, 'to' => $to, 'employee_id' => $empId > 0 ? $empId : null],
        ]);
    }

    public function leaveSummary(): void
    {
        $this->requireApiKey();

        $from = Sanitize::date($this->getGet('from', date('Y-m-01')));
        $to   = Sanitize::date($this->getGet('to', date('Y-m-t')));
        $empId = Sanitize::int($this->getGet('employee_id'));

        $where = ['lr.start_date <= :to', 'lr.end_date >= :from', 'lr.status = "approved"'];
        $params = [':from' => $from, ':to' => $to];

        if ($empId > 0) {
            $where[] = 'lr.employee_id = :employee_id';
            $params[':employee_id'] = $empId;
        }

        $sqlWhere = 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT e.id AS employee_id, e.employee_code, e.first_name, e.last_name,
                       lr.leave_type, lr.start_date, lr.end_date,
                       DATEDIFF(lr.end_date, lr.start_date) + 1 AS duration_days
                FROM leave_requests lr
                JOIN employees e ON e.id = lr.employee_id
                {$sqlWhere}
                ORDER BY lr.start_date ASC";

        $stmt = (new LeaveRequest())->db()->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->json([
            'success' => true,
            'data'    => $rows,
            'meta'    => ['from' => $from, 'to' => $to, 'employee_id' => $empId > 0 ? $empId : null],
        ]);
    }

    public function employees(): void
    {
        $this->requireApiKey();

        $status = $this->getGet('status', 'active');
        $deptId = Sanitize::int($this->getGet('department_id'));

        $where = ['e.status = :status'];
        $params = [':status' => $status];

        if ($deptId > 0) {
            $where[] = 'e.department_id = :department_id';
            $params[':department_id'] = $deptId;
        }

        $sqlWhere = 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT e.id, e.employee_code, e.first_name, e.last_name, e.email, e.phone,
                       d.name AS department_name, e.status, e.hire_date
                FROM employees e
                LEFT JOIN departments d ON d.id = e.department_id
                {$sqlWhere}
                ORDER BY e.last_name ASC, e.first_name ASC";

        $stmt = (new Employee())->db()->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->json([
            'success' => true,
            'data'    => $rows,
            'meta'    => ['status' => $status, 'department_id' => $deptId > 0 ? $deptId : null],
        ]);
    }

    private function requireApiKey(): void
    {
        $apiKey = $this->getGet('api_key') ?? $this->getServer('HTTP_X_API_KEY') ?? '';
        $settingModel = new \App\Models\Setting();
        $validKey = $settingModel->get('payroll_api_key', '');

        if ($apiKey === '' || $validKey === '' || !hash_equals((string) $validKey, (string) $apiKey)) {
            $this->json(['success' => false, 'message' => 'Clé API invalide ou manquante.'], 401);
        }
    }

    private function getServer(string $key): ?string
    {
        return $_SERVER[$key] ?? null;
    }
}
