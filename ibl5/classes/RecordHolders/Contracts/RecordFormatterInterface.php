<?php

declare(strict_types=1);

namespace RecordHolders\Contracts;

/**
 * Formatter interface for Record Holders module.
 *
 * Transforms already-fetched DB rows into view-ready display structures.
 * Repository-free: all team-lookup is a const registry, all helpers static.
 *
 * @phpstan-import-type FormattedPlayerRecord from RecordHoldersServiceInterface
 * @phpstan-import-type FormattedSeasonRecord from RecordHoldersServiceInterface
 * @phpstan-import-type FormattedTeamGameRecord from RecordHoldersServiceInterface
 * @phpstan-import-type FormattedTeamSeasonRecord from RecordHoldersServiceInterface
 * @phpstan-import-type FormattedFranchiseRecord from RecordHoldersServiceInterface
 */
interface RecordFormatterInterface
{
    /**
     * Format player single-game records from DB rows.
     *
     * @param list<array{pid: int, name: string, teamid: int, team_name: string, date: string, box_id: int, game_of_that_day: int, oppTid: int, opp_team_name: string, value: int}> $dbRecords
     * @param string $gameType
     * @return list<FormattedPlayerRecord>
     */
    public function formatPlayerRecords(array $dbRecords, string $gameType): array;

    /**
     * Format quadruple double records.
     *
     * @param list<array{pid: int, name: string, teamid: int, team_name: string, date: string, box_id: int, game_of_that_day: int, oppTid: int, opp_team_name: string, points: int, rebounds: int, assists: int, steals: int, blocks: int}> $dbRecords
     * @return list<FormattedPlayerRecord>
     */
    public function formatQuadrupleDoubles(array $dbRecords): array;

    /**
     * Format the all-star appearances record.
     *
     * @param list<array{name: string, pid: int|null, appearances: int}> $dbRecords
     * @return array{name: string, pid: int|null, teams: string, teamTids: string, amount: int, years: string}
     */
    public function formatAllStarRecord(array $dbRecords): array;

    /**
     * Format player full-season records from DB rows.
     *
     * @param list<array{pid: int, name: string, teamid: int, team: string, year: int, value: float|int}> $dbRecords
     * @return list<FormattedSeasonRecord>
     */
    public function formatPlayerSeasonRecords(array $dbRecords): array;

    /**
     * Format team single-game records from DB rows.
     *
     * @param list<array{teamid: int, team_name: string, date: string, box_id: int, game_of_that_day: int, oppTid: int, opp_team_name: string, value: int}> $dbRecords
     * @return list<FormattedTeamGameRecord>
     */
    public function formatTeamGameRecords(array $dbRecords): array;

    /**
     * Format margin of victory records.
     *
     * @param list<array{winner_tid: int, winner_name: string, loser_tid: int, loser_name: string, date: string, box_id: int, game_of_that_day: int, margin: int}> $dbRecords
     * @return list<FormattedTeamGameRecord>
     */
    public function formatMarginRecords(array $dbRecords): array;

    /**
     * Format season win/loss records.
     *
     * @param list<array{team_name: string, year: int, wins: int, losses: int}> $dbRecords
     * @return list<FormattedTeamSeasonRecord>
     */
    public function formatSeasonWinLossRecords(array $dbRecords): array;

    /**
     * Format season start records.
     *
     * @param list<array{team_name: string, year: int, wins: int, losses: int}> $dbRecords
     * @param string $type 'best' or 'worst'
     * @return list<FormattedTeamSeasonRecord>
     */
    public function formatSeasonStartRecords(array $dbRecords, string $type): array;

    /**
     * Format streak records.
     *
     * @param list<array{team_name: string, streak: int, start_date: string, end_date: string, start_year: int, end_year: int}> $dbRecords
     * @return list<FormattedTeamSeasonRecord>
     */
    public function formatStreakRecords(array $dbRecords): array;

    /**
     * Format franchise records (titles, appearances).
     *
     * @param list<array{team_name: string, count: int, years: string}> $dbRecords
     * @return list<FormattedFranchiseRecord>
     */
    public function formatFranchiseRecords(array $dbRecords): array;

    /**
     * Detect ties in formatted records (multiple entries sharing the same top value).
     *
     * Only keeps entries that match the top (first) value.
     *
     * @param list<array<string, mixed>> $records Each record must have an 'amount' key
     * @return list<array<string, mixed>>
     */
    public function detectTies(array $records): array;

    /**
     * Add " [tie]" suffix to category name if there are multiple records.
     *
     * @param list<array<string, mixed>> $records
     */
    public function addTieLabel(string $category, array $records): string;
}
