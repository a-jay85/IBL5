<?php

declare(strict_types=1);

$opts = getopt('', ['dry-run']);
$dryRun = in_array('--dry-run', $argv ?? [], true);
