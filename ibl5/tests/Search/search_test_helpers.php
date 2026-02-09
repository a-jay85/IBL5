<?php

declare(strict_types=1);

// Legacy PHP-Nuke function stubs needed by SearchView tests.
// Defined in a separate file to ensure global namespace scope.

if (!function_exists('formatTimestamp')) {
    function formatTimestamp(string $time): string
    {
        return $time;
    }
}
