<?php

declare(strict_types=1);

namespace SeasonHighs\Contracts;

/**
 * Service interface for Season Highs module.
 *
 * Provides business logic for retrieving season high stats.
 *
 * @phpstan-type SeasonHighEntry array{
 *     name: string,
 *     date: string,
 *     value: int,
 *     pid?: int,
 *     tid?: int,
 *     teamname?: string,
 *     team_city?: string,
 *     color1?: string,
 *     color2?: string,
 *     teamid?: int,
 *     boxId?: int,
 *     gameOfThatDay?: int
 * }
 *
 * @phpstan-type SeasonHighsData array{
 *     playerHighs: array<string, list<SeasonHighEntry>>,
 *     teamHighs: array<string, list<SeasonHighEntry>>
 * }
 */
interface SeasonHighsServiceInterface
{
    /**
     * Get all season highs data for players and teams.
     *
     * @param string $seasonPhase Season phase ('Regular Season', 'Playoffs', 'Preseason', 'HEAT')
     * @return SeasonHighsData
     */
    public function getSeasonHighsData(string $seasonPhase): array;
}
