<?php

declare(strict_types=1);

require $_SERVER['DOCUMENT_ROOT'] . '/ibl5/mainfile.php';

global $mysqli_db;

$view = new Boxscore\BoxscoreView();
$repository = new Boxscore\BoxscoreRepository($mysqli_db);

$hasUploadedFiles = isset($_FILES['scoFiles']['name']) && is_array($_FILES['scoFiles']['name']);
$isRenameRequest = isset($_POST['renameTeamId']) && isset($_POST['renameTeamName']);

// All-Star team rename (async POST from rename UI)
if ($isRenameRequest) {
    header('Content-Type: application/json');

    $renameTeamId = is_string($_POST['renameTeamId']) ? (int) $_POST['renameTeamId'] : 0;
    $renameTeamName = is_string($_POST['renameTeamName']) ? trim((string) $_POST['renameTeamName']) : '';

    if ($renameTeamId <= 0 || $renameTeamName === '') {
        echo json_encode(['success' => false, 'error' => 'Invalid team ID or name.']);
        exit;
    }

    // Enforce varchar(16) limit
    $renameTeamName = mb_substr($renameTeamName, 0, 16);

    $affectedRows = $repository->renameAllStarTeam($renameTeamId, $renameTeamName);

    if ($affectedRows > 0) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No record updated.']);
    }

    exit;
} elseif (!$hasUploadedFiles) {
    // Full page load — render upload form + process default .sco file
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

        // Process All-Star Weekend games from the same file
        $allStarResult = $processor->processAllStarGames($defaultScoPath, 0);
        echo $view->renderAllStarLog($allStarResult);
    }

    // Render rename UI for any All-Star games with default placeholder names
    $pendingDefaults = $repository->findAllStarGamesWithDefaultNames();
    if ($pendingDefaults !== []) {
        $pendingRenames = [];
        foreach ($pendingDefaults as $row) {
            $date = $row['Date'];
            $teamID = $row['name'] === Boxscore\BoxscoreProcessor::DEFAULT_AWAY_NAME
                ? 50
                : 51;
            $teamLabel = $teamID === 50 ? 'Away (Visitor)' : 'Home';

            // Derive season year from the date (Feb 03 of ending year)
            $seasonYear = (int) substr($date, 0, 4);

            $players = $repository->getPlayersForAllStarTeam($date, $teamID);

            $pendingRenames[] = [
                'id' => $row['id'],
                'date' => $date,
                'name' => $row['name'],
                'seasonYear' => $seasonYear,
                'teamLabel' => $teamLabel,
                'players' => $players,
            ];
        }

        echo $view->renderAllStarRenameUI($pendingRenames);
    }

    echo '</body></html>';
} else {
    // Uploaded files via fetch
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

        // Process All-Star games from the same uploaded file (bytes 0–3999)
        $allStarResult = $processor->processAllStarGames($tmpName, $seasonEndingYear);
        echo $view->renderAllStarLog($allStarResult);
    }

    // Render rename UI for any All-Star games with default placeholder names
    $pendingDefaults = $repository->findAllStarGamesWithDefaultNames();
    if ($pendingDefaults !== []) {
        $pendingRenames = [];
        foreach ($pendingDefaults as $row) {
            $date = $row['Date'];
            $teamID = $row['name'] === Boxscore\BoxscoreProcessor::DEFAULT_AWAY_NAME
                ? 50
                : 51;
            $teamLabel = $teamID === 50 ? 'Away (Visitor)' : 'Home';
            $seasonYear = (int) substr($date, 0, 4);
            $players = $repository->getPlayersForAllStarTeam($date, $teamID);

            $pendingRenames[] = [
                'id' => $row['id'],
                'date' => $date,
                'name' => $row['name'],
                'seasonYear' => $seasonYear,
                'teamLabel' => $teamLabel,
                'players' => $players,
            ];
        }

        echo $view->renderAllStarRenameUI($pendingRenames);
    }
}
