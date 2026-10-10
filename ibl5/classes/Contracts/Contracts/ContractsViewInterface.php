<?php

declare(strict_types=1);

namespace Contracts\Contracts;

/**
 * ContractsViewInterface - Contract for the tabbed Contracts page wrapper
 *
 * @see \Contracts\ContractsView For the concrete implementation
 */
interface ContractsViewInterface
{
    /**
     * Wrap the active tab's already-rendered body in the Contracts tab nav
     *
     * @param string $activeTab Tab key; unknown values fall back to the default tab
     * @param string $tabContentHtml Pre-escaped HTML from the active tab's view
     * @return string HTML output
     */
    public function render(string $activeTab, string $tabContentHtml): string;
}
