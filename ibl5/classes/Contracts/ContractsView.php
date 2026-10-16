<?php

declare(strict_types=1);

namespace Contracts;

use Contracts\Contracts\ContractsViewInterface;
use UI\Components\PageTabs;

/**
 * ContractsView - Tab nav wrapper for the Contracts page
 *
 * The Teams tab body comes from CapSpaceView and the Players tab body from
 * ContractListView. This view only adds the tab nav; it never touches the database.
 *
 * @see ContractsViewInterface For the interface contract
 */
class ContractsView implements ContractsViewInterface
{
    public const TAB_TEAMS = 'teams';
    public const TAB_PLAYERS = 'players';
    public const DEFAULT_TAB = self::TAB_TEAMS;

    /** @var array<string, string> tab key => label, in display order */
    public const TABS = [self::TAB_TEAMS => 'Teams', self::TAB_PLAYERS => 'Players'];

    private const BASE_URL = 'modules.php?name=Contracts';

    /**
     * Whitelist the raw ?tab= value; anything not a key of TABS falls back to DEFAULT_TAB.
     */
    public static function resolveTab(mixed $raw): string
    {
        return self::pageTabs()->resolve($raw);
    }

    /**
     * @see ContractsViewInterface::render()
     */
    public function render(string $activeTab, string $tabContentHtml): string
    {
        $tabs = self::pageTabs();
        $tab = $tabs->resolve($activeTab);
        return $tabs->renderTabBar($tab, self::BASE_URL) . $tabs->wrapPanel($tabContentHtml, $tab);
    }

    private static function pageTabs(): PageTabs
    {
        return new PageTabs(self::TABS, self::DEFAULT_TAB);
    }
}
