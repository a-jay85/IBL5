<?php

declare(strict_types=1);

namespace Boxscore\Contracts;

/**
 * BoxscoreProcessorInterface - Contract for .sco file processing
 *
 * Handles parsing Jump Shot Basketball .sco files, inserting/updating
 * boxscore records, and managing sim dates.
 *
 * @see \Boxscore\BoxscoreProcessor For the concrete implementation
 */
interface BoxscoreProcessorInterface
{
    /**
     * Process a .sco file and insert/update boxscore records
     *
     * Parses the binary .sco file, checks for existing games (upsert logic),
     * inserts team and player boxscore rows, and updates sim dates.
     *
     * @param string $filePath Path to the .sco file
     * @param int $seasonEndingYear Season ending year (0 to use current season)
     * @param string $seasonPhase Season phase (empty to use current phase)
     * @param bool $skipSimDates When true, skip updating ibl_sim_dates (use for historical imports)
     * @return array{success: bool, gamesInserted: int, gamesUpdated: int, gamesSkipped: int, linesProcessed: int, messages: list<string>, error?: string}
     *         Result:
     *         - 'success': bool - Whether processing completed without errors
     *         - 'gamesInserted': int - Number of new games inserted
     *         - 'gamesUpdated': int - Number of games updated (deleted + re-inserted)
     *         - 'gamesSkipped': int - Number of games skipped (scores match)
     *         - 'linesProcessed': int - Total number of .sco lines processed
     *         - 'messages': list<string> - Log messages from processing
     *         - 'error': string - Error message if success is false
     */
    public function processScoFile(string $filePath, int $seasonEndingYear, string $seasonPhase, bool $skipSimDates = false): array;

    /**
     * Process All-Star Weekend games from the first 4000 bytes of a .sco file
     *
     * Block 0 (bytes 0–1999): Rising Stars Game (Rookies vs Sophomores)
     * Block 1 (bytes 2000–3999): All-Star Game (inserted with default placeholder names)
     *
     * @param string $filePath Path to the .sco file
     * @param int $seasonEndingYear Season ending year
     * @return array{success: bool, messages: list<string>, skipped?: string}
     */
    public function processAllStarGames(
        string $filePath,
        int $seasonEndingYear,
    ): array;
}
