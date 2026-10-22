<?php

declare(strict_types=1);

namespace TradeSuggestions\Contracts;

use TradeSuggestions\SuggestionPlayer;

/**
 * Batched public-data reads for the trade suggestion engine.
 *
 * @phpstan-import-type MarketMaximums from \TradeSuggestions\PlayerRating
 * @phpstan-type SuggestionPickRow array{pickid: int, owner_teamid: int, teampick_teamid: int, year: int, round: int}
 */
interface SuggestionAssetRepositoryInterface
{
    /**
     * Active (non-retired, rostered) players for each team, in one query.
     *
     * @param list<int> $tids
     * @param MarketMaximums $maximums League rating maximums for PlayerRating::overall()
     * @param bool $advancesContractYears True in the offseason phases: contracts are read one year ahead
     * @return array<int, list<SuggestionPlayer>> Keyed by teamid; an empty $tids returns []
     */
    public function getRostersForTeams(array $tids, array $maximums, bool $advancesContractYears): array;

    /**
     * Regular-season wins and losses per team for one season ending year.
     *
     * @return array<int, array{wins: int, losses: int}> Keyed by teamid
     */
    public function getRegularSeasonRecords(int $year): array;

    /**
     * Round 1-2 draft picks from $fromYear onward, owned by each team, in one query.
     *
     * @param list<int> $tids
     * @return array<int, list<SuggestionPickRow>> Keyed by owner_teamid; an empty $tids returns []
     */
    public function getPicksForTeams(array $tids, int $fromYear): array;
}
