<?php

declare(strict_types=1);

error_reporting(E_ALL);
libxml_use_internal_errors(true);

// Load mainfile first for authentication
require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';

// SECURITY: Redirect logged-out users to login
if (!function_exists('is_user') || !is_user($user ?? '')) {
    $_SESSION['redirect_after_login_path'] = 'scripts/updateAllTheThings.php'
        . (isset($_GET['league']) && $_GET['league'] === League\LeagueContext::LEAGUE_OLYMPICS ? '?league=olympics' : '');
    header('Location: ../modules.php?name=YourAccount');
    exit;
}

// SECURITY: Admin-only — logged-in non-admins get 403
if (!is_admin()) {
    http_response_code(403);
    die('Access denied. This script requires administrator privileges.');
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

// Determine league context from explicit URL parameter only (not cookie/session)
$leagueParam = isset($_GET['league']) && is_string($_GET['league']) ? $_GET['league'] : null;
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
    $safeMessage = \Utilities\HtmlSanitizer::safeHtmlOutput(
        "[$errno] $errstr in $errfile on line $errline"
    );
    echo $view->renderStepError('PHP Error', (string) $safeMessage);
    flush();
    return false;
});

// Set up exception handler with XSS-safe output
set_exception_handler(function (\Throwable $exception) use ($view): void {
    $safeMessage = \Utilities\HtmlSanitizer::safeHtmlOutput($exception->getMessage());
    $safeFile = \Utilities\HtmlSanitizer::safeHtmlOutput($exception->getFile());
    $safeTrace = \Utilities\HtmlSanitizer::safeHtmlOutput($exception->getTraceAsString());

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

    $commonRepository = new \Services\CommonMysqliRepository($mysqli_db);
    echo $view->renderInitStatus('CommonRepository initialized');
    flush();

    $season = new \Season\Season($mysqli_db);

    $sharedRepository = new Shared\SharedRepository($mysqli_db, $leagueContext);
    echo $view->renderInitStatus('Shared repository initialized');
    flush();

    // Season year override for historical imports (e.g., Olympics 2003)
    $seasonYearOverride = isset($_GET['season_year']) && is_string($_GET['season_year'])
        ? (int) $_GET['season_year'] : null;
    if ($seasonYearOverride !== null && $seasonYearOverride > 1900 && $seasonYearOverride < 2100) {
        $season->endingYear = $seasonYearOverride;
        $season->beginningYear = $seasonYearOverride - 1;
    }

    echo $view->renderInitStatus('Season initialized');
    flush();

    $filePrefix = $leagueContext !== null ? $leagueContext->getFilePrefix() : 'IBL5';


    // --- Pipeline: register all steps and delegate to controller ---
    $updaterService = new Updater\UpdaterService();

    $defaultPlrPath = $basePath . '/' . $filePrefix . '.plr';
    $defaultScoPath = $basePath . '/' . $filePrefix . '.sco';

    $lgeRepo = new LeagueConfig\LeagueConfigRepository($mysqli_db, $leagueContext);
    $lgeService = new LeagueConfig\LeagueConfigService($lgeRepo);
    $lgeView = new LeagueConfig\LeagueConfigView();

    $plrRepo = new PlrParser\PlrParserRepository($mysqli_db, $leagueContext);
    $plrService = new PlrParser\PlrParserService($plrRepo, $commonRepository, $season);

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
    $updaterService->addStep(new Updater\Steps\ExtractFromBackupStep(
        $backupLocator, $archiveExtractor, $season, $basePath, $filePrefix,
    ));

    // JsbSourceResolver reads .lge/.sch directly from archive (disk-fallback)
    $seasonLabel = BulkImport\BackupArchiveLocator::seasonLabel($season->beginningYear, $season->endingYear);
    $seasonBackupDir = $basePath . '/backups/' . $seasonLabel;
    $sourceResolver = new Updater\JsbSourceResolver(
        $backupLocator, $archiveExtractor, $seasonBackupDir, $basePath, $filePrefix,
    );

    $updaterService->addStep(new Updater\Steps\ImportLeagueConfigStep(
        $lgeRepo, $lgeService, $lgeView, $season->endingYear, $sourceResolver,
    ));

    $scheduleUpdater = new Updater\ScheduleUpdater($mysqli_db, $season, $leagueContext, $sourceResolver);
    echo $view->renderInitStatus('ScheduleUpdater initialized');
    flush();

    $standingsUpdater = new Updater\StandingsUpdater($mysqli_db, $season, $leagueContext);
    echo $view->renderInitStatus('StandingsUpdater initialized');
    flush();

    $powerRankingsUpdater = new Updater\PowerRankingsUpdater($mysqli_db, $season, null, $leagueContext);
    echo $view->renderInitStatus('PowerRankingsUpdater initialized');
    flush();

    echo $view->renderSectionClose();
    flush();

    $updaterService->addStep(new Updater\Steps\ParsePlayerFileStep($plrService, $defaultPlrPath));

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
        $updaterService->addStep(new Updater\Steps\ResetExtensionAttemptsStep($sharedRepository));
    }
    $updaterService->addStep(new Updater\Steps\ExtendDepthChartsStep(
        $savedDcRepo, $season->lastSimEndDate, $season->lastSimNumber,
    ));

    $updaterService->addStep(new Updater\Steps\ProcessBoxscoresStep(
        $boxscoreProcessor, $boxscoreView, $defaultScoPath,
    ));

    // IBL-only: All-Star games don't exist in Olympics
    if (!$isOlympics) {
        $updaterService->addStep(new Updater\Steps\ProcessAllStarGamesStep(
            $boxscoreProcessor, $boxscoreRepo, $boxscoreView, $defaultScoPath,
        ));
    }

    $updaterService->addStep(new Updater\Steps\ParseJsbFilesStep($jsbService, $basePath, $season, $filePrefix));

    // IBL-only: End-of-season imports when champion exists
    if (!$isOlympics) {
        $updaterService->addStep(new Updater\Steps\EndOfSeasonImportStep(
            $jsbRepo, $jsbService, $season->endingYear, $basePath, $filePrefix,
        ));
    }

    // IBL-only: snapshot player stats + refresh materialized ibl_hist table
    if (!$isOlympics) {
        $updaterService->addStep(new Updater\Steps\SnapshotPlrStep(
            $plrService, $jsbRepo, $season->endingYear, $defaultPlrPath,
        ));
        $updaterService->addStep(new Updater\Steps\RefreshIblHistStep($mysqli_db));
    }

    $controller = new Updater\UpdaterController($updaterService, $view);
    $controller->run();

} catch (\Exception $e) {
    $safeMessage = \Utilities\HtmlSanitizer::safeHtmlOutput($e->getMessage());
    $safeTrace = \Utilities\HtmlSanitizer::safeHtmlOutput($e->getTraceAsString());

    echo $view->renderStepError('Exception', (string) $safeMessage);
    echo $view->renderLog('<pre>' . (string) $safeTrace . '</pre>');
    echo $view->renderSummary(0, 1);
    flush();
}

echo $view->renderPageClose();
flush();
