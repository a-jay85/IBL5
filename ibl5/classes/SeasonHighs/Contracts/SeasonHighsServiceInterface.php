<?php

declare(strict_types=1);

namespace SeasonHighs\Contracts;

/**
 * Service interface for Season Highs module.
 *
 * Provides business logic for retrieving season high stats.
 */
interface SeasonHighsServiceInterface
{
    /**
     * Get all season highs data for players and teams.
     *
     * @param string $seasonPhase Season phase ('Regular Season', 'Playoffs', 'Preseason', 'HEAT')
     * @return array{
     *     playerHighs: array<string, array<int, array{name: string, date: string, value: int}>>,
     *     teamHighs: array<string, array<int, array{name: string, date: string, value: int>>>
     * } Array containing player and team season highs
     */
    public function getSeasonHighsData(string $seasonPhase): array;
}
