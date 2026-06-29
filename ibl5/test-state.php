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

// Minimal bootstrap — config.php for DB credentials, Composer autoloader for
// the delight-auth seeding actions below (reset/confirm user fixtures). Both
// requires sit after the E2E_TESTING gate, so neither loads in production.
require __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

// In git worktrees vendor/ is symlinked to the main repo, so Composer resolves
// classes/ through the symlink's realpath and app classes (e.g. PdoConnection)
// fail to load. Mirror mainfile.php's worktree fallback autoloader so the
// seeding actions below can reach classes/. No-op in CI (vendor/ is real).
if (is_link(__DIR__ . '/vendor')) {
    $worktreeClasses = realpath(__DIR__ . '/classes');
    if ($worktreeClasses !== false) {
        spl_autoload_register(static function (string $class) use ($worktreeClasses): void {
            $file = $worktreeClasses . '/' . str_replace('\\', '/', $class) . '.php';
            if (file_exists($file)) {
                require $file;
            }
        }, true, true);
    }
}

/** @var string $dbhost */
/** @var string $dbuname */
/** @var string $dbpass */
/** @var string $dbname */

/**
 * Build a Delight\Auth\Auth instance over the shared PDO connection.
 *
 * Mirrors classes/Auth/AuthService.php's construction (prefix 'auth_') but with
 * throttling disabled — E2E seeds many accounts and must not trip the lockout.
 * Used only by the seed-reset-user / seed-confirm-user actions.
 */
function e2eAuth(): \Delight\Auth\Auth
{
    return new \Delight\Auth\Auth(
        \Database\PdoConnection::getInstance(),
        null,
        'auth_',
        false,
    );
}

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
    // Also reset the team-level extension flags for Metros (teamid=1) so
    // repeated local runs are idempotent — the processExtension() happy path
    // sets Used_Extension_This_Season=1 on accept; without this reset the next
    // run's Block 1 sees "already used" on its first request.
    $db->query(
        'UPDATE ibl_team_info SET Used_Extension_This_Season = 0, Used_Extension_This_Chunk = 0
         WHERE teamid = 1'
    );
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
    $db->query("UPDATE ibl_settings SET value = 'No' WHERE setting_key = 'Draft Order Finalized' AND league = 'ibl'");
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

// POST ?action=seed-reset-user — create a VERIFIED e2e_reset_<rand> account
// and generate a real password-reset selector/token pair via the delight-auth
// library. The reset row stores a password_hash() digest of the token, so a
// raw SQL INSERT cannot mint a usable pair — only forgotPassword()'s callback
// can. forgotPassword() requires a confirmed account, so we register then
// immediately confirm before requesting the reset. Returns the pair plus the
// known oldPassword so the E2E can prove the new password works / old fails.
if ($method === 'POST' && $action === 'seed-reset-user') {
    $rand = bin2hex(random_bytes(4));
    $username = 'e2e_reset_' . $rand;
    $email = $username . '@example.test';
    $oldPassword = 'OldPass!' . $rand;
    try {
        $auth = e2eAuth();
        $confirm = ['selector' => '', 'token' => ''];
        $auth->register($email, $oldPassword, $username, function (string $selector, string $token) use (&$confirm): void {
            $confirm['selector'] = $selector;
            $confirm['token'] = $token;
        });
        $auth->confirmEmail($confirm['selector'], $confirm['token']);

        $reset = ['selector' => '', 'token' => ''];
        $auth->forgotPassword($email, function (string $selector, string $token) use (&$reset): void {
            $reset['selector'] = $selector;
            $reset['token'] = $token;
        });
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => 'seed-reset-user failed: ' . $e->getMessage()]);
        $db->close();
        exit;
    }
    echo json_encode([
        'username' => $username,
        'email' => $email,
        'selector' => $reset['selector'],
        'token' => $reset['token'],
        'oldPassword' => $oldPassword,
    ]);
    $db->close();
    exit;
}

// POST ?action=seed-confirm-user — register an UNVERIFIED e2e_confirm_<rand>
// account and capture the confirmation selector/token from the registration
// email callback, so the confirm_email success round-trip can be driven E2E.
if ($method === 'POST' && $action === 'seed-confirm-user') {
    $rand = bin2hex(random_bytes(4));
    $username = 'e2e_confirm_' . $rand;
    $email = $username . '@example.test';
    $password = 'ConfirmPass!' . $rand;
    try {
        $auth = e2eAuth();
        $confirm = ['selector' => '', 'token' => ''];
        $auth->register($email, $password, $username, function (string $selector, string $token) use (&$confirm): void {
            $confirm['selector'] = $selector;
            $confirm['token'] = $token;
        });
    } catch (\Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => 'seed-confirm-user failed: ' . $e->getMessage()]);
        $db->close();
        exit;
    }
    echo json_encode([
        'username' => $username,
        'email' => $email,
        'selector' => $confirm['selector'],
        'token' => $confirm['token'],
    ]);
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
        $stmt = $db->prepare("INSERT IGNORE INTO ibl_jsb_history (season_year, team_name, wins, losses, won_championship) VALUES (?, '__e2e_champ', 50, 32, 1)");
        $stmt->bind_param('i', $year);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $db->prepare("DELETE FROM ibl_jsb_history WHERE season_year = ? AND team_name = '__e2e_champ'");
        $stmt->bind_param('i', $year);
        $stmt->execute();
        $stmt->close();
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
        $stmt = $db->prepare("UPDATE ibl_team_info SET eoy_vote = NOW() WHERE teamid BETWEEN 1 AND ?");
        $stmt->bind_param('i', $count);
        $stmt->execute();
        $stmt->close();
    }
    if ($count < 28) {
        $next = $count + 1;
        $stmt = $db->prepare("UPDATE ibl_team_info SET eoy_vote = 'No Vote' WHERE teamid BETWEEN ? AND 28");
        $stmt->bind_param('i', $next);
        $stmt->execute();
        $stmt->close();
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

// DELETE ?action=clear-fa-offers — wipe ibl_fa_offers without re-inserting
if ($method === 'DELETE' && $action === 'clear-fa-offers') {
    $db->query('DELETE FROM ibl_fa_offers');
    echo json_encode(['cleared' => $db->affected_rows]);
    $db->close();
    exit;
}

// DELETE ?action=reset-fa-offers[&pid=N] — wipe and re-insert the seed FA offer
// rows. With no pid, all three seed rows (pid 10/11/12, all on Metros) are
// inserted. With ?pid=N, only that pid's row is inserted — the other Metros
// offers count against the team's soft cap (FreeAgencyCapCalculator sums pending
// offers), so a max-contract test for one pid needs the other two offers gone to
// leave enough cap room for a non-exception max offer.
if ($method === 'DELETE' && $action === 'reset-fa-offers') {
    $seedRows = [
        10 => "('FA Guard',   10, 'Metros', 1, 700, 770, 840, 0, 0, 0,  1.0, 0.5, 1000.0, 0, 0, 0)",
        11 => "('FA Center',  11, 'Metros', 1, 480, 528, 0,   0, 0, 0,  1.0, 0.5, 600.0,  0, 0, 0)",
        12 => "('FA Forward', 12, 'Metros', 1, 380, 418, 460, 0, 0, 0,  1.0, 0.5, 550.0,  0, 0, 0)",
    ];
    $onlyPid = isset($_GET['pid']) ? (int) $_GET['pid'] : 0;
    if ($onlyPid !== 0 && isset($seedRows[$onlyPid])) {
        $rows = [$seedRows[$onlyPid]];
    } else {
        $rows = array_values($seedRows);
    }
    $db->query('DELETE FROM ibl_fa_offers');
    $db->query(
        'INSERT INTO ibl_fa_offers (name, pid, team, teamid, offer1, offer2, offer3, offer4, offer5, offer6,
                                    modifier, random, perceivedvalue, mle, lle, offer_type) VALUES '
        . implode(', ', $rows)
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

// GET ?action=engine-binary-ready — whether the jsbsim binary is installed and
// executable in this image. The detached-spawn E2E skips when it is absent (the
// prebaked :latest image may predate the binary — cf. prebaked-image rebuild lag).
if ($method === 'GET' && $action === 'engine-binary-ready') {
    $binary = __DIR__ . '/bin/jsbsim';
    echo json_encode(['ready' => is_file($binary) && is_executable($binary)]);
    $db->close();
    exit;
}

// GET ?action=count-shadow-rows — return row counts for the engine shadow tables,
// so the detached-spawn E2E can poll until the background run lands rows.
if ($method === 'GET' && $action === 'count-shadow-rows') {
    $players = $db->query('SELECT COUNT(*) AS cnt FROM ibl_box_scores_engine_shadow');
    $teams = $db->query('SELECT COUNT(*) AS cnt FROM ibl_box_scores_engine_shadow_teams');
    $playersRow = $players ? $players->fetch_assoc() : null;
    $teamsRow = $teams ? $teams->fetch_assoc() : null;
    echo json_encode([
        'players' => $playersRow !== null ? (int) $playersRow['cnt'] : 0,
        'teams' => $teamsRow !== null ? (int) $teamsRow['cnt'] : 0,
    ]);
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

// DELETE ?action=reset-waiver-player&pid=N — restore the dedicated waivable
// player after a waive/drop submission test. WaiversRepository::dropPlayerToWaivers
// sets ordinal='1000' + droptime=time() on ibl_plr; this restores the seed ordinal
// (20) and droptime (0) and deletes the "make waiver cuts" news story so news is
// idempotent. Refuses any pid other than 200000031 (allowlist, mirroring
// reset-extension's pid=30 guard). See tests/e2e/fixtures/ci-seed.sql.
if ($method === 'DELETE' && $action === 'reset-waiver-player') {
    $pid = (int)($_GET['pid'] ?? 0);
    if ($pid !== 200000031) {
        http_response_code(400);
        echo json_encode(['error' => 'reset-waiver-player only supports pid=200000031 (Waive Target seed)']);
        $db->close();
        exit;
    }
    $stmt = $db->prepare("UPDATE ibl_plr SET ordinal = 20, droptime = 0, teamid = 1 WHERE pid = ?");
    $stmt->bind_param('i', $pid);
    $stmt->execute();
    $reset = $stmt->affected_rows > 0 ? 1 : 0;
    $stmt->close();
    // Delete the waiver-cuts news story (title = "{teamName} make waiver cuts").
    $db->query("DELETE FROM nuke_stories WHERE title = 'Metros make waiver cuts'");
    echo json_encode(['reset' => $reset, 'stories_deleted' => $db->affected_rows]);
    $db->close();
    exit;
}

// DELETE ?action=reset-rookie-option&pid=N — restore the rookie-option player's
// contract after a successful option exercise. RookieOptionRepository writes
// salary_yr4 (round 1) for this round-1 fixture; restore salary_yr3=500 (final
// rookie year) and salary_yr4=0 (option year) and delete the news story the
// controller inserts on success. Refuses any pid other than 200000032 (allowlist).
if ($method === 'DELETE' && $action === 'reset-rookie-option') {
    $pid = (int)($_GET['pid'] ?? 0);
    if ($pid !== 200000032) {
        http_response_code(400);
        echo json_encode(['error' => 'reset-rookie-option only supports pid=200000032 (Rookie Option Target seed)']);
        $db->close();
        exit;
    }
    $stmt = $db->prepare("UPDATE ibl_plr SET salary_yr3 = 500, salary_yr4 = 0 WHERE pid = ?");
    $stmt->bind_param('i', $pid);
    $stmt->execute();
    $reset = $stmt->affected_rows > 0 ? 1 : 0;
    $stmt->close();
    // RookieOptionController inserts a story titled "{playerName} extends their
    // contract with the {teamName}"; scope the delete to this fixture's title.
    $db->query("DELETE FROM nuke_stories WHERE title LIKE 'Rookie Option Target extends their contract%'");
    echo json_encode(['reset' => $reset, 'stories_deleted' => $db->affected_rows]);
    $db->close();
    exit;
}

// DELETE ?action=reset-draft-pick&round=N&pick=N&year=N — make the draft-selection
// submission test idempotent. A successful op=select writes UPDATE ibl_draft SET
// player=?,date=? WHERE round=? AND pick=?, sets ibl_draft_class.drafted='1' for
// the picked prospect (by name), and INSERTs a new ibl_plr row (dynamic pid). This
// reads the slot's stored player name, un-drafts that prospect, deletes the created
// ibl_plr row(s), and clears the slot. Distinct scope from reset-draft-order (which
// does DELETE FROM ibl_draft WHERE year=? for the ProjectedDraftOrder save flow):
// this targets a single (round,pick) row. draft.spec.ts and
// projected-draft-order-submission.spec.ts are separate serial files.
if ($method === 'DELETE' && $action === 'reset-draft-pick') {
    $round = (int)($_GET['round'] ?? 0);
    $pick = (int)($_GET['pick'] ?? 0);
    $year = (int)($_GET['year'] ?? 0);
    if ($round < 1 || $round > 12 || $pick < 1 || $pick > 60 || $year < 1900 || $year > 2200) {
        http_response_code(400);
        echo json_encode(['error' => 'reset-draft-pick requires valid round (1-12), pick (1-60), year (1900-2200)']);
        $db->close();
        exit;
    }
    // Read the player name currently stored in the slot.
    $sel = $db->prepare('SELECT player FROM ibl_draft WHERE round = ? AND pick = ? AND year = ? LIMIT 1');
    $sel->bind_param('iii', $round, $pick, $year);
    $sel->execute();
    $row = $sel->get_result()->fetch_assoc();
    $sel->close();
    $draftedName = ($row !== null && is_string($row['player'])) ? $row['player'] : '';

    $undrafted = 0;
    $playersDeleted = 0;
    if ($draftedName !== '') {
        // Reset the draft-class prospect so it is selectable again.
        $u = $db->prepare("UPDATE ibl_draft_class SET drafted = '0' WHERE name = ?");
        $u->bind_param('s', $draftedName);
        $u->execute();
        $undrafted = $u->affected_rows;
        $u->close();
        // Remove the ibl_plr row(s) the draft created for this prospect. Draft
        // class prospect names never match a seeded ibl_plr player, so a
        // name-scoped delete is safe.
        $d = $db->prepare('DELETE FROM ibl_plr WHERE name = ? AND teamid BETWEEN 1 AND 30');
        $d->bind_param('s', $draftedName);
        $d->execute();
        $playersDeleted = $d->affected_rows;
        $d->close();
    }
    // Clear the slot. `date` is a nullable datetime — NULL means "no pick made";
    // an empty string is rejected under MySQL strict mode (Incorrect datetime value).
    $c = $db->prepare("UPDATE ibl_draft SET player = '', date = NULL WHERE round = ? AND pick = ? AND year = ?");
    $c->bind_param('iii', $round, $pick, $year);
    $c->execute();
    $cleared = $c->affected_rows;
    $c->close();

    echo json_encode([
        'cleared' => $cleared,
        'undrafted' => $undrafted,
        'players_deleted' => $playersDeleted,
        'player' => $draftedName,
    ]);
    $db->close();
    exit;
}

// DELETE ?action=reset-saved-dc-names&teamid=N — restore the Monarchs (tid=8)
// saved-DC fixture after rename / rename-active mutations. id=10/11 names are
// reset to their seed values; any active-DC row that rename-active inserted
// (is_active=1) is deleted (the seed has no active row for tid=8). Refuses any
// teamid other than 8 (allowlist). See tests/e2e/fixtures/ci-seed.sql.
if ($method === 'DELETE' && $action === 'reset-saved-dc-names') {
    $teamid = (int)($_GET['teamid'] ?? 0);
    if ($teamid !== 8) {
        http_response_code(400);
        echo json_encode(['error' => 'reset-saved-dc-names only supports teamid=8 (Monarchs DC test team)']);
        $db->close();
        exit;
    }
    $db->query("UPDATE ibl_saved_depth_charts SET name = 'DC Test Offense' WHERE id = 10 AND teamid = 8");
    $db->query("UPDATE ibl_saved_depth_charts SET name = 'DC Test Defense' WHERE id = 11 AND teamid = 8");
    // Delete any active snapshot row rename-active created (seed has none).
    $db->query("DELETE FROM ibl_saved_depth_charts WHERE teamid = 8 AND is_active = 1");
    echo json_encode(['reset' => 'ok', 'active_deleted' => $db->affected_rows]);
    $db->close();
    exit;
}

// DELETE ?action=clear-trade-offers — delete ALL non-REST trade offers so the
// trade-review empty-state can be observed. Spares ids 7-8 (reserved for
// api-v1-rest.spec.ts) — ci-seed seeds 7-8 as non-Metros trades (Spurs/Flames,
// Minutemen/Royals), so they do NOT appear on the Metros Trade Review page.
// Uses NOT IN (7,8) rather than IN (1-6) because trading-submission.spec.ts
// creates dynamic offers during the same run that also block the empty-state.
// Paired with reset-trade-offers.
if ($method === 'DELETE' && $action === 'clear-trade-offers') {
    $db->query('DELETE FROM ibl_trade_info WHERE tradeofferid NOT IN (7,8)');
    $infoDeleted = $db->affected_rows;
    $db->query('DELETE FROM ibl_trade_offers WHERE id NOT IN (7,8)');
    $offersDeleted = $db->affected_rows;
    echo json_encode(['info_deleted' => $infoDeleted, 'offers_deleted' => $offersDeleted]);
    $db->close();
    exit;
}

// DELETE ?action=reset-trade-offers — restore the seeded review offers (1-6)
// exactly as tests/e2e/fixtures/ci-seed.sql so the offers-present tests see seed
// state again. Also restores REST spec offers 7-8 back to 'test' approval so
// the api-v1-rest.spec.ts sees the expected pending state. Idempotent.
if ($method === 'DELETE' && $action === 'reset-trade-offers') {
    $db->query('DELETE FROM ibl_trade_info WHERE tradeofferid IN (1,2,3,4,5,6)');
    $db->query('DELETE FROM ibl_trade_offers WHERE id IN (1,2,3,4,5,6)');
    $db->query("UPDATE ibl_trade_info SET approval = 'test' WHERE tradeofferid IN (7,8)");
    $db->query('INSERT INTO ibl_trade_offers (id) VALUES (1), (2), (3), (4), (5), (6)');
    $db->query(
        "INSERT INTO ibl_trade_info (tradeofferid, itemid, itemtype, trade_from, trade_to, approval) VALUES
          (1, 4, '1', 'Stars', 'Metros', 'Metros'),
          (1, 2, '1', 'Metros', 'Stars', 'Metros'),
          (2, 6, '1', 'Phoenixes', 'Metros', 'Metros'),
          (2, 1, '0', 'Metros', 'Phoenixes', 'Metros'),
          (3, 23, '1', 'Cougars', 'Metros', 'Metros'),
          (3, 21, '1', 'Metros', 'Cougars', 'Metros'),
          (4, 5, '1', 'Stars', 'Metros', 'Metros'),
          (4, 20, '1', 'Metros', 'Stars', 'Metros'),
          (5, 7, '1', 'Phoenixes', 'Metros', 'Metros'),
          (5, 22, '1', 'Metros', 'Phoenixes', 'Metros'),
          (6, 24, '1', 'Cougars', 'Metros', 'Metros'),
          (6, 10, '1', 'Metros', 'Cougars', 'Metros')"
    );
    echo json_encode(['reset' => $db->affected_rows]);
    $db->close();
    exit;
}

// DELETE ?action=reset-fa-signings — restore state after a block.php
// assign_free_agents test. FreeAgencyAdminRepository::updatePlayerContract moves
// signed players (pids 10/11/12) onto the bidding team and writes cy/cyt/salary_yr1-6
// + fa_signing_flag=1, and may clear ibl_team_info.has_mle/has_lle. This restores
// the three FA players to their pre-signing ibl_plr seed state, restores Metros'
// exceptions, deletes the inserted news story (title '2006 IBL Free Agency, Days %'),
// and re-seeds ibl_fa_offers (same rows as reset-fa-offers). Returns counts.
if ($method === 'DELETE' && $action === 'reset-fa-signings') {
    // Restore the three FA players to seed teamid + zeroed contract columns.
    $db->query("UPDATE ibl_plr SET teamid = 1, cy = 0, cyt = 0, salary_yr1 = 0, salary_yr2 = 0, salary_yr3 = 0, salary_yr4 = 0, salary_yr5 = 0, salary_yr6 = 0, fa_signing_flag = 0 WHERE pid = 10");
    $db->query("UPDATE ibl_plr SET teamid = 0, cy = 0, cyt = 0, salary_yr1 = 0, salary_yr2 = 0, salary_yr3 = 0, salary_yr4 = 0, salary_yr5 = 0, salary_yr6 = 0, fa_signing_flag = 0 WHERE pid = 11");
    $db->query("UPDATE ibl_plr SET teamid = 2, cy = 0, cyt = 0, salary_yr1 = 0, salary_yr2 = 0, salary_yr3 = 0, salary_yr4 = 0, salary_yr5 = 0, salary_yr6 = 0, fa_signing_flag = 0 WHERE pid = 12");
    // Restore Metros' MLE/LLE exceptions (executeSignings may consume them).
    $db->query("UPDATE ibl_team_info SET has_mle = 1, has_lle = 1 WHERE teamid = 1");
    // Delete the FA assign news story.
    $db->query("DELETE FROM nuke_stories WHERE title LIKE '2006 IBL Free Agency, Days %'");
    $storiesDeleted = $db->affected_rows;
    // Re-seed ibl_fa_offers (same three rows as reset-fa-offers).
    $db->query('DELETE FROM ibl_fa_offers');
    $db->query(
        "INSERT INTO ibl_fa_offers (name, pid, team, teamid, offer1, offer2, offer3, offer4, offer5, offer6,
                                    modifier, random, perceivedvalue, mle, lle, offer_type) VALUES
          ('FA Guard',   10, 'Metros', 1, 700, 770, 840, 0, 0, 0,  1.0, 0.5, 1000.0, 0, 0, 0),
          ('FA Center',  11, 'Metros', 1, 480, 528, 0,   0, 0, 0,  1.0, 0.5, 600.0,  0, 0, 0),
          ('FA Forward', 12, 'Metros', 1, 380, 418, 460, 0, 0, 0,  1.0, 0.5, 550.0,  0, 0, 0)"
    );
    echo json_encode(['reset' => 'ok', 'stories_deleted' => $storiesDeleted]);
    $db->close();
    exit;
}

$settingsLeague = is_string($_GET['league'] ?? null) ? $_GET['league'] : 'ibl';

if ($method === 'GET') {
    $stmt = $db->prepare('SELECT setting_key, value FROM ibl_settings WHERE league = ?');
    $stmt->bind_param('s', $settingsLeague);
    $stmt->execute();
    $result = $stmt->get_result();
    $settings = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['value'];
        }
        $result->free();
    }
    $stmt->close();
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

    $selectStmt = $db->prepare('SELECT value FROM ibl_settings WHERE setting_key = ? AND league = ?');
    $upsertStmt = $db->prepare(
        'INSERT INTO ibl_settings (setting_key, value, league) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)'
    );

    foreach ($input as $name => $value) {
        // Get current value
        $selectStmt->bind_param('ss', $name, $settingsLeague);
        $selectStmt->execute();
        $result = $selectStmt->get_result();
        $row = $result->fetch_assoc();
        $previous[$name] = $row !== null ? $row['value'] : null;

        // Upsert new value
        $upsertStmt->bind_param('sss', $name, $value, $settingsLeague);
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
