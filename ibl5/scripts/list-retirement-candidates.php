<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'This script must be run from the command line.';
    exit(1);
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db/db.php';

$query = "
SELECT p.pid, p.name, MAX(h.year) AS last_hist_year, p.age, p.exp, p.teamid
FROM ibl_plr p
LEFT JOIN ibl_hist h ON h.pid = p.pid
WHERE p.retired = 0 AND p.teamid = 0
GROUP BY p.pid, p.name, p.age, p.exp, p.teamid
HAVING last_hist_year IS NULL OR last_hist_year < 2006
ORDER BY last_hist_year ASC, p.pid ASC
";

/** @var \mysqli $mysqli_db */
$db = $mysqli_db;

$result = $db->query($query);
if (!$result instanceof \mysqli_result) {
    fwrite(STDERR, 'Query failed: ' . $db->error . PHP_EOL);
    exit(1);
}

printf("%-8s | %-30s | %-13s | %-3s | %-3s | %-6s\n", 'pid', 'name', 'last_hist_year', 'age', 'exp', 'teamid');
printf("%s\n", str_repeat('-', 80));

$count = 0;
while (($row = $result->fetch_assoc()) !== null) {
    $pid = isset($row['pid']) && is_numeric($row['pid']) ? (int) $row['pid'] : 0;
    $name = isset($row['name']) && is_string($row['name']) ? $row['name'] : '';
    $lastHistYear = isset($row['last_hist_year']) && is_numeric($row['last_hist_year'])
        ? (string) (int) $row['last_hist_year']
        : 'NULL';
    $age = isset($row['age']) && is_numeric($row['age']) ? (int) $row['age'] : 0;
    $exp = isset($row['exp']) && is_numeric($row['exp']) ? (int) $row['exp'] : 0;
    $teamid = isset($row['teamid']) && is_numeric($row['teamid']) ? (int) $row['teamid'] : 0;
    printf("%-8d | %-30s | %-13s | %-3d | %-3d | %-6d\n", $pid, $name, $lastHistYear, $age, $exp, $teamid);
    $count++;
}

printf("\n-- %d candidates\n", $count);
