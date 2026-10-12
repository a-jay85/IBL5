<?php

declare(strict_types=1);

namespace UI\Contracts;

/**
 * PageTabsInterface - Contract for query-string tab navigation on a single page
 *
 * Implementations hold a fixed tab map (key => label) and a default key. Every
 * key, label, and href they emit must pass through HtmlSanitizer::safeHtmlOutput().
 */
interface PageTabsInterface
{
    /**
     * Resolve a raw request value to a known tab key
     *
     * Returns $raw only when it is a string that exactly matches a key of the tab map.
     * Any other input (null, int, array, empty string, unknown key, case mismatch)
     * returns the default tab key.
     *
     * @param mixed $raw Untrusted value, typically $_GET['tab'] ?? null
     * @return string A key guaranteed to exist in the tab map
     */
    public function resolve(mixed $raw): string;

    /**
     * Render the tab bar as a <div class="ibl-tabs"> of <a class="ibl-tab"> links
     *
     * Each link's href is $baseUrl plus a tab=<key> query parameter. The link whose key
     * equals $activeTab gets the ibl-tab--active class and aria-current="page". Every
     * link carries data-tab="<key>". All keys, labels, and hrefs are HTML-escaped.
     *
     * @param string $activeTab Tab key to mark active (pass the result of resolve())
     * @param string $baseUrl Page URL the tab parameter is appended to, e.g. "modules.php?name=Records"
     * @return string Tab bar HTML
     */
    public function renderTabBar(string $activeTab, string $baseUrl): string;

    /**
     * Wrap pre-rendered panel content in <div class="ibl-tab-panel" data-tab="<key>">
     *
     * $content is trusted, already-escaped HTML and is emitted unchanged. $tabKey is escaped.
     *
     * @param string $content Pre-rendered HTML for the active panel
     * @param string $tabKey Tab key the panel belongs to
     * @return string Panel HTML
     */
    public function wrapPanel(string $content, string $tabKey): string;
}
