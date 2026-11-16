<?php

declare(strict_types=1);

require __DIR__ . '/../mainfile.php';

$nukeCompat = new \Utilities\NukeCompat();

// SECURITY: Redirect logged-out users to login
if (!function_exists('is_user') || !$nukeCompat->isUser($user ?? '')) {
    header('Location: ../modules.php?name=YourAccount');
    exit;
}

// SECURITY: Admin-only (NukeCompat::isAdmin() wraps is_admin()) — logged-in non-admins get 403
if (!$nukeCompat->isAdmin()) {
    http_response_code(403);
    die('Access denied. This script requires administrator privileges.');
}

if (!isset($_POST['confirmed'])) {
    $csrfToken = \Security\CsrfGuard::generateRawToken('jsb_export');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            .modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 1000;
            }
            .modal-content {
                background: white;
                padding: 20px;
                border-radius: 5px;
                text-align: center;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            }
            .modal-buttons {
                margin-top: 20px;
            }
            .btn-run {
                background: #dc3545;
                color: white;
                border: none;
                padding: 10px 20px;
                margin: 0 10px;
                border-radius: 5px;
                cursor: pointer;
            }
            .btn-cancel {
                background: white;
                color: black;
                border: 1px solid #ccc;
                padding: 10px 20px;
                margin: 0 10px;
                border-radius: 5px;
                cursor: pointer;
            }
        </style>
    </head>
    <body>
        <div class="modal-overlay">
            <div class="modal-content">
                <h2>JSB File Export</h2>
                <p>This will export current database state to JSB files.</p>
                <p>
                    <b>PLR Export:</b> Reads <code>IBL5.plr</code>, applies contracts, trades (moved to the new team's slots), depth, and active flags,
                    writes to <code>IBL5_export.plr</code><br>
                    <b>PLB Export:</b> Writes <code>IBL5_export.plb</code> with minutes and OF/DF/OI/DI/BH for teams with a site depth chart<br>
                    <b>TRN Export:</b> Generates <code>IBL5_export.trn</code> from trade data
                </p>
                <p><em>Source files are never overwritten.</em></p>
                <div class="modal-buttons">
                    <form method="POST">
                        <input type="hidden" name="confirmed" value="1">
                        <input type="hidden" name="_csrf_token" value="<?= \Security\HtmlSanitizer::safeHtmlOutput($csrfToken) ?>">
                        <button type="submit" class="btn-run">Run Export</button>
                        <button type="button" class="btn-cancel" onclick="window.history.back()">Cancel</button>
                    </form>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// SECURITY: Validate CSRF token (form name must match the generateRawToken call above)
if (!\Security\CsrfGuard::validateSubmittedToken('jsb_export')) {
    http_response_code(403);
    die('Invalid CSRF token.');
}

use JsbParser\JsbExportRepository;
use JsbParser\JsbExportService;
use JsbParser\PlbExportService;
use PlrParser\PlrExportService;
use PlrParser\PlrWriteResult;
use Security\HtmlSanitizer;

/** @var \mysqli $mysqli_db */
$repository = new JsbExportRepository($mysqli_db);
$service = new JsbExportService($repository);
$plrExportService = new PlrExportService($repository);
$season = new \Season\Season($mysqli_db);

$basePath = __DIR__ . '/..';
$plrOutput = $basePath . '/IBL5_export.plr';
$trnOutput = $basePath . '/IBL5_export.trn';
$plbOutput = $basePath . '/IBL5_export.plb';

// Sources: the newest backup archive for this season, else IBL5.plr / IBL5.plb on disk.
// The bytes go to a private temp dir, so the files on disk are only ever read.
$archiveExtractor = new \BulkImport\ArchiveExtractor();
$sourceResolver = new \Updater\JsbSourceResolver(
    new \BulkImport\BackupArchiveLocator($archiveExtractor),
    $archiveExtractor,
    $basePath . '/backups/' . \BulkImport\BackupArchiveLocator::seasonLabel($season->beginningYear, $season->endingYear),
    $basePath,
    'IBL5',
);
$tmpDir = sys_get_temp_dir() . '/jsb_export_' . bin2hex(random_bytes(8));
mkdir($tmpDir, 0700);
$plrInput = $tmpDir . '/IBL5.plr';
$plbInput = $tmpDir . '/IBL5.plb';

$renderResult = static function (PlrWriteResult $result): void {
    foreach ($result->messages as $msg) {
        $class = str_starts_with($msg, 'ERROR') || str_starts_with($msg, 'WARNING') ? 'error' : '';
        echo '<p class="' . $class . '">' . HtmlSanitizer::safeHtmlOutput($msg) . '</p>';
    }

    echo '<p class="success"><b>' . HtmlSanitizer::safeHtmlOutput($result->summary()) . '</b></p>';

    if ($result->changeLog !== []) {
        echo '<h3>Change Details</h3>';
        echo '<table><tr><th>PID</th><th>Player</th><th>Field</th><th>Old</th><th>New</th></tr>';
        foreach ($result->changeLog as $entry) {
            foreach ($entry['changes'] as $change) {
                echo '<tr>';
                echo '<td>' . HtmlSanitizer::safeHtmlOutput($entry['pid']) . '</td>';
                echo '<td>' . HtmlSanitizer::safeHtmlOutput($entry['name']) . '</td>';
                echo '<td class="change">' . HtmlSanitizer::safeHtmlOutput($change['field']) . '</td>';
                echo '<td>' . HtmlSanitizer::safeHtmlOutput($change['old']) . '</td>';
                echo '<td>' . HtmlSanitizer::safeHtmlOutput($change['new']) . '</td>';
                echo '</tr>';
            }
        }
        echo '</table>';
    }
};

echo '<html><head><style>
    body { font-family: monospace; padding: 20px; }
    .success { color: #28a745; }
    .error { color: #dc3545; }
    .change { color: #0066cc; }
    table { border-collapse: collapse; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 4px 8px; text-align: left; }
    th { background: #f5f5f5; }
</style></head><body>';

try {
    $sourceNotes = [];
    foreach (['plr' => $plrInput, 'plb' => $plbInput] as $extension => $tmpPath) {
        $bytes = $sourceResolver->getContents($extension);
        $source = $sourceResolver->describeLastSource();
        if ($bytes === null || $source === null) {
            $sourceNotes[] = 'IBL5.' . $extension . ' source: none found';
            continue;
        }
        file_put_contents($tmpPath, $bytes);
        $sourceNotes[] = 'IBL5.' . $extension . ' source: ' . $source->kind . ' ' . $source->name;
    }

    // ── PLR Export ─────────────────────────────────────────────────────

    echo '<h2>PLR Export</h2>';

    foreach ($sourceNotes as $note) {
        echo '<p>' . HtmlSanitizer::safeHtmlOutput($note) . '</p>';
    }

    $plrResult = null;
    if (!file_exists($plrInput)) {
        echo '<p class="error">ERROR: IBL5.plr not found in the season backup archive or at ' . HtmlSanitizer::safeHtmlOutput($basePath . '/IBL5.plr') . '</p>';
    } else {
        $plrResult = $plrExportService->exportPlrFile($plrInput, $plrOutput);
        $renderResult($plrResult);
    }

    // ── PLB Export ─────────────────────────────────────────────────────

    echo '<h2>PLB Export</h2>';

    $plrWrote = $plrResult !== null && $plrResult->errors === 0 && array_filter(
        $plrResult->messages,
        static fn (string $msg): bool => str_starts_with($msg, 'Wrote '),
    ) !== [];
    if (!$plrWrote) {
        echo '<p class="error">ERROR: PLB export skipped because the PLR export did not finish</p>';
    } else {
        $renderResult((new PlbExportService($repository))->exportPlbFile($plrInput, $plrOutput, $plbInput, $plbOutput));
    }

    // ── TRN Export ─────────────────────────────────────────────────────

    echo '<h2>TRN Export</h2>';

    $seasonStartDate = $season->beginningYear . '-07-01';
    $trnResult = $service->exportTrnFile($trnOutput, $seasonStartDate);

    foreach ($trnResult->messages as $msg) {
        $class = str_starts_with($msg, 'ERROR') ? 'error' : '';
        echo '<p class="' . $class . '">' . HtmlSanitizer::safeHtmlOutput($msg) . '</p>';
    }

    echo '<p class="success"><b>' . HtmlSanitizer::safeHtmlOutput($trnResult->summary()) . '</b></p>';

    echo '</body></html>';
} finally {
    foreach ([$plrInput, $plbInput] as $tmpPath) {
        if (is_file($tmpPath)) {
            unlink($tmpPath);
        }
    }
    rmdir($tmpDir);
}
