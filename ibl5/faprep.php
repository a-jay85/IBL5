<?php

declare(strict_types=1);

require __DIR__ . '/mainfile.php';

use Security\HtmlSanitizer;

if (!is_admin()) {
    http_response_code(403);
    die('Forbidden');
}

/** @var mysqli $mysqli_db */

$query = <<<'SQL'
SELECT p.ordinal, p.name, p.age, t.team_name AS teamname, p.pos, p.coach, p.loyalty, p.playing_time,
       p.winner, p.tradition, p.security, p.exp, p.stamina
FROM ibl_plr p
LEFT JOIN ibl_team_info t ON p.teamid = t.teamid
WHERE p.retired = 0
ORDER BY p.ordinal ASC
SQL;

$result = $mysqli_db->query($query);
$rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];

?>
<html>
<head>
    <title>Free Agent Prep</title>
</head>
<body>
<table>
<tr>
    <th>ordinal</th>
    <th>name</th>
    <th>age</th>
    <th>teamname</th>
    <th>pos</th>
    <th>coach</th>
    <th>loyalty</th>
    <th>playingTime</th>
    <th>winner</th>
    <th>tradition</th>
    <th>security</th>
    <th>exp</th>
    <th>Sta</th>
</tr>
<?php foreach ($rows as $row): ?>
<tr>
    <td><?= HtmlSanitizer::e($row['ordinal']) ?></td>
    <td><?= HtmlSanitizer::e($row['name']) ?></td>
    <td><?= HtmlSanitizer::e($row['age']) ?></td>
    <td><?= HtmlSanitizer::e($row['teamname']) ?></td>
    <td><?= HtmlSanitizer::e($row['pos']) ?></td>
    <td><?= HtmlSanitizer::e($row['coach']) ?></td>
    <td><?= HtmlSanitizer::e($row['loyalty']) ?></td>
    <td><?= HtmlSanitizer::e($row['playing_time']) ?></td>
    <td><?= HtmlSanitizer::e($row['winner']) ?></td>
    <td><?= HtmlSanitizer::e($row['tradition']) ?></td>
    <td><?= HtmlSanitizer::e($row['security']) ?></td>
    <td><?= HtmlSanitizer::e($row['exp']) ?></td>
    <td><?= HtmlSanitizer::e($row['stamina']) ?></td>
</tr>
<?php endforeach; ?>
</table>
</body>
</html>
