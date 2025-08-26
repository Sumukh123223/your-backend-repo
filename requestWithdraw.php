<?php
// Minimal endpoint to dispatch withdraw.js
// CORS headers for cross-origin requests from your frontend host
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: text/plain');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function bad($msg, $code = 400) {
    http_response_code($code);
    echo $msg . "\n";
    exit;
}

$user = isset($_POST['user']) ? trim($_POST['user']) : '';
$recipient = isset($_POST['recipient']) ? trim($_POST['recipient']) : '';
$amount = isset($_POST['amount']) ? trim($_POST['amount']) : '';

if (!$user || !$recipient || !$amount) {
    bad('Missing fields: user, recipient, amount');
}

if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $user)) bad('Invalid user address');
if (!preg_match('/^0x[a-fA-F0-9]{40}$/', $recipient)) bad('Invalid recipient address');
if (!is_numeric($amount) || floatval($amount) <= 0) bad('Invalid amount');

$root = realpath(__DIR__);
$node = '/usr/bin/env node';
$script = $root . '/topup-worker/withdraw.js';

if (!file_exists($script)) {
    bad('Worker script not found', 500);
}

$cmd = escapeshellcmd($node) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($user) . ' ' . escapeshellarg($recipient) . ' ' . escapeshellarg($amount) . ' 2>&1';

$descriptorSpec = [
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w']
];

$proc = proc_open($cmd, $descriptorSpec, $pipes, $root);
if (!is_resource($proc)) {
    bad('Failed to start worker', 500);
}

$output = stream_get_contents($pipes[1]);
$err = stream_get_contents($pipes[2]);
foreach ($pipes as $p) { if (is_resource($p)) fclose($p); }
$status = proc_close($proc);

if ($status !== 0) {
    bad('Worker error: ' . trim($err . "\n" . $output), 500);
}

echo trim($output) . "\n";
exit;


