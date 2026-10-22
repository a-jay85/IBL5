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
     * Render the tab bar plus the active tab's panel, both inside one .ibl-tab-card
     * wrapper; only the active renderer runs
     *
     * @param array<mixed> $query
     */
    public function render(mixed $rawTab, array $query): string
    {
        $tab = self::resolveTab($rawTab);

        // .ibl-tab-card: one white card with the tab strip as its header (hub-only styling)
        return '<div class="ibl-tab-card">'
            . $this->pageTabs->renderTabBar($tab, 'modules.php?name=Records')
            . $this->pageTabs->wrapPanel(($this->tabRenderers[$tab])($query), $tab)
            . '</div>';
    }

    /**
     * Map a retired module plus its query to the Records-hub URL, or null when the
     * module has nothing to redirect (unknown module, or the HTMX `op=api` fast path).
     *
     * @param array<array-key, mixed> $query
     */
    public static function legacyRedirectUrl(string $legacyModule, array $query): ?string
    {
        $records = 'modules.php?name=Records&tab=';

        switch ($legacyModule) {
            case 'RecordHolders':
                if (($query['op'] ?? null) === 'allstar') {
                    return 'modules.php?name=AllStarAppearances';
                }
                return $records . self::TAB_ALLTIME;

            case 'FranchiseRecordBook':
                if (($query['op'] ?? null) === 'api') {
                    return null;
                }
                $teamId = $query['teamid'] ?? null;
                if (is_string($teamId) && ctype_digit($teamId)) {
                    return $records . self::TAB_BYFRANCHISE
                        . '&' . http_build_query(['teamid' => (int) $teamId], '', '&', PHP_QUERY_RFC3986);
                }
                return $records . self::TAB_BYFRANCHISE;

            case 'SeasonHighs':
                $phase = $query['seasonPhase'] ?? null;
                if (is_string($phase) && $phase !== '') {
                    return $records . self::TAB_THISSEASON
                        . '&' . http_build_query(['seasonPhase' => $phase], '', '&', PHP_QUERY_RFC3986);
                }
                return $records . self::TAB_THISSEASON;

            default:
                return null;
        }
    }
}
