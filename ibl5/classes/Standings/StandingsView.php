<?php

declare(strict_types=1);

namespace Standings;

use Standings\Contracts\StandingsRepositoryInterface;
use Standings\Contracts\StandingsViewInterface;

/**
 * StandingsView - HTML rendering for team standings
 *
 * Generates sortable HTML tables for conference and division standings.
 * Handles clinched indicators (X/Y/Z) and team streak display.
 *
 * @see StandingsViewInterface For the interface contract
 * @see StandingsRepository For data access
 */
class StandingsView implements StandingsViewInterface
{
    private StandingsRepositoryInterface $repository;

    /**
     * Constructor
     *
     * @param StandingsRepositoryInterface $repository Standings data repository
     */
    public function __construct(StandingsRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @see StandingsViewInterface::render()
     */
    public function render(): string
    {
        $html = $this->getStyleBlock();

        // Conference standings
        $html .= $this->renderRegion('Eastern');
        $html .= $this->renderRegion('Western');

        // Division standings
        $html .= $this->renderRegion('Atlantic');
        $html .= $this->renderRegion('Central');
        $html .= $this->renderRegion('Midwest');
        $html .= $this->renderRegion('Pacific');

        return $html;
    }

    /**
     * Generate consolidated CSS styles for standings tables
     *
     * @return string CSS style block
     */
    private function getStyleBlock(): string
    {
        return '<style>
/* Standings section title */
.standings-title {
    font-family: var(--font-display, \'Poppins\', -apple-system, BlinkMacSystemFont, sans-serif);
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--navy-900, #0f172a);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin: 1.5rem 0 0.75rem 0;
    padding-bottom: 0rem;
    border-bottom: 2px solid var(--accent-500, #f97316);
    display: inline-block;
}
.standings-title:first-of-type {
    margin-top: 0;
}

/* Standings table */
.standings-table {
    font-family: var(--font-sans, \'Inter\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif);
    border-collapse: separate;
    border-spacing: 0;
    border: none;
    width: 100%;
    max-width: 100%;
    margin-bottom: 0.5rem;
    min-width: 800px; /* Ensure table is wide enough to scroll */
}

/* Scroll container gets the visual styling on mobile */
@media (max-width: 768px) {
    .standings-table-container {
        /* No overflow hidden on mobile - allows sticky to work */
    }
}
@media (min-width: 769px) {
    .standings-table-container {
        border-radius: var(--radius-lg, 0.5rem);
        box-shadow: var(--shadow-md, 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1));
        overflow: hidden; /* For rounded corners - only on desktop */
    }
}

/* Header row */
.standings-header-row {
    background: linear-gradient(135deg, var(--navy-800, #1e293b), var(--navy-900, #0f172a));
}

.standings-header-cell {
    color: white;
    font-family: var(--font-display, \'Poppins\', -apple-system, BlinkMacSystemFont, sans-serif);
    font-weight: 600;
    font-size: 1.25rem;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    padding: 0.75rem 0.375rem;
    text-align: center;
    white-space: nowrap;
}

/* Data cells */
.standings-cell {
    color: var(--gray-800, #1f2937);
    font-size: 1rem;
    padding: 0.5rem 0.375rem;
    text-align: center;
    white-space: nowrap;
}

.standings-team-cell {
    text-align: left;
    padding-left: 0.75rem;
    font-weight: 500;
}

.standings-team-cell a {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.standings-team-logo {
    width: 24px;
    height: 24px;
    object-fit: contain;
    border-radius: var(--radius-sm, 0.25rem);
    flex-shrink: 0;
}

/* Rating highlight */
.standings-rating {
    font-family: var(--font-display, \'Poppins\', -apple-system, BlinkMacSystemFont, sans-serif);
    font-weight: 700;
    color: var(--accent-500, #f97316);
}

/* Row styling */
.standings-table tbody tr {
    transition: background-color 150ms ease;
}
.standings-table tbody tr:nth-child(odd) {
    background-color: white;
}
.standings-table tbody tr:nth-child(even) {
    background-color: var(--gray-50, #f9fafb);
}
.standings-table tbody tr:hover {
    background-color: var(--gray-100, #f3f4f6);
}

/* Links */
.standings-table a {
    color: var(--gray-800, #1f2937);
    text-decoration: none;
    font-weight: 500;
    transition: color 150ms ease;
}
.standings-table a:hover {
    color: var(--accent-500, #f97316);
}

/* Clinched indicators */
.standings-table strong {
    color: var(--accent-600, #ea580c);
    font-weight: 700;
}

/* Mobile sticky column support */
@media (max-width: 768px) {
    .standings-table.responsive-table th.sticky-col,
    .standings-table.responsive-table td.sticky-col {
        position: sticky;
        left: 0;
        z-index: 1;
        min-width: 120px;
    }
    .standings-table.responsive-table thead th.sticky-col {
        background: linear-gradient(135deg, var(--navy-800, #1e293b), var(--navy-900, #0f172a));
        z-index: 3;
    }
    .standings-table.responsive-table tbody tr:nth-child(odd) td.sticky-col {
        background-color: white;
    }
    .standings-table.responsive-table tbody tr:nth-child(even) td.sticky-col {
        background-color: var(--gray-50, #f9fafb);
    }
    .standings-table.responsive-table tbody tr:hover td.sticky-col {
        background-color: var(--gray-100, #f3f4f6);
    }
    .standings-table.responsive-table td.sticky-col {
        box-shadow: 2px 0 4px rgba(0, 0, 0, 0.05);
    }
}
        </style>
        <script>
        (function() {
            // Set explicit width on scroll containers for iOS compatibility
            function setContainerWidths() {
                var viewportWidth = document.documentElement.clientWidth;
                var containers = document.querySelectorAll(".table-scroll-container");
                containers.forEach(function(container) {
                    // Get the container offset from the left edge of viewport
                    var rect = container.getBoundingClientRect();
                    var availableWidth = viewportWidth - rect.left;
                    container.style.width = availableWidth + "px";
                    container.style.maxWidth = availableWidth + "px";
                });
            }

            function initScrollContainers() {
                // Scroll indicator logic
                document.querySelectorAll(".table-scroll-container").forEach(function(container) {
                    var wrapper = container.closest(".table-scroll-wrapper");
                    function updateScrollIndicator() {
                        var isAtEnd = container.scrollLeft + container.clientWidth >= container.scrollWidth - 5;
                        if (wrapper) wrapper.classList.toggle("scrolled-end", isAtEnd);
                    }
                    container.addEventListener("scroll", updateScrollIndicator);
                    updateScrollIndicator();
                });

                // Set widths
                setContainerWidths();
                window.addEventListener("resize", setContainerWidths);
                window.addEventListener("orientationchange", setContainerWidths);
            }

            // Run on DOMContentLoaded and load to ensure it works
            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", initScrollContainers);
            } else {
                initScrollContainers();
            }
        })();
        </script>';
    }

    /**
     * @see StandingsViewInterface::renderRegion()
     */
    public function renderRegion(string $region): string
    {
        $groupingType = $this->getGroupingType($region);
        $standings = $this->repository->getStandingsByRegion($region);

        $html = $this->renderHeader($region, $groupingType);
        $html .= $this->renderRows($standings);
        $html .= '</tbody></table></div></div></div>'; // Close table, table-container, scroll container, and wrapper

        return $html;
    }

    /**
     * Get the grouping type (Conference or Division) for a region
     *
     * @param string $region Region name
     * @return string 'Conference' or 'Division'
     */
    private function getGroupingType(string $region): string
    {
        if (in_array($region, \League::CONFERENCE_NAMES, true)) {
            return 'Conference';
        }

        return 'Division';
    }

    /**
     * Render the table header for a standings section
     *
     * @param string $region Region name
     * @param string $groupingType 'Conference' or 'Division'
     * @return string HTML for table header
     */
    private function renderHeader(string $region, string $groupingType): string
    {
        $safeRegion = \Utilities\HtmlSanitizer::safeHtmlOutput($region);
        $title = $safeRegion . ' ' . $groupingType;

        ob_start();
        ?>
        <div class="standings-title"><?= $title; ?></div>
        <div class="table-scroll-wrapper">
        <div class="table-scroll-container">
        <div class="standings-table-container">
        <table class="sortable standings-table responsive-table">
            <thead>
                <tr class="standings-header-row">
                    <th class="standings-header-cell sticky-col">Team</th>
                    <th class="standings-header-cell">W-L</th>
                    <th class="standings-header-cell">Pct</th>
                    <th class="standings-header-cell">Pyth<br>W-L%</th>
                    <th class="standings-header-cell">GB</th>
                    <th class="standings-header-cell">Magic#</th>
                    <th class="standings-header-cell">Left</th>
                    <th class="standings-header-cell">Conf.</th>
                    <th class="standings-header-cell">Div.</th>
                    <th class="standings-header-cell">Home</th>
                    <th class="standings-header-cell">Away</th>
                    <th class="standings-header-cell">Home<br>Played</th>
                    <th class="standings-header-cell">Away<br>Played</th>
                    <th class="standings-header-cell">Last 10</th>
                    <th class="standings-header-cell">Streak</th>
                    <th class="standings-header-cell">Rating</th>
                </tr>
            </thead>
            <tbody>
        <?php
        return ob_get_clean();
    }

    /**
     * Render all team rows for a standings table
     *
     * @param array $standings Array of team standings data
     * @return string HTML for all team rows
     */
    private function renderRows(array $standings): string
    {
        $html = '';

        foreach ($standings as $team) {
            $html .= $this->renderTeamRow($team);
        }

        return $html;
    }

    /**
     * Render a single team row
     *
     * @param array $team Team standings data
     * @return string HTML for team row
     */
    private function renderTeamRow(array $team): string
    {
        $teamId = (int) $team['tid'];
        $teamName = $this->formatTeamName($team);
        $streakData = $this->repository->getTeamStreakData($teamId);

        $lastWin = $streakData['last_win'] ?? 0;
        $lastLoss = $streakData['last_loss'] ?? 0;
        $streakType = \Utilities\HtmlSanitizer::safeHtmlOutput($streakData['streak_type'] ?? '');
        $streak = $streakData['streak'] ?? 0;
        $rating = \Utilities\HtmlSanitizer::safeHtmlOutput((string) ($streakData['ranking'] ?? 0));

        // Get Pythagorean win percentage
        $pythagoreanStats = $this->repository->getTeamPythagoreanStats($teamId);
        $pythagoreanPct = '0.000';
        if ($pythagoreanStats !== null) {
            $pythagoreanPct = \BasketballStats\StatsFormatter::calculatePythagoreanWinPercentage(
                $pythagoreanStats['pointsScored'],
                $pythagoreanStats['pointsAllowed']
            );
        }

        ob_start();
        ?>
        <tr>
            <td class="standings-team-cell sticky-col"><a href="modules.php?name=Team&op=team&teamID=<?= $teamId; ?>"><img src="images/logo/new<?= $teamId; ?>.png" alt="Team Logo" class="standings-team-logo" loading="lazy"><?= $teamName; ?></a></td>
            <td class="standings-cell"><?= $team['leagueRecord']; ?></td>
            <td class="standings-cell"><?= $team['pct']; ?></td>
            <td class="standings-cell"><?= $pythagoreanPct; ?></td>
            <td class="standings-cell"><?= $team['gamesBack']; ?></td>
            <td class="standings-cell"><?= $team['magicNumber']; ?></td>
            <td class="standings-cell"><?= $team['gamesUnplayed']; ?></td>
            <td class="standings-cell"><?= $team['confRecord']; ?></td>
            <td class="standings-cell"><?= $team['divRecord']; ?></td>
            <td class="standings-cell"><?= $team['homeRecord']; ?></td>
            <td class="standings-cell"><?= $team['awayRecord']; ?></td>
            <td class="standings-cell"><?= $team['homeGames']; ?></td>
            <td class="standings-cell"><?= $team['awayGames']; ?></td>
            <td class="standings-cell"><?= $lastWin; ?>-<?= $lastLoss; ?></td>
            <td class="standings-cell"><?= $streakType; ?> <?= $streak; ?></td>
            <td class="standings-cell"><span class="standings-rating"><?= $rating; ?></span></td>
        </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Format team name with clinched indicator
     *
     * @param array $team Team standings data
     * @return string Formatted team name with clinched prefix if applicable
     */
    private function formatTeamName(array $team): string
    {
        $teamName = \Utilities\HtmlSanitizer::safeHtmlOutput($team['team_name']);

        if ($team['clinchedConference'] == 1) {
            return '<strong>Z</strong>-' . $teamName;
        }

        if ($team['clinchedDivision'] == 1) {
            return '<strong>Y</strong>-' . $teamName;
        }

        if ($team['clinchedPlayoffs'] == 1) {
            return '<strong>X</strong>-' . $teamName;
        }

        return $teamName;
    }
}
