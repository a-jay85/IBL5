<?php

declare(strict_types=1);

namespace Trading;

use Season\Season;
use Trading\Contracts\TradeAssetRepositoryInterface;
use Trading\Contracts\TradeCashRepositoryInterface;
use Trading\Contracts\TradeOfferGrouperInterface;

/**
 * Groups and enriches trade offers for display.
 *
 * @see TradeOfferGrouperInterface
 *
 * @phpstan-import-type TradeInfoRow from \Trading\Contracts\TradeOfferRepositoryInterface
 * @phpstan-import-type TeamWithCityRow from \Trading\Contracts\TradeFormRepositoryInterface
 * @phpstan-import-type TradeCashRow from \Trading\Contracts\TradeCashRepositoryInterface
 * @phpstan-import-type DraftPickRow from \Trading\Contracts\TradeAssetRepositoryInterface
 * @phpstan-import-type PlayerRow from \Repositories\Contracts\PlayerLookupRepositoryInterface
 */
final class TradeOfferGrouper implements TradeOfferGrouperInterface
{
    private TradeAssetRepositoryInterface $assetRepository;
    private TradeCashRepositoryInterface $cashRepository;

    public function __construct(
        TradeAssetRepositoryInterface $assetRepository,
        TradeCashRepositoryInterface $cashRepository
    ) {
        $this->assetRepository = $assetRepository;
        $this->cashRepository = $cashRepository;
    }

    /**
     * Group trade offer rows by offer ID and resolve item details
     *
     * @param list<TradeInfoRow> $allTradeRows Raw trade info rows
     * @param string $userTeam Current user's team name
     * @param int $seasonEndingYear Season ending year for cash season labels
     * @return array<int, array{from: string, to: string, approval: string, oppositeTeam: string, hasHammer: bool, items: list<array{type: string, description: string, notes: string|null, from: string, to: string}>, previewData: array{fromPids: list<int>, toPids: list<int>}}> Grouped trade offers with resolved item descriptions
     */
    public function groupOffers(array $allTradeRows, string $userTeam, int $seasonEndingYear): array
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
        $playersMap = $this->assetRepository->getPlayersByIds(array_values(array_unique($playerIds)));
        $picksMap = $this->assetRepository->getDraftPicksByIds(array_values(array_unique($pickIds)));
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
     * Enrich trade offers with preview data (team IDs, colors, cash amounts)
     *
     * @param array<int, array{from: string, to: string, approval: string, oppositeTeam: string, hasHammer: bool, items: list<array{type: string, description: string, notes: string|null, from: string, to: string}>, previewData: array{fromPids: list<int>, toPids: list<int>}}> $tradeOffers
     * @param list<TeamWithCityRow> $allTeams
     * @return array<int, array{from: string, to: string, approval: string, oppositeTeam: string, hasHammer: bool, items: list<array{type: string, description: string, notes: string|null, from: string, to: string}>, previewData: array{fromPids: list<int>, toPids: list<int>, fromTeamId: int, toTeamId: int, fromColor1: string, toColor1: string, fromCash: array<int, int>, toCash: array<int, int>, cashStartYear: int, cashEndYear: int, seasonEndingYear: int}}>
     */
    public function enrichWithPreviewData(array $tradeOffers, array $allTeams, Season $season): array
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
        if ($season->advancesContractYears()) {
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
                $fromCash[$y] = ($fromCashRow !== null && $fromCashRow["salary_yr{$y}"] !== null) ? $fromCashRow["salary_yr{$y}"] : 0;
                $toCash[$y] = ($toCashRow !== null && $toCashRow["salary_yr{$y}"] !== null) ? $toCashRow["salary_yr{$y}"] : 0;
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
     * @param array<string, TradeCashRow> $cashMap Keyed by "{offerId}:{sending_team}"
     * @return list<array{type: string, description: string, notes: string|null, from: string, to: string}>
     */
    private function resolveCashItemsFromMap(string $from, string $to, int $offerId, int $seasonEndingYear, array $cashMap): array
    {
        $cashDetails = $cashMap[$offerId . ':' . $from] ?? null;
        $items = [];

        if ($cashDetails !== null) {
            for ($y = 1; $y <= 6; $y++) {
                $cyKey = "salary_yr{$y}";
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
}
