<?php

declare(strict_types=1);

namespace LeagueSchedule;

use LeagueSchedule\Contracts\LeagueScheduleRepositoryInterface;
use LeagueSchedule\Contracts\LeagueScheduleServiceInterface;
use StrengthOfSchedule\StrengthOfScheduleCalculator;

/**
 * LeagueScheduleService - Business logic for league-wide schedule display
 *
 * Organizes games by month, computes upcoming/unplayed flags,
 * and handles playoff reordering.
 *
 * @phpstan-import-type LeagueGame from LeagueScheduleServiceInterface
 * @phpstan-import-type MonthData from LeagueScheduleServiceInterface
 * @phpstan-import-type SchedulePageData from LeagueScheduleServiceInterface
 *
 * @see LeagueScheduleServiceInterface For the interface contract
 */
class LeagueScheduleService implements LeagueScheduleServiceInterface
{
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
        \Season $season,
        \League $league,
        \Services\CommonMysqliRepository $commonRepo
    ): array {
        $projectedNextSimEndDate = $season->projectedNextSimEndDate;
        $simLengthDays = $league->getSimLengthInDays();

        $rawGames = $this->repository->getAllGamesWithBoxScoreInfo();
        $teamRecords = $this->repository->getTeamRecords();

        /** @var array<string, MonthData> $gamesByMonth */
        $gamesByMonth = [];
        /** @var ?string $firstUnplayedId */
        $firstUnplayedId = null;

        foreach ($rawGames as $row) {
            $date = $row['Date'];
            $visitor = $row['Visitor'];
            $visitorScore = $row['VScore'];
            $home = $row['Home'];
            $homeScore = $row['HScore'];
            $boxid = $row['BoxID'];
            $gameOfThatDay = $row['gameOfThatDay'];

            $dateTimestamp = strtotime($date);
            $monthKey = $dateTimestamp !== false ? date('Y-m', $dateTimestamp) : '1970-01';
            $monthLabel = $dateTimestamp !== false ? date('F', $dateTimestamp) : 'Unknown';

            if (!isset($gamesByMonth[$monthKey])) {
                $gamesByMonth[$monthKey] = [
                    'label' => $monthLabel,
                    'dates' => [],
                ];
            }

            if (!isset($gamesByMonth[$monthKey]['dates'][$date])) {
                $gamesByMonth[$monthKey]['dates'][$date] = [];
            }

            $gameDate = date_create($date);
            $isUpcoming = ($gameDate !== false) && \Utilities\ScheduleHighlighter::shouldHighlight(
                $visitorScore,
                $homeScore,
                $gameDate,
                $projectedNextSimEndDate
            );
            $isUnplayed = \Utilities\ScheduleHighlighter::isGameUnplayed($visitorScore, $homeScore);

            if ($isUpcoming && $firstUnplayedId === null) {
                $firstUnplayedId = 'game-' . $boxid;
            }

            $visitorTeam = $commonRepo->getTeamnameFromTeamID($visitor);
            $homeTeam = $commonRepo->getTeamnameFromTeamID($home);

            $visitorTier = $this->teamPowerRankings !== []
                ? StrengthOfScheduleCalculator::assignTier($this->teamPowerRankings[$visitor] ?? 0.0)
                : '';
            $homeTier = $this->teamPowerRankings !== []
                ? StrengthOfScheduleCalculator::assignTier($this->teamPowerRankings[$home] ?? 0.0)
                : '';

            $gamesByMonth[$monthKey]['dates'][$date][] = [
                'date' => $date,
                'visitor' => $visitor,
                'visitorScore' => $visitorScore,
                'visitorTeam' => $visitorTeam ?? '',
                'visitorRecord' => $teamRecords[$visitor] ?? '',
                'home' => $home,
                'homeScore' => $homeScore,
                'homeTeam' => $homeTeam ?? '',
                'homeRecord' => $teamRecords[$home] ?? '',
                'boxid' => $boxid,
                'gameOfThatDay' => $gameOfThatDay,
                'boxScoreUrl' => \Utilities\BoxScoreUrlBuilder::buildUrl($date, $gameOfThatDay, $boxid),
                'isUnplayed' => $isUnplayed,
                'isUpcoming' => $isUpcoming,
                'visitorWon' => ($visitorScore > $homeScore),
                'homeWon' => ($homeScore > $visitorScore),
                'visitorTier' => $visitorTier,
                'homeTier' => $homeTier,
            ];
        }

        // In playoff phases, relabel June as "Playoffs" and move to front
        $isPlayoffPhase = in_array($season->phase, ['Playoffs', 'Draft', 'Free Agency'], true);
        $playoffMonthKey = null;
        if ($isPlayoffPhase) {
            foreach (array_keys($gamesByMonth) as $key) {
                $monthTimestamp = strtotime($key . '-01');
                if ($monthTimestamp !== false && (int)date('n', $monthTimestamp) === \Season::IBL_PLAYOFF_MONTH) {
                    $playoffMonthKey = $key;
                    break;
                }
            }
            if ($playoffMonthKey !== null && isset($gamesByMonth[$playoffMonthKey])) {
                $gamesByMonth[$playoffMonthKey]['label'] = 'Playoffs';
                $reordered = [$playoffMonthKey => $gamesByMonth[$playoffMonthKey]];
                unset($gamesByMonth[$playoffMonthKey]);
                $gamesByMonth = $reordered + $gamesByMonth;
            }
        }

        return [
            'gamesByMonth' => $gamesByMonth,
            'firstUnplayedId' => $firstUnplayedId,
            'isPlayoffPhase' => $isPlayoffPhase,
            'playoffMonthKey' => $playoffMonthKey,
            'simLengthDays' => $simLengthDays,
        ];
    }
}
