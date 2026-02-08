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
     * Get CSS custom properties inline style string for card containers
     *
     * Sets the custom properties that player-cards.css references.
     * Apply this as a style attribute on the outermost card container.
     *
     * @param array{primary: string, secondary: string, gradient_start: string, gradient_mid: string, gradient_end: string, border: string, border_rgb: string, accent: string, text: string, text_muted: string} $colorScheme Color scheme from TeamColorHelper
     * @return string Inline style value (without style="" wrapper)
     */
    public static function getCardCssProperties(array $colorScheme): string
    {
        return '--card-grad-start:#' . $colorScheme['gradient_start']
            . ';--card-grad-mid:#' . $colorScheme['gradient_mid']
            . ';--card-grad-end:#' . $colorScheme['gradient_end']
            . ';--card-border:#' . $colorScheme['border']
            . ';--card-border-rgb:' . $colorScheme['border_rgb']
            . ';--card-accent:#' . $colorScheme['accent']
            . ';--card-text:#' . $colorScheme['text']
            . ';--card-text-muted:#' . $colorScheme['text_muted'];
    }

    /**
     * Get complete shared CSS for trading cards (front and back)
     *
     * @deprecated CSS is now centralized in design/components/player-cards.css.
     *             Use getCardCssProperties() to set custom properties on container elements.
     *
     * @param array{primary: string, secondary: string, gradient_start: string, gradient_mid: string, gradient_end: string, border: string, border_rgb: string, accent: string, text: string, text_muted: string} $colorScheme Color scheme from TeamColorHelper
     * @return string Empty string — styles are in centralized CSS
     */
    public static function getStyles(array $colorScheme): string
    {
        return '';
    }

    /**
     * Render the common card top section (header, photo, stats grid, draft info)
     *
     * This HTML is identical between front and back trading cards.
     *
     * @param array{name: string, nickname: string, position: string, teamName: string, teamID: int, age: string, height: string, weight: string, college: string, draftYear: int, draftRound: string, draftPick: string, draftTeam: string, imageUrl: string} $playerData Sanitized player data from preparePlayerData()
     * @return string HTML for the common card top section
     */
    public static function renderCardTop(array $playerData): string
    {
        $nicknameHtml = $playerData['nickname'] !== ''
            ? '<p class="nickname">"' . $playerData['nickname'] . '"</p>'
            : '';
        
        $name = $playerData['name'];
        $position = $playerData['position'];
        /** @var string $imageUrl */
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

        $teamLogoHtml = '';
        if ($teamID > 0) {
            $teamLogoHtml = '<div class="card-team-logo">'
                . '<a href="modules.php?name=Team&amp;op=team&amp;teamID=' . $teamID . '">'
                . '<img src="images/logo/new' . $teamID . '.png"'
                . ' alt="" width="83" height="83" loading="lazy">'
                . '</a></div>';
        }

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
            {$teamLogoHtml}
        </div>
    </div>

    <!-- Draft Info -->
    <div class="draft-info">
        Drafted by {$draftTeam} · Rd {$draftRound}, Pick #{$draftPick} · 
        <a href="/ibl5/modules.php?name=DraftHistory&year={$draftYear}">{$draftYear}</a>
    </div>
HTML;
    }

    /**
     * Prepare player data for rendering (sanitizes all output)
     *
     * @param Player $player The player object
     * @param int $playerID The player's ID
     * @return array{name: string, nickname: string, position: string, teamName: string, teamID: int, age: string, height: string, weight: string, college: string, draftYear: int, draftRound: string, draftPick: string, draftTeam: string, imageUrl: string} Sanitized data ready for rendering
     */
    public static function preparePlayerData(Player $player, int $playerID): array
    {
        /** @var string $name */
        $name = HtmlSanitizer::safeHtmlOutput($player->name);
        /** @var string $nickname */
        $nickname = HtmlSanitizer::safeHtmlOutput($player->nickname ?? '');
        /** @var string $position */
        $position = HtmlSanitizer::safeHtmlOutput($player->position);
        /** @var string $teamName */
        $teamName = HtmlSanitizer::safeHtmlOutput($player->teamName);
        /** @var string $age */
        $age = HtmlSanitizer::safeHtmlOutput((string) ($player->age ?? 0));
        /** @var string $height */
        $height = HtmlSanitizer::safeHtmlOutput(($player->heightFeet ?? 0) . "'" . ($player->heightInches ?? 0) . '"');
        /** @var string $weight */
        $weight = HtmlSanitizer::safeHtmlOutput((string) ($player->weightPounds ?? 0));
        /** @var string $college */
        $college = HtmlSanitizer::safeHtmlOutput($player->collegeName ?? 'N/A');
        /** @var string $draftRound */
        $draftRound = HtmlSanitizer::safeHtmlOutput((string) ($player->draftRound ?? 0));
        /** @var string $draftPick */
        $draftPick = HtmlSanitizer::safeHtmlOutput((string) ($player->draftPickNumber ?? 0));
        /** @var string $draftTeam */
        $draftTeam = HtmlSanitizer::safeHtmlOutput($player->draftTeamOriginalName ?? '');

        return [
            'name' => $name,
            'nickname' => $nickname,
            'position' => $position,
            'teamName' => $teamName,
            'teamID' => $player->teamID ?? 0,
            'age' => $age,
            'height' => $height,
            'weight' => $weight,
            'college' => $college,
            'draftYear' => $player->draftYear ?? 0,
            'draftRound' => $draftRound,
            'draftPick' => $draftPick,
            'draftTeam' => $draftTeam,
            'imageUrl' => PlayerImageHelper::getImageUrl($playerID),
        ];
    }

    /**
     * Get color scheme for a player's team
     *
     * @param \mysqli|null $db Database connection
     * @param int $teamID Team ID
     * @return array{primary: string, secondary: string, gradient_start: string, gradient_mid: string, gradient_end: string, border: string, border_rgb: string, accent: string, text: string, text_muted: string} Color scheme array
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
