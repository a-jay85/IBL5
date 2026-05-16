<?php

declare(strict_types=1);

namespace UI\Contracts;

/**
 * RatingsInterface - Contract for displaying player ratings table
 */
interface RatingsInterface
{
    /**
     * Render the ratings table
     *
     * @param \mysqli $db Database connection
     * @param iterable<int, \Player\Player|array<string, mixed>> $data Player data
     * @param \Team\Team $team Team object
     * @param string $yr Year filter (empty for current season)
     * @param \Season\Season $season Season object
     * @param string $moduleName Module name for styling variations
     * @param list<int> $starterPids Starter player IDs
     * @return string HTML table
     */
    public static function render($db, $data, $team, string $yr, $season, string $moduleName = "", array $starterPids = []): string;
}
