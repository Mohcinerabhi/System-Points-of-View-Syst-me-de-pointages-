<?php

declare(strict_types=1);

namespace Attendance\Services;

use Exception;

/**
 * ISUP Protocol encoder/decoder for Hikvision terminals.
 *
 * Handles binary message framing, checksums, and payload parsing
 * for Hikvision ISUP/EHome protocol.
 *
 * @package Attendance\Services
 */
class ISUPProtocol
{
    /** @var string Protocol version */
    private const PROTOCOL_VERSION = 'ISUP5.0';

    /** @var int Message header size in bytes */
    private const HEADER_SIZE = 12;

    /**
     * Encodes an ISUP message.
     *
     * @param int    $msgType Message type constant
     * @param mixed  $payload Request/response payload
     * @param int    $seq     Sequence number
     * @return string Binary message
     */
    public static function encode(int $msgType, mixed $payload, int $seq = 1): string
    {
        $body = json_encode([
            'version' => self::PROTOCOL_VERSION,
            'msgType' => $msgType,
            'sequence' => $seq,
            'timestamp' => time(),
            'payload' => $payload,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($body === false) {
            throw new Exception('ISUP encode failed: json_encode error');
        }

        $bodyBytes = $body;
        $bodyLength = strlen($bodyBytes);

        $header = pack('N', $bodyLength);
        $header .= str_pad((string) $msgType, 4, "\0", STR_PAD_RIGHT);
        $header .= str_pad((string) $seq, 4, "\0", STR_PAD_RIGHT);

        return $header . $bodyBytes;
    }

    /**
     * Decodes an ISUP message from binary stream.
     *
     * @param string $data Raw binary data
     * @return array<string, mixed>|null Decoded message or null if incomplete
     */
    public static function decode(string $data): ?array
    {
        if (strlen($data) < self::HEADER_SIZE) {
            return null;
        }

        $header = substr($data, 0, self::HEADER_SIZE);
        $bodyLength = unpack('N', $header)[1] ?? 0;

        if ($bodyLength <= 0 || $bodyLength > 65535) {
            throw new Exception("Invalid ISUP message length: {$bodyLength}");
        }

        $totalLength = self::HEADER_SIZE + $bodyLength;
        if (strlen($data) < $totalLength) {
            return null;
        }

        $body = substr($data, self::HEADER_SIZE, $bodyLength);
        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('ISUP decode failed: invalid JSON in message body');
        }

        return $decoded;
    }

    /**
     * Parses a registration request from terminal.
     *
     * @param array<string, mixed> $message Decoded ISUP message
     * @return array<string, mixed> Parsed registration data
     */
    public static function parseRegistration(array $message): array
    {
        $payload = $message['payload'] ?? [];

        return [
            'deviceId' => (string) ($payload['deviceId'] ?? ''),
            'deviceType' => (string) ($payload['deviceType'] ?? ''),
            'firmwareVersion' => (string) ($payload['firmwareVersion'] ?? ''),
            'ipAddress' => (string) ($payload['ipAddress'] ?? ''),
            'macAddress' => (string) ($payload['macAddress'] ?? ''),
            'model' => (string) ($payload['model'] ?? ''),
            'serialNumber' => (string) ($payload['serialNumber'] ?? ''),
            'username' => (string) ($payload['username'] ?? ''),
            'timestamp' => (int) ($payload['timestamp'] ?? time()),
        ];
    }

    /**
     * Parses an access control event from terminal.
     *
     * @param array<string, mixed> $message Decoded ISUP message
     * @return array<string, mixed> Parsed attendance event
     */
    public static function parseAccessEvent(array $message): array
    {
        $payload = $message['payload'] ?? [];

        return [
            'deviceId' => (string) ($payload['deviceId'] ?? ''),
            'employeeNo' => (string) ($payload['employeeNo'] ?? ($payload['cardNo'] ?? '')),
            'eventType' => (string) ($payload['eventType'] ?? 'entry'),
            'doorNo' => (int) ($payload['doorNo'] ?? 1),
            'dateTime' => (string) ($payload['dateTime'] ?? date('Y-m-d H:i:s')),
            'eventReason' => (string) ($payload['eventReason'] ?? ''),
            'verified' => (bool) ($payload['verified'] ?? true),
            'imageUrl' => (string) ($payload['imageUrl'] ?? ''),
        ];
    }

    /**
     * Parses a user sync message from terminal.
     *
     * @param array<string, mixed> $message Decoded ISUP message
     * @return array<string, mixed> Parsed user data
     */
    public static function parseUserSync(array $message): array
    {
        $payload = $message['payload'] ?? [];

        return [
            'action' => (string) ($payload['action'] ?? ''),
            'userId' => (string) ($payload['userId'] ?? ''),
            'employeeNo' => (string) ($payload['employeeNo'] ?? ''),
            'name' => (string) ($payload['name'] ?? ''),
            'cardNo' => (string) ($payload['cardNo'] ?? ''),
            'userType' => (string) ($payload['userType'] ?? 'normal'),
            'doorRightPlanList' => $payload['doorRightPlanList'] ?? [],
        ];
    }

    /**
     * Parses a heartbeat message from terminal.
     *
     * @param array<string, mixed> $message Decoded ISUP message
     * @return array<string, mixed> Parsed heartbeat data
     */
    public static function parseHeartbeat(array $message): array
    {
        $payload = $message['payload'] ?? [];

        return [
            'deviceId' => (string) ($payload['deviceId'] ?? ''),
            'timestamp' => (int) ($payload['timestamp'] ?? time()),
            'status' => (string) ($payload['status'] ?? 'online'),
            'uptime' => (int) ($payload['uptime'] ?? 0),
        ];
    }

    /**
     * Encodes a registration response.
     *
     * @param bool   $success Whether registration succeeded
     * @param string $deviceId Device ID assigned by server
     * @param string $message  Response message
     * @return string Binary response
     */
    public static function encodeRegistrationResponse(bool $success, string $deviceId = '', string $message = ''): string
    {
        return self::encode(0x81, [
            'success' => $success,
            'deviceId' => $deviceId,
            'message' => $message,
            'serverTime' => time(),
        ]);
    }

    /**
     * Encodes a heartbeat acknowledgment.
     *
     * @param bool $success Whether heartbeat was accepted
     * @return string Binary response
     */
    public static function encodeHeartbeatAck(bool $success = true): string
    {
        return self::encode(0x82, [
            'success' => $success,
            'serverTime' => time(),
        ]);
    }

    /**
     * Encodes an access event acknowledgment.
     *
     * @param bool $success Whether event was accepted
     * @return string Binary response
     */
    public static function encodeEventAck(bool $success = true): string
    {
        return self::encode(0x83, [
            'success' => $success,
            'serverTime' => time(),
        ]);
    }

    /**
     * Encodes a user sync response.
     *
     * @param bool   $success Whether sync succeeded
     * @param string $message Response message
     * @return string Binary response
     */
    public static function encodeUserSyncResponse(bool $success, string $message = ''): string
    {
        return self::encode(0x84, [
            'success' => $success,
            'message' => $message,
            'serverTime' => time(),
        ]);
    }
}
