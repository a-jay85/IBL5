<?php

declare(strict_types=1);

namespace Player\Views;

use Player\Player;
use Player\PlayerStats;
use Utilities\HtmlSanitizer;

/**
 * PlayerTradingCardBackView - Renders the back side of the player trading card
 * 
 * Shows player highs, career statistics, and All-Star activities.
 * Uses CardBaseStyles for shared styling with PlayerTradingCardFrontView.
 * 
 * @see CardBaseStyles for shared CSS
 * @see PlayerTradingCardFrontView for the front side of the card
 * @since 2026-01-08
 */
class PlayerTradingCardBackView
{
    /**
     * Get scoped custom styles for trading card back with team colors
     * 
     * @param array|null $colorScheme Optional color scheme from TeamColorHelper
     * @return string HTML style tag with scoped CSS
     */
    public static function getStyles(?array $colorScheme = null): string
    {
        if ($colorScheme === null) {
            $colorScheme = TeamColorHelper::getDefaultColorScheme();
        }
        
        // Get shared base styles from CardBaseStyles
        $baseStyles = CardBaseStyles::getStyles($colorScheme);
        
        // Add back-card-specific styles only
        $borderRgb = $colorScheme['border_rgb'];
        $accent = $colorScheme['accent'];
        $text = $colorScheme['text'];
        $textMuted = $colorScheme['text_muted'];
        
        $backStyles = <<<HTML
<style>
/* Trading Card Back - Unique Styles (highs table, all-star pills) */
.trading-card-back .highs-table {
    width: 100%;
    background: rgba(0,0,0,0.3);
    border-radius: 8px;
    padding: 8px;
    margin-bottom: 12px;
    border-collapse: collapse;
    table-layout: fixed;
}

.trading-card-back .highs-table th,
.trading-card-back .highs-table td {
    padding: 4px 6px;
    text-align: center;
    font-size: 12px;
    width: 18%;
}

.trading-card-back .highs-table .stat-label {
    width: 28%;
}

.trading-card-back .highs-table .highs-header {
    color: #{$accent};
    font-weight: 600;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid rgba({$borderRgb}, 0.3);
}

.trading-card-back .highs-table .category-header {
    color: #{$textMuted};
    font-size: 9px;
    font-weight: 600;
    text-transform: uppercase;
}

.trading-card-back .highs-table .stat-label {
    color: #{$accent};
    font-weight: 600;
    text-align: left;
    font-size: 11px;
}

.trading-card-back .highs-table .stat-value {
    color: #{$text};
    font-weight: 700;
    font-family: 'Monaco', 'Menlo', monospace;
}

.trading-card-back .highs-table .season-col {
    background: rgba({$borderRgb}, 0.05);
}

.trading-card-back .highs-table .career-col {
    background: rgba({$borderRgb}, 0.1);
}

.trading-card-back .allstar-pill .pill-label {
    display: block;
    font-size: 9px;
    text-transform: uppercase;
}

@media (max-width: 480px) {
    .trading-card-back .highs-table th,
    .trading-card-back .highs-table td {
        padding: 3px 4px;
        font-size: 10px;
    }
}
</style>
HTML;

        return $baseStyles . $backStyles;
    }

    /**
     * Render the complete trading card back
     * 
     * @param Player $player The player object
     * @param PlayerStats $playerStats The player's statistics
     * @param int $playerID The player's ID
     * @param int $allStarGames Number of All-Star Games
     * @param int $threePointContests Number of Three-Point Contests
     * @param int $dunkContests Number of Slam Dunk Competitions
     * @param int $rookieSophChallenges Number of Rookie-Sophomore Challenges
     * @param \mysqli|null $db Optional database connection for team colors
     * @return string HTML for trading card back
     */
    public static function render(
        Player $player,
        PlayerStats $playerStats,
        int $playerID,
        int $allStarGames = 0,
        int $threePointContests = 0,
        int $dunkContests = 0,
        int $rookieSophChallenges = 0,
        ?\mysqli $db = null
    ): string {
        // Get color scheme and prepare player data using shared helpers
        $colorScheme = CardBaseStyles::getColorSchemeForTeam($db, (int)$player->teamID);
        $playerData = CardBaseStyles::preparePlayerData($player, $playerID);

        ob_start();
        ?>
<div class="trading-card-back">
<?= CardBaseStyles::renderCardTop($playerData) ?>

    <!-- PLAYER HIGHS SECTION -->
    <div>
        <h3 class="section-title">Player Highs</h3>
        
        <table class="highs-table">
            <tr>
                <th class="highs-header"></th>
                <th class="highs-header" colspan="2">Regular Season</th>
                <th class="highs-header" colspan="2">Playoffs</th>
            </tr>
            <tr>
                <td class="category-header"></td>
                <td class="category-header season-col">Ssn</td>
                <td class="category-header career-col">Car</td>
                <td class="category-header season-col">Ssn</td>
                <td class="category-header career-col">Car</td>
            </tr>
            <?= self::renderHighsRow('Points', 
                $playerStats->seasonHighPoints, 
                $playerStats->careerSeasonHighPoints,
                $playerStats->seasonPlayoffHighPoints,
                $playerStats->careerPlayoffHighPoints
            ) ?>
            <?= self::renderHighsRow('Rebounds', 
                $playerStats->seasonHighRebounds, 
                $playerStats->careerSeasonHighRebounds,
                $playerStats->seasonPlayoffHighRebounds,
                $playerStats->careerPlayoffHighRebounds
            ) ?>
            <?= self::renderHighsRow('Assists', 
                $playerStats->seasonHighAssists, 
                $playerStats->careerSeasonHighAssists,
                $playerStats->seasonPlayoffHighAssists,
                $playerStats->careerPlayoffHighAssists
            ) ?>
            <?= self::renderHighsRow('Steals', 
                $playerStats->seasonHighSteals, 
                $playerStats->careerSeasonHighSteals,
                $playerStats->seasonPlayoffHighSteals,
                $playerStats->careerPlayoffHighSteals
            ) ?>
            <?= self::renderHighsRow('Blocks', 
                $playerStats->seasonHighBlocks, 
                $playerStats->careerSeasonHighBlocks,
                $playerStats->seasonPlayoffHighBlocks,
                $playerStats->careerPlayoffHighBlocks
            ) ?>
            <?= self::renderHighsRow('Double-Doubles', 
                $playerStats->seasonDoubleDoubles, 
                $playerStats->careerDoubleDoubles,
                $playerStats->seasonPlayoffDoubleDoubles,
                $playerStats->careerPlayoffDoubleDoubles
            ) ?>
            <?= self::renderHighsRow('Triple-Doubles', 
                $playerStats->seasonTripleDoubles, 
                $playerStats->careerTripleDoubles,
                $playerStats->seasonPlayoffTripleDoubles,
                $playerStats->careerPlayoffTripleDoubles
            ) ?>
        </table>
    </div>

    <!-- ALL-STAR WEEKEND SECTION -->
    <div>
        <h3 class="section-title">All-Star Weekend</h3>
        <div class="allstar-row">
            <?= self::renderAllStarPill('All-Star Games', $allStarGames) ?>
            <?= self::renderAllStarPill('3PT Contest', $threePointContests) ?>
            <?= self::renderAllStarPill('Dunk Contest', $dunkContests) ?>
            <?= self::renderAllStarPill('Rookie-Soph', $rookieSophChallenges) ?>
        </div>
    </div>
</div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a single highs row
     */
    private static function renderHighsRow(
        string $label,
        ?int $regSeasonValue,
        ?int $regCareerValue,
        ?int $playoffSeasonValue,
        ?int $playoffCareerValue
    ): string {
        $regSeason = HtmlSanitizer::safeHtmlOutput((string)($regSeasonValue ?? 0));
        $regCareer = HtmlSanitizer::safeHtmlOutput((string)($regCareerValue ?? 0));
        $playoffSeason = HtmlSanitizer::safeHtmlOutput((string)($playoffSeasonValue ?? 0));
        $playoffCareer = HtmlSanitizer::safeHtmlOutput((string)($playoffCareerValue ?? 0));
        
        return <<<HTML
<tr>
    <td class="stat-label">{$label}</td>
    <td class="stat-value season-col">{$regSeason}</td>
    <td class="stat-value career-col">{$regCareer}</td>
    <td class="stat-value season-col">{$playoffSeason}</td>
    <td class="stat-value career-col">{$playoffCareer}</td>
</tr>
HTML;
    }

    /**
     * Render an All-Star activity pill
     */
    private static function renderAllStarPill(string $label, int $value): string
    {
        $safeValue = HtmlSanitizer::safeHtmlOutput((string)$value);
        $safeLabel = HtmlSanitizer::safeHtmlOutput($label);
        
        return <<<HTML
<div class="allstar-pill">
    <span class="pill-label">{$safeLabel}</span>
    <span class="pill-value">{$safeValue}</span>
</div>
HTML;
    }
}
