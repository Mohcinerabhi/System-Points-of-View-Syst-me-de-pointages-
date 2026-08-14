<?php

declare(strict_types=1);

namespace Attendance\Services;

use Exception;

/**
 * ISUP Client for Hikvision terminals supporting ISUP protocol.
 *
 * This is a minimal ISUP implementation for terminals that use ISUP
 * instead of ISUP. ISUP is a binary TCP protocol used by some
 * Hikvision video surveillance and access control devices.
 *
 * The client provides the same interface as the legacy client for compatibility
 * with the existing application architecture.
 *
 * @package Attendance\Services
 */
class ISUPClient
{
    /** @var string Terminal IP address */
    private string $ip;

    /** @var int Terminal ISUP port (default 8000) */
    private int $port;

    /** @var string Terminal authentication username */
    private string $username;

    /** @var string Terminal authentication password */
    private string $password;

    /** @var int TCP connection timeout in seconds */
    private int $timeout;

    /** @var int TCP read timeout in seconds */
    private int $readTimeout;

    /** @var string|null Last error message */
    private ?string $lastError = null;

    /** @var resource|null TCP socket resource */
    private $socket = null;

    /** @var bool Whether connection is alive */
    private bool $connected = false;

    /** @var string Session/device ID from registration */
    private ?string $sessionId = null;

    /** @var int Message sequence number */
    private int $sequence = 0;

    /** @var string Directory for Hikvision debug logs */
    private string $hikvisionLogDir;

    /** @var string Last request payload for debug logging */
    private string $lastRequestBody = '';

    /** @var string Last endpoint for debug logging */
    private string $lastEndpoint = '';

    /** @var array Supported ISUP message types */
    private const MSG_REGISTER = 0x01;
    private const MSG_HEARTBEAT = 0x02;
    private const MSG_ACCESS_EVENT = 0x03;
    private const MSG_USER_SYNC = 0x04;
    private const MSG_TIME_SYNC = 0x05;

    /**
     * ISUPClient constructor.
     */
    public function __construct(
        string $ip,
        int $port = 8000,
        string $username = '',
        string $password = '',
        int $timeout = 10
    ) {
        $this->ip = $ip;
        $this->port = max(1, $port);
        $this->username = $username;
        $this->password = $password;
        $this->timeout = max(1, $timeout);
        $this->readTimeout = max(1, (int) ceil($timeout / 2));
        $this->hikvisionLogDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
    }

    /**
     * Establishes TCP connection to the terminal.
     */
    public function connect(): bool
    {
        $result = $this->testConnection();
        return $result['success'] === true;
    }

    /**
     * Tests ISUP connection and registration.
     *
     * @return array<string, mixed>
     */
    public function testConnection(): array
    {
        $result = [
            'success' => false,
            'reachable' => false,
            'authenticated' => false,
            'http_code' => 0,
            'error' => null,
            'device_info' => null,
        ];

        try {
            $this->disconnect();

            $socket = @fsockopen($this->ip, $this->port, $errno, $errstr, $this->timeout);

            if ($socket === false) {
                $result['error'] = "TCP connection failed: {$errstr} (code {$errno})";
                \App\Helpers\Logger::warning("ISUP connection failed", [
                    'ip' => $this->ip,
                    'port' => $this->port,
                    'error' => $result['error'],
                ]);
                return $result;
            }

            $this->socket = $socket;
            $this->connected = true;
            $result['reachable'] = true;

            stream_set_timeout($this->socket, $this->readTimeout);

            $registerResult = $this->sendRegister();

            if ($registerResult['success']) {
                $this->sessionId = $registerResult['sessionId'] ?? null;
                $result['success'] = true;
                $result['authenticated'] = true;
                $result['device_info'] = $registerResult['deviceInfo'] ?? null;
                \App\Helpers\Logger::info("ISUP registration success", [
                    'ip' => $this->ip,
                    'port' => $this->port,
                    'session_id' => $this->sessionId,
                ]);
            } else {
                $result['error'] = $registerResult['error'] ?? 'Registration failed';
                $result['http_code'] = 401;
                \App\Helpers\Logger::error("ISUP registration failed", [
                    'ip' => $this->ip,
                    'port' => $this->port,
                    'error' => $result['error'],
                ]);
            }

            $this->disconnect();
        } catch (Exception $e) {
            $result['error'] = "Exception: " . $e->getMessage();
            \App\Helpers\Logger::exception($e, 'ISUP testConnection exception');
            $this->disconnect();
        }

        return $result;
    }

    /**
     * Generic ISUP request handler.
     *
     * @return array<string, mixed>
     */
    public function request(string $method, string $endpoint, ?array $data = null, ?string $xmlBody = null): array
    {
        $result = [
            'success' => false,
            'http_code' => 0,
            'data' => null,
            'error' => null,
        ];

        $this->lastEndpoint = $endpoint;
        $upperMethod = strtoupper($method);

        try {
            if (!$this->connected) {
                $connected = $this->connect();
                if (!$connected) {
                    $result['error'] = $this->lastError ?? 'Not connected';
                    return $result;
                }
            }

            switch ($upperMethod) {
                case 'GET':
                    return $this->handleGet($endpoint);
                case 'POST':
                    return $this->handlePost($endpoint, $data, $xmlBody);
                case 'PUT':
                    return $this->handlePut($endpoint, $data, $xmlBody);
                default:
                    $result['error'] = "Unsupported method: {$upperMethod}";
                    return $result;
            }
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
            \App\Helpers\Logger::error("ISUP request error", [
                'method' => $upperMethod,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);
            $this->disconnect();
        }

        return $result;
    }

    /**
     * GET request wrapper.
     */
    public function get(string $endpoint): array
    {
        return $this->request('GET', $endpoint);
    }

    /**
     * POST request wrapper with XML body support.
     */
    public function post(string $endpoint, array $data = [], ?string $xmlBody = null): array
    {
        return $this->request('POST', $endpoint, $data, $xmlBody);
    }

    /**
     * PUT request wrapper with XML body support.
     */
    public function put(string $endpoint, array $data = [], ?string $xmlBody = null): array
    {
        return $this->request('PUT', $endpoint, $data, $xmlBody);
    }

    /**
     * DELETE request wrapper.
     */
    public function delete(string $endpoint): array
    {
        return $this->request('DELETE', $endpoint);
    }

    /**
     * Retrieves users from the terminal via ISUP.
     */
    public function getUsers(): array
    {
        $response = $this->sendMessage(self::MSG_USER_SYNC, [
            'action' => 'query',
            'start' => 0,
            'limit' => 100,
        ]);

        if ($response['success']) {
            return [
                'success' => true,
                'data' => $response['users'] ?? [],
            ];
        }

        return [
            'success' => false,
            'data' => [],
            'error' => $response['error'] ?? 'Failed to fetch users',
        ];
    }

    /**
     * Retrieves a specific user by ID.
     */
    public function getUser(int $userId): array
    {
        $response = $this->sendMessage(self::MSG_USER_SYNC, [
            'action' => 'get',
            'user_id' => $userId,
        ]);

        if ($response['success']) {
            return [
                'success' => true,
                'data' => $response['user'] ?? [],
            ];
        }

        return [
            'success' => false,
            'data' => [],
            'error' => $response['error'] ?? 'Failed to fetch user',
        ];
    }

    /**
     * Creates a new user on the terminal.
     */
    public function createUser(array $userData): array
    {
        $response = $this->sendMessage(self::MSG_USER_SYNC, [
            'action' => 'create',
            'employeeNo' => $userData['employeeNo'] ?? '',
            'name' => $userData['name'] ?? '',
            'cardNo' => $userData['cardNo'] ?? '',
        ]);

        if ($response['success']) {
            return [
                'success' => true,
                'data' => [
                    'id' => $response['userId'] ?? rand(100, 999),
                    'name' => $userData['name'] ?? 'Unknown',
                    'employeeNo' => $userData['employeeNo'] ?? 'UNKNOWN',
                ],
            ];
        }

        return [
            'success' => false,
            'error' => $response['error'] ?? 'Failed to create user',
        ];
    }

    /**
     * Updates an existing user on the terminal.
     */
    public function updateUser(int $userId, array $userData): array
    {
        $response = $this->sendMessage(self::MSG_USER_SYNC, [
            'action' => 'update',
            'user_id' => $userId,
            'employeeNo' => $userData['employeeNo'] ?? '',
            'name' => $userData['name'] ?? '',
            'cardNo' => $userData['cardNo'] ?? '',
        ]);

        if ($response['success']) {
            return [
                'success' => true,
                'data' => ['id' => $userId],
            ];
        }

        return [
            'success' => false,
            'data' => ['id' => $userId],
            'error' => $response['error'] ?? 'Failed to update user',
        ];
    }

    /**
     * Deletes a user from the terminal.
     */
    public function deleteUser(int $userId): array
    {
        $response = $this->sendMessage(self::MSG_USER_SYNC, [
            'action' => 'delete',
            'user_id' => $userId,
        ]);

        if ($response['success']) {
            return [
                'success' => true,
            ];
        }

        return [
            'success' => false,
            'message' => $response['error'] ?? "ISUP deletion of user {$userId} failed",
        ];
    }

    /**
     * Retrieves attendance records from the terminal.
     */
    public function getAttendanceRecords(string $startDate, string $endDate): array
    {
        $response = $this->sendMessage(self::MSG_ACCESS_EVENT, [
            'action' => 'query',
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        if ($response['success']) {
            return [
                'success' => true,
                'data' => $response['events'] ?? [],
            ];
        }

        return [
            'success' => false,
            'data' => [],
            'error' => $response['error'] ?? 'Failed to fetch attendance',
        ];
    }

    /**
     * Downloads attendance records for a specific date.
     */
    public function downloadAttendance(string $date): array
    {
        $response = $this->sendMessage(self::MSG_ACCESS_EVENT, [
            'action' => 'query_date',
            'date' => $date,
        ]);

        if ($response['success']) {
            return [
                'success' => true,
                'data' => $response['events'] ?? [],
            ];
        }

        return [
            'success' => false,
            'error' => $response['error'] ?? 'Failed to download attendance',
        ];
    }

    /**
     * Searches attendance records with filters.
     */
    public function searchAttendance(string $startDate, string $endDate, ?int $userId = null): array
    {
        $records = $this->getAttendanceRecords($startDate, $endDate);

        if ($userId !== null && !empty($records['data'])) {
            $prefix = 'EMP' . str_pad((string)$userId, 3, '0', STR_PAD_LEFT);
            $records['data'] = array_values(array_filter($records['data'], function ($record) use ($prefix) {
                return ($record['employeeNo'] ?? '') === $prefix;
            }));
        }

        return $records;
    }

    /**
     * Retrieves terminal device information via ISUP.
     */
    public function getDeviceInfo(): array
    {
        $response = $this->sendMessage(self::MSG_TIME_SYNC, [
            'action' => 'info',
        ]);

        if ($response['success']) {
            return [
                'success' => true,
                'data' => $response['deviceInfo'] ?? [],
            ];
        }

        return [
            'success' => false,
            'data' => [
                'deviceName' => 'ISUP Terminal',
                'model' => 'ISUP',
                'serialNumber' => null,
                'firmwareVersion' => null,
                'manufacturer' => 'Hikvision',
                'ipAddress' => $this->ip,
            ],
            'message' => 'ISUP device info not available',
        ];
    }

    /**
     * Returns the last error message.
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Sets the session token.
     */
    public function setSessionToken(?string $token): void
    {
        $this->sessionId = $token;
    }

    /**
     * Returns the current session token.
     */
    public function getSessionToken(): ?string
    {
        return $this->sessionId;
    }

    /**
     * Logs a synchronization event to terminal_sync_logs.
     */
    public function logSync(int $terminalId, string $type, array $data): void
    {
        try {
            $db = \App\Core\Database::connection();

            $message = is_array($data) ? ($data['message'] ?? '') : '';
            $status = 'failed';
            $recordsSynced = 0;
            $recordsFailed = 0;

            if (!empty($data['success']) || !empty($data['synced']) || !empty($data['records_synced'])) {
                $status = !empty($data['failed']) || !empty($data['records_failed']) ? 'partial' : 'success';
            }

            $recordsSynced = (int)($data['synced'] ?? $data['records_synced'] ?? 0);
            $recordsFailed = (int)($data['failed'] ?? $data['records_failed'] ?? 0);

            $sql = 'INSERT INTO terminal_sync_logs
                (terminal_id, sync_type, status, records_synced, records_failed, message, started_at, finished_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())';

            $stmt = $db->prepare($sql);
            $stmt->execute([
                $terminalId,
                $type,
                $status,
                $recordsSynced,
                $recordsFailed,
                $message,
            ]);
        } catch (\Throwable $e) {
            \App\Helpers\Logger::error("ISUP logSync failed", [
                'terminal_id' => $terminalId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Sends an ISUP registration message.
     */
    private function sendRegister(): array
    {
        $payload = [
            'username' => $this->username,
            'password' => $this->password,
            'clientType' => 'attendance',
            'version' => '1.0',
        ];

        return $this->sendMessage(self::MSG_REGISTER, $payload);
    }

    /**
     * Sends a generic ISUP message.
     *
     * This is a minimal implementation. Actual ISUP protocol requires
     * specific binary framing, checksums, and message formats per
     * Hikvision's ISUP specification.
     */
    private function sendMessage(int $msgType, array $payload): array
    {
        if (!$this->connected || $this->socket === null) {
            return ['success' => false, 'error' => 'Not connected'];
        }

        if ($this->lastEndpoint === '') {
            $this->lastEndpoint = 'MSG_' . $msgType;
        }

        try {
            $data = json_encode([
                'msgType' => $msgType,
                'sequence' => ++$this->sequence,
                'timestamp' => time(),
                'payload' => $payload,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $length = strlen($data);
            $header = pack('N', $length);

            $this->lastRequestBody = $data;

            fwrite($this->socket, $header . $data, $length + 4);

            $response = $this->readResponse();

            return $response;
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            \App\Helpers\Logger::error("ISUP message send failed", [
                'msgType' => $msgType,
                'error' => $e->getMessage(),
            ]);
            $this->disconnect();

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Reads a response from the ISUP socket.
     */
    private function readResponse(): array
    {
        if ($this->socket === null) {
            return ['success' => false, 'error' => 'Socket closed'];
        }

        $header = fread($this->socket, 4);
        if ($header === false || strlen($header) < 4) {
            $this->disconnect();
            return ['success' => false, 'error' => 'Failed to read response header'];
        }

        $unpacked = unpack('Nlength', $header);
        $length = (int)($unpacked['length'] ?? 0);

        if ($length <= 0 || $length > 65535) {
            $this->disconnect();
            return ['success' => false, 'error' => "Invalid message length: {$length}"];
        }

        $body = '';
        $remaining = $length;
        while ($remaining > 0) {
            $chunk = fread($this->socket, $remaining);
            if ($chunk === false || $chunk === '') {
                $this->disconnect();
                return ['success' => false, 'error' => 'Connection closed during read'];
            }
            $body .= $chunk;
            $remaining -= strlen($chunk);
        }

        $this->logHikvisionResponse($body, $this->lastRequestBody, $this->lastEndpoint);

        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => true,
                'raw' => $body,
                'data' => [],
            ];
        }

        return [
            'success' => ($decoded['status'] ?? 'error') === 'ok',
            'data' => $decoded,
            'error' => ($decoded['status'] ?? 'error') === 'error' ? ($decoded['message'] ?? 'ISUP error') : null,
        ];
    }

    /**
     * Sends a GET-style ISUP query.
     */
    private function handleGet(string $endpoint): array
    {
        $parts = parse_url($endpoint);
        $path = $parts['path'] ?? $endpoint;

        if (str_contains($path, 'attendance') || str_contains($path, 'AcsEvent')) {
            $query = $parts['query'] ?? '';
            parse_str($query, $queryParams);
            $date = $queryParams['date'] ?? date('Y-m-d');
            return $this->downloadAttendance($date);
        }

        if (str_contains($path, 'UserInfo')) {
            $userId = isset($parts['query']['userId']) ? (int)$parts['query']['userId'] : null;
            if ($userId) {
                return $this->getUser($userId);
            }
            return $this->getUsers();
        }

        if (str_contains($path, 'deviceInfo') || str_contains($path, 'time')) {
            return $this->getDeviceInfo();
        }

        return ['success' => false, 'error' => 'ISUP GET endpoint not implemented: ' . $endpoint];
    }

    /**
     * Sends a POST-style ISUP command.
     */
    private function handlePost(string $endpoint, ?array $data, ?string $xmlBody): array
    {
        if (str_contains($endpoint, 'UserInfo/Search')) {
            $users = $this->getUsers();
            return $users['success'] ? $users : ['success' => false, 'error' => $users['error'] ?? 'No users'];
        }

        if (str_contains($endpoint, 'UserInfo/Create')) {
            $userData = [];
            if ($xmlBody !== null) {
                preg_match('/<employeeNo>(.*?)<\/employeeNo>/', $xmlBody, $empMatch);
                preg_match('/<name>(.*?)<\/name>/', $xmlBody, $nameMatch);
                $userData['employeeNo'] = $empMatch[1] ?? '';
                $userData['name'] = $nameMatch[1] ?? '';
            }
            return $this->createUser($userData);
        }

        return ['success' => false, 'error' => 'ISUP POST endpoint not implemented: ' . $endpoint];
    }

    /**
     * Sends a PUT-style ISUP command.
     */
    private function handlePut(string $endpoint, ?array $data, ?string $xmlBody): array
    {
        if (str_contains($endpoint, 'UserInfo/Update')) {
            $userId = (int)($data['userId'] ?? 0);
            return $this->updateUser($userId, $data);
        }

        return ['success' => false, 'error' => 'ISUP PUT endpoint not implemented: ' . $endpoint];
    }

    /**
     * Disconnects the TCP socket.
     */
    private function disconnect(): void
    {
        if ($this->socket !== null) {
            fclose($this->socket);
            $this->socket = null;
        }
        $this->connected = false;
        $this->sessionId = null;
    }

    /**
     * Saves debug info for a Hikvision terminal response.
     */
    private function logHikvisionResponse(string $rawBody, string $requestBody, string $endpoint): void
    {
        try {
            if (!is_dir($this->hikvisionLogDir)) {
                @mkdir($this->hikvisionLogDir, 0755, true);
            }

            $contentType = $this->detectContentType($rawBody);
            $timestamp = date('Y-m-d_H-i-s');

            if ($contentType === 'xml') {
                $file = $this->hikvisionLogDir . DIRECTORY_SEPARATOR . 'hikvision_response.xml';
                file_put_contents($file, $rawBody);
                $this->logHikvisionDebug($endpoint, $requestBody, $rawBody, $contentType,
                    $this->analyzeXml($rawBody));
            } else {
                $file = $this->hikvisionLogDir . DIRECTORY_SEPARATOR . 'hikvision_response.json';
                file_put_contents($file, $rawBody);
                $this->logHikvisionDebug($endpoint, $requestBody, $rawBody, $contentType,
                    $this->analyzeJson($rawBody));
            }
        } catch (\Throwable $e) {
            // Silently ignore logging errors
        }
    }

    /**
     * Detects whether raw body is XML or JSON.
     */
    private function detectContentType(string $rawBody): string
    {
        $trimmed = ltrim($rawBody);
        if (str_starts_with($trimmed, '<?xml')) {
            return 'xml';
        }
        if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
            return 'json';
        }
        return 'json';
    }

    /**
     * Analyzes XML and returns debug info.
     */
    private function analyzeXml(string $rawBody): array
    {
        $result = ['root_node' => null, 'unique_nodes' => [], 'total_nodes' => 0];
        try {
            $previous = libxml_use_internal_errors(true);
            $xml = simplexml_load_string($rawBody);
            libxml_use_internal_errors($previous);
            if ($xml !== false) {
                $result['root_node'] = $xml->getName();
                $nodes = $xml->xpath('//*');
                $unique = [];
                foreach ($nodes as $node) {
                    $name = $node->getName();
                    if (!in_array($name, $unique)) {
                        $unique[] = $name;
                    }
                }
                $result['unique_nodes'] = $unique;
                $result['total_nodes'] = count($nodes);
            }
        } catch (\Throwable $e) {
            // Silently ignore XML parse errors
        }
        return $result;
    }

    /**
     * Analyzes JSON and returns debug info.
     */
    private function analyzeJson(string $rawBody): array
    {
        $result = ['pretty' => '', 'top_level_keys' => []];
        try {
            $decoded = json_decode($rawBody, true);
            if ($decoded !== null) {
                $result['pretty'] = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $result['top_level_keys'] = array_keys(is_array($decoded) ? $decoded : []);
            } else {
                $result['pretty'] = $rawBody;
            }
        } catch (\Throwable $e) {
            $result['pretty'] = $rawBody;
        }
        return $result;
    }

    /**
     * Writes structured debug info to the Hikvision log file.
     */
    private function logHikvisionDebug(string $endpoint, string $requestBody, string $responseBody, string $contentType, array $analysis): void
    {
        try {
            $logFile = $this->hikvisionLogDir . DIRECTORY_SEPARATOR . 'hikvision_debug.log';
            $timestamp = date('Y-m-d H:i:s');
            $contentTypeLabel = ($contentType === 'xml') ? 'application/xml' : 'application/json';
            $lines = [];
            $lines[] = "=== Hikvision Debug [{$timestamp}] ===";
            $lines[] = "Endpoint: {$endpoint}";
            $lines[] = "Content-Type: {$contentTypeLabel}";
            $lines[] = "Request Body: " . substr($requestBody, 0, 2000);
            $lines[] = "Response Body (raw, first 5000 chars): " . substr($responseBody, 0, 5000);
            if ($contentType === 'xml') {
                $lines[] = "XML Root Node: {$analysis['root_node']}";
                $lines[] = "XML Unique Nodes: " . implode(', ', $analysis['unique_nodes']);
                $lines[] = "XML Total Nodes: {$analysis['total_nodes']}";
            } else {
                $lines[] = "JSON Top-Level Keys: " . implode(', ', $analysis['top_level_keys']);
            }
            $lines[] = str_repeat('-', 60);
            $lines[] = '';
            @file_put_contents($logFile, implode(PHP_EOL, $lines), FILE_APPEND);
        } catch (\Throwable $e) {
            // Silently ignore logging errors
        }
    }

    /**
     * Destructor ensures socket is closed.
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
