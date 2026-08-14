<?php
namespace App\Controllers;

use App\Models\LeaveRequest;
use App\Models\Employee;
use App\Helpers\Paginator;
use App\Helpers\Sanitize;
use App\Helpers\AuditLogger;
use App\Helpers\Session;
use App\Helpers\Csrf;
use App\Helpers\Auth;
use App\Helpers\NotificationHelper;

class LeavesController extends BaseController
{
    private LeaveRequest $leaveModel;
    private Employee $employeeModel;

    public function __construct()
    {
        parent::__construct();
        $this->leaveModel = new LeaveRequest();
        $this->employeeModel = new Employee();
    }

    public function index(): void
    {
        $this->requirePermission('leaves', 'view');

        $page    = Sanitize::int($this->getGet('page'), 1);
        $perPage = Sanitize::int($this->getGet('per_page'), 20);
        $status  = $this->getGet('status', '');
        $empId   = Sanitize::int($this->getGet('employee_id'));
        $search  = $this->getGet('search');

        $conditions = [];
        if ($status !== '') {
            $conditions['status'] = $status;
        }
        if ($empId > 0) {
            $conditions['employee_id'] = $empId;
        }

        $result = $this->leaveModel->paginateWithEmployee(
            $page, $perPage, $conditions,
            ['e.first_name', 'e.last_name', 'lr.leave_type'],
            $search,
            'lr.created_at DESC'
        );

        $paginator = new Paginator($result['total'], $page, $perPage);
        $employees = $this->employeeModel->all('first_name ASC, last_name ASC');

        $this->render('leaves/index', [
            'pageTitle'  => 'Congés',
            'leaves'     => $result['data'],
            'employees'  => $employees,
            'pagination' => $paginator->toArray(),
            'filters'    => ['status' => $status, 'employee_id' => $empId, 'search' => $search],
        ]);
    }

    public function create(): void
    {
        $this->requirePermission('leaves', 'create');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $errors = $this->validate(['employee_id', 'leave_type', 'start_date', 'end_date']);

            if ($errors === true) {
                $employeeId = Sanitize::int($this->getPost('employee_id'));
                $startDate  = Sanitize::date($this->getPost('start_date'));
                $endDate    = Sanitize::date($this->getPost('end_date'));

                if ($endDate < $startDate) {
                    $errors = ['end_date' => 'La date de fin doit être après la date de début.'];
                } else {
                    $data = [
                        'employee_id' => $employeeId,
                        'leave_type'  => $this->getPost('leave_type'),
                        'start_date'  => $startDate,
                        'end_date'    => $endDate,
                        'reason'      => $this->getPost('reason'),
                        'status'      => 'pending',
                    ];

                    $id = $this->leaveModel->create($data);
                    AuditLogger::log('create', 'leaves', 'Demande de congé créée', $id, null, $data);

                    $employee = $this->employeeModel->find($employeeId);
                    if ($employee) {
                        NotificationHelper::send(
                            'leave_request',
                            'Nouvelle demande de congé',
                            sprintf('%s %s (%s) a demandé un congé du %s au %s.',
                                $employee['first_name'], $employee['last_name'], $employee['employee_code'],
                                $startDate, $endDate),
                            null,
                            ['leave_id' => $id, 'employee_id' => $employeeId]
                        );
                    }

                    Session::set('flash_success', 'Demande de congé créée avec succès.');
                    $this->redirect($this->url('leaves', 'index'));
                }
            }

            $this->render('leaves/create', [
                'pageTitle' => 'Nouvelle demande de congé',
                'errors'    => is_array($errors) ? $errors : [],
                'employees' => $this->employeeModel->all('first_name ASC, last_name ASC'),
                'leave'     => $_POST,
            ]);
            return;
        }

        $this->render('leaves/create', [
            'pageTitle' => 'Nouvelle demande de congé',
            'employees' => $this->employeeModel->all('first_name ASC, last_name ASC'),
        ]);
    }

    public function view(): void
    {
        $this->requirePermission('leaves', 'view');
        $id = Sanitize::int($this->getGet('id'));
        $leave = $this->leaveModel->find($id);

        if (!$leave) {
            Session::set('flash_error', 'Demande introuvable.');
            $this->redirect($this->url('leaves', 'index'));
        }

        $employee = $leave['employee_id'] ? $this->employeeModel->find((int) $leave['employee_id']) : null;
        $approver = $leave['approved_by'] ? $this->employeeModel->find((int) $leave['approved_by']) : null;

        $this->render('leaves/view', [
            'pageTitle' => 'Détail de la demande de congé',
            'leave'     => $leave,
            'employee'  => $employee,
            'approver'  => $approver,
        ]);
    }

    public function approve(): void
    {
        $this->requirePermission('leaves', 'edit');
        $id = Sanitize::int($this->getPost('id'));

        if (!Csrf::verify($this->getPost('csrf_token'))) {
            Session::set('flash_error', 'Jeton de sécurité invalide.');
            $this->redirect($this->url('leaves', 'index'));
        }

        $leave = $this->leaveModel->find($id);
        if (!$leave || $leave['status'] !== 'pending') {
            Session::set('flash_error', 'Demande introuvable.');
            $this->redirect($this->url('leaves', 'index'));
        }

        $userId = Auth::id();
        $old = $leave;
        $this->leaveModel->update($id, [
            'status'      => 'approved',
            'approved_by' => $userId,
        ]);
        AuditLogger::log('approve', 'leaves', 'Congé approuvé', $id, $old, ['status' => 'approved']);

        NotificationHelper::send(
            'leave_approved',
            'Congé approuvé',
            sprintf('Votre demande de congé du %s au %s a été approuvée.',
                $leave['start_date'], $leave['end_date']),
            (int) $leave['employee_id'],
            ['leave_id' => $id]
        );

        Session::set('flash_success', 'Congé approuvé avec succès.');
        $this->redirect($this->url('leaves', 'index'));
    }

    public function reject(): void
    {
        $this->requirePermission('leaves', 'edit');
        $id = Sanitize::int($this->getPost('id'));

        if (!Csrf::verify($this->getPost('csrf_token'))) {
            Session::set('flash_error', 'Jeton de sécurité invalide.');
            $this->redirect($this->url('leaves', 'index'));
        }

        $leave = $this->leaveModel->find($id);
        if (!$leave || $leave['status'] !== 'pending') {
            Session::set('flash_error', 'Demande introuvable.');
            $this->redirect($this->url('leaves', 'index'));
        }

        $rejectionReason = $this->getPost('rejection_reason');
        $userId = Auth::id();
        $old = $leave;
        $this->leaveModel->update($id, [
            'status'           => 'rejected',
            'approved_by'      => $userId,
            'rejection_reason' => $rejectionReason,
        ]);
        AuditLogger::log('reject', 'leaves', 'Congé rejeté', $id, $old, ['status' => 'rejected', 'rejection_reason' => $rejectionReason]);

        NotificationHelper::send(
            'leave_rejected',
            'Congé rejeté',
            sprintf('Votre demande de congé du %s au %s a été rejetée. Motif : %s',
                $leave['start_date'], $leave['end_date'], $rejectionReason ?: 'Non spécifié'),
            (int) $leave['employee_id'],
            ['leave_id' => $id]
        );

        Session::set('flash_success', 'Congé rejeté.');
        $this->redirect($this->url('leaves', 'index'));
    }

    public function cancel(): void
    {
        $this->requirePermission('leaves', 'edit');
        $id = Sanitize::int($this->getPost('id'));

        if (!Csrf::verify($this->getPost('csrf_token'))) {
            Session::set('flash_error', 'Jeton de sécurité invalide.');
            $this->redirect($this->url('leaves', 'index'));
        }

        $leave = $this->leaveModel->find($id);
        if (!$leave) {
            Session::set('flash_error', 'Demande introuvable.');
            $this->redirect($this->url('leaves', 'index'));
        }

        $old = $leave;
        $this->leaveModel->update($id, ['status' => 'cancelled']);
        AuditLogger::log('cancel', 'leaves', 'Congé annulé', $id, $old, ['status' => 'cancelled']);

        Session::set('flash_success', 'Demande de congé annulée.');
        $this->redirect($this->url('leaves', 'index'));
    }
}
