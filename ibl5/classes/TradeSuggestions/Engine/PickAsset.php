<?php

declare(strict_types=1);

namespace TradeSuggestions\Engine;

/**
 * One tradeable draft pick (rounds 1-2), from
 * `SuggestionAssetRepositoryInterface::getPicksForTeams`.
 */
final readonly class PickAsset
{
    public function __construct(
        public int $pickId,
        public int $ownerTid,
        public int $originalTid,
        public int $year,
        public int $round,
        public int $yearsAway,
    ) {
    }
}
