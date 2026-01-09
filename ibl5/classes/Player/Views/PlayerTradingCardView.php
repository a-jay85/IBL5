<?php

declare(strict_types=1);

namespace Player\Views;

use Player\Player;
use Player\PlayerImageHelper;
use Utilities\HtmlSanitizer;

/**
 * PlayerTradingCardView - Renders player info as a basketball trading card
 * 
 * Modern, mobile-first layout optimized for readability and information density.
 * Uses Tailwind CSS via CDN for rapid prototyping.
 * 
 * @since 2026-01-08
 */
class PlayerTradingCardView
{
    /**
     * Get scoped custom styles for trading card (no external dependencies)
     * 
     * @return string HTML style tag with scoped CSS
     */
    public static function getStyles(): string
    {
        return <<<'HTML'
<style>
/* Trading Card Custom Styles - Scoped to .trading-card */
.trading-card {
    background: linear-gradient(145deg, #1e3a5f 0%, #0f1419 50%, #1e3a5f 100%);
    border: 4px solid #D4AF37;
    border-radius: 16px;
    box-shadow: 
        0 0 0 2px #1e3a5f,
        0 0 0 4px #D4AF37,
        0 10px 40px rgba(0,0,0,0.4);
    max-width: 420px;
    margin: 0 auto;
    padding: 16px;
    color: #fff;
}

.trading-card * {
    box-sizing: border-box;
}

.trading-card .card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}

.trading-card .card-header h2 {
    font-size: 20px;
    font-weight: bold;
    color: #fff;
    line-height: 1.2;
    margin: 0;
}

.trading-card .card-header .nickname {
    color: #D4AF37;
    font-size: 14px;
    font-style: italic;
    margin: 2px 0 0 0;
}

.trading-card .meta-badge {
    background: linear-gradient(135deg, #D4AF37 0%, #b8972e 100%);
    color: #0f1419;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
}

.trading-card .photo-stats-row {
    display: flex;
    gap: 16px;
    margin-bottom: 16px;
}

.trading-card .player-photo-frame {
    border: 3px solid #D4AF37;
    border-radius: 8px;
    background: linear-gradient(135deg, #2a2a2a 0%, #1a1a1a 100%);
    padding: 4px;
    flex-shrink: 0;
}

.trading-card .player-photo-frame img {
    width: 96px;
    height: 112px;
    object-fit: cover;
    border-radius: 4px;
    display: block;
}

.trading-card .quick-stats {
    flex: 1;
    font-size: 14px;
}

.trading-card .stats-grid {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 4px 8px;
    color: #d1d5db;
}

.trading-card .stats-grid .label {
    color: #D4AF37;
    font-weight: 600;
}

.trading-card .stats-grid .value {
    color: #fff;
}

.trading-card .stats-grid a {
    color: #fff;
    text-decoration: none;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.trading-card .stats-grid a:hover {
    color: #D4AF37;
}

.trading-card .draft-info {
    text-align: center;
    font-size: 12px;
    color: #9ca3af;
    margin-bottom: 12px;
    font-style: italic;
}

.trading-card .draft-info a {
    color: #D4AF37;
    text-decoration: none;
}

.trading-card .draft-info a:hover {
    text-decoration: underline;
}

.trading-card .section-title {
    color: #D4AF37;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 8px;
    text-align: center;
}

.trading-card .rating-row {
    display: grid;
    gap: 4px;
    background: rgba(0,0,0,0.3);
    border-radius: 8px;
    padding: 8px;
    margin-bottom: 6px;
}

.trading-card .rating-row.shooting { grid-template-columns: repeat(6, 1fr); }
.trading-card .rating-row.rebounding { grid-template-columns: repeat(7, 1fr); }
.trading-card .rating-row.offense-defense { grid-template-columns: repeat(9, 1fr); }

.trading-card .rating-cell {
    text-align: center;
    padding: 4px 2px;
}

.trading-card .rating-label {
    font-size: 9px;
    font-weight: 600;
    text-transform: uppercase;
    color: #D4AF37;
    letter-spacing: 0.5px;
}

.trading-card .rating-value {
    font-size: 14px;
    font-weight: 700;
    color: #fff;
    font-family: 'Monaco', 'Menlo', monospace;
}

.trading-card .stat-pill {
    background: rgba(212, 175, 55, 0.15);
    border: 1px solid rgba(212, 175, 55, 0.3);
    border-radius: 6px;
    padding: 2px 6px;
    font-family: 'Monaco', 'Menlo', monospace;
    display: inline-block;
}

.trading-card .pills-row {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin-bottom: 12px;
    font-size: 12px;
    flex-wrap: wrap;
}

.trading-card .stat-pill .pill-label {
    color: #D4AF37;
}

.trading-card .stat-pill .pill-value {
    color: #fff;
    font-weight: bold;
}

.trading-card .stat-pill.intangible .pill-label {
    color: #D4AF37;
}

.trading-card .stat-pill.preference .pill-label {
    color: #9ca3af;
}

.trading-card .contract-bar {
    background: linear-gradient(90deg, rgba(212,175,55,0.2) 0%, rgba(212,175,55,0.05) 100%);
    border-left: 3px solid #D4AF37;
    padding: 8px 12px;
    border-radius: 0 8px 8px 0;
    margin-top: 8px;
}

.trading-card .contract-bar .contract-flex {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 12px;
}

.trading-card .contract-bar .contract-label {
    color: #9ca3af;
}

.trading-card .contract-bar .contract-value {
    color: #fff;
    font-weight: bold;
    margin-left: 4px;
}

.trading-card .contract-bar .contract-amount {
    color: #D4AF37;
    font-weight: bold;
    margin-left: 4px;
}

/* Mobile responsiveness */
@media (max-width: 480px) {
    .trading-card {
        max-width: 100%;
        margin: 8px;
        border-radius: 12px;
        padding: 12px;
    }
    
    .trading-card .rating-label { font-size: 8px; }
    .trading-card .rating-value { font-size: 12px; }
    
    .trading-card .photo-stats-row {
        gap: 12px;
    }
    
    .trading-card .player-photo-frame img {
        width: 80px;
        height: 96px;
    }
}
</style>
HTML;
    }

    /**
     * Render the complete trading card
     * 
     * @param Player $player The player object
     * @param int $playerID The player's ID
     * @param string $contractDisplay Formatted contract string
     * @return string HTML for trading card
     */
    public static function render(Player $player, int $playerID, string $contractDisplay): string
    {
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
        $expYears = HtmlSanitizer::safeHtmlOutput((string)$player->yearsOfExperience);
        $birdYears = HtmlSanitizer::safeHtmlOutput((string)$player->birdYears);
        $contractSafe = HtmlSanitizer::safeHtmlOutput($contractDisplay);
        $teamID = (int)$player->teamID;

        ob_start();
        ?>
<div class="trading-card">
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

    <!-- RATINGS SECTION -->
    <div>
        <h3 class="section-title">Player Ratings</h3>
        
        <!-- Row 1: Shooting (2ga 2gp fta ftp 3ga 3gp) -->
        <div class="rating-row shooting">
            <?= self::renderRatingCell('2ga', $player->ratingFieldGoalAttempts) ?>
            <?= self::renderRatingCell('2gp', $player->ratingFieldGoalPercentage) ?>
            <?= self::renderRatingCell('fta', $player->ratingFreeThrowAttempts) ?>
            <?= self::renderRatingCell('ftp', $player->ratingFreeThrowPercentage) ?>
            <?= self::renderRatingCell('3ga', $player->ratingThreePointAttempts) ?>
            <?= self::renderRatingCell('3gp', $player->ratingThreePointPercentage) ?>
        </div>

        <!-- Row 2: Rebounding/Defense (orb drb ast stl tvr blk foul) -->
        <div class="rating-row rebounding">
            <?= self::renderRatingCell('orb', $player->ratingOffensiveRebounds) ?>
            <?= self::renderRatingCell('drb', $player->ratingDefensiveRebounds) ?>
            <?= self::renderRatingCell('ast', $player->ratingAssists) ?>
            <?= self::renderRatingCell('stl', $player->ratingSteals) ?>
            <?= self::renderRatingCell('tvr', $player->ratingTurnovers) ?>
            <?= self::renderRatingCell('blk', $player->ratingBlocks) ?>
            <?= self::renderRatingCell('foul', $player->ratingFouls) ?>
        </div>

        <!-- Row 3: Offense/Defense (oo do po to od dd pd td) -->
        <div class="rating-row offense-defense">
            <?= self::renderRatingCell('oo', $player->ratingOutsideOffense) ?>
            <?= self::renderRatingCell('do', $player->ratingDriveOffense) ?>
            <?= self::renderRatingCell('po', $player->ratingPostOffense) ?>
            <?= self::renderRatingCell('to', $player->ratingTransitionOffense) ?>
            <div class="rating-cell"></div>
            <?= self::renderRatingCell('od', $player->ratingOutsideDefense) ?>
            <?= self::renderRatingCell('dd', $player->ratingDriveDefense) ?>
            <?= self::renderRatingCell('pd', $player->ratingPostDefense) ?>
            <?= self::renderRatingCell('td', $player->ratingTransitionDefense) ?>
        </div>
    </div>

    <!-- Intangibles Row -->
    <div class="pills-row">
        <div class="stat-pill intangible">
            <span class="pill-label">TAL</span> 
            <span class="pill-value"><?= HtmlSanitizer::safeHtmlOutput((string)$player->ratingTalent) ?></span>
        </div>
        <div class="stat-pill intangible">
            <span class="pill-label">SKL</span> 
            <span class="pill-value"><?= HtmlSanitizer::safeHtmlOutput((string)$player->ratingSkill) ?></span>
        </div>
        <div class="stat-pill intangible">
            <span class="pill-label">INT</span> 
            <span class="pill-value"><?= HtmlSanitizer::safeHtmlOutput((string)$player->ratingIntangibles) ?></span>
        </div>
        <div class="stat-pill intangible">
            <span class="pill-label">CLU</span> 
            <span class="pill-value"><?= HtmlSanitizer::safeHtmlOutput((string)$player->ratingClutch) ?></span>
        </div>
        <div class="stat-pill intangible">
            <span class="pill-label">CON</span> 
            <span class="pill-value"><?= HtmlSanitizer::safeHtmlOutput((string)$player->ratingConsistency) ?></span>
        </div>
    </div>

    <!-- Free Agency Preferences -->
    <div>
        <h3 class="section-title">Free Agency Preferences</h3>
        <div class="pills-row">
            <div class="stat-pill preference">
                <span class="pill-label">LOY</span> 
                <span class="pill-value"><?= HtmlSanitizer::safeHtmlOutput((string)$player->freeAgencyLoyalty) ?></span>
            </div>
            <div class="stat-pill preference">
                <span class="pill-label">WIN</span> 
                <span class="pill-value"><?= HtmlSanitizer::safeHtmlOutput((string)$player->freeAgencyPlayForWinner) ?></span>
            </div>
            <div class="stat-pill preference">
                <span class="pill-label">PT</span> 
                <span class="pill-value"><?= HtmlSanitizer::safeHtmlOutput((string)$player->freeAgencyPlayingTime) ?></span>
            </div>
            <div class="stat-pill preference">
                <span class="pill-label">SEC</span> 
                <span class="pill-value"><?= HtmlSanitizer::safeHtmlOutput((string)$player->freeAgencySecurity) ?></span>
            </div>
            <div class="stat-pill preference">
                <span class="pill-label">TRD</span> 
                <span class="pill-value"><?= HtmlSanitizer::safeHtmlOutput((string)$player->freeAgencyTradition) ?></span>
            </div>
        </div>
    </div>

    <!-- Contract Info Footer -->
    <div class="contract-bar">
        <div class="contract-flex">
            <div>
                <span class="contract-label">Exp:Bird Years:</span>
                <span class="contract-value"><?= $expYears ?>:<?= $birdYears ?></span>
            </div>
            <div>
                <span class="contract-label">Contract:</span>
                <span class="contract-amount"><?= $contractSafe ?></span>
            </div>
        </div>
    </div>
</div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a single rating cell
     * 
     * @param string $label The rating abbreviation
     * @param mixed $value The rating value
     * @return string HTML for rating cell
     */
    private static function renderRatingCell(string $label, $value): string
    {
        $safeValue = HtmlSanitizer::safeHtmlOutput((string)$value);
        return <<<HTML
<div class="rating-cell">
    <div class="rating-label">{$label}</div>
    <div class="rating-value">{$safeValue}</div>
</div>
HTML;
    }
}
