<?php

declare(strict_types=1);

namespace UI\Contracts;

/**
 * TableViewSwitcherInterface - Contract for tab navigation switching table display views
 */
interface TableViewSwitcherInterface
{
    /**
     * Inject tabs as a <caption> element inside the first <table> tag
     *
     * @param string $tableHtml Pre-rendered table HTML
     * @return string Table HTML with caption injected after opening <table> tag
     */
    public function wrap(string $tableHtml): string;

    /**
     * Render just the tabs HTML (for standalone placement above multiple tables)
     *
     * @return string HTML div containing tab links
     */
    public function renderTabs(): string;
}
