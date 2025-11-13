<?php

// Sanitize input to prevent SQL injection
$player = isset($_REQUEST['player']) ? trim($_REQUEST['player']) : '';

if (empty($player)) {
    echo "<small>No player specified.</small>";
    return;
}

// Use prepared statement to prevent SQL injection
$searchTerm = '%' . $db->real_escape_string($player) . '%';
$query = "SELECT sid, title, time FROM nuke_stories WHERE hometext LIKE ? OR bodytext LIKE ? ORDER BY time DESC";
$stmt = $db->prepare($query);

if ($stmt === false) {
    echo "<small>Error searching for articles.</small>";
    return;
}

$stmt->bind_param('ss', $searchTerm, $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

echo "<small>";

while ($row = $result->fetch_assoc()) {
    $sid = htmlspecialchars($row['sid'], ENT_QUOTES, 'UTF-8');
    $title = htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8');
    $time = htmlspecialchars($row['time'], ENT_QUOTES, 'UTF-8');

    echo "
* <a href=\"modules.php?name=News&amp;file=article&amp;sid=$sid&amp;mode=&amp;order=0&amp;thold=0\">$title</a> ($time)<br>";
}

echo "</small>";

$stmt->close();
