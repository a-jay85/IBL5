<?php

declare(strict_types=1);

\Cli\CliFlagGuard::assertKnown($argv ?? [], ['--dry-run']);
$dryRun = in_array('--dry-run', $argv ?? [], true);
