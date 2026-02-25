<?php

declare(strict_types=1);

namespace Trading;

use Trading\Contracts\TradingServiceInterface;
use Trading\Contracts\TradingRepositoryInterface;
use Trading\Contracts\TradeCashRepositoryInterface;

/**
 * @see TradingServiceInterface
 *
 * @phpstan-import-type TradeInfoRow from \Trading\Contracts\TradingRepositoryInterface
 * @phpstan-import-type TradeCashRow from \Trading\Contracts\TradeCashRepositoryInterface
 * @phpstan-import-type DraftPickRow from \Trading\Contracts\TradingRepositoryInterface
 * @phpstan-import-type TradingDraftPickRow from \Trading\Contracts\TradingRepositoryInterface
 * @phpstan-import-type TradingPlayerRow from \Trading\Contracts\TradingRepositoryInterface
 * @phpstan-import-type TeamWithCityRow from \Trading\Contracts\TradingRepositoryInterface
 * @phpstan-import-type PlayerRow from \Services\CommonMysqliRepository
 */
class TradingService implements TradingServiceInterface
{
    private TradingRepositoryInterface $repository;
    private TradeCashRepositoryInterface $cashRepository;
    private \Services\CommonMysqliRepository $commonRepository;
    private \mysqli $mysqli_db;

    public function __construct(
        TradingRepositoryInterface $repository,
        \Services\CommonMysqliRepository $commonRepository,
        \mysqli $mysqli_db,
        ?TradeCashRepositoryInterface $cashRepository = null
    ) {
        $this->repository = $repository;
        $this->cashRepository = $cashRepository ?? new TradeCashRepository($mysqli_db);
        $this->commonRepository = $commonRepository;
        $this->mysqli_db = $mysqli_db;
    }

    /**
     * @see TradingServiceInterface::getTradeOfferPageData()
     *
     * @return array{userTeam: string, userTeamId: int, partnerTeam: string, partnerTeamId: int, userPlayers: list<TradingPlayerRow>, userPicks: list<TradingDraftPickRow>, userFutureSalary: array{player: array<int, int>, hold: array<int, int>}, partnerPlayers: list<TradingPlayerRow>, partnerPicks: list<TradingDraftPickRow>, partnerFutureSalary: array{player: array<int, int>, hold: array<int, int>}, seasonEndingYear: int, seasonPhase: string, cashStartYear: int, cashEndYear: int, userTeamColor1: string, userTeamColor2: string, partnerTeamColor1: string, partnerTeamColor2: string, userPlayerContracts: array<int, list<int>>, partnerPlayerContracts: array<int, list<int>>}
     */
    public function getTradeOfferPageData(string $username, string $partnerTeam): array
    {
        /** @var \mysqli $mysqliDb */
        $mysqliDb = $this->mysqli_db;
        $season = new \Season($mysqliDb);

        $userTeam = $this->commonRepository->getTeamnameFromUsername($username) ?? '';

        $userTeamData = $this->commonRepository->getTeamByName($userTeam);
        $partnerTeamData = $this->commonRepository->getTeamByName($partnerTeam);

        $userTeamId = $userTeamData !== null ? $userTeamData['teamid'] : 0;
        $partnerTeamId = $partnerTeamData !== null ? $partnerTeamData['teamid'] : 0;

        $userPlayers = $this->repository->getTeamPlayersForTrading($userTeamId);
        $userPicks = $this->repository->getTeamDraftPicksForTrading($userTeamId);
        $userCashRecords = $this->cashRepository->getTeamCashRecordsForSalary($userTeamId);
        $userFutureSalary = $this->calculateFutureSalaries([...$userPlayers, ...$userCashRecords], $season);

        $partnerPlayers = $this->repository->getTeamPlayersForTrading($partnerTeamId);
        $partnerPicks = $this->repository->getTeamDraftPicksForTrading($partnerTeamId);
        $partnerCashRecords = $this->cashRepository->getTeamCashRecordsForSalary($partnerTeamId);
        $partnerFutureSalary = $this->calculateFutureSalaries([...$partnerPlayers, ...$partnerCashRecords], $season);

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
            'userTeamColor1' => $userTeamData !== null ? $userTeamData['color1'] : '000000',
            'userTeamColor2' => $userTeamData !== null ? $userTeamData['color2'] : 'ffffff',
            'partnerTeamColor1' => $partnerTeamData !== null ? $partnerTeamData['color1'] : '000000',
            'partnerTeamColor2' => $partnerTeamData !== null ? $partnerTeamData['color2'] : 'ffffff',
            'userPlayerContracts' => $this->buildContractsMap($userPlayers, $season->phase),
            'partnerPlayerContracts' => $this->buildContractsMap($partnerPlayers, $season->phase),
        ];
    }

    /**
     * @see TradingServiceInterface::getTradeReviewPageData()
     *
     * @return array{userTeam: string, userTeamId: int, tradeOffers: array<int, array{from: string, to: string, approval: string, oppositeTeam: string, hasHammer: bool, items: list<array{type: string, description: string, notes: string|null, from: string, to: string}>}>, teams: list<array{name: string, city: string, fullName: string, teamid: int, color1: string, color2: string}>}
     */
    public function getTradeReviewPageData(string $username): array
    {
        $userTeam = $this->commonRepository->getTeamnameFromUsername($username) ?? '';
        $userTeamId = $this->commonRepository->getTidFromTeamname($userTeam) ?? 0;

        /** @var \mysqli $mysqliDb */
        $mysqliDb = $this->mysqli_db;
        $season = new \Season($mysqliDb);

        $allTradeRows = $this->repository->getAllTradeOffers();
        $tradeOffers = $this->groupTradeOffers($allTradeRows, $userTeam, $season->endingYear);

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
     *
     * @param list<array<string, mixed>> $players Player rows from repository
     * @return array{player: array<int, int>, hold: array<int, int>}
     */
    public function calculateFutureSalaries(array $players, \Season $season): array
    {
        $futureSalary = [
            'player' => [0, 0, 0, 0, 0, 0],
            'hold' => [0, 0, 0, 0, 0, 0],
        ];

        foreach ($players as $playerRow) {
            $contractYearRaw = $playerRow['cy'] ?? 0;
            $contractYear = is_int($contractYearRaw) ? $contractYearRaw : 0;

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
                $cyRawValue = $playerRow["cy{$cy}"] ?? 0;
                $cyValue = is_int($cyRawValue) ? $cyRawValue : 0;
                $futureSalary['player'][$i] += $cyValue;
                if ($cyValue > 0) {
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
     * @param list<TradeInfoRow> $allTradeRows Raw trade info rows
     * @param string $userTeam Current user's team name
     * @param int $seasonEndingYear Season ending year for cash season labels
     * @return array<int, array{from: string, to: string, approval: string, oppositeTeam: string, hasHammer: bool, items: list<array{type: string, description: string, notes: string|null, from: string, to: string}>}> Grouped trade offers with resolved item descriptions
     */
    private function groupTradeOffers(array $allTradeRows, string $userTeam, int $seasonEndingYear): array
    {
        $tradeOffers = [];

        foreach ($allTradeRows as $row) {
            $offerId = $row['tradeofferid'];
            $from = $row['from'];
            $to = $row['to'];
            $approval = $row['approval'];
            $itemId = $row['itemid'];
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

            if ($itemType === 'cash') {
                $cashItems = $this->resolveCashItems($from, $to, $offerId, $seasonEndingYear);
                array_push($tradeOffers[$offerId]['items'], ...$cashItems);
            } else {
                $tradeOffers[$offerId]['items'][] = $this->resolveTradeItem(
                    $itemId,
                    $itemType,
                    $from,
                    $to
                );
            }
        }

        return $tradeOffers;
    }

    /**
     * Resolve a non-cash trade item into a displayable description
     *
     * @param int $itemId Item ID
     * @param string $itemType Item type ('0' for pick, '1' for player)
     * @param string $from Sending team
     * @param string $to Receiving team
     * @return array{type: string, description: string, notes: string|null, from: string, to: string}
     */
    private function resolveTradeItem(int $itemId, string $itemType, string $from, string $to): array
    {
        if ($itemType === '0') {
            return $this->resolvePickItem($itemId, $from, $to);
        }

        // itemtype === '1' (player)
        return $this->resolvePlayerItem($itemId, $from, $to);
    }

    /**
     * Resolve a cash trade item into per-year line items with season labels
     *
     * @return list<array{type: string, description: string, notes: string|null, from: string, to: string}>
     */
    private function resolveCashItems(string $from, string $to, int $offerId, int $seasonEndingYear): array
    {
        $cashDetails = $this->cashRepository->getCashTransactionByOffer($offerId, $from);
        $items = [];

        if ($cashDetails !== null) {
            for ($y = 1; $y <= 6; $y++) {
                $cyKey = "cy{$y}";
                $amount = $cashDetails[$cyKey];
                if ($amount === null || $amount <= 0) {
                    continue;
                }

                $startYear = $seasonEndingYear - 2 + $y;
                $endYear = $seasonEndingYear - 1 + $y;
                $yearLabel = "{$startYear}-{$endYear}";

                $items[] = [
                    'type' => 'cash',
                    'description' => "The {$from} send {$amount} in cash to the {$to} for {$yearLabel}.",
                    'notes' => null,
                    'from' => $from,
                    'to' => $to,
                ];
            }
        }

        return $items;
    }

    /**
     * Resolve a draft pick trade item
     *
     * @return array{type: string, description: string, notes: string|null, from: string, to: string}
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
     *
     * @return array{type: string, description: string, notes: string|null, from: string, to: string}
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
     * Build the Discord DM message sent to the proposing GM when a trade is declined.
     *
     * @param string $decliningGmDiscordId Discord user ID of the GM who declined
     * @param string $decliningTeamName Team name of the GM who declined (used in counter-offer link)
     */
    public static function buildDeclineMessage(string $decliningGmDiscordId, string $decliningTeamName): string
    {
        $counterOfferUrl = 'http://www.iblhoops.net/ibl5/modules.php?name=Trading&op=offertrade&partner='
            . rawurlencode($decliningTeamName);

        return 'Sorry, trade proposal declined by <@!' . $decliningGmDiscordId . '>.'
            . "\n" . '[Click/tap to counter-offer](' . $counterOfferUrl . ')';
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
     * Build PID-keyed contract salary arrays for JavaScript cap totals
     *
     * Each PID maps to a 6-element array of annual salaries aligned to display years
     * (index 0 = current/next year, index 5 = year 6). Contract year offset adjusts
     * for offseason phase.
     *
     * @param list<TradingPlayerRow> $players Player rows from repository
     * @return array<int, list<int>> PID-keyed salary arrays (6 years each)
     */
    public function buildContractsMap(array $players, string $seasonPhase): array
    {
        $isOffseason = $this->isOffseasonPhase($seasonPhase);
        $map = [];

        foreach ($players as $row) {
            $pid = $row['pid'];
            $contractYear = $row['cy'] ?? 0;

            if ($isOffseason) {
                $contractYear++;
            }
            if ($contractYear === 0) {
                $contractYear = 1;
            }

            $salaries = [0, 0, 0, 0, 0, 0];
            $i = 0;
            $cy = $contractYear;
            while ($cy < 7 && $i < 6) {
                $rawValue = $row["cy{$cy}"] ?? 0;
                $salaries[$i] = is_int($rawValue) ? $rawValue : 0;
                $cy++;
                $i++;
            }

            $map[$pid] = $salaries;
        }

        return $map;
    }

    /**
     * Build filtered team list for team selection UI (excludes Free Agents)
     *
     * @param list<TeamWithCityRow> $allTeams Raw team rows from repository
     * @return list<array{name: string, city: string, fullName: string, teamid: int, color1: string, color2: string}>
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
                'teamid' => $row['teamid'],
                'color1' => $row['color1'],
                'color2' => $row['color2'],
            ];
        }

        return $teams;
    }
}
