<?php

declare(strict_types=1);

namespace Records;

use UI\Components\PageTabs;

/**
 * RecordsController - Tab whitelist, tab bar, dispatch for the Records page
 *
 * Holds no DB handle, session, or actor identity. The Records entry point wires the
 * DB-backed services into the tab renderer closures.
 */
final class RecordsController
{
    public const TAB_ALLTIME = 'alltime';
    public const TAB_BYFRANCHISE = 'byfranchise';
    public const TAB_THISSEASON = 'thisseason';
    public const DEFAULT_TAB = self::TAB_ALLTIME;

    /** @var array<string, string> tab key => label, in display order */
    public const TABS = [
        self::TAB_ALLTIME => 'All-Time',
        self::TAB_BYFRANCHISE => 'By Franchise',
        self::TAB_THISSEASON => 'This Season',
    ];

    /** @var array<string, \Closure(array<mixed>): string> */
    private array $tabRenderers;

    private PageTabs $pageTabs;

    /**
     * @param array<string, \Closure(array<mixed>): string> $tabRenderers One renderer per TABS key
     * @throws \InvalidArgumentException When the renderer keys differ from the TABS keys
     */
    public function __construct(array $tabRenderers)
    {
        $given = array_keys($tabRenderers);
        $expected = array_keys(self::TABS);
        sort($given);
        sort($expected);
        if ($given !== $expected) {
            throw new \InvalidArgumentException(
                'Records tab renderers must cover exactly: ' . implode(', ', array_keys(self::TABS))
            );
        }

        $this->tabRenderers = $tabRenderers;
        $this->pageTabs = new PageTabs(self::TABS, self::DEFAULT_TAB);
    }

    /**
     * Whitelist the raw `tab` query value; anything unrecognized falls back to DEFAULT_TAB
     */
    public static function resolveTab(mixed $rawTab): string
    {
        return (new PageTabs(self::TABS, self::DEFAULT_TAB))->resolve($rawTab);
    }

    public static function pageTitle(string $tab): string
    {
        return '- Records: ' . self::TABS[self::resolveTab($tab)];
    }

    /**
     * Render the tab bar plus the active tab's panel; only the active renderer runs
     *
     * @param array<mixed> $query
     */
    public function render(mixed $rawTab, array $query): string
    {
        $tab = self::resolveTab($rawTab);

        return $this->pageTabs->renderTabBar($tab, 'modules.php?name=Records')
            . $this->pageTabs->wrapPanel(($this->tabRenderers[$tab])($query), $tab);
    }
}
