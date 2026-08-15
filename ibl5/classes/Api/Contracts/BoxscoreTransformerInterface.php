<?php

declare(strict_types=1);

namespace Api\Contracts;

use Api\Repository\ApiGameRepository;

/**
 * @phpstan-import-type BoxscoreTeamRow from ApiGameRepository
 * @phpstan-import-type BoxscorePlayerRow from ApiGameRepository
 */
interface BoxscoreTransformerInterface
{
    /**
     * @param BoxscoreTeamRow $row
     * @return array<string, mixed>
     */
    public function transformTeamStats(array $row): array;

    /**
     * @param BoxscorePlayerRow $row
     * @return array<string, mixed>
     */
    public function transformPlayerLine(array $row): array;
}
