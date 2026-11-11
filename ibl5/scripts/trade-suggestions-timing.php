<?php

declare(strict_types=1);

/**
 * Times the trade suggestions engine against a league database: a cold
 * snapshot, a warm snapshot, and one GM's generate-and-score pass.
 *
 *   php scripts/trade-suggestions-timing.php --tid=1 --repeat=3
 *
 * Connection flags default to DB_HOST, DB_NAME, DB_USER and DB_PASS.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'This script must be run from the command line.';
    exit(1);
}

require_once __DIR__ . '/../vendor/autoload.php';

// A worktree symlinks vendor/ to the main checkout. Prefer this tree's
// classes/, the way Bootstrap\AutoloaderBootstrap does for web requests.
if (is_link(__DIR__ . '/../vendor')) {
    $worktreeClasses = realpath(__DIR__ . '/../classes');
    if ($worktreeClasses !== false) {
        spl_autoload_register(static function (string $class) use ($worktreeClasses): void {
            $file = $worktreeClasses . '/' . str_replace('\\', '/', $class) . '.php';
            if (file_exists($file)) {
                require $file;
            }
        }, true, true);
    }
}

use TradeSuggestions\Engine\Timing\SnapshotTimingArgs;

$env = [];
foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $name) {
    $value = getenv($name);
    if (is_string($value)) {
        $env[$name] = $value;
    }
}

try {
    $cliArgs = is_array($_SERVER['argv'] ?? null) ? array_slice($_SERVER['argv'], 1) : [];
    $parsed = SnapshotTimingArgs::parse(array_values(array_filter($cliArgs, 'is_string')), $env);
} catch (\InvalidArgumentException $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL . SnapshotTimingArgs::USAGE . PHP_EOL);
    exit(2);
}

$db = new \mysqli($parsed['host'], $parsed['user'], $parsed['pass'], $parsed['db']);
$season = new \Season\Season($db);

$formRepo = new \Trading\TradeFormRepository($db);
$teamModeRepo = new \TradeSuggestions\TeamModeRepository($db);
$assetRepo = new \TradeSuggestions\SuggestionAssetRepository($db);
$draftOrder = new \ProjectedDraftOrder\ProjectedDraftOrderService(new \ProjectedDraftOrder\ProjectedDraftOrderRepository($db));
$modes = new \TradeSuggestions\Engine\ModeResolver(
    $assetRepo,
    $draftOrder,
    $teamModeRepo,
    $formRepo,
    new \TradeSuggestions\TeamModeService($teamModeRepo, new \TradeSuggestions\TeamModeGuesser()),
    $season,
);
$loader = new \TradeSuggestions\Engine\LeagueSnapshotLoader(
    new \TradeSuggestions\Projection\ProjectionPlayerRepository($db),
    new \TradeSuggestions\Valuation\ValuationTeamFactorsRepository($db),
    new \Standings\StandingsRepository($db),
    $draftOrder,
    $assetRepo,
    $formRepo,
    $modes,
    $season,
);
$cache = new \Cache\DatabaseCache($db);
$provider = new \TradeSuggestions\Engine\CachedLeagueSnapshotProvider(
    $loader,
    $cache,
    new \TradeSuggestions\Engine\SnapshotKeyRepository($db),
    $modes,
    $season,
);
$generator = new \TradeSuggestions\Engine\CandidateGenerator([
    new \TradeSuggestions\Engine\Shape\OneForOne(),
    new \TradeSuggestions\Engine\Shape\TwoForOne(),
    new \TradeSuggestions\Engine\Shape\OneForTwo(),
    new \TradeSuggestions\Engine\Shape\StarForYoungAndPicks(),
    new \TradeSuggestions\Engine\Shape\DumpPaidWithPick(),
    new \TradeSuggestions\Engine\Shape\ExpiringForLong(),
    new \TradeSuggestions\Engine\Shape\PickSwap(),
    new \TradeSuggestions\Engine\Shape\ChangeOfScenery(),
]);

exit((new \TradeSuggestions\Engine\Timing\SnapshotTimingCommand($provider, $generator, $cache))->run($parsed, STDOUT));
