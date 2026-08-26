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
     * @param string|null $sourceArchive Human-readable provenance of the bytes (archive or file name).
     *                                   Recorded with rejects; never used for control flow.
     * @return array{success: bool, gamesInserted: int, gamesUpdated: int, gamesSkipped: int, linesProcessed: int, messages: list<string>, error?: string, gamesRejected?: int, rejectedGames?: list<\Boxscore\RejectedGame>, operatingSeasonEndingYear?: int, operatingSeasonPhase?: string, outOfWindowGames?: int, rejectsRecorded: int, scheduleGuardEnabled: bool, sourceArchive: string|null}
     *         Result:
     *         - 'success': bool - Whether processing completed without errors
     *         - 'gamesInserted': int - Number of new games inserted
     *         - 'gamesUpdated': int - Number of games updated (deleted + re-inserted)
     *         - 'gamesSkipped': int - Number of games skipped (scores match)
     *         - 'linesProcessed': int - Total number of .sco lines processed
     *         - 'messages': list<string> - Log messages from processing
     *         - 'error': string - Error message if success is false
     *         - 'gamesRejected': int - Number of games rejected by the schedule membership guard
     *         - 'rejectedGames': list<RejectedGame> - Details of each rejected game
     *         - 'operatingSeasonEndingYear': int - Season ending year determined during processing
     *         - 'operatingSeasonPhase': string - Season phase determined during processing
     *         - 'outOfWindowGames': int - Games whose date falls outside the schedule window
     *         - 'rejectsRecorded': int - Number of rejected games written to the audit table
     *         - 'scheduleGuardEnabled': bool - Whether the schedule membership guard was active
     *         - 'sourceArchive': string|null - Archive or file name that provided the data
     */
    public function processScoFile(string $filePath, int $seasonEndingYear, string $seasonPhase, bool $skipSimDates = false, ?string $sourceArchive = null): array;

    /**
     * Process .sco data from a string and insert/update boxscore records
     *
     * Same as processScoFile() but accepts raw binary data instead of a file path.
     *
     * @param string $data Raw .sco file contents (must be at least HEADER_OFFSET_BYTES long)
     * @param int $seasonEndingYear Season ending year (0 to use current season)
     * @param string $seasonPhase Season phase (empty to use current phase)
     * @param bool $skipSimDates When true, skip updating ibl_sim_dates
     * @param string|null $sourceArchive Human-readable provenance of the bytes (archive or file name).
     *                                   Recorded with rejects; never used for control flow.
     * @return array{success: bool, gamesInserted: int, gamesUpdated: int, gamesSkipped: int, linesProcessed: int, messages: list<string>, error?: string, gamesRejected?: int, rejectedGames?: list<\Boxscore\RejectedGame>, operatingSeasonEndingYear?: int, operatingSeasonPhase?: string, outOfWindowGames?: int, rejectsRecorded: int, scheduleGuardEnabled: bool, sourceArchive: string|null}
     */
    public function processScoData(string $data, int $seasonEndingYear, string $seasonPhase, bool $skipSimDates = false, ?string $sourceArchive = null): array;

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

    /**
     * Process All-Star Weekend games from raw .sco data
     *
     * Same as processAllStarGames() but accepts raw binary data instead of a file path.
     *
     * @param string $data Raw .sco file contents (first 4000 bytes used)
     * @param int $seasonEndingYear Season ending year
     * @return array{success: bool, messages: list<string>, skipped?: string}
     */
    public function processAllStarGamesData(
        string $data,
        int $seasonEndingYear,
    ): array;
}
