<?php

declare(strict_types=1);

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';

global $mysqli_db;

$view = new Boxscore\BoxscoreView();
echo $view->renderUploadForm();

if (isset($_FILES['scoFile']['error']) && $_FILES['scoFile']['error'] > 0) {
    echo $view->renderUploadError((int) $_FILES['scoFile']['error']);
}

if (isset($_FILES['scoFile']['tmp_name']) && $_FILES['scoFile']['tmp_name'] !== '') {
    $uploadedFilePath = $_FILES['scoFile']['tmp_name'];
    $seasonEndingYear = (int) ($_POST['seasonEndingYear'] ?? 0);
    $seasonPhase = (string) ($_POST['seasonPhase'] ?? '');

    $processor = new Boxscore\BoxscoreProcessor($mysqli_db);
    $result = $processor->processScoFile($uploadedFilePath, $seasonEndingYear, $seasonPhase);
    echo $view->renderParseLog($result);
}
