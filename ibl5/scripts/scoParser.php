<?php

declare(strict_types=1);

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';

global $mysqli_db;

$view = new Boxscore\BoxscoreView();
echo $view->renderUploadForm();

if (isset($_FILES['scoFiles']['name']) && is_array($_FILES['scoFiles']['name'])) {
    $seasonEndingYear = (int) ($_POST['seasonEndingYear'] ?? 0);
    $seasonPhase = (string) ($_POST['seasonPhase'] ?? '');
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

        $result = $processor->processScoFile($tmpName, $seasonEndingYear, $seasonPhase);
        echo $view->renderParseLog($result);
    }
}
