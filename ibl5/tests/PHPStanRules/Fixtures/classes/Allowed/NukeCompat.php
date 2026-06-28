<?php

declare(strict_types=1);

// date("Z") returns the timezone offset, not a now-read; allowlisted by file.
$tz = date('Z');
$now = time();
echo $tz, $now;
