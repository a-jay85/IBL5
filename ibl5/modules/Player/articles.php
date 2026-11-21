<?php

declare(strict_types=1);

// Sanitize input to prevent SQL injection
$rawPlayer = $_REQUEST['player'] ?? null;
$player = is_string($rawPlayer) ? trim($rawPlayer) : '';

if ($player === '' || $player === '0') {
    echo "<small>No player specified.</small>";
    return;
}

$rows = \Module\ModuleServices::current()
    ->factory(\Module\Factories\PlayerFactory::class)
    ->newsRepository()
    ->searchStoriesMentioning($player);

if ($rows === null) {
    echo "<small>Error searching for articles.</small>";
    return;
}

echo "<small>";

foreach ($rows as $row) {
    $sid = htmlspecialchars((string) $row['sid'], ENT_QUOTES, 'UTF-8');
    $title = htmlspecialchars($row['title'] ?? '', ENT_QUOTES, 'UTF-8');
    $time = htmlspecialchars($row['time'] ?? '', ENT_QUOTES, 'UTF-8');

    echo "
* <a href=\"modules.php?name=News&amp;file=article&amp;sid=$sid&amp;mode=&amp;order=0&amp;thold=0\">$title</a> ($time)<br>";
}

echo "</small>";
