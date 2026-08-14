<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Employee;
use Attendance\Services\HikvisionClient;
use Attendance\Services\ISAPIClient;

class HikvisionTerminal
{
    private HikvisionClient $client;
    private ISAPIClient $isapiClient;
    private array $terminal;

    public function __construct(array $terminal)
    {
        $this->terminal = $terminal;

        $this->client = new HikvisionClient(
            (string) ($terminal['ip_address'] ?? ''),
            (int) ($terminal['port'] ?? 80),
            (string) ($terminal['username'] ?? ''),
            (string) ($terminal['plain_password'] ?? ''),
            10,
            !empty($terminal['use_https'])
        );

        $this->isapiClient = new ISAPIClient(
            (string) ($terminal['ip_address'] ?? ''),
            (int) ($terminal['port'] ?? 80),
            (string) ($terminal['username'] ?? ''),
            (string) ($terminal['plain_password'] ?? ''),
            10,
            !empty($terminal['use_https']),
            $this->resolveTimezone($terminal)
        );
    }

    private function resolveTimezone(array $terminal): string
    {
        $tz = $terminal['timezone'] ?? '';
        if (preg_match('/([+-]\d{2}:\d{2})/', $tz, $matches)) {
            return $matches[1];
        }
        return '+00:00';
    }

    public function testConnection(): array
    {
        return $this->client->testConnection();
    }

    public function getDeviceInfo(): array
    {
        return $this->client->getDeviceInfo();
    }

    public function getCapabilities(): array
    {
        return $this->client->getCapabilities();
    }

    public function checkAcsEventSupport(): array
    {
        return $this->client->checkAcsEventSupport();
    }

    public function diagnoseTerminal(): array
    {
        $connection = $this->client->testConnection();
        $deviceInfo = $this->client->getDeviceInfo();
        $capabilities = $this->client->getCapabilities();
        $acsEventSupport = $this->client->checkAcsEventSupport();

        return [
            'connection' => [
                'reachable' => $connection['reachable'] ?? false,
                'authenticated' => $connection['authenticated'] ?? false,
                'http_code' => $connection['http_code'] ?? 0,
                'error' => $connection['error'] ?? null,
            ],
            'device_info' => $deviceInfo['data'] ?? null,
            'capabilities' => $capabilities['data'] ?? null,
            'acs_event_support' => [
                'isSupported' => $acsEventSupport['isSupported'] ?? false,
                'error' => $acsEventSupport['error'] ?? null,
            ],
            'last_http_code' => $this->client->getLastHttpCode(),
            'last_request_url' => $this->client->getLastRequestUrl(),
            'last_request_body' => $this->client->getLastRequestBody(),
            'last_response_body' => $this->client->getLastResponseBody(),
        ];
    }

    public function addUser(array $employee): array
    {
        return $this->client->createUser([
            'employeeNo' => (string) ($employee['employee_code'] ?? ''),
            'name'       => trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? '')),
            'cardNo'     => (string) ($employee['badge_code'] ?? ''),
        ]);
    }

    public function downloadAttendance(string $startDate = '', string $endDate = ''): array
    {
        if ($startDate === '') {
            $startDate = (new \DateTime('now', new \DateTimeZone($this->resolveTimezone($this->terminal))))->modify('-30 days')->format('Y-m-d');
        }
        if ($endDate === '') {
            $endDate = (new \DateTime('now', new \DateTimeZone($this->resolveTimezone($this->terminal))))->format('Y-m-d');
        }
        return $this->isapiClient->searchAttendance($startDate, $endDate);
    }

    public function fetchUsers(): array
    {
        $result = $this->isapiClient->searchUsers();
        return [
            'success' => $result['success'] ?? false,
            'users'   => is_array($result['data'] ?? null) ? $result['data'] : [],
            'error'   => $result['error'] ?? null,
        ];
    }

    public function logSync(int $terminalId, string $type, array $data): void
    {
        $this->client->logSync($terminalId, $type, $data);
    }

    public function getLastHttpCode(): int
    {
        return $this->client->getLastHttpCode();
    }

    public function getLastCurlError(): ?string
    {
        return $this->client->getLastCurlError();
    }

    public function getLastResponseBody(): ?string
    {
        return $this->client->getLastResponseBody();
    }

    public function getIsapiClient(): ISAPIClient
    {
        return $this->isapiClient;
    }
}
