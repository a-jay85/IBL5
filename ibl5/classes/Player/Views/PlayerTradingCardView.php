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
     * Get Tailwind CSS CDN and custom styles for trading card
     * 
     * @return string HTML style and script tags
     */
    public static function getStyles(): string
    {
        return <<<'HTML'
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config = {
    theme: {
        extend: {
            colors: {
                'card-gold': '#D4AF37',
                'card-navy': '#1e3a5f',
                'card-dark': '#0f1419',
            }
        }
    }
}
</script>
<style>
/* Trading Card Custom Styles */
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
}

.player-photo-frame {
    border: 3px solid #D4AF37;
    border-radius: 8px;
    background: linear-gradient(135deg, #2a2a2a 0%, #1a1a1a 100%);
}

.stat-pill {
    background: rgba(212, 175, 55, 0.15);
    border: 1px solid rgba(212, 175, 55, 0.3);
    border-radius: 6px;
    padding: 2px 6px;
    font-family: 'Monaco', 'Menlo', monospace;
}

.rating-row {
    display: grid;
    gap: 4px;
    background: rgba(0,0,0,0.3);
    border-radius: 8px;
    padding: 8px;
    margin-bottom: 6px;
}

.rating-row.shooting { grid-template-columns: repeat(6, 1fr); }
.rating-row.rebounding { grid-template-columns: repeat(7, 1fr); }
.rating-row.offense-defense { grid-template-columns: repeat(8, 1fr); }

.rating-cell {
    text-align: center;
    padding: 4px 2px;
}

.rating-label {
    font-size: 9px;
    font-weight: 600;
    text-transform: uppercase;
    color: #D4AF37;
    letter-spacing: 0.5px;
}

.rating-value {
    font-size: 14px;
    font-weight: 700;
    color: #fff;
    font-family: 'Monaco', 'Menlo', monospace;
}

.meta-badge {
    background: linear-gradient(135deg, #D4AF37 0%, #b8972e 100%);
    color: #0f1419;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
}

.contract-bar {
    background: linear-gradient(90deg, rgba(212,175,55,0.2) 0%, rgba(212,175,55,0.05) 100%);
    border-left: 3px solid #D4AF37;
    padding: 8px 12px;
    border-radius: 0 8px 8px 0;
}

/* Mobile responsiveness */
@media (max-width: 480px) {
    .trading-card {
        max-width: 100%;
        margin: 8px;
        border-radius: 12px;
    }
    
    .rating-label { font-size: 8px; }
    .rating-value { font-size: 12px; }
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
        $birdYears = HtmlSanitizer::safeHtmlOutput((string)$player->birdYears);
        $contractSafe = HtmlSanitizer::safeHtmlOutput($contractDisplay);
        $teamID = (int)$player->teamID;

        ob_start();
        ?>
<div class="trading-card p-4">
    <!-- Card Header: Name & Position -->
    <div class="flex items-center justify-between mb-3">
        <div>
            <h2 class="text-xl font-bold text-white leading-tight"><?= $name ?></h2>
            <?php if (!empty($nickname)): ?>
            <p class="text-card-gold text-sm italic">"<?= $nickname ?>"</p>
            <?php endif; ?>
        </div>
        <span class="meta-badge"><?= $position ?></span>
    </div>

    <!-- Player Photo & Quick Stats -->
    <div class="flex gap-4 mb-4">
        <div class="player-photo-frame p-1 flex-shrink-0">
            <img src="<?= HtmlSanitizer::safeHtmlOutput($imageUrl) ?>" 
                 alt="<?= $name ?>" 
                 class="w-24 h-28 object-cover rounded"
                 onerror="this.style.display='none'">
        </div>
        <div class="flex-1 text-sm">
            <div class="grid grid-cols-2 gap-y-1 text-gray-300">
                <span class="text-card-gold font-semibold">Team</span>
                <a href="modules.php?name=Team&op=team&teamID=<?= $teamID ?>" 
                   class="text-white hover:text-card-gold truncate"><?= $teamName ?></a>
                
                <span class="text-card-gold font-semibold">Age</span>
                <span class="text-white"><?= $age ?></span>
                
                <span class="text-card-gold font-semibold">Height</span>
                <span class="text-white"><?= $height ?></span>
                
                <span class="text-card-gold font-semibold">Weight</span>
                <span class="text-white"><?= $weight ?> lbs</span>
                
                <span class="text-card-gold font-semibold">College</span>
                <span class="text-white truncate"><?= $college ?></span>
            </div>
        </div>
    </div>

    <!-- Draft Info -->
    <div class="text-center text-xs text-gray-400 mb-3 italic">
        Drafted by <?= $draftTeam ?> · Rd <?= $draftRound ?>, Pick #<?= $draftPick ?> · 
        <a href="/ibl5/pages/draftHistory.php?year=<?= $player->draftYear ?>" class="text-card-gold hover:underline"><?= $draftYear ?></a>
    </div>

    <!-- RATINGS SECTION -->
    <div class="mb-3">
        <h3 class="text-card-gold text-xs font-bold uppercase tracking-wider mb-2 text-center">Player Ratings</h3>
        
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
            <?= self::renderRatingCell('od', $player->ratingOutsideDefense) ?>
            <?= self::renderRatingCell('dd', $player->ratingDriveDefense) ?>
            <?= self::renderRatingCell('pd', $player->ratingPostDefense) ?>
            <?= self::renderRatingCell('td', $player->ratingTransitionDefense) ?>
        </div>
    </div>

    <!-- Intangibles Row -->
    <div class="flex justify-center gap-3 mb-3 text-xs">
        <div class="stat-pill">
            <span class="text-card-gold">TAL</span> 
            <span class="text-white font-bold"><?= HtmlSanitizer::safeHtmlOutput((string)$player->ratingTalent) ?></span>
        </div>
        <div class="stat-pill">
            <span class="text-card-gold">SKL</span> 
            <span class="text-white font-bold"><?= HtmlSanitizer::safeHtmlOutput((string)$player->ratingSkill) ?></span>
        </div>
        <div class="stat-pill">
            <span class="text-card-gold">INT</span> 
            <span class="text-white font-bold"><?= HtmlSanitizer::safeHtmlOutput((string)$player->ratingIntangibles) ?></span>
        </div>
        <div class="stat-pill">
            <span class="text-card-gold">CLU</span> 
            <span class="text-white font-bold"><?= HtmlSanitizer::safeHtmlOutput((string)$player->ratingClutch) ?></span>
        </div>
        <div class="stat-pill">
            <span class="text-card-gold">CON</span> 
            <span class="text-white font-bold"><?= HtmlSanitizer::safeHtmlOutput((string)$player->ratingConsistency) ?></span>
        </div>
    </div>

    <!-- Free Agency Preferences -->
    <div class="mb-3">
        <h3 class="text-card-gold text-xs font-bold uppercase tracking-wider mb-2 text-center">FA Preferences</h3>
        <div class="flex justify-center gap-2 text-xs flex-wrap">
            <div class="stat-pill">
                <span class="text-gray-400">LOY</span> 
                <span class="text-white font-bold"><?= HtmlSanitizer::safeHtmlOutput((string)$player->freeAgencyLoyalty) ?></span>
            </div>
            <div class="stat-pill">
                <span class="text-gray-400">WIN</span> 
                <span class="text-white font-bold"><?= HtmlSanitizer::safeHtmlOutput((string)$player->freeAgencyPlayForWinner) ?></span>
            </div>
            <div class="stat-pill">
                <span class="text-gray-400">PT</span> 
                <span class="text-white font-bold"><?= HtmlSanitizer::safeHtmlOutput((string)$player->freeAgencyPlayingTime) ?></span>
            </div>
            <div class="stat-pill">
                <span class="text-gray-400">SEC</span> 
                <span class="text-white font-bold"><?= HtmlSanitizer::safeHtmlOutput((string)$player->freeAgencySecurity) ?></span>
            </div>
            <div class="stat-pill">
                <span class="text-gray-400">TRD</span> 
                <span class="text-white font-bold"><?= HtmlSanitizer::safeHtmlOutput((string)$player->freeAgencyTradition) ?></span>
            </div>
        </div>
    </div>

    <!-- Contract Info Footer -->
    <div class="contract-bar mt-2">
        <div class="flex justify-between items-center text-xs">
            <div>
                <span class="text-gray-400">Bird Years:</span>
                <span class="text-white font-bold ml-1"><?= $birdYears ?></span>
            </div>
            <div>
                <span class="text-gray-400">Contract:</span>
                <span class="text-card-gold font-bold ml-1"><?= $contractSafe ?></span>
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
