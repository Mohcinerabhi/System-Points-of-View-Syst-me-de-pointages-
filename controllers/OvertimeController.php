<?php
namespace App\Controllers;

use App\Models\OvertimeRequest;
use App\Models\Employee;
use App\Helpers\Paginator;
use App\Helpers\Sanitize;
use App\Helpers\AuditLogger;
use App\Helpers\Session;
use App\Helpers\Csrf;
use App\Helpers\Auth;
use App\Helpers\NotificationHelper;

class OvertimeController extends BaseController
{
    private OvertimeRequest $overtimeModel;
    private Employee $employeeModel;

    public function __construct()
    {
        parent::__construct();
        $this->overtimeModel = new OvertimeRequest();
        $this->employeeModel = new Employee();
    }

    public function index(): void
    {
        $this->requirePermission('overtime', 'view');

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

        $result = $this->overtimeModel->paginate(
            $page, $perPage, $conditions,
            [],
            $search,
            'requested_date DESC'
        );

        $paginator = new Paginator($result['total'], $page, $perPage);
        $employees = $this->employeeModel->all('first_name ASC, last_name ASC');

        $this->render('overtime/index', [
            'pageTitle'  => 'Heures supplémentaires',
            'overtimes'  => $result['data'],
            'employees'  => $employees,
            'pagination' => $paginator->toArray(),
            'filters'    => ['status' => $status, 'employee_id' => $empId, 'search' => $search],
        ]);
    }

    public function request(): void
    {
        $this->requirePermission('overtime', 'create');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $errors = $this->validate(['employee_id', 'requested_date', 'start_time', 'end_time', 'estimated_hours']);

            if ($errors === true) {
                $employeeId = Sanitize::int($this->getPost('employee_id'));
                $requestedDate = Sanitize::date($this->getPost('requested_date'));
                $startTime  = Sanitize::time($this->getPost('start_time'));
                $endTime    = Sanitize::time($this->getPost('end_time'));

                $data = [
                    'employee_id'    => $employeeId,
                    'requested_date' => $requestedDate,
                    'start_time'     => $startTime,
                    'end_time'       => $endTime,
                    'estimated_hours'=> Sanitize::float($this->getPost('estimated_hours')),
                    'reason'         => $this->getPost('reason'),
                    'status'         => 'pending',
                ];

                $id = $this->overtimeModel->create($data);
                AuditLogger::log('create', 'overtime', 'Demande d\'heures supplémentaires créée', $id, null, $data);

                $employee = $this->employeeModel->find($employeeId);
                if ($employee) {
                    NotificationHelper::send(
                        'overtime_request',
                        'Nouvelle demande d\'heures supplémentaires',
                        sprintf('%s %s (%s) demande %s heures supplémentaires le %s.',
                            $employee['first_name'], $employee['last_name'], $employee['employee_code'],
                            $data['estimated_hours'], $requestedDate),
                        null,
                        ['overtime_id' => $id, 'employee_id' => $employeeId]
                    );
                }

                Session::set('flash_success', 'Demande d\'heures supplémentaires créée avec succès.');
                $this->redirect($this->url('overtime', 'index'));
            }

            $this->render('overtime/create', [
                'pageTitle' => 'Nouvelle demande d\'heures supplémentaires',
                'errors'    => is_array($errors) ? $errors : [],
                'employees' => $this->employeeModel->all('first_name ASC, last_name ASC'),
                'overtime'  => $_POST,
            ]);
            return;
        }

        $this->render('overtime/create', [
            'pageTitle' => 'Nouvelle demande d\'heures supplémentaires',
            'employees' => $this->employeeModel->all('first_name ASC, last_name ASC'),
        ]);
    }

    public function approve(): void
    {
        $this->requirePermission('overtime', 'edit');
        $id = Sanitize::int($this->getPost('id'));

        if (!Csrf::verify($this->getPost('csrf_token'))) {
            Session::set('flash_error', 'Jeton de sécurité invalide.');
            $this->redirect($this->url('overtime', 'index'));
        }

        $overtime = $this->overtimeModel->find($id);
        if (!$overtime || $overtime['status'] !== 'pending') {
            Session::set('flash_error', 'Demande introuvable.');
            $this->redirect($this->url('overtime', 'index'));
        }

        $userId = Auth::id();
        $old = $overtime;
        $this->overtimeModel->update($id, [
            'status'      => 'approved',
            'approved_by' => $userId,
        ]);
        AuditLogger::log('approve', 'overtime', 'Heures supplémentaires approuvées', $id, $old, ['status' => 'approved']);

        NotificationHelper::send(
            'overtime_approved',
            'Heures supplémentaires approuvées',
            sprintf('Votre demande d\'heures supplémentaires du %s a été approuvée.',
                $overtime['requested_date']),
            (int) $overtime['employee_id'],
            ['overtime_id' => $id]
        );

        Session::set('flash_success', 'Heures supplémentaires approuvées avec succès.');
        $this->redirect($this->url('overtime', 'index'));
    }

    public function reject(): void
    {
        $this->requirePermission('overtime', 'edit');
        $id = Sanitize::int($this->getPost('id'));

        if (!Csrf::verify($this->getPost('csrf_token'))) {
            Session::set('flash_error', 'Jeton de sécurité invalide.');
            $this->redirect($this->url('overtime', 'index'));
        }

        $overtime = $this->overtimeModel->find($id);
        if (!$overtime || $overtime['status'] !== 'pending') {
            Session::set('flash_error', 'Demande introuvable.');
            $this->redirect($this->url('overtime', 'index'));
        }

        $rejectionReason = $this->getPost('rejection_reason');
        $userId = Auth::id();
        $old = $overtime;
        $this->overtimeModel->update($id, [
            'status'           => 'rejected',
            'approved_by'      => $userId,
            'rejection_reason' => $rejectionReason,
        ]);
        AuditLogger::log('reject', 'overtime', 'Heures supplémentaires rejetées', $id, $old, ['status' => 'rejected', 'rejection_reason' => $rejectionReason]);

        NotificationHelper::send(
            'overtime_rejected',
            'Heures supplémentaires rejetées',
            sprintf('Votre demande d\'heures supplémentaires du %s a été rejetée. Motif : %s',
                $overtime['requested_date'], $rejectionReason ?: 'Non spécifié'),
            (int) $overtime['employee_id'],
            ['overtime_id' => $id]
        );

        Session::set('flash_success', 'Demande rejetée.');
        $this->redirect($this->url('overtime', 'index'));
    }
}
