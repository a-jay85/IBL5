<?php

declare(strict_types=1);

namespace Waivers;

use League\League;
use Player\Player;
use Season\Season;
use Team\Contracts\TeamQueryRepositoryInterface;
use Team\Team;
use Waivers\Contracts\WaiversProcessorInterface;
use Waivers\Contracts\WaiversServiceInterface;
use Waivers\Contracts\WaiversViewInterface;

/**
 * @see WaiversServiceInterface
 *
 * @phpstan-import-type WaiverFormData from WaiversServiceInterface
 */
class WaiversService implements WaiversServiceInterface
{
    private \Services\CommonMysqliRepository $commonRepository;
    private WaiversProcessorInterface $processor;
    private WaiversViewInterface $view;
    private TeamQueryRepositoryInterface $teamQueryRepo;
    private \mysqli $db;

    public function __construct(
        \Services\CommonMysqliRepository $commonRepository,
        WaiversProcessorInterface $processor,
        WaiversViewInterface $view,
        TeamQueryRepositoryInterface $teamQueryRepo,
        \mysqli $db
    ) {
        $this->commonRepository = $commonRepository;
        $this->processor = $processor;
        $this->view = $view;
        $this->teamQueryRepo = $teamQueryRepo;
        $this->db = $db;
    }

    /**
     * @see WaiversServiceInterface::getWaiverFormData()
     *
     * @return WaiverFormData
     */
    public function getWaiverFormData(string $username, string $action): array
    {
        $teamName = $this->commonRepository->getTeamnameFromUsername($username) ?? '';
        $team = Team::initialize($this->db, $teamName);
        $season = new Season($this->db);

        $tableData = $this->getTableResultAndStyle($team, $season, $action);
        $players = $this->buildPlayerOptions($tableData['tableResult'], $action, $season);

        $openRosterSpots = 15 - count($this->teamQueryRepo->getHealthyAndInjuredPlayersOrderedByName($team->teamID, $season));
        $healthyOpenRosterSpots = 15 - count($this->teamQueryRepo->getHealthyPlayersOrderedByName($team->teamID, $season));

        return [
            'team' => $team,
            'players' => $players,
            'openRosterSpots' => $openRosterSpots,
            'healthyOpenRosterSpots' => $healthyOpenRosterSpots,
            'tableResult' => $tableData['tableResult'],
            'styleTeam' => $tableData['styleTeam'],
            'season' => $season,
        ];
    }

    /**
     * @param array<int, array<string, mixed>|\Player\Player> $result
     * @return list<string>
     */
    private function buildPlayerOptions(array $result, string $action, Season $season): array
    {
        $timeNow = time();
        /** @var list<string> $players */
        $players = [];

        foreach ($result as $playerRow) {
            $player = Player::withPlrRow($this->db, $playerRow);
            $contract = $this->processor->getPlayerContractDisplay($player, $season);
            $waitTime = '';

            if ($action === 'add' && $player->timeDroppedOnWaivers !== null && $player->timeDroppedOnWaivers > 0) {
                $waitTime = $this->processor->getWaiverWaitTime($player->timeDroppedOnWaivers, $timeNow);
            }

            $playerID = $player->playerID ?? 0;
            $playerName = $player->name ?? '';
            $players[] = $this->view->buildPlayerOption(
                $playerID,
                $playerName,
                $contract,
                $waitTime
            );
        }

        return $players;
    }

    /**
     * @return array{tableResult: array<int, array<string, mixed>|\Player\Player>, styleTeam: Team}
     */
    private function getTableResultAndStyle(Team $team, Season $season, string $action): array
    {
        $league = new League($this->db);

        if ($action === 'waive') {
            return [
                'tableResult' => $this->teamQueryRepo->getHealthyAndInjuredPlayersOrderedByName($team->teamID, $season),
                'styleTeam' => $team,
            ];
        }

        if ($season->isOffseasonPhase()) {
            return [
                'tableResult' => $league->getFreeAgentsResult($season),
                'styleTeam' => Team::initialize($this->db, League::FREE_AGENTS_TEAMID),
            ];
        }

        return [
            'tableResult' => $league->getWaivedPlayersResult(),
            'styleTeam' => Team::initialize($this->db, League::FREE_AGENTS_TEAMID),
        ];
    }
}
