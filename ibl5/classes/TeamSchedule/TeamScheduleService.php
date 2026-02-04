<?php

declare(strict_types=1);

namespace TeamSchedule;

use TeamSchedule\Contracts\TeamScheduleServiceInterface;
use Schedule\TeamSchedule as ScheduleTeamSchedule;

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
    /** @phpstan-var \mysqli */
    private object $db;

    /**
     * Constructor
     *
     * @param object $db Database connection
     * @phpstan-param \mysqli $db
     */
    public function __construct(object $db)
    {
        $this->db = $db;
    }

    /**
     * @see TeamScheduleServiceInterface::getProcessedSchedule()
     *
     * @return list<ScheduleGameRow>
     */
    public function getProcessedSchedule(int $teamId, \Season $season): array
    {
        $teamSchedule = ScheduleTeamSchedule::getSchedule($this->db, $teamId);

        /** @var list<ScheduleGameRow> $rows */
        $rows = [];
        $wins = 0;
        $losses = 0;
        $winStreak = 0;
        $lossStreak = 0;

        foreach ($teamSchedule as $gameRow) {
            /** @var array{Date: string, BoxID: int, Visitor: int, Home: int, VScore: int, HScore: int} $gameRow */
            $game = new \Game($gameRow);
            $opposingTeam = \Team::initialize($this->db, $game->getOpposingTeamID($teamId));

            $dateFormat = $game->dateObject instanceof \DateTime ? $game->dateObject->format('F') : '';

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
