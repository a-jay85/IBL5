<?php

declare(strict_types=1);

namespace Tests\SeasonHighs;

use SeasonHighs\Contracts\SeasonHighsRepositoryInterface;

/**
 * Inner-repository double that counts getSeasonHighsBatch() calls and returns a settable result.
 *
 * @phpstan-import-type SeasonHighEntry from \SeasonHighs\Contracts\SeasonHighsServiceInterface
 */
final class CountingSeasonHighsInner implements SeasonHighsRepositoryInterface
{
    public int $calls = 0;

    /**
     * @param array<string, list<SeasonHighEntry>> $result
     */
    public function __construct(public array $result)
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

        return $this->result;
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
