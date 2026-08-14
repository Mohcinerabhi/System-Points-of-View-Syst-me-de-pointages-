<?php

declare(strict_types=1);

namespace Attendance\Services;

use Exception;
use PDO;
use PDOException;

/**
 * ISUP Server for Hikvision terminals.
 *
 * Listens for incoming TCP connections from Hikvision terminals
 * and handles registration, heartbeats, attendance events, and user sync.
 *
 * This server is designed to run as a CLI daemon process.
 *
 * @package Attendance\Services
 */
class ISUPServer
{
    /** @var string Server host */
    private string $host;

    /** @var int Server port */
    private int $port;

    /** @var PDO Database connection */
    private PDO $db;

    /** @var bool Server running state */
    private bool $running = false;

    /** @var resource|null Server socket */
    private $serverSocket = null;

    /** @var array Active connections */
    private array $connections = [];

    /** @var Logger|null */
    private ?\App\Helpers\Logger $logger = null;

    /** @var int Message sequence counter */
    private int $sequence = 0;

    /** @var string Database table name for terminals */
    private const TABLE = 'terminals';

    /** @var string Database table name for attendance logs */
    private const ATTENDANCE_TABLE = 'attendance_logs';

    /** @var string Database table name for sync logs */
    private const SYNC_LOG_TABLE = 'terminal_sync_logs';

    /**
     * ISUPServer constructor.
     */
    public function __construct(string $host = '0.0.0.0', int $port = 8000, ?PDO $db = null)
    {
        $this->host = $host;
        $this->port = max(1, $port);
        $this->db = $db ?? \App\Core\Database::connection();
        $this->logger = new \App\Helpers\Logger();
    }

    /**
     * Starts the ISUP server daemon.
     */
    public function start(): void
    {
        $this->serverSocket = @stream_socket_server("tcp://{$this->host}:{$this->port}", $errno, $errstr);

        if ($this->serverSocket === false) {
            $this->logger->error("ISUP server failed to start", [
                'host' => $this->host,
                'port' => $this->port,
                'error' => $errstr,
                'code' => $errno,
            ]);
            exit(1);
        }

        stream_set_blocking($this->serverSocket, true);
        $this->running = true;

        $this->logger->info("ISUP server started", [
            'host' => $this->host,
            'port' => $this->port,
        ]);

        $this->acceptLoop();
    }

    /**
     * Main accept loop for incoming connections.
     */
    private function acceptLoop(): void
    {
        while ($this->running) {
            $read = array_merge([$this->serverSocket], array_keys($this->connections));
            $write = null;
            $except = null;

            if (stream_select($read, $write, $except, 5) > 0) {
                foreach ($read as $socket) {
                    if ($socket === $this->serverSocket) {
                        $this->acceptConnection();
                    } else {
                        $this->handleClient($socket);
                    }
                }
            }

            $this->cleanupDeadConnections();
        }
    }

    /**
     * Accepts a new terminal connection.
     */
    private function acceptConnection(): void
    {
        $client = @stream_socket_accept($this->serverSocket, 5);

        if ($client === false) {
            return;
        }

        stream_set_blocking($client, false);
        stream_set_timeout($client, 10);

        $this->connections[(int) $client] = [
            'socket' => $client,
            'buffer' => '',
            'deviceId' => null,
            'lastHeartbeat' => time(),
            ' authenticated' => false,
        ];

        $this->logger->info("ISUP client connected", [
            'remote_addr' => stream_socket_get_name($client, true),
        ]);
    }

    /**
     * Handles data from a connected terminal.
     */
    private function handleClient($client): void
    {
        $clientId = (int) $client;
        $connection = $this->connections[$clientId] ?? null;

        if ($connection === null) {
            return;
        }

        $data = fread($client, 4096);

        if ($data === false || $data === '') {
            $this->closeConnection($clientId);
            return;
        }

        $connection['buffer'] .= $data;

        $messages = $this->extractMessages($connection['buffer']);
        $connection['buffer'] = $messages['remaining'];

        foreach ($messages['messages'] as $message) {
            $this->processMessage($client, $message);
        }

        $this->connections[$clientId] = $connection;
    }

    /**
     * Extracts complete ISUP messages from buffer.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: string}
     */
    private function extractMessages(string $buffer): array
    {
        $messages = [];
        $remaining = $buffer;

        while (strlen($remaining) >= ISUPProtocol::HEADER_SIZE) {
            $header = substr($remaining, 0, ISUPProtocol::HEADER_SIZE);
            $bodyLength = unpack('N', $header)[1] ?? 0;

            if ($bodyLength <= 0 || $bodyLength > 65535) {
                break;
            }

            $totalLength = ISUPProtocol::HEADER_SIZE + $bodyLength;
            if (strlen($remaining) < $totalLength) {
                break;
            }

            $rawMessage = substr($remaining, 0, $totalLength);
            $decoded = ISUPProtocol::decode($rawMessage);

            if ($decoded !== null) {
                $messages[] = $decoded;
            }

            $remaining = substr($remaining, $totalLength);
        }

        return [$messages, $remaining];
    }

    /**
     * Processes a decoded ISUP message.
     */
    private function processMessage($client, array $message): void
    {
        $msgType = (int) ($message['msgType'] ?? 0);
        $clientId = (int) $client;
        $connection = $this->connections[$clientId] ?? null;

        try {
            switch ($msgType) {
                case 0x01:
                    $this->handleRegistration($client, $message);
                    if ($connection) {
                        $connection['authenticated'] = true;
                        $this->connections[$clientId] = $connection;
                    }
                    break;
                case 0x02:
                    $this->handleHeartbeat($client, $message);
                    if ($connection) {
                        $connection['lastHeartbeat'] = time();
                        $this->connections[$clientId] = $connection;
                    }
                    break;
                case 0x03:
                    $this->handleAccessEvent($client, $message);
                    break;
                case 0x04:
                    $this->handleUserSync($client, $message);
                    break;
                case 0x05:
                    $this->handleTimeSync($client, $message);
                    break;
                default:
                    $this->logger->warning("ISUP unknown message type", [
                        'msgType' => $msgType,
                    ]);
            }
        } catch (Exception $e) {
            $this->logger->error("ISUP message processing failed", [
                'msgType' => $msgType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handles terminal registration.
     */
    private function handleRegistration($client, array $message): void
    {
        $reg = ISUPProtocol::parseRegistration($message);
        $deviceId = $reg['deviceId'];

        $this->logger->info("ISUP registration received", [
            'deviceId' => $deviceId,
            'model' => $reg['model'],
            'serialNumber' => $reg['serialNumber'],
            'ipAddress' => $reg['ipAddress'],
        ]);

        if ($deviceId === '') {
            $response = ISUPProtocol::encodeRegistrationResponse(false, '', 'Missing device ID');
            fwrite($client, $response);
            return;
        }

        try {
            $stmt = $this->db->prepare('SELECT id FROM ' . self::TABLE . ' WHERE serial_number = :serial LIMIT 1');
            $stmt->execute(['serial' => $deviceId]);
            $terminal = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($terminal) {
                $this->db->prepare('UPDATE ' . self::TABLE . ' SET connection_status = :status, last_connection_test = NOW() WHERE id = :id')
                    ->execute([
                        'status' => 'online',
                        'id' => $terminal['id'],
                    ]);
            }

            $response = ISUPProtocol::encodeRegistrationResponse(true, $deviceId, 'Registered');
            fwrite($client, $response);

            $clientId = (int) $client;
            if (isset($this->connections[$clientId])) {
                $this->connections[$clientId]['deviceId'] = $deviceId;
            }
        } catch (PDOException $e) {
            $this->logger->error("ISUP registration DB error", [
                'error' => $e->getMessage(),
            ]);
            $response = ISUPProtocol::encodeRegistrationResponse(false, '', 'Server error');
            fwrite($client, $response);
        }
    }

    /**
     * Handles terminal heartbeat.
     */
    private function handleHeartbeat($client, array $message): void
    {
        $hb = ISUPProtocol::parseHeartbeat($message);
        $clientId = (int) $client;
        $connection = $this->connections[$clientId] ?? null;
        $deviceId = $connection['deviceId'] ?? ($hb['deviceId'] ?? '');

        $this->logger->debug("ISUP heartbeat received", [
            'deviceId' => $deviceId,
            'status' => $hb['status'],
        ]);

        if ($deviceId !== '') {
            try {
                $stmt = $this->db->prepare('UPDATE ' . self::TABLE . ' SET last_connection_test = NOW() WHERE serial_number = :serial');
                $stmt->execute(['serial' => $deviceId]);
            } catch (PDOException $e) {
                $this->logger->error("ISUP heartbeat DB error", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $response = ISUPProtocol::encodeHeartbeatAck(true);
        fwrite($client, $response);
    }

    /**
     * Handles access control events from terminal.
     */
    private function handleAccessEvent($client, array $message): void
    {
        $event = ISUPProtocol::parseAccessEvent($message);
        $clientId = (int) $client;
        $connection = $this->connections[$clientId] ?? null;
        $deviceId = $connection['deviceId'] ?? ($event['deviceId'] ?? '');

        $this->logger->info("ISUP access event received", [
            'deviceId' => $deviceId,
            'employeeNo' => $event['employeeNo'],
            'eventType' => $event['eventType'],
            'dateTime' => $event['dateTime'],
        ]);

        $response = ISUPProtocol::encodeEventAck(true);
        fwrite($client, $response);
    }

    /**
     * Handles user synchronization from terminal.
     */
    private function handleUserSync($client, array $message): void
    {
        $user = ISUPProtocol::parseUserSync($message);
        $clientId = (int) $client;
        $connection = $this->connections[$clientId] ?? null;
        $deviceId = $connection['deviceId'] ?? '';

        $this->logger->info("ISUP user sync received", [
            'deviceId' => $deviceId,
            'userId' => $user['userId'],
            'name' => $user['name'],
        ]);

        $response = ISUPProtocol::encodeUserSyncResponse(true, 'OK');
        fwrite($client, $response);
    }

    /**
     * Handles time synchronization from terminal.
     */
    private function handleTimeSync($client, array $message): void
    {
        $this->logger->debug("ISUP time sync received");

        $response = ISUPProtocol::encode(0x85, [
            'success' => true,
            'serverTime' => time(),
        ]);
        fwrite($client, $response);
    }

    /**
     * Closes a client connection.
     */
    private function closeConnection(int $clientId): void
    {
        $connection = $this->connections[$clientId] ?? null;

        if ($connection && isset($connection['socket'])) {
            fclose($connection['socket']);
            $this->logger->info("ISUP client disconnected", [
                'deviceId' => $connection['deviceId'] ?? 'unknown',
            ]);
        }

        unset($this->connections[$clientId]);
    }

    /**
     * Removes dead connections from the pool.
     */
    private function cleanupDeadConnections(): void
    {
        $now = time();

        foreach ($this->connections as $clientId => $connection) {
            if ($now - ($connection['lastHeartbeat'] ?? $now) > 300) {
                $this->closeConnection($clientId);
            }
        }
    }

    /**
     * Stops the server.
     */
    public function stop(): void
    {
        $this->running = false;

        foreach ($this->connections as $clientId => $connection) {
            $this->closeConnection($clientId);
        }

        if ($this->serverSocket !== null) {
            fclose($this->serverSocket);
        }

        $this->logger->info("ISUP server stopped");
    }

    /**
     * Returns server status information.
     *
     * @return array<string, mixed>
     */
    public function getStatus(): array
    {
        return [
            'running' => $this->running,
            'host' => $this->host,
            'port' => $this->port,
            'active_connections' => count($this->connections),
            'connections' => array_map(function ($conn) {
                return [
                    'deviceId' => $conn['deviceId'],
                    'lastHeartbeat' => $conn['lastHeartbeat'],
                    'authenticated' => $conn['authenticated'] ?? false,
                ];
            }, $this->connections),
        ];
    }
}
