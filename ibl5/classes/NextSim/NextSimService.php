<?php

declare(strict_types=1);

namespace NextSim;

use NextSim\Contracts\NextSimServiceInterface;
use Player\Player;
use StrengthOfSchedule\StrengthOfScheduleCalculator;
use Team\Contracts\TeamQueryRepositoryInterface;
use TeamSchedule\Contracts\TeamScheduleRepositoryInterface;

/**
 * NextSimService - Business logic for next simulation games
 *
 * Processes upcoming game data and starting lineups.
 *
 * @phpstan-import-type NextSimGameData from \NextSim\Contracts\NextSimServiceInterface
 *
 * @see NextSimServiceInterface For the interface contract
 */
class NextSimService implements NextSimServiceInterface
{
    private \mysqli $db;

    private TeamScheduleRepositoryInterface $teamScheduleRepository;

    private TeamQueryRepositoryInterface $teamQueryRepo;

    /** @var array<int, float> Power rankings by team ID (0.0-100.0) */
    private array $teamPowerRankings;

    /**
     * Constructor
     *
     * @param \mysqli $db Database connection
     * @param TeamScheduleRepositoryInterface $teamScheduleRepository Team schedule repository
     * @param array<int, float> $teamPowerRankings Optional power rankings for SOS tier indicators
     */
    public function __construct(\mysqli $db, TeamScheduleRepositoryInterface $teamScheduleRepository, array $teamPowerRankings = [])
    {
        $this->db = $db;
        $this->teamScheduleRepository = $teamScheduleRepository;
        $this->teamQueryRepo = new \Team\TeamQueryRepository($db);
        $this->teamPowerRankings = $teamPowerRankings;
    }

    /**
     * @see NextSimServiceInterface::getNextSimGames()
     *
     * @return array<int, NextSimGameData>
     */
    public function getNextSimGames(int $teamId, \Season $season): array
    {
        $projectedGames = $this->teamScheduleRepository->getProjectedGamesNextSimResult(
            $teamId,
            $season->lastSimEndDate,
            $season->projectedNextSimEndDate->format('Y-m-d')
        );

        $lastSimEndDateObject = new \DateTime($season->lastSimEndDate);
        $games = [];

        foreach ($projectedGames as $gameRow) {
            /** @var array{Date: string, BoxID: int, Visitor: int, Home: int, VScore: int, HScore: int} $gameRow */
            $game = new \Game($gameRow);
            $gameDate = new \DateTime($game->date);
            $dayNumber = $gameDate->diff($lastSimEndDateObject)->format('%a');

            $opposingTeamId = $game->getOpposingTeamID($teamId);
            $opposingTeam = \Team::initialize($this->db, $opposingTeamId);
            $opponentRanking = $this->teamPowerRankings[$opposingTeamId] ?? 0.0;

            $games[] = [
                'game' => $game,
                'date' => $gameDate,
                'dayNumber' => (int)$dayNumber,
                'opposingTeam' => $opposingTeam,
                'locationPrefix' => $game->getUserTeamLocationPrefix($teamId),
                'opposingStarters' => $this->getOpposingStartingLineup($opposingTeam),
                'opponentTier' => $this->teamPowerRankings !== []
                    ? StrengthOfScheduleCalculator::assignTier($opponentRanking)
                    : '',
                'opponentPowerRanking' => $opponentRanking,
            ];
        }

        return $games;
    }

    /**
     * @see NextSimServiceInterface::getUserStartingLineup()
     *
     * @return array<string, Player>
     */
    public function getUserStartingLineup(\Team $team): array
    {
        $starters = [];
        $positions = ['PG', 'SG', 'SF', 'PF', 'C'];

        foreach ($positions as $position) {
            $playerId = $this->teamQueryRepo->getCurrentlySetStarterPlayerIDForPosition($team->teamID, $position);
            $starters[$position] = Player::withPlayerID($this->db, $playerId);
        }

        return $starters;
    }

    /**
     * Get opposing team's starting lineup
     *
     * @param \Team $opposingTeam Opposing team
     * @return array<string, Player> Starting players by position
     */
    private function getOpposingStartingLineup(\Team $opposingTeam): array
    {
        $starters = [];
        $positions = ['PG', 'SG', 'SF', 'PF', 'C'];

        foreach ($positions as $position) {
            $playerId = $this->teamQueryRepo->getLastSimStarterPlayerIDForPosition($opposingTeam->teamID, $position);
            $starters[$position] = Player::withPlayerID($this->db, $playerId);
        }

        return $starters;
    }
}
