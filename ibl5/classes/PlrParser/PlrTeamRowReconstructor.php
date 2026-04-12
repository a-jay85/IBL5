<?php

declare(strict_types=1);

namespace PlrParser;

/**
 * Builds a reconstructed franchise team-summary row by overlaying validated
 * cumulative stats onto a base 607-or-608-byte team row from a prior `.plr` snapshot.
 *
 * Field offsets and widths come from {@see PlrTeamRowLayout}. Bytes outside
 * those validated ranges are preserved byte-for-byte from the base row, so an
 * incomplete layout can never produce a corrupted record — only one with some
 * bytes still reflecting the older snapshot's values.
 */
class PlrTeamRowReconstructor
{
    /**
     * Overlay validated regular-season totals onto the base row.
     *
     * @param string $baseRow Base 607-or-608-byte franchise team row from a prior snapshot
     * @param array<string, int> $stats Stats keyed by {@see PlrTeamRowLayout::REGULAR_SEASON_FIELD_MAP} key
     * @return string Row with the validated fields rewritten, same length as input
     */
    public static function applyRegularSeasonStats(string $baseRow, array $stats): string
    {
        $len = strlen($baseRow);
        if ($len < PlrTeamRowLayout::FRANCHISE_ROW_MIN_LENGTH
            || $len > PlrTeamRowLayout::FRANCHISE_ROW_MAX_LENGTH
        ) {
            throw new \InvalidArgumentException(
                'Franchise team row must be ' . PlrTeamRowLayout::FRANCHISE_ROW_MIN_LENGTH
                . '-' . PlrTeamRowLayout::FRANCHISE_ROW_MAX_LENGTH . ' bytes, got ' . $len
            );
        }

        $row = $baseRow;
        foreach (PlrTeamRowLayout::REGULAR_SEASON_FIELD_MAP as $key => [$offset, $width]) {
            if (!array_key_exists($key, $stats)) {
                continue;
            }
            $value = $stats[$key];
            $formatted = str_pad((string) $value, $width, ' ', STR_PAD_LEFT);
            if (strlen($formatted) !== $width) {
                throw new \InvalidArgumentException(
                    "Stat '{$key}' value {$value} does not fit in {$width} bytes"
                );
            }
            $row = substr_replace($row, $formatted, $offset, $width);
        }

        return $row;
    }

    /**
     * Overlay validated playoff-season totals onto the base row.
     *
     * @param string $baseRow Base 607-or-608-byte franchise team row
     * @param array<string, int> $stats Stats keyed by {@see PlrTeamRowLayout::PLAYOFF_SEASON_FIELD_MAP} key
     * @return string Row with the validated playoff fields rewritten, same length as input
     */
    public static function applyPlayoffSeasonStats(string $baseRow, array $stats): string
    {
        $len = strlen($baseRow);
        if ($len < PlrTeamRowLayout::FRANCHISE_ROW_MIN_LENGTH
            || $len > PlrTeamRowLayout::FRANCHISE_ROW_MAX_LENGTH
        ) {
            throw new \InvalidArgumentException(
                'Franchise team row must be ' . PlrTeamRowLayout::FRANCHISE_ROW_MIN_LENGTH
                . '-' . PlrTeamRowLayout::FRANCHISE_ROW_MAX_LENGTH . ' bytes, got ' . $len
            );
        }

        $row = $baseRow;
        foreach (PlrTeamRowLayout::PLAYOFF_SEASON_FIELD_MAP as $key => [$offset, $width]) {
            if (!array_key_exists($key, $stats)) {
                continue;
            }
            $value = $stats[$key];
            $formatted = str_pad((string) $value, $width, ' ', STR_PAD_LEFT);
            if (strlen($formatted) !== $width) {
                throw new \InvalidArgumentException(
                    "Stat '{$key}' value {$value} does not fit in {$width} bytes"
                );
            }
            $row = substr_replace($row, $formatted, $offset, $width);
        }

        return $row;
    }
}
