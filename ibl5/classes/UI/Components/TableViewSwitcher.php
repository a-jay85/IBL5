<?php

declare(strict_types=1);

namespace UI\Components;

use Utilities\HtmlSanitizer;

/**
 * TableViewSwitcher - Reusable tab navigation for switching table display views
 *
 * Renders tab navigation (Ratings, Season Totals, etc.) and optionally injects
 * it as a <caption> element inside a table's HTML.
 *
 * Two usage patterns:
 * - Single table: $switcher->wrap($tableHtml) — tabs injected as <caption>
 * - Multiple tables: $switcher->renderTabs() — rendered standalone above all tables
 */
class TableViewSwitcher
{
    /** @var array<string, string> Tab keys mapped to display labels */
    private array $tabs;

    /** @var string Currently active tab key */
    private string $activeTab;

    /** @var string Base URL with plain & (HTML-encoded on output) */
    private string $baseUrl;

    /** @var string Primary team hex color (without #) */
    private string $color1;

    /** @var string Secondary team hex color (without #) */
    private string $color2;

    /**
     * @param array<string, string> $tabs Tab keys mapped to display labels
     * @param string $activeTab Currently active tab key
     * @param string $baseUrl Plain URL with & separators (not &amp;)
     * @param string $color1 Primary team hex color (without #)
     * @param string $color2 Secondary team hex color (without #)
     */
    public function __construct(
        array $tabs,
        string $activeTab,
        string $baseUrl,
        string $color1,
        string $color2
    ) {
        $this->tabs = $tabs;
        $this->activeTab = $activeTab;
        $this->baseUrl = $baseUrl;
        $this->color1 = \UI\TableStyles::sanitizeColor($color1);
        $this->color2 = \UI\TableStyles::sanitizeColor($color2);
    }

    /**
     * Inject tabs as a <caption> element inside the first <table> tag
     *
     * @param string $tableHtml Pre-rendered table HTML
     * @return string Table HTML with caption injected after opening <table> tag
     */
    public function wrap(string $tableHtml): string
    {
        $tabsHtml = $this->renderTabs();

        return $this->injectCaption($tableHtml, $tabsHtml);
    }

    /**
     * Render just the tabs HTML (for standalone placement above multiple tables)
     *
     * @return string HTML div containing tab links
     */
    public function renderTabs(): string
    {
        $tabLinks = '';
        foreach ($this->tabs as $tabKey => $tabLabel) {
            $tabLinks .= $this->buildTab($tabKey, $tabLabel);
        }

        return '<div class="ibl-tabs" style="--team-tab-bg-color: #' . $this->color1 . '; --team-tab-active-color: #' . $this->color2 . '">' . $tabLinks . '</div>';
    }

    /**
     * Build a single tab link
     */
    private function buildTab(string $tabKey, string $tabLabel): string
    {
        $activeClass = ($this->activeTab === $tabKey) ? ' ibl-tab--active' : '';
        /** @var string $href */
        $href = HtmlSanitizer::safeHtmlOutput($this->baseUrl . '&display=' . $tabKey);
        /** @var string $safeLabel */
        $safeLabel = HtmlSanitizer::safeHtmlOutput($tabLabel);

        /** @var string $safeKey */
        $safeKey = HtmlSanitizer::safeHtmlOutput($tabKey);

        return '<a href="' . $href . '" class="ibl-tab' . $activeClass . '" data-display="' . $safeKey . '">' . $safeLabel . '</a>';
    }

    /**
     * Inject tabs HTML as a <caption> element inside the table
     *
     * Inserts a <caption> immediately after the opening <table ...> tag
     * so tabs inherit the table's width by definition.
     */
    private function injectCaption(string $tableHtml, string $tabsHtml): string
    {
        $result = preg_replace(
            '/(<table\b[^>]*>)/i',
            '$1<caption class="team-table-caption">' . $tabsHtml . '</caption>',
            $tableHtml,
            1
        );

        return $result ?? $tableHtml;
    }
}
