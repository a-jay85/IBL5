<?php

declare(strict_types=1);

namespace DraftPickLocator\Contracts;

/**
 * DraftPickLocatorViewInterface - Contract for draft pick locator view rendering
 *
 * Defines methods for generating HTML output for draft pick matrix.
 *
 * @phpstan-import-type DraftPickRow from DraftPickLocatorRepositoryInterface
 * @phpstan-type TeamWithPicks array{teamId: int, teamCity: string, teamName: string, color1: string, color2: string, picks: list<array{ownerofpick: string, year: int, round: int}>}
 *
 * @see \DraftPickLocator\DraftPickLocatorView For the concrete implementation
 */
interface DraftPickLocatorViewInterface
{
    /**
     * Render the complete draft pick matrix
     *
     * @param list<array{teamId: int, teamCity: string, teamName: string, color1: string, color2: string, picks: list<array{ownerofpick: string, year: int, round: int}>}> $teamsWithPicks Teams with their draft pick data
     * @param int $currentEndingYear Current season ending year
     * @return string HTML output
     */
    public function render(array $teamsWithPicks, int $currentEndingYear): string;
}
