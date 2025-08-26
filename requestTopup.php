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
$need = isset($data['need']) ? floatval($data['need']) : 0.0; // in BNB

if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $wallet)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Invalid wallet address']);
    exit;
}

// Very basic rate-limit: one request per wallet per 6 hours
$rateDir = __DIR__ . DIRECTORY_SEPARATOR . 'topup_requests';
if (!is_dir($rateDir)) @mkdir($rateDir, 0775, true);
$rateFile = $rateDir . DIRECTORY_SEPARATOR . strtolower($wallet) . '.json';
$now = time();
$cooldownSeconds = 6 * 60 * 60;

if (file_exists($rateFile)) {
    $prev = json_decode(@file_get_contents($rateFile), true);
    $prevTime = isset($prev['ts']) ? intval($prev['ts']) : 0;
    if ($prevTime && ($now - $prevTime) < $cooldownSeconds) {
        $wait = $cooldownSeconds - ($now - $prevTime);
        http_response_code(429);
        echo json_encode(['ok' => false, 'error' => 'Rate limited', 'retry_after_seconds' => $wait]);
        exit;
    }
}

// Log the request for manual processing or a separate funding cron/worker
$logLine = json_encode([
    'ts' => gmdate('c'),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'wallet' => $wallet,
    'need_bnb' => $need,
]) . "\n";
$logFile = __DIR__ . DIRECTORY_SEPARATOR . 'topup_queue.log';
if (@file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX) === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to log request']);
    exit;
}

// Update rate file
@file_put_contents($rateFile, json_encode(['ts' => $now]));

// NOTE: This endpoint does not actually send BNB. A separate funded worker should poll topup_queue.log and send small BNB amounts.
echo json_encode(['ok' => true, 'queued' => true]);
exit;
?>


