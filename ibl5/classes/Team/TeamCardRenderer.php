<?php

declare(strict_types=1);

namespace Team;

use Team\Views\AwardsView;
use Team\Views\BannersView;
use Team\Views\CurrentSeasonView;
use Team\Views\DraftPicksView;
use Team\Views\FranchiseHistoryView;
use Team\Views\SidebarView;

/**
 * Thin assembler that owns every `new XView()` call for the team page sidebar.
 *
 * Receives typed data from TeamPageDataPreparer and returns rendered HTML strings.
 * This is the only surviving site of View instantiation; no data-fetching occurs here.
 *
 * @phpstan-import-type BannerData from Contracts\TeamServiceInterface
 * @phpstan-import-type CurrentSeasonData from Contracts\TeamServiceInterface
 * @phpstan-import-type WinLossHistoryData from Contracts\TeamServiceInterface
 * @phpstan-import-type PlayoffData from Contracts\TeamServiceInterface
 * @phpstan-import-type DraftPickItemData from Contracts\TeamServiceInterface
 * @phpstan-import-type SidebarData from Contracts\TeamServiceInterface
 * @phpstan-import-type GMTenureRow from Contracts\TeamRepositoryInterface
 * @phpstan-import-type GMAwardRow from Contracts\TeamRepositoryInterface
 * @phpstan-import-type TeamAwardRow from Contracts\TeamRepositoryInterface
 *
 * @phpstan-type AwardsData array{tenures: list<GMTenureRow>, gmAwards: list<GMAwardRow>, teamAccomplishments: list<TeamAwardRow>}
 */
class TeamCardRenderer
{
    /**
     * Render all sidebar cards (rafters, current season, awards, franchise history).
     *
     * @param BannerData $bannerData
     * @param CurrentSeasonData|null $currentSeasonData
     * @param AwardsData $awardsData
     * @param WinLossHistoryData $regularSeasonData
     * @param WinLossHistoryData $heatData
     * @param PlayoffData $playoffData
     * @return SidebarData
     */
    public function renderSidebarCards(
        Team $team,
        array $bannerData,
        ?array $currentSeasonData,
        array $awardsData,
        array $regularSeasonData,
        array $heatData,
        array $playoffData,
    ): array {
        $teamColorStyle = \UI\TableStyles::inlineTeamVars($team->color1, $team->color2);
        $sidebarView = new SidebarView();

        $bannersView = new BannersView();
        $rafters = $bannersView->render($bannerData);

        $currentSeasonHtml = '';
        if ($currentSeasonData !== null) {
            $currentSeasonView = new CurrentSeasonView();
            $currentSeasonHtml = $currentSeasonView->render($currentSeasonData);
        }
        $currentSeasonCard = $sidebarView->renderCurrentSeasonCard($currentSeasonHtml, $teamColorStyle);

        $awardsRenderer = new AwardsView();
        $gmHistoryHtml = $awardsRenderer->renderGmHistory($awardsData['tenures'], $awardsData['gmAwards']);
        $teamAccomplishmentsHtml = $awardsRenderer->renderTeamAccomplishments($awardsData['teamAccomplishments']);
        $awardsCard = $sidebarView->renderAwardsCard($gmHistoryHtml, $teamAccomplishmentsHtml, $teamColorStyle);

        $franchiseHistoryView = new FranchiseHistoryView();
        $regularSeasonHtml = $franchiseHistoryView->renderRegularSeason($regularSeasonData);
        $heatHtml = $franchiseHistoryView->renderHeat($heatData);
        $playoffsHtml = $franchiseHistoryView->renderPlayoffs($playoffData);
        $franchiseHistoryCard = $sidebarView->renderFranchiseHistoryCard($heatHtml, $regularSeasonHtml, $playoffsHtml, $teamColorStyle);

        return [
            'currentSeasonCard' => $currentSeasonCard,
            'awardsCard' => $awardsCard,
            'franchiseHistoryCard' => $franchiseHistoryCard,
            'rafters' => $rafters,
        ];
    }

    /**
     * Render the draft picks table.
     *
     * @param list<DraftPickItemData> $draftPicks
     */
    public function renderDraftPicksTable(array $draftPicks): string
    {
        return (new DraftPicksView())->render($draftPicks);
    }
}
