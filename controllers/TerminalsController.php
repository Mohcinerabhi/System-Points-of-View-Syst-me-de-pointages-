<?php
namespace App\Controllers;

use App\Models\Terminal;
use App\Models\TerminalSyncLog;
use App\Models\Employee;
use App\Services\HikvisionTerminal;
use Attendance\Services\AttendanceSync;
use App\Helpers\Sanitize;
use App\Helpers\AuditLogger;
use App\Helpers\Session;

/**
 * Contrôleur des terminaux biométriques Hikvision.
 */
class TerminalsController extends BaseController
{
    private Terminal $terminalModel;
    private TerminalSyncLog $syncLogModel;

    public function __construct()
    {
        parent::__construct();
        $this->terminalModel = new Terminal();
        $this->syncLogModel = new TerminalSyncLog();
    }

    public function index(): void
    {
        $this->requirePermission('terminals', 'view');
        $this->render('terminals/index', [
            'pageTitle' => 'Terminaux',
            'terminals' => $this->terminalModel->all('name ASC'),
        ]);
    }

    public function create(): void
    {
        $this->requirePermission('terminals', 'create');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $errors = $this->validate(['name', 'ip_address', 'username', 'password']);
            if ($errors === true) {
                $data = [
                    'name'            => $this->getPost('name'),
                    'ip_address'      => $this->getPost('ip_address'),
                    'port'            => Sanitize::int($this->getPost('port'), 80),
                    'username'        => $this->getPost('username'),
                    'password_hash'   => \App\Models\Terminal::encryptPassword($_POST['password'] ?? ''),
                    'serial_number'   => $this->getPost('serial_number'),
                    'model'           => $this->getPost('model') ?: null,
                    'mac_address'     => $this->getPost('mac_address') ?: null,
                    'protocol'        => $this->getPost('protocol') ?: null,
                    'network_info'    => $this->getPost('network_info') ? json_encode(['raw' => $this->getPost('network_info')]) : null,
                    'rtsp_port'       => Sanitize::int($this->getPost('rtsp_port'), 554),
                    'isup_server_ip'  => $this->getPost('isup_server_ip') ?: null,
                    'isup_server_port' => Sanitize::int($this->getPost('isup_server_port'), 8000),
                    'isup_device_id'  => $this->getPost('isup_device_id') ?: null,
                    'sync_enabled'    => $this->getPost('sync_enabled') ? 1 : 0,
                    'status'          => $this->getPost('status') === 'inactive' ? 'inactive' : 'active',
                    'device_time'     => $this->parseDeviceTime($this->getPost('device_time')),
                    'timezone'        => $this->getPost('timezone') ?: null,
                    'notes'           => $this->getPost('notes'),
                ];
                try {
                    $id = $this->terminalModel->create($data);
                } catch (\Throwable $e) {
                    \App\Helpers\Logger::error('Terminal create failed', [
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'data_keys' => array_keys($data),
                    ]);
                    throw $e;
                }
                AuditLogger::log('create', 'terminals', 'Terminal créé', $id, null, $data);
                Session::set('flash_success', 'Terminal créé.');
                $this->redirect($this->url('terminals', 'index'));
            }
            $this->render('terminals/create', [
                'pageTitle' => 'Nouveau terminal',
                'errors'    => is_array($errors) ? $errors : [],
                'terminal'  => $_POST,
            ]);
            return;
        }

        $this->render('terminals/create', ['pageTitle' => 'Nouveau terminal']);
    }

    public function edit(): void
    {
        $this->requirePermission('terminals', 'edit');
        $id = Sanitize::int($this->getGet('id'));
        $terminal = $this->terminalModel->find($id);
        if (!$terminal) {
            Session::set('flash_error', 'Terminal introuvable.');
            $this->redirect($this->url('terminals', 'index'));
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $errors = $this->validate(['name', 'ip_address', 'username']);
            if ($errors === true) {
                $old = $terminal;
                $data = [
                    'name'          => $this->getPost('name'),
                    'ip_address'    => $this->getPost('ip_address'),
                    'port'          => Sanitize::int($this->getPost('port'), 80),
                    'username'      => $this->getPost('username'),
                    'serial_number' => $this->getPost('serial_number'),
                    'model'         => $this->getPost('model') ?: null,
                    'mac_address'   => $this->getPost('mac_address') ?: null,
                    'protocol'      => $this->getPost('protocol') ?: null,
                    'network_info'  => $this->getPost('network_info') ? json_encode(['raw' => $this->getPost('network_info')]) : null,
                    'rtsp_port'     => Sanitize::int($this->getPost('rtsp_port'), 554),
                    'isup_server_ip' => $this->getPost('isup_server_ip') ?: null,
                    'isup_server_port' => Sanitize::int($this->getPost('isup_server_port'), 8000),
                    'isup_device_id' => $this->getPost('isup_device_id') ?: null,
                    'sync_enabled'  => $this->getPost('sync_enabled') ? 1 : 0,
                    'status'        => $this->getPost('status') === 'inactive' ? 'inactive' : 'active',
                    'device_time'   => $this->parseDeviceTime($this->getPost('device_time')),
                    'timezone'      => $this->getPost('timezone') ?: null,
                    'notes'         => $this->getPost('notes'),
                ];
                if (!empty($_POST['password'])) {
                    $data['password_hash'] = \App\Models\Terminal::encryptPassword($_POST['password']);
                }
                try {
                    $this->terminalModel->update($id, $data);
                } catch (\Throwable $e) {
                    \App\Helpers\Logger::error('Terminal edit failed', [
                        'error' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'data_keys' => array_keys($data),
                    ]);
                    throw $e;
                }
                AuditLogger::log('update', 'terminals', 'Terminal modifié', $id, $old, $data);
                Session::set('flash_success', 'Terminal mis à jour.');
                $this->redirect($this->url('terminals', 'index'));
            }
        }

        $this->render('terminals/edit', [
            'pageTitle' => 'Modifier terminal',
            'terminal'  => $terminal,
        ]);
    }

    public function delete(): void
    {
        $this->requirePermission('terminals', 'delete');
        $id = Sanitize::int($this->getGet('id'));
        if ($this->terminalModel->find($id)) {
            $this->terminalModel->delete($id);
            AuditLogger::log('delete', 'terminals', 'Terminal supprimé', $id);
            Session::set('flash_success', 'Terminal supprimé.');
        } else {
            Session::set('flash_error', 'Terminal introuvable.');
        }
        $this->redirect($this->url('terminals', 'index'));
    }

    private function isConnectionError(array $result): bool
    {
        $httpCode = (int) ($result['http_code'] ?? 0);
        $error = (string) ($result['error'] ?? '');

        if ($httpCode === 0) {
            return true;
        }

        $connectionKeywords = [
            'cURL error',
            'timed out',
            'Empty reply',
            'Connection refused',
            'Could not resolve host',
            'Failed to connect',
            'Network is unreachable',
            'Operation timed out',
            'No ISAPI client',
        ];

        foreach ($connectionKeywords as $keyword) {
            if (str_contains($error, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Find the best matching employee for a terminal attendance record.
     */
    private function findEmployeeForRecord(Employee $empModel, array $record): ?array
    {
        $employeeNo = (string) ($record['employeeNo'] ?? '');
        $cardNo = (string) ($record['cardNo'] ?? '');

        if ($employeeNo !== '' && $employeeNo !== '0') {
            $byCode = $empModel->findByCode($employeeNo);
            if ($byCode) {
                return $byCode;
            }
        }

        if ($cardNo !== '' && $cardNo !== '0') {
            $byBadge = $empModel->where('badge_code = :badge', [':badge' => $cardNo]);
            if (!empty($byBadge[0])) {
                return $byBadge[0];
            }
        }

        return null;
    }

    /**
     * Teste la connexion ISUP du terminal.
     */
    public function testConnection(): void
    {
        $this->requirePermission('terminals', 'view');
        $id = Sanitize::int($this->getGet('id'));
        $terminal = $this->terminalModel->find($id);
        if (!$terminal) {
            $this->json(['success' => false, 'message' => 'Terminal introuvable.'], 404);
        }

        $password = \App\Models\Terminal::decryptPassword((string) ($terminal['password_hash'] ?? ''));
        $client = new HikvisionTerminal($terminal + ['plain_password' => $password]);
        $result = $client->testConnection();
        
        $status = $result['authenticated'] ? 'online' : ($result['reachable'] ? 'error' : 'offline');
        $this->terminalModel->setConnectionStatus($id, $status);

        $message = $result['error'] ?? ($result['authenticated'] ? 'Connexion réussie' : 'Connexion échouée');
        $client->logSync($id, 'users', ['success' => $result['success'] ?? $result['authenticated'], 'message' => $message]);
        \App\Helpers\Logger::info("Terminal test connection", [
            'terminal_id' => $id,
            'terminal_name' => $terminal['name'] ?? '',
            'status' => $status,
            'http_code' => $result['http_code'] ?? 0,
            'error' => $result['error'] ?? null,
            'authenticated' => $result['authenticated'] ?? false,
            'reachable' => $result['reachable'] ?? false,
        ]);
        AuditLogger::log('test_connection', 'terminals', $message, $id);

        $this->json($result);
    }

    /**
     * Importe les informations du terminal depuis l'appareil via HTTP.
     */
    public function importDeviceInfo(): void
    {
        $this->requirePermission('terminals', 'edit');
        $id = Sanitize::int($this->getGet('id'));
        $terminal = $this->terminalModel->find($id);
        if (!$terminal) {
            $this->json(['success' => false, 'message' => 'Terminal introuvable.'], 404);
        }

        $password = \App\Models\Terminal::decryptPassword((string) ($terminal['password_hash'] ?? ''));
        $hikvision = new HikvisionTerminal($terminal + ['plain_password' => $password]);
        $result = $hikvision->getDeviceInfo();

        if ($result['success'] && !empty($result['data'])) {
            $updated = false;
            $updates = [];

            if (!empty($result['data']['serialNumber'])) {
                $updates['serial_number'] = $result['data']['serialNumber'];
            }
            if (!empty($result['data']['macAddress'])) {
                $updates['mac_address'] = $result['data']['macAddress'];
            }
            if (!empty($result['data']['model'])) {
                $updates['model'] = $result['data']['model'];
            }
            if (!empty($result['data']['deviceName'])) {
                $updates['name'] = $result['data']['deviceName'];
            }

            if (!empty($updates)) {
                $this->terminalModel->update($id, $updates);
                $updated = true;
            }

            AuditLogger::log('import_device_info', 'terminals', 'Infos importées depuis le terminal', $id);

            $this->json([
                'success' => true,
                'message' => 'Informations importées avec succès.',
                'data' => $result['data'],
                'updated' => $updated,
            ]);
        } else {
            $this->json([
                'success' => false,
                'message' => $result['error'] ?? 'Échec de l\'importation.',
            ], 500);
        }
    }

    /**
     * Synchronise les utilisateurs depuis le terminal vers la base.
     */
    public function syncUsers(): void
    {
        $this->requirePermission('terminals', 'edit');
        $id = Sanitize::int($this->getGet('id'));
        $terminal = $this->terminalModel->find($id);
        if (!$terminal) {
            $this->json(['success' => false, 'message' => 'Terminal introuvable.'], 404);
        }

        $password = \App\Models\Terminal::decryptPassword((string) ($terminal['password_hash'] ?? ''));
        $client = new HikvisionTerminal($terminal + ['plain_password' => $password]);

        $attendanceSync = new AttendanceSync(
            \App\Core\Database::connection(),
            $client->getIsapiClient(),
            null
        );

        $result = $attendanceSync->importUsersFromTerminal($id);

        $imported = (int) ($result['imported'] ?? 0);
        $updated = (int) ($result['updated'] ?? 0);
        $skipped = (int) ($result['skipped'] ?? 0);
        $errors = (int) ($result['errors'] ?? 0);
        $error = (string) ($result['message'] ?? '');

        $message = 'Utilisateurs synchronisés : ' . $imported . ' ajoutés, ' . $updated . ' mis à jour, ' . $skipped . ' ignorés, ' . $errors . ' erreurs';
        if (!$result['success'] && !empty($error)) {
            $message = $error;
        } elseif ($result['success'] && $imported === 0 && $updated === 0) {
            $message = 'Aucun utilisateur trouvé sur le terminal ou déjà synchronisé.';
        }

        $client->logSync($id, 'users', [
            'success' => $result['success'] ?? false,
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
            'message' => $message,
            'http_code' => $result['http_code'] ?? 0,
            'endpoint' => $result['endpoint'] ?? null,
        ]);

        \App\Helpers\Logger::info("Terminal sync users (import)", [
            'terminal_id' => $id,
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
            'success' => $result['success'] ?? false,
            'error' => $error,
            'endpoint' => $result['endpoint'] ?? null,
            'http_code' => $result['http_code'] ?? 0,
        ]);

        if ($result['success']) {
            $this->terminalModel->setConnectionStatus($id, 'online');
        } elseif ($this->isConnectionError($result)) {
            $this->terminalModel->setConnectionStatus($id, 'error');
        }

        $this->json([
            'success' => $result['success'] ?? false,
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $errors,
            'synced' => $imported + $updated,
            'message' => $message,
            'error' => $error ?: null,
            'endpoint' => $result['endpoint'] ?? null,
            'http_code' => $result['http_code'] ?? 0,
        ]);
    }

    /**
     * Télécharge les pointages depuis le terminal.
     */
    public function syncAttendance(): void
    {
        $this->requirePermission('terminals', 'edit');
        $id = Sanitize::int($this->getGet('id'));
        $terminal = $this->terminalModel->find($id);
        if (!$terminal) {
            $this->json(['success' => false, 'message' => 'Terminal introuvable.'], 404);
            return;
        }

        $password = \App\Models\Terminal::decryptPassword((string) ($terminal['password_hash'] ?? ''));
        $client = new HikvisionTerminal($terminal + ['plain_password' => $password]);

        $attendanceSync = new AttendanceSync(
            \App\Core\Database::connection(),
            $client->getIsapiClient(),
            null
        );

        $result = $attendanceSync->syncFromTerminal($id, $terminal['timezone'] ?? null);

        $receivedCount = (int) ($result['records_received'] ?? 0);
        $synced = (int) ($result['records_synced'] ?? 0);
        $failed = (int) ($result['records_failed'] ?? 0);
        $error = (string) ($result['error'] ?? $result['message'] ?? '');
        $clientSuccess = (bool) ($result['success'] ?? false);

        $message = 'Pointages synchronisés';
        if (!$clientSuccess && !empty($error)) {
            $message = $error;
        } elseif ($receivedCount === 0 && $clientSuccess) {
            $message = 'Aucun pointage reçu du terminal pour la période';
        } elseif ($receivedCount > 0 && $synced === 0 && $failed > 0) {
            $message = 'Tous les pointages ont échoué lors de l\'insertion en base';
        } elseif ($receivedCount > 0 && $synced > 0 && $failed > 0) {
            $message = $synced . ' pointage(s) synchronisé(s), ' . $failed . ' échoué(s) sur ' . $receivedCount . ' reçus';
        }

        \App\Helpers\Logger::info("Terminal sync attendance completed", [
            'terminal_id' => $id,
            'terminal_name' => $terminal['name'] ?? '',
            'records_synced' => $synced,
            'records_failed' => $failed,
            'success' => $clientSuccess,
            'error' => $error,
            'endpoint' => $result['endpoint'] ?? null,
            'http_code' => $result['http_code'] ?? 0,
            'raw_hikvision_response' => substr((string) ($result['raw_response'] ?? ''), 0, 2000),
        ]);

        $client->logSync($id, 'attendance', [
            'success' => $clientSuccess,
            'records_synced' => $synced,
            'records_failed' => $failed,
            'error' => $error,
            'endpoint' => $result['endpoint'] ?? null,
            'http_code' => $result['http_code'] ?? 0,
            'raw_hikvision_response' => substr((string) ($result['raw_response'] ?? ''), 0, 2000),
        ]);
        $this->terminalModel->markSync($id);

        if ($clientSuccess) {
            $this->terminalModel->setConnectionStatus($id, 'online');
        } elseif ($this->isConnectionError($result)) {
            $this->terminalModel->setConnectionStatus($id, 'error');
        }

        $this->json([
            'success' => $clientSuccess,
            'synced' => $synced,
            'failed' => $failed,
            'message' => $message,
            'error' => $error ?: null,
            'endpoint' => $result['endpoint'] ?? null,
            'http_code' => $result['http_code'] ?? 0,
            'raw_hikvision_response' => substr((string) ($result['raw_response'] ?? ''), 0, 2000),
        ]);
    }

    /**
     * Importe les photos de profil des employés depuis le terminal.
     */
    public function importPhotos(): void
    {
        $this->requirePermission('terminals', 'edit');
        $id = Sanitize::int($this->getGet('id'));
        $terminal = $this->terminalModel->find($id);
        if (!$terminal) {
            $this->json(['success' => false, 'message' => 'Terminal introuvable.'], 404);
            return;
        }

        $password = \App\Models\Terminal::decryptPassword((string) ($terminal['password_hash'] ?? ''));
        $client = new HikvisionTerminal($terminal + ['plain_password' => $password]);

        $attendanceSync = new AttendanceSync(
            \App\Core\Database::connection(),
            $client->getIsapiClient(),
            null
        );

        $result = $attendanceSync->importEmployeePhotos($id);

        $imported = (int) ($result['imported'] ?? 0);
        $updated = (int) ($result['updated'] ?? 0);
        $skipped = (int) ($result['skipped'] ?? 0);
        $failed = (int) ($result['failed'] ?? 0);
        $error = (string) ($result['error'] ?? $result['message'] ?? '');
        $clientSuccess = (bool) ($result['success'] ?? false);

        $message = (string) ($result['message'] ?? '');
        if (!$clientSuccess && $error !== '') {
            $message = $error;
        }

        \App\Helpers\Logger::info('Terminal import photos completed', [
            'terminal_id' => $id,
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $failed,
            'success' => $clientSuccess,
            'error' => $error ?: null,
            'endpoint' => $result['endpoint'] ?? null,
            'http_code' => $result['http_code'] ?? 0,
        ]);

        $client->logSync($id, 'photos', [
            'success' => $clientSuccess,
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $failed,
            'error' => $error ?: null,
            'endpoint' => $result['endpoint'] ?? null,
            'http_code' => $result['http_code'] ?? 0,
        ]);

        if ($clientSuccess) {
            $this->terminalModel->setConnectionStatus($id, 'online');
        } elseif ($this->isConnectionError($result)) {
            $this->terminalModel->setConnectionStatus($id, 'error');
        }

        $this->terminalModel->markSync($id);

        $this->json([
            'success' => $clientSuccess,
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $failed,
            'synced' => $imported + $updated,
            'total' => (int) ($result['total'] ?? 0),
            'message' => $message,
            'error' => $error ?: null,
            'endpoint' => $result['endpoint'] ?? null,
            'http_code' => $result['http_code'] ?? 0,
            'results' => array_values(array_slice($result['results'] ?? [], 0, 100)),
        ]);
    }

    /**
     * Importe les utilisateurs depuis le terminal vers la base.
     */
    public function importUsers(): void
    {
        $this->requirePermission('terminals', 'edit');
        $id = Sanitize::int($this->getGet('id'));
        $terminal = $this->terminalModel->find($id);
        if (!$terminal) {
            $this->json(['success' => false, 'message' => 'Terminal introuvable.'], 404);
        }

        $password = \App\Models\Terminal::decryptPassword((string) ($terminal['password_hash'] ?? ''));
        $client = new HikvisionTerminal($terminal + ['plain_password' => $password]);
        $result = $client->fetchUsers();
        $imported = 0;
        if ($result['success']) {
            $empModel = new Employee();
            foreach ($result['users'] as $u) {
                $code = $u['cardNo'] ?? ($u['employeeNo'] ?? '');
                if ($code === '' || $code === '0') {
                    continue;
                }
                if (!$empModel->findByCode($code)) {
                    $empModel->create([
                        'employee_code' => $code,
                        'first_name'    => $u['name'] ?? 'Unknown',
                        'last_name'     => '',
                        'badge_code'    => $u['cardNo'] ?? null,
                        'status'        => 'active',
                    ]);
                    $imported++;
                }
            }
        }
        $log = ['success' => $result['success'], 'synced' => $imported, 'message' => 'Utilisateurs importés.'];
        $client->logSync($id, 'users', $log);
        \App\Helpers\Logger::info("Terminal import users", [
            'terminal_id' => $id,
            'imported' => $imported,
            'success' => $result['success'] ?? false,
            'error' => $result['error'] ?? null,
        ]);
        AuditLogger::log('import_users', 'terminals', $log['message'], $id);

        if (!empty($result['success'])) {
            $this->terminalModel->setConnectionStatus($id, 'online');
        } elseif ($this->isConnectionError($result)) {
            $this->terminalModel->setConnectionStatus($id, 'error');
        }

        $this->json($log);
    }

    /**
     * Exporte les utilisateurs de la base vers le terminal.
     */
    public function exportUsers(): void
    {
        // Réutilise la logique de synchronisation des utilisateurs.
        $this->syncUsers();
    }

    /**
     * Retourne le statut de connexion d'un terminal en JSON.
     */
    public function status(): void
    {
        $this->requirePermission('terminals', 'view');
        $id = Sanitize::int($this->getGet('id'));
        $terminal = $this->terminalModel->find($id);

        if (!$terminal) {
            $this->json(['success' => false, 'message' => 'Terminal introuvable.'], 404);
            return;
        }

        $this->json([
            'success' => true,
            'status' => $terminal['connection_status'] ?? 'offline',
            'last_test' => $terminal['last_connection_test'] ?? null,
        ]);
    }

    /**
     * Affiche les journaux de synchronisation des terminaux.
     */
    public function logs(): void
    {
        $this->requirePermission('terminals', 'view');
        $id = Sanitize::int($this->getGet('id'));
        if ($id) {
            $logs = $this->syncLogModel->getByTerminal($id, 100);
            $terminal = $this->terminalModel->find($id);
        } else {
            $logs = $this->syncLogModel->all('started_at DESC', 100);
            $terminal = null;
        }

        $this->render('terminals/logs', [
            'pageTitle' => 'Journaux de synchronisation',
            'logs'      => $logs,
            'terminal'  => $terminal,
            'terminals' => $this->terminalModel->all('name ASC'),
        ]);
    }

    /**
     * Détail d'un terminal.
     */
    public function view(): void
    {
        $this->requirePermission('terminals', 'view');
        $id = Sanitize::int($this->getGet('id'));
        $terminal = $this->terminalModel->find($id);

        if (!$terminal) {
            Session::set('flash_error', 'Terminal introuvable.');
            $this->redirect($this->url('terminals', 'index'));
        }

        $this->render('terminals/view', [
            'pageTitle' => 'Détail du terminal',
            'terminal'  => $terminal,
        ]);
    }

    private function parseDeviceTime(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $dt = \DateTime::createFromFormat('d-m-Y\TH:i:sP', $value);
        if ($dt !== false) {
            return $dt->format('Y-m-d H:i:s');
        }
        return $value;
    }
}
