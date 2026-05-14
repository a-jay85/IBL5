<?php // phpcs:ignore -- test-state endpoint for E2E testing

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
    'Current Season Ending Year',
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

// DELETE ?action=reset-extension&pid=N — reset rolling player's contract back
// to seed values after a contract-extension submission test. Scaffolded for
// PR-B's contract-extension flow; pid=30 (Extension Vet, Metros) is the only
// supported player today since it's the seed fixture for that test. See
// tests/e2e/fixtures/ci-seed.sql for the source-of-truth values.
if ($method === 'DELETE' && $action === 'reset-extension') {
    $pid = (int)($_GET['pid'] ?? 0);
    if ($pid !== 30) {
        http_response_code(400);
        echo json_encode(['error' => 'reset-extension only supports pid=30 (Extension Vet seed)']);
        $db->close();
        exit;
    }
    $stmt = $db->prepare(
        'UPDATE ibl_plr SET cy = 2, cyt = 2,
            salary_yr1 = 1500, salary_yr2 = 1650,
            salary_yr3 = 0, salary_yr4 = 0, salary_yr5 = 0, salary_yr6 = 0
         WHERE pid = ?'
    );
    $stmt->bind_param('i', $pid);
    $stmt->execute();
    $reset = $stmt->affected_rows > 0 ? 1 : 0;
    $stmt->close();
    echo json_encode(['reset' => $reset]);
    $db->close();
    exit;
}

// DELETE ?action=reset-draft-order&year=N — clear ibl_draft rows for one
// season year and flip `Draft Order Finalized` back to 'No', allowing repeat
// runs of the ProjectedDraftOrder save_order test. ProjectedDraftOrderService::
// saveLotteryOrder writes draft slots to ibl_draft (not ibl_draft_picks, the
// pick-ownership table) and sets the finalized flag as a side effect; both
// must be reset for a clean slate.
if ($method === 'DELETE' && $action === 'reset-draft-order') {
    $year = (int)($_GET['year'] ?? 0);
    if ($year < 1900 || $year > 2200) {
        http_response_code(400);
        echo json_encode(['error' => 'reset-draft-order requires a valid year']);
        $db->close();
        exit;
    }
    $stmt = $db->prepare('DELETE FROM ibl_draft WHERE year = ?');
    $stmt->bind_param('i', $year);
    $stmt->execute();
    $cleared = $stmt->affected_rows;
    $stmt->close();
    $db->query("UPDATE ibl_settings SET value = 'No' WHERE name = 'Draft Order Finalized'");
    echo json_encode(['cleared' => $cleared]);
    $db->close();
    exit;
}

// DELETE ?action=delete-test-user&username=NAME — remove a registration-test
// user from auth_users. Refuses to delete usernames that do not start with
// the `e2e_` prefix so the endpoint cannot wipe real accounts even if
// E2E_TESTING is mistakenly enabled in a non-test environment.
if ($method === 'DELETE' && $action === 'delete-test-user') {
    $username = (string)($_GET['username'] ?? '');
    if (!str_starts_with($username, 'e2e_')) {
        http_response_code(400);
        echo json_encode(['error' => 'delete-test-user only accepts usernames starting with e2e_']);
        $db->close();
        exit;
    }
    $stmt = $db->prepare('DELETE FROM auth_users WHERE username = ?');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();
    echo json_encode(['deleted' => $deleted]);
    $db->close();
    exit;
}

// DELETE ?action=set-eoy-votes&count=N — set N teams as having voted for E2E
// testing of the updater awards step. Teams 1..count get a non-default vote,
// teams (count+1)..28 get reset to 'No Vote'.
if ($method === 'DELETE' && $action === 'set-eoy-votes') {
    $count = (int)($_GET['count'] ?? -1);
    if ($count < 0 || $count > 28) {
        http_response_code(400);
        echo json_encode(['error' => 'set-eoy-votes count must be 0-28']);
        $db->close();
        exit;
    }
    if ($count > 0) {
        $db->query("UPDATE ibl_team_info SET eoy_vote = NOW() WHERE teamid BETWEEN 1 AND $count");
    }
    if ($count < 28) {
        $next = $count + 1;
        $db->query("UPDATE ibl_team_info SET eoy_vote = 'No Vote' WHERE teamid BETWEEN $next AND 28");
    }
    echo json_encode(['set' => $count]);
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
