<?php

declare(strict_types=1);

namespace Api\Contracts;

use Api\Repository\ApiPlayerStatsRepository;

/**
 * @phpstan-import-type CareerStatsRow from ApiPlayerStatsRepository
 * @phpstan-import-type SeasonHistoryRow from ApiPlayerStatsRepository
 */
interface PlayerStatsTransformerInterface
{
    /**
     * @param CareerStatsRow $row
     * @return array<string, mixed>
     */
    public function transformCareer(array $row): array;

    /**
     * @param SeasonHistoryRow $row
     * @return array<string, mixed>
     */
    public function transformSeason(array $row): array;
}
