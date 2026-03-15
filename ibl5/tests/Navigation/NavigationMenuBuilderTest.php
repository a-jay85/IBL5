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

    public function testDraftOrderLabelWithFinalBadgeWhenFinalized(): void
    {
        $builder = new NavigationMenuBuilder($this->createConfig(isDraftOrderFinalized: true));
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
