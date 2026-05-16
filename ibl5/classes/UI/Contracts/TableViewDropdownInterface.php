<?php

declare(strict_types=1);

namespace UI\Contracts;

/**
 * TableViewDropdownInterface - Contract for dropdown select navigation for team page views
 */
interface TableViewDropdownInterface
{
    /**
     * Render the dropdown HTML
     */
    public function renderDropdown(): string;

    /**
     * Inject dropdown as a <caption> element inside the first <table> tag
     *
     * @param string $tableHtml Pre-rendered table HTML
     * @return string Table HTML with caption injected after opening <table> tag
     */
    public function wrap(string $tableHtml): string;
}
