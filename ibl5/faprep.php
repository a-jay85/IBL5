<?php

declare(strict_types=1);

/**
 * Free Agent Prep Report (faprep.php).
 *
 * An admin-only HTML table of every non-retired player (ibl_plr rows with
 * retired = 0, ordered by ordinal) with team name, ordinal, coach, stamina,
 * and the other free agency prep columns. The commissioner uses it to
 * prepare the free agency period.
 *
 * Access is limited to admins. Anyone else gets HTTP 403 from the
 * is_admin() check below. There is no nav or admin-panel link. Open it
 * directly at /ibl5/faprep.php.
 *
 * It stays separate from the public FreeAgencyPreview module
 * (modules.php?name=FreeAgencyPreview). Preview filters to players whose
 * contracts expire in the chosen year and does not show coach, stamina,
 * or ordinal. This report lists all active players with those columns.
 *
 * Tests: tests/WideUnit/Scripts/FaprepGuardTest.php plus the Playwright
 * specs tests/e2e/smoke/faprep-admin.spec.ts,
 * tests/e2e/flows/faprep-xss-escape.spec.ts, and
 * tests/e2e/flows/role-gating-non-admin.spec.ts.
 *
 * Runbook: ibl5/docs/OPERATIONS_RUNBOOK.md, section 9.
 */

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
<html lang="en">
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
