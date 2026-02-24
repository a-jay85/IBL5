<?php

declare(strict_types=1);

namespace Player\Views;

use Player\PlayerPageType;

/**
 * PlayerMenuView - Renders player page navigation menu
 *
 * Grouped segmented navigation using the IBL5 design system.
 * Groups: General, Regular Season, Playoffs, Special (H.E.A.T. + Olympics), History.
 *
 * @since 2026-01-08
 */
class PlayerMenuView
{
    /**
     * Menu groups with their page types
     *
     * @return array<string, array<int|null>>
     */
    private static function getMenuGroups(): array
    {
        return [
            'General' => [
                PlayerPageType::OVERVIEW,
                PlayerPageType::SIM_STATS,
            ],
            'Regular Season' => [
                PlayerPageType::REGULAR_SEASON_AVERAGES,
                PlayerPageType::REGULAR_SEASON_TOTALS,
            ],
            'Playoffs' => [
                PlayerPageType::PLAYOFF_AVERAGES,
                PlayerPageType::PLAYOFF_TOTALS,
            ],
            'Special' => [
                PlayerPageType::HEAT_AVERAGES,
                PlayerPageType::HEAT_TOTALS,
                PlayerPageType::OLYMPIC_AVERAGES,
                PlayerPageType::OLYMPIC_TOTALS,
            ],
            'History' => [
                PlayerPageType::AWARDS_AND_NEWS,
                PlayerPageType::ONE_ON_ONE,
                PlayerPageType::RATINGS_AND_SALARY,
            ],
        ];
    }

    /**
     * Short labels for nav items (keeps pills compact)
     *
     * @return array<int|string, string>
     */
    private static function getShortLabels(): array
    {
        return [
            'overview' => 'Overview',
            'sim' => 'Sim Stats',
            'rs_avg' => 'Averages',
            'rs_tot' => 'Totals',
            'po_avg' => 'Averages',
            'po_tot' => 'Totals',
            'heat_avg' => 'H.E.A.T. Avg',
            'heat_tot' => 'H.E.A.T. Tot',
            'oly_avg' => 'Olympic Avg',
            'oly_tot' => 'Olympic Tot',
            'awards' => 'Awards & News',
            'one_on_one' => '1-on-1',
            'ratings' => 'Ratings & Salary',
        ];
    }

    /**
     * Map page type constants to short label keys
     */
    private static function getLabelKey(?int $pageType): string
    {
        return match ($pageType) {
            PlayerPageType::OVERVIEW => 'overview',
            PlayerPageType::SIM_STATS => 'sim',
            PlayerPageType::REGULAR_SEASON_AVERAGES => 'rs_avg',
            PlayerPageType::REGULAR_SEASON_TOTALS => 'rs_tot',
            PlayerPageType::PLAYOFF_AVERAGES => 'po_avg',
            PlayerPageType::PLAYOFF_TOTALS => 'po_tot',
            PlayerPageType::HEAT_AVERAGES => 'heat_avg',
            PlayerPageType::HEAT_TOTALS => 'heat_tot',
            PlayerPageType::OLYMPIC_AVERAGES => 'oly_avg',
            PlayerPageType::OLYMPIC_TOTALS => 'oly_tot',
            PlayerPageType::AWARDS_AND_NEWS => 'awards',
            PlayerPageType::ONE_ON_ONE => 'one_on_one',
            PlayerPageType::RATINGS_AND_SALARY => 'ratings',
            default => 'overview',
        };
    }

    /**
     * Render player page navigation
     *
     * @param int $playerID The player's ID
     * @param int|null $currentPageType Current page type to mark as selected
     * @param array{primary: string, secondary: string, gradient_start: string, gradient_mid: string, gradient_end: string, border: string, border_rgb: string, accent: string, text: string, text_muted: string}|null $colorScheme Team color scheme from TeamColorHelper
     * @return string HTML for player menu
     */
    public static function render(int $playerID, ?int $currentPageType = null, ?array $colorScheme = null): string
    {
        $groups = self::getMenuGroups();
        $shortLabels = self::getShortLabels();

        // Determine which group the current page belongs to (for mobile dropdown default)
        $activeGroupName = 'General';
        foreach ($groups as $groupName => $pageTypes) {
            foreach ($pageTypes as $pt) {
                $isActive = ($currentPageType === $pt) || ($currentPageType === null && $pt === PlayerPageType::OVERVIEW);
                if ($isActive) {
                    $activeGroupName = $groupName;
                    break 2;
                }
            }
        }

        // Team color CSS custom properties for accent coloring
        $primaryColor = $colorScheme['primary'] ?? '#f97316';
        $primaryDark = '#' . TeamColorHelper::darken($colorScheme['primary'] ?? 'f97316', 15);

        ob_start();
        ?>
<tr>
    <td colspan="2">
        <nav class="plr-nav" style="--plr-nav-accent: <?= $primaryColor ?>; --plr-nav-accent-dark: <?= $primaryDark ?>;" aria-label="Player page navigation">
            <?php foreach ($groups as $groupName => $pageTypes):
                $groupNameEscaped = \Utilities\HtmlSanitizer::safeHtmlOutput($groupName);
            ?>
            <div class="plr-nav__group">
                <span class="plr-nav__group-label"><?= $groupNameEscaped ?></span>
                <div class="plr-nav__pills">
                    <?php foreach ($pageTypes as $pageType):
                        $labelKey = self::getLabelKey($pageType);
                        $label = $shortLabels[$labelKey] ?? PlayerPageType::getDescription($pageType);
                        $url = PlayerPageType::getUrl($playerID, $pageType);
                        $isActive = ($currentPageType === $pageType) || ($currentPageType === null && $pageType === PlayerPageType::OVERVIEW);
                        $labelEscaped = \Utilities\HtmlSanitizer::safeHtmlOutput($label);
                    ?>
                    <a href="<?= $url ?>" class="plr-nav__pill<?= $isActive ? ' plr-nav__pill--active' : '' ?>"><?= $labelEscaped ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <select class="plr-nav__mobile-select" onchange="if(this.value) window.location.href=this.value;" aria-label="Navigate player pages">
                <?php foreach ($groups as $groupName => $pageTypes):
                    $groupNameEscaped2 = \Utilities\HtmlSanitizer::safeHtmlOutput($groupName);
                ?>
                <optgroup label="<?= $groupNameEscaped2 ?>">
                    <?php foreach ($pageTypes as $pageType):
                        $label = PlayerPageType::getDescription($pageType);
                        $url = PlayerPageType::getUrl($playerID, $pageType);
                        $isActive = ($currentPageType === $pageType) || ($currentPageType === null && $pageType === PlayerPageType::OVERVIEW);
                        $labelEscaped2 = \Utilities\HtmlSanitizer::safeHtmlOutput($label);
                    ?>
                    <option value="<?= $url ?>" <?= $isActive ? 'selected' : '' ?>><?= $labelEscaped2 ?></option>
                    <?php endforeach; ?>
                </optgroup>
                <?php endforeach; ?>
            </select>
        </nav>
    </td>
</tr>
        <?php
        return (string) ob_get_clean();
    }
}
