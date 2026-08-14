<?php
namespace App\Controllers;

use App\Models\Setting;
use App\Helpers\Sanitize;
use App\Helpers\AuditLogger;
use App\Helpers\Session;
use App\Helpers\Csrf;

/**
 * Contrôleur des paramètres généraux.
 */
class SettingsController extends BaseController
{
    private Setting $settingModel;

    public function __construct()
    {
        parent::__construct();
        $this->settingModel = new Setting();
    }

    public function index(): void
    {
        $this->requirePermission('settings', 'view');
        $this->render('settings/index', [
            'pageTitle' => 'Paramètres',
            'settings'  => $this->settingModel->allAsKeyValue(),
        ]);
    }

    public function update(): void
    {
        $this->requirePermission('settings', 'edit');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::verify($this->getPost('csrf_token'))) {
                Session::set('flash_error', 'Jeton de sécurité invalide.');
                $this->redirect($this->url('settings', 'index'));
            }

            $editable = [
                'company_name', 'company_address', 'company_phone', 'company_email',
                'timezone', 'date_format', 'time_format', 'attendance_start_time',
                'attendance_end_time', 'default_late_tolerance', 'default_early_tolerance',
                'default_work_hours', 'session_timeout', 'items_per_page', 'language',
                'email_host', 'email_port', 'email_username', 'email_password', 'email_from',
            ];

            $values = [];
            foreach ($editable as $key) {
                if (isset($_POST[$key])) {
                    $values[$key] = $this->getPost($key);
                }
            }

            $this->settingModel->updateMany($values);
            AuditLogger::log('update', 'settings', 'Paramètres mis à jour', null, null, $values);
            Session::set('flash_success', 'Paramètres enregistrés.');

            if (isset($values['language'])) {
                Session::set('app_language', $values['language']);
                \App\Helpers\Language::set($values['language']);
            }

            $this->redirect($this->url('settings', 'index'));
        }

        $this->redirect($this->url('settings', 'index'));
    }

    /**
     * Teste la configuration e-mail (simulation).
     */
    public function testEmail(): void
    {
        $this->requirePermission('settings', 'edit');

        $host = $this->settingModel->findBy('key', 'email_host');
        $username = $this->settingModel->findBy('key', 'email_username');
        $to = $this->getGet('email') ?: ($this->settingModel->findBy('key', 'company_email')['value'] ?? '');

        if (empty($to)) {
            $this->json(['success' => false, 'message' => 'Aucune adresse de destination fournie.'], 400);
        }

        // Scaffold : vérifie la connectivité SMTP sans envoyer réellement.
        $result = ['success' => false, 'message' => 'Non configuré'];
        if ($host && !empty($host['value']) && $username) {
            $conn = @fsockopen($host['value'], (int) ($this->settingModel->findBy('key', 'email_port')['value'] ?? 25), $errno, $errstr, 5);
            if ($conn) {
                fclose($conn);
                $result = ['success' => true, 'message' => 'Connexion SMTP réussie vers ' . $host['value']];
            } else {
                $result = ['success' => false, 'message' => 'Échec de connexion SMTP : ' . $errstr];
            }
        }

        AuditLogger::log('test_email', 'settings', $result['message']);
        $this->json($result, $result['success'] ? 200 : 400);
    }
}
