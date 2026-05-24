<?php

declare(strict_types=1);

namespace LastSimRecap\Contracts;

/**
 * Data access for the Last-Sim Recap card on the News page.
 *
 * @phpstan-type LastSimWindow array{sim:int,startDate:string,endDate:string}
 *
 * @phpstan-type RecapGameRow array{
 *   schedId:int,
 *   boxId:int,
 *   date:string,
 *   visitor:int,
 *   vScore:int,
 *   home:int,
 *   hScore:int,
 *   year:int
 * }
 *
 * @phpstan-type TeamBoxscoreLines array{
 *   visQ:array{0:int,1:int,2:int,3:int},
 *   homeQ:array{0:int,1:int,2:int,3:int},
 *   visOT:int,
 *   homeOT:int,
 *   visitorPreWins:int,
 *   visitorPreLosses:int,
 *   homePreWins:int,
 *   homePreLosses:int,
 *   gameOfThatDay:int
 * }
 *
 * @phpstan-type InjuryRow array{
 *   pid:int,
 *   name:string,
 *   pos:string,
 *   date:string,
 *   injuryDescription:string,
 *   injuryGamesMissed:int,
 *   daysRemaining:int,
 *   returnDate:string,
 *   isNew:bool
 * }
 *
 * @phpstan-type StarterMap array{PG:int,SG:int,SF:int,PF:int,C:int}
 *
 * @phpstan-type PlayerLine array{
 *   pid:int,
 *   name:string,
 *   pos:string,
 *   pts:int,
 *   reb:int,
 *   ast:int,
 *   stl:int,
 *   blk:int,
 *   minutes:int
 * }
 *
 * @phpstan-type TeamRecord array{wins:int,losses:int}
 */
interface LastSimRecapRepositoryInterface
{
    /** @return LastSimWindow|null */
    public function getLastSimWindow(): ?array;

    /** @return list<RecapGameRow> */
    public function getGamesForTeamInWindow(int $tid, string $startDate, string $endDate): array;

    /** @return TeamBoxscoreLines|null */
    public function getTeamBoxscoreLines(int $visitor, int $home, string $date): ?array;

    /**
     * Return active injuries for the listed players as of $date.
     *  - `date <= $date`
     *  - `DATE_ADD(date, INTERVAL injury_games_missed DAY) > $date`
     *  - `isNew` = the injury occurred on $date.
     *
     * @param list<int> $playerIds
     * @return list<InjuryRow>
     */
    public function getActiveInjuriesForPlayers(array $playerIds, string $date): array;

    /** @return list<int> */
    public function getTeamRosterPids(int $tid): array;

    /** @return StarterMap|null */
    public function getStarterPidsFromLastSim(int $tid): ?array;

    /** @return StarterMap|null */
    public function getStarterPidsFromSnapshot(int $tid, string $date): ?array;

    /** @return StarterMap */
    public function getStarterPidsFromBoxScores(int $schedId, int $tid): array;

    /** @return PlayerLine|null */
    public function getPlayerLineForGame(int $pid, int $schedId): ?array;

    /** @return TeamRecord */
    public function getTeamRecordAsOf(int $tid, string $date): array;

    /**
     * @return array{tid:int,city:string,name:string}|null
     */
    public function getTeamInfo(int $tid): ?array;
}
