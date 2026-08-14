<?php
namespace App\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Helpers\Sanitize;
use App\Helpers\AuditLogger;
use App\Helpers\Session;
use App\Helpers\Logger;

/**
 * Contrôleur des départements.
 */
class DepartmentsController extends BaseController
{
    private Department $departmentModel;
    private Employee $employeeModel;

    public function __construct()
    {
        parent::__construct();
        $this->departmentModel = new Department();
        $this->employeeModel = new Employee();
    }

    public function index(): void
    {
        $this->requirePermission('departments', 'view');
        $departments = $this->departmentModel->all('name ASC');
        foreach ($departments as &$d) {
            $stmt = $this->employeeModel->db()
                ->prepare("SELECT COUNT(*) FROM employees WHERE department_id = ? AND status = 'active'");
            $stmt->execute([$d['id']]);
            $d['employee_count'] = (int) $stmt->fetchColumn();
        }
        unset($d);
        $this->render('departments/index', [
            'pageTitle'    => 'Départements',
            'departments'  => $departments,
        ]);
    }

    public function create(): void
    {
        $this->requirePermission('departments', 'create');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $errors = $this->validate(['name']);
            if ($errors === true) {
                $data = [
                    'name'        => $this->getPost('name'),
                    'description' => $this->getPost('description'),
                    'schedule_id' => Sanitize::int($this->getPost('schedule_id')),
                    'manager_id'  => Sanitize::int($this->getPost('manager_id')),
                    'status'      => $this->getPost('status') === 'inactive' ? 'inactive' : 'active',
                ];
                $id = $this->departmentModel->create($data);
                AuditLogger::log('create', 'departments', 'Département créé', $id, null, $data);
                Session::set('flash_success', 'Département créé.');
                $this->refreshDashboardStats();
                $this->redirect($this->url('departments', 'index'));
            }
            $this->render('departments/create', [
                'pageTitle'   => 'Nouveau département',
                'errors'      => is_array($errors) ? $errors : [],
                'department'  => $_POST,
                'schedules'   => (new \App\Models\WorkSchedule())->all('name ASC'),
                'managers'    => $this->employeeModel->all('first_name ASC'),
            ]);
            return;
        }

        $this->render('departments/create', [
            'pageTitle'  => 'Nouveau département',
            'schedules'  => (new \App\Models\WorkSchedule())->all('name ASC'),
            'managers'   => $this->employeeModel->all('first_name ASC'),
        ]);
    }

    public function edit(): void
    {
        $this->requirePermission('departments', 'edit');
        $id = Sanitize::int($this->getGet('id'));
        $department = $this->departmentModel->find($id);
        if (!$department) {
            Session::set('flash_error', 'Département introuvable.');
            $this->redirect($this->url('departments', 'index'));
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $errors = $this->validate(['name']);
            if ($errors === true) {
                $old = $department;
                $this->departmentModel->update($id, [
                    'name'        => $this->getPost('name'),
                    'description' => $this->getPost('description'),
                    'schedule_id' => Sanitize::int($this->getPost('schedule_id')),
                    'manager_id'  => Sanitize::int($this->getPost('manager_id')),
                    'status'      => $this->getPost('status') === 'inactive' ? 'inactive' : 'active',
                ]);
                AuditLogger::log('update', 'departments', 'Département modifié', $id, $old, $_POST);
                Session::set('flash_success', 'Département mis à jour.');
                $this->redirect($this->url('departments', 'index'));
            }
        }

        $this->render('departments/edit', [
            'pageTitle'   => 'Modifier département',
            'department'  => $department,
            'schedules'   => (new \App\Models\WorkSchedule())->all('name ASC'),
            'managers'    => $this->employeeModel->all('first_name ASC'),
        ]);
    }

    public function delete(): void
    {
        $this->requirePermission('departments', 'delete');
        $id = Sanitize::int($this->getGet('id'));
        if ($this->departmentModel->find($id)) {
            $this->departmentModel->delete($id);
            AuditLogger::log('delete', 'departments', 'Département supprimé', $id);
            Session::set('flash_success', 'Département supprimé.');
        } else {
            Session::set('flash_error', 'Département introuvable.');
        }
        $this->redirect($this->url('departments', 'index'));
    }
}
