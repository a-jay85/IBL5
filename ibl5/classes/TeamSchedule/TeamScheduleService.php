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
 * @see TeamScheduleServiceInterface For the interface contract
 */
class TeamScheduleService implements TeamScheduleServiceInterface
{
    private object $db;

    /**
     * Constructor
     *
     * @param object $db Database connection
     */
    public function __construct(object $db)
    {
        $this->db = $db;
    }

    /**
     * @see TeamScheduleServiceInterface::getProcessedSchedule()
     */
    public function getProcessedSchedule(int $teamId, \Season $season): array
    {
        $teamSchedule = ScheduleTeamSchedule::getSchedule($this->db, $teamId);

        $rows = [];
        $wins = 0;
        $losses = 0;
        $winStreak = 0;
        $lossStreak = 0;

        foreach ($teamSchedule as $gameRow) {
            $game = new \Game($gameRow);
            $opposingTeam = \Team::initialize($this->db, $game->getOpposingTeamID($teamId));

            $row = [
                'game' => $game,
                'currentMonth' => $game->dateObject->format('F'),
                'opposingTeam' => $opposingTeam,
                'opponentText' => $game->getUserTeamLocationPrefix($teamId) . ' ' . 
                    $opposingTeam->name . ' (' . $opposingTeam->seasonRecord . ')',
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
                $row['highlight'] = \Utilities\ScheduleHighlighter::isNextSimGame(
                    $game->dateObject,
                    $season->projectedNextSimEndDate
                ) ? 'next-sim' : '';
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
