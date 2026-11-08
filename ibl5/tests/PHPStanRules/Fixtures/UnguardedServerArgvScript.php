<?php

declare(strict_types=1);

$raw = $_SERVER['argv'] ?? [];
$apply = in_array('--apply', $raw, true);
