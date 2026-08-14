<?php
namespace App\Controllers;

use App\Helpers\Session;
use App\Helpers\Csrf;
use App\Helpers\Auth;
use App\Helpers\Sanitize;
use App\Helpers\Logger;

/**
 * Contrôleur de base commun à tous les contrôleurs.
 * Gère le rendu des vues, les réponses JSON, les redirections,
 * la validation (CSRF incluse) et la lecture des entrées.
 */
class BaseController
{
    protected string $viewPath = 'views/';
    protected string $layout = 'partials/layout';
    protected string $viewsDir;

    public function __construct()
    {
        Session::start();
        $this->viewsDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . $this->viewPath;
    }

    /**
     * Rend une vue, en incluant le layout par défaut si $layout est true.
     */
    protected function render(string $view, array $data = [], bool $layout = true): void
    {
        $viewFile = $this->viewsDir . $view . '.php';

        if (!is_readable($viewFile)) {
            Logger::error("View not found: {$viewFile}");
            throw new \RuntimeException("Vue introuvable : {$view}");
        }

        try {
            $data['csrf_token'] = Csrf::token();
            $data['auth_user']  = Auth::user();
            $data['auth_name']  = Auth::name();
            $data['auth_role']  = Auth::role();
            $data['flash_success'] = Session::flash('success');
            $data['flash_error']   = Session::flash('error');
            $data['flash_info']    = Session::flash('info');

            extract($data, EXTR_SKIP);

            $activeMenu = $data['activeMenu'] ?? '';
            if ($activeMenu === '') {
                $activeMenu = $this->detectActiveMenu($view);
            }

            ob_start();
            include $viewFile;
            $content = ob_get_clean();

            if ($layout) {
                $layoutFile = $this->viewsDir . $this->layout . '.php';
                if (is_readable($layoutFile)) {
                    $pageTitle = $data['pageTitle'] ?? 'Système de Pointage';
                    ob_start();
                    include $layoutFile;
                    echo ob_get_clean();
                    return;
                }
            }

            echo $content;
        } catch (\Throwable $e) {
            Logger::exception($e, "Failed to render view: {$view}");
            throw $e;
        }
    }

    private function detectActiveMenu(string $view): string
    {
        $view = strtolower($view);
        return match (true) {
            str_contains($view, 'hr_dashboard') => 'hr_dashboard',
            str_contains($view, 'dashboard') => 'dashboard',
            str_contains($view, 'employee') && !str_contains($view, 'attendance') => 'employees',
            str_contains($view, 'attendance') || str_contains($view, 'calendar') => 'attendance',
            str_contains($view, 'leave') => 'leaves',
            str_contains($view, 'overtime') => 'overtime',
            str_contains($view, 'shift') => 'shifts',
            str_contains($view, 'remote_work') => 'remote_work',
            str_contains($view, 'report') => 'reports',
            str_contains($view, 'schedule') => 'schedules',
            str_contains($view, 'terminal') => 'terminals',
            str_contains($view, 'setting') => 'settings',
            str_contains($view, 'log') => 'logs',
            str_contains($view, 'department') => 'departments',
            default => '',
        };
    }

    /**
     * Renvoie une réponse JSON et termine le script.
     */
    protected function json($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Redirige vers une URL et termine le script.
     */
    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    /**
     * Construit une URL vers un contrôleur/action.
     */
    protected function url(string $controller, string $action = 'index', array $params = []): string
    {
        $url = "index.php?controller={$controller}&action={$action}";
        foreach ($params as $k => $v) {
            $url .= '&' . urlencode($k) . '=' . urlencode($v);
        }
        return $url;
    }

    /**
     * Valide les champs requis du POST et vérifie le jeton CSRF.
     * Retourne true si valide, sinon un tableau d'erreurs.
     */
    protected function validate(array $required): array|bool
    {
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $this->getPost('csrf_token');
            if (!Csrf::verify($token)) {
                $errors['csrf_token'] = 'Jeton de sécurité invalide ou expiré.';
            }
        }

        foreach ($required as $field) {
            $value = $this->getPost($field);
            if ($value === null || (is_string($value) && trim($value) === '')) {
                $errors[$field] = 'Le champ « ' . ucfirst(str_replace('_', ' ', $field)) . ' » est requis.';
            }
        }

        return empty($errors) ? true : $errors;
    }

    protected function getPost(string $key, $default = null)
    {
        if (!isset($_POST[$key])) {
            return $default;
        }
        return $this->sanitizeValue($_POST[$key]);
    }

    protected function getGet(string $key, $default = null)
    {
        if (!isset($_GET[$key])) {
            return $default;
        }
        return $this->sanitizeValue($_GET[$key]);
    }

    protected function sanitizeValue($value)
    {
        if (is_array($value)) {
            return Sanitize::array($value);
        }
        return Sanitize::string($value);
    }

    /**
     * Exige une authentification, sinon redirection vers la connexion.
     */
    protected function requireAuth(): void
    {
        Auth::requireAuth($this->url('auth', 'login'));
    }

    /**
     * Exige une permission, sinon redirection vers le tableau de bord.
     */
    protected function requirePermission(string $module, string $action = 'view'): void
    {
        Auth::requirePermission($module, $action);
    }

    /**
     * Refresh dashboard stats after mutations.
     * Sets a session flag so the layout can trigger a stats refresh.
     */
    protected function refreshDashboardStats(): void
    {
        Session::set('refresh_dashboard', '1');
    }
}
