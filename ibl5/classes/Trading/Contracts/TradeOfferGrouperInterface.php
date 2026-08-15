<?php

declare(strict_types=1);

namespace Trading\Contracts;

use Season\Season;

/**
 * TradeOfferGrouperInterface - Contract for grouping and enriching trade offers
 *
 * @phpstan-import-type TradeInfoRow from \Trading\Contracts\TradeOfferRepositoryInterface
 * @phpstan-import-type TeamWithCityRow from \Trading\Contracts\TradeFormRepositoryInterface
 */
interface TradeOfferGrouperInterface
{
    /**
     * Group trade offer rows by offer ID and resolve item details
     *
     * @param list<TradeInfoRow> $allTradeRows Raw trade info rows
     * @param string $userTeam Current user's team name
     * @param int $seasonEndingYear Season ending year for cash season labels
     * @return array<int, array{from: string, to: string, approval: string, oppositeTeam: string, hasHammer: bool, items: list<array{type: string, description: string, notes: string|null, from: string, to: string}>, previewData: array{fromPids: list<int>, toPids: list<int>}}> Grouped trade offers with resolved item descriptions
     */
    public function groupOffers(array $allTradeRows, string $userTeam, int $seasonEndingYear): array;

    /**
     * Enrich trade offers with preview data (team IDs, colors, cash amounts)
     *
     * @param array<int, array{from: string, to: string, approval: string, oppositeTeam: string, hasHammer: bool, items: list<array{type: string, description: string, notes: string|null, from: string, to: string}>, previewData: array{fromPids: list<int>, toPids: list<int>}}> $tradeOffers
     * @param list<TeamWithCityRow> $allTeams
     * @return array<int, array{from: string, to: string, approval: string, oppositeTeam: string, hasHammer: bool, items: list<array{type: string, description: string, notes: string|null, from: string, to: string}>, previewData: array{fromPids: list<int>, toPids: list<int>, fromTeamId: int, toTeamId: int, fromColor1: string, toColor1: string, fromCash: array<int, int>, toCash: array<int, int>, cashStartYear: int, cashEndYear: int, seasonEndingYear: int}}>
     */
    public function enrichWithPreviewData(array $tradeOffers, array $allTeams, Season $season): array;
}
