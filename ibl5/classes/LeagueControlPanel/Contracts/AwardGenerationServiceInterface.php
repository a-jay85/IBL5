<?php

declare(strict_types=1);

namespace LeagueControlPanel\Contracts;

/**
 * Interface for generating season awards from votes and JSB Leaders.htm data.
 */
interface AwardGenerationServiceInterface
{
    /**
     * Generate all non-event season awards for a given year.
     *
     * Combines end-of-year vote results with JSB Leaders.htm data to produce
     * individual awards, stat leaders, and team selections.
     *
     * @param int $year Season ending year
     * @param string $leadersHtmPath Path to the Leaders.htm file
     * @return array{success: bool, message: string, inserted: int, skipped: int}
     */
    public function generateSeasonAwards(int $year, string $leadersHtmPath): array;
}
