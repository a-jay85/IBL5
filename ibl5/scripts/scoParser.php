<?php

declare(strict_types=1);

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';

global $mysqli_db;

$view = new Boxscore\BoxscoreView();

$hasUploadedFiles = isset($_FILES['scoFiles']['name']) && is_array($_FILES['scoFiles']['name']);

// When reached via fetch with uploaded files, skip rendering the upload form
if (!$hasUploadedFiles) {
    $stylesheetPath = '/ibl5/themes/IBL/style/style.css';
    /** @var int|false $stylesheetMtime */
    $stylesheetMtime = filemtime($_SERVER['DOCUMENT_ROOT'] . $stylesheetPath);
    $cacheBuster = $stylesheetMtime !== false ? '?v=' . $stylesheetMtime : '';

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>.sco File Parser</title>'
        . '<link rel="preconnect" href="https://fonts.googleapis.com">'
        . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
        . '<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@500;600;700;800&family=Barlow:wght@400;500;600;700&display=block" rel="stylesheet">'
        . '<link rel="stylesheet" href="' . $stylesheetPath . $cacheBuster . '">'
        . '</head><body>';

    echo $view->renderUploadForm();

    // On first load (no upload), parse the default .sco file with current season settings
    $defaultScoPath = $_SERVER['DOCUMENT_ROOT'] . '/ibl5/IBL5.sco';
    if (is_file($defaultScoPath)) {
        $processor = new Boxscore\BoxscoreProcessor($mysqli_db);
        $result = $processor->processScoFile($defaultScoPath, 0, '');
        echo $view->renderParseLog($result);
    }

    echo '</body></html>';
}

if ($hasUploadedFiles) {
    /** @var array<int, string> $seasonEndingYears */
    $seasonEndingYears = is_array($_POST['seasonEndingYears'] ?? null) ? $_POST['seasonEndingYears'] : [];
    /** @var array<int, string> $seasonPhases */
    $seasonPhases = is_array($_POST['seasonPhases'] ?? null) ? $_POST['seasonPhases'] : [];

    $processor = new Boxscore\BoxscoreProcessor($mysqli_db);

    $fileCount = count($_FILES['scoFiles']['name']);
    for ($i = 0; $i < $fileCount; $i++) {
        /** @var int $error */
        $error = (int) $_FILES['scoFiles']['error'][$i];
        /** @var string $tmpName */
        $tmpName = (string) $_FILES['scoFiles']['tmp_name'][$i];

        if ($error > 0) {
            echo $view->renderUploadError($error);
            continue;
        }

        if ($tmpName === '') {
            continue;
        }

        $seasonEndingYear = (int) ($seasonEndingYears[$i] ?? 0);
        $seasonPhase = (string) ($seasonPhases[$i] ?? '');

        $result = $processor->processScoFile($tmpName, $seasonEndingYear, $seasonPhase);
        echo $view->renderParseLog($result);
    }
}
