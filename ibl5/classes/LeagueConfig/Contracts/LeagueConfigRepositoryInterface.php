<?php

declare(strict_types=1);

namespace LeagueConfig\Contracts;

/**
 * @phpstan-type LeagueConfigRow array{
 *     id: int,
 *     season_ending_year: int,
 *     team_slot: int,
 *     team_name: string,
 *     conference: string,
 *     division: string,
 *     playoff_qualifiers_per_conf: int,
 *     playoff_round1_format: string,
 *     playoff_round2_format: string,
 *     playoff_round3_format: string,
 *     playoff_round4_format: string,
 *     team_count: int,
 *     created_at: string
 * }
 *
 * @see \LeagueConfig\LeagueConfigRepository For the concrete implementation
 */
interface LeagueConfigRepositoryInterface
{
    /**
     * Check if league config data exists for a given season.
     */
    public function hasConfigForSeason(int $seasonEndingYear): bool;

    /**
     * Upsert team rows for a season. Uses INSERT ... ON DUPLICATE KEY UPDATE
     * to allow idempotent re-imports.
     *
     * @param list<array{
     *     team_slot: int,
     *     team_name: string,
     *     conference: string,
     *     division: string,
     *     playoff_qualifiers_per_conf: int,
     *     playoff_round1_format: string,
     *     playoff_round2_format: string,
     *     playoff_round3_format: string,
     *     playoff_round4_format: string,
     *     team_count: int
     * }> $rows
     *
     * @return int Number of rows affected
     */
    public function upsertSeasonConfig(int $seasonEndingYear, array $rows): int;

    /**
     * Retrieve all config rows for a season.
     *
     * @return list<LeagueConfigRow>
     */
    public function getConfigForSeason(int $seasonEndingYear): array;
}
