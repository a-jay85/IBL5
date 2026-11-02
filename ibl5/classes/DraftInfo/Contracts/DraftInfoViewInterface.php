<?php

declare(strict_types=1);

namespace DraftInfo\Contracts;

/**
 * View interface for the Draft Info module.
 *
 * Wraps the already-rendered body of one tab in the Order / Pick ownership / History tab strip.
 */
interface DraftInfoViewInterface
{
    /**
     * Render the tab strip plus the already-rendered body of the active tab,
     * both inside one .ibl-tab-card wrapper.
     *
     * @param string $activeTab One of the keys of DraftInfoView::TABS (caller validates)
     * @param string $tabBodyHtml Pre-rendered HTML from the tab's existing view; embedded verbatim
     */
    public function render(string $activeTab, string $tabBodyHtml): string;

    /**
     * Render the History tab's nested League / team sub-tab strip plus its panel.
     *
     * League href has no view param; team href is `&teamid=<id>` when $otherTeamId is set,
     * else `&view=team`. $teamTabLabel is escaped; $panelHtml is embedded verbatim (caller escapes).
     *
     * @param string $activeSubTab DraftInfoView::SUBTAB_LEAGUE or DraftInfoView::SUBTAB_TEAM
     * @param string $teamTabLabel Label of the right sub-tab ("My team" or a team name)
     * @param int|null $otherTeamId Team id the right sub-tab links to, or null for the viewer's own team
     * @param string $panelHtml Pre-rendered HTML of the active sub-tab
     */
    public function renderHistorySubTabs(string $activeSubTab, string $teamTabLabel, ?int $otherTeamId, string $panelHtml): string;

    /**
     * Static login prompt for the team sub-tab when the viewer has no team.
     */
    public function renderTeamLoginPrompt(): string;
}
