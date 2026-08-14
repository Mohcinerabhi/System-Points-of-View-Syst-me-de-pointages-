<?php

declare(strict_types=1);

namespace Attendance\Services;

use App\Helpers\Logger;

class HikvisionClient
{
    private string $ip;
    private int $port;
    private bool $https;
    private string $username;
    private string $password;
    private int $timeout;
    private ?string $lastError = null;
    private int $lastHttpCode = 0;
    private ?string $lastResponseBody = null;
    private ?string $lastRequestBody = null;
    private ?string $lastRequestUrl = null;
    private ?array $lastDeviceInfo = null;
    private ?array $lastCapabilities = null;
    private string $timezone;

    public function __construct(
        string $ip,
        int $port = 80,
        string $username = '',
        string $password = '',
        int $timeout = 10,
        bool $https = false,
        string $timezone = '+00:00'
    ) {
        $this->ip = $ip;
        $this->port = max(1, $port);
        $this->username = $username;
        $this->password = $password;
        $this->timeout = max(1, $timeout);
        $this->https = $https;
        $this->timezone = $timezone;
    }

    private function baseUrl(): string
    {
        $scheme = $this->https ? 'https' : 'http';
        return "{$scheme}://{$this->ip}:{$this->port}";
    }

    private function initCurl(string $url, array $options = [])
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException("curl_init failed for {$url}");
        }

        $defaults = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => min($this->timeout, 10),
            CURLOPT_HTTPAUTH => CURLAUTH_DIGEST,
            CURLOPT_USERPWD => $this->username . ':' . $this->password,
            CURLOPT_HEADER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => false,
        ];

        curl_setopt_array($ch, $defaults + $options);

        return $ch;
    }

    private function logDebug(string $context, array $data): void
    {
        try {
            Logger::debug("HikvisionClient {$context}", $data);
        } catch (\Throwable $e) {
        }
    }

    public function testConnection(): array
    {
        $result = [
            'success' => false,
            'reachable' => false,
            'authenticated' => false,
            'http_code' => 0,
            'curl_error' => null,
            'error' => null,
            'device_info' => null,
        ];

        $this->lastError = null;
        $this->lastHttpCode = 0;
        $this->lastResponseBody = null;
        $this->lastDeviceInfo = null;

        try {
            $ch = $this->initCurl($this->baseUrl() . '/ISAPI/System/deviceInfo');
        } catch (\RuntimeException $e) {
            $result['error'] = $e->getMessage();
            $result['curl_error'] = $e->getMessage();
            return $result;
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $curlError = curl_error($ch);
            $this->lastError = $curlError;
            curl_close($ch);
            $result['curl_error'] = $curlError;
            $result['error'] = "cURL error: {$curlError}";
            return $result;
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->lastHttpCode = $httpCode;
        $this->lastResponseBody = (string) $response;
        curl_close($ch);

        $result['http_code'] = $this->lastHttpCode;
        $result['response_body'] = $this->lastResponseBody;

        if ($this->lastHttpCode > 0) {
            $result['reachable'] = true;
        }

        if ($this->lastHttpCode === 200) {
            $info = $this->parseDeviceInfo($this->lastResponseBody);
            if ($info !== null) {
                $result['success'] = true;
                $result['authenticated'] = true;
                $result['device_info'] = $info;
                $this->lastDeviceInfo = $info;
                $result['error'] = null;
            } else {
                $result['error'] = $this->lastError ?? 'XML parse failed';
            }
        } elseif ($this->lastHttpCode === 401) {
            $result['error'] = 'Authentication failed (HTTP 401)';
        } elseif ($this->lastHttpCode > 0) {
            $result['error'] = "HTTP error {$this->lastHttpCode}";
        } else {
            $result['error'] = 'Cannot connect to terminal';
        }

        return $result;
    }

    public function getDeviceInfo(): array
    {
        $result = [
            'success' => false,
            'data' => null,
            'error' => null,
        ];

        try {
            $ch = $this->initCurl($this->baseUrl() . '/ISAPI/System/deviceInfo');
        } catch (\RuntimeException $e) {
            $result['error'] = $e->getMessage();
            return $result;
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $result['error'] = 'cURL error: ' . curl_error($ch);
            curl_close($ch);
            return $result;
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && !empty($response)) {
            $data = $this->parseDeviceInfo((string) $response);
            if ($data !== null) {
                $result['success'] = true;
                $result['data'] = $data;
                return $result;
            }
            $result['error'] = $this->lastError ?? 'XML parse failed';
        } elseif ($httpCode === 401) {
            $result['error'] = 'Authentication failed (HTTP 401)';
        } else {
            $result['error'] = "HTTP error {$httpCode}";
        }

        return $result;
    }

    public function getCapabilities(): array
    {
        $result = [
            'success' => false,
            'data' => null,
            'error' => null,
        ];

        try {
            $ch = $this->initCurl($this->baseUrl() . '/ISAPI/System/capabilities');
        } catch (\RuntimeException $e) {
            $result['error'] = $e->getMessage();
            return $result;
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $result['error'] = 'cURL error: ' . curl_error($ch);
            curl_close($ch);
            return $result;
        }

        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $responseBody = (string) $response;

        if ($httpCode === 200 && !empty($responseBody)) {
            $data = $this->parseCapabilities($responseBody);
            if ($data !== null) {
                $result['success'] = true;
                $result['data'] = $data;
                return $result;
            }
            $result['error'] = $this->lastError ?? 'Capabilities parse failed';
        } elseif ($httpCode === 401) {
            $result['error'] = 'Authentication failed (HTTP 401)';
        } else {
            $result['error'] = "HTTP error {$httpCode}";
        }

        return $result;
    }

    public function checkAcsEventSupport(): array
    {
        $capabilities = $this->getCapabilities();

        if (!$capabilities['success']) {
            return [
                'success' => false,
                'isSupported' => false,
                'error' => $capabilities['error'] ?? 'Failed to retrieve capabilities',
            ];
        }

        $data = $capabilities['data'];
        if ($data === null) {
            return [
                'success' => false,
                'isSupported' => false,
                'error' => 'No capabilities data',
            ];
        }

        $isSupported = !empty($data['isSupportAcsEvent'])
            || !empty($data['isSupportAccessControl'])
            || !empty($data['capability']['isSupportAcsEvent'] ?? null);

        return [
            'success' => true,
            'isSupported' => $isSupported,
            'capabilities' => $data,
            'error' => $isSupported ? null : 'Terminal does not support AcsEvent',
        ];
    }

    public function getUsers(): array
    {
        $result = [
            'success' => false,
            'data' => [],
            'error' => null,
        ];

        $postResult = $this->post('/ISAPI/AccessControl/UserInfo/Search', [
            'body' => '<?xml version="1.0" encoding="UTF-8"?>
<UserInfoSearchCond>
    <searchID>1</searchID>
    <maxResults>50</maxResults>
</UserInfoSearchCond>',
            'headers' => ['Content-Type: application/xml'],
        ]);

        if ($postResult['success'] && !empty($postResult['response'])) {
            $users = $this->parseUserList($postResult['response']);
            if ($users !== null) {
                $result['success'] = true;
                $result['data'] = $users;
                return $result;
            }
            $result['error'] = $this->lastError ?? 'XML parse failed';
        } elseif ($postResult['error']) {
            $result['error'] = $postResult['error'];
        } else {
            $result['error'] = $this->formatHttpError($postResult);
        }

        return $result;
    }

    public function getUserCount(): array
    {
        $result = [
            'success' => false,
            'data' => 0,
            'error' => null,
        ];

        $getResult = $this->get('/ISAPI/AccessControl/UserInfo/Count');

        if ($getResult['success'] && !empty($getResult['response'])) {
            $xml = $getResult['response'];
            libxml_use_internal_errors(true);
            $doc = simplexml_load_string($xml);

            if ($doc !== false) {
                $nodes = $doc->xpath('//*[local-name()="count"]');
                if ($nodes !== false && !empty($nodes[0])) {
                    $result['success'] = true;
                    $result['data'] = (int) (string) $nodes[0];
                    return $result;
                }
            }

            $json = json_decode($xml, true);
            if (is_array($json)) {
                $result['success'] = true;
                $result['data'] = (int) ($json['count'] ?? $json['total'] ?? 0);
                return $result;
            }

            $result['error'] = 'Cannot parse user count';
        } elseif ($getResult['error']) {
            $result['error'] = $getResult['error'];
        } else {
            $result['error'] = $this->formatHttpError($getResult);
        }

        return $result;
    }

    public function createUser(array $user): array
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<UserInfoCond>
    <employeeNo>' . htmlspecialchars((string) ($user['employeeNo'] ?? ''), ENT_XML1 | ENT_QUOTES) . '</employeeNo>
    <name>' . htmlspecialchars((string) ($user['name'] ?? ''), ENT_XML1 | ENT_QUOTES) . '</name>
    <cardNo>' . htmlspecialchars((string) ($user['cardNo'] ?? ''), ENT_XML1 | ENT_QUOTES) . '</cardNo>
</UserInfoCond>';

        $postResult = $this->post('/ISAPI/AccessControl/UserInfo/Record', [
            'body' => $xml,
            'headers' => ['Content-Type: application/xml'],
        ]);

        if ($postResult['success']) {
            return [
                'success' => true,
                'data' => ['id' => $user['employeeNo'] ?? ''],
            ];
        }

        return [
            'success' => false,
            'error' => $this->formatHttpError($postResult),
        ];
    }

    public function updateUser(int $userId, array $user): array
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<UserInfoCond>
    <employeeNo>' . htmlspecialchars((string) ($user['employeeNo'] ?? ''), ENT_XML1 | ENT_QUOTES) . '</employeeNo>
    <name>' . htmlspecialchars((string) ($user['name'] ?? ''), ENT_XML1 | ENT_QUOTES) . '</name>
    <cardNo>' . htmlspecialchars((string) ($user['cardNo'] ?? ''), ENT_XML1 | ENT_QUOTES) . '</cardNo>
</UserInfoCond>';

        $postResult = $this->post('/ISAPI/AccessControl/UserInfo/Modify', [
            'body' => $xml,
            'headers' => ['Content-Type: application/xml'],
        ]);

        if ($postResult['success']) {
            return ['success' => true, 'data' => ['id' => $userId]];
        }

        return [
            'success' => false,
            'error' => $this->formatHttpError($postResult),
        ];
    }

    public function deleteUser(int $userId): array
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<UserInfoCond>
    <employeeNo>' . (int) $userId . '</employeeNo>
</UserInfoCond>';

        $postResult = $this->post('/ISAPI/AccessControl/UserInfo/Delete', [
            'body' => $xml,
            'headers' => ['Content-Type: application/xml'],
        ]);

        if ($postResult['success']) {
            return ['success' => true];
        }

        return [
            'success' => false,
            'error' => $this->formatHttpError($postResult),
        ];
    }

    public function searchAttendance(?string $startDate = null, ?string $endDate = null, ?int $userId = null): array
    {
        $isapi = new ISAPIClient(
            $this->ip,
            $this->port,
            $this->username,
            $this->password,
            $this->timeout,
            $this->https,
            $this->timezone
        );

        return $isapi->searchAttendance($startDate, $endDate, $userId);
    }

    public function downloadAttendance(?string $date = null): array
    {
        $isapi = new ISAPIClient(
            $this->ip,
            $this->port,
            $this->username,
            $this->password,
            $this->timeout,
            $this->https,
            $this->timezone
        );

        return $isapi->downloadAttendance($date);
    }

    public function getLastHttpCode(): int
    {
        return $this->lastHttpCode;
    }

    public function getLastCurlError(): ?string
    {
        return $this->lastError;
    }

    public function getLastResponseBody(): ?string
    {
        return $this->lastResponseBody;
    }

    public function getLastRequestBody(): ?string
    {
        return $this->lastRequestBody;
    }

    public function getLastRequestUrl(): ?string
    {
        return $this->lastRequestUrl;
    }

    public function getLastDeviceInfo(): ?array
    {
        return $this->lastDeviceInfo;
    }

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
            \App\Helpers\Logger::error("HikvisionClient logSync failed", [
                'terminal_id' => $terminalId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function get(string $path): array
    {
        $url = $this->baseUrl() . $path;
        $result = [
            'success' => false,
            'http_code' => 0,
            'response' => null,
            'error' => null,
            'request_url' => $url,
            'request_body' => '',
            'request_headers' => [],
        ];

        try {
            $ch = $this->initCurl($url);
        } catch (\RuntimeException $e) {
            $result['error'] = $e->getMessage();
            return $result;
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $result['error'] = 'cURL error: ' . curl_error($ch);
            curl_close($ch);
            return $result;
        }

        $result['http_code'] = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $result['response'] = (string) $response;
        $result['success'] = $result['http_code'] === 200;
        curl_close($ch);

        return $result;
    }

    private function post(string $path, array $data = []): array
    {
        $result = [
            'success' => false,
            'http_code' => 0,
            'response' => null,
            'error' => null,
            'request_url' => '',
            'request_body' => '',
            'request_headers' => [],
        ];

        $url = $this->baseUrl() . $path;
        $result['request_url'] = $url;

        $body = $data['body'] ?? '';
        $result['request_body'] = is_string($body) ? $body : json_encode($body);

        try {
            $ch = $this->initCurl($url, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
            ]);
        } catch (\RuntimeException $e) {
            $result['error'] = $e->getMessage();
            return $result;
        }

        $headers = $data['headers'] ?? [];
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $result['request_headers'] = $headers;
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $result['error'] = 'cURL error: ' . curl_error($ch);
            curl_close($ch);
            return $result;
        }

        $result['http_code'] = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $result['response'] = (string) $response;
        $result['success'] = $result['http_code'] === 200;
        $this->lastResponseBody = (string) $response;
        $this->lastHttpCode = $result['http_code'];
        $this->lastRequestBody = $result['request_body'];
        $this->lastRequestUrl = $url;
        curl_close($ch);

        return $result;
    }

    private function parseDeviceInfo(string $xml): ?array
    {
        if (empty($xml)) {
            $this->lastError = 'Empty XML response';
            return null;
        }

        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);

        if ($doc === false) {
            $errors = libxml_get_errors();
            $this->lastError = 'XML parse error';
            libxml_clear_errors();
            return null;
        }

        $data = [];

        $fields = [
            'model' => 'model',
            'serialNumber' => 'serialNumber',
            'macAddress' => 'macAddress',
            'firmwareVersion' => 'firmwareVersion',
            'deviceName' => 'deviceName',
        ];

        foreach ($fields as $key => $path) {
            $node = $doc->xpath('//*[local-name()="' . $path . '"]');
            $data[$key] = $node !== false && !empty($node[0]) ? (string) $node[0] : null;
        }

        if (empty($data['model']) && empty($data['serialNumber'])) {
            $json = json_decode($xml, true);
            if (is_array($json)) {
                $data['model'] = $json['model'] ?? null;
                $data['serialNumber'] = $json['serialNumber'] ?? null;
                $data['macAddress'] = $json['macAddress'] ?? null;
                $data['firmwareVersion'] = $json['firmwareVersion'] ?? null;
                $data['deviceName'] = $json['deviceName'] ?? null;
            }
        }

        return $data;
    }

    private function parseCapabilities(string $xml): ?array
    {
        if (empty($xml)) {
            $this->lastError = 'Empty XML response';
            return null;
        }

        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);

        if ($doc === false) {
            $this->lastError = 'XML parse error';
            libxml_clear_errors();
            return null;
        }

        $data = [];

        $nodes = $doc->xpath('//*');
        if ($nodes !== false) {
            foreach ($nodes as $node) {
                $name = $node->getName();
                $value = (string) $node;
                if ($value !== '') {
                    $data[$name] = $value;
                }
            }
        }

        return $data;
    }

    private function parseUserList(string $xml): ?array
    {
        if (empty($xml)) {
            $this->lastError = 'Empty XML response';
            return null;
        }

        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);

        if ($doc === false) {
            $this->lastError = 'XML parse error';
            libxml_clear_errors();
            return null;
        }

        $users = [];
        $userNodes = $doc->xpath('//*[local-name()="UserInfo"]');
        if ($userNodes === false) {
            $this->lastError = 'XPath failed';
            libxml_clear_errors();
            return null;
        }

        foreach ($userNodes as $node) {
            $users[] = [
                'employeeNo' => $this->xmlValue($node, 'employeeNo'),
                'name'       => $this->xmlValue($node, 'name'),
                'cardNo'     => $this->xmlValue($node, 'cardNo'),
            ];
        }

        return $users;
    }

    private function xmlValue(\SimpleXMLElement $node, string $field): ?string
    {
        $child = $node->children()->{$field};
        if ($child !== null && (string) $child !== '') {
            return (string) $child;
        }

        $nodes = $node->xpath('//*[local-name()="' . $field . '"]');
        if ($nodes !== false && !empty($nodes[0])) {
            return (string) $nodes[0];
        }

        return null;
    }

    private function formatHttpError(array $postResult): string
    {
        $httpCode = $postResult['http_code'] ?? 0;
        $responseBody = $postResult['response'] ?? '';

        if ($httpCode === 400 && !empty($responseBody)) {
            $decoded = json_decode($responseBody, true);
            if (is_array($decoded)) {
                return $this->formatHikvisionError($decoded);
            }
            if (str_contains($responseBody, 'notSupport')) {
                return "Attendance sync not supported by this model via ISAPI.";
            }
            if (str_contains($responseBody, 'Invalid Operation')) {
                return "Attendance sync not supported by this model via ISAPI.";
            }
            return "HTTP 400: {$responseBody}";
        }

        if ($httpCode === 401) {
            return 'Authentication failed (HTTP 401)';
        }

        if ($httpCode === 404) {
            return "Attendance sync not supported by this model via ISAPI.";
        }

        return "HTTP error {$httpCode}";
    }

    private function formatHikvisionError(array $decoded): string
    {
        $statusCode = $decoded['statusCode'] ?? $decoded['code'] ?? null;
        $statusString = $decoded['statusString'] ?? $decoded['msg'] ?? null;
        $subStatusCode = $decoded['subStatusCode'] ?? null;
        $errorMsg = $decoded['errorMsg'] ?? $decoded['message'] ?? null;

        if ($statusCode !== null || $statusString !== null) {
            $parts = [];
            if ($statusCode !== null) {
                $parts[] = "statusCode={$statusCode}";
            }
            if ($statusString !== null) {
                $parts[] = "statusString={$statusString}";
            }
            if ($subStatusCode !== null) {
                $parts[] = "subStatusCode={$subStatusCode}";
            }
            if ($errorMsg !== null) {
                $parts[] = "errorMsg={$errorMsg}";
            }
            return 'Hikvision error: ' . implode(', ', $parts);
        }

        return 'Unexpected JSON response from terminal';
    }
}
