<?php

declare(strict_types=1);

error_reporting(E_ALL);
libxml_use_internal_errors(true);

// Load mainfile first for authentication
require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';

// SECURITY: Redirect logged-out users to login
if (!function_exists('is_user') || !is_user($user ?? '')) {
    $_SESSION['redirect_after_login_path'] = 'leagueControlPanel.php'
        . (isset($_GET['league']) && $_GET['league'] === League\LeagueContext::LEAGUE_OLYMPICS ? '?league=olympics' : '');
    header('Location: ../modules.php?name=YourAccount');
    exit;
}

// SECURITY: Admin-only — logged-in non-admins get 403
if (!is_admin()) {
    http_response_code(403);
    die('Access denied. This script requires administrator privileges.');
}

// SECURITY: This is a destructive full-league mutation — POST-only closes the
// GET-based CSRF vector (e.g. <img src=".../updateAllTheThings.php">). A GET
// from a stale link/bookmark bounces back to the control panel.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../leagueControlPanel.php');
    exit;
}

// SECURITY: Validate CSRF token (form name must match the LCP View's generateToken call)
$csrfToken = isset($_POST['_csrf_token']) && is_string($_POST['_csrf_token']) ? $_POST['_csrf_token'] : '';
if (!\Security\CsrfGuard::validateToken($csrfToken, 'lcp_update_all')) {
    http_response_code(403);
    die('Invalid CSRF token.');
}

// Allow up to 5 minutes for the full update (production shared hosting has 30s default)
set_time_limit(300);

// --- Progressive output: disable every buffering layer ---

// 1. Disable PHP-level gzip compression (ignores flush() when enabled)
@ini_set('zlib.output_compression', '0');

// 2. Disable Apache mod_deflate for this response
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}

// 3. Flush and destroy all PHP output buffers (e.g. from session_start, mainfile.php)
while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

// 4. Tell proxies/servers not to buffer
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Accel-Buffering: no');          // nginx proxy buffering
    header('Content-Encoding: none');          // prevent mod_deflate compression
    header('Cache-Control: no-cache');         // prevent proxy caching
}

global $mysqli_db;

// Determine league context from the explicit POST parameter only (not cookie/session);
// the LCP's conditional `league=olympics` hidden input rides the CSRF-validated POST.
$leagueParam = isset($_POST['league']) && is_string($_POST['league']) ? $_POST['league'] : null;
$leagueContext = $leagueParam === League\LeagueContext::LEAGUE_OLYMPICS ? new League\LeagueContext() : null;
$isOlympics = $leagueContext !== null;
if ($leagueContext !== null) {
    $leagueContext->setLeague(League\LeagueContext::LEAGUE_OLYMPICS);
}

$view = new Updater\UpdaterView();

$stylesheetPath = '/ibl5/themes/IBL/style/style.css';
/** @var int|false $stylesheetMtime */
$stylesheetMtime = filemtime($_SERVER['DOCUMENT_ROOT'] . $stylesheetPath);
$cacheBuster = $stylesheetMtime !== false ? '?v=' . $stylesheetMtime : '';

echo $view->renderPageOpen($stylesheetPath . $cacheBuster);
flush();

// Set up error handler to catch all errors with XSS-safe output
set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) use ($view): bool {
    $safeMessage = \Security\HtmlSanitizer::safeHtmlOutput(
        "[$errno] $errstr in $errfile on line $errline"
    );
    echo $view->renderStepError('PHP Error', (string) $safeMessage);
    flush();
    return false;
});

// Set up exception handler with XSS-safe output
set_exception_handler(function (\Throwable $exception) use ($view): void {
    $safeMessage = \Security\HtmlSanitizer::safeHtmlOutput($exception->getMessage());
    $safeFile = \Security\HtmlSanitizer::safeHtmlOutput($exception->getFile());
    $safeTrace = \Security\HtmlSanitizer::safeHtmlOutput($exception->getTraceAsString());

    echo $view->renderStepError(
        'Uncaught Exception',
        (string) $safeMessage . ' in ' . (string) $safeFile . ' on line ' . $exception->getLine()
    );
    echo $view->renderLog('<pre>' . (string) $safeTrace . '</pre>');
    flush();
});

$basePath = $_SERVER['DOCUMENT_ROOT'] . '/ibl5';

try {
    // --- Initialization ---
    $leagueLabel = $isOlympics ? 'Olympics' : 'IBL';
    echo $view->renderSectionOpen("Initialization ({$leagueLabel})");
    flush();

    echo $view->renderInitStatus('mainfile.php loaded');
    flush();

    $season = new \Season\Season($mysqli_db);

    // Season year override for historical imports (e.g., Olympics 2003)
    $seasonYearOverride = isset($_POST['season_year']) && is_string($_POST['season_year'])
        ? (int) $_POST['season_year'] : null;
    if ($seasonYearOverride !== null && $seasonYearOverride > 1900 && $seasonYearOverride < 2100) {
        $season->endingYear = $seasonYearOverride;
        $season->beginningYear = $seasonYearOverride - 1;
    }

    echo $view->renderInitStatus('Season initialized');
    flush();

    $filePrefix = $leagueContext !== null ? $leagueContext->getFilePrefix() : 'IBL5';


    // --- Pipeline: register all steps and delegate to controller ---
    $updaterService = new Updater\UpdaterService();

    $lgeRepo = new LeagueConfig\LeagueConfigRepository($mysqli_db, $leagueContext);
    $lgeService = new LeagueConfig\LeagueConfigService($lgeRepo);
    $lgeView = new LeagueConfig\LeagueConfigView();

    $plrRepo = new PlrParser\PlrParserRepository($mysqli_db, $leagueContext);
    $plrService = new PlrParser\PlrParserService($plrRepo, $season);

    $boxscoreProcessor = new Boxscore\BoxscoreProcessor($mysqli_db, null, $season, $leagueContext);
    $boxscoreRepo = new Boxscore\BoxscoreRepository($mysqli_db, $leagueContext);
    $boxscoreView = new Boxscore\BoxscoreView();

    $savedDcRepo = new SavedDepthChart\SavedDepthChartRepository($mysqli_db, $leagueContext);

    $jsbRepo = new JsbParser\JsbImportRepository($mysqli_db, $leagueContext);
    $jsbResolver = new JsbParser\PlayerIdResolver($mysqli_db, $leagueContext);
    $jsbService = new JsbParser\JsbImportService($jsbRepo, $jsbResolver);

    // Step 0: Extract JSB files from latest backup archive
    $archiveExtractor = new BulkImport\ArchiveExtractor();
    $backupLocator = new BulkImport\BackupArchiveLocator($archiveExtractor);
    // JsbSourceResolver reads .lge/.sch directly from archive (disk-fallback)
    $seasonLabel = BulkImport\BackupArchiveLocator::seasonLabel($season->beginningYear, $season->endingYear);
    $seasonBackupDir = $basePath . '/backups/' . ($isOlympics ? 'olympics/' : '') . $seasonLabel;

    $updaterService->addStep(new Updater\Steps\ExtractFromBackupStep(
        $backupLocator, $season, $basePath,
        $isOlympics ? $seasonBackupDir : null,
        $isOlympics,
    ));
    $sourceResolver = new Updater\JsbSourceResolver(
        $backupLocator, $archiveExtractor, $seasonBackupDir, $basePath, $filePrefix,
    );

    $updaterService->addStep(new Updater\Steps\ImportLeagueConfigStep(
        $lgeRepo, $lgeService, $lgeView, $season->endingYear, $sourceResolver, $leagueContext,
    ));

    if ($isOlympics) {
        $realTeamCountParam = isset($_POST['real_team_count']) && is_string($_POST['real_team_count'])
            ? (int) $_POST['real_team_count'] : null;
        $updaterService->addStep(new Updater\Steps\AutoSeedOlympicsTeamInfoStep(
            $mysqli_db, $season->endingYear, $realTeamCountParam,
        ));
    }

    $scheduleUpdater = new Updater\ScheduleUpdater($mysqli_db, $season, $leagueContext, $sourceResolver);
    echo $view->renderInitStatus('ScheduleUpdater initialized');
    flush();

    $standingsRepo = new Standings\StandingsRepository($mysqli_db, $leagueContext);
    $standingsUpdater = $isOlympics
        ? new Updater\OlympicsFlatStandingsUpdater($standingsRepo, $season, true)
        : new Updater\StandingsUpdater($standingsRepo, $season);
    echo $view->renderInitStatus('StandingsUpdater initialized');
    flush();

    $powerRankingsUpdater = new Updater\PowerRankingsUpdater($mysqli_db, $season, null, $leagueContext);
    echo $view->renderInitStatus('PowerRankingsUpdater initialized');
    flush();

    echo $view->renderSectionClose();
    flush();

    $updaterService->addStep(new Updater\Steps\ParsePlayerFileStep($plrService, $sourceResolver));

    // IBL-only: clean preseason data on first Regular Season sim
    if (!$isOlympics) {
        $updaterService->addStep(new Updater\Steps\CleanupPreseasonDataStep(
            $boxscoreRepo, $season, $mysqli_db, $leagueContext,
        ));
    }

    $updaterService->addStep(new Updater\Steps\UpdateScheduleStep($scheduleUpdater));
    $updaterService->addStep(new Updater\Steps\UpdateStandingsStep($standingsUpdater));
    $updaterService->addStep(new Updater\Steps\UpdatePowerRankingsStep($powerRankingsUpdater));

    // IBL-only: contract extensions don't exist in Olympics (ibl_olympics_team_info lacks used_extension_this_chunk)
    if (!$isOlympics) {
        $updaterService->addStep(new Updater\Steps\ResetExtensionAttemptsStep($mysqli_db));
    }
    $updaterService->addStep(new Updater\Steps\ExtendDepthChartsStep(
        $savedDcRepo, $season->lastSimEndDate, $season->lastSimNumber,
    ));

    $updaterService->addStep(new Updater\Steps\ProcessBoxscoresStep(
        $boxscoreProcessor, $boxscoreView, $sourceResolver,
    ));

    // IBL-only: All-Star games don't exist in Olympics
    if (!$isOlympics) {
        $updaterService->addStep(new Updater\Steps\ProcessAllStarGamesStep(
            $boxscoreProcessor, $boxscoreRepo, $boxscoreView, $sourceResolver,
        ));
        $updaterService->addStep(new Updater\Steps\RefreshPlayoffSeriesResultsStep($mysqli_db));
        $updaterService->addStep(new Updater\Steps\RefreshTeamSeasonRecordsStep($mysqli_db));
    }

    $updaterService->addStep(new Updater\Steps\ParseJsbFilesStep($jsbService, $sourceResolver, $season->endingYear));

    // IBL-only: Season awards + end-of-season imports when champion exists
    if (!$isOlympics) {
        $lcpRepo = new LeagueControlPanel\LeagueControlPanelRepository($mysqli_db);
        $seasonPhase = $lcpRepo->getSetting('Current Season Phase') ?? '';
        $eoyVotesCast = $lcpRepo->getEoyVotesCastCount();
        $awardsAlreadyGenerated = $lcpRepo->hasGeneratedAwardsForYear($season->endingYear);
        $leadersHtmExists = file_exists($basePath . '/Leaders.htm');
        $hasFinalsMvp = $lcpRepo->hasFinalsMvp($season->endingYear);

        $updaterService->addStep(new Updater\Steps\GenerateSeasonAwardsStep(
            $seasonPhase,
            $season->endingYear,
            $eoyVotesCast,
            League\League::MAX_REAL_TEAMID,
            $awardsAlreadyGenerated,
            $leadersHtmExists,
        ));

        $updaterService->addStep(new Updater\Steps\EndOfSeasonImportStep(
            $jsbRepo, $jsbService, $season->endingYear, $sourceResolver, $hasFinalsMvp,
        ));
    }

    // IBL-only: snapshot player stats + refresh materialized ibl_hist table
    if (!$isOlympics) {
        $updaterService->addStep(new Updater\Steps\SnapshotPlrStep(
            $plrService, $jsbRepo, $season->endingYear, $sourceResolver,
        ));
        $updaterService->addStep(new Updater\Steps\RefreshIblHistStep($mysqli_db));
        $updaterService->addStep(new Updater\Steps\QueueSimSummaryStep(
            new \SimRecap\SimSummaryRepository($mysqli_db),
            new \Season\SeasonQueryRepository($mysqli_db),
        ));
    }

    $controller = new Updater\UpdaterController($updaterService, $view);
    $controller->run();

    // Shadow sim: run the native Go engine over the full season and write its output
    // to droppable shadow tables for engine-vs-JSB comparison. Default OFF. Spawned
    // OUT-OF-BAND as a detached process (see ADR-0037) so a long/heavy/crashing
    // full-season run can never block or break this synchronous admin request —
    // shadow only READS inputs and writes droppable diagnostic tables. IBL-only (the
    // engine bundle is IBL-scoped), so the gate stays !$isOlympics. Fire-and-forget:
    // the admin request returns immediately and the shadow process outlives it.
    if (!$isOlympics && filter_var(getenv('ENGINE_SHADOW_ENABLED') ?: '', FILTER_VALIDATE_BOOLEAN)) {
        $shadowLauncher = new EngineShadow\ShadowProcessLauncher(
            $basePath . '/scripts/runEngineShadow.php',
            sys_get_temp_dir() . '/ibl5-engine-shadow.log',
        );
        $shadowLauncher->launch(); // detached; never blocks or aborts the request
    }

} catch (\Exception $e) {
    $safeMessage = \Security\HtmlSanitizer::safeHtmlOutput($e->getMessage());
    $safeTrace = \Security\HtmlSanitizer::safeHtmlOutput($e->getTraceAsString());

    echo $view->renderStepError('Exception', (string) $safeMessage);
    echo $view->renderLog('<pre>' . (string) $safeTrace . '</pre>');
    echo $view->renderSummary(0, 1);
    flush();
}

echo $view->renderPageClose();
flush();
