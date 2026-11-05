<?php

declare(strict_types=1);

namespace UI\Components;

use Security\HtmlSanitizer;
use UI\Contracts\PageTabsInterface;

/**
 * PageTabs - page-level tab bar and panel wrapper for multi-view module pages
 */
final class PageTabs implements PageTabsInterface
{
    /**
     * @param array<array-key, string> $tabs Tab key => display label, in render order
     */
    public function __construct(
        private readonly array $tabs,
        private readonly string $defaultTab,
    ) {}

    public function resolve(mixed $raw): string
    {
        if (is_string($raw) && array_key_exists($raw, $this->tabs)) {
            return $raw;
        }
        return $this->defaultTab;
    }

    public function renderTabBar(string $activeTab, string $baseUrl): string
    {
        $items = [];
        foreach ($this->tabs as $tabKey => $label) {
            $key = (string) $tabKey;
            $items[] = ['key' => $key, 'label' => $label, 'href' => $baseUrl . '&tab=' . rawurlencode($key)];
        }
        return self::renderLinkStrip($items, $activeTab);
    }

    /**
     * Render a tab-styled link strip with caller-supplied hrefs.
     * Same markup and aria-current pattern as renderTabBar(); used for nested sub-tabs.
     *
     * @param list<array{key: string, label: string, href: string}> $items in render order
     * @param string $modifierClass extra class appended to "ibl-tabs" (e.g. "ibl-tabs--sub"); '' for none
     */
    public static function renderLinkStrip(array $items, string $activeKey, string $modifierClass = ''): string
    {
        $wrapperClass = $modifierClass === '' ? 'ibl-tabs' : 'ibl-tabs ' . $modifierClass;
        $html = '<div class="' . HtmlSanitizer::safeHtmlOutput($wrapperClass) . '">';
        foreach ($items as $item) {
            $isActive = $item['key'] === $activeKey;
            $class = $isActive ? 'ibl-tab ibl-tab--active' : 'ibl-tab';
            $html .= '<a class="' . $class . '"'
                . ' href="' . HtmlSanitizer::safeHtmlOutput($item['href']) . '"'
                . ' data-tab="' . HtmlSanitizer::safeHtmlOutput($item['key']) . '"'
                . ($isActive ? ' aria-current="page"' : '')
                . '>' . HtmlSanitizer::safeHtmlOutput($item['label']) . '</a>';
        }
        return $html . '</div>';
    }

    public function wrapPanel(string $content, string $tabKey): string
    {
        return '<div class="ibl-tab-panel" data-tab="'
            . HtmlSanitizer::safeHtmlOutput($tabKey) . '">'
            . $content . '</div>';
    }
}
