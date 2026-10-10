<?php

declare(strict_types=1);

namespace DraftInfo;

use DraftInfo\Contracts\DraftInfoViewInterface;
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

    /** @var array<string, string> tab key => tab label, in render order */
    public const TABS = [
        self::TAB_ORDER => 'Order',
        self::TAB_PICKS => 'Pick ownership',
        self::TAB_HISTORY => 'Past drafts',
    ];

    private const BASE_URL = 'modules.php?name=DraftInfo';

    /**
     * @see DraftInfoViewInterface::render()
     */
    public function render(string $activeTab, string $tabBodyHtml): string
    {
        $tabs = new PageTabs(self::TABS, self::DEFAULT_TAB);

        return $tabs->renderTabBar($activeTab, self::BASE_URL)
            . $tabs->wrapPanel($tabBodyHtml, $activeTab);
    }
}
