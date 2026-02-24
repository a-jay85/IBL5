<?php

declare(strict_types=1);

namespace TeamSchedule;

use StrengthOfSchedule\StrengthOfScheduleCalculator;
use TeamSchedule\Contracts\TeamScheduleRepositoryInterface;
use TeamSchedule\Contracts\TeamScheduleServiceInterface;

/**
 * TeamScheduleService - Business logic for team schedule display
 *
 * Processes schedule data and calculates win/loss records and streaks.
 *
 * @phpstan-import-type ScheduleGameRow from TeamScheduleServiceInterface
 *
 * @see TeamScheduleServiceInterface For the interface contract
 */
class TeamScheduleService implements TeamScheduleServiceInterface
{
    private \mysqli $db;

    private TeamScheduleRepositoryInterface $repository;

    /** @var array<int, \Team> */
    private array $teamCache = [];

    /** @var array<int, float> Power rankings by team ID (0.0-100.0) */
    private array $teamPowerRankings;

    /**
     * Constructor
     *
     * @param \mysqli $db Database connection
     * @param TeamScheduleRepositoryInterface $repository Team schedule repository
     * @param array<int, float> $teamPowerRankings Optional power rankings for SOS tier indicators
     */
    public function __construct(\mysqli $db, TeamScheduleRepositoryInterface $repository, array $teamPowerRankings = [])
    {
        $this->db = $db;
        $this->repository = $repository;
        $this->teamPowerRankings = $teamPowerRankings;
    }

    /**
     * @see TeamScheduleServiceInterface::getProcessedSchedule()
     *
     * @return list<ScheduleGameRow>
     */
    public function getProcessedSchedule(int $teamId, \Season $season): array
    {
        $teamSchedule = $this->repository->getSchedule($teamId);

        /** @var list<ScheduleGameRow> $rows */
        $rows = [];
        $wins = 0;
        $losses = 0;
        $winStreak = 0;
        $lossStreak = 0;

        foreach ($teamSchedule as $gameRow) {
            $game = new \Game($gameRow);
            $opposingTeamId = $game->getOpposingTeamID($teamId);
            if (!isset($this->teamCache[$opposingTeamId])) {
                $this->teamCache[$opposingTeamId] = \Team::initialize($this->db, $opposingTeamId);
            }
            $opposingTeam = $this->teamCache[$opposingTeamId];

            $dateFormat = $game->dateObject instanceof \DateTime ? $game->dateObject->format('F') : '';

            $opponentRanking = $this->teamPowerRankings[$opposingTeamId] ?? 0.0;
            $opponentTier = $this->teamPowerRankings !== []
                ? StrengthOfScheduleCalculator::assignTier($opponentRanking)
                : '';

            $row = [
                'game' => $game,
                'currentMonth' => $dateFormat,
                'opposingTeam' => $opposingTeam,
                'opponentText' => $game->getUserTeamLocationPrefix($teamId) . ' ' .
                    $opposingTeam->name . ' (' . ($opposingTeam->seasonRecord ?? '') . ')',
                'highlight' => '',
                'gameResult' => '',
                'wins' => 0,
                'losses' => 0,
                'streak' => '',
                'winLossColor' => '',
                'isUnplayed' => $game->isUnplayed,
                'opponentTier' => $opponentTier,
            ];

            if ($game->isUnplayed) {
                // Check if game is projected for next sim using shared utility
                $gameDate = $game->dateObject;
                $row['highlight'] = ($gameDate instanceof \DateTimeInterface && \Utilities\ScheduleHighlighter::isNextSimGame(
                    $gameDate,
                    $season->projectedNextSimEndDate
                )) ? 'next-sim' : '';
            } else {
                if ($teamId === $game->winningTeamID) {
                    $row['gameResult'] = 'W';
                    $wins++;
                    $winStreak++;
                    $lossStreak = 0;
                    $row['winLossColor'] = 'green';
                } else {
                    $row['gameResult'] = 'L';
                    $losses++;
                    $lossStreak++;
                    $winStreak = 0;
                    $row['winLossColor'] = 'red';
                }

                $row['wins'] = $wins;
                $row['losses'] = $losses;
                $row['streak'] = ($winStreak > $lossStreak)
                    ? 'W ' . $winStreak
                    : 'L ' . $lossStreak;
            }

            $rows[] = $row;
        }

        return $rows;
    }
}
