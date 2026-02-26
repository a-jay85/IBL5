<?php

declare(strict_types=1);

if (!isset($_POST['confirmed'])) {
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
                background: #28a745;
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
                    <b>PLR Export:</b> Reads <code>IBL5.plr</code>, applies database changes,
                    writes to <code>IBL5_export.plr</code><br>
                    <b>TRN Export:</b> Generates <code>IBL5_export.trn</code> from trade data
                </p>
                <p><em>Source files are never overwritten.</em></p>
                <div class="modal-buttons">
                    <form method="POST">
                        <input type="hidden" name="confirmed" value="1">
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

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';

use JsbParser\JsbExportRepository;
use JsbParser\JsbExportService;
use Utilities\HtmlSanitizer;

$repository = new JsbExportRepository($mysqli_db);
$service = new JsbExportService($repository);
$season = new Season($mysqli_db);

$basePath = $_SERVER['DOCUMENT_ROOT'] . '/ibl5';
$plrInput = $basePath . '/IBL5.plr';
$plrOutput = $basePath . '/IBL5_export.plr';
$trnOutput = $basePath . '/IBL5_export.trn';

echo '<html><head><style>
    body { font-family: monospace; padding: 20px; }
    .success { color: #28a745; }
    .error { color: #dc3545; }
    .change { color: #0066cc; }
    table { border-collapse: collapse; margin: 10px 0; }
    th, td { border: 1px solid #ddd; padding: 4px 8px; text-align: left; }
    th { background: #f5f5f5; }
</style></head><body>';

// ── PLR Export ─────────────────────────────────────────────────────

echo '<h2>PLR Export</h2>';

if (!file_exists($plrInput)) {
    echo '<p class="error">ERROR: IBL5.plr not found at ' . HtmlSanitizer::safeHtmlOutput($plrInput) . '</p>';
} else {
    $plrResult = $service->exportPlrFile($plrInput, $plrOutput);

    foreach ($plrResult->messages as $msg) {
        $class = str_starts_with($msg, 'ERROR') ? 'error' : '';
        echo '<p class="' . $class . '">' . HtmlSanitizer::safeHtmlOutput($msg) . '</p>';
    }

    echo '<p class="success"><b>' . HtmlSanitizer::safeHtmlOutput($plrResult->summary()) . '</b></p>';

    if ($plrResult->changeLog !== []) {
        echo '<h3>Change Details</h3>';
        echo '<table><tr><th>PID</th><th>Player</th><th>Field</th><th>Old</th><th>New</th></tr>';
        foreach ($plrResult->changeLog as $entry) {
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
