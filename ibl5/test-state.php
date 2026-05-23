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

// DELETE ?action=set-leaders-htm&present=1|0 — create or remove Leaders.htm
// for E2E testing of the GenerateSeasonAwardsStep (Leaders.htm gate).
if ($method === 'DELETE' && $action === 'set-leaders-htm') {
    $present = (int)($_GET['present'] ?? -1);
    if ($present !== 0 && $present !== 1) {
        http_response_code(400);
        echo json_encode(['error' => 'set-leaders-htm requires present=0 or present=1']);
        $db->close();
        exit;
    }
    $path = __DIR__ . '/Leaders.htm';
    if ($present === 1) {
        file_put_contents($path, '<!-- E2E test fixture -->');
    } elseif (file_exists($path)) {
        unlink($path);
    }
    echo json_encode(['leaders_htm' => $present === 1 ? 'created' : 'removed']);
    $db->close();
    exit;
}

// DELETE ?action=set-champion&year=N&present=1|0 — insert or remove a
// won_championship=1 row in ibl_jsb_history for E2E testing of
// EndOfSeasonImportStep (champion gate).
if ($method === 'DELETE' && $action === 'set-champion') {
    $year = (int)($_GET['year'] ?? 0);
    $present = (int)($_GET['present'] ?? -1);
    if ($year < 1900 || $year > 2200 || ($present !== 0 && $present !== 1)) {
        http_response_code(400);
        echo json_encode(['error' => 'set-champion requires valid year and present=0|1']);
        $db->close();
        exit;
    }
    if ($present === 1) {
        $db->query("INSERT IGNORE INTO ibl_jsb_history (season_year, team_name, wins, losses, won_championship) VALUES ($year, '__e2e_champ', 50, 32, 1)");
    } else {
        $db->query("DELETE FROM ibl_jsb_history WHERE season_year = $year AND team_name = '__e2e_champ'");
    }
    echo json_encode(['champion' => $present === 1 ? 'inserted' : 'removed']);
    $db->close();
    exit;
}

// DELETE ?action=set-award&year=N&award=NAME&present=1|0 — insert or remove an
// award row in ibl_awards for E2E testing. Used to control awardsAlreadyGenerated
// (award='Most Valuable Player (1st)') and hasFinalsMvp (award='IBL Finals MVP').
if ($method === 'DELETE' && $action === 'set-award') {
    $year = (int)($_GET['year'] ?? 0);
    $award = (string)($_GET['award'] ?? '');
    $present = (int)($_GET['present'] ?? -1);
    $allowedAwards = ['Most Valuable Player (1st)', 'IBL Finals MVP'];
    if ($year < 1900 || $year > 2200 || !in_array($award, $allowedAwards, true) || ($present !== 0 && $present !== 1)) {
        http_response_code(400);
        echo json_encode(['error' => 'set-award requires valid year, allowed award name, and present=0|1']);
        $db->close();
        exit;
    }
    if ($present === 1) {
        $stmt = $db->prepare("INSERT IGNORE INTO ibl_awards (year, Award, name) VALUES (?, ?, '__e2e_test')");
        $stmt->bind_param('is', $year, $award);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $db->prepare("DELETE FROM ibl_awards WHERE year = ? AND Award = ? AND name = '__e2e_test'");
        $stmt->bind_param('is', $year, $award);
        $stmt->execute();
        $stmt->close();
    }
    echo json_encode(['award' => $present === 1 ? 'inserted' : 'removed']);
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

// GET ?action=get-votes&team=X — query ASG/EOY vote status for a team
if ($method === 'GET' && $action === 'get-votes') {
    $team = $_GET['team'] ?? '';
    if ($team === '') {
        http_response_code(400);
        echo json_encode(['error' => 'get-votes requires team parameter']);
        $db->close();
        exit;
    }
    $stmt = $db->prepare('SELECT asg_vote, eoy_vote FROM ibl_team_info WHERE team_name = ?');
    $stmt->bind_param('s', $team);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    if ($row === null) {
        http_response_code(404);
        echo json_encode(['error' => "Team not found: $team"]);
        $db->close();
        exit;
    }
    echo json_encode([
        'asg_vote' => $row['asg_vote'],
        'eoy_vote' => $row['eoy_vote'],
        'asg_voted' => $row['asg_vote'] !== 'No Vote' && $row['asg_vote'] !== '',
        'eoy_voted' => $row['eoy_vote'] !== 'No Vote' && $row['eoy_vote'] !== '',
    ]);
    $db->close();
    exit;
}

// DELETE ?action=reset-vote&team=X&type=asg|eoy — reset a team's vote flag
if ($method === 'DELETE' && $action === 'reset-vote') {
    $team = $_GET['team'] ?? '';
    $type = $_GET['type'] ?? '';
    if ($team === '' || !in_array($type, ['asg', 'eoy'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'reset-vote requires team and type=asg|eoy']);
        $db->close();
        exit;
    }
    $column = $type === 'asg' ? 'asg_vote' : 'eoy_vote';
    $stmt = $db->prepare("UPDATE ibl_team_info SET $column = 'No Vote' WHERE team_name = ?");
    $stmt->bind_param('s', $team);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    echo json_encode(['reset' => $column, 'team' => $team, 'affected' => $affected]);
    $db->close();
    exit;
}

// DELETE ?action=reset-fa-offers — wipe and re-insert the three seed FA offer rows
if ($method === 'DELETE' && $action === 'reset-fa-offers') {
    $db->query('DELETE FROM ibl_fa_offers');
    $db->query(
        "INSERT INTO ibl_fa_offers (name, pid, team, teamid, offer1, offer2, offer3, offer4, offer5, offer6,
                                    modifier, random, perceivedvalue, mle, lle, offer_type) VALUES
          ('FA Guard',   10, 'Metros', 1, 700, 770, 840, 0, 0, 0,  1.0, 0.5, 700.0, 0, 0, 0),
          ('FA Center',  11, 'Metros', 1, 480, 528, 0,   0, 0, 0,  1.0, 0.5, 480.0, 0, 0, 0),
          ('FA Forward', 12, 'Metros', 1, 380, 418, 460, 0, 0, 0,  1.0, 0.5, 380.0, 0, 0, 0)"
    );
    echo json_encode(['reset' => $db->affected_rows]);
    $db->close();
    exit;
}

// DELETE ?action=reset-demands — wipe and re-insert the three seed demand rows
if ($method === 'DELETE' && $action === 'reset-demands') {
    $db->query('DELETE FROM ibl_demands');
    $db->query(
        "INSERT INTO ibl_demands (name, pid, dem1, dem2, dem3, dem4, dem5, dem6) VALUES
          ('FA Guard',   10, 800, 880, 960, 1040, 0, 0),
          ('FA Center',  11, 500, 550, 600, 0, 0, 0),
          ('FA Forward', 12, 400, 440, 480, 520, 560, 600)"
    );
    echo json_encode(['reset' => $db->affected_rows]);
    $db->close();
    exit;
}

// GET ?action=count-demands — return current row count for ibl_demands
if ($method === 'GET' && $action === 'count-demands') {
    $result = $db->query('SELECT COUNT(*) AS cnt FROM ibl_demands');
    $row = $result ? $result->fetch_assoc() : null;
    echo json_encode(['count' => $row !== null ? (int) $row['cnt'] : 0]);
    $db->close();
    exit;
}

// DELETE ?action=reset-allstar-names — delete and re-insert the ASG seed rows
// (delete+insert is idempotent even after partial renames)
if ($method === 'DELETE' && $action === 'reset-allstar-names') {
    $db->query(
        "DELETE FROM ibl_box_scores_teams
         WHERE visitor_teamid = 50 AND home_teamid = 51 AND game_date = '2026-02-15'"
    );
    $deleted = $db->affected_rows;
    $db->query(
        "INSERT INTO ibl_box_scores_teams (game_date, visitor_teamid, home_teamid, game_of_that_day, name,
          game_2gm, game_2ga, game_ftm, game_fta, game_3gm, game_3ga,
          game_orb, game_drb, game_ast, game_stl, game_tov, game_blk, game_pf,
          visitor_q1_points, visitor_q2_points, visitor_q3_points, visitor_q4_points,
          home_q1_points, home_q2_points, home_q3_points, home_q4_points) VALUES
          ('2026-02-15', 50, 51, 1, 'Team Away',
           30, 60, 18, 22, 12, 28, 10, 28, 24, 8, 10, 5, 16,
           28, 26, 27, 24, 24, 25, 24, 25),
          ('2026-02-15', 50, 51, 1, 'Team Home',
           28, 58, 20, 25, 10, 25, 9, 26, 22, 7, 12, 4, 17,
           28, 26, 27, 24, 24, 25, 24, 25)"
    );
    echo json_encode(['reset' => $deleted]);
    $db->close();
    exit;
}

// GET ?action=get-allstar-name&id=N — read back a single row's name from ibl_box_scores_teams
if ($method === 'GET' && $action === 'get-allstar-name') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'get-allstar-name requires a positive id']);
        $db->close();
        exit;
    }
    $stmt = $db->prepare('SELECT name FROM ibl_box_scores_teams WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    echo json_encode(['name' => $row !== null ? $row['name'] : null]);
    $db->close();
    exit;
}

// GET ?action=get-allstar-ids — return ids for ASG seed rows with default names
if ($method === 'GET' && $action === 'get-allstar-ids') {
    $result = $db->query(
        "SELECT id, name FROM ibl_box_scores_teams
         WHERE visitor_teamid = 50 AND home_teamid = 51
           AND game_date = '2026-02-15'
           AND name IN ('Team Away', 'Team Home')
         ORDER BY id ASC"
    );
    $ids = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $ids[] = ['id' => (int) $row['id'], 'name' => $row['name']];
        }
        $result->free();
    }
    echo json_encode(['rows' => $ids]);
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
