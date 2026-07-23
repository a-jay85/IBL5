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

// Plain-text export of a single recap body
if ($wantsTxt) {
    if ($sim === null || $row === null || $row['recap_text'] === null) {
        http_response_code($simError === 'malformed' || $sim === null ? 400 : 404);
        header('Content-Type: text/plain; charset=utf-8');
        echo "No recap text available.\n";
        exit;
    }

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="sim-' . $sim . '-recap.txt"');
    header('X-Content-Type-Options: nosniff');
    echo (string) $row['recap_text'];
    exit;
}

$rows = $repo->listAll();

// Per-game recaps only accompany a stored envelope body; a pending/failed row
// renders its status message instead of a game list (plan Phase 3b).
$gameRecaps = [];
if ($sim !== null && $row !== null && $row['recap_text'] !== null) {
    $gameRecaps = $repo->findDisplayableGameRecaps($sim);
}

if ($simError === 'malformed') {
    http_response_code(400);
} elseif ($simError === 'notfound') {
    http_response_code(404);
}

// $sim is passed so the 'notfound' notice can name the sim it could not find.
echo (new \SimRecap\SimSummariesView())->render($rows, $row, $gameRecaps, $simError, $sim);
