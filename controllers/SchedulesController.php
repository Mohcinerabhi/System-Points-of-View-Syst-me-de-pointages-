<?php
namespace App\Controllers;

use App\Models\WorkSchedule;
use App\Models\ScheduleAssignment;
use App\Models\Employee;
use App\Helpers\Paginator;
use App\Helpers\Sanitize;
use App\Helpers\AuditLogger;
use App\Helpers\Session;
use App\Helpers\Csrf;

/**
 * Contrôleur des horaires de travail et de leurs affectations.
 */
class SchedulesController extends BaseController
{
    private WorkSchedule $scheduleModel;
    private ScheduleAssignment $assignmentModel;
    private Employee $employeeModel;

    public function __construct()
    {
        parent::__construct();
        $this->scheduleModel = new WorkSchedule();
        $this->assignmentModel = new ScheduleAssignment();
        $this->employeeModel = new Employee();
    }

    public function index(): void
    {
        $this->requirePermission('schedules', 'view');
        $schedules = $this->scheduleModel->all('name ASC');
        $activeEmployees = $this->employeeModel->where('status = ?', ['active']);

        $assignments = $this->assignmentModel->fetchAll(
            "SELECT sa.`id` AS `id`, sa.`schedule_id` AS `schedule_id`, sa.`employee_id` AS `employee_id`,
                    sa.`start_date` AS `start_date`, sa.`end_date` AS `end_date`, sa.`notes` AS `notes`,
                    e.`first_name` AS `first_name`, e.`last_name` AS `last_name`,
                    e.`employee_code` AS `employee_code`
             FROM `schedule_assignments` sa
             INNER JOIN `employees` e ON e.`id` = sa.`employee_id`
             ORDER BY sa.`start_date` DESC, e.`last_name` ASC, e.`first_name` ASC, sa.`id` DESC"
        );

        $assignmentsBySchedule = [];
        foreach ($assignments as $a) {
            $assignmentsBySchedule[(int) $a['schedule_id']][] = $a;
        }

        $this->render('schedules/index', [
            'pageTitle'            => 'Horaires',
            'schedules'            => $schedules,
            'activeEmployees'      => $activeEmployees,
            'assignmentsBySchedule' => $assignmentsBySchedule,
        ]);
    }

    public function create(): void
    {
        $this->requirePermission('schedules', 'create');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $errors = $this->validate(['name', 'type']);
            if ($errors === true) {
                $data = $this->collectScheduleData();
                $id = $this->scheduleModel->create($data);
                AuditLogger::log('create', 'schedules', 'Horaire créé', $id, null, $data);
                Session::set('flash_success', 'Horaire créé.');
                $this->redirect($this->url('schedules', 'index'));
            }
            $this->render('schedules/create', [
                'pageTitle' => 'Nouvel horaire',
                'errors'    => is_array($errors) ? $errors : [],
                'schedule'  => $_POST,
            ]);
            return;
        }

        $this->render('schedules/create', ['pageTitle' => 'Nouvel horaire']);
    }

    public function edit(): void
    {
        $this->requirePermission('schedules', 'edit');
        $id = Sanitize::int($this->getGet('id'));
        $schedule = $this->scheduleModel->find($id);
        if (!$schedule) {
            Session::set('flash_error', 'Horaire introuvable.');
            $this->redirect($this->url('schedules', 'index'));
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $errors = $this->validate(['name', 'type']);
            if ($errors === true) {
                $old = $schedule;
                $this->scheduleModel->update($id, $this->collectScheduleData());
                AuditLogger::log('update', 'schedules', 'Horaire modifié', $id, $old, $_POST);
                Session::set('flash_success', 'Horaire mis à jour.');
                $this->redirect($this->url('schedules', 'index'));
            }
        }

        $this->render('schedules/edit', [
            'pageTitle' => 'Modifier horaire',
            'schedule'  => $schedule,
        ]);
    }

    public function delete(): void
    {
        $this->requirePermission('schedules', 'delete');
        $id = Sanitize::int($this->getGet('id'));

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::verify($this->getPost('csrf_token'))) {
                Session::set('flash_error', 'Jeton de sécurité invalide.');
                $this->redirect($this->url('schedules', 'index'));
            }

            $assignmentCount = (int) ($this->assignmentModel->fetch('SELECT COUNT(*) AS cnt FROM `schedule_assignments` WHERE `schedule_id` = :sid', [':sid' => $id])['cnt'] ?? 0);
            if ($assignmentCount > 0) {
                Session::set('flash_error', 'Impossible de supprimer : cet horaire est assigné à ' . $assignmentCount . ' employé(s). Retirez d\'abord les affectations.');
                $this->redirect($this->url('schedules', 'index'));
            }

            if ($this->scheduleModel->find($id)) {
                $this->scheduleModel->delete($id);
                AuditLogger::log('delete', 'schedules', 'Horaire supprimé', $id);
                Session::set('flash_success', 'Horaire supprimé.');
            } else {
                Session::set('flash_error', 'Horaire introuvable.');
            }
            $this->redirect($this->url('schedules', 'index'));
        }

        $schedule = $this->scheduleModel->find($id);
        if (!$schedule) {
            Session::set('flash_error', 'Horaire introuvable.');
            $this->redirect($this->url('schedules', 'index'));
        }

        $assignmentCount = (int) ($this->assignmentModel->fetch('SELECT COUNT(*) AS cnt FROM `schedule_assignments` WHERE `schedule_id` = :sid', [':sid' => $id])['cnt'] ?? 0);
        if ($assignmentCount > 0) {
            Session::set('flash_error', 'Impossible de supprimer : cet horaire est assigné à ' . $assignmentCount . ' employé(s). Retirez d\'abord les affectations.');
            $this->redirect($this->url('schedules', 'index'));
        }

        $this->render('schedules/delete', [
            'pageTitle'   => 'Supprimer l\'horaire',
            'schedule'    => $schedule,
            'csrf_token'  => Csrf::token(),
        ]);
    }

    /**
     * Gestion des affectations d'horaires aux employés.
     */
    public function assignments(): void
    {
        $this->requirePermission('schedules', 'edit');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::verify($this->getPost('csrf_token'))) {
                Session::set('flash_error', 'Jeton de sécurité invalide.');
                $this->redirect($this->url('schedules', 'assignments'));
            }
            $scheduleId = Sanitize::int($this->getPost('schedule_id'));
            $employeeId = Sanitize::int($this->getPost('employee_id'));
            $startDate  = Sanitize::date($this->getPost('start_date'), date('Y-m-d'));
            $endDate    = Sanitize::date($this->getPost('end_date'));

            if ($scheduleId && $employeeId) {
                try {
                    $this->assignmentModel->create([
                        'schedule_id' => $scheduleId,
                        'employee_id' => $employeeId,
                        'start_date'  => $startDate,
                        'end_date'    => $endDate !== '' ? $endDate : null,
                        'notes'       => $this->getPost('notes'),
                    ]);
                    AuditLogger::log('assignment', 'schedules', 'Affectation horaire', $employeeId);
                    Session::set('flash_success', 'Affectation enregistrée.');
                } catch (\Throwable $e) {
                    $this->logException('SchedulesController::assignments CREATE FAILED', $e, [
                        'scheduleId' => $scheduleId,
                        'employeeId' => $employeeId,
                        'startDate'  => $startDate,
                        'endDate'    => $endDate,
                    ]);
                    Session::set('flash_error', 'Erreur lors de l\'enregistrement de l\'affectation. Vérifiez que cette affectation n\'existe pas déjà.');
                }
            }
            $this->redirect($this->url('schedules', 'assignments'));
        }

        $assignmentsRaw = $this->assignmentModel->all('start_date DESC');

        $scheduleIds = array_unique(array_column($assignmentsRaw, 'schedule_id'));
        $empIds      = array_unique(array_column($assignmentsRaw, 'employee_id'));

        $schedulesMap = [];
        if (!empty($scheduleIds)) {
            $stmt = $this->scheduleModel->db()->prepare('SELECT id, name FROM work_schedules WHERE id IN (' . implode(',', array_fill(0, count($scheduleIds), '?')) . ')');
            $stmt->execute($scheduleIds);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $s) {
                $schedulesMap[$s['id']] = $s;
            }
        }

        $empMap = [];
        if (!empty($empIds)) {
            $stmt = $this->employeeModel->db()->prepare('SELECT id, first_name, last_name FROM employees WHERE id IN (' . implode(',', array_fill(0, count($empIds), '?')) . ')');
            $stmt->execute($empIds);
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $e) {
                $empMap[$e['id']] = $e;
            }
        }

        $assignments = [];
        foreach ($assignmentsRaw as $a) {
            $schedule = $schedulesMap[$a['schedule_id']] ?? null;
            $emp      = $empMap[$a['employee_id']] ?? null;
            $a['schedule_name'] = $schedule ? $schedule['name'] : '-';
            $a['first_name']    = $emp ? $emp['first_name'] : '-';
            $a['last_name']     = $emp ? $emp['last_name'] : '-';
            $assignments[] = $a;
        }

        $this->render('schedules/assignments', [
            'pageTitle'   => 'Affectations d\'horaires',
            'schedules'   => $this->scheduleModel->all('name ASC'),
            'employees'   => $this->employeeModel->all('first_name ASC'),
            'assignments' => $assignments,
        ]);
    }

    public function deleteAssignment(): void
    {
        $this->requirePermission('schedules', 'edit');
        $id = Sanitize::int($this->getPost('id'));

        if (!Csrf::verify($this->getPost('csrf_token'))) {
            Session::set('flash_error', 'Jeton de sécurité invalide.');
            $this->redirect($this->url('schedules', 'assignments'));
        }

        $assignment = $this->assignmentModel->find($id);
        if ($assignment) {
            $this->assignmentModel->delete($id);
            AuditLogger::log('delete', 'schedules', 'Affectation horaire supprimée', $id);
            Session::set('flash_success', 'Affectation supprimée.');
        } else {
            Session::set('flash_error', 'Affectation introuvable.');
        }

        $this->redirect($this->url('schedules', 'assignments'));
    }

    /**
     * Charge dynamiquement tous les employés actifs (liste déroulante).
     */
    public function ajaxLoadEmployees(): void
    {
        $this->requirePermission('schedules', 'view');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->json(['success' => false, 'message' => 'Methode non autorisee'], 405);
            return;
        }

        $employees = $this->employeeModel->fetchAll(
            "SELECT `id`, `employee_code`, `first_name`, `last_name`
             FROM `employees`
             WHERE `status` = 'active'
             ORDER BY `last_name` ASC, `first_name` ASC, `id` ASC"
        );

        $list = array_map(static function (array $e): array {
            return [
                'id'            => (int) ($e['id'] ?? 0),
                'employee_code' => $e['employee_code'] ?? '',
                'first_name'    => $e['first_name'] ?? '',
                'last_name'     => $e['last_name'] ?? '',
            ];
        }, $employees);

        $this->json(['success' => true, 'employees' => $list]);
    }

    public function ajaxAssign(): void
    {
        $this->requirePermission('schedules', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Methode non autorisee'], 405);
            return;
        }

        if (!Csrf::verify($this->getPost('csrf_token'))) {
            $this->json(['success' => false, 'message' => 'Jeton de securite invalide'], 419);
            return;
        }

        $scheduleId = Sanitize::int($this->getPost('schedule_id'));
        $employeeId = Sanitize::int($this->getPost('employee_id'));
        $startDate  = Sanitize::date($this->getPost('start_date'), date('Y-m-d'));
        $endDate    = Sanitize::date($this->getPost('end_date'));

        if (!$scheduleId || !$employeeId) {
            $this->json(['success' => false, 'message' => 'Donnees manquantes'], 400);
            return;
        }

        if (!$this->scheduleModel->find($scheduleId)) {
            $this->json(['success' => false, 'message' => 'Horaire introuvable'], 404);
            return;
        }

        $employee = $this->employeeModel->find($employeeId);
        if (!$employee || ($employee['status'] ?? '') !== 'active') {
            $this->json(['success' => false, 'message' => 'Employe introuvable ou inactif'], 404);
            return;
        }

        $existing = $this->assignmentModel->where(
            'schedule_id = ? AND employee_id = ? AND start_date = ?',
            [$scheduleId, $employeeId, $startDate]
        );

        if (!empty($existing)) {
            $this->json(['success' => false, 'message' => 'Cet employe est deja affecte a cet horaire pour cette date'], 409);
            return;
        }

        try {
            $newId = $this->assignmentModel->create([
                'schedule_id' => $scheduleId,
                'employee_id' => $employeeId,
                'start_date'  => $startDate,
                'end_date'    => $endDate !== '' ? $endDate : null,
                'notes'       => $this->getPost('notes'),
            ]);

            $assignment = $this->assignmentModel->fetchOneRaw(
                "SELECT sa.`id` AS `id`, sa.`employee_id` AS `employee_id`,
                        sa.`start_date` AS `start_date`, sa.`end_date` AS `end_date`,
                        e.`first_name` AS `first_name`, e.`last_name` AS `last_name`
                 FROM `schedule_assignments` sa
                 INNER JOIN `employees` e ON e.`id` = sa.`employee_id`
                 WHERE sa.`id` = :id",
                [':id' => $newId]
            );

            $countRow = $this->assignmentModel->fetch(
                'SELECT COUNT(*) AS cnt FROM `schedule_assignments` WHERE `schedule_id` = :sid',
                [':sid' => $scheduleId]
            );
            $count = $countRow ? (int) ($countRow['cnt'] ?? 0) : 0;

            AuditLogger::log('assignment', 'schedules', 'Affectation horaire via AJAX', $employeeId);
            $this->json([
                'success'    => true,
                'message'    => 'Affectation enregistrée',
                'assignment' => $assignment,
                'count'      => $count,
            ]);
        } catch (\Throwable $e) {
            $this->logException('SchedulesController::ajaxAssign CREATE FAILED', $e, [
                'scheduleId' => $scheduleId,
                'employeeId' => $employeeId,
                'startDate'  => $startDate,
                'endDate'    => $endDate,
            ]);
            $this->json(['success' => false, 'message' => 'Erreur lors de l\'enregistrement de l\'affectation'], 500);
        }
    }

    public function ajaxRemoveAssignment(): void
    {
        $this->requirePermission('schedules', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'Methode non autorisee'], 405);
            return;
        }

        if (!Csrf::verify($this->getPost('csrf_token'))) {
            $this->json(['success' => false, 'message' => 'Jeton de securite invalide'], 419);
            return;
        }

        $id = Sanitize::int($this->getPost('id'));

        if (!$id) {
            $this->json(['success' => false, 'message' => 'ID manquant'], 400);
            return;
        }

        $assignment = $this->assignmentModel->find($id);
        if (!$assignment) {
            $this->json(['success' => false, 'message' => 'Affectation introuvable'], 404);
            return;
        }

        $scheduleId = (int) $assignment['schedule_id'];

        try {
            $this->assignmentModel->delete($id);
            $countRow = $this->assignmentModel->fetch(
                'SELECT COUNT(*) AS cnt FROM `schedule_assignments` WHERE `schedule_id` = :sid',
                [':sid' => $scheduleId]
            );
            $count = $countRow ? (int) ($countRow['cnt'] ?? 0) : 0;
            AuditLogger::log('delete', 'schedules', 'Affectation horaire supprimee via AJAX', $id);
            $this->json([
                'success' => true,
                'message' => 'Affectation supprimee',
                'count'   => $count,
            ]);
        } catch (\Throwable $e) {
            $this->logException('SchedulesController::ajaxRemoveAssignment DELETE FAILED', $e, ['id' => $id]);
            $this->json(['success' => false, 'message' => 'Erreur lors de la suppression de l\'affectation'], 500);
        }
    }

    private function collectScheduleData(): array
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $workingDays = array_map('strtolower', $this->getPost('working_day', []));
        $data = [
            'name'                           => $this->getPost('name'),
            'description'                    => $this->getPost('description'),
            'type'                           => $this->getPost('type') === 'flexible' ? 'flexible' : 'fixed',
            'late_tolerance_minutes'         => Sanitize::int($this->getPost('late_tolerance_minutes'), 15),
            'early_departure_tolerance_minutes' => Sanitize::int($this->getPost('early_departure_tolerance_minutes'), 10),
            'required_work_hours'            => Sanitize::float($this->getPost('required_work_hours'), 8.0),
            'is_active'                      => $this->getPost('is_active') ? 1 : 0,
            'break_start'                    => Sanitize::time($this->getPost('break_start')),
            'break_end'                      => Sanitize::time($this->getPost('break_end')),
        ];
        foreach ($days as $day) {
            if (in_array($day, $workingDays, true)) {
                $data[$day . '_start'] = Sanitize::time($this->getPost($day . '_start'));
                $data[$day . '_end']   = Sanitize::time($this->getPost($day . '_end'));
            } else {
                $data[$day . '_start'] = null;
                $data[$day . '_end']   = null;
            }
        }
        return $data;
    }
}
