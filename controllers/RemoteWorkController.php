<?php
namespace App\Controllers;

use App\Models\RemoteWork;
use App\Models\Employee;
use App\Helpers\Paginator;
use App\Helpers\Sanitize;
use App\Helpers\AuditLogger;
use App\Helpers\Session;
use App\Helpers\Csrf;
use App\Helpers\Auth;
use App\Helpers\NotificationHelper;

class RemoteWorkController extends BaseController
{
    private RemoteWork $remoteModel;
    private Employee $employeeModel;

    public function __construct()
    {
        parent::__construct();
        $this->remoteModel = new RemoteWork();
        $this->employeeModel = new Employee();
    }

    public function index(): void
    {
        $this->requirePermission('remote_work', 'view');

        $page    = Sanitize::int($this->getGet('page'), 1);
        $perPage = Sanitize::int($this->getGet('per_page'), 20);
        $status  = $this->getGet('status', '');
        $empId   = Sanitize::int($this->getGet('employee_id'));
        $search  = $this->getGet('search');

        $conditions = [];
        if ($status !== '') {
            $conditions['rw.status'] = $status;
        }
        if ($empId > 0) {
            $conditions['rw.employee_id'] = $empId;
        }

        $where = [];
        $params = [];
        foreach ($conditions as $col => $val) {
            $where[] = "{$col} = ?";
            $params[] = $val;
        }
        if ($search !== null && $search !== '') {
            $where[] = '(e.first_name LIKE ? OR e.last_name LIKE ? OR e.employee_code LIKE ? OR rw.reason LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }
        $sqlWhere = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $countSql = "SELECT COUNT(*) FROM remote_work_requests rw LEFT JOIN employees e ON e.id = rw.employee_id{$sqlWhere}";
        $countStmt = $this->remoteModel->db()->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $sql = "SELECT rw.*, e.first_name, e.last_name, e.employee_code
                FROM remote_work_requests rw
                LEFT JOIN employees e ON e.id = rw.employee_id
                {$sqlWhere}
                ORDER BY rw.start_date DESC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->remoteModel->db()->prepare($sql);
        $stmt->execute($params);
        $remotes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $paginator = new Paginator($total, $page, $perPage);
        $employees = $this->employeeModel->all('first_name ASC, last_name ASC');

        $this->render('remote_work/index', [
            'pageTitle'  => 'Travail à distance',
            'remotes'    => $remotes,
            'employees'  => $employees,
            'pagination' => $paginator->toArray(),
            'filters'    => ['status' => $status, 'employee_id' => $empId, 'search' => $search],
        ]);
    }

    public function request(): void
    {
        $this->requirePermission('remote_work', 'create');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $errors = $this->validate(['employee_id', 'start_date', 'end_date']);

            if ($errors === true) {
                $employeeId = Sanitize::int($this->getPost('employee_id'));
                $startDate  = Sanitize::date($this->getPost('start_date'));
                $endDate    = Sanitize::date($this->getPost('end_date'));

                if ($endDate < $startDate) {
                    $errors = ['end_date' => 'La date de fin doit être après la date de début.'];
                } else {
                    $data = [
                        'employee_id' => $employeeId,
                        'request_date' => date('Y-m-d'),
                        'start_date'  => $startDate,
                        'end_date'    => $endDate,
                        'reason'      => $this->getPost('reason'),
                        'status'      => 'pending',
                    ];

                    $id = $this->remoteModel->create($data);
                    AuditLogger::log('create', 'remote_work', 'Demande de travail à distance créée', $id, null, $data);

                    $employee = $this->employeeModel->find($employeeId);
                    if ($employee) {
                        NotificationHelper::send(
                            'remote_work_request',
                            'Nouvelle demande de travail à distance',
                            sprintf('%s %s (%s) demande de travailler à distance du %s au %s.',
                                $employee['first_name'], $employee['last_name'], $employee['employee_code'],
                                $startDate, $endDate),
                            null,
                            ['remote_id' => $id, 'employee_id' => $employeeId]
                        );
                    }

                    Session::set('flash_success', 'Demande de travail à distance créée avec succès.');
                    $this->redirect($this->url('remote_work', 'index'));
                }
            }

            $this->render('remote_work/request', [
                'pageTitle' => 'Nouvelle demande de travail à distance',
                'errors'    => is_array($errors) ? $errors : [],
                'employees' => $this->employeeModel->all('first_name ASC, last_name ASC'),
                'remote'    => $_POST,
            ]);
            return;
        }

        $this->render('remote_work/request', [
            'pageTitle' => 'Nouvelle demande de travail à distance',
            'employees' => $this->employeeModel->all('first_name ASC, last_name ASC'),
        ]);
    }

    public function approve(): void
    {
        $this->requirePermission('remote_work', 'edit');
        $id = Sanitize::int($this->getPost('id'));

        if (!Csrf::verify($this->getPost('csrf_token'))) {
            Session::set('flash_error', 'Jeton de sécurité invalide.');
            $this->redirect($this->url('remote_work', 'index'));
        }

        $remote = $this->remoteModel->find($id);
        if (!$remote || $remote['status'] !== 'pending') {
            Session::set('flash_error', 'Demande introuvable.');
            $this->redirect($this->url('remote_work', 'index'));
        }

        $userId = Auth::id();
        $old = $remote;
        $this->remoteModel->update($id, [
            'status'      => 'approved',
            'approved_by' => $userId,
        ]);
        AuditLogger::log('approve', 'remote_work', 'Travail à distance approuvé', $id, $old, ['status' => 'approved']);

        NotificationHelper::send(
            'remote_work_approved',
            'Travail à distance approuvé',
            sprintf('Votre demande de travail à distance du %s au %s a été approuvée.',
                $remote['start_date'], $remote['end_date']),
            (int) $remote['employee_id'],
            ['remote_id' => $id]
        );

        Session::set('flash_success', 'Demande de travail à distance approuvée.');
        $this->redirect($this->url('remote_work', 'index'));
    }

    public function reject(): void
    {
        $this->requirePermission('remote_work', 'edit');
        $id = Sanitize::int($this->getPost('id'));

        if (!Csrf::verify($this->getPost('csrf_token'))) {
            Session::set('flash_error', 'Jeton de sécurité invalide.');
            $this->redirect($this->url('remote_work', 'index'));
        }

        $remote = $this->remoteModel->find($id);
        if (!$remote || $remote['status'] !== 'pending') {
            Session::set('flash_error', 'Demande introuvable.');
            $this->redirect($this->url('remote_work', 'index'));
        }

        $rejectionReason = $this->getPost('rejection_reason');
        $userId = Auth::id();
        $old = $remote;
        $this->remoteModel->update($id, [
            'status'           => 'rejected',
            'approved_by'      => $userId,
            'rejection_reason' => $rejectionReason,
        ]);
        AuditLogger::log('reject', 'remote_work', 'Travail à distance rejeté', $id, $old, ['status' => 'rejected', 'rejection_reason' => $rejectionReason]);

        NotificationHelper::send(
            'remote_work_rejected',
            'Travail à distance rejeté',
            sprintf('Votre demande de travail à distance du %s au %s a été rejetée. Motif : %s',
                $remote['start_date'], $remote['end_date'], $rejectionReason ?: 'Non spécifié'),
            (int) $remote['employee_id'],
            ['remote_id' => $id]
        );

        Session::set('flash_success', 'Demande rejetée.');
        $this->redirect($this->url('remote_work', 'index'));
    }

    public function cancel(): void
    {
        $this->requirePermission('remote_work', 'edit');
        $id = Sanitize::int($this->getPost('id'));

        if (!Csrf::verify($this->getPost('csrf_token'))) {
            Session::set('flash_error', 'Jeton de sécurité invalide.');
            $this->redirect($this->url('remote_work', 'index'));
        }

        $remote = $this->remoteModel->find($id);
        if (!$remote) {
            Session::set('flash_error', 'Demande introuvable.');
            $this->redirect($this->url('remote_work', 'index'));
        }

        $old = $remote;
        $this->remoteModel->update($id, ['status' => 'cancelled']);
        AuditLogger::log('cancel', 'remote_work', 'Travail à distance annulé', $id, $old, ['status' => 'cancelled']);

        Session::set('flash_success', 'Demande de travail à distance annulée.');
        $this->redirect($this->url('remote_work', 'index'));
    }
}
