<?php

declare(strict_types=1);

namespace JsbParser\Contracts;

/**
 * Interface for generating JSB .trn (Transactions) binary files.
 *
 * The .trn format is a fixed 64,000-byte file with 128-byte records for
 * injuries, trades, and waiver actions. All numeric fields are right-justified
 * and space-padded.
 */
interface TrnFileWriterInterface
{
    /**
     * Generate a complete 64,000-byte .trn file from transaction records.
     *
     * @param list<string> $records Pre-built 128-byte record strings
     * @return string Exactly 64,000 bytes
     */
    public static function generate(array $records): string;

    /**
     * Build a 128-byte injury record (type 1).
     *
     * @param int $month Transaction month (1-12)
     * @param int $day Transaction day (1-31)
     * @param int $year Transaction year (4-digit)
     * @param int $pid Player ID
     * @param int $teamId JSB team ID (1-28)
     * @param int $gamesMissed Number of games missed
     * @param string $injuryDesc Injury description text
     * @return string Exactly 128 bytes
     */
    public static function buildInjuryRecord(
        int $month,
        int $day,
        int $year,
        int $pid,
        int $teamId,
        int $gamesMissed,
        string $injuryDesc,
    ): string;

    /**
     * Build a 128-byte trade record (type 2) with player move items.
     *
     * @param int $month Transaction month (1-12)
     * @param int $day Transaction day (1-31)
     * @param int $year Transaction year (4-digit)
     * @param list<array{marker: int, from_team: int, to_team: int, player_id?: int, draft_year?: int}> $items Trade items
     * @return string Exactly 128 bytes
     */
    public static function buildTradeRecord(int $month, int $day, int $year, array $items): string;

    /**
     * Build a 128-byte waiver record (type 3 or 4).
     *
     * @param int $month Transaction month (1-12)
     * @param int $day Transaction day (1-31)
     * @param int $year Transaction year (4-digit)
     * @param int $type Transaction type (3=waiver claim, 4=waiver release)
     * @param int $teamId JSB team ID
     * @param int $pid Player ID
     * @return string Exactly 128 bytes
     */
    public static function buildWaiverRecord(
        int $month,
        int $day,
        int $year,
        int $type,
        int $teamId,
        int $pid,
    ): string;
}
