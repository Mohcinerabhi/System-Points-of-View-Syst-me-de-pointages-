<?php

declare(strict_types=1);

/**
 * ISUP Server Daemon for Hikvision terminals.
 *
 * Run this script from CLI to start the ISUP server:
 * php attendance/isupd.php
 *
 * Or run it in the background:
 * php attendance/isupd.php &
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Attendance\Services\ISUPServer;

$host = '0.0.0.0';
$port = 8000;

for ($i = 1; $i < $argc; $i++) {
    $arg = $argv[$i];
    if (str_starts_with($arg, '--port=')) {
        $port = (int) substr($arg, 7);
    } elseif (str_starts_with($arg, '--host=')) {
        $host = substr($arg, 7);
    }
}

$server = new ISUPServer($host, $port);

echo "ISUP Server starting on {$host}:{$port}\n";
echo "Press Ctrl+C to stop\n\n";

$server->start();
