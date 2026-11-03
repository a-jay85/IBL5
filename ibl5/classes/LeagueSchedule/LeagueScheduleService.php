<?php

declare(strict_types=1);

namespace LeagueSchedule;

use League\League;
use LeagueSchedule\Contracts\LeagueScheduleRepositoryInterface;
use LeagueSchedule\Contracts\LeagueScheduleServiceInterface;
use BasketballStats\StrengthOfScheduleCalculator;
use Season\Season;

/**
 * LeagueScheduleService - Business logic for league-wide schedule display
 *
 * Organizes games by month, computes upcoming/unplayed flags,
 * and handles playoff reordering.
 *
 * @phpstan-import-type LeagueGame from LeagueScheduleServiceInterface
 * @phpstan-import-type MonthData from LeagueScheduleServiceInterface
 * @phpstan-import-type SchedulePageData from LeagueScheduleServiceInterface
 * @phpstan-import-type ScheduleRow from LeagueScheduleRepositoryInterface
 *
 * @see LeagueScheduleServiceInterface For the interface contract
 */
class LeagueScheduleService implements LeagueScheduleServiceInterface
{
    /** Power ranking assumed for a team missing from $teamPowerRankings. */
    private const UNRANKED_POWER_RANKING = 0.0;

    private LeagueScheduleRepositoryInterface $repository;

    /** @var array<int, float> Power rankings by team ID (0.0-100.0) */
    private array $teamPowerRankings;

    /**
     * @param LeagueScheduleRepositoryInterface $repository
     * @param array<int, float> $teamPowerRankings Optional power rankings for SOS tier indicators
     */
    public function __construct(LeagueScheduleRepositoryInterface $repository, array $teamPowerRankings = [])
    {
        $this->repository = $repository;
        $this->teamPowerRankings = $teamPowerRankings;
    }

    /**
     * @see LeagueScheduleServiceInterface::getSchedulePageData()
     *
     * @return SchedulePageData
     */
    public function getSchedulePageData(
        Season $season,
        League $league,
        \Repositories\Contracts\TeamIdentityRepositoryInterface $commonRepo
    ): array {
        $projectedNextSimEndDate = $season->projectedNextSimEndDate;
        $simLengthDays = $league->getSimLengthInDays();

        $rawGames = $this->repository->getAllGamesWithBoxScoreInfo($season->endingYear);
        $teamRecords = $this->repository->getTeamRecords();

        [$gamesByMonth, $firstUnplayedId] = $this->groupGamesByMonth(
            $rawGames,
            $teamRecords,
            $projectedNextSimEndDate,
            $commonRepo
        );

        // In playoff phases, relabel June as "Playoffs" and move to front
        $isPlayoffPhase = in_array($season->phase, ['Playoffs', 'Draft', 'Free Agency'], true);
        $playoffMonthKey = $isPlayoffPhase ? $this->findPlayoffMonthKey($gamesByMonth) : null;
        if ($playoffMonthKey !== null) {
            $gamesByMonth = $this->movePlayoffMonthToFront($gamesByMonth, $playoffMonthKey);
        }

        return [
            'gamesByMonth' => $gamesByMonth,
            'firstUnplayedId' => $firstUnplayedId,
            'isPlayoffPhase' => $isPlayoffPhase,
            'playoffMonthKey' => $playoffMonthKey,
            'simLengthDays' => $simLengthDays,
        ];
    }

    /**
     * Group raw schedule rows into month buckets keyed 'Y-m', each holding
     * per-date lists of decorated game rows, and note the first upcoming game.
     *
     * @param list<ScheduleRow> $rawGames
     * @param array<int, string> $teamRecords
     * @return array{array<string, MonthData>, ?string}
     */
    private function groupGamesByMonth(
        array $rawGames,
        array $teamRecords,
        \DateTimeInterface $projectedNextSimEndDate,
        \Repositories\Contracts\TeamIdentityRepositoryInterface $commonRepo
    ): array {
        /** @var array<string, MonthData> $gamesByMonth */
        $gamesByMonth = [];
        /** @var ?string $firstUnplayedId */
        $firstUnplayedId = null;

        foreach ($rawGames as $row) {
            $date = $row['game_date'];
            [$monthKey, $monthLabel] = $this->resolveMonth($date);

            if (!isset($gamesByMonth[$monthKey])) {
                $gamesByMonth[$monthKey] = [
                    'label' => $monthLabel,
                    'dates' => [],
                ];
            }

            if (!isset($gamesByMonth[$monthKey]['dates'][$date])) {
                $gamesByMonth[$monthKey]['dates'][$date] = [];
            }

            $game = $this->buildGameRow($row, $teamRecords, $projectedNextSimEndDate, $commonRepo);

            if ($game['isUpcoming'] && $firstUnplayedId === null) {
                $firstUnplayedId = 'game-' . $game['boxid'];
            }

            $gamesByMonth[$monthKey]['dates'][$date][] = $game;
        }

        return [$gamesByMonth, $firstUnplayedId];
    }

    /**
     * Month bucket key ('Y-m') and label ('F') for a game date; unparseable
     * dates fall into '1970-01' / 'Unknown'.
     *
     * @return array{string, string}
     */
    private function resolveMonth(string $date): array
    {
        $dateTimestamp = strtotime($date);
        if ($dateTimestamp === false) {
            return ['1970-01', 'Unknown'];
        }

        return [date('Y-m', $dateTimestamp), date('F', $dateTimestamp)];
    }

    /**
     * @param ScheduleRow $row
     * @param array<int, string> $teamRecords
     * @return LeagueGame
     */
    private function buildGameRow(
        array $row,
        array $teamRecords,
        \DateTimeInterface $projectedNextSimEndDate,
        \Repositories\Contracts\TeamIdentityRepositoryInterface $commonRepo
    ): array {
        $date = $row['game_date'];
        $visitor = $row['visitor_teamid'];
        $visitorScore = $row['visitor_score'];
        $home = $row['home_teamid'];
        $homeScore = $row['home_score'];
        $boxid = $row['box_id'];
        $gameOfThatDay = $row['game_of_that_day'];

        $gameDate = date_create($date);
        $isUpcoming = ($gameDate !== false) && \Utilities\ScheduleHighlighter::shouldHighlight(
            $visitorScore,
            $homeScore,
            $gameDate,
            $projectedNextSimEndDate
        );

        $visitorTeam = $commonRepo->getTeamnameFromTeamID($visitor);
        $homeTeam = $commonRepo->getTeamnameFromTeamID($home);

        return [
            'date' => $date,
            'visitor' => $visitor,
            'visitorScore' => $visitorScore,
            'visitorTeam' => $visitorTeam ?? '',
            'visitorRecord' => $teamRecords[$visitor] ?? '',
            'home' => $home,
            'homeScore' => $homeScore,
            'homeTeam' => $homeTeam ?? '',
            'home_record' => $teamRecords[$home] ?? '',
            'boxid' => $boxid,
            'gameOfThatDay' => $gameOfThatDay,
            'boxScoreUrl' => \Utilities\BoxScoreUrlBuilder::buildUrl($date, $gameOfThatDay, $boxid),
            'visitorBoxScoreUrl' => \Utilities\BoxScoreUrlBuilder::buildUrl($date, $gameOfThatDay, $boxid, $visitor),
            'homeBoxScoreUrl' => \Utilities\BoxScoreUrlBuilder::buildUrl($date, $gameOfThatDay, $boxid, $home),
            'isUnplayed' => \Utilities\ScheduleHighlighter::isGameUnplayed($visitorScore, $homeScore),
            'isUpcoming' => $isUpcoming,
            'visitorWon' => ($visitorScore > $homeScore),
            'homeWon' => ($homeScore > $visitorScore),
            'visitorTier' => $this->tierFor($visitor),
            'homeTier' => $this->tierFor($home),
        ];
    }

    private function tierFor(int $teamId): string
    {
        return StrengthOfScheduleCalculator::assignTierOrEmpty(
            $this->teamPowerRankings,
            $this->teamPowerRankings[$teamId] ?? self::UNRANKED_POWER_RANKING
        );
    }

    /**
     * First month key (in grouping order) whose month is the IBL playoff month.
     *
     * @param array<string, MonthData> $gamesByMonth
     */
    private function findPlayoffMonthKey(array $gamesByMonth): ?string
    {
        foreach (array_keys($gamesByMonth) as $key) {
            $monthStart = \DateTimeImmutable::createFromFormat('!Y-m', $key);
            if ($monthStart !== false && (int) $monthStart->format('n') === Season::IBL_PLAYOFF_MONTH) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Relabel the playoff month "Playoffs" and move it to the front. The array
     * union keeps the left-hand entry for the shared key and the original order
     * of every other month.
     *
     * @param array<string, MonthData> $gamesByMonth
     * @return array<string, MonthData>
     */
    private function movePlayoffMonthToFront(array $gamesByMonth, string $playoffMonthKey): array
    {
        $playoffMonth = $gamesByMonth[$playoffMonthKey];
        $playoffMonth['label'] = 'Playoffs';

        return [$playoffMonthKey => $playoffMonth] + $gamesByMonth;
    }
}
