<?php

declare(strict_types=1);

namespace Boxscore\Contracts;

/**
 * BoxscoreViewInterface - Contract for scoParser HTML rendering
 *
 * @see \Boxscore\BoxscoreView For the concrete implementation
 */
interface BoxscoreViewInterface
{
    /**
     * Render parse results log
     *
     * @param array{success: bool, gamesInserted: int, gamesUpdated: int, gamesSkipped: int, linesProcessed: int, messages: list<string>, error?: string} $result
     * @return string HTML parse log
     */
    public function renderParseLog(array $result): string;

    /**
     * Render All-Star game processing results
     *
     * @param array{success: bool, messages: list<string>, skipped?: string} $result
     * @return string HTML output
     */
    public function renderAllStarLog(array $result): string;

    /**
     * Render the async rename UI for All-Star teams with default placeholder names
     *
     * @param list<array{id: int, date: string, name: string, seasonYear: int, teamLabel: string, players: list<string>}> $pendingRenames
     * @return string HTML output
     */
    public function renderAllStarRenameUI(array $pendingRenames): string;
}
