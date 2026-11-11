<?php

declare(strict_types=1);

namespace TradeSuggestions\Projection\Contracts;

use TradeSuggestions\Projection\ProjectionPlayer;
use TradeSuggestions\Projection\RatingScale;

/**
 * Read-only loader for the v3 projection inputs.
 */
interface ProjectionPlayerRepositoryInterface
{
    /**
     * Every rostered, non-retired player (the same player set v2's
     * SuggestionAssetRepository reads), grouped by team id.
     *
     * @param bool $advancesContractYears True during the offseason phases, when the effective contract year is cy + 1
     * @param int $lastSeasonYear The ibl_hist year used when the current line is under the minutes sample
     * @return array<int, list<ProjectionPlayer>> Keyed by tid
     */
    public function getLeaguePlayers(bool $advancesContractYears, int $lastSeasonYear): array;

    /**
     * The observed league maximum per rating key, used to clamp projections.
     */
    public function getRatingScale(): RatingScale;
}
