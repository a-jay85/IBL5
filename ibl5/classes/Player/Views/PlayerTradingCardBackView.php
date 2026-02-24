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
     * @deprecated CSS is now centralized in design/components/player-cards.css.
     *             Custom properties are set inline on the container element in render().
     *
     * @param array{primary: string, secondary: string, gradient_start: string, gradient_mid: string, gradient_end: string, border: string, border_rgb: string, accent: string, text: string, text_muted: string}|null $colorScheme Optional color scheme from TeamColorHelper
     * @return string Empty string — styles are in centralized CSS
     */
    public static function getStyles(?array $colorScheme = null): string
    {
        return '';
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
        $colorScheme = CardBaseStyles::getColorSchemeForTeam($db, $player->teamID ?? 0);
        $playerData = CardBaseStyles::preparePlayerData($player, $playerID);

        $cssProps = CardBaseStyles::getCardCssProperties($colorScheme);

        ob_start();
        ?>
<div class="trading-card-back" style="<?= $cssProps ?>">
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
        return (string) ob_get_clean();
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
        $regSeason = (string) ($regSeasonValue ?? 0);
        $regCareer = (string) ($regCareerValue ?? 0);
        $playoffSeason = (string) ($playoffSeasonValue ?? 0);
        $playoffCareer = (string) ($playoffCareerValue ?? 0);
        
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
        $safeValue = (string) $value;
        $safeLabel = HtmlSanitizer::safeHtmlOutput($label);
        
        return <<<HTML
<div class="allstar-pill">
    <span class="pill-label">{$safeLabel}</span>
    <span class="pill-value">{$safeValue}</span>
</div>
HTML;
    }
}
