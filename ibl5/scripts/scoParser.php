<?php

declare(strict_types=1);

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';

global $mysqli_db;

$view = new Boxscore\BoxscoreView();

$hasUploadedFiles = isset($_FILES['scoFiles']['name']) && is_array($_FILES['scoFiles']['name']);

// When reached via fetch with uploaded files, skip rendering the upload form
if (!$hasUploadedFiles) {
    echo $view->renderUploadForm();

    // On first load (no upload), parse the default .sco file with current season settings
    $defaultScoPath = $_SERVER['DOCUMENT_ROOT'] . '/ibl5/IBL5.sco';
    if (is_file($defaultScoPath)) {
        $processor = new Boxscore\BoxscoreProcessor($mysqli_db);
        $result = $processor->processScoFile($defaultScoPath, 0, '');
        echo $view->renderParseLog($result);
    }
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
