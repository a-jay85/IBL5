<?php

declare(strict_types=1);

namespace LeagueSchedule\Contracts;

/**
 * LeagueScheduleServiceInterface - Contract for league schedule business logic
 *
 * @phpstan-type LeagueGame array{
 *     date: string,
 *     visitor: int,
 *     visitorScore: int,
 *     visitorTeam: string,
 *     visitorRecord: string,
 *     home: int,
 *     homeScore: int,
 *     homeTeam: string,
 *     homeRecord: string,
 *     boxid: int,
 *     gameOfThatDay: int,
 *     boxScoreUrl: string,
 *     isUnplayed: bool,
 *     isUpcoming: bool,
 *     visitorWon: bool,
 *     homeWon: bool
 * }
 *
 * @phpstan-type MonthData array{label: string, dates: array<string, list<LeagueGame>>}
 *
 * @phpstan-type SchedulePageData array{
 *     gamesByMonth: array<string, MonthData>,
 *     firstUnplayedId: ?string,
 *     isPlayoffPhase: bool,
 *     playoffMonthKey: ?string,
 *     simLengthDays: int
 * }
 *
 * @see \LeagueSchedule\LeagueScheduleService For the concrete implementation
 */
interface LeagueScheduleServiceInterface
{
    /**
     * Get organized schedule page data
     *
     * @param \Season $season Current season
     * @param \League $league Current league
     * @param \Services\CommonMysqliRepository $commonRepo Common repository for team name lookups
     * @return SchedulePageData
     */
    public function getSchedulePageData(
        \Season $season,
        \League $league,
        \Services\CommonMysqliRepository $commonRepo
    ): array;
}
