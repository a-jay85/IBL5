<?php

declare(strict_types=1);

namespace Updater\Contracts;

/**
 * Resolves JSB file contents from the best available source.
 *
 * Primary: reads from the latest backup archive (ZIP/RAR).
 * Fallback: reads from raw files on disk.
 */
interface JsbSourceResolverInterface
{
    /**
     * Get the raw contents of a JSB file by extension.
     *
     * @param string $extension File extension without dot (e.g. 'lge', 'sch')
     * @return string|null Raw file bytes, or null if unavailable from any source
     */
    public function getContents(string $extension): ?string;
}
