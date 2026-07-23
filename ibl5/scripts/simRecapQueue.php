<?php

declare(strict_types=1);

/**
 * CLI-only queue driver for the sim-recap pipeline.
 *
 * Usage: php ibl5/scripts/simRecapQueue.php <verb> [--sim=N]
 *   Verbs: claim-next | claim --sim=N | reclaim-stale | park --sim=N
 *          | find --sim=N | recent-themes | mention-map
 *   One JSON object is emitted on stdout; bad argv writes
 *   "simRecapQueue: <message>" to stderr and exits 1 before any repo is built.
 *
 * This is the queue half of the trust boundary (ADR-0092): the Mac-side tick
 * holds only a read-only MySQL credential and so cannot drive the queue over
 * the tunnel at all (claiming a row is an UPDATE). Every privileged queue
 * action happens here, prod-side, reached over ssh — never from the Mac.
 *
 * Zero SQL is composed in this file; every statement stays bound inside
 * SimRecap\SimSummaryRepository. Protected by both the PHP_SAPI guard below
 * (the first executable statement) and an ibl5/scripts/.htaccess scoped deny.
 */

// ── CLI-only guard — must stay the FIRST executable statement: a web hit must
//    be refused before any resource (autoload, config, db) is touched.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script is CLI-only.');
}

// ── Minimal bootstrap (mirrors scripts/storeSimRecap.php) ─────────────────────
$_SERVER['PHP_SELF'] = 'simRecapQueue';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SCRIPT_FILENAME'] = __FILE__;

require_once __DIR__ . '/../vendor/autoload.php';

// Worktree fix: vendor/ symlinks to the main repo, so PSR-4 resolves classes/
// there; register the local classes/ dir so this worktree's code is used.
$localClassesDir = realpath(__DIR__ . '/../classes');
if ($localClassesDir !== false) {
    spl_autoload_register(static function (string $class) use ($localClassesDir): void {
        $path = $localClassesDir . '/' . str_replace('\\', '/', $class) . '.php';
        if (file_exists($path)) {
            require_once $path;
        }
    });
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db/db.php';

/** @var \mysqli $mysqli_db */

function fail(string $msg): never
{
    fwrite(STDERR, "simRecapQueue: {$msg}\n");
    exit(1);
}

/**
 * Flatten the last 5 sims' themes into a de-duplicated list, newest first.
 * recentThemes() returns raw JSON strings (decoding is the caller's job);
 * a malformed or non-array entry is skipped rather than aborting the ledger.
 *
 * @return list<string>
 */
function collectRecentThemes(\SimRecap\SimSummaryRepository $repo): array
{
    $seen = [];
    $themes = [];
    foreach ($repo->recentThemes() as $row) {
        $decoded = json_decode($row['themes_used'], true);
        if (!is_array($decoded)) {
            continue;
        }
        foreach ($decoded as $theme) {
            if (!is_string($theme) || isset($seen[$theme])) {
                continue;
            }
            $seen[$theme] = true;
            $themes[] = $theme;
        }
    }

    return $themes;
}

/**
 * Team name → Discord snowflake, as STRINGS. Teams with no id are omitted
 * entirely so the prompt's plain-team-name fallback fires on a missing key
 * and no "<@null>" can ever be built.
 *
 * The snowflake is emitted as a JSON string, never a bare JSON number: a bare
 * number round-trips only under jq >= 1.7 with no arithmetic, and corrupts under
 * jq <= 1.6 (IEEE-754) or any other consumer. The (string) cast removes every
 * such case at once — and is a string cast, not an integer cast, so it leaves
 * the single pinned numeric conversion on --sim untouched.
 *
 * @return array<string, string>
 */
function buildMentionMap(\mysqli $db): array
{
    $teams = new \Repositories\TeamIdentityRepository($db);
    $map = [];
    foreach ($teams->getAllRealTeams() as $team) {
        $name = $team['team_name'];
        $id = $teams->getTeamDiscordID($name);
        if ($id === null) {
            continue;
        }
        $map[$name] = (string) $id;
    }

    return $map;
}

// ── Argv parse — positional verb, then flags (mirrors bug-pipeline/transition.php).
//    Runs entirely before any repository is constructed.
$verb = null;
$simRaw = null;
foreach (array_slice($argv, 1) as $arg) {
    if (!is_string($arg)) {
        continue;
    }
    if (str_starts_with($arg, '--sim=')) {
        $simRaw = substr($arg, 6);
    } elseif ($verb === null && !str_starts_with($arg, '--')) {
        $verb = $arg;
    } else {
        fail("unexpected argument: {$arg}");
    }
}

if ($verb === null) {
    fail('a verb is required (claim-next|claim|reclaim-stale|park|find|recent-themes|mention-map)');
}

// Verbs that require --sim; parse and validate it once, here.
$sim = null;
if (in_array($verb, ['claim', 'park', 'find'], true)) {
    if ($simRaw === null || !ctype_digit($simRaw)) {
        fail("--sim=N (positive integer) is required for verb {$verb}");
    }
    $sim = (int) $simRaw;
    if ($sim < 1) {
        fail('--sim must be >= 1');
    }
}

// ── Dispatch ──────────────────────────────────────────────────────────────────
$repo = new \SimRecap\SimSummaryRepository($mysqli_db);

switch ($verb) {
    case 'claim-next':
        $out = ['ok' => true, 'cmd' => 'claim-next', 'sim' => $repo->claimNextPending()];
        break;

    case 'claim':
        /** @var int $sim */
        $out = ['ok' => true, 'cmd' => 'claim', 'sim' => $sim, 'claimed' => $repo->claimPending($sim)];
        break;

    case 'reclaim-stale':
        $out = ['ok' => true, 'cmd' => 'reclaim-stale', 'sim' => $repo->reclaimStaleClaim()];
        break;

    case 'park':
        /** @var int $sim */
        $out = ['ok' => true, 'cmd' => 'park', 'sim' => $sim, 'outcome' => $repo->parkOrFail($sim)];
        break;

    case 'find':
        /** @var int $sim */
        $row = $repo->find($sim);
        // Project the full envelope down to the six queue-state keys the tick
        // reads; the frozen find() returns recap_text (MEDIUMTEXT) and the
        // prose fields too, and none of that should cross the ssh pipe per poll.
        $projected = $row === null ? null : [
            'sim' => $row['sim'],
            'status' => $row['status'],
            'attempts' => $row['attempts'],
            'blocked_until' => $row['blocked_until'],
            'claimed_at' => $row['claimed_at'],
            'generated_at' => $row['generated_at'],
        ];
        $out = ['ok' => true, 'cmd' => 'find', 'row' => $projected];
        break;

    case 'recent-themes':
        $out = ['ok' => true, 'cmd' => 'recent-themes', 'themes' => collectRecentThemes($repo)];
        break;

    case 'mention-map':
        $out = ['ok' => true, 'cmd' => 'mention-map', 'teams' => buildMentionMap($mysqli_db)];
        break;

    default:
        fail("unknown verb: {$verb}");
}

echo json_encode($out), "\n";
exit(0);
