<?php

declare(strict_types=1);

// Filename matches the DB-access-boundary allowlist, so \mysqli::query() is permitted here.
function boundaryQuery(\mysqli $db): void
{
    $db->query('SELECT 1');
}
