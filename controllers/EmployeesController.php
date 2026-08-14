<?php
namespace App\Controllers;

use App\Models\Employee;
use App\Models\Department;
use App\Models\AttendanceLog;
use App\Models\User;
use App\Helpers\Paginator;
use App\Helpers\CsvHelper;
use App\Helpers\Sanitize;
use App\Helpers\AuditLogger;
use App\Helpers\Session;
use App\Helpers\Csrf;
use App\Helpers\Logger;
use Attendance\Services\TerminalService;

/**
 * ContrÃ´leur CRUD complet des employÃ©s.
 */
class EmployeesController extends BaseController
{
    private Employee $employeeModel;
    private Department $departmentModel;

    public function __construct()
    {
        parent::__construct();
        $this->employeeModel = new Employee();
        $this->departmentModel = new Department();
    }

    /**
     * Liste des employÃ©s avec recherche, filtre et pagination.
     */
    public function index(): void
    {
        $this->requirePermission('employees', 'view');

        $page    = Sanitize::int($this->getGet('page'), 1);
        $perPage = Sanitize::int($this->getGet('per_page'), 20);
        $search  = $this->getGet('search');
        $status  = $this->getGet('status');
        $deptId  = Sanitize::int($this->getGet('department_id'));
        $regStatus = $this->getGet('registration_status', '');

        $conditions = [];
        if ($status) {
            $conditions['status'] = $status;
        }
        if ($deptId > 0) {
            $conditions['department_id'] = $deptId;
        }
        if ($regStatus !== '') {
            $conditions['registration_status'] = $regStatus;
        }
        if ($deptId) {
            $conditions['department_id'] = $deptId;
        }

        $result = $this->employeeModel->paginate(
            $page, $perPage, $conditions,
            ['first_name', 'last_name', 'employee_code', 'phone'],
            $search,
            'first_name ASC, last_name ASC'
        );

        $paginator = new Paginator($result['total'], $page, $perPage);
        $departments = $this->departmentModel->all('name ASC');

        $this->render('employees/index', [
            'pageTitle'   => 'Employés',
            'employees'   => $result['data'],
            'departments' => $departments,
            'pagination'  => $paginator->toArray(),
            'filters'     => ['search' => $search, 'status' => $status, 'department_id' => $deptId, 'registration_status' => $regStatus],
        ]);
    }

    /**
     * GET : formulaire de crÃ©ation. POST : crÃ©ation de l'employÃ©.
     */
    public function create(): void
    {
        $this->requirePermission('employees', 'create');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $errors = $this->validate(['employee_code', 'first_name', 'last_name']);

            if ($errors === true) {
                if ($this->employeeModel->findByCode($this->getPost('employee_code'))) {
                    $errors = ['employee_code' => 'Ce matricule existe déjà.'];
                } else {
                    $data = [
                        'employee_code' => $this->getPost('employee_code'),
                        'first_name'    => $this->getPost('first_name'),
                        'last_name'     => $this->getPost('last_name'),
                        'phone'         => $this->getPost('phone'),
                        'department_id' => Sanitize::int($this->getPost('department_id')),
                        'hire_date'     => Sanitize::date($this->getPost('hire_date')),
                        'badge_id'      => $this->getPost('badge_id'),
                        'badge_code'    => $this->getPost('badge_code'),
                        'status'        => $this->getPost('status') === 'inactive' ? 'inactive' : 'active',
                    ];


                    if (!empty($_FILES['photo']['name'])) {
                        $data['photo'] = $this->uploadPhoto();
                        if ($data['photo'] === false) {
                            $errors = ['photo' => 'Ã‰chec du tÃ©lÃ©versement de la photo.'];
                        }
                    }

                    if ($errors === true) {
                        try {
                            $id = $this->employeeModel->create($data);
                            AuditLogger::log('create', 'employees', 'CrÃ©ation employÃ© ' . $data['employee_code'], $id, null, $data);
                            Session::set('flash_success', 'EmployÃ© crÃ©Ã© avec succÃ¨s.');
                            $this->refreshDashboardStats();
                            $this->redirect($this->url('employees', 'index'));
                        } catch (\Throwable $e) {
                            Logger::exception($e, 'Failed to create employee');
                            Session::set('flash_error', 'Erreur lors de la crÃ©ation : ' . $e->getMessage());
                        }
                    }
                }
            }

            $this->render('employees/create', [
                'pageTitle'   => 'Nouvel employÃ©',
                'errors'      => is_array($errors) ? $errors : [],
                'employee'    => $_POST,
                'departments' => $this->departmentModel->all('name ASC'),
            ]);
            return;
        }

        $this->render('employees/create', [
            'pageTitle'   => 'Nouvel employÃ©',
            'departments' => $this->departmentModel->all('name ASC'),
        ]);
    }

    /**
     * GET : formulaire d'Ã©dition. POST : mise Ã  jour.
     */
    public function edit(): void
    {
        $this->requirePermission('employees', 'edit');

        $id = Sanitize::int($this->getGet('id'));
        if ($id <= 0) {
            Session::set('flash_error', 'Identifiant employÃ© invalide.');
            $this->redirect($this->url('employees', 'index'));
        }

        $employee = $this->employeeModel->find($id);
        if (!$employee) {
            Session::set('flash_error', 'EmployÃ© introuvable.');
            $this->redirect($this->url('employees', 'index'));
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $errors = $this->validate(['employee_code', 'first_name', 'last_name']);

            if ($errors === true) {
                $existing = $this->employeeModel->findByCode($this->getPost('employee_code'));
                if ($existing && (int) $existing->id !== $id) {
                    $errors = ['employee_code' => 'Ce matricule existe dÃ©jÃ .'];
                }

                $badgeCode = $this->getPost('badge_code');
                if ($badgeCode !== '' && $errors === true) {
                    $existingBadge = $this->employeeModel->fetchOneRaw(
                        'SELECT id FROM employees WHERE badge_code = :badge AND id != :id LIMIT 1',
                        [':badge' => $badgeCode, ':id' => $id]
                    );
                    if ($existingBadge) {
                        $errors = ['badge_code' => 'Ce code badge existe dÃ©jÃ .'];
                    }
                }

                if ($errors === true) {
                    $old = $employee;
                    $data = [
                        'employee_code' => $this->getPost('employee_code'),
                        'first_name'    => $this->getPost('first_name'),
                        'last_name'     => $this->getPost('last_name'),
                        'phone'         => $this->getPost('phone'),
                        'department_id' => Sanitize::int($this->getPost('department_id')),
                        'hire_date'     => Sanitize::date($this->getPost('hire_date')),
                        'badge_id'      => $this->getPost('badge_id'),
                        'badge_code'    => $badgeCode,
                        'status'        => $this->getPost('status') === 'inactive' ? 'inactive' : 'active',
                        'registration_status' => $this->getPost('registration_status') ?: 'pending',
                    ];

                    if (!empty($_FILES['photo']['name'])) {
                        $photo = $this->uploadPhoto();
                        if ($photo === false) {
                            $errors = ['photo' => 'Ã‰chec du tÃ©lÃ©versement de la photo.'];
                        } else {
                            $data['photo'] = $photo;
                        }
                    }

                    if ($errors === true) {
                        try {
                            $this->employeeModel->update($id, $data);
                            AuditLogger::log('update', 'employees', 'Modification employÃ© ' . $data['employee_code'], $id, $old, $data);
                            Session::set('flash_success', 'EmployÃ© mis Ã  jour.');
                            $this->refreshDashboardStats();
                            $this->redirect($this->url('employees', 'index'));
                        } catch (\Throwable $e) {
                            Logger::exception($e, 'Failed to update employee');
                            Session::set('flash_error', 'Erreur lors de la mise Ã  jour : ' . $e->getMessage());
                        }
                    }
                }
            }

            $this->render('employees/edit', [
                'pageTitle'   => 'Modifier employÃ©',
                'errors'      => is_array($errors) ? $errors : [],
                'employee'    => array_merge($employee, $_POST),
                'departments' => $this->departmentModel->all('name ASC'),
            ]);
            return;
        }

        $departments = $this->departmentModel->all('name ASC');

        $this->render('employees/edit', [
            'pageTitle'   => 'Modifier employÃ©',
            'employee'    => $employee,
            'departments' => $departments,
        ]);
    }

    /**
     * Suppression d'un employÃ© (POST avec confirmation CSRF).
     */
    public function delete(): void
    {
        $this->requirePermission('employees', 'delete');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect($this->url('employees', 'index'));
            return;
        }

        if (!Csrf::verify($this->getPost('csrf_token'))) {
            Session::set('flash_error', 'Jeton de sÃ©curitÃ© invalide.');
            $this->redirect($this->url('employees', 'index'));
            return;
        }

        $id = Sanitize::int($this->getPost('id'));
        $employee = $this->employeeModel->find($id);

        if (!$employee) {
            Session::set('flash_error', 'EmployÃ© introuvable.');
            $this->redirect($this->url('employees', 'index'));
            return;
        }

        try {
            $this->employeeModel->delete($id);
            AuditLogger::log('delete', 'employees', 'Suppression employÃ© ' . $employee['employee_code'], $id, $employee);
            Session::set('flash_success', 'EmployÃ© supprimÃ©.');
            $this->refreshDashboardStats();
        } catch (\Throwable $e) {
            Logger::exception($e, 'Failed to delete employee');
            Session::set('flash_error', 'Erreur lors de la suppression : ' . $e->getMessage());
        }

        $this->redirect($this->url('employees', 'index'));
    }

    /**
     * DÃ©tail d'un employÃ© avec historique de pointage.
     */
    public function view(): void
    {
        $this->requirePermission('employees', 'view');
        $id = Sanitize::int($this->getGet('id'));
        $employee = $this->employeeModel->find($id);
        if (!$employee) {
            Session::set('flash_error', 'EmployÃ© introuvable.');
            $this->redirect($this->url('employees', 'index'));
        }

        $history = $this->employeeModel->getAttendanceHistory($id, 50);
        $department = $employee['department_id'] ? $this->departmentModel->find($employee['department_id']) : null;

        $this->render('employees/view', [
            'pageTitle'  => $employee['first_name'] . ' ' . $employee['last_name'],
            'employee'   => $employee,
            'department' => $department,
            'history'    => $history,
        ]);
    }

    /**
     * Import CSV d'employÃ©s.
     */
    public function import(): void
    {
        $this->requirePermission('employees', 'create');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::verify($this->getPost('csrf_token'))) {
                Session::set('flash_error', 'Jeton de sÃ©curitÃ© invalide.');
                $this->redirect($this->url('employees', 'import'));
            }

            if (empty($_FILES['csv_file']['tmp_name'])) {
                Session::set('flash_error', 'Veuillez sÃ©lectionner un fichier CSV.');
                $this->redirect($this->url('employees', 'import'));
            }

            $columns = ['employee_code', 'first_name', 'last_name', 'phone', 'department_id', 'hire_date', 'badge_id', 'badge_code'];
            $rows = CsvHelper::read($_FILES['csv_file']['tmp_name'], $columns);
            $imported = 0;
            $skipped = 0;

            foreach ($rows as $row) {
                if (empty($row['employee_code']) || empty($row['first_name'])) {
                    $skipped++;
                    continue;
                }
                if ($this->employeeModel->findByCode($row['employee_code'])) {
                    $skipped++;
                    continue;
                }
                try {
                    $this->employeeModel->create([
                        'employee_code' => $row['employee_code'],
                        'first_name'    => $row['first_name'],
                        'last_name'     => $row['last_name'],
                        'phone'         => $row['phone'],
                        'department_id' => Sanitize::int($row['department_id']),
                        'hire_date'     => Sanitize::date($row['hire_date']),
                        'badge_id'      => $row['badge_id'],
                        'badge_code'    => $row['badge_code'],
                        'status'        => 'active',
                    ]);
                    $imported++;
                } catch (\Throwable $e) {
                    Logger::exception($e, 'Failed to import employee: ' . $row['employee_code']);
                    $skipped++;
                }
            }

            AuditLogger::log('import', 'employees', "Import CSV : {$imported} importÃ©s, {$skipped} ignorÃ©s");
            Session::set('flash_success', "{$imported} employÃ©(s) importÃ©(s), {$skipped} ignorÃ©(s).");
            $this->refreshDashboardStats();
            $this->redirect($this->url('employees', 'index'));
        }

        $this->render('employees/import', ['pageTitle' => 'Importer des employÃ©s']);
    }

    /**
     * Export CSV de tous les employÃ©s.
     */
    public function export(): void
    {
        $this->requirePermission('employees', 'view');

        $employees = $this->employeeModel->all('first_name ASC, last_name ASC');
        $header = [
            'employee_code' => 'Matricule', 'first_name' => 'PrÃ©nom', 'last_name' => 'Nom',
            'phone' => 'TÃ©lÃ©phone', 'department_id' => 'DÃ©partement', 'hire_date' => 'Embauche',
            'badge_id' => 'Badge', 'badge_code' => 'Code badge', 'status' => 'Statut',
        ];

        $departments = [];
        foreach ($this->departmentModel->all() as $d) {
            $departments[$d['id']] = $d['name'];
        }
        foreach ($employees as &$e) {
            $e['department_id'] = $departments[$e['department_id']] ?? '';
        }

        CsvHelper::export($header, $employees, 'employees_' . date('Y-m-d') . '.csv');
    }

    /**
     * Activation / dÃ©sactivation d'un employÃ©.
     */
    public function toggleStatus(): void
    {
        $this->requirePermission('employees', 'edit');
        $id = Sanitize::int($this->getGet('id'));
        if (!$this->employeeModel->find($id)) {
            Session::set('flash_error', 'EmployÃ© introuvable.');
        } else {
            $this->employeeModel->toggleStatus($id);
            AuditLogger::log('toggle_status', 'employees', 'Changement de statut', $id);
            Session::set('flash_success', 'Statut mis Ã  jour.');
        }
        $this->redirect($this->url('employees', 'index'));
    }

    /**
     * Affectation d'un badge / informations terminal.
     */
    public function assignTerminal(): void
    {
        $this->requirePermission('employees', 'edit');
        $id = Sanitize::int($this->getGet('id'));
        $employee = $this->employeeModel->find($id);
        if (!$employee) {
            Session::set('flash_error', 'EmployÃ© introuvable.');
            $this->redirect($this->url('employees', 'index'));
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $errors = $this->validate(['badge_id', 'badge_code']);
            if ($errors === true) {
                try {
                    $this->employeeModel->assignTerminal($id, [
                        'badge_id'            => $this->getPost('badge_id'),
                        'badge_code'          => $this->getPost('badge_code'),
                        'hikvision_user_id'   => Sanitize::int($this->getPost('hikvision_user_id')),
                        'registration_status' => $this->getPost('registration_status') ?: 'pending',
                    ]);
                    AuditLogger::log('assign_terminal', 'employees', 'Affectation terminal', $id);
                    Session::set('flash_success', 'Informations terminal enregistrÃ©es.');
                    $this->refreshDashboardStats();
                    $this->redirect($this->url('employees', 'view', ['id' => $id]));
                } catch (\Throwable $e) {
                    Logger::exception($e, 'Failed to assign terminal to employee');
                    Session::set('flash_error', 'Erreur lors de l\'affectation : ' . $e->getMessage());
                }
            }
            $this->render('employees/assign_terminal', [
                'pageTitle' => 'Affecter un terminal',
                'employee'  => array_merge($employee, $_POST),
                'errors'    => is_array($errors) ? $errors : [],
            ]);
            return;
        }

        $this->render('employees/assign_terminal', [
            'pageTitle' => 'Affecter un terminal',
            'employee'  => $employee,
        ]);
    }

    /**
     * AJAX: liste des employÃ©s pour DataTables / recherche dynamique.
     */
    public function ajaxList(): void
    {
        $this->requirePermission('employees', 'view');

        $draw   = (int) ($this->getGet('draw', 1));
        $start  = (int) ($this->getGet('start', 0));
        $length = (int) ($this->getGet('length', 10));
        $search = $this->getGet('search', '');
        $deptId = Sanitize::int($this->getGet('department_id'));
        $status = $this->getGet('status', '');
        $regStatus = $this->getGet('registration_status', '');

        $page = (int) ($start / max(1, $length)) + 1;
        $perPage = max(1, $length);

        $conditions = [];
        if ($deptId > 0) {
            $conditions['department_id'] = $deptId;
        }
        if ($status !== '') {
            $conditions['status'] = $status;
        }
        if ($regStatus !== '') {
            $conditions['registration_status'] = $regStatus;
        }

        $searchColumns = ['first_name', 'last_name', 'employee_code', 'phone', 'badge_id', 'badge_code'];
        $orderBy = $this->getGet('order_by', 'first_name ASC');

        $result = $this->employeeModel->paginate($page, $perPage, $conditions, $searchColumns, $search, $orderBy);

        $data = [];
        foreach ($result['data'] as $e) {
            $photoHtml = Employee::photoTag($e['photo'] ?? null, 'emp-photo');

            $data[] = [
                'photo' => $photoHtml,
                'code' => htmlspecialchars($e['employee_code']),
                'name' => htmlspecialchars($e['first_name'] . ' ' . $e['last_name']),
                'department' => htmlspecialchars($e['department_name'] ?? '-'),
                'badge' => htmlspecialchars($e['badge_id'] ?? '-'),
                'registration' => $e['registration_status'] ?? '',
                'status' => $e['status'] ?? '',
                'id' => (int) $e['id'],
                'actions' => '<a href="index.php?controller=employees&action=edit&id=' . (int) $e['id'] . '" class="btn btn-sm btn-primary btn-icon" title="Modifier"><i class="fas fa-edit"></i></a>'
                    . ' <button class="btn btn-sm btn-danger btn-icon" title="Supprimer" data-delete-employee="' . (int) $e['id'] . '" data-bs-toggle="modal" data-bs-target="#deleteModal"><i class="fas fa-trash"></i></button>',
            ];
        }

        $this->json([
            'success' => true,
            'draw' => $draw,
            'recordsTotal' => $result['total'],
            'recordsFiltered' => $result['total'],
            'data' => $data,
        ]);
    }

    /**
     * AJAX: met Ã  jour un employÃ© et renvoie le HTML de la ligne mise Ã  jour.
     */
    public function ajaxUpdate(): void
    {
        $this->requirePermission('employees', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'MÃ©thode non autorisÃ©e.'], 405);
        }

        if (!Csrf::verify($this->getPost('csrf_token'))) {
            $this->json(['success' => false, 'message' => 'Jeton de sÃ©curitÃ© invalide.'], 419);
        }

        $id = Sanitize::int($this->getPost('id'));
        $employee = $this->employeeModel->find($id);
        if (!$employee) {
            $this->json(['success' => false, 'message' => 'EmployÃ© introuvable.'], 404);
        }

        $errors = $this->validate(['employee_code', 'first_name', 'last_name']);
        if ($errors === true) {
            $existing = $this->employeeModel->findByCode($this->getPost('employee_code'));
            if ($existing && (int) $existing->id !== $id) {
                $errors = ['employee_code' => 'Ce matricule existe dÃ©jÃ .'];
            }
        }

        if ($errors !== true) {
            $this->json(['success' => false, 'errors' => $errors], 422);
        }

        $badgeCode = $this->getPost('badge_code');
        if ($badgeCode !== '') {
            $existingBadge = $this->employeeModel->fetchOneRaw(
                'SELECT id FROM employees WHERE badge_code = :badge AND id != :id LIMIT 1',
                [':badge' => $badgeCode, ':id' => $id]
            );
            if ($existingBadge) {
                $this->json(['success' => false, 'errors' => ['badge_code' => 'Ce code badge existe dÃ©jÃ .']], 422);
            }
        }

        $old = $employee;
                    $data = [
            'first_name'    => $this->getPost('first_name'),
            'last_name'     => $this->getPost('last_name'),
            'phone'         => $this->getPost('phone'),
            'department_id' => Sanitize::int($this->getPost('department_id')),
            'hire_date'     => Sanitize::date($this->getPost('hire_date')),
            'badge_id'      => $this->getPost('badge_id'),
            'badge_code'    => $badgeCode,
            'status'        => $this->getPost('status') === 'inactive' ? 'inactive' : 'active',
            'registration_status' => $this->getPost('registration_status') ?: 'pending',
                    ];


                    if (!empty($_FILES['photo']['name'])) {
            $photo = $this->uploadPhoto();
            if ($photo === false) {
                $this->json(['success' => false, 'errors' => ['photo' => 'Ã‰chec du tÃ©lÃ©versement de la photo.']], 422);
            }
            $data['photo'] = $photo;
        }

        $this->employeeModel->update($id, $data);
        AuditLogger::log('update', 'employees', 'Modification employÃ© ' . $data['employee_code'], $id, $old, $data);

        $this->json([
            'success' => true,
            'message' => 'EmployÃ© mis Ã  jour avec succÃ¨s.',
            'csrf_token' => Csrf::token(),
        ]);
    }

    /**
     * AJAX: supprime un employÃ©.
     */
    public function ajaxDelete(): void
    {
        $this->requirePermission('employees', 'delete');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['success' => false, 'message' => 'MÃ©thode non autorisÃ©e.'], 405);
        }

        if (!Csrf::verify($this->getPost('csrf_token'))) {
            $this->json(['success' => false, 'message' => 'Jeton de sÃ©curitÃ© invalide.'], 419);
        }

        $id = Sanitize::int($this->getPost('id'));
        $employee = $this->employeeModel->find($id);

        if (!$employee) {
            $this->json(['success' => false, 'message' => 'EmployÃ© introuvable.'], 404);
        }

        try {
            $this->employeeModel->delete($id);
            AuditLogger::log('delete', 'employees', 'Suppression employÃ© ' . $employee['employee_code'], $id, $employee);
            $this->json([
                'success' => true,
                'message' => 'EmployÃ© supprimÃ©.',
                'csrf_token' => Csrf::token(),
            ]);
        } catch (\Throwable $e) {
            $this->json(['success' => false, 'message' => 'Erreur lors de la suppression : ' . $e->getMessage()], 500);
        }
    }

    /**
     * AJAX: recherche instantanÃ©e d'employÃ©s.
     */
    public function ajaxSearch(): void
    {
        $this->requirePermission('employees', 'view');

        $q = $this->getGet('q', '');
        if (mb_strlen($q) < 2) {
            $this->json(['success' => true, 'data' => []]);
        }

        $sql = 'SELECT id, employee_code, first_name, last_name, badge_id, department_id
                FROM employees
                WHERE status = "active"
                  AND (first_name LIKE :q OR last_name LIKE :q OR employee_code LIKE :q OR badge_id LIKE :q)
                ORDER BY first_name ASC
                LIMIT 20';
        $stmt = $this->employeeModel->db()->prepare($sql);
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

    public function login(): void
    {
        $username = $this->getGet('user');
        if ($username === null || $username === '') {
            $this->json(['success' => false, 'message' => 'Paramètre user requis.'], 400);
            return;
        }

        $user = (new User())->findByUsername($username);
        if (!$user) {
            $this->json(['success' => false, 'message' => 'Utilisateur introuvable.'], 401);
            return;
        }

        Session::regenerate();
        $perm = (new User())->loadPermissions($user['id']);
        Session::set('user_id', $user['id']);
        Session::set('user', $user);
        Session::set('user_role', $perm['role']);
        Session::set('permissions', $perm['permissions']);

        (new User())->update($user['id'], ['last_login' => date('Y-m-d H:i:s')]);
        AuditLogger::log('login', 'employees', 'Connexion réussie', $user['id']);

        $this->json(['success' => true, 'message' => 'Connexion réussie.', 'user' => ['id' => $user['id'], 'name' => ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')]]);
    }

    public function sync(): void
    {
        $this->requireAuth();

        $from = $this->getGet('from');
        $to = $this->getGet('to');

        $terminalService = new TerminalService(\App\Core\Database::connection());
        $result = $terminalService->syncAllTerminals();

        $this->json([
            'success' => $result['success'] ?? false,
            'message' => 'Synchronisation terminée',
            'results' => $result['results'] ?? [],
        ]);
    }

    private function uploadPhoto()
    {
        $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'employees' . DIRECTORY_SEPARATOR;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $file = $_FILES['photo'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $allowed, true) || $file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        $filename = uniqid('emp_', true) . '.' . $ext;
        if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            return 'uploads/employees/' . $filename;
        }
        return false;
    }
}












