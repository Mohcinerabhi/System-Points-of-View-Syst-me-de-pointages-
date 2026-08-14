<?php
namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Helpers\Paginator;
use App\Helpers\Sanitize;
use App\Helpers\Session;

/**
 * Contrôleur des journaux d'audit.
 */
class LogsController extends BaseController
{
    private AuditLog $logModel;
    private User $userModel;

    public function __construct()
    {
        parent::__construct();
        $this->logModel = new AuditLog();
        $this->userModel = new User();
    }

    public function index(): void
    {
        $this->requirePermission('logs', 'view');

        $page = Sanitize::int($this->getGet('page'), 1);
        $perPage = Sanitize::int($this->getGet('per_page'), 20);

        $viewFilters = [
            'user'   => trim((string) $this->getGet('user')),
            'module' => (string) $this->getGet('module'),
            'action' => (string) $this->getGet('action'),
            'from'   => Sanitize::date($this->getGet('from')),
            'to'     => Sanitize::date($this->getGet('to')),
        ];

        $queryFilters = [];
        if (!empty($viewFilters['user'])) {
            $userId = $this->userModel->db()->fetchOneRaw(
                "SELECT id FROM users WHERE username = ? OR first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ?",
                [$viewFilters['user'], "%{$viewFilters['user']}%", "%{$viewFilters['user']}%", "%{$viewFilters['user']}%"]
            );
            if ($userId) {
                $queryFilters['user_id'] = (int) $userId['id'];
            }
        }
        if ($viewFilters['module'] !== '') {
            $queryFilters['module'] = $viewFilters['module'];
        }
        if ($viewFilters['action'] !== '') {
            $queryFilters['action'] = $viewFilters['action'];
        }
        if ($viewFilters['from'] !== '') {
            $queryFilters['date_from'] = $viewFilters['from'];
        }
        if ($viewFilters['to'] !== '') {
            $queryFilters['date_to'] = $viewFilters['to'];
        }

        $result = $this->logModel->paginateLogs($page, $perPage, $queryFilters);
        $paginator = new Paginator($result['total'], $page, $perPage);

        $this->render('logs/index', [
            'pageTitle'  => 'Journaux d\'audit',
            'logs'       => $result['data'],
            'pagination' => $paginator->toArray(),
            'filters'    => $viewFilters,
            'users'      => $this->userModel->all('first_name ASC'),
            'modules'    => ['auth', 'employees', 'attendance', 'reports', 'schedules', 'departments', 'terminals', 'settings', 'logs'],
        ]);
    }

    /**
     * Journaux d'un utilisateur spécifique.
     */
    public function userLogs(): void
    {
        $this->requirePermission('logs', 'view');
        $userId = Sanitize::int($this->getGet('user_id'));
        $user = $this->userModel->find($userId);
        if (!$user) {
            Session::set('flash_error', 'Utilisateur introuvable.');
            $this->redirect($this->url('logs', 'index'));
        }

        $page = Sanitize::int($this->getGet('page'), 1);
        $result = $this->logModel->paginateLogs($page, 20, ['user_id' => $userId]);
        $paginator = new Paginator($result['total'], $page, 20);

        $this->render('logs/user', [
            'pageTitle'  => 'Journaux de ' . $user['first_name'] . ' ' . $user['last_name'],
            'user'       => $user,
            'logs'       => $result['data'],
            'pagination' => $paginator->toArray(),
        ]);
    }

    /**
     * Journaux d'un module spécifique.
     */
    public function moduleLogs(): void
    {
        $this->requirePermission('logs', 'view');
        $module = $this->getGet('module');
        if (!$module) {
            $this->redirect($this->url('logs', 'index'));
        }

        $page = Sanitize::int($this->getGet('page'), 1);
        $result = $this->logModel->paginateLogs($page, 20, ['module' => $module]);
        $paginator = new Paginator($result['total'], $page, 20);

        $this->render('logs/module', [
            'pageTitle'  => 'Journaux du module ' . ucfirst($module),
            'module'     => $module,
            'logs'       => $result['data'],
            'pagination' => $paginator->toArray(),
        ]);
    }
}
