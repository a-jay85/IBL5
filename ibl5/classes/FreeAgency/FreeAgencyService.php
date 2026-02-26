<?php

declare(strict_types=1);

namespace FreeAgency;

use FreeAgency\Contracts\FreeAgencyServiceInterface;
use FreeAgency\Contracts\FreeAgencyRepositoryInterface;
use FreeAgency\Contracts\FreeAgencyDemandRepositoryInterface;
use Player\Player;

/**
 * @see FreeAgencyServiceInterface
 */
class FreeAgencyService implements FreeAgencyServiceInterface
{
    private FreeAgencyRepositoryInterface $repository;
    private FreeAgencyDemandRepositoryInterface $demandRepository;
    private \mysqli $mysqli_db;

    public function __construct(
        FreeAgencyRepositoryInterface $repository,
        FreeAgencyDemandRepositoryInterface $demandRepository,
        \mysqli $mysqli_db
    ) {
        $this->repository = $repository;
        $this->demandRepository = $demandRepository;
        $this->mysqli_db = $mysqli_db;
    }

    /**
     * @see FreeAgencyServiceInterface::getMainPageData()
     *
     * @return array{capMetrics: array{totalSalaries: array<int, int>, softCapSpace: array<int, int>, hardCapSpace: array<int, int>, rosterSpots: array<int, int>}, team: \Team, season: \Season, allOtherPlayers: list<array<string, mixed>>}
     */
    public function getMainPageData(\Team $team, \Season $season): array
    {
        $capCalculator = new FreeAgencyCapCalculator($this->mysqli_db, $team, $season);
        /** @var array{totalSalaries: array<int, int>, softCapSpace: array<int, int>, hardCapSpace: array<int, int>, rosterSpots: array<int, int>} $capMetrics */
        $capMetrics = $capCalculator->calculateTeamCapMetrics();

        $allOtherPlayers = $this->repository->getAllPlayersExcludingTeam($team->name);

        return [
            'capMetrics' => $capMetrics,
            'team' => $team,
            'season' => $season,
            'allOtherPlayers' => $allOtherPlayers,
        ];
    }

    /**
     * @see FreeAgencyServiceInterface::getNegotiationData()
     *
     * @return array{player: Player, capMetrics: array{totalSalaries: array<int, int>, softCapSpace: array<int, int>, hardCapSpace: array<int, int>, rosterSpots: array<int, int>}, demands: array<string, int>, existingOffer: array<string, int>, amendedCapSpace: int, hasExistingOffer: bool, veteranMinimum: int, maxContract: int}
     */
    public function getNegotiationData(int $playerID, \Team $team, \Season $season): array
    {
        $player = Player::withPlayerID($this->mysqli_db, $playerID);

        $capCalculator = new FreeAgencyCapCalculator($this->mysqli_db, $team, $season);
        /** @var array{totalSalaries: array<int, int>, softCapSpace: array<int, int>, hardCapSpace: array<int, int>, rosterSpots: array<int, int>} $capMetrics */
        $capMetrics = $capCalculator->calculateTeamCapMetrics($player->name);

        $demands = $this->demandRepository->getPlayerDemands($player->playerID ?? 0);

        $existingOffer = $this->getExistingOffer($team->name, $player->name ?? '');

        $amendedCapSpace = $capMetrics['softCapSpace'][0] + $existingOffer['offer1'];
        $hasExistingOffer = $existingOffer['offer1'] > 0;

        $veteranMinimum = \ContractRules::getVeteranMinimumSalary($player->yearsOfExperience ?? 0);
        $maxContract = \ContractRules::getMaxContractSalary($player->yearsOfExperience ?? 0);

        return [
            'player' => $player,
            'capMetrics' => $capMetrics,
            'demands' => $demands,
            'existingOffer' => $existingOffer,
            'amendedCapSpace' => $amendedCapSpace,
            'hasExistingOffer' => $hasExistingOffer,
            'veteranMinimum' => $veteranMinimum,
            'maxContract' => $maxContract,
        ];
    }

    /**
     * @see FreeAgencyServiceInterface::getExistingOffer()
     */
    public function getExistingOffer(string $teamName, string $playerName): array
    {
        $offer = $this->repository->getExistingOffer($teamName, $playerName);

        if ($offer === null) {
            return [
                'offer1' => 0,
                'offer2' => 0,
                'offer3' => 0,
                'offer4' => 0,
                'offer5' => 0,
                'offer6' => 0,
            ];
        }

        return [
            'offer1' => (int) ($offer['offer1'] ?? 0),
            'offer2' => (int) ($offer['offer2'] ?? 0),
            'offer3' => (int) ($offer['offer3'] ?? 0),
            'offer4' => (int) ($offer['offer4'] ?? 0),
            'offer5' => (int) ($offer['offer5'] ?? 0),
            'offer6' => (int) ($offer['offer6'] ?? 0),
        ];
    }
}
