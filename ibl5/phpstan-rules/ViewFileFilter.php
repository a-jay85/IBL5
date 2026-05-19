<?php

declare(strict_types=1);

namespace PHPStanRules;

final class ViewFileFilter
{
    public static function isViewFile(string $file): bool
    {
        if (!str_contains($file, 'classes/') && !str_contains($file, 'classes' . DIRECTORY_SEPARATOR)) {
            return false;
        }

        $basename = basename($file);
        if (!str_contains($basename, 'View') || !str_ends_with($basename, '.php')) {
            return false;
        }

        if (str_contains($file, 'Contracts/') || str_contains($file, 'Contracts' . DIRECTORY_SEPARATOR)) {
            return false;
        }

        return true;
    }
}
