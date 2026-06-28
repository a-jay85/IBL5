<?php

declare(strict_types=1);

$ts = 1_700_000_000;
$raw = '2024-01-01';

$a = date('Y-m-d H:i:s', $ts);
$b = gmdate('Y-m-d', $ts);
$c = strtotime($raw);
$d = mktime(0, 0, 0, 1, 1, 2024);
$e = microtime(true);

echo $a, $b, $c, $d, $e;
