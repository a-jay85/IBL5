<?php

declare(strict_types=1);

namespace Trading;

use League\League;
use Trading\Contracts\TradingServiceInterface;
use Trading\Contracts\TradingRepositoryInterface;
use Trading\Contracts\TradeCashRepositoryInterface;
use Season\Season;

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
     * @return array{userTeam: string, userTeamId: int, partnerTeam: string, partnerTeamId: int, userPlayers: list<TradingPlayerRow>, userPicks: list<TradingDraftPickRow>, userFutureSalary: array{player: array<int, int>, hold: array<int, int>}, partnerPlayers: list<TradingPlayerRow>, partnerPicks: list<TradingDraftPickRow>, partnerFutureSalary: array{player: array<int, int>, hold: array<int, int>}, seasonEndingYear: int, seasonPhase: string, cashStartYear: int, cashEndYear: int, userTeamColor1: string, userTeamColor2: string, partnerTeamColor1: string, partnerTeamColor2: string}
     */
    public function getTradeOfferPageData(string $username, string $partnerTeam): array
    {
        /** @var \mysqli $mysqliDb */
        $mysqliDb = $this->mysqli_db;
        $season = new Season($mysqliDb);

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
        ];
    }

    /**
     * @see TradingServiceInterface::getTradeReviewPageData()
     *
     * @return array{userTeam: string, userTeamId: int, tradeOffers: array<int, array{from: string, to: string, approval: string, oppositeTeam: string, hasHammer: bool, items: list<array{type: string, description: string, notes: string|null, from: string, to: string}>, previewData: array{fromPids: list<int>, toPids: list<int>, fromTeamId: int, toTeamId: int, fromColor1: string, toColor1: string, fromCash: array<int, int>, toCash: array<int, int>, cashStartYear: int, cashEndYear: int, seasonEndingYear: int}}>, teams: list<array{name: string, city: string, fullName: string, teamid: int, color1: string, color2: string}>}
     */
    public function getTradeReviewPageData(string $username): array
    {
        $userTeam = $this->commonRepository->getTeamnameFromUsername($username) ?? '';
        $userTeamId = $this->commonRepository->getTidFromTeamname($userTeam) ?? 0;

        /** @var \mysqli $mysqliDb */
        $mysqliDb = $this->mysqli_db;
        $season = new Season($mysqliDb);

        $allTradeRows = $this->repository->getAllTradeOffers();
        $tradeOffers = $this->groupTradeOffers($allTradeRows, $userTeam, $season->endingYear);

        // Get teams for the team selection sidebar
        $allTeams = $this->repository->getAllTeamsWithCity();
        $teams = $this->buildTeamList($allTeams);

        // Enrich offers with preview data (team IDs, colors, cash)
        $tradeOffers = $this->enrichOffersWithPreviewData($tradeOffers, $allTeams, $season);

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
    public function calculateFutureSalaries(array $players, Season $season): array
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
     * @return array<int, array{from: string, to: string, approval: string, oppositeTeam: string, hasHammer: bool, items: list<array{type: string, description: string, notes: string|null, from: string, to: string}>, previewData: array{fromPids: list<int>, toPids: list<int>}}> Grouped trade offers with resolved item descriptions
     */
    private function groupTradeOffers(array $allTradeRows, string $userTeam, int $seasonEndingYear): array
    {
        // Pre-load all players, picks, and cash in batch to avoid N+1 queries
        $playerIds = [];
        $pickIds = [];
        $offerIds = [];
        foreach ($allTradeRows as $row) {
            $from = $row['trade_from'];
            $to = $row['trade_to'];
            if ($from !== $userTeam && $to !== $userTeam) {
                continue;
            }
            if ($row['itemtype'] === TradeItemType::Player->value) {
                $playerIds[] = $row['itemid'];
            } elseif ($row['itemtype'] === TradeItemType::DraftPick->value) {
                $pickIds[] = $row['itemid'];
            } elseif ($row['itemtype'] === TradeItemType::Cash->value) {
                $offerIds[] = $row['tradeofferid'];
            }
        }
        $playersMap = $this->repository->getPlayersByIds(array_values(array_unique($playerIds)));
        $picksMap = $this->repository->getDraftPicksByIds(array_values(array_unique($pickIds)));
        $cashMap = $this->cashRepository->getCashTransactionsByOfferIds(array_values(array_unique($offerIds)));

        $tradeOffers = [];

        foreach ($allTradeRows as $row) {
            $offerId = $row['tradeofferid'];
            $from = $row['trade_from'];
            $to = $row['trade_to'];
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
                    'previewData' => ['fromPids' => [], 'toPids' => []],
                ];
            }

            if ($itemType === TradeItemType::Cash->value) {
                $cashItems = $this->resolveCashItemsFromMap($from, $to, $offerId, $seasonEndingYear, $cashMap);
                array_push($tradeOffers[$offerId]['items'], ...$cashItems);
            } else {
                $tradeOffers[$offerId]['items'][] = $this->resolveTradeItemFromMaps(
                    $itemId,
                    $itemType,
                    $from,
                    $to,
                    $playersMap,
                    $picksMap
                );

                // Collect player PIDs for roster preview (classify by sending team)
                if ($itemType === TradeItemType::Player->value) {
                    if ($from === $tradeOffers[$offerId]['from']) {
                        $tradeOffers[$offerId]['previewData']['fromPids'][] = $itemId;
                    } else {
                        $tradeOffers[$offerId]['previewData']['toPids'][] = $itemId;
                    }
                }
            }
        }

        return $tradeOffers;
    }

    /**
     * Resolve a non-cash trade item from pre-loaded maps
     *
     * @param array<int, PlayerRow> $playersMap
     * @param array<int, DraftPickRow> $picksMap
     * @return array{type: string, description: string, notes: string|null, from: string, to: string}
     */
    private function resolveTradeItemFromMaps(int $itemId, string $itemType, string $from, string $to, array $playersMap, array $picksMap): array
    {
        if ($itemType === TradeItemType::DraftPick->value) {
            $pick = $picksMap[$itemId] ?? null;
            $description = '';
            $notes = null;

            if ($pick !== null) {
                $notes = $pick['notes'] ?? null;
                if ($notes === '') {
                    $notes = null;
                }
                $description = "The {$from} send the {$pick['teampick']} {$pick['year']} Round {$pick['round']} draft pick to the {$to}.";
            }

            return ['type' => 'pick', 'description' => $description, 'notes' => $notes, 'from' => $from, 'to' => $to];
        }

        // itemtype === Player
        $player = $playersMap[$itemId] ?? null;
        $description = '';

        if ($player !== null) {
            $description = "The {$from} send {$player['pos']} {$player['name']} to the {$to}.";
        }

        return ['type' => 'player', 'description' => $description, 'notes' => null, 'from' => $from, 'to' => $to];
    }

    /**
     * Resolve cash items from a pre-loaded cash map
     *
     * @param array<string, TradeCashRow> $cashMap Keyed by "{offerId}:{sendingTeam}"
     * @return list<array{type: string, description: string, notes: string|null, from: string, to: string}>
     */
    private function resolveCashItemsFromMap(string $from, string $to, int $offerId, int $seasonEndingYear, array $cashMap): array
    {
        $cashDetails = $cashMap[$offerId . ':' . $from] ?? null;
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
     * Enrich trade offers with preview data (team IDs, colors, cash amounts)
     *
     * @param array<int, array{from: string, to: string, approval: string, oppositeTeam: string, hasHammer: bool, items: list<array{type: string, description: string, notes: string|null, from: string, to: string}>, previewData: array{fromPids: list<int>, toPids: list<int>}}> $tradeOffers
     * @param list<TeamWithCityRow> $allTeams
     * @return array<int, array{from: string, to: string, approval: string, oppositeTeam: string, hasHammer: bool, items: list<array{type: string, description: string, notes: string|null, from: string, to: string}>, previewData: array{fromPids: list<int>, toPids: list<int>, fromTeamId: int, toTeamId: int, fromColor1: string, toColor1: string, fromCash: array<int, int>, toCash: array<int, int>, cashStartYear: int, cashEndYear: int, seasonEndingYear: int}}>
     */
    private function enrichOffersWithPreviewData(array $tradeOffers, array $allTeams, Season $season): array
    {
        // Build team lookup map: team_name => {teamid, color1}
        $teamLookup = [];
        foreach ($allTeams as $row) {
            $teamLookup[$row['team_name']] = [
                'teamid' => $row['teamid'],
                'color1' => $row['color1'],
            ];
        }

        $cashStartYear = 1;
        if ($this->isOffseasonPhase($season->phase)) {
            $cashStartYear = 2;
        }

        // Batch-load all cash transactions for preview data
        $offerIds = array_values(array_unique(array_map(
            static fn (int $id): int => $id,
            array_keys($tradeOffers)
        )));
        $cashMap = $this->cashRepository->getCashTransactionsByOfferIds($offerIds);

        /** @var array<int, array{from: string, to: string, approval: string, oppositeTeam: string, hasHammer: bool, items: list<array{type: string, description: string, notes: string|null, from: string, to: string}>, previewData: array{fromPids: list<int>, toPids: list<int>, fromTeamId: int, toTeamId: int, fromColor1: string, toColor1: string, fromCash: array<int, int>, toCash: array<int, int>, cashStartYear: int, cashEndYear: int, seasonEndingYear: int}}> $enriched */
        $enriched = [];

        foreach ($tradeOffers as $offerId => $offer) {
            $fromTeam = $offer['from'];
            $toTeam = $offer['to'];

            $fromTeamData = $teamLookup[$fromTeam] ?? ['teamid' => 0, 'color1' => '000000'];
            $toTeamData = $teamLookup[$toTeam] ?? ['teamid' => 0, 'color1' => '000000'];

            // Look up cash from pre-loaded batch map
            $fromCashRow = $cashMap[$offerId . ':' . $fromTeam] ?? null;
            $toCashRow = $cashMap[$offerId . ':' . $toTeam] ?? null;

            $fromCash = [];
            $toCash = [];
            for ($y = 1; $y <= 6; $y++) {
                $fromCash[$y] = ($fromCashRow !== null && $fromCashRow["cy{$y}"] !== null) ? $fromCashRow["cy{$y}"] : 0;
                $toCash[$y] = ($toCashRow !== null && $toCashRow["cy{$y}"] !== null) ? $toCashRow["cy{$y}"] : 0;
            }

            $offer['previewData'] = [
                'fromPids' => $offer['previewData']['fromPids'],
                'toPids' => $offer['previewData']['toPids'],
                'fromTeamId' => $fromTeamData['teamid'],
                'toTeamId' => $toTeamData['teamid'],
                'fromColor1' => $fromTeamData['color1'],
                'toColor1' => $toTeamData['color1'],
                'fromCash' => $fromCash,
                'toCash' => $toCash,
                'cashStartYear' => $cashStartYear,
                'cashEndYear' => 6,
                'seasonEndingYear' => $season->endingYear,
            ];

            $enriched[$offerId] = $offer;
        }

        return $enriched;
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
            if ($teamName === League::FREE_AGENTS_TEAM_NAME) {
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
