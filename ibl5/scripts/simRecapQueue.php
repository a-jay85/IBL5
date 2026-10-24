<?php

declare(strict_types=1);

/**
 * CLI-only queue driver for the sim-recap pipeline.
 *
 * Usage: php ibl5/scripts/simRecapQueue.php <verb> [--sim=N] [--host=H] [--cause=C] [--reset=EPOCH] [--event=done]
 *   Verbs: claim-next --host=H | claim --sim=N --host=H | reclaim-stale
 *          | park --sim=N --host=H --cause=C [--reset=EPOCH]
 *          | notify --sim=N --host=H --event=done
 *          | find --sim=N | recent-themes | mention-map
 *   H is mac|actions; C is flaky|usage_limit|auth|transport|prompt.
 *   One JSON object is emitted on stdout; bad argv writes
 *   "simRecapQueue: <message>" to stderr and exits 1 before any repo is built.
 *
 * This is the queue half of the trust boundary (ADR-0093): the Mac-side tick
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

// db/db.php populates the untyped global $mysqli_db; narrow it once, here, so
// every downstream call site is type-checked. (A @var directly above a function
// declaration has no effect — that is why this needs its own statement.)
/** @var \mysqli $mysqli_db */
$db = $mysqli_db;

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
 * The canonical host from prod-side config, never from argv (mirrors
 * scripts/storeSimRecap.php). A real host makes Discord's isProduction() true,
 * so status lines reach #admin-chat; '' (CI, dev) keeps them on the testing
 * webhook. Without this init every status line went to the testing webhook.
 */
function canonicalHost(): string
{
    $raw = defined('IBL5_CANONICAL_HOST') ? constant('IBL5_CANONICAL_HOST') : '';

    return is_string($raw) ? $raw : '';
}

/**
 * Post the #admin-chat line for a state change, at most once per sim and state.
 *
 * Returns 'unchanged' when the state is the same as last time or another host
 * won the compare-and-set, the state name when posted, and 'failed' when the
 * post threw (the state is reverted so a later change can post). A failed
 * post never changes the exit code. Only the exception class is logged:
 * the message can carry the webhook host.
 */
function postStatus(\SimRecap\SimSummaryRepository $repo, int $sim, string $host, ?string $prior, string $next): string
{
    if ($next === $prior) {
        return 'unchanged';
    }
    if (!$repo->transitionNotifiedState($sim, $prior, $next)) {
        return 'unchanged';
    }
    $row = $repo->find($sim);
    $context = ['last_cause' => $row['last_cause'] ?? null, 'blocked_until' => $row['blocked_until'] ?? null];
    $canonical = canonicalHost();
    $viewerUrl = $canonical === ''
        ? \SimRecap\SimSummaryLink::path($sim)
        : \SimRecap\SimSummaryLink::absolute($sim, $canonical);
    try {
        \Discord\Discord::postToChannel('admin-chat', \SimRecap\AdminChatStatus::message($next, $sim, $context, $host, $viewerUrl));
    } catch (\Throwable $e) {
        $repo->revertNotifiedState($sim, $next, $prior);
        fwrite(STDERR, 'simRecapQueue: admin-chat post failed (' . get_class($e) . ")\n");

        return 'failed';
    }

    return $next;
}

/**
 * Post the one-time "Sim N recap started on <host>." line after this host
 * claimed $sim. Returns 'started', 'unchanged' (already announced, or a
 * re-claim after a park), or 'failed'. The claim is already committed, so a
 * failed post never undoes it and never changes the exit code. Only the
 * exception class is logged: the message can carry the webhook host.
 */
function announceStart(\SimRecap\SimSummaryRepository $repo, int $sim, string $host): string
{
    $announcer = new \SimRecap\ClaimAnnouncer(
        $repo,
        static function (string $text): void {
            \Discord\Discord::postToChannel('admin-chat', $text);
        },
    );
    try {
        $result = $announcer->announceStart($sim, $host);
    } catch (\Throwable $e) {
        fwrite(STDERR, 'simRecapQueue: start announcement failed (' . get_class($e) . ")\n");

        return 'failed';
    }
    if ($result['error'] !== null) {
        fwrite(STDERR, 'simRecapQueue: admin-chat post failed (' . $result['error'] . ")\n");
    }

    return $result['notified'];
}

// ── Argv parse — positional verb, then flags (mirrors bug-pipeline/transition.php).
//    Runs entirely before any repository is constructed.
$verb = null;
$simRaw = null;
$host = null;
$cause = null;
$resetRaw = null;
$event = null;
foreach (array_slice($argv ?? [], 1) as $arg) {
    if (!is_string($arg)) {
        continue;
    }
    if (str_starts_with($arg, '--sim=')) {
        $simRaw = substr($arg, 6);
    } elseif (str_starts_with($arg, '--host=')) {
        $host = substr($arg, 7);
    } elseif (str_starts_with($arg, '--cause=')) {
        $cause = substr($arg, 8);
    } elseif (str_starts_with($arg, '--reset=')) {
        $resetRaw = substr($arg, 8);
    } elseif (str_starts_with($arg, '--event=')) {
        $event = substr($arg, 8);
    } elseif ($verb === null && !str_starts_with($arg, '--')) {
        $verb = $arg;
    } else {
        fail("unexpected argument: {$arg}");
    }
}

if ($verb === null) {
    fail('a verb is required (claim-next|claim|reclaim-stale|park|notify|find|recent-themes|mention-map)');
}

// Verbs that require --sim; parse and validate it once, here.
$sim = null;
if (in_array($verb, ['claim', 'park', 'notify', 'find'], true)) {
    if ($simRaw === null || !ctype_digit($simRaw)) {
        fail("--sim=N (positive integer) is required for verb {$verb}");
    }
    $sim = (int) $simRaw;
    if ($sim < 1) {
        fail('--sim must be >= 1');
    }
}

// Host, cause, reset, and event are closed whitelists, checked before any repo exists.
if (in_array($verb, ['claim-next', 'claim', 'park', 'notify'], true)) {
    if ($host === null || !in_array($host, \SimRecap\ParkPolicy::HOSTS, true)) {
        fail("--host=mac|actions is required for verb {$verb}");
    }
} elseif ($host !== null) {
    fail("--host is not accepted for verb {$verb}");
}
if ($verb === 'park') {
    if ($cause === null || !in_array($cause, \SimRecap\ParkPolicy::CAUSES, true)) {
        fail('--cause=flaky|usage_limit|auth|transport|prompt is required for verb park');
    }
} elseif ($cause !== null) {
    fail("--cause is not accepted for verb {$verb}");
}
$reset = null;
if ($resetRaw !== null) {
    if ($cause !== 'usage_limit') {
        fail('--reset is accepted only with --cause=usage_limit');
    }
    $resetParsed = filter_var($resetRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!is_int($resetParsed)) {
        fail('--reset must be a positive epoch');
    }
    $reset = $resetParsed;
}
if ($verb === 'notify') {
    if ($event !== 'done') {
        fail('--event=done is required for verb notify');
    }
} elseif ($event !== null) {
    fail("--event is not accepted for verb {$verb}");
}

// ── Dispatch ──────────────────────────────────────────────────────────────────
\Discord\Discord::init(canonicalHost());
$repo = new \SimRecap\SimSummaryRepository($db);
$seasonQuery = new \Season\SeasonQueryRepository($db);

switch ($verb) {
    case 'claim-next':
        $phase = $seasonQuery->getSeasonPhase();
        /** @var string $host */
        $claimedSim = $repo->claimNextPending($host);
        $out = [
            'ok' => true,
            'cmd' => 'claim-next',
            'sim' => $claimedSim,
            'phase' => $phase,
            'recaps_enabled' => \SimRecap\RecapPhasePolicy::isEnabled($phase),
            'notified' => $claimedSim !== null ? announceStart($repo, $claimedSim, $host) : 'skipped',
        ];
        break;

    case 'claim':
        if (!is_int($sim)) {
            fail('sim number is required for this verb');
        }
        /** @var string $host */
        $claimed = $repo->claimPending($sim, $host);
        $out = [
            'ok' => true,
            'cmd' => 'claim',
            'sim' => $sim,
            'claimed' => $claimed,
            'notified' => $claimed ? announceStart($repo, $sim, $host) : 'skipped',
        ];
        break;

    case 'reclaim-stale':
        $out = ['ok' => true, 'cmd' => 'reclaim-stale', 'sim' => $repo->reclaimStaleClaim()];
        break;

    case 'park':
        if (!is_int($sim)) {
            fail('sim number is required for this verb');
        }
        /** @var string $host */
        /** @var string $cause */
        $parked = $repo->parkByCause($sim, $host, $cause, $reset);
        $notified = 'skipped';
        if ($parked['outcome'] !== 'none') {
            $nextState = \SimRecap\ParkPolicy::notifyStateFor($parked['outcome'], $parked['route'] ?? 'any', $parked['prior_notified']);
            $notified = postStatus($repo, $sim, $host, $parked['prior_notified'], $nextState);
        }
        $out = [
            'ok' => true,
            'cmd' => 'park',
            'sim' => $sim,
            'outcome' => $parked['outcome'],
            'route' => $parked['route'],
            'blocked_until' => $parked['blocked_until'],
            'notified' => $notified,
        ];
        break;

    case 'notify':
        if ($sim === null || $host === null) {
            fail('--sim and --host are required for verb notify');
        }
        $row = $repo->find($sim);
        $notified = 'skipped';
        if ($row !== null && $row['status'] === 'done') {
            $prior = $row['notified_state'];
            $notified = postStatus($repo, $sim, $host, $prior, \SimRecap\ParkPolicy::notifyStateFor('done', $row['route'], $prior));
        }
        $out = ['ok' => true, 'cmd' => 'notify', 'sim' => $sim, 'notified' => $notified];
        break;

    case 'find':
        if (!is_int($sim)) {
            fail('sim number is required for this verb');
        }
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
        $mentionMap = new \SimRecap\MentionMapBuilder(new \Repositories\TeamIdentityRepository($db));
        $out = ['ok' => true, 'cmd' => 'mention-map', 'teams' => $mentionMap->build()];
        break;

    default:
        fail("unknown verb: {$verb}");
}

echo json_encode($out), "\n";
exit(0);
