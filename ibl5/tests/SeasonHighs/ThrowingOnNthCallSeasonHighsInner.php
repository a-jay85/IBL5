<?php

declare(strict_types=1);

namespace Tests\SeasonHighs;

use SeasonHighs\Contracts\SeasonHighsRepositoryInterface;

/**
 * Inner-repository double that returns an empty list per stat, then throws on the Nth getSeasonHighsBatch() call.
 *
 * @phpstan-import-type SeasonHighEntry from \SeasonHighs\Contracts\SeasonHighsServiceInterface
 */
final class ThrowingOnNthCallSeasonHighsInner implements SeasonHighsRepositoryInterface
{
    public int $calls = 0;

    public function __construct(private readonly int $throwOnCall)
    {
    }

    /**
     * @see SeasonHighsRepositoryInterface::getSeasonHighsBatch()
     *
     * @param array<string, string> $stats
     * @return array<string, list<SeasonHighEntry>>
     */
    public function getSeasonHighsBatch(
        array $stats,
        string $tableSuffix,
        string $startDate,
        string $endDate,
        int $limit = 15,
        ?string $locationFilter = null
    ): array {
        $this->calls++;
        if ($this->calls === $this->throwOnCall) {
            throw new \RuntimeException('inner failed on call ' . $this->calls);
        }

        return array_fill_keys(array_keys($stats), []);
    }

    /**
     * @see SeasonHighsRepositoryInterface::getRcbSeasonHighs()
     *
     * @return list<array{stat_category: string, ranking: int, player_name: string, player_position: string|null, stat_value: int, record_season_year: int}>
     */
    public function getRcbSeasonHighs(int $seasonYear, string $context): array
    {
        return [];
    }
}
