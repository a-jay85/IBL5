<?php

declare(strict_types=1);

$ts = 1_700_000_000;
$var = '2024-01-01';

$bannedTime = time();
$bannedDate = date('Y');
$bannedMktime = mktime();

$okDate = date('Y-m-d', $ts);
$okGmdate = gmdate('Y-m-d', 0);
$okStrtotime = strtotime($var);

echo $bannedTime, $bannedDate, $bannedMktime, $okDate, $okGmdate, $okStrtotime;
