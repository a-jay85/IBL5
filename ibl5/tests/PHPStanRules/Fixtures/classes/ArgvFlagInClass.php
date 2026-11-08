<?php

declare(strict_types=1);

function argvFlagInClass(array $argv): bool { return in_array('--dry-run', $argv, true); }
