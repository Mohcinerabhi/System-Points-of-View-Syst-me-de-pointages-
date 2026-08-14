<?php

declare(strict_types=1);

namespace Attendance\Services;

use App\Helpers\Logger;

class AttendanceSync
{
    private \PDO $db;
    private ?ISAPIClient $isapiClient = null;
    private ?ISUPClient $isupClient = null;

    private const ATTENDANCE_TABLE = 'attendance_logs';
    private const EMPLOYEE_TABLE = 'employees';
    private const TERMINAL_TABLE = 'terminals';
    private const SYNC_LOG_TABLE = 'terminal_sync_logs';
    private const DEBUG_LOG = 'sync_debug.log';

    public function __construct(\PDO $db, ?ISAPIClient $isapiClient = null, ?ISUPClient $isupClient = null)
    {
        $this->db = $db;
        $this->isapiClient = $isapiClient;
        $this->isupClient = $isupClient;
    }

        public function syncFromTerminal(int $terminalId, ?string $timezone = null): array
        {
            set_time_limit(0);
            $stepStart = microtime(true);
            $this->log('syncFromTerminal START', [
                'terminalId' => $terminalId,
            ]);

            $terminal = $this->getTerminal($terminalId);
            if (!$terminal) {
                $this->log('syncFromTerminal FAIL', [
                    'terminalId' => $terminalId,
                    'reason' => 'Terminal not found',
                    'elapsed_ms' => round((microtime(true) - $stepStart) * 1000, 2),
                ]);
                return ['success' => false, 'message' => 'Terminal not found'];
            }

            $tz = $timezone ?? $terminal['timezone'] ?? '+00:00';
            $timezoneObj = new \DateTimeZone($tz);

            $logId = $this->createSyncLog($terminalId, 'attendance', 'pending', 'Starting attendance sync', 0);
            $startTime = microtime(true);

             try {
                  $lastSync = $this->getLastSync($terminalId);
                  $startDate = null;
                  if ($lastSync !== null) {
                      $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $lastSync);
                      if ($dt !== false) {
                          $startDate = $dt->format('Y-m-d');
                      }
                  }
                  $this->log('syncFromTerminal FETCH_START', [
                      'terminalId' => $terminalId,
                      'terminalIp' => $terminal['ip_address'] ?? '',
                      'dateRange' => $startDate !== null ? $startDate . ' to present' : 'unrestricted (all records)',
                      'lastSync' => $lastSync ?? null,
                      'timezone' => $tz,
                  ]);

                  $records = $this->fetchAttendanceFromTerminal($terminal, $startDate, null);

                 $this->log('syncFromTerminal FETCH_END', [
                     'terminalId' => $terminalId,
                     'success' => $records['success'] ?? false,
                     'received_count' => count($records['data'] ?? []),
                     'error' => $records['error'] ?? null,
                     'endpoint' => $records['endpoint'] ?? null,
                     'http_code' => $records['http_code'] ?? 0,
                     'raw_response' => substr((string)($records['raw_response'] ?? ''), 0, 3000),
                     'elapsed_ms' => round((microtime(true) - $startTime) * 1000, 2),
                 ]);

                 if (!$records['success']) {
                     $error = (string) ($records['error'] ?? 'Unknown error');
                     $this->finishSyncLog($logId, 'failed', 0, 0, $error);
                     $isConnectionError = $this->isConnectionError($records);
                     $this->updateTerminalSync($terminalId, $isConnectionError);

                     return [
                         'success' => false,
                         'message' => $error,
                         'error' => $error,
                         'endpoint' => $records['endpoint'] ?? null,
                         'http_code' => $records['http_code'] ?? 0,
                         'raw_response' => substr((string) ($records['raw_response'] ?? ''), 0, 2000),
                     ];
                 }

             $receivedCount = count($records['data']);

             if ($receivedCount === 0) {
                 $this->finishSyncLog($logId, 'success', 0, 0, 'No records received from terminal');
                 $this->updateTerminalSync($terminalId);
                 $this->log('syncFromTerminal NO_RECORDS', [
                     'terminalId' => $terminalId,
                     'elapsed_ms' => round((microtime(true) - $stepStart) * 1000, 2),
                 ]);
                 return ['success' => true, 'records_synced' => 0, 'message' => 'No records received from terminal'];
             }

            $processed = $this->processAttendanceRecords($records['data'], $terminalId);
            $recordsCount = count($processed);
            $failedCount = count($records['data']) - $recordsCount;

            $this->finishSyncLog($logId, $failedCount > 0 ? 'partial' : 'success', $recordsCount, $failedCount);
            $this->updateTerminalSync($terminalId);

            $this->log('syncFromTerminal SUCCESS', [
                'terminalId' => $terminalId,
                'records_received' => $receivedCount,
                'records_synced' => $recordsCount,
                'records_failed' => $failedCount,
                'elapsed_ms' => round((microtime(true) - $stepStart) * 1000, 2),
            ]);

            return [
                'success' => true,
                'records_synced' => $recordsCount,
                'records_failed' => $failedCount,
                'records_received' => $receivedCount,
            ];
        } catch (\Throwable $e) {
            $this->logException('syncFromTerminal EXCEPTION', $e, [
                'terminalId' => $terminalId,
                'terminalIp' => $terminal['ip_address'] ?? '',
                'elapsed_ms' => round((microtime(true) - $stepStart) * 1000, 2),
            ]);
            Logger::exception($e, 'syncFromTerminal failed unexpectedly');
            $this->finishSyncLog($logId, 'failed', 0, 0, get_class($e) . ': ' . $e->getMessage());
            return [
                'success' => false,
                'message' => get_class($e) . ': ' . $e->getMessage(),
                'error' => get_class($e) . ': ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
        }
    }

    private function fetchAttendanceFromTerminal(array $terminal, ?string $startDate, ?string $endDate): array
    {
        $stepStart = microtime(true);

        $this->log('fetchAttendanceFromTerminal START', [
            'terminalIp' => $terminal['ip_address'] ?? '',
            'startDate' => $startDate,
            'endDate' => $endDate,
            'hasIsapiClient' => $this->isapiClient !== null,
        ]);

        if ($this->isapiClient === null) {
            $this->log('fetchAttendanceFromTerminal NO_CLIENT', [
                'terminalIp' => $terminal['ip_address'] ?? '',
                'elapsed_ms' => round((microtime(true) - $stepStart) * 1000, 2),
            ]);
            return ['success' => false, 'data' => [], 'error' => 'No ISAPI client available'];
        }

        try {
            $result = $this->isapiClient->searchAttendance($startDate, $endDate);

            $this->log('fetchAttendanceFromTerminal ISAPI_RESULT', [
                'terminalIp' => $terminal['ip_address'] ?? '',
                'success' => $result['success'] ?? false,
                'received_count' => count($result['data'] ?? []),
                'error' => $result['error'] ?? null,
                'endpoint' => $result['endpoint'] ?? null,
                'raw_response' => substr((string)($result['raw_response'] ?? ''), 0, 3000),
                'http_code' => $result['http_code'] ?? 0,
                'elapsed_ms' => round((microtime(true) - $stepStart) * 1000, 2),
            ]);

            return $result;
        } catch (\Throwable $e) {
            $this->logException('fetchAttendanceFromTerminal EXCEPTION', $e, [
                'terminalIp' => $terminal['ip_address'] ?? '',
                'elapsed_ms' => round((microtime(true) - $stepStart) * 1000, 2),
            ]);
            return ['success' => false, 'data' => [], 'error' => $e->getMessage()];
        }
    }

    public function syncToTerminal(int $terminalId): array
    {
        $stepStart = microtime(true);
        $this->log('syncToTerminal START', [
            'terminalId' => $terminalId,
        ]);

        $terminal = $this->getTerminal($terminalId);
        if (!$terminal) {
            $this->log('syncToTerminal FAIL', [
                'terminalId' => $terminalId,
                'reason' => 'Terminal not found',
                'elapsed_ms' => round((microtime(true) - $stepStart) * 1000, 2),
            ]);
            return ['success' => false, 'message' => 'Terminal not found'];
        }

        $logId = $this->createSyncLog($terminalId, 'users', 'pending', 'Starting user sync', 0);

        try {
            $stmt = $this->db->query('SELECT id, first_name, last_name, employee_code, badge_code, registration_status FROM ' . self::EMPLOYEE_TABLE . ' WHERE status = :status');
            $stmt->execute(['status' => 'active']);
            $employees = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $this->log('syncToTerminal DB_QUERY_RESULT', [
                'terminalId' => $terminalId,
                'employee_count' => count($employees),
            ]);

            $synced = 0;
            $failed = 0;

            foreach ($employees as $employee) {
                $this->log('syncToTerminal SYNC_USER', [
                    'terminalId' => $terminalId,
                    'employeeCode' => $employee['employee_code'] ?? '',
                    'employeeName' => ($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''),
                ]);

                if ($this->isupClient === null) {
                    $failed++;
                    $this->log('syncToTerminal NO_CLIENT', [
                        'terminalId' => $terminalId,
                        'employeeCode' => $employee['employee_code'] ?? '',
                        'reason' => 'ISUP client not available',
                    ]);
                    continue;
                }

                $result = $this->isupClient->createUser([
                    'name' => trim($employee['first_name'] . ' ' . $employee['last_name']),
                    'employeeNo' => $employee['employee_code'],
                ]);

                $this->log('syncToTerminal SYNC_USER_RESULT', [
                    'terminalId' => $terminalId,
                    'employeeCode' => $employee['employee_code'] ?? '',
                    'success' => $result['success'] ?? false,
                    'error' => $result['error'] ?? null,
                ]);

                if ($result['success']) {
                    $synced++;
                    $sql = 'UPDATE ' . self::EMPLOYEE_TABLE . ' SET registration_status = :status, hikvision_user_id = :hid WHERE id = :id';
                    $params = [
                        'status' => 'registered',
                        'hid' => $result['data']['id'] ?? null,
                        'id' => $employee['id'],
                    ];
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute($params);
                } else {
                    $failed++;
                }
            }

            $this->finishSyncLog($logId, $failed > 0 ? 'partial' : 'success', $synced, $failed);
            $this->updateTerminalSync($terminalId);

            $this->log('syncToTerminal SUCCESS', [
                'terminalId' => $terminalId,
                'synced' => $synced,
                'failed' => $failed,
                'elapsed_ms' => round((microtime(true) - $stepStart) * 1000, 2),
            ]);

            return [
                'success' => true,
                'records_synced' => $synced,
                'records_failed' => $failed,
            ];
        } catch (\Throwable $e) {
            $this->logException('syncToTerminal EXCEPTION', $e, [
                'terminalId' => $terminalId,
                'terminalIp' => $terminal['ip_address'] ?? '',
                'elapsed_ms' => round((microtime(true) - $stepStart) * 1000, 2),
            ]);
            Logger::exception($e, 'syncToTerminal failed unexpectedly');
            $this->finishSyncLog($logId, 'failed', 0, 0, $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function syncAll(): array
    {
        $terminalService = new TerminalService($this->db);
        $terminals = $terminalService->getActiveTerminals();

        $this->log('syncAll START', [
            'terminalCount' => count($terminals),
        ]);

        $results = [];

        foreach ($terminals as $terminal) {
            if ((int)$terminal['sync_enabled'] !== 1) {
                continue;
            }

            $this->log('syncAll SYNC_TERMINAL', [
                'terminalId' => $terminal['id'],
                'terminalName' => $terminal['name'],
            ]);

            $attendanceResult = $this->syncFromTerminal((int)$terminal['id']);
            $usersResult = $this->syncToTerminal((int)$terminal['id']);

            $results[] = [
                'terminal_id' => $terminal['id'],
                'name' => $terminal['name'],
                'attendance' => $attendanceResult,
                'users' => $usersResult,
            ];
        }

        $this->log('syncAll END', [
            'terminalCount' => count($terminals),
            'results' => $results,
        ]);

        return ['success' => true, 'results' => $results];
    }

    public function importUsersFromTerminal(int $terminalId): array
    {
        $stepStart = microtime(true);
        $this->log('importUsersFromTerminal START', [
            'terminalId' => $terminalId,
        ]);

        $terminal = $this->getTerminal($terminalId);
        if (!$terminal) {
            $this->log('importUsersFromTerminal FAIL', [
                'terminalId' => $terminalId,
                'reason' => 'Terminal not found',
            ]);
            return ['success' => false, 'message' => 'Terminal not found'];
        }

        try {
            if ($this->isapiClient === null) {
                return ['success' => false, 'imported' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0, 'message' => 'ISAPI client not available'];
            }

            $remoteUsers = $this->isapiClient->searchUsers();

            $this->log('importUsersFromTerminal FETCH_USERS_RESULT', [
                'terminalId' => $terminalId,
                'success' => $remoteUsers['success'] ?? false,
                'userCount' => count($remoteUsers['data'] ?? []),
                'error' => $remoteUsers['error'] ?? null,
                'http_code' => $remoteUsers['http_code'] ?? 0,
                'endpoint' => $remoteUsers['endpoint'] ?? null,
            ]);

            if (!$remoteUsers['success']) {
                return [
                    'success' => false,
                    'imported' => 0,
                    'updated' => 0,
                    'skipped' => 0,
                    'errors' => 0,
                    'message' => $remoteUsers['error'] ?? 'Failed to fetch users from terminal',
                    'http_code' => $remoteUsers['http_code'] ?? 0,
                    'endpoint' => $remoteUsers['endpoint'] ?? null,
                ];
            }

            if (empty($remoteUsers['data'])) {
                return [
                    'success' => true,
                    'imported' => 0,
                    'updated' => 0,
                    'skipped' => 0,
                    'errors' => 0,
                    'message' => 'No users on terminal',
                ];
            }

            $imported = 0;
            $updated = 0;
            $skipped = 0;
            $errors = 0;

            foreach ($remoteUsers['data'] as $user) {
                $employeeNo = (string) ($user['employeeNo'] ?? '');
                $employeeNoString = (string) ($user['employeeNoString'] ?? '');
                $code = $employeeNo !== '' ? $employeeNo : ($employeeNoString !== '' ? $employeeNoString : '');

                if ($code === '' || $code === '0') {
                    $skipped++;
                    $this->log('importUsersFromTerminal SKIP_NO_CODE', [
                        'terminalId' => $terminalId,
                        'user' => $user,
                        'reason' => 'No employee code',
                    ]);
                    continue;
                }

                $fullName = trim((string) ($user['name'] ?? 'Unknown'));
                $nameParts = explode(' ', $fullName, 2);
                $firstName = $nameParts[0] !== '' ? $nameParts[0] : 'Unknown';
                $lastName = $nameParts[1] ?? '';

                $badgeCode = (string) ($user['cardNo'] ?? '');
                $hikvisionUserId = $employeeNoString !== '' && is_numeric($employeeNoString) ? (int) $employeeNoString : null;

                $existing = $this->findEmployeeByCode($code);

                if ($existing) {
                    $sql = 'UPDATE ' . self::EMPLOYEE_TABLE . '
                        SET first_name = :first,
                            last_name = :last,
                            badge_code = :badge,
                            hikvision_user_id = :hid,
                            status = :status,
                            registration_status = :reg_status,
                            updated_at = CURRENT_TIMESTAMP
                        WHERE id = :id';
                    $params = [
                        'first' => $firstName,
                        'last' => $lastName,
                        'badge' => $badgeCode !== '' ? $badgeCode : null,
                        'hid' => $hikvisionUserId,
                        'status' => 'active',
                        'reg_status' => 'registered',
                        'id' => $existing['id'],
                    ];

                    try {
                        $stmt = $this->db->prepare($sql);
                        $stmt->execute($params);
                        $updated++;
                    } catch (\Throwable $e) {
                        $errors++;
                        $this->logException('importUsersFromTerminal UPDATE_FAILED', $e, [
                            'terminalId' => $terminalId,
                            'employeeCode' => $code,
                        ]);
                    }
                } else {
                    $sql = 'INSERT INTO ' . self::EMPLOYEE_TABLE . '
                        (employee_code, first_name, last_name, badge_code, hikvision_user_id, status, registration_status)
                        VALUES (:code, :first, :last, :badge, :hid, :status, :reg_status)';
                    $params = [
                        'code' => $code,
                        'first' => $firstName,
                        'last' => $lastName,
                        'badge' => $badgeCode !== '' ? $badgeCode : null,
                        'hid' => $hikvisionUserId,
                        'status' => 'active',
                        'reg_status' => 'registered',
                    ];

                    try {
                        $stmt = $this->db->prepare($sql);
                        $stmt->execute($params);
                        $imported++;
                    } catch (\Throwable $e) {
                        $errors++;
                        $this->logException('importUsersFromTerminal INSERT_FAILED', $e, [
                            'terminalId' => $terminalId,
                            'employeeCode' => $code,
                        ]);
                    }
                }
            }

            $this->log('importUsersFromTerminal SUCCESS', [
                'terminalId' => $terminalId,
                'imported' => $imported,
                'updated' => $updated,
                'skipped' => $skipped,
                'errors' => $errors,
                'total_processed' => count($remoteUsers['data']),
                'elapsed_ms' => round((microtime(true) - $stepStart) * 1000, 2),
            ]);

            return [
                'success' => true,
                'imported' => $imported,
                'updated' => $updated,
                'skipped' => $skipped,
                'errors' => $errors,
                'message' => 'Users imported successfully',
            ];
        } catch (\Throwable $e) {
            $this->logException('importUsersFromTerminal EXCEPTION', $e, [
                'terminalId' => $terminalId,
                'terminalIp' => $terminal['ip_address'] ?? '',
            ]);
            Logger::exception($e, 'importUsersFromTerminal failed unexpectedly');
            return [
                'success' => false,
                'imported' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => 0,
                'message' => get_class($e) . ': ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Importe les photos de profil des employés déjà présents sur un terminal.
     *
     * Parcourt les utilisateurs du terminal, télécharge la photo de chaque
     * utilisateur via l'endpoint ISAPI Face/Picture, l'enregistre dans
     * uploads/employees/ et met à jour la colonne employees.photo.
     */
    public function importEmployeePhotos(int $terminalId): array
    {
        $stepStart = microtime(true);
        $this->log('importEmployeePhotos START', ['terminalId' => $terminalId]);

        $terminal = $this->getTerminal($terminalId);
        if (!$terminal) {
            return ['success' => false, 'message' => 'Terminal not found', 'imported' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'total' => 0];
        }

        $logId = $this->createSyncLog($terminalId, 'photos', 'pending', 'Starting employee photo import', 0);

        if ($this->isapiClient === null) {
            return ['success' => false, 'message' => 'ISAPI client not available', 'imported' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'total' => 0];
        }

        $remoteUsers = $this->isapiClient->searchUsers();

        $this->log('importEmployeePhotos FETCH_USERS_RESULT', [
            'terminalId' => $terminalId,
            'terminalIp' => $terminal['ip_address'] ?? '',
            'success' => $remoteUsers['success'] ?? false,
            'userCount' => count($remoteUsers['data'] ?? []),
            'error' => $remoteUsers['error'] ?? null,
            'http_code' => $remoteUsers['http_code'] ?? 0,
            'endpoint' => $remoteUsers['endpoint'] ?? null,
        ]);

        if (!$remoteUsers['success']) {
            $this->finishSyncLog($logId, 'failed', 0, 0, $remoteUsers['error'] ?? 'Failed to fetch users from terminal');
            return [
                'success' => false,
                'message' => $remoteUsers['error'] ?? 'Failed to fetch users from terminal',
                'http_code' => $remoteUsers['http_code'] ?? 0,
                'endpoint' => $remoteUsers['endpoint'] ?? null,
                'imported' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'total' => 0,
            ];
        }

        $users = is_array($remoteUsers['data'] ?? null) ? $remoteUsers['data'] : [];
        if (empty($users)) {
            $this->finishSyncLog($logId, 'success', 0, 0, 'No users on terminal');
            return ['success' => true, 'message' => 'No users on terminal', 'imported' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'total' => 0];
        }

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $results = [];
        $dir = $this->employeePhotoDir();

        foreach ($users as $user) {
            $employeeNo = (string) ($user['employeeNo'] ?? '');
            $employeeNoString = (string) ($user['employeeNoString'] ?? '');
            $code = $employeeNo !== '' ? $employeeNo : ($employeeNoString !== '' ? $employeeNoString : '');
            $terminalKey = $employeeNo !== '' ? $employeeNo : $employeeNoString;

            $existing = ($code !== '' && $code !== '0') ? $this->findEmployeeByCode($code) : null;

            if (!$existing) {
                $skipped++;
                $results[] = ['employee_code' => $code, 'status' => 'skipped', 'reason' => 'No matching local employee'];
                continue;
            }

            $photo = null;
            $faceUrl = (string) ($user['faceURL'] ?? '');
            if ($faceUrl !== '') {
                $photo = $this->isapiClient->getFacePhoto($faceUrl);
                $this->log('importEmployeePhotos FACE_URL_ATTEMPT', [
                    'terminalId' => $terminalId,
                    'employee_code' => $code,
                    'faceURL' => $faceUrl,
                    'success' => $photo['success'] ?? false,
                    'http_code' => $photo['http_code'] ?? 0,
                    'content_type' => $photo['content_type'] ?? '',
                ]);
            }

            // Fallback : endpoint ISAPI /UserInfo/Face/Picture (rarement supporté).
            if ($photo === null || !$photo['success']) {
                $photo = $this->isapiClient->getEmployeePhoto($terminalKey, $employeeNoString);
            }

            if (!$photo['success']) {
                $httpCode = (int) ($photo['http_code'] ?? 0);
                if ($httpCode === 404 || $httpCode === 410 || $httpCode === 400 || ($photo['error'] ?? '') === 'Empty response') {
                    $currentPhoto = (string) ($existing['photo'] ?? '');

                    // L'employé possède déjà une vraie photo (pas un avatar généré) : on la conserve.
                    if ($currentPhoto !== '' && !$this->isGeneratedAvatarPhoto($currentPhoto)) {
                        $skipped++;
                        $results[] = ['employee_code' => $code, 'employee_id' => $existing['id'], 'status' => 'skipped', 'reason' => 'No photo on terminal but employee already has a photo', 'http_code' => $httpCode];
                        continue;
                    }

                    // Le terminal ne fournit pas de photo téléchargeable : on génère un
                    // avatar initiales stable et on l'affecte à l'employé local.
                    $avatarRel = $this->assignGeneratedAvatar(
                        (int) $existing['id'],
                        $code,
                        (string) ($existing['first_name'] ?? ''),
                        (string) ($existing['last_name'] ?? '')
                    );

                    if ($avatarRel === null) {
                        $failed++;
                        $results[] = ['employee_code' => $code, 'employee_id' => $existing['id'], 'status' => 'failed', 'error' => 'Failed to generate avatar', 'http_code' => $httpCode];
                        continue;
                    }

                    $updateSql = 'UPDATE ' . self::EMPLOYEE_TABLE . ' SET photo = :photo, updated_at = CURRENT_TIMESTAMP WHERE id = :id';
                    try {
                        $stmt = $this->db->prepare($updateSql);
                        $stmt->execute(['photo' => $avatarRel, 'id' => (int) $existing['id']]);

                        if ($currentPhoto !== '' && $currentPhoto !== $avatarRel) {
                            $this->deleteOldPhoto($currentPhoto);
                        }

                        if ($currentPhoto !== '') {
                            $updated++;
                            $results[] = ['employee_code' => $code, 'employee_id' => $existing['id'], 'status' => 'updated', 'photo' => $avatarRel, 'method' => 'avatar', 'http_code' => $httpCode];
                        } else {
                            $imported++;
                            $results[] = ['employee_code' => $code, 'employee_id' => $existing['id'], 'status' => 'imported', 'photo' => $avatarRel, 'method' => 'avatar', 'http_code' => $httpCode];
                        }
                    } catch (\Throwable $e) {
                        $failed++;
                        $this->logException('importEmployeePhotos AVATAR_UPDATE_FAILED', $e, [
                            'terminalId' => $terminalId,
                            'employee_code' => $code,
                            'employee_id' => $existing['id'],
                        ]);
                        $results[] = ['employee_code' => $code, 'employee_id' => $existing['id'], 'status' => 'failed', 'error' => $e->getMessage()];
                    }
                    continue;
                }
                $failed++;
                $results[] = ['employee_code' => $code, 'employee_id' => $existing['id'], 'status' => 'failed', 'error' => $photo['error'] ?? 'Unknown', 'http_code' => $httpCode];
                continue;
            }

            $ext = $this->photoExtension((string) ($photo['content_type'] ?? ''), (string) ($photo['data'] ?? ''));
            if ($ext === null) {
                $failed++;
                $results[] = ['employee_code' => $code, 'employee_id' => $existing['id'], 'status' => 'failed', 'error' => 'Unsupported image type', 'content_type' => (string) ($photo['content_type'] ?? '')];
                continue;
            }

            $filename = $this->buildPhotoFilename((int) $existing['id'], $code, $ext);
            $destPath = $dir . $filename;
            $destRel = 'uploads/employees/' . $filename;
            $binary = (string) $photo['data'];
            $hasPriorPhoto = !empty($existing['photo']);

            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            $this->log('importEmployeePhotos SAVE_ATTEMPT', [
                'terminalId' => $terminalId,
                'employee_code' => $code,
                'employee_id' => $existing['id'],
                'file' => $destRel,
                'content_type' => $photo['content_type'] ?? null,
            ]);

            $written = @file_put_contents($destPath, $binary);
            if ($written === false) {
                $failed++;
                $results[] = ['employee_code' => $code, 'employee_id' => $existing['id'], 'status' => 'failed', 'error' => 'Failed to write file'];
                continue;
            }

            if ($hasPriorPhoto) {
                $this->deleteOldPhoto((string) $existing['photo']);
            }

            $sql = 'UPDATE ' . self::EMPLOYEE_TABLE . ' SET photo = :photo, updated_at = CURRENT_TIMESTAMP WHERE id = :id';
            try {
                $stmt = $this->db->prepare($sql);
                $stmt->execute(['photo' => $destRel, 'id' => (int) $existing['id']]);
                if ($hasPriorPhoto) {
                    $updated++;
                } else {
                    $imported++;
                }
                $results[] = ['employee_code' => $code, 'employee_id' => $existing['id'], 'status' => $hasPriorPhoto ? 'updated' : 'imported', 'photo' => $destRel, 'bytes' => $written];
            } catch (\Throwable $e) {
                @unlink($destPath);
                $failed++;
                $this->logException('importEmployeePhotos UPDATE_FAILED', $e, [
                    'terminalId' => $terminalId,
                    'employee_code' => $code,
                    'employee_id' => $existing['id'],
                ]);
                $results[] = ['employee_code' => $code, 'employee_id' => $existing['id'], 'status' => 'failed', 'error' => $e->getMessage()];
            }
        }

        $totalSet = $imported + $updated;
        $summary = sprintf('Photos importées : %d nouvelle(s), %d mise(s) à jour, %d ignorée(s), %d erreur(s) (sur %d utilisateurs du terminal)', $imported, $updated, $skipped, $failed, count($users));

        $this->finishSyncLog(
            $logId,
            $failed > 0 ? ($totalSet > 0 ? 'partial' : 'failed') : 'success',
            $totalSet,
            $failed,
            $summary
        );

        $this->log('importEmployeePhotos END', [
            'terminalId' => $terminalId,
            'total' => count($users),
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $failed,
            'elapsed_ms' => round((microtime(true) - $stepStart) * 1000, 2),
        ]);

        return [
            'success' => $failed === 0,
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $failed,
            'total' => count($users),
            'message' => $summary,
            'results' => $results,
        ];
    }

    /**
     * Retourne le répertoire de stockage local des photos d'employés.
     */
    private function employeePhotoDir(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'employees' . DIRECTORY_SEPARATOR;
    }

    /**
     * Détermine l'extension de fichier image à partir du type MIME ou de la lecture des octets.
     */
    private function photoExtension(string $contentType, string $binary): ?string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/jpg'  => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
            'image/bmp'  => 'bmp',
        ];

        $type = strtolower($contentType);
        if (isset($map[$type])) {
            return $map[$type];
        }

        if ($binary !== '') {
            $info = @getimagesizefromstring($binary);
            if (is_array($info) && isset($info['mime'])) {
                $mime = strtolower($info['mime']);
                if (isset($map[$mime])) {
                    return $map[$mime];
                }
            }

            if (strncmp($binary, "\xFF\xD8\xFF", 3) === 0) {
                return 'jpg';
            }
            if (strncmp($binary, "\x89PNG\r\n\x1a\n", 8) === 0) {
                return 'png';
            }
            if (strncmp($binary, 'GIF87a', 6) === 0 || strncmp($binary, 'GIF89a', 6) === 0) {
                return 'gif';
            }
            if (strncmp($binary, "RIFF", 4) === 0 && substr($binary, 8, 4) === 'WEBP') {
                return 'webp';
            }
        }

        return null;
    }

    /**
     * Construit un nom de fichier photo unique et traçable.
     */
    private function buildPhotoFilename(int $employeeId, string $code, string $ext): string
    {
        $safeCode = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $code);
        if ($safeCode === '') {
            $safeCode = 'emp' . $employeeId;
        }

        return 'emp_' . $safeCode . '_' . time() . '_' . random_int(1000, 9999) . '.' . $ext;
    }

    /**
     * Supprime un ancien fichier photo lorsqu'il se trouve dans le répertoire
     * de photos d'employés (et uniquement dans ce répertoire).
     */
    private function deleteOldPhoto(string $photo): void
    {
        if ($photo === '') {
            return;
        }

        $dir = rtrim($this->employeePhotoDir(), DIRECTORY_SEPARATOR);
        $photo = ltrim($photo, '/');

        // Ne supprimer que les fichiers situés dans le répertoire employees/.
        if (str_starts_with($photo, 'uploads/employees/')) {
            $base = basename($photo);
        } elseif (str_starts_with($photo, 'employees/')) {
            $base = basename($photo);
        } else {
            return;
        }

        if ($base === '' || $base === '.' || $base === '..') {
            return;
        }

        $candidate = $dir . DIRECTORY_SEPARATOR . $base;
        if (strpos($candidate, $dir) === 0 && is_file($candidate)) {
            @unlink($candidate);
        }
    }

    /**
     * Détermine si une photo stockée est un avatar SVG généré localement
     * (au lieu d'une vraie photo importée depuis le terminal).
     */
    private function isGeneratedAvatarPhoto(string $photo): bool
    {
        $photo = ltrim($photo, '/');
        if (str_starts_with($photo, 'uploads/employees/')) {
            $photo = substr($photo, strlen('uploads/employees/'));
        } elseif (str_starts_with($photo, 'employees/')) {
            $photo = substr($photo, strlen('employees/'));
        }

        return preg_match('/^emp_[A-Za-z0-9_-]+\.svg$/i', $photo) === 1;
    }

    /**
     * Génère un avatar SVG initiales stable pour un employé et l'enregistre
     * dans uploads/employees/. Retourne le chemin relatif web ou null en cas
     * d'échec.
     */
    private function assignGeneratedAvatar(int $employeeId, string $code, string $firstName, string $lastName): ?string
    {
        $safeCode = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $code);
        if ($safeCode === '') {
            $safeCode = 'emp' . $employeeId;
        }

        $filename = 'emp_' . $safeCode . '.svg';
        $dir = $this->employeePhotoDir();

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $svg = \App\Models\Employee::generateAvatarSvg($firstName, $lastName);
        if ($svg === '' || @file_put_contents($dir . $filename, $svg) === false) {
            return null;
        }

        return 'uploads/employees/' . $filename;
    }

    public function exportUsersToTerminal(int $terminalId): array
    {
        return $this->syncToTerminal($terminalId);
    }

    public function processAttendanceRecords(array $records, int $terminalId): array
    {
        $processed = [];
        $terminal = $this->getTerminal($terminalId);
        $terminalIp = $terminal['ip_address'] ?? '';

        $this->log('processAttendanceRecords START', [
            'terminalId' => $terminalId,
            'terminalIp' => $terminalIp,
            'recordCount' => count($records),
        ]);

        $employeeDateDeduped = [];

        $receivedCount = count($records);
        $insertedCount = 0;
        $ignoredNoEmployee = 0;
        $ignoredDuplicate = 0;
        $ignoredOtherError = 0;

        foreach ($records as $index => $record) {
            $recordStart = microtime(true);

            $this->log('processAttendanceRecords RECORD_START', [
                'terminalId' => $terminalId,
                'recordIndex' => $index,
                'employeeNo' => $record['employeeNo'] ?? '',
                'employeeNoString' => $record['employeeNoString'] ?? '',
                'cardNo' => $record['cardNo'] ?? '',
                'eventType' => $record['eventType'] ?? '',
                'date' => $record['date'] ?? '',
                'time' => $record['time'] ?? '',
                'allFields' => $record,
            ]);

            try {
                $employeeNo = (string) ($record['employeeNo'] ?? '');
                $employeeNoString = (string) ($record['employeeNoString'] ?? '');
                $cardNo = (string) ($record['cardNo'] ?? '');
                $employeeName = (string) ($record['employeeName'] ?? $record['name'] ?? '');
                $major = (string) ($record['major'] ?? '');
                $minor = (string) ($record['minor'] ?? '');
                $eventType = (string) ($record['eventType'] ?? '');
                $recordIsSystemEvent = !empty($record['isSystemEvent']) || ($record['isSystemEvent'] ?? false);

                if ($recordIsSystemEvent) {
                    $this->log('processAttendanceRecords SKIP_SYSTEM_EVENT', [
                        'terminalId' => $terminalId,
                        'recordIndex' => $index,
                        'employeeNo' => $employeeNo,
                        'employeeNoString' => $employeeNoString,
                        'cardNo' => $cardNo,
                        'major' => $major,
                        'minor' => $minor,
                        'eventType' => $eventType,
                        'reason' => 'System event ignored (no employee identifier or card number)',
                    ]);
                    continue;
                }

                if ($employeeNo === '' || $employeeNo === '0') {
                    $employeeNo = $employeeNoString;
                }

                $employeeId = null;
                $matchMethod = '';
                $allLookupSQLs = [];

                if ($employeeNo !== '' && $employeeNo !== '0') {
                    $r = $this->resolveEmployeeId($employeeNo);
                    $allLookupSQLs[] = $r;
                    if ($r['found']) {
                        $employeeId = $r['id'];
                        $matchMethod = 'employee_code (employeeNo=' . $employeeNo . ')';
                    }
                }

                if (!$employeeId && $cardNo !== '' && $cardNo !== '0') {
                    $r = $this->resolveEmployeeIdByCard($cardNo);
                    $allLookupSQLs[] = $r;
                    if ($r['found']) {
                        $employeeId = $r['id'];
                        $matchMethod = 'badge_code (cardNo=' . $cardNo . ')';
                    }
                }

                if (!$employeeId && $employeeNo !== '' && $employeeNo !== '0') {
                    $r = $this->resolveEmployeeByIdempNo($employeeNo);
                    $allLookupSQLs[] = $r;
                    if ($r['found']) {
                        $employeeId = $r['id'];
                        $matchMethod = 'hikvision_user_id (employeeNo=' . $employeeNo . ')';
                    }
                }

                if (!$employeeId && $employeeNoString !== '' && $employeeNoString !== '0') {
                    $r = $this->resolveEmployeeId($employeeNoString);
                    $allLookupSQLs[] = $r;
                    if ($r['found']) {
                        $employeeId = $r['id'];
                        $matchMethod = 'employee_code (employeeNoString=' . $employeeNoString . ')';
                    }
                }

                if (!$employeeId && $employeeNoString !== '' && $employeeNoString !== '0') {
                    $r = $this->resolveEmployeeByIdempNo($employeeNoString);
                    $allLookupSQLs[] = $r;
                    if ($r['found']) {
                        $employeeId = $r['id'];
                        $matchMethod = 'hikvision_user_id (employeeNoString=' . $employeeNoString . ')';
                    }
                }

                if (!$employeeId && $employeeNoString !== '' && $employeeNoString !== '0') {
                    $r = $this->resolveEmployeeIdByCard($employeeNoString);
                    $allLookupSQLs[] = $r;
                    if ($r['found']) {
                        $employeeId = $r['id'];
                        $matchMethod = 'badge_code (employeeNoString=' . $employeeNoString . ')';
                    }
                }

                if (!$employeeId && $employeeName !== '') {
                    $r = $this->resolveEmployeeByName($employeeName);
                    $allLookupSQLs[] = $r;
                    if ($r['found']) {
                        $employeeId = $r['id'];
                        $matchMethod = 'name_match (name=' . $employeeName . ') [WARN: ambiguous]';
                    }
                }

                if (!$employeeId) {
                    $ignoredNoEmployee++;
                    $this->log('processAttendanceRecords SKIP_NO_EMPLOYEE', [
                        'terminalId' => $terminalId,
                        'recordIndex' => $index,
                        'employeeNo' => $employeeNo,
                        'employeeNoString' => $employeeNoString,
                        'cardNo' => $cardNo,
                        'employeeName' => $employeeName,
                        'major' => $major,
                        'minor' => $minor,
                        'eventType' => $eventType,
                        'allLookupSQLs' => $allLookupSQLs,
                        'reason' => 'Employee not found in local database by any identifier or name',
                    ]);
                    continue;
                }

                $date = $record['date'] ?? date('Y-m-d');
                $time = $record['time'] ?? '00:00:00';

                $batchDedupKey = $employeeId . ':' . $date . ':' . $time;
                if (isset($employeeDateDeduped[$batchDedupKey])) {
                    $ignoredDuplicate++;
                    $this->log('processAttendanceRecords SKIP_DUPLICATE', [
                        'terminalId' => $terminalId,
                        'recordIndex' => $index,
                        'employeeId' => $employeeId,
                        'employeeNo' => $employeeNo,
                        'employeeNoString' => $employeeNoString,
                        'cardNo' => $cardNo,
                        'datetime' => $date . ' ' . $time,
                        'reason' => 'Duplicate event timestamp already processed in this batch',
                    ]);
                    continue;
                }

                $duplicateSql = 'SELECT 1 FROM ' . self::ATTENDANCE_TABLE . '
                    WHERE employee_id = :employee_id
                      AND attendance_date = :attendance_date
                      AND attendance_time = :attendance_time
                    LIMIT 1';
                $duplicateStmt = $this->db->prepare($duplicateSql);
                $duplicateStmt->execute([
                    ':employee_id' => $employeeId,
                    ':attendance_date' => $date,
                    ':attendance_time' => $time,
                ]);
                if ($duplicateStmt->fetch(\PDO::FETCH_ASSOC) !== false) {
                    $ignoredDuplicate++;
                    $employeeDateDeduped[$batchDedupKey] = true;
                    $this->log('processAttendanceRecords SKIP_DUPLICATE', [
                        'terminalId' => $terminalId,
                        'recordIndex' => $index,
                        'employeeId' => $employeeId,
                        'employeeNo' => $employeeNo,
                        'employeeNoString' => $employeeNoString,
                        'cardNo' => $cardNo,
                        'datetime' => $date . ' ' . $time,
                        'reason' => 'Record already exists in database for this employee and datetime',
                    ]);
                    continue;
                }

                $employeeDateDeduped[$batchDedupKey] = true;

                $typesSql = 'SELECT DISTINCT type FROM ' . self::ATTENDANCE_TABLE . '
                    WHERE employee_id = :employee_id
                      AND attendance_date = :attendance_date
                      AND type IN ("check_in", "check_out")';
                $typesStmt = $this->db->prepare($typesSql);
                $typesStmt->execute([
                    ':employee_id' => $employeeId,
                    ':attendance_date' => $date,
                ]);
                $existingTypes = $typesStmt->fetchAll(\PDO::FETCH_COLUMN);
                $hasCheckIn = in_array('check_in', $existingTypes, true);
                $hasCheckOut = in_array('check_out', $existingTypes, true);

                if ($hasCheckIn && $hasCheckOut) {
                    // Rule 3: Both check_in and check_out exist.
                    // Do NOT insert another row.
                    // UPDATE the existing check_out with the newest attendance_time.
                    // The newest attendance_time must always become the employee's exit time.
                    $updateTimeSql = 'UPDATE ' . self::ATTENDANCE_TABLE . '
                        SET attendance_time = GREATEST(attendance_time, :attendance_time),
                            updated_at = CURRENT_TIMESTAMP
                        WHERE employee_id = :employee_id
                          AND attendance_date = :attendance_date
                          AND type = "check_out"
                        LIMIT 1';
                    $updateTimeStmt = $this->db->prepare($updateTimeSql);
                    $updateTimeStmt->execute([
                        ':attendance_time' => $time,
                        ':employee_id' => $employeeId,
                        ':attendance_date' => $date,
                    ]);
                    $insertedCount++;

                    $type = 'check_out';

                    $metrics = $this->calculateWorkMetrics($employeeId, $date);
                    $metricsUpdateSql = 'UPDATE ' . self::ATTENDANCE_TABLE . ' SET
                        work_duration_minutes = :work_duration,
                        late_minutes = :late_minutes,
                        early_departure_minutes = :early_departure,
                        overtime_minutes = :overtime,
                        missing_minutes = :missing
                        WHERE employee_id = :employee_id AND attendance_date = :date';
                    $metricsUpdateParams = [
                        'work_duration' => $metrics['work_duration_minutes'],
                        'late_minutes' => $metrics['late_minutes'],
                        'early_departure' => $metrics['early_departure_minutes'],
                        'overtime' => $metrics['overtime_minutes'],
                        'missing' => $metrics['missing_minutes'],
                        'employee_id' => $employeeId,
                        'date' => $date,
                    ];
                    $metricsUpdateStmt = $this->db->prepare($metricsUpdateSql);
                    $metricsUpdateStmt->execute($metricsUpdateParams);

                    $processed[] = [
                        'employee_id' => $employeeId,
                        'date' => $date,
                        'time' => $time,
                        'type' => $type,
                        'match_method' => $matchMethod,
                    ];

                    $this->log('processAttendanceRecords RECORD_INSERTED', [
                        'terminalId' => $terminalId,
                        'recordIndex' => $index,
                        'employeeId' => $employeeId,
                        'employeeNo' => $employeeNo,
                        'employeeNoString' => $employeeNoString,
                        'cardNo' => $cardNo,
                        'matchMethod' => $matchMethod,
                        'datetime' => $date . ' ' . $time,
                        'eventType' => $record['eventType'] ?? '',
                        'metrics' => $metrics,
                    ]);
                    continue;
                }

                // Rule 1: No attendance record for the day -> insert check_in
                // Rule 2: check_in exists but no check_out -> insert check_out
                $type = !$hasCheckIn ? 'check_in' : 'check_out';

                $sql = 'INSERT INTO ' . self::ATTENDANCE_TABLE . '
                    (employee_id, attendance_date, attendance_time, type, terminal_id, terminal_ip, source,
                    work_duration_minutes, late_minutes, early_departure_minutes, overtime_minutes, missing_minutes)
                    VALUES
                    (:employee_id, :attendance_date, :attendance_time, :type, :terminal_id, :terminal_ip, :source,
                    :work_duration, :late_minutes, :early_departure, :overtime, :missing)
                    ON DUPLICATE KEY UPDATE
                    type = VALUES(type),
                    terminal_id = VALUES(terminal_id),
                    terminal_ip = VALUES(terminal_ip),
                    work_duration_minutes = VALUES(work_duration_minutes),
                    late_minutes = VALUES(late_minutes),
                    early_departure_minutes = VALUES(early_departure_minutes),
                    overtime_minutes = VALUES(overtime_minutes),
                    missing_minutes = VALUES(missing_minutes),
                    updated_at = CURRENT_TIMESTAMP';

                $params = [
                    'employee_id' => $employeeId,
                    'attendance_date' => $date,
                    'attendance_time' => $time,
                    'type' => $type,
                    'terminal_id' => $terminalId,
                    'terminal_ip' => $terminalIp,
                    'source' => 'terminal',
                    'work_duration' => 0,
                    'late_minutes' => 0,
                    'early_departure' => 0,
                    'overtime' => 0,
                    'missing' => 480,
                ];

                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
                $insertedId = (int) $this->db->lastInsertId();
                $insertedCount++;

                $metrics = $this->calculateWorkMetrics($employeeId, $date);
                $updateSql = 'UPDATE ' . self::ATTENDANCE_TABLE . ' SET
                    work_duration_minutes = :work_duration,
                    late_minutes = :late_minutes,
                    early_departure_minutes = :early_departure,
                    overtime_minutes = :overtime,
                    missing_minutes = :missing
                    WHERE employee_id = :employee_id AND attendance_date = :date';
                $updateParams = [
                    'work_duration' => $metrics['work_duration_minutes'],
                    'late_minutes' => $metrics['late_minutes'],
                    'early_departure' => $metrics['early_departure_minutes'],
                    'overtime' => $metrics['overtime_minutes'],
                    'missing' => $metrics['missing_minutes'],
                    'employee_id' => $employeeId,
                    'date' => $date,
                ];
                $updateStmt = $this->db->prepare($updateSql);
                $updateStmt->execute($updateParams);

                $processed[] = [
                    'employee_id' => $employeeId,
                    'date' => $date,
                    'time' => $time,
                    'type' => $type,
                    'match_method' => $matchMethod,
                ];

                $this->log('processAttendanceRecords RECORD_INSERTED', [
                    'terminalId' => $terminalId,
                    'recordIndex' => $index,
                    'employeeId' => $employeeId,
                    'employeeNo' => $employeeNo,
                    'employeeNoString' => $employeeNoString,
                    'cardNo' => $cardNo,
                    'matchMethod' => $matchMethod,
                    'datetime' => $date . ' ' . $time,
                    'eventType' => $record['eventType'] ?? '',
                    'metrics' => $metrics,
                ]);
            } catch (\Throwable $e) {
                $ignoredOtherError++;
                $this->logException('processAttendanceRecords RECORD_FAILED', $e, [
                    'terminalId' => $terminalId,
                    'recordIndex' => $index,
                    'employeeNo' => $record['employeeNo'] ?? '',
                    'employeeNoString' => $record['employeeNoString'] ?? '',
                    'cardNo' => $record['cardNo'] ?? '',
                    'datetime' => ($record['date'] ?? '') . ' ' . ($record['time'] ?? ''),
                    'eventType' => $record['eventType'] ?? '',
                    'elapsed_ms' => round((microtime(true) - $recordStart) * 1000, 2),
                ]);
                Logger::exception($e, 'processAttendanceRecords failed for record');
                continue;
            }
        }

        $this->log('processAttendanceRecords END', [
            'terminalId' => $terminalId,
            'totalRecords' => $receivedCount,
            'insertedCount' => $insertedCount,
            'ignoredNoEmployee' => $ignoredNoEmployee,
            'ignoredDuplicate' => $ignoredDuplicate,
            'ignoredOtherError' => $ignoredOtherError,
            'processedCount' => count($processed),
        ]);

        return $processed;
    }

    public function deduplicateAttendance(int $employeeId, string $date): void
    {
        $sql = 'DELETE al1 FROM ' . self::ATTENDANCE_TABLE . ' al1
            INNER JOIN ' . self::ATTENDANCE_TABLE . ' al2
            ON al1.employee_id = al2.employee_id
            AND al1.attendance_date = al2.attendance_date
            AND al1.attendance_time = al2.attendance_time
            AND al1.type = al2.type
            WHERE al1.id > al2.id
            AND al1.employee_id = :employee_id
            AND al1.attendance_date = :date';

        $params = ['employee_id' => $employeeId, 'date' => $date];

        $this->log('deduplicateAttendance', [
            'employeeId' => $employeeId,
            'date' => $date,
            'sql' => $sql,
            'params' => $params,
        ]);

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        } catch (\Throwable $e) {
            $this->logException('deduplicateAttendance EXCEPTION', $e, [
                'employeeId' => $employeeId,
                'date' => $date,
                'sql' => $sql,
                'params' => $params,
            ]);
            Logger::exception($e, 'deduplicateAttendance failed');
        }
    }

    public function calculateWorkMetrics(int $employeeId, string $date): array
    {
        $sql = 'SELECT MIN(attendance_time) AS first_in, MAX(attendance_time) AS last_out FROM ' . self::ATTENDANCE_TABLE . ' WHERE employee_id = :employee_id AND attendance_date = :date';
        $params = ['employee_id' => $employeeId, 'date' => $date];

        $this->log('calculateWorkMetrics', [
            'employeeId' => $employeeId,
            'date' => $date,
            'sql' => $sql,
            'params' => $params,
        ]);

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $times = $stmt->fetch(\PDO::FETCH_ASSOC);

            $workDuration = 0;
            $lateMinutes = 0;
            $earlyDepartureMinutes = 0;
            $overtimeMinutes = 0;
            $missingMinutes = 480;

            if ($times && $times['first_in'] && $times['last_out']) {
                $in = new \DateTime($date . ' ' . $times['first_in']);
                $out = new \DateTime($date . ' ' . $times['last_out']);
                $workDuration = $out->getTimestamp() - $in->getTimestamp();

                $scheduledStart = new \DateTime($date . ' 08:30:00');
                $scheduledEnd = new \DateTime($date . ' 17:30:00');

                if ($in > $scheduledStart) {
                    $lateMinutes = ($in->getTimestamp() - $scheduledStart->getTimestamp()) / 60;
                }

                if ($out < $scheduledEnd) {
                    $earlyDepartureMinutes = ($scheduledEnd->getTimestamp() - $out->getTimestamp()) / 60;
                }

                $scheduledDuration = $scheduledEnd->getTimestamp() - $scheduledStart->getTimestamp();
                if ($workDuration > $scheduledDuration) {
                    $overtimeMinutes = ($workDuration - $scheduledDuration) / 60;
                }

                $missingMinutes = max(0, $scheduledDuration - $workDuration) / 60;
            }

            $result = [
                'work_duration_minutes' => (int)round($workDuration / 60),
                'late_minutes' => (int)round($lateMinutes),
                'early_departure_minutes' => (int)round($earlyDepartureMinutes),
                'overtime_minutes' => (int)round($overtimeMinutes),
                'missing_minutes' => (int)round($missingMinutes),
            ];

            $this->log('calculateWorkMetrics RESULT', [
                'employeeId' => $employeeId,
                'date' => $date,
                'result' => $result,
            ]);

            return $result;
        } catch (\Throwable $e) {
            $this->logException('calculateWorkMetrics EXCEPTION', $e, [
                'employeeId' => $employeeId,
                'date' => $date,
                'sql' => $sql,
                'params' => $params,
            ]);
            Logger::exception($e, 'calculateWorkMetrics failed');
            return [
                'work_duration_minutes' => 0,
                'late_minutes' => 0,
                'early_departure_minutes' => 0,
                'overtime_minutes' => 0,
                'missing_minutes' => 480,
            ];
        }
    }

    public function getLastSync(int $terminalId): ?string
    {
        $sql = 'SELECT last_sync FROM ' . self::TERMINAL_TABLE . ' WHERE id = :id LIMIT 1';
        $params = ['id' => $terminalId];

        $this->log('getLastSync', [
            'terminalId' => $terminalId,
            'sql' => $sql,
            'params' => $params,
        ]);

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $lastSync = $result['last_sync'] ?? null;

            $this->log('getLastSync RESULT', [
                'terminalId' => $terminalId,
                'lastSync' => $lastSync,
            ]);

            return $lastSync;
        } catch (\Throwable $e) {
            $this->logException('getLastSync EXCEPTION', $e, [
                'terminalId' => $terminalId,
                'sql' => $sql,
                'params' => $params,
            ]);
            Logger::exception($e, 'getLastSync failed');
            return null;
        }
    }

    public function createSyncLog(int $terminalId, string $type, string $status, string $message, int $recordsCount = 0): ?int
    {
        $sql = 'INSERT INTO ' . self::SYNC_LOG_TABLE . '
            (terminal_id, sync_type, status, records_synced, records_failed, message, started_at)
            VALUES
            (:terminal_id, :sync_type, :status, :records_synced, :records_failed, :message, :started_at)';

        $params = [
            'terminal_id' => $terminalId,
            'sync_type' => $type,
            'status' => $status,
            'records_synced' => $recordsCount,
            'records_failed' => 0,
            'message' => $message,
            'started_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ];

        $this->log('createSyncLog', [
            'terminalId' => $terminalId,
            'syncType' => $type,
            'status' => $status,
            'message' => $message,
            'recordsCount' => $recordsCount,
            'sql' => $sql,
            'params' => $params,
        ]);

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $logId = (int)$this->db->lastInsertId();

            $this->log('createSyncLog SUCCESS', [
                'terminalId' => $terminalId,
                'logId' => $logId,
            ]);

            return $logId;
        } catch (\Throwable $e) {
            $this->logException('createSyncLog EXCEPTION', $e, [
                'terminalId' => $terminalId,
                'sql' => $sql,
                'params' => $params,
            ]);
            Logger::exception($e, 'createSyncLog failed');
            return null;
        }
    }

    public function checkForNewRecords(int $terminalId, ?string $lastSync): bool
    {
        $this->log('checkForNewRecords', [
            'terminalId' => $terminalId,
            'lastSync' => $lastSync,
        ]);

        if ($lastSync === null) {
            return true;
        }

        $terminal = $this->getTerminal($terminalId);
        if (!$terminal) {
            return false;
        }

        return true;
    }

    private function getTerminal(int $id): ?array
    {
        $sql = 'SELECT * FROM ' . self::TERMINAL_TABLE . ' WHERE id = :id LIMIT 1';
        $params = ['id' => $id];

        $this->log('getTerminal', [
            'terminalId' => $id,
            'sql' => $sql,
            'params' => $params,
        ]);

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $terminal = $result ?: null;

            $this->log('getTerminal RESULT', [
                'terminalId' => $id,
                'found' => $terminal !== null,
            ]);

            return $terminal;
        } catch (\Throwable $e) {
            $this->logException('getTerminal EXCEPTION', $e, [
                'terminalId' => $id,
                'sql' => $sql,
                'params' => $params,
            ]);
            Logger::exception($e, 'getTerminal failed');
            return null;
        }
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

    private function updateTerminalSync(int $terminalId, bool $connectionError = false): void
    {
        $status = $connectionError ? 'error' : 'online';
        $sql = 'UPDATE ' . self::TERMINAL_TABLE . ' SET last_sync = :last_sync, connection_status = :status WHERE id = :id';
        $params = [
            'last_sync' => (new \DateTime())->format('Y-m-d H:i:s'),
            'status' => $status,
            'id' => $terminalId,
        ];

        $this->log('updateTerminalSync', [
            'terminalId' => $terminalId,
            'status' => $status,
            'sql' => $sql,
            'params' => $params,
        ]);

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $this->log('updateTerminalSync SUCCESS', [
                'terminalId' => $terminalId,
                'affectedRows' => $stmt->rowCount(),
            ]);
        } catch (\Throwable $e) {
            $this->logException('updateTerminalSync EXCEPTION', $e, [
                'terminalId' => $terminalId,
                'sql' => $sql,
                'params' => $params,
            ]);
            Logger::exception($e, 'updateTerminalSync failed');
        }
    }

    private function finishSyncLog(int $logId, string $status, int $recordsCount, int $failedCount, string $message = ''): void
    {
        $sql = 'UPDATE ' . self::SYNC_LOG_TABLE . '
            SET status = :status, records_synced = :records_synced, records_failed = :records_failed,
            message = :message, finished_at = :finished_at
            WHERE id = :id';

        $params = [
            'status' => $status,
            'records_synced' => $recordsCount,
            'records_failed' => $failedCount,
            'message' => $message,
            'finished_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            'id' => $logId,
        ];

        $this->log('finishSyncLog', [
            'logId' => $logId,
            'status' => $status,
            'recordsCount' => $recordsCount,
            'failedCount' => $failedCount,
            'message' => $message,
            'sql' => $sql,
            'params' => $params,
        ]);

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $this->log('finishSyncLog SUCCESS', [
                'logId' => $logId,
                'affectedRows' => $stmt->rowCount(),
            ]);
        } catch (\Throwable $e) {
            $this->logException('finishSyncLog EXCEPTION', $e, [
                'logId' => $logId,
                'sql' => $sql,
                'params' => $params,
            ]);
            Logger::exception($e, 'finishSyncLog failed');
        }
    }

    private function findEmployeeByCode(string $code): ?array
    {
        $sql = 'SELECT id, employee_code, first_name, last_name, photo FROM ' . self::EMPLOYEE_TABLE . ' WHERE employee_code = :code AND status = :status LIMIT 1';
        $params = ['code' => $code, 'status' => 'active'];

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function resolveEmployeeId(string $employeeCode): array
    {
        $sql = 'SELECT id, employee_code, first_name, last_name FROM ' . self::EMPLOYEE_TABLE . ' WHERE employee_code = :code AND status = :status LIMIT 1';
        $params = ['code' => $employeeCode, 'status' => 'active'];

        $this->log('resolveEmployeeId', [
            'employeeCode' => $employeeCode,
            'sql' => $sql,
            'params' => $params,
        ]);

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $found = $result !== false;
            $employeeId = $found ? (int)$result['id'] : null;

            $this->log('resolveEmployeeId RESULT', [
                'employeeCode' => $employeeCode,
                'employeeId' => $employeeId,
                'sql' => $sql,
                'dbResult' => $found ? $result : null,
            ]);

            return ['found' => $found, 'id' => $employeeId, 'sql' => $sql, 'params' => $params, 'dbResult' => $result];
        } catch (\Throwable $e) {
            $this->logException('resolveEmployeeId EXCEPTION', $e, [
                'employeeCode' => $employeeCode,
                'sql' => $sql,
                'params' => $params,
            ]);
            Logger::exception($e, 'resolveEmployeeId failed');
            return ['found' => false, 'id' => null, 'sql' => $sql, 'params' => $params, 'error' => $e->getMessage()];
        }
    }

    private function resolveEmployeeIdByCard(string $cardNo): array
    {
        $sql = 'SELECT id, employee_code, first_name, last_name, badge_code FROM ' . self::EMPLOYEE_TABLE . ' WHERE badge_code = :cardNo AND status = :status LIMIT 1';
        $params = ['cardNo' => $cardNo, 'status' => 'active'];

        $this->log('resolveEmployeeIdByCard', [
            'cardNo' => $cardNo,
            'sql' => $sql,
            'params' => $params,
        ]);

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $found = $result !== false;
            $employeeId = $found ? (int)$result['id'] : null;

            $this->log('resolveEmployeeIdByCard RESULT', [
                'cardNo' => $cardNo,
                'employeeId' => $employeeId,
                'sql' => $sql,
                'dbResult' => $found ? $result : null,
            ]);

            return ['found' => $found, 'id' => $employeeId, 'sql' => $sql, 'params' => $params, 'dbResult' => $result];
        } catch (\Throwable $e) {
            $this->logException('resolveEmployeeIdByCard EXCEPTION', $e, [
                'cardNo' => $cardNo,
                'sql' => $sql,
                'params' => $params,
            ]);
            Logger::exception($e, 'resolveEmployeeIdByCard failed');
            return ['found' => false, 'id' => null, 'sql' => $sql, 'params' => $params, 'error' => $e->getMessage()];
        }
    }

    private function resolveEmployeeByIdempNo(string $employeeNo): array
    {
        if ($employeeNo === '' || $employeeNo === '0') {
            return ['found' => false, 'id' => null, 'sql' => '', 'params' => [], 'reason' => 'empty employeeNo'];
        }

        $sql = 'SELECT id, employee_code, first_name, last_name, hikvision_user_id FROM ' . self::EMPLOYEE_TABLE . ' WHERE hikvision_user_id = :hid AND status = :status LIMIT 1';
        $params = ['hid' => $employeeNo, 'status' => 'active'];

        $this->log('resolveEmployeeByIdempNo', [
            'employeeNo' => $employeeNo,
            'sql' => $sql,
            'params' => $params,
        ]);

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $found = $result !== false;
            $employeeId = $found ? (int)$result['id'] : null;

            $this->log('resolveEmployeeByIdempNo RESULT', [
                'employeeNo' => $employeeNo,
                'employeeId' => $employeeId,
                'sql' => $sql,
                'dbResult' => $found ? $result : null,
            ]);

            return ['found' => $found, 'id' => $employeeId, 'sql' => $sql, 'params' => $params, 'dbResult' => $result];
        } catch (\Throwable $e) {
            $this->logException('resolveEmployeeByIdempNo EXCEPTION', $e, [
                'employeeNo' => $employeeNo,
                'sql' => $sql,
                'params' => $params,
            ]);
            Logger::exception($e, 'resolveEmployeeByIdempNo failed');
            return ['found' => false, 'id' => null, 'sql' => $sql, 'params' => $params, 'error' => $e->getMessage()];
        }
    }

    private function resolveEmployeeByName(string $employeeName): array
    {
        $sql = 'SELECT id, employee_code, first_name, last_name, badge_code FROM ' . self::EMPLOYEE_TABLE . ' WHERE first_name = :first AND last_name = :last AND status = :status LIMIT 1';
        $nameParts = explode(' ', trim($employeeName), 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';
        $params = [
            'first' => $firstName,
            'last' => $lastName,
            'status' => 'active',
        ];

        $this->log('resolveEmployeeByName', [
            'employeeName' => $employeeName,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'sql' => $sql,
            'params' => $params,
        ]);

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $found = $result !== false;
            $employeeId = $found ? (int)$result['id'] : null;

            $this->log('resolveEmployeeByName RESULT', [
                'employeeName' => $employeeName,
                'employeeId' => $employeeId,
                'sql' => $sql,
                'dbResult' => $found ? $result : null,
            ]);

            return ['found' => $found, 'id' => $employeeId, 'sql' => $sql, 'params' => $params, 'dbResult' => $result];
        } catch (\Throwable $e) {
            $this->logException('resolveEmployeeByName EXCEPTION', $e, [
                'employeeName' => $employeeName,
                'sql' => $sql,
                'params' => $params,
            ]);
            Logger::exception($e, 'resolveEmployeeByName failed');
            return ['found' => false, 'id' => null, 'sql' => $sql, 'params' => $params, 'error' => $e->getMessage()];
        }
    }

    private function resolveAttendanceType(string $eventType): string
    {
        $map = [
            'entry' => 'check_in',
            'exit' => 'check_out',
            'break_start' => 'break_start',
            'break_end' => 'break_end',
            'manual' => 'manual',
        ];

        $resolved = $map[$eventType] ?? 'check_in';

        $this->log('resolveAttendanceType', [
            'eventType' => $eventType,
            'resolved' => $resolved,
        ]);

        return $resolved;
    }

    private function log(string $step, array $context = []): void
    {
        try {
            $logDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }

            $timestamp = (new \DateTime())->format('Y-m-d H:i:s.u');
            $lines = [];
            $lines[] = "[{$timestamp}] [{$step}]";

            foreach ($context as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $lines[] = "  {$key}: " . json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                } elseif (is_string($value) && strlen($value) > 2000) {
                    $lines[] = "  {$key}: " . substr($value, 0, 2000) . "... [truncated]";
                } else {
                    $lines[] = "  {$key}: " . var_export($value, true);
                }
            }

            $lines[] = "";

            @file_put_contents($logDir . DIRECTORY_SEPARATOR . self::DEBUG_LOG, implode(PHP_EOL, $lines), FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // ignore logging errors
        }
    }

    private function logException(string $step, \Throwable $e, array $context = []): void
    {
        $exceptionContext = array_merge($context, [
            'exception_class' => get_class($e),
            'exception_message' => $e->getMessage(),
            'exception_file' => $e->getFile(),
            'exception_line' => $e->getLine(),
            'exception_trace' => $e->getTraceAsString(),
        ]);

        $this->log($step, $exceptionContext);
    }
}
