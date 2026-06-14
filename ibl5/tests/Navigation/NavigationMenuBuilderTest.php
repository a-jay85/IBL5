<?php

declare(strict_types=1);

namespace Tests\Navigation;

use Navigation\NavigationConfig;
use Navigation\NavigationMenuBuilder;
use PHPUnit\Framework\TestCase;

class NavigationMenuBuilderTest extends TestCase
{
    private function createConfig(
        bool $isLoggedIn = true,
        ?string $username = 'TestUser',
        string $currentLeague = 'ibl',
        ?int $teamId = 1,
        string $seasonPhase = 'Regular Season',
        string $allowWaivers = 'No',
        string $showDraftLink = 'Off',
        bool $isDraftOrderFinalized = false,
        bool $isAdmin = false,
    ): NavigationConfig {
        return new NavigationConfig(
            isLoggedIn: $isLoggedIn,
            username: $username,
            currentLeague: $currentLeague,
            teamId: $teamId,
            seasonPhase: $seasonPhase,
            allowWaivers: $allowWaivers,
            showDraftLink: $showDraftLink,
            isDraftOrderFinalized: $isDraftOrderFinalized,
            isAdmin: $isAdmin,
        );
    }

    // --- Menu Structure Tests ---

    public function testMenuStructureContainsFourMenus(): void
    {
        $builder = new NavigationMenuBuilder($this->createConfig());
        $menus = $builder->getMenuStructure();

        $this->assertCount(4, $menus);
        $this->assertArrayHasKey('Season', $menus);
        $this->assertArrayHasKey('Stats', $menus);
        $this->assertArrayHasKey('History', $menus);
        $this->assertArrayHasKey('Community', $menus);
    }

    public function testMenuStructureHasIconsAndLinks(): void
    {
        $builder = new NavigationMenuBuilder($this->createConfig());
        $menus = $builder->getMenuStructure();

        foreach ($menus as $name => $menu) {
            $this->assertArrayHasKey('icon', $menu, "Menu '$name' should have an icon");
            $this->assertArrayHasKey('links', $menu, "Menu '$name' should have links");
            $this->assertNotEmpty($menu['links'], "Menu '$name' should have at least one link");
        }
    }

    public function testGmContactListVisibleWhenLoggedInWithTeam(): void
    {
        $builder = new NavigationMenuBuilder($this->createConfig(isLoggedIn: true, teamId: 5));
        $menus = $builder->getMenuStructure();

        $communityLabels = array_map(
            static fn (array $link): string => $link['label'] ?? '',
            $menus['Community']['links']
        );

        $this->assertContains('GM Contact List', $communityLabels);
    }

    public function testGmContactListHiddenWhenLoggedOut(): void
    {
        $builder = new NavigationMenuBuilder($this->createConfig(isLoggedIn: false, username: null, teamId: null));
        $menus = $builder->getMenuStructure();

        $communityLabels = array_map(
            static fn (array $link): string => $link['label'] ?? '',
            $menus['Community']['links']
        );

        $this->assertNotContains('GM Contact List', $communityLabels);
    }

    // --- My Team Menu Tests ---

    public function testMyTeamMenuNullWhenLoggedOut(): void
    {
        $builder = new NavigationMenuBuilder($this->createConfig(isLoggedIn: false, username: null, teamId: null));
        $this->assertNull($builder->getMyTeamMenu());
    }

    public function testMyTeamMenuNullWhenNoTeam(): void
    {
        $builder = new NavigationMenuBuilder($this->createConfig(isLoggedIn: true, teamId: null));
        $this->assertNull($builder->getMyTeamMenu());
    }

    public function testVotingResultsLinkVisibleForAdminAndPositionedAfterVoting(): void
    {
        $builder = new NavigationMenuBuilder($this->createConfig(isLoggedIn: true, teamId: 5, isAdmin: true));
        $menu = $builder->getMyTeamMenu();
        $this->assertNotNull($menu);

        $labels = array_map(
            static fn (array $link): string => $link['label'] ?? '',
            $menu['links']
        );

        $votingResults = array_filter(
            $menu['links'],
            static fn (array $link): bool => ($link['label'] ?? '') === 'Voting Results'
        );
        $this->assertCount(1, $votingResults, 'Voting Results link should be present for admins');
        $this->assertSame(
            'modules.php?name=VotingResults',
            array_values($votingResults)[0]['url'] ?? null
        );

        // Relative-position assertion: the Draft/Free Agency branches array_unshift()
        // (prepend), so absolute indices shift — only the relative ordering is invariant.
        $votingIdx = array_search('Voting', $labels, true);
        $votingResultsIdx = array_search('Voting Results', $labels, true);
        $draftHistoryIdx = array_search('Draft History', $labels, true);
        $this->assertIsInt($votingIdx);
        $this->assertIsInt($votingResultsIdx);
        $this->assertIsInt($draftHistoryIdx);
        $this->assertSame($votingIdx + 1, $votingResultsIdx, 'Voting Results should sit directly after Voting');
        $this->assertSame($votingResultsIdx + 1, $draftHistoryIdx, 'Voting Results should sit directly before Draft History');
    }

    public function testVotingResultsLinkHiddenForNonAdmin(): void
    {
        $builder = new NavigationMenuBuilder($this->createConfig(isLoggedIn: true, teamId: 5, isAdmin: false));
        $menu = $builder->getMyTeamMenu();
        $this->assertNotNull($menu);

        $labels = array_map(
            static fn (array $link): string => $link['label'] ?? '',
            $menu['links']
        );

        // The menu still renders (Voting present); only the admin-gated link is hidden.
        $this->assertContains('Voting', $labels);
        $this->assertNotContains('Voting Results', $labels);
    }

    public function testVotingResultsLinkAbsentInOlympicsTeamMenuForAdmin(): void
    {
        $builder = new NavigationMenuBuilder($this->createConfig(currentLeague: 'olympics', isAdmin: true));
        $menu = $builder->getMyTeamMenu();
        $this->assertNotNull($menu);

        $labels = array_map(
            static fn (array $link): string => $link['label'] ?? '',
            $menu['links']
        );

        $this->assertNotContains('Voting Results', $labels);
    }

    public function testIblTeamMenuIncludesMyDashboard(): void
    {
        $builder = new NavigationMenuBuilder($this->createConfig(isLoggedIn: true, teamId: 1));
        $menu = $builder->getMyTeamMenu();
        $this->assertNotNull($menu);

        $dashboardLinks = array_filter(
            $menu['links'],
            static fn (array $link): bool => ($link['url'] ?? '') === 'modules.php?name=GMDashboard'
        );
        $this->assertCount(1, $dashboardLinks, 'My Dashboard link should be present in the IBL team menu');
        $this->assertSame('My Dashboard', array_values($dashboardLinks)[0]['label'] ?? null);
    }

    public function testMyDashboardAbsentFromOlympicsTeamMenu(): void
    {
        $builder = new NavigationMenuBuilder($this->createConfig(currentLeague: 'olympics'));
        $menu = $builder->getMyTeamMenu();
        $this->assertNotNull($menu);

        $urls = array_map(
            static fn (array $link): string => $link['url'] ?? '',
            $menu['links']
        );
        $this->assertNotContains('modules.php?name=GMDashboard', $urls);
    }

    public function testMyDashboardAbsentWhenLoggedOut(): void
    {
        $builder = new NavigationMenuBuilder($this->createConfig(isLoggedIn: false, username: null, teamId: null));
        $this->assertNull($builder->getMyTeamMenu());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('draftLinkProvider')]
    public function testDraftLinkBehavior(string $seasonPhase, string $showDraftLink, bool $expectDraftLink, ?string $expectedBadge): void
    {
        $builder = new NavigationMenuBuilder($this->createConfig(
            seasonPhase: $seasonPhase,
            showDraftLink: $showDraftLink,
        ));

        $menu = $builder->getMyTeamMenu();
        $this->assertNotNull($menu);

        $draftLinks = array_filter(
            $menu['links'],
            static fn (array $link): bool => ($link['label'] ?? '') === 'Draft'
        );

        if ($expectDraftLink) {
            $this->assertNotEmpty($draftLinks, 'Draft link should be present');
            $draftLink = array_values($draftLinks)[0];
            if ($expectedBadge !== null) {
                $this->assertSame($expectedBadge, $draftLink['badge'] ?? null);
            } else {
                $this->assertArrayNotHasKey('badge', $draftLink);
            }
        } else {
            $this->assertEmpty($draftLinks, 'Draft link should not be present');
        }
    }

    /**
     * @return array<string, array{string, string, bool, string|null}>
     */
    public static function draftLinkProvider(): array
    {
        return [
            'Draft phase, toggle Off' => ['Draft', 'Off', true, 'LIVE'],
            'Draft phase, toggle On' => ['Draft', 'On', true, 'LIVE'],
            'Regular Season, toggle On' => ['Regular Season', 'On', true, null],
            'Regular Season, toggle Off' => ['Regular Season', 'Off', false, null],
            'Playoffs, toggle On' => ['Playoffs', 'On', true, null],
        ];
    }

    public function testFreeAgencyLinkDuringFaPhase(): void
    {
        $builder = new NavigationMenuBuilder($this->createConfig(seasonPhase: 'Free Agency'));
        $menu = $builder->getMyTeamMenu();
        $this->assertNotNull($menu);

        $faLinks = array_filter(
            $menu['links'],
            static fn (array $link): bool => ($link['label'] ?? '') === 'Free Agency'
        );

        $this->assertNotEmpty($faLinks, 'Free Agency link should be present');
        $faLink = array_values($faLinks)[0];
        $this->assertSame('LIVE', $faLink['badge'] ?? null);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('waiversLinkProvider')]
    public function testWaiversLinkBehavior(string $seasonPhase, string $allowWaivers, bool $expectWaiversLink): void
    {
        $builder = new NavigationMenuBuilder($this->createConfig(
            seasonPhase: $seasonPhase,
            allowWaivers: $allowWaivers,
        ));

        $menu = $builder->getMyTeamMenu();
        $this->assertNotNull($menu);

        $waiversLinks = array_filter(
            $menu['links'],
            static fn (array $link): bool => str_contains($link['rawHtml'] ?? '', 'Waivers')
        );

        if ($expectWaiversLink) {
            $this->assertNotEmpty($waiversLinks, "Waivers link should be present during $seasonPhase");
        } else {
            $this->assertEmpty($waiversLinks, "Waivers link should not be present during $seasonPhase");
        }
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function waiversLinkProvider(): array
    {
        return [
            'HEAT, toggle off — always on' => ['HEAT', 'No', true],
            'HEAT, toggle on — always on' => ['HEAT', 'Yes', true],
            'Regular Season, toggle off — always on' => ['Regular Season', 'No', true],
            'Regular Season, toggle on — always on' => ['Regular Season', 'Yes', true],
            'Playoffs, toggle off — always on' => ['Playoffs', 'No', true],
            'Draft, toggle off — never' => ['Draft', 'No', false],
            'Draft, toggle on — never' => ['Draft', 'Yes', false],
            'Free Agency, toggle off — toggle-dependent' => ['Free Agency', 'No', false],
            'Free Agency, toggle on — toggle-dependent' => ['Free Agency', 'Yes', true],
            'Preseason, toggle off — toggle-dependent' => ['Preseason', 'No', false],
            'Preseason, toggle on — toggle-dependent' => ['Preseason', 'Yes', true],
        ];
    }

    public function testOlympicsVariant(): void
    {
        $builder = new NavigationMenuBuilder($this->createConfig(currentLeague: 'olympics'));
        $menu = $builder->getMyTeamMenu();
        $this->assertNotNull($menu);

        $labels = array_map(
            static fn (array $link): string => $link['label'] ?? '',
            $menu['links']
        );

        $this->assertContains('Depth Chart Entry', $labels);
        $this->assertContains('Activity Tracker', $labels);
        $this->assertNotContains('Trading', $labels);
    }

    public function testIblModeMenuStructureContainsAllSeasonLinks(): void
    {
        $builder = new NavigationMenuBuilder($this->createConfig(currentLeague: 'ibl'));
        $menus = $builder->getMenuStructure();

        $seasonLabels = array_map(
            static fn (array $link): string => $link['label'] ?? '',
            $menus['Season']['links']
        );

        $expectedSeasonLinks = [
            'Standings', 'Schedule', 'Injuries', 'Player Database', 'Player Export',
            'Cap Space', 'Draft Pick Locator', 'Training Camp Ratings Diff',
            'Free Agency Preview', 'Contract List', 'Player Movement',
        ];
        foreach ($expectedSeasonLinks as $label) {
            $this->assertContains($label, $seasonLabels, "Season menu should contain '$label'");
        }

        $jsbExport = array_filter(
            $menus['Season']['links'],
            static fn (array $link): bool => ($link['label'] ?? '') === 'JSB Export'
        );
        $this->assertNotEmpty($jsbExport, 'Season menu should contain JSB Export');
        $jsbLink = array_values($jsbExport)[0];
        $this->assertTrue($jsbLink['external'] ?? false, 'JSB Export should be external');

        $historyLabels = array_map(
            static fn (array $link): string => $link['label'] ?? '',
            $menus['History']['links']
        );

        $expectedHistoryLinks = [
            'Franchise History', 'Draft History', 'All-Star Appearances', '1-On-1 Game',
        ];
        foreach ($expectedHistoryLinks as $label) {
            $this->assertContains($label, $historyLabels, "History menu should contain '$label'");
        }
    }

    public function testOlympicsModeFiltersIblOnlyLinksFromMenuStructure(): void
    {
        $builder = new NavigationMenuBuilder($this->createConfig(currentLeague: 'olympics'));
        $menus = $builder->getMenuStructure();

        $allLabels = [];
        foreach ($menus as $menu) {
            foreach ($menu['links'] as $link) {
                $allLabels[] = $link['label'] ?? ($link['rawHtml'] ?? '');
            }
        }

        $shouldBeAbsent = [
            'Cap Space', 'Projected Draft Order', 'Draft Pick Locator',
            'Training Camp Ratings Diff', 'Free Agency Preview', 'Contract List',
            'Player Movement', 'Franchise History', 'Draft History',
            'All-Star Appearances', '1-On-1 Game', 'JSB Export',
            'Award History', 'Record Holders', 'Season Leaderboards',
            'Career Leaderboards', 'Franchise Record Book',
        ];
        foreach ($shouldBeAbsent as $label) {
            $this->assertNotContains($label, $allLabels, "'$label' should be filtered in Olympics mode");
        }

        $shouldBePresent = [
            'Standings', 'Schedule', 'Injuries', 'Player Database', 'Player Export',
            'Season Archive',
        ];
        foreach ($shouldBePresent as $label) {
            $this->assertContains($label, $allLabels, "'$label' should remain in Olympics mode");
        }
    }

    // --- Draft Order Finalization Tests ---

    public function testProjectedDraftOrderLabelWhenNotFinalized(): void
    {
        $builder = new NavigationMenuBuilder($this->createConfig(isDraftOrderFinalized: false));
        $menus = $builder->getMenuStructure();

        $seasonLinks = $menus['Season']['links'];
        $draftOrderLink = null;
        foreach ($seasonLinks as $link) {
            if (str_contains($link['url'] ?? '', 'ProjectedDraftOrder')) {
                $draftOrderLink = $link;
                break;
            }
        }

        $this->assertNotNull($draftOrderLink);
        $this->assertSame('Projected Draft Order', $draftOrderLink['label']);
        $this->assertArrayNotHasKey('badge', $draftOrderLink);
    }

    public function testDraftOrderLabelWithFinalBadgeWhenFinalizedDuringDraft(): void
    {
        $builder = new NavigationMenuBuilder($this->createConfig(isDraftOrderFinalized: true, seasonPhase: 'Draft'));
        $menus = $builder->getMenuStructure();

        $seasonLinks = $menus['Season']['links'];
        $draftOrderLink = null;
        foreach ($seasonLinks as $link) {
            if (str_contains($link['url'] ?? '', 'ProjectedDraftOrder')) {
                $draftOrderLink = $link;
                break;
            }
        }

        $this->assertNotNull($draftOrderLink);
        $this->assertSame('Draft Order', $draftOrderLink['label']);
        $this->assertSame('FINAL', $draftOrderLink['badge'] ?? null);
    }

    public function testDraftOrderLabelWithoutBadgeWhenFinalizedOutsideDraft(): void
    {
        $builder = new NavigationMenuBuilder($this->createConfig(isDraftOrderFinalized: true, seasonPhase: 'Regular Season'));
        $menus = $builder->getMenuStructure();

        $seasonLinks = $menus['Season']['links'];
        $draftOrderLink = null;
        foreach ($seasonLinks as $link) {
            if (str_contains($link['url'] ?? '', 'ProjectedDraftOrder')) {
                $draftOrderLink = $link;
                break;
            }
        }

        $this->assertNotNull($draftOrderLink);
        $this->assertSame('Draft Order', $draftOrderLink['label']);
        $this->assertNull($draftOrderLink['badge'] ?? null);
    }

    // --- Account Menu Tests ---

    public function testAccountMenuWhenLoggedIn(): void
    {
        $builder = new NavigationMenuBuilder($this->createConfig(isLoggedIn: true));
        $menu = $builder->getAccountMenu();

        $this->assertCount(1, $menu);
        $this->assertSame('Logout', $menu[0]['label']);
    }

    public function testAccountMenuWhenLoggedOut(): void
    {
        $builder = new NavigationMenuBuilder($this->createConfig(isLoggedIn: false, username: null, teamId: null));
        $menu = $builder->getAccountMenu();

        $this->assertCount(2, $menu);
        $labels = array_column($menu, 'label');
        $this->assertContains('Sign Up', $labels);
        $this->assertContains('Forgot Password', $labels);
    }
}
