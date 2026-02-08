<?php

declare(strict_types=1);

namespace RecordHolders\Contracts;

/**
 * Service interface for Record Holders module.
 *
 * Orchestrates repository calls and returns structured data for the view.
 *
 * @phpstan-type FormattedPlayerRecord array{
 *     pid: int,
 *     name: string,
 *     teamAbbr: string,
 *     teamTid: int,
 *     teamYr: string,
 *     boxScoreUrl: string,
 *     dateDisplay: string,
 *     oppAbbr: string,
 *     oppTid: int,
 *     oppYr: string,
 *     amount: string
 * }
 *
 * @phpstan-type FormattedSeasonRecord array{
 *     pid: int,
 *     name: string,
 *     teamAbbr: string,
 *     teamTid: int,
 *     teamYr: string,
 *     season: string,
 *     amount: string
 * }
 *
 * @phpstan-type FormattedTeamGameRecord array{
 *     teamAbbr: string,
 *     teamTid: int,
 *     boxScoreUrl: string,
 *     dateDisplay: string,
 *     oppAbbr: string,
 *     oppTid: int,
 *     amount: string
 * }
 *
 * @phpstan-type FormattedTeamSeasonRecord array{
 *     teamAbbr: string,
 *     season: string,
 *     amount: string
 * }
 *
 * @phpstan-type FormattedFranchiseRecord array{
 *     teamAbbr: string,
 *     amount: string,
 *     years: string
 * }
 *
 * @phpstan-type AllRecordsData array{
 *     playerSingleGame: array{
 *         regularSeason: array<string, list<FormattedPlayerRecord>>,
 *         playoffs: array<string, list<FormattedPlayerRecord>>,
 *         heat: array<string, list<FormattedPlayerRecord>>
 *     },
 *     quadrupleDoubles: list<FormattedPlayerRecord>,
 *     allStarRecord: array{name: string, pid: int|null, teams: string, teamTids: string, amount: int, years: string},
 *     playerFullSeason: array<string, list<FormattedSeasonRecord>>,
 *     teamGameRecords: array<string, list<FormattedTeamGameRecord>>,
 *     teamSeasonRecords: array<string, list<FormattedTeamSeasonRecord>>,
 *     teamFranchise: array<string, list<FormattedFranchiseRecord>>
 * }
 */
interface RecordHoldersServiceInterface
{
    /**
     * Get all record holder data, structured for view rendering.
     *
     * @return AllRecordsData
     */
    public function getAllRecords(): array;
}
