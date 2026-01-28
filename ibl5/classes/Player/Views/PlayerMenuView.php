<?php

declare(strict_types=1);

namespace Player\Views;

/**
 * PlayerMenuView - Renders player page navigation menu
 * 
 * @since 2026-01-08
 */
class PlayerMenuView
{
    /**
     * Render player menu navigation as dropdown
     * 
     * @param int $playerID The player's ID
     * @param int|null $currentPageType Current page type to mark as selected (optional)
     * @return string HTML for player menu dropdown
     */
    public static function render(int $playerID, ?int $currentPageType = null): string
    {
        $pageTypes = [
            \PlayerPageType::OVERVIEW,
            \PlayerPageType::AWARDS_AND_NEWS,
            \PlayerPageType::ONE_ON_ONE,
            \PlayerPageType::SIM_STATS,
            \PlayerPageType::REGULAR_SEASON_TOTALS,
            \PlayerPageType::REGULAR_SEASON_AVERAGES,
            \PlayerPageType::PLAYOFF_TOTALS,
            \PlayerPageType::PLAYOFF_AVERAGES,
            \PlayerPageType::HEAT_TOTALS,
            \PlayerPageType::HEAT_AVERAGES,
            \PlayerPageType::OLYMPIC_TOTALS,
            \PlayerPageType::OLYMPIC_AVERAGES,
            \PlayerPageType::RATINGS_AND_SALARY,
        ];

        ob_start();
        ?>
<tr>
    <td colspan="2" class="player-menu-container">
        <select class="player-menu-dropdown" onchange="if(this.value) window.location.href=this.value;">
            <option value="">-- Select a Page --</option>
            <?php foreach ($pageTypes as $pageType): 
                $isSelected = ($currentPageType === $pageType) || ($currentPageType === null && $pageType === \PlayerPageType::OVERVIEW);
            ?>
                <option value="<?= \PlayerPageType::getUrl($playerID, $pageType) ?>" 
                        <?= $isSelected ? 'selected' : '' ?>>
                    <?= \PlayerPageType::getDescription($pageType) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </td>
</tr>
        <?php
        return ob_get_clean();
    }
}
