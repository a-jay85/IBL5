<?php

declare(strict_types=1);

namespace Player\Views;

use Player\Player;
use Player\PlayerImageHelper;
use Utilities\HtmlSanitizer;

/**
 * CardBaseStyles - Shared CSS styles and HTML for trading cards
 * 
 * Consolidates common CSS patterns used across PlayerTradingCardFrontView
 * and PlayerTradingCardBackView to eliminate duplication (~80% shared CSS).
 * Also provides shared HTML rendering for identical card sections.
 * 
 * @since 2026-01-08
 */
class CardBaseStyles
{
    /**
     * Get complete shared CSS for trading cards (front and back)
     * 
     * Returns all base styles that are identical between front and back trading cards.
     * Use this as the primary method - front/back views only add their unique styles.
     * 
     * @param array $colorScheme Color scheme from TeamColorHelper
     * @return string HTML style tag with all base CSS
     */
    public static function getStyles(array $colorScheme): string
    {
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
/* Trading Card Base Styles - Shared between front (.trading-card) and back (.trading-card-back) */
.trading-card,
.trading-card-back {
    background: linear-gradient(145deg, #{$gradStart} 0%, #{$gradMid} 20%, #{$gradMid} 80%, #{$gradEnd} 100%);
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

.trading-card *,
.trading-card-back * {
    box-sizing: border-box;
}

/* Card Header */
.trading-card .card-header,
.trading-card-back .card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}

.trading-card .card-header h2,
.trading-card-back .card-header h2 {
    font-size: 20px;
    font-weight: bold;
    color: #{$text};
    line-height: 1.2;
    margin: 0;
}

.trading-card .card-header .nickname,
.trading-card-back .card-header .nickname {
    color: #{$accent};
    font-size: 14px;
    font-style: italic;
    margin: 2px 0 0 0;
}

.trading-card .meta-badge,
.trading-card-back .meta-badge {
    background: linear-gradient(135deg, #{$border} 0%, #{$accent} 100%);
    color: #{$gradMid};
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
}

/* Photo and Stats Row */
.trading-card .photo-stats-row,
.trading-card-back .photo-stats-row {
    display: flex;
    gap: 16px;
    margin-bottom: 16px;
}

.trading-card .player-photo-frame,
.trading-card-back .player-photo-frame {
    border: 3px solid #{$border};
    border-radius: 8px;
    background: linear-gradient(135deg, #{$gradMid} 0%, #{$gradStart} 100%);
    padding: 4px;
    flex-shrink: 0;
}

.trading-card .player-photo-frame img,
.trading-card-back .player-photo-frame img {
    width: 96px;
    height: 112px;
    object-fit: cover;
    border-radius: 4px;
    display: block;
}

.trading-card .quick-stats,
.trading-card-back .quick-stats {
    flex: 1;
    font-size: 14px;
}

.trading-card .stats-grid,
.trading-card-back .stats-grid {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 4px 8px;
    color: #{$textMuted};
}

.trading-card .stats-grid .label,
.trading-card-back .stats-grid .label {
    color: #{$accent};
    font-weight: 600;
}

.trading-card .stats-grid .value,
.trading-card-back .stats-grid .value {
    color: #{$text};
}

.trading-card .stats-grid a,
.trading-card-back .stats-grid a {
    color: #{$text};
    text-decoration: none;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.trading-card .stats-grid a:hover,
.trading-card-back .stats-grid a:hover {
    color: #{$accent};
}

/* Draft Info */
.trading-card .draft-info,
.trading-card-back .draft-info {
    text-align: center;
    font-size: 12px;
    color: #{$textMuted};
    margin-bottom: 12px;
    font-style: italic;
}

.trading-card .draft-info a,
.trading-card-back .draft-info a {
    color: #{$accent};
    text-decoration: none;
}

.trading-card .draft-info a:hover,
.trading-card-back .draft-info a:hover {
    text-decoration: underline;
}

/* Section Title */
.trading-card .section-title,
.trading-card-back .section-title {
    color: #{$accent};
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 8px;
    text-align: center;
}

/* Shared Pill Styles */
.trading-card .pills-row,
.trading-card-back .pills-row,
.trading-card-back .allstar-row {
    display: flex;
    justify-content: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 12px;
    font-size: 12px;
}

.trading-card .stat-pill,
.trading-card-back .stat-pill,
.trading-card-back .allstar-pill {
    background: rgba({$borderRgb}, 0.15);
    border: 1px solid rgba({$borderRgb}, 0.3);
    border-radius: 6px;
    padding: 4px 8px;
    font-family: 'Monaco', 'Menlo', monospace;
    display: inline-block;
    text-align: center;
}

.trading-card .stat-pill .pill-label,
.trading-card-back .stat-pill .pill-label,
.trading-card-back .allstar-pill .pill-label {
    color: #{$accent};
}

.trading-card .stat-pill .pill-value,
.trading-card-back .stat-pill .pill-value,
.trading-card-back .allstar-pill .pill-value {
    color: #{$text};
    font-weight: bold;
}

.trading-card .stat-pill.preference .pill-label {
    color: #{$textMuted};
}

/* Mobile Responsiveness */
@media (max-width: 480px) {
    .trading-card,
    .trading-card-back {
        max-width: 100%;
        margin: 8px;
        border-radius: 12px;
        padding: 12px;
    }
    
    .trading-card .photo-stats-row,
    .trading-card-back .photo-stats-row {
        gap: 12px;
    }
    
    .trading-card .player-photo-frame img,
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
     * Render the common card top section (header, photo, stats grid, draft info)
     * 
     * This HTML is identical between front and back trading cards.
     * 
     * @param array $playerData Sanitized player data from preparePlayerData()
     * @return string HTML for the common card top section
     */
    public static function renderCardTop(array $playerData): string
    {
        $nicknameHtml = !empty($playerData['nickname']) 
            ? '<p class="nickname">"' . $playerData['nickname'] . '"</p>' 
            : '';
        
        $name = $playerData['name'];
        $position = $playerData['position'];
        $imageUrl = HtmlSanitizer::safeHtmlOutput($playerData['imageUrl']);
        $teamID = $playerData['teamID'];
        $teamName = $playerData['teamName'];
        $age = $playerData['age'];
        $height = $playerData['height'];
        $weight = $playerData['weight'];
        $college = $playerData['college'];
        $draftTeam = $playerData['draftTeam'];
        $draftRound = $playerData['draftRound'];
        $draftPick = $playerData['draftPick'];
        $draftYear = $playerData['draftYear'];

        return <<<HTML
    <!-- Card Header: Name & Position -->
    <div class="card-header">
        <div>
            <h2>{$name}</h2>
            {$nicknameHtml}
        </div>
        <span class="meta-badge">{$position}</span>
    </div>

    <!-- Player Photo & Quick Stats -->
    <div class="photo-stats-row">
        <div class="player-photo-frame">
            <img src="{$imageUrl}" 
                 alt="{$name}"
                 onerror="this.style.display='none'">
        </div>
        <div class="quick-stats">
            <div class="stats-grid">
                <span class="label">Team</span>
                <a href="modules.php?name=Team&op=team&teamID={$teamID}">{$teamName}</a>
                
                <span class="label">Age</span>
                <span class="value">{$age}</span>
                
                <span class="label">Height</span>
                <span class="value">{$height}</span>
                
                <span class="label">Weight</span>
                <span class="value">{$weight} lbs</span>
                
                <span class="label">College</span>
                <span class="value">{$college}</span>
            </div>
        </div>
    </div>

    <!-- Draft Info -->
    <div class="draft-info">
        Drafted by {$draftTeam} · Rd {$draftRound}, Pick #{$draftPick} · 
        <a href="/ibl5/pages/draftHistory.php?year={$draftYear}">{$draftYear}</a>
    </div>
HTML;
    }

    /**
     * Prepare player data for rendering (sanitizes all output)
     * 
     * @param Player $player The player object
     * @param int $playerID The player's ID
     * @return array Sanitized data ready for rendering
     */
    public static function preparePlayerData(Player $player, int $playerID): array
    {
        return [
            'name' => HtmlSanitizer::safeHtmlOutput($player->name),
            'nickname' => HtmlSanitizer::safeHtmlOutput($player->nickname ?? ''),
            'position' => HtmlSanitizer::safeHtmlOutput($player->position),
            'teamName' => HtmlSanitizer::safeHtmlOutput($player->teamName),
            'teamID' => (int)$player->teamID,
            'age' => HtmlSanitizer::safeHtmlOutput((string)$player->age),
            'height' => HtmlSanitizer::safeHtmlOutput($player->heightFeet . "'" . $player->heightInches . '"'),
            'weight' => HtmlSanitizer::safeHtmlOutput((string)$player->weightPounds),
            'college' => HtmlSanitizer::safeHtmlOutput($player->collegeName ?? 'N/A'),
            'draftYear' => (int)$player->draftYear,
            'draftRound' => HtmlSanitizer::safeHtmlOutput((string)$player->draftRound),
            'draftPick' => HtmlSanitizer::safeHtmlOutput((string)$player->draftPickNumber),
            'draftTeam' => HtmlSanitizer::safeHtmlOutput($player->draftTeamOriginalName ?? ''),
            'imageUrl' => PlayerImageHelper::getImageUrl($playerID),
        ];
    }

    /**
     * Get color scheme for a player's team
     * 
     * @param \mysqli|null $db Database connection
     * @param int $teamID Team ID
     * @return array Color scheme array
     */
    public static function getColorSchemeForTeam(?\mysqli $db, int $teamID): array
    {
        if ($db !== null && $teamID > 0) {
            $teamColors = TeamColorHelper::getTeamColors($db, $teamID);
            return TeamColorHelper::generateColorScheme($teamColors['color1'], $teamColors['color2']);
        }
        return TeamColorHelper::getDefaultColorScheme();
    }
}
