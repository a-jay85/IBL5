<?php

declare(strict_types=1);

namespace UI\Contracts;

/**
 * TeamCellHelperInterface - Contract for rendering team name table cells
 *
 * Provides a consistent way to render colored team cells with logo, link,
 * and team name across all View files. Eliminates 22+ copies of the same
 * ibl-team-cell--colored HTML pattern.
 */
interface TeamCellHelperInterface
{
    /**
     * Render a colored team cell with logo and link
     *
     * Returns a <td> element with team-colored background, logo image,
     * and linked team name. Colors are sanitized to prevent CSS injection.
     *
     * @param int $teamId Team ID (used for logo image and default link)
     * @param string $teamName Team name to display (will be HTML-escaped)
     * @param string $color1 Primary color (hex, without #) for background
     * @param string $color2 Secondary color (hex, without #) for text
     * @param string $extraClasses Additional CSS classes (e.g. 'sticky-col')
     * @param string $linkUrl Custom link URL; if empty, uses default team page URL
     * @param string $nameHtml Pre-escaped custom inner HTML; if empty, uses escaped team name
     * @return string Complete <td> HTML element
     */
    public static function renderTeamCell(
        int $teamId,
        string $teamName,
        string $color1,
        string $color2,
        string $extraClasses = '',
        string $linkUrl = '',
        string $nameHtml = '',
    ): string;

    /**
     * Render a team cell or plain "Free Agent" cell when teamId is 0
     *
     * Convenience method that wraps renderTeamCell() with a free-agent check.
     *
     * @param int $teamId Team ID (0 = free agent)
     * @param string $teamName Team name to display
     * @param string $color1 Primary color (hex, without #) for background
     * @param string $color2 Secondary color (hex, without #) for text
     * @param string $extraClasses Additional CSS classes (e.g. 'sticky-col')
     * @param string $freeAgentText Text to display for free agents (default: 'Free Agent')
     * @return string Complete <td> HTML element
     */
    public static function renderTeamCellOrFreeAgent(
        int $teamId,
        string $teamName,
        string $color1,
        string $color2,
        string $extraClasses = '',
        string $freeAgentText = 'Free Agent',
    ): string;

    /**
     * Build the standard team page URL
     *
     * @param int $teamId Team ID
     * @param int|null $year Optional year parameter (for SeasonArchive)
     * @return string URL string (already HTML-escaped for use in href attributes)
     */
    public static function teamPageUrl(int $teamId, ?int $year = null): string;
}
