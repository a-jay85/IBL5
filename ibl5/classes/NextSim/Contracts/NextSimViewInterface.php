<?php

declare(strict_types=1);

namespace NextSim\Contracts;

/**
 * NextSimViewInterface - Contract for next sim view rendering
 *
 * Defines methods for generating HTML output for next sim games.
 *
 * @phpstan-import-type NextSimGameData from \NextSim\Contracts\NextSimServiceInterface
 *
 * @see \NextSim\NextSimView For the concrete implementation
 */
interface NextSimViewInterface
{
    /**
     * Render the complete next sim display
     *
     * @param array<int, NextSimGameData> $games Processed game data
     * @param int $simLengthInDays Simulation length in days
     * @return string HTML output
     */
    public function render(array $games, int $simLengthInDays): string;
}
