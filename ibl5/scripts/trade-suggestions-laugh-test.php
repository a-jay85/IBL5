<?php

declare(strict_types=1);

/**
 * The trade suggestions laugh test over a whole league database. Builds every
 * team's panel and checks each shown deal again: no veto fires, both sides
 * gain, and neither side gains more than three times the other. Exits 1 when
 * any shown deal breaks one of these.
 *
 *   php scripts/trade-suggestions-laugh-test.php --max-teams=28
 *
 * Connection flags default to DB_HOST, DB_NAME, DB_USER and DB_PASS.
 *
 * A CLI script does not bootstrap ModuleServices, so it cannot call
 * NewsFactory. Keep the graph below in step with NewsFactory::controller():
 * the same classes in the same order, with one ModeResolver shared by the
 * loader, the provider and the service.
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

use TradeSuggestions\Engine\Sweep\LeagueSweepArgs;

$env = [];
foreach (['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'] as $name) {
    $value = getenv($name);
    if (is_string($value)) {
        $env[$name] = $value;
    }
}

try {
    $cliArgs = is_array($_SERVER['argv'] ?? null) ? array_slice($_SERVER['argv'], 1) : [];
    $parsed = LeagueSweepArgs::parse(array_values(array_filter($cliArgs, 'is_string')), $env);
} catch (\InvalidArgumentException $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL . LeagueSweepArgs::USAGE . PHP_EOL);
    exit(2);
}

$db = new \mysqli($parsed['host'], $parsed['user'], $parsed['pass'], $parsed['db']);
$season = new \Season\Season($db);

$tradeFormRepo = new \Trading\TradeFormRepository($db);
$tradeValidator = new \Trading\TradeValidator($db, $season);
$teamModeRepo = new \TradeSuggestions\TeamModeRepository($db);

$assetRepo = new \TradeSuggestions\SuggestionAssetRepository($db);
$draftOrder = new \ProjectedDraftOrder\ProjectedDraftOrderService(new \ProjectedDraftOrder\ProjectedDraftOrderRepository($db));
$modeResolver = new \TradeSuggestions\Engine\ModeResolver(
    $assetRepo,
    $draftOrder,
    $teamModeRepo,
    $tradeFormRepo,
    new \TradeSuggestions\TeamModeService($teamModeRepo, new \TradeSuggestions\TeamModeGuesser()),
    $season,
);
$snapshots = new \TradeSuggestions\Engine\CachedLeagueSnapshotProvider(
    new \TradeSuggestions\Engine\LeagueSnapshotLoader(
        new \TradeSuggestions\Projection\ProjectionPlayerRepository($db),
        new \TradeSuggestions\Valuation\ValuationTeamFactorsRepository($db),
        new \Standings\StandingsRepository($db),
        $draftOrder,
        $assetRepo,
        $tradeFormRepo,
        $modeResolver,
        $season,
    ),
    new \Cache\DatabaseCache($db),
    new \TradeSuggestions\Engine\SnapshotKeyRepository($db),
    $modeResolver,
    $season,
);

$vetoes = \TradeSuggestions\Veto\VetoSet::standard();
/** @var array<mixed> $panelStore The CLI has no session; the panel state lives in this array. */
$panelStore = [];
$service = new \TradeSuggestions\TradeSuggestionsService(
    $snapshots,
    new \TradeSuggestions\Engine\CandidateGenerator([
        new \TradeSuggestions\Engine\Shape\OneForOne(),
        new \TradeSuggestions\Engine\Shape\TwoForOne(),
        new \TradeSuggestions\Engine\Shape\OneForTwo(),
        new \TradeSuggestions\Engine\Shape\StarForYoungAndPicks(),
        new \TradeSuggestions\Engine\Shape\DumpPaidWithPick(),
        new \TradeSuggestions\Engine\Shape\ExpiringForLong(),
        new \TradeSuggestions\Engine\Shape\PickSwap(),
        new \TradeSuggestions\Engine\Shape\ChangeOfScenery(),
    ]),
    $vetoes,
    new \TradeSuggestions\Engine\DealRanker(),
    new \TradeSuggestions\SuggestionValidationFilter(
        $tradeValidator,
        new \Trading\TradeCapCalculator(
            new \Repositories\TeamIdentityRepository($db),
            new \Trading\BuyoutLedgerRepository($db),
            $season,
            $tradeValidator,
        ),
        $tradeFormRepo,
        $season,
    ),
    $tradeFormRepo,
    new \Settings\SettingsService($season),
    $modeResolver,
    new \TradeSuggestions\Feedback\FeedbackRepository($db),
    new \TradeSuggestions\Feedback\PanelSessionState($panelStore),
);

$command = new \TradeSuggestions\Engine\Sweep\LeagueSweepCommand($service, $snapshots, $vetoes, $tradeFormRepo);
exit($command->run($parsed['maxTeams'], STDOUT));
