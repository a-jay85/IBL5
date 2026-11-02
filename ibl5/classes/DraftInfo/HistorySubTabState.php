<?php

declare(strict_types=1);

namespace DraftInfo;

use League\League;

/**
 * Pure decision for the Draft Info History tab's League / team sub-tabs.
 *
 * Holds no database handle: the caller passes the validated request inputs and the viewer's team id.
 */
final readonly class HistorySubTabState
{
    public function __construct(
        public string $activeSubTab,
        public string $teamTabLabel,
        public ?int $otherTeamId,
        public ?int $teamIdToRender,
        public bool $showLoginPrompt,
    ) {
    }

    /**
     * A valid requested team wins over the view param; view=team shows the viewer's own team
     * (or the login prompt); anything else is the League sub-tab.
     */
    public static function resolve(?int $requestedTeamId, ?string $requestedTeamName, string $view, ?int $viewerTeamId): self
    {
        if ($requestedTeamId !== null && $requestedTeamName !== null) {
            $isOwn = $viewerTeamId === $requestedTeamId;
            return new self(
                DraftInfoView::SUBTAB_TEAM,
                $isOwn ? DraftInfoView::MY_TEAM_LABEL : $requestedTeamName,
                $isOwn ? null : $requestedTeamId,
                $requestedTeamId,
                false
            );
        }

        if ($view === DraftInfoView::SUBTAB_TEAM) {
            return $viewerTeamId !== null
                ? new self(DraftInfoView::SUBTAB_TEAM, DraftInfoView::MY_TEAM_LABEL, null, $viewerTeamId, false)
                : new self(DraftInfoView::SUBTAB_TEAM, DraftInfoView::MY_TEAM_LABEL, null, null, true);
        }

        return new self(DraftInfoView::SUBTAB_LEAGUE, DraftInfoView::MY_TEAM_LABEL, null, null, false);
    }

    /**
     * @param \Closure(string): ?string $teamnameFromUsername
     * @param \Closure(string): ?int $tidFromTeamname
     */
    public static function resolveViewerTeamId(?string $username, \Closure $teamnameFromUsername, \Closure $tidFromTeamname): ?int
    {
        if ($username === null || $username === '') {
            return null;
        }

        $teamName = $teamnameFromUsername($username);
        if ($teamName === null || $teamName === '' || $teamName === League::FREE_AGENTS_TEAM_NAME) {
            return null;
        }

        $tid = $tidFromTeamname($teamName);
        return ($tid !== null && $tid > 0) ? $tid : null;
    }
}
