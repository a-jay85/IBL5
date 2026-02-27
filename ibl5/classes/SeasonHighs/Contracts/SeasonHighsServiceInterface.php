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
 * @phpstan-type RcbSeasonHighEntry array{stat_category: string, ranking: int, player_name: string, player_position: string|null, stat_value: int, record_season_year: int}
 *
 * @phpstan-type RcbDiscrepancy array{context: string, stat: string, boxValue: int, boxPlayer: string, rcbValue: int, rcbPlayer: string}
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

    /**
     * Get home/away single-game records from box scores.
     *
     * @param string $seasonPhase Season phase for date range calculation
     * @return array{home: array<string, list<SeasonHighEntry>>, away: array<string, list<SeasonHighEntry>>}
     */
    public function getHomeAwayHighs(string $seasonPhase): array;

    /**
     * Validate box score home/away data against RCB records.
     *
     * @param array{home: array<string, list<SeasonHighEntry>>, away: array<string, list<SeasonHighEntry>>} $homeAwayData
     * @param int $seasonYear Beginning year of the season
     * @return list<RcbDiscrepancy>
     */
    public function validateAgainstRcb(array $homeAwayData, int $seasonYear): array;
}
