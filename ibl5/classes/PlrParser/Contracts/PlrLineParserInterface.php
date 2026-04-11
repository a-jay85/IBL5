<?php

declare(strict_types=1);

namespace PlrParser\Contracts;

/**
 * Interface for parsing a single 607-byte record from a JSB .plr file.
 *
 * The .plr format is a line-based fixed-width binary file; each player record
 * occupies exactly 607 bytes terminated by CRLF. Field offsets and widths are
 * documented in docs/JSB_FILE_FORMATS.md and mirrored in PlrFileWriter::FIELD_MAP.
 */
interface PlrLineParserInterface
{
    /**
     * Parse a single .plr line into raw field values.
     *
     * Returns null for lines that should be skipped — pid=0 rows (ordinals 1441+
     * are team-summary rows, not player records) and pid=0 filler rows below
     * ordinal 1440.
     *
     * @param string $line Raw line from the .plr file
     * @return array<string, int|string>|null Parsed fields, or null to skip
     */
    public static function parse(string $line): ?array;
}
