<?php

declare(strict_types=1);

global $mysqli_db;
/** @var \mysqli $mysqli_db */

// Sanitize input to prevent SQL injection
$rawPlayer = $_REQUEST['player'] ?? null;
$player = is_string($rawPlayer) ? trim($rawPlayer) : '';

if ($player === '' || $player === '0') {
    echo "<small>No player specified.</small>";
    return;
}

// Bound via prepared statement below; only LIKE metacharacters need escaping.
$searchTerm = '%' . addcslashes($player, '\\%_') . '%';
$query = "SELECT sid, title, time FROM nuke_stories WHERE hometext LIKE ? OR bodytext LIKE ? ORDER BY time DESC";
$stmt = $mysqli_db->prepare($query);

if ($stmt === false) {
    echo "<small>Error searching for articles.</small>";
    return;
}

$stmt->bind_param('ss', $searchTerm, $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

if ($result === false) {
    echo "<small>Error searching for articles.</small>";
    $stmt->close();
    return;
}

echo "<small>";

while (is_array($row = $result->fetch_assoc())) {
    /** @var array{sid: int, title: string|null, time: string|null} $row nuke_stories: sid int(11) NOT NULL, title varchar(80) NULL, time datetime NULL (per ibl5/docs/schema/current-schema.sql) */
    $sid = htmlspecialchars((string) $row['sid'], ENT_QUOTES, 'UTF-8');
    $title = htmlspecialchars($row['title'] ?? '', ENT_QUOTES, 'UTF-8');
    $time = htmlspecialchars($row['time'] ?? '', ENT_QUOTES, 'UTF-8');

    echo "
* <a href=\"modules.php?name=News&amp;file=article&amp;sid=$sid&amp;mode=&amp;order=0&amp;thold=0\">$title</a> ($time)<br>";
}

echo "</small>";

$stmt->close();
