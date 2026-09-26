<?php

declare(strict_types=1);

namespace Records;

use Security\HtmlSanitizer;

/**
 * RecordsController - Tab whitelist, tab bar, dispatch, and legacy redirects for the Records page
 *
 * Holds no DB handle, session, or actor identity. The Records entry point wires the
 * DB-backed services into the tab renderer closures; the legacy module entry points
 * call legacyRedirectUrl() before any DB work.
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

    /** @var array<string, \Closure(array<string, mixed>): string> */
    private array $tabRenderers;

    /**
     * @param array<string, \Closure(array<string, mixed>): string> $tabRenderers One renderer per TABS key
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
    }

    /**
     * Whitelist the raw `tab` query value; anything unrecognized falls back to DEFAULT_TAB
     */
    public static function resolveTab(mixed $rawTab): string
    {
        if (is_string($rawTab) && array_key_exists($rawTab, self::TABS)) {
            return $rawTab;
        }

        return self::DEFAULT_TAB;
    }

    public static function pageTitle(string $tab): string
    {
        return '- Records: ' . self::TABS[self::resolveTab($tab)];
    }

    /**
     * Render the standalone tab bar (same markup as TableViewSwitcher, without a wrapped table)
     */
    public function renderTabBar(string $activeTab): string
    {
        $activeTab = self::resolveTab($activeTab);

        $html = '<div class="ibl-tabs">';
        foreach (self::TABS as $tabKey => $tabLabel) {
            $isActive = $tabKey === $activeTab;
            $activeClass = $isActive ? ' ibl-tab--active' : '';
            $ariaCurrent = $isActive ? ' aria-current="page"' : '';
            $href = HtmlSanitizer::safeHtmlOutput('modules.php?name=Records&tab=' . $tabKey);
            $safeLabel = HtmlSanitizer::safeHtmlOutput($tabLabel);
            $safeKey = HtmlSanitizer::safeHtmlOutput($tabKey);

            $html .= '<a href="' . $href . '" class="ibl-tab' . $activeClass . '" data-tab="' . $safeKey . '"'
                . $ariaCurrent . '>' . $safeLabel . '</a>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * Render the tab bar plus the active tab's panel; only the active renderer runs
     *
     * @param array<string, mixed> $query
     */
    public function render(mixed $rawTab, array $query): string
    {
        $tab = self::resolveTab($rawTab);
        $safeTab = HtmlSanitizer::safeHtmlOutput($tab);

        return $this->renderTabBar($tab)
            . '<div class="records-page__panel" data-tab="' . $safeTab . '">'
            . ($this->tabRenderers[$tab])($query)
            . '</div>';
    }

    /**
     * Map a legacy module URL to its Records (or All-Star Appearances) replacement
     *
     * Returns null when the legacy request must keep being served (the FranchiseRecordBook
     * HTMX API fast path) or the module is not a legacy Records module.
     *
     * @param array<string, mixed> $query
     */
    public static function legacyRedirectUrl(string $legacyModule, array $query): ?string
    {
        switch ($legacyModule) {
            case 'RecordHolders':
                if (($query['op'] ?? null) === 'allstar') {
                    return 'modules.php?' . self::buildQuery(['name' => 'AllStarAppearances']);
                }
                return 'modules.php?' . self::buildQuery(['name' => 'Records', 'tab' => self::TAB_ALLTIME]);

            case 'FranchiseRecordBook':
                if (($query['op'] ?? null) === 'api') {
                    return null;
                }
                $params = ['name' => 'Records', 'tab' => self::TAB_BYFRANCHISE];
                $teamId = $query['teamid'] ?? null;
                if (is_string($teamId) && ctype_digit($teamId)) {
                    $params['teamid'] = (int) $teamId;
                }
                return 'modules.php?' . self::buildQuery($params);

            case 'SeasonHighs':
                $params = ['name' => 'Records', 'tab' => self::TAB_THISSEASON];
                $seasonPhase = $query['seasonPhase'] ?? null;
                if (is_string($seasonPhase) && $seasonPhase !== '') {
                    $params['seasonPhase'] = $seasonPhase;
                }
                return 'modules.php?' . self::buildQuery($params);

            default:
                return null;
        }
    }

    /**
     * @param array<string, string|int> $params
     */
    private static function buildQuery(array $params): string
    {
        return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}
