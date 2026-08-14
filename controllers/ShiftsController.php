<?php
namespace App\Controllers;

use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\Employee;
use App\Helpers\Sanitize;
use App\Helpers\AuditLogger;
use App\Helpers\Session;
use App\Helpers\Csrf;

class ShiftsController extends BaseController
{
    private Shift $shiftModel;
    private ShiftAssignment $assignmentModel;
    private Employee $employeeModel;

    public function __construct()
    {
        parent::__construct();
        $this->shiftModel = new Shift();
        $this->assignmentModel = new ShiftAssignment();
        $this->employeeModel = new Employee();
    }

    public function index(): void
    {
        $this->requirePermission('shifts', 'view');
        $shifts = $this->shiftModel->all('name ASC');

        $this->render('shifts/index', [
            'pageTitle' => 'Shifts',
            'shifts'    => $shifts,
        ]);
    }

    public function assignments(): void
    {
        $this->requirePermission('shifts', 'edit');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::verify($this->getPost('csrf_token'))) {
                Session::set('flash_error', 'Jeton de sécurité invalide.');
                $this->redirect($this->url('shifts', 'assignments'));
            }

            $errors = $this->validate(['shift_id', 'employee_id', 'start_date']);

            if ($errors === true) {
                $shiftId    = Sanitize::int($this->getPost('shift_id'));
                $employeeId = Sanitize::int($this->getPost('employee_id'));
                $startDate  = Sanitize::date($this->getPost('start_date'));
                $endDate    = Sanitize::date($this->getPost('end_date'));

                if ($endDate !== '' && $endDate < $startDate) {
                    $errors = ['end_date' => 'La date de fin doit être après la date de début.'];
                } else {
                    $this->assignmentModel->create([
                        'shift_id'    => $shiftId,
                        'employee_id' => $employeeId,
                        'start_date'  => $startDate,
                        'end_date'    => $endDate !== '' ? $endDate : null,
                        'notes'       => $this->getPost('notes'),
                    ]);

                    AuditLogger::log('assignment', 'shifts', 'Affectation de shift', $employeeId);
                    Session::set('flash_success', 'Shift affecté avec succès.');
                    $this->redirect($this->url('shifts', 'assignments'));
                }
            }

            $this->render('shifts/assignments', [
                'pageTitle'   => 'Affectations de shifts',
                'errors'      => is_array($errors) ? $errors : [],
                'shifts'      => $this->shiftModel->all('name ASC'),
                'employees'   => $this->employeeModel->all('first_name ASC, last_name ASC'),
                'assignments' => $this->assignmentModel->all('start_date DESC'),
            ]);
            return;
        }

        $assignmentsRaw = $this->assignmentModel->all('start_date DESC');

        $shiftIds = array_unique(array_column($assignmentsRaw, 'shift_id'));
        $empIds   = array_unique(array_column($assignmentsRaw, 'employee_id'));

        $shiftsMap = [];
        if (!empty($shiftIds)) {
            $stmt = $this->shiftModel->db()->prepare('SELECT id, name, code FROM shifts WHERE id IN (' . implode(',', array_fill(0, count($shiftIds), '?')) . ')');
            $stmt->execute($shiftIds);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $s) {
                $shiftsMap[$s['id']] = $s;
            }
        }

        $empMap = [];
        if (!empty($empIds)) {
            $stmt = $this->employeeModel->db()->prepare('SELECT id, first_name, last_name, employee_code FROM employees WHERE id IN (' . implode(',', array_fill(0, count($empIds), '?')) . ')');
            $stmt->execute($empIds);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $e) {
                $empMap[$e['id']] = $e;
            }
        }

        $assignments = [];
        foreach ($assignmentsRaw as $a) {
            $shift = $shiftsMap[$a['shift_id']] ?? null;
            $emp   = $empMap[$a['employee_id']] ?? null;
            $a['shift_name'] = $shift ? $shift['name'] : '-';
            $a['shift_code'] = $shift ? $shift['code'] : '-';
            $a['first_name'] = $emp ? $emp['first_name'] : '-';
            $a['last_name']  = $emp ? $emp['last_name'] : '-';
            $assignments[] = $a;
        }

        $this->render('shifts/assignments', [
            'pageTitle'   => 'Affectations de shifts',
            'shifts'      => $this->shiftModel->all('name ASC'),
            'employees'   => $this->employeeModel->all('first_name ASC, last_name ASC'),
            'assignments' => $assignments,
        ]);
    }

    public function deleteAssignment(): void
    {
        $this->requirePermission('shifts', 'edit');
        $id = Sanitize::int($this->getPost('id'));

        if (!Csrf::verify($this->getPost('csrf_token'))) {
            Session::set('flash_error', 'Jeton de sécurité invalide.');
            $this->redirect($this->url('shifts', 'assignments'));
        }

        $assignment = $this->assignmentModel->find($id);
        if ($assignment) {
            $this->assignmentModel->delete($id);
            AuditLogger::log('delete', 'shifts', 'Affectation de shift supprimée', $id);
            Session::set('flash_success', 'Affectation supprimée.');
        } else {
            Session::set('flash_error', 'Affectation introuvable.');
        }

        $this->redirect($this->url('shifts', 'assignments'));
    }
}
