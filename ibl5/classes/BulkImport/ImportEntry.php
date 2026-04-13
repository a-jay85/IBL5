<?php

declare(strict_types=1);

namespace BulkImport;

use PlrParser\PlrOrdinalMap;

/**
 * A single item to be processed by the bulk import pipeline.
 *
 * Represents one file extraction + import operation. For cumulative types,
 * there is typically one entry per season. For snapshot types, there is
 * one entry per archive.
 */
final class ImportEntry
{
    public function __construct(
        /** Directory or archive path containing the file */
        public readonly string $path,
        /** Display label (directory name or archive basename) */
        public readonly string $label,
        /** Season ending year (e.g., 2007 for the 2006-07 season) */
        public readonly int $year,
        /** Season phase: 'HEAT', 'Regular Season/Playoffs', 'Preseason', etc. */
        public readonly string $phase,
        /** Specific archive path for production mode (null for pre-extracted dirs) */
        public readonly ?string $archivePath,
        /** Source label passed to service methods that accept it */
        public readonly string $sourceLabel,
        /** Pre-built PlrOrdinalMap for .plb entries (null for all other types) */
        public readonly ?PlrOrdinalMap $plrMap = null,
        /** Archive sequence number for .plb entries (null for all other types) */
        public readonly ?int $simNumber = null,
    ) {
    }
}
