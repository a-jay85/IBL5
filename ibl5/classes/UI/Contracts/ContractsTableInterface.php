<?php

declare(strict_types=1);

namespace UI\Contracts;

/**
 * ContractsTableInterface - Contract for displaying team contracts table
 */
interface ContractsTableInterface
{
    /**
     * Render the contracts table
     *
     * @param \mysqli $db Database connection
     * @param iterable<int, array<string, mixed>> $result Player result set
     * @param \Team\Team $team Team object
     * @param \Season\Season $season Season object
     * @param list<int> $starterPids Starter player IDs
     * @param list<int> $excludeFromCapPids PIDs to exclude from cap total sums (e.g. outgoing trade players)
     * @param bool $showActionLinks When false, Rookie Option / Contract Extension eligibility markers still render but are not clickable links (used when previewing another GM's roster in the Trading module)
     * @return string HTML table
     */
    public static function render(\mysqli $db, iterable $result, \Team\Team $team, \Season\Season $season, array $starterPids = [], array $excludeFromCapPids = [], bool $showActionLinks = true): string;
}
