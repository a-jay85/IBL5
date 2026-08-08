<?php

declare(strict_types=1);

namespace Standings;

use Standings\Contracts\StandingsRepositoryInterface;
use UI\TeamCellHelper;

/**
 * StandingsRowView - HTML rendering for a single standings table row
 *
 * Renders individual team rows, team name formatting, and clinch-tier helpers.
 *
 * @phpstan-import-type StandingsRow from StandingsRepositoryInterface
 * @phpstan-import-type StreakRow from StandingsRepositoryInterface
 * @phpstan-import-type PythagoreanStats from StandingsRepositoryInterface
 */
final class StandingsRowView
{
    /**
     * Render a single team row
     *
     * @param StandingsRow $team Team standings data
     * @param bool $isBottomLocked Whether this team is mathematically locked at the bottom
     * @param StreakRow|null $streakData Pre-loaded streak data for this team
     * @param PythagoreanStats|null $pythagoreanStats Pre-loaded Pythagorean stats for this team
     * @return string HTML for team row
     */
    public function renderTeamRow(array $team, bool $isBottomLocked, ?array $streakData, ?array $pythagoreanStats): string
    {
        $teamId = $team['teamid'];
        $teamName = $this->formatTeamName($team);

        $lastWin = $streakData['last_win'] ?? 0;
        $lastLoss = $streakData['last_loss'] ?? 0;
        $streak = $streakData['streak'] ?? 0;
        $streakSortKey = ($streakData['streak_type'] ?? '') === 'W' ? $streak : -$streak;
        $rating = $streakData['ranking'] ?? 0;
        $sos = $streakData['sos'] ?? 0;
        $remainingSos = $streakData['remaining_sos'] ?? 0;

        // Get Pythagorean win percentage from pre-loaded data
        $pythagoreanPct = '0.000';
        if ($pythagoreanStats !== null) {
            $pythagoreanPct = \BasketballStats\StatsFormatter::calculatePythagoreanWinPercentage(
                $pythagoreanStats['pointsScored'],
                $pythagoreanStats['pointsAllowed']
            );
        }

        $leagueRecord = $team['league_record'];
        $pct = $team['pct'];
        $gamesBack = $team['gamesBack'];
        $magicNumber = $team['magicNumber'];
        $gamesUnplayed = $team['games_unplayed'];
        $confRecord = $team['conf_record'];
        $divRecord = $team['div_record'];
        $homeRecord = $team['home_record'];
        $awayRecord = $team['away_record'];
        $homeGames = $team['homeGames'];
        $awayGames = $team['awayGames'];

        // Build CSS class for row highlighting
        $rowClass = '';
        if ($isBottomLocked) {
            $rowClass = 'bottom-locked';
        } else {
            $rowClass = self::getClinchTierClass($team);
        }

        ob_start();
        ?>
        <tr data-team-id="<?= \Security\HtmlSanitizer::e($teamId); ?>"<?= $rowClass !== '' ? ' class="' . \Security\HtmlSanitizer::e($rowClass) . '"' : ''; ?>>
            <?= TeamCellHelper::renderTeamCell($teamId, $team['team_name'], $team['color1'], $team['color2'], 'sticky-col', '', $teamName) ?>
            <td><?= \Security\HtmlSanitizer::e($leagueRecord); ?></td>
            <td><?= \BasketballStats\StatsFormatter::formatWithDecimals((float)$pct, 3); ?></td>
            <td><?= \Security\HtmlSanitizer::e($pythagoreanPct); ?></td>
            <td><?= \Security\HtmlSanitizer::e($gamesBack); ?></td>
            <td><?= \Security\HtmlSanitizer::e($magicNumber); ?></td>
            <td><?= \Security\HtmlSanitizer::e($gamesUnplayed); ?></td>
            <td><?= \Security\HtmlSanitizer::e($confRecord); ?></td>
            <td><?= \Security\HtmlSanitizer::e($divRecord); ?></td>
            <td><?= \Security\HtmlSanitizer::e($homeRecord); ?></td>
            <td><?= \Security\HtmlSanitizer::e($awayRecord); ?></td>
            <td><?= \Security\HtmlSanitizer::e($homeGames); ?></td>
            <td><?= \Security\HtmlSanitizer::e($awayGames); ?></td>
            <td><?= \Security\HtmlSanitizer::e($lastWin); ?>-<?= \Security\HtmlSanitizer::e($lastLoss); ?></td>
            <td sorttable_customkey="<?= \Security\HtmlSanitizer::e($streakSortKey); ?>"><?= \Security\HtmlSanitizer::e($streakData['streak_type'] ?? ''); ?> <?= \Security\HtmlSanitizer::e($streak); ?></td>
            <td><span class="ibl-stat-highlight"><?= \Security\HtmlSanitizer::e($rating); ?></span></td>
            <td><?= \BasketballStats\StatsFormatter::formatWithDecimals((float)$sos, 3); ?></td>
            <td><?= \BasketballStats\StatsFormatter::formatWithDecimals((float)$remainingSos, 3); ?></td>
        </tr>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Format team name with clinched indicator
     *
     * Priority: W (league) > Z (conference) > Y (division) > X (playoffs)
     *
     * @param StandingsRow $team Team standings data
     * @return string Formatted team name with clinched prefix if applicable
     */
    public function formatTeamName(array $team): string
    {
        $teamName = \Security\HtmlSanitizer::safeHtmlOutput($team['team_name']);

        if ($team['clinched_league'] === 1) {
            return '<span class="ibl-clinched-indicator">W</span>-' . $teamName;
        }

        if ($team['clinched_conference'] === 1) {
            return '<span class="ibl-clinched-indicator">Z</span>-' . $teamName;
        }

        if ($team['clinched_division'] === 1) {
            return '<span class="ibl-clinched-indicator">Y</span>-' . $teamName;
        }

        if ($team['clinched_playoffs'] === 1) {
            return '<span class="ibl-clinched-indicator">X</span>-' . $teamName;
        }

        return $teamName;
    }

    /**
     * Determine which teams are eliminated (bottom-locked) in standings
     *
     * When the season is over (all gamesUnplayed = 0), any team without a clinch
     * flag is eliminated. During the season, cascades from the bottom: a team is
     * locked if even winning all remaining games can't catch the team above.
     * The cascade stops at clinched teams or when a team can catch the one above.
     *
     * @param list<StandingsRow> $standings Standings data sorted by games back ASC
     * @return array<int, true> Map of array indexes that are bottom-locked
     */
    public function getBottomLockedIndexes(array $standings): array
    {
        $count = count($standings);
        /** @var array<int, true> $locked */
        $locked = [];

        if ($this->isSeasonOver($standings)) {
            foreach ($standings as $index => $team) {
                if (!self::hasClinchStatus($team)) {
                    $locked[$index] = true;
                }
            }

            return $locked;
        }

        // During season: cascade from bottom, stop at clinched teams
        for ($i = $count - 1; $i >= 1; $i--) {
            if (self::hasClinchStatus($standings[$i])) {
                break;
            }

            $maxPossibleWins = $standings[$i]['wins'] + $standings[$i]['games_unplayed'];
            if ($maxPossibleWins < $standings[$i - 1]['wins']) {
                $locked[$i] = true;
            } else {
                break;
            }
        }

        return $locked;
    }

    /**
     * Check if the season is over (all teams have 0 games remaining)
     *
     * @param list<StandingsRow> $standings Standings data
     */
    private function isSeasonOver(array $standings): bool
    {
        foreach ($standings as $team) {
            if ($team['games_unplayed'] > 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a team has any clinch status (playoffs, division, conference, or league)
     *
     * @param StandingsRow $team Team standings data
     */
    public static function hasClinchStatus(array $team): bool
    {
        return $team['clinched_league'] === 1
            || $team['clinched_conference'] === 1
            || $team['clinched_division'] === 1
            || $team['clinched_playoffs'] === 1;
    }

    /**
     * Get the CSS class for a team's clinch tier
     *
     * @param StandingsRow $team Team standings data
     * @return string CSS class name, or empty string if not clinched
     */
    public static function getClinchTierClass(array $team): string
    {
        if ($team['clinched_league'] === 1) {
            return 'clinch-league';
        }

        if ($team['clinched_conference'] === 1) {
            return 'clinch-conference';
        }

        if ($team['clinched_division'] === 1) {
            return 'clinch-division';
        }

        if ($team['clinched_playoffs'] === 1) {
            return 'clinch-playoffs';
        }

        return '';
    }

    /**
     * Compute clinch tier score for a team (higher = better clinch status)
     *
     * @param array{clinched_league: int, clinched_conference: int, clinched_division: int, clinched_playoffs: int, ...} $team
     */
    public static function getClinchTierScore(array $team): int
    {
        return $team['clinched_league'] * 4
            + $team['clinched_conference'] * 3
            + $team['clinched_division'] * 2
            + $team['clinched_playoffs'];
    }
}
