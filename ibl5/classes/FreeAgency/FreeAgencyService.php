<?php

declare(strict_types=1);

namespace FreeAgency;

use FreeAgency\Contracts\FreeAgencyServiceInterface;
use FreeAgency\Contracts\FreeAgencyRepositoryInterface;
use FreeAgency\Contracts\FreeAgencyDemandRepositoryInterface;
use Player\Player;
use Team\Team;
use Team\Contracts\TeamQueryRepositoryInterface;
use Season\Season;

/**
 * @see FreeAgencyServiceInterface
 *
 * @phpstan-import-type PlayerRow from \Repositories\Contracts\PlayerLookupRepositoryInterface
 */
class FreeAgencyService implements FreeAgencyServiceInterface
{
    private FreeAgencyRepositoryInterface $repository;
    private FreeAgencyDemandRepositoryInterface $demandRepository;
    private \mysqli $mysqli_db;
    private TeamQueryRepositoryInterface $teamQueryRepo;

    public function __construct(
        FreeAgencyRepositoryInterface $repository,
        FreeAgencyDemandRepositoryInterface $demandRepository,
        \mysqli $mysqli_db,
        ?TeamQueryRepositoryInterface $teamQueryRepo = null
    ) {
        $this->repository = $repository;
        $this->demandRepository = $demandRepository;
        $this->mysqli_db = $mysqli_db;
        $this->teamQueryRepo = $teamQueryRepo ?? new \Team\TeamQueryRepository($mysqli_db);
    }

    /**
     * @see FreeAgencyServiceInterface::getMainPageData()
     *
     * @return array{capMetrics: array{totalSalaries: array<int, int>, softCapSpace: array<int, int>, hardCapSpace: array<int, int>, rosterSpots: array<int, int>}, team: Team, season: Season, allOtherPlayers: list<Player>, teamColorsByTeamId: array<int, array{color1: string, color2: string}>, playersUnderContract: list<Player>, unsignedFreeAgents: list<Player>, offerPlayers: list<array{player: Player, offer: array<string, int>}>, cashPlayers: list<array{player: Player, label: string}>}
     */
    public function getMainPageData(Team $team, Season $season): array
    {
        $capCalculator = new FreeAgencyCapCalculator($this->mysqli_db, $team, $season);
        /** @var array{totalSalaries: array<int, int>, softCapSpace: array<int, int>, hardCapSpace: array<int, int>, rosterSpots: array<int, int>} $capMetrics */
        $capMetrics = $capCalculator->calculateTeamCapMetrics();

        $allOtherPlayerRows = $this->repository->getAllPlayersExcludingTeam($team->teamid);
        $allOtherPlayers = [];
        $teamIds = [];
        foreach ($allOtherPlayerRows as $row) {
            $player = Player::withPlrRow($this->mysqli_db, $row);
            $allOtherPlayers[] = $player;
            $tid = $player->teamid ?? 0;
            if ($tid !== 0) {
                $teamIds[$tid] = true;
            }
        }

        $teamColorsByTeamId = [];
        foreach (array_keys($teamIds) as $tid) {
            $teamColorsByTeamId[$tid] = \Player\Views\TeamColorHelper::getTeamColors($this->mysqli_db, $tid);
        }

        $rosterPartition = $this->buildRosterPartition($team->teamid, $season);
        $offerPlayers = $this->buildOfferPlayers($team->teamid);
        $cashPlayers = $this->buildCashPlayers($team->teamid);

        return [
            'capMetrics' => $capMetrics,
            'team' => $team,
            'season' => $season,
            'allOtherPlayers' => $allOtherPlayers,
            'teamColorsByTeamId' => $teamColorsByTeamId,
            'playersUnderContract' => $rosterPartition['playersUnderContract'],
            'unsignedFreeAgents' => $rosterPartition['unsignedFreeAgents'],
            'offerPlayers' => $offerPlayers,
            'cashPlayers' => $cashPlayers,
        ];
    }

    /**
     * @return array{playersUnderContract: list<Player>, unsignedFreeAgents: list<Player>}
     */
    private function buildRosterPartition(int $teamId, Season $season): array
    {
        $rosterRows = $this->teamQueryRepo->getRosterUnderContractOrderedByOrdinal($teamId);
        $contracted = [];
        $unsigned = [];

        foreach ($rosterRows as $playerRow) {
            $player = Player::withPlrRow($this->mysqli_db, $playerRow);
            if ($player->isPlayerFreeAgent($season) && !$player->isSalaryPlaceholder()) {
                $unsigned[] = $player;
            } else {
                $contracted[] = $player;
            }
        }

        return [
            'playersUnderContract' => $contracted,
            'unsignedFreeAgents' => $unsigned,
        ];
    }

    /**
     * @return list<array{player: Player, offer: array<string, int>}>
     */
    private function buildOfferPlayers(int $teamId): array
    {
        $offersResult = $this->teamQueryRepo->getFreeAgencyOffers($teamId);
        $result = [];

        foreach ($offersResult as $offerRow) {
            $player = Player::withPlayerID($this->mysqli_db, $offerRow['pid'] ?? 0);
            $result[] = [
                'player' => $player,
                'offer' => [
                    'offer1' => $offerRow['offer1'],
                    'offer2' => $offerRow['offer2'],
                    'offer3' => $offerRow['offer3'],
                    'offer4' => $offerRow['offer4'],
                    'offer5' => $offerRow['offer5'],
                    'offer6' => $offerRow['offer6'],
                ],
            ];
        }

        return $result;
    }

    /**
     * @return list<array{player: Player, label: string}>
     */
    private function buildCashPlayers(int $teamId): array
    {
        $cashRepo = new \Trading\CashConsiderationRepository($this->mysqli_db);
        $cashRows = $cashRepo->getTeamCashConsiderations($teamId);
        $result = [];

        foreach ($cashRows as $cashRow) {
            $cashPlayerRow = \Team\TeamTableService::cashConsiderationToRosterRow($cashRow);
            /** @phpstan-ignore argument.type (cashConsiderationToRosterRow produces a PlayerRow-shaped array) */
            $player = Player::withPlrRow($this->mysqli_db, $cashPlayerRow);
            $label = is_string($cashRow['label'] ?? null) ? $cashRow['label'] : '';
            $result[] = [
                'player' => $player,
                'label' => $label,
            ];
        }

        return $result;
    }

    /**
     * @see FreeAgencyServiceInterface::getNegotiationData()
     *
     * @return array{player: Player, capMetrics: array{totalSalaries: array<int, int>, softCapSpace: array<int, int>, hardCapSpace: array<int, int>, rosterSpots: array<int, int>}, demands: array<string, int>, existingOffer: array<string, int>, amendedCapSpace: int, hasExistingOffer: bool, veteranMinimum: int, maxContract: int}
     */
    public function getNegotiationData(int $playerID, Team $team, Season $season): array
    {
        $player = Player::withPlayerID($this->mysqli_db, $playerID);

        $capCalculator = new FreeAgencyCapCalculator($this->mysqli_db, $team, $season);
        /** @var array{totalSalaries: array<int, int>, softCapSpace: array<int, int>, hardCapSpace: array<int, int>, rosterSpots: array<int, int>} $capMetrics */
        $capMetrics = $capCalculator->calculateTeamCapMetrics($player->playerID);

        $demands = $this->demandRepository->getPlayerDemands($player->playerID ?? 0);

        $existingOffer = $this->getExistingOffer($team->teamid, $player->playerID ?? 0);

        // calculateTeamCapMetrics() already excludes this player's existing offer,
        // so softCapSpace[0] is the true available cap space for a new/replacement offer.
        $amendedCapSpace = $capMetrics['softCapSpace'][0];
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
    public function getExistingOffer(int $teamid, int $pid): array
    {
        $offer = $this->repository->getExistingOffer($teamid, $pid);

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
