<?php

declare(strict_types=1);

namespace SeasonRosterChanges\Contracts;

/**
 * SeasonRosterChangesRepositoryInterface - Contract for player movement database operations
 *
 * @phpstan-type MovementRow array{
 *     pid: int,
 *     name: string,
 *     old_teamid: int,
 *     old_team: string,
 *     new_teamid: int,
 *     new_team: string,
 *     old_city: ?string,
 *     old_color1: ?string,
 *     old_color2: ?string,
 *     new_city: ?string,
 *     new_color1: ?string,
 *     new_color2: ?string
 * }
 *
 * @see \SeasonRosterChanges\SeasonRosterChangesRepository For the concrete implementation
 */
interface SeasonRosterChangesRepositoryInterface
{
    /**
     * Get player movements since the previous season
     *
     * @param int $previousSeasonYear The ending year of the previous season
     * @return list<MovementRow> Players who changed teams, ordered by new team name
     */
    public function getSeasonRosterChanges(int $previousSeasonYear): array;
}
