<?php

declare(strict_types=1);

namespace Player\Views;

use Player\Player;
use Player\PlayerStats;
use Player\PlayerImageHelper;
use Utilities\HtmlSanitizer;

/**
 * PlayerTradingCardBackView - Renders the back side of the player trading card
 * 
 * Shows player highs, career statistics, and All-Star activities.
 * Uses the same styling as PlayerTradingCardFrontView for visual consistency.
 * 
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
        // Use default colors if no scheme provided
        if ($colorScheme === null) {
            $colorScheme = TeamColorHelper::getDefaultColorScheme();
        }
        
        $gradStart = $colorScheme['gradient_start'];
        $gradMid = $colorScheme['gradient_mid'];
        $gradEnd = $colorScheme['gradient_end'];
        $border = $colorScheme['border'];
        $borderRgb = $colorScheme['border_rgb'];
        $accent = $colorScheme['accent'];
        $text = $colorScheme['text'];
        $textMuted = $colorScheme['text_muted'];
        
        return <<<HTML
<style>
/* Trading Card Back Custom Styles - Scoped to .trading-card-back */
.trading-card-back {
    background: linear-gradient(145deg, #{$gradStart} 0%, #{$gradMid} 50%, #{$gradEnd} 100%);
    border: 4px solid #{$border};
    border-radius: 16px;
    box-shadow: 
        0 0 0 2px #{$gradMid},
        0 0 0 4px #{$border},
        0 10px 40px rgba(0,0,0,0.4);
    max-width: 420px;
    margin: 0 auto;
    padding: 16px 16px 50px 16px;
    color: #{$text};
}

.trading-card-back * {
    box-sizing: border-box;
}

.trading-card-back .card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}

.trading-card-back .card-header h2 {
    font-size: 20px;
    font-weight: bold;
    color: #{$text};
    line-height: 1.2;
    margin: 0;
}

.trading-card-back .card-header .nickname {
    color: #{$accent};
    font-size: 14px;
    font-style: italic;
    margin: 2px 0 0 0;
}

.trading-card-back .meta-badge {
    background: linear-gradient(135deg, #{$border} 0%, #{$accent} 100%);
    color: #{$gradMid};
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
}

.trading-card-back .photo-stats-row {
    display: flex;
    gap: 16px;
    margin-bottom: 16px;
}

.trading-card-back .player-photo-frame {
    border: 3px solid #{$border};
    border-radius: 8px;
    background: linear-gradient(135deg, #{$gradMid} 0%, #{$gradStart} 100%);
    padding: 4px;
    flex-shrink: 0;
}

.trading-card-back .player-photo-frame img {
    width: 96px;
    height: 112px;
    object-fit: cover;
    border-radius: 4px;
    display: block;
}

.trading-card-back .quick-stats {
    flex: 1;
    font-size: 14px;
}

.trading-card-back .stats-grid {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 4px 8px;
    color: #{$textMuted};
}

.trading-card-back .stats-grid .label {
    color: #{$accent};
    font-weight: 600;
}

.trading-card-back .stats-grid .value {
    color: #{$text};
}

.trading-card-back .stats-grid a {
    color: #{$text};
    text-decoration: none;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.trading-card-back .stats-grid a:hover {
    color: #{$accent};
}

.trading-card-back .draft-info {
    text-align: center;
    font-size: 12px;
    color: #{$textMuted};
    margin-bottom: 12px;
    font-style: italic;
}

.trading-card-back .draft-info a {
    color: #{$accent};
    text-decoration: none;
}

.trading-card-back .draft-info a:hover {
    text-decoration: underline;
}

.trading-card-back .section-title {
    color: #{$accent};
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 8px;
    text-align: center;
}

/* Player Highs Table Styles */
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

/* All-Star Activity Pills */
.trading-card-back .allstar-row {
    display: flex;
    justify-content: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 8px;
}

.trading-card-back .allstar-pill {
    background: rgba({$borderRgb}, 0.15);
    border: 1px solid rgba({$borderRgb}, 0.3);
    border-radius: 6px;
    padding: 4px 8px;
    font-size: 11px;
    text-align: center;
}

.trading-card-back .allstar-pill .pill-label {
    color: #{$textMuted};
    font-size: 9px;
    text-transform: uppercase;
    display: block;
}

.trading-card-back .allstar-pill .pill-value {
    color: #{$accent};
    font-weight: bold;
    font-family: 'Monaco', 'Menlo', monospace;
}

.trading-card-back .card-footer {
    text-align: center;
    font-size: 10px;
    color: #{$textMuted};
    margin-top: 12px;
    font-style: italic;
    border-top: 1px solid rgba({$borderRgb}, 0.2);
    padding-top: 8px;
}

/* Mobile responsiveness */
@media (max-width: 480px) {
    .trading-card-back {
        max-width: 100%;
        margin: 8px;
        border-radius: 12px;
        padding: 12px;
    }
    
    .trading-card-back .highs-table th,
    .trading-card-back .highs-table td {
        padding: 3px 4px;
        font-size: 10px;
    }
    
    .trading-card-back .photo-stats-row {
        gap: 12px;
    }
    
    .trading-card-back .player-photo-frame img {
        width: 80px;
        height: 96px;
    }
    
    .trading-card-back .allstar-pill {
        padding: 3px 6px;
        font-size: 10px;
    }
}
</style>
HTML;
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
        // Fetch team colors if database connection provided
        $colorScheme = null;
        if ($db !== null && $player->teamID > 0) {
            $teamColors = TeamColorHelper::getTeamColors($db, $player->teamID);
            $colorScheme = TeamColorHelper::generateColorScheme($teamColors['color1'], $teamColors['color2']);
        }
        
        $imageUrl = PlayerImageHelper::getImageUrl($playerID);
        
        // Sanitize all output values
        $name = HtmlSanitizer::safeHtmlOutput($player->name);
        $nickname = HtmlSanitizer::safeHtmlOutput($player->nickname ?? '');
        $position = HtmlSanitizer::safeHtmlOutput($player->position);
        $teamName = HtmlSanitizer::safeHtmlOutput($player->teamName);
        $age = HtmlSanitizer::safeHtmlOutput((string)$player->age);
        $height = HtmlSanitizer::safeHtmlOutput($player->heightFeet . "'" . $player->heightInches . '"');
        $weight = HtmlSanitizer::safeHtmlOutput((string)$player->weightPounds);
        $college = HtmlSanitizer::safeHtmlOutput($player->collegeName ?? 'N/A');
        $draftYear = HtmlSanitizer::safeHtmlOutput((string)$player->draftYear);
        $draftRound = HtmlSanitizer::safeHtmlOutput((string)$player->draftRound);
        $draftPick = HtmlSanitizer::safeHtmlOutput((string)$player->draftPickNumber);
        $draftTeam = HtmlSanitizer::safeHtmlOutput($player->draftTeamOriginalName ?? '');
        $teamID = (int)$player->teamID;

        ob_start();
        ?>
<div class="trading-card-back">
    <!-- Card Header: Name & Position -->
    <div class="card-header">
        <div>
            <h2><?= $name ?></h2>
            <?php if (!empty($nickname)): ?>
            <p class="nickname">"<?= $nickname ?>"</p>
            <?php endif; ?>
        </div>
        <span class="meta-badge"><?= $position ?></span>
    </div>

    <!-- Player Photo & Quick Stats -->
    <div class="photo-stats-row">
        <div class="player-photo-frame">
            <img src="<?= HtmlSanitizer::safeHtmlOutput($imageUrl) ?>" 
                 alt="<?= $name ?>"
                 onerror="this.style.display='none'">
        </div>
        <div class="quick-stats">
            <div class="stats-grid">
                <span class="label">Team</span>
                <a href="modules.php?name=Team&op=team&teamID=<?= $teamID ?>"><?= $teamName ?></a>
                
                <span class="label">Age</span>
                <span class="value"><?= $age ?></span>
                
                <span class="label">Height</span>
                <span class="value"><?= $height ?></span>
                
                <span class="label">Weight</span>
                <span class="value"><?= $weight ?> lbs</span>
                
                <span class="label">College</span>
                <span class="value"><?= $college ?></span>
            </div>
        </div>
    </div>

    <!-- Draft Info -->
    <div class="draft-info">
        Drafted by <?= $draftTeam ?> · Rd <?= $draftRound ?>, Pick #<?= $draftPick ?> · 
        <a href="/ibl5/pages/draftHistory.php?year=<?= $player->draftYear ?>"><?= $draftYear ?></a>
    </div>

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
     * 
     * @param string $label The stat label
     * @param int|null $regSeasonValue Regular season value
     * @param int|null $regCareerValue Regular season career value
     * @param int|null $playoffSeasonValue Playoff season value
     * @param int|null $playoffCareerValue Playoff career value
     * @return string HTML for highs row
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
     * 
     * @param string $label The activity label
     * @param int $value The count value
     * @return string HTML for All-Star pill
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
