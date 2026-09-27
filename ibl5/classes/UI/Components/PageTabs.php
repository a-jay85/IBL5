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
        $html = '<div class="ibl-tabs">';
        foreach ($this->tabs as $tabKey => $label) {
            $key = (string) $tabKey;
            $isActive = $key === $activeTab;
            $class = $isActive ? 'ibl-tab ibl-tab--active' : 'ibl-tab';
            $href = $baseUrl . '&tab=' . rawurlencode($key);
            $html .= '<a class="' . $class . '"'
                . ' href="' . HtmlSanitizer::safeHtmlOutput($href) . '"'
                . ' data-tab="' . HtmlSanitizer::safeHtmlOutput($key) . '"'
                . ($isActive ? ' aria-current="page"' : '')
                . '>' . HtmlSanitizer::safeHtmlOutput($label) . '</a>';
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
