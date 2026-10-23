<?php

declare(strict_types=1);

require __DIR__ . '/mainfile.php';

// Auth guard
if (!is_user($user)) {
    $_SESSION['redirect_after_login_path'] = 'simSummaries.php';
    \Utilities\HtmxHelper::redirect('modules.php?name=YourAccount');
}

if (!is_admin()) {
    http_response_code(403);
    echo 'Access denied. Administrator privileges required.';
    exit;
}

const SIM_MAX = 4294967295; // INT UNSIGNED ceiling of ibl_sim_summaries.sim

// A non-string `sim` (e.g. `?sim[]=1`) is treated as malformed rather than cast.
$simParam = $_GET['sim'] ?? null;
$rawSim   = is_string($simParam) ? $simParam : ($simParam === null ? '' : 'invalid');
$wantsTxt = (($_GET['format'] ?? '') === 'txt');

$simError = null;   // 'malformed' | 'notfound' | null
$sim      = null;   // int, only ever set from a fully validated value
$row      = null;

if ($rawSim !== '') {
    if (!ctype_digit($rawSim) || strlen($rawSim) > 10) {
        $simError = 'malformed';
    } else {
        $candidate = (int) $rawSim;
        if ($candidate < 1 || $candidate > SIM_MAX) {
            $simError = 'malformed';
        } else {
            $sim = $candidate;
        }
    }
}

$repo = new \SimRecap\SimSummaryRepository($mysqli_db);
if ($sim !== null) {
    $row = $repo->find($sim);          // bound int parameter, never string concatenation
    if ($row === null) {
        $simError = 'notfound';
    }
}

// The links footer points at the canonical host, the same prod-side constant
// scripts/storeSimRecap.php reads. Empty means production (RecapDocument resolves it).
$rawHost       = defined('IBL5_CANONICAL_HOST') ? constant('IBL5_CANONICAL_HOST') : '';
$canonicalHost = is_string($rawHost) ? $rawHost : '';

// Plain-text export of a single recap body
if ($wantsTxt) {
    if ($sim === null || $row === null || $row['recap_text'] === null) {
        http_response_code($simError === 'malformed' || $sim === null ? 400 : 404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "No recap text available.\n";
        exit;
    }

    // recap_text is usually a ~220-char teaser; assemble the full document from the game
    // parts before emitting headers so a fatal in assembly cannot produce partial output.
    $document  = \SimRecap\RecapDocument::postableText(
        $repo->findDisplayableGameRecaps($sim),
        (string) $row['recap_text'],
        $canonicalHost
    );
    $orphanCount = count($repo->findOrphanedGameRecaps($sim));

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="sim-' . $sim . '-recap.txt"');
    header('X-Content-Type-Options: nosniff');
    header('X-Recap-Orphaned-Games: ' . $orphanCount);
    echo $document;
    exit;
}

$rows = $repo->listAll();

// Per-game recaps only accompany a stored envelope body; a pending/failed row
// renders its status message instead of a game list (plan Phase 3b).
$gameRecaps    = [];
$orphanedGames = [];
if ($sim !== null && $row !== null && $row['recap_text'] !== null) {
    $gameRecaps    = $repo->findDisplayableGameRecaps($sim);
    $orphanedGames = $repo->findOrphanedGameRecaps($sim);
}

if ($simError === 'malformed') {
    http_response_code(400);
} elseif ($simError === 'notfound') {
    http_response_code(404);
}

// $sim is passed so the 'notfound' notice can name the sim it could not find.
echo (new \SimRecap\SimSummariesView($canonicalHost))->render($rows, $row, $gameRecaps, $simError, $sim, $orphanedGames);
