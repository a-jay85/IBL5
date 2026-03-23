<?php

declare(strict_types=1);

/**
 * E2E Test State Control Endpoint
 *
 * Allows Playwright tests to read/write ibl_settings for deterministic test state.
 * Gated by E2E_TESTING environment variable — returns 403 in production.
 *
 * GET  → returns all ibl_settings as JSON {"name": "value", ...}
 * POST → accepts JSON body {"Setting Name": "value", ...}
 *        returns {"previous": {...}, "applied": {...}}
 */

// Environment gate: check getenv first, then fall back to .env.test file
$allowed = getenv('E2E_TESTING') === '1';

if (!$allowed) {
    $envFile = __DIR__ . '/.env.test';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') {
                    continue;
                }
                if (str_starts_with($line, 'E2E_TESTING=1')) {
                    $allowed = true;
                    break;
                }
            }
        }
    }
}

if (!$allowed) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'E2E_TESTING is not enabled']);
    exit;
}

// Minimal bootstrap — only config.php for DB credentials
require __DIR__ . '/config.php';

/** @var string $dbhost */
/** @var string $dbuname */
/** @var string $dbpass */
/** @var string $dbname */

$db = new mysqli($dbhost, $dbuname, $dbpass, $dbname);
if ($db->connect_error) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}
$db->set_charset('utf8mb4');

// Allowlist of mutable settings
$ALLOWLIST = [
    'Current Season Phase',
    'Allow Trades',
    'Allow Waiver Moves',
    'Show Draft Link',
    'Trivia Mode',
    'ASG Voting',
    'EOY Voting',
    'Free Agency Notifications',
];

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
header('Content-Type: application/json');

// DELETE ?action=clear-throttle — clear auth throttling for E2E login
if ($method === 'DELETE' && $action === 'clear-throttle') {
    $db->query('DELETE FROM auth_users_throttling WHERE 1=1');
    echo json_encode(['cleared' => $db->affected_rows]);
    $db->close();
    exit;
}

if ($method === 'GET') {
    $result = $db->query('SELECT name, value FROM ibl_settings');
    $settings = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row['name']] = $row['value'];
        }
        $result->free();
    }
    echo json_encode($settings);
    $db->close();
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input) || $input === []) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON body — expected {"Setting Name": "value"}']);
        $db->close();
        exit;
    }

    // Validate all keys against allowlist
    $invalidKeys = array_diff(array_keys($input), $ALLOWLIST);
    if ($invalidKeys !== []) {
        http_response_code(400);
        echo json_encode(['error' => 'Unknown settings: ' . implode(', ', $invalidKeys)]);
        $db->close();
        exit;
    }

    // Read previous values
    $previous = [];
    $applied = [];

    $selectStmt = $db->prepare('SELECT value FROM ibl_settings WHERE name = ?');
    $upsertStmt = $db->prepare(
        'INSERT INTO ibl_settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)'
    );

    foreach ($input as $name => $value) {
        // Get current value
        $selectStmt->bind_param('s', $name);
        $selectStmt->execute();
        $result = $selectStmt->get_result();
        $row = $result->fetch_assoc();
        $previous[$name] = $row !== null ? $row['value'] : null;

        // Upsert new value
        $upsertStmt->bind_param('ss', $name, $value);
        $upsertStmt->execute();

        $applied[$name] = $value;
    }

    $selectStmt->close();
    $upsertStmt->close();

    echo json_encode(['previous' => $previous, 'applied' => $applied]);
    $db->close();
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
$db->close();
