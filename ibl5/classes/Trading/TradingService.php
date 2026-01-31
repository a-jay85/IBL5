<?php

declare(strict_types=1);

namespace Trading;

use Trading\Contracts\TradingServiceInterface;
use Trading\Contracts\TradingRepositoryInterface;

/**
 * @see TradingServiceInterface
 */
class TradingService implements TradingServiceInterface
{
    private TradingRepositoryInterface $repository;
    private \Services\CommonMysqliRepository $commonRepository;
    private object $mysqli_db;

    public function __construct(
        TradingRepositoryInterface $repository,
        \Services\CommonMysqliRepository $commonRepository,
        object $mysqli_db
    ) {
        $this->repository = $repository;
        $this->commonRepository = $commonRepository;
        $this->mysqli_db = $mysqli_db;
    }

    /**
     * @see TradingServiceInterface::getTradeOfferPageData()
     */
    public function getTradeOfferPageData(string $username, string $partnerTeam): array
    {
        $season = new \Season($this->mysqli_db);

        $userTeam = $this->commonRepository->getTeamnameFromUsername($username) ?? '';
        $userTeamId = $this->commonRepository->getTidFromTeamname($userTeam) ?? 0;
        $partnerTeamId = $this->commonRepository->getTidFromTeamname($partnerTeam) ?? 0;

        $userPlayers = $this->repository->getTeamPlayersForTrading($userTeamId);
        $userPicks = $this->repository->getTeamDraftPicksForTrading($userTeam);
        $userFutureSalary = $this->calculateFutureSalaries($userPlayers, $season);

        $partnerPlayers = $this->repository->getTeamPlayersForTrading($partnerTeamId);
        $partnerPicks = $this->repository->getTeamDraftPicksForTrading($partnerTeam);
        $partnerFutureSalary = $this->calculateFutureSalaries($partnerPlayers, $season);

        // Calculate cash exchange year range
        $currentSeasonEndingYear = $season->endingYear;
        $cashStartYear = 1;
        if ($this->isOffseasonPhase($season->phase)) {
            $cashStartYear = 2;
        }

        return [
            'userTeam' => $userTeam,
            'userTeamId' => $userTeamId,
            'partnerTeam' => $partnerTeam,
            'partnerTeamId' => $partnerTeamId,
            'userPlayers' => $userPlayers,
            'userPicks' => $userPicks,
            'userFutureSalary' => $userFutureSalary,
            'partnerPlayers' => $partnerPlayers,
            'partnerPicks' => $partnerPicks,
            'partnerFutureSalary' => $partnerFutureSalary,
            'seasonEndingYear' => $currentSeasonEndingYear,
            'seasonPhase' => $season->phase,
            'cashStartYear' => $cashStartYear,
            'cashEndYear' => 6,
        ];
    }

    /**
     * @see TradingServiceInterface::getTradeReviewPageData()
     */
    public function getTradeReviewPageData(string $username): array
    {
        $userTeam = $this->commonRepository->getTeamnameFromUsername($username) ?? '';
        $userTeamId = $this->commonRepository->getTidFromTeamname($userTeam) ?? 0;

        $allTradeRows = $this->repository->getAllTradeOffers();
        $tradeOffers = $this->groupTradeOffers($allTradeRows, $userTeam);

        // Get teams for the team selection sidebar
        $allTeams = $this->repository->getAllTeamsWithCity();
        $teams = $this->buildTeamList($allTeams);

        return [
            'userTeam' => $userTeam,
            'userTeamId' => $userTeamId,
            'tradeOffers' => $tradeOffers,
            'teams' => $teams,
        ];
    }

    /**
     * @see TradingServiceInterface::calculateFutureSalaries()
     */
    public function calculateFutureSalaries(array $players, \Season $season): array
    {
        $futureSalary = [
            'player' => [0, 0, 0, 0, 0, 0],
            'hold' => [0, 0, 0, 0, 0, 0],
        ];

        foreach ($players as $playerRow) {
            $contractYear = (int) $playerRow['cy'];

            // Adjust contract year based on season phase
            if ($this->isOffseasonPhase($season->phase)) {
                $contractYear++;
            }
            if ($contractYear === 0) {
                $contractYear = 1;
            }

            // Calculate future salary commitments
            $i = 0;
            $cy = $contractYear;
            while ($cy < 7) {
                $futureSalary['player'][$i] += (int) $playerRow["cy{$cy}"];
                if ((int) $playerRow["cy{$cy}"] > 0) {
                    $futureSalary['hold'][$i]++;
                }
                $cy++;
                $i++;
            }
        }

        return $futureSalary;
    }

    /**
     * Group trade offer rows by offer ID and resolve item details
     *
     * @param array $allTradeRows Raw trade info rows
     * @param string $userTeam Current user's team name
     * @return array<int, array> Grouped trade offers with resolved item descriptions
     */
    private function groupTradeOffers(array $allTradeRows, string $userTeam): array
    {
        $tradeOffers = [];

        foreach ($allTradeRows as $row) {
            $offerId = (int) $row['tradeofferid'];
            $from = $row['from'];
            $to = $row['to'];
            $approval = $row['approval'];
            $itemId = (int) $row['itemid'];
            $itemType = $row['itemtype'];

            $isInvolved = ($from === $userTeam || $to === $userTeam);
            if (!$isInvolved) {
                continue;
            }

            if (!isset($tradeOffers[$offerId])) {
                $tradeOffers[$offerId] = [
                    'from' => $from,
                    'to' => $to,
                    'approval' => $approval,
                    'oppositeTeam' => ($from === $userTeam) ? $to : $from,
                    'hasHammer' => ($approval === $userTeam || $approval === 'test'),
                    'items' => [],
                ];
            }

            $tradeOffers[$offerId]['items'][] = $this->resolveTradeItem(
                $itemId,
                $itemType,
                $from,
                $to,
                $offerId
            );
        }

        return $tradeOffers;
    }

    /**
     * Resolve a trade item into a displayable description
     *
     * @param int $itemId Item ID
     * @param string $itemType Item type ('cash', '0' for pick, '1' for player)
     * @param string $from Sending team
     * @param string $to Receiving team
     * @param int $offerId Trade offer ID
     * @return array{type: string, description: string, notes: string|null, from: string, to: string}
     */
    private function resolveTradeItem(int $itemId, string $itemType, string $from, string $to, int $offerId): array
    {
        if ($itemType === 'cash') {
            return $this->resolveCashItem($from, $to, $offerId);
        }

        if ($itemType === '0') {
            return $this->resolvePickItem($itemId, $from, $to);
        }

        // itemtype === '1' (player)
        return $this->resolvePlayerItem($itemId, $from, $to);
    }

    /**
     * Resolve a cash trade item
     */
    private function resolveCashItem(string $from, string $to, int $offerId): array
    {
        $cashDetails = $this->repository->getCashTransactionByOffer($offerId, $from);
        $cashAmounts = [];

        if ($cashDetails !== null) {
            for ($y = 1; $y <= 6; $y++) {
                if (isset($cashDetails["cy{$y}"]) && (int) $cashDetails["cy{$y}"] > 0) {
                    $cashAmounts[] = (int) $cashDetails["cy{$y}"];
                }
            }
        }

        $cashStr = implode(', ', $cashAmounts);

        return [
            'type' => 'cash',
            'description' => "The {$from} send {$cashStr} in cash to the {$to}.",
            'notes' => null,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * Resolve a draft pick trade item
     */
    private function resolvePickItem(int $itemId, string $from, string $to): array
    {
        $pick = $this->repository->getDraftPickById($itemId);
        $description = '';
        $notes = null;

        if ($pick !== null) {
            $pickTeam = $pick['teampick'];
            $pickYear = $pick['year'];
            $pickRound = $pick['round'];
            $notes = $pick['notes'] ?? null;
            if ($notes === '') {
                $notes = null;
            }
            $description = "The {$from} send the {$pickTeam} {$pickYear} Round {$pickRound} draft pick to the {$to}.";
        }

        return [
            'type' => 'pick',
            'description' => $description,
            'notes' => $notes,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * Resolve a player trade item
     */
    private function resolvePlayerItem(int $itemId, string $from, string $to): array
    {
        $player = $this->repository->getPlayerById($itemId);
        $description = '';

        if ($player !== null) {
            $playerName = $player['name'];
            $playerPos = $player['pos'];
            $description = "The {$from} send {$playerPos} {$playerName} to the {$to}.";
        }

        return [
            'type' => 'player',
            'description' => $description,
            'notes' => null,
            'from' => $from,
            'to' => $to,
        ];
    }

    /**
     * Check if the season is in an offseason phase where contract years advance
     */
    private function isOffseasonPhase(string $phase): bool
    {
        return $phase === 'Playoffs'
            || $phase === 'Draft'
            || $phase === 'Free Agency';
    }

    /**
     * Build filtered team list for team selection UI (excludes Free Agents)
     *
     * @param array $allTeams Raw team rows from repository
     * @return array<array{name: string, city: string, fullName: string, teamid: int, color1: string, color2: string}>
     */
    private function buildTeamList(array $allTeams): array
    {
        $teams = [];

        foreach ($allTeams as $row) {
            $teamName = $row['team_name'];
            if ($teamName === 'Free Agents') {
                continue;
            }

            $teamCity = $row['team_city'];
            $teams[] = [
                'name' => $teamName,
                'city' => $teamCity,
                'fullName' => "{$teamCity} {$teamName}",
                'teamid' => (int) $row['teamid'],
                'color1' => $row['color1'] ?? '333333',
                'color2' => $row['color2'] ?? 'FFFFFF',
            ];
        }

        return $teams;
    }
}
