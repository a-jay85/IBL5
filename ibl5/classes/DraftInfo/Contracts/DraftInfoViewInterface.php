<?php

declare(strict_types=1);

namespace DraftInfo\Contracts;

/**
 * View interface for the Draft Info module.
 *
 * Wraps the already-rendered body of one tab in the Order / Pick ownership / Past drafts tab strip.
 */
interface DraftInfoViewInterface
{
    /**
     * Render the tab strip plus the already-rendered body of the active tab.
     *
     * @param string $activeTab One of the keys of DraftInfoView::TABS (caller validates)
     * @param string $tabBodyHtml Pre-rendered HTML from the tab's existing view; embedded verbatim
     */
    public function render(string $activeTab, string $tabBodyHtml): string;
}
