<?php

declare(strict_types=1);

$owner = '';
foreach (array_slice($argv ?? [], 1) as $arg) {
    if (str_starts_with($arg, '--owner=')) { $owner = substr($arg, 8); }
}
