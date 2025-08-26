<?php
// Allow same-origin requests and set JSON response type
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// Read and decode JSON body
$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON body']);
    exit;
}

$wallet = isset($data['wallet']) ? trim($data['wallet']) : '';

// Basic EVM address validation (0x + 40 hex chars)
$isValidEvm = (bool)preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet);
if (!$isValidEvm) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Invalid wallet address']);
    exit;
}

// Build log entry (CSV format): timestamp, ip, wallet
$timestamp = gmdate('c');
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$logLine = sprintf("%s,%s,%s\n", $timestamp, $ip, $wallet);

// Append to a log file
$logFile = __DIR__ . DIRECTORY_SEPARATOR . 'wallet_log.csv';
$result = @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);

if ($result === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to write log']);
    exit;
}

echo json_encode(['ok' => true]);
exit;
?>


