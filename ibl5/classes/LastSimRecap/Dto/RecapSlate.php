<?php

declare(strict_types=1);

namespace LastSimRecap\Dto;

final class RecapSlate
{
    /**
     * @param list<RecapGame> $games
     * @param string $bestLabel e.g. "+11 vs CLE"
     * @param string $worstLabel e.g. "−3 @ DET"
     */
    public function __construct(
        public readonly int $teamTid,
        public readonly string $teamCity,
        public readonly string $teamName,
        public readonly int $simNumber,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly int $wins,
        public readonly int $losses,
        public readonly int $netMargin,
        public readonly string $bestLabel,
        public readonly string $worstLabel,
        public readonly int $teamWins,
        public readonly int $teamLosses,
        public readonly array $games,
    ) {}
}
