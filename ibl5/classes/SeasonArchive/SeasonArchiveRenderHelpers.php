<?php

declare(strict_types=1);

namespace SeasonArchive;

use Player\PlayerImageHelper;
use Security\HtmlSanitizer;
use UI\TeamCellHelper;

trait SeasonArchiveRenderHelpers
{
    /**
     * Escape a string value for safe HTML output
     *
     * Wraps HtmlSanitizer::safeHtmlOutput() with a string return type cast
     * to satisfy PHPStan strict type checking in string concatenation contexts.
     */
    private static function esc(string $value): string
    {
        /** @var string */
        return HtmlSanitizer::safeHtmlOutput($value);
    }

    /**
     * Render a player name with optional photo thumbnail and link
     *
     * @param array<string, int> $playerIds Map of player name => pid
     */
    private static function renderPlayerName(string $name, array $playerIds): string
    {
        if ($name === '') {
            return '';
        }

        $pid = $playerIds[$name] ?? null;
        if ($pid !== null) {
            return '<span class="ibl-player-cell">'
                . PlayerImageHelper::renderPlayerLink($pid, $name)
                . '</span>';
        }

        return self::esc($name);
    }

    /**
     * Render a team cell with colored background, logo, and link
     *
     * @param array<string, array{color1: string, color2: string, teamid: int}> $teamColors
     */
    private static function renderTeamCell(string $teamName, array $teamColors, int $year): string
    {
        $colors = $teamColors[$teamName] ?? null;
        if ($colors !== null) {
            $teamid = $colors['teamid'];
            $yearUrl = TeamCellHelper::teamPageUrl($teamid, $year);
            return TeamCellHelper::renderTeamCell($teamid, $teamName, $colors['color1'], $colors['color2'], '', $yearUrl);
        }

        return '<td>' . self::esc($teamName) . '</td>';
    }

    /**
     * Styles are now centralized in design/components/season-archive.css.
     */
    private function renderStyles(): string
    {
        return '';
    }
}
