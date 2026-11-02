<?php

declare(strict_types=1);

namespace DraftInfo;

use DraftInfo\Contracts\DraftInfoViewInterface;
use Security\HtmlSanitizer;
use UI\Components\PageTabs;

/**
 * @see DraftInfoViewInterface
 */
final class DraftInfoView implements DraftInfoViewInterface
{
    public const TAB_ORDER = 'order';
    public const TAB_PICKS = 'picks';
    public const TAB_HISTORY = 'history';
    public const DEFAULT_TAB = self::TAB_ORDER;

    public const SUBTAB_LEAGUE = 'league';
    public const SUBTAB_TEAM = 'team';
    public const MY_TEAM_LABEL = 'My team';
    public const HISTORY_URL = 'modules.php?name=DraftInfo&tab=history';

    /** @var array<string, string> tab key => tab label, in render order */
    public const TABS = [
        self::TAB_ORDER => 'Order',
        self::TAB_PICKS => 'Pick ownership',
        self::TAB_HISTORY => 'History',
    ];

    private const BASE_URL = 'modules.php?name=DraftInfo';

    /**
     * @see DraftInfoViewInterface::render()
     */
    public function render(string $activeTab, string $tabBodyHtml): string
    {
        $tabs = new PageTabs(self::TABS, self::DEFAULT_TAB);

        // .ibl-tab-card: one white card with the tab strip as its header (hub-only styling)
        return '<div class="ibl-tab-card">'
            . $tabs->renderTabBar($activeTab, self::BASE_URL)
            . $tabs->wrapPanel($tabBodyHtml, $activeTab)
            . '</div>';
    }

    /**
     * @see DraftInfoViewInterface::renderHistorySubTabs()
     */
    public function renderHistorySubTabs(string $activeSubTab, string $teamTabLabel, ?int $otherTeamId, string $panelHtml): string
    {
        $teamHref = $otherTeamId !== null
            ? self::HISTORY_URL . '&teamid=' . $otherTeamId
            : self::HISTORY_URL . '&view=' . self::SUBTAB_TEAM;
        $items = [
            ['key' => self::SUBTAB_LEAGUE, 'label' => 'League', 'href' => self::HISTORY_URL],
            ['key' => self::SUBTAB_TEAM, 'label' => $teamTabLabel, 'href' => $teamHref],
        ];
        return PageTabs::renderLinkStrip($items, $activeSubTab, 'ibl-tabs--sub')
            . '<div class="ibl-subtab-panel" data-subtab="' . HtmlSanitizer::e($activeSubTab) . '">'
            . $panelHtml . '</div>';
    }

    /**
     * @see DraftInfoViewInterface::renderTeamLoginPrompt()
     */
    public function renderTeamLoginPrompt(): string
    {
        return '<p class="ibl-subtab-login-prompt">'
            . '<a href="modules.php?name=YourAccount">Log in</a>'
            . ' to see your team&#039;s draft history.</p>';
    }
}
