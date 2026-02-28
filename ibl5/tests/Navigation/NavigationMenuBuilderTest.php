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
    ): NavigationConfig {
        return new NavigationConfig(
            isLoggedIn: $isLoggedIn,
            username: $username,
            currentLeague: $currentLeague,
            teamId: $teamId,
            seasonPhase: $seasonPhase,
            allowWaivers: $allowWaivers,
            showDraftLink: $showDraftLink,
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

    public function testWaiversLinksWhenAllowed(): void
    {
        $builder = new NavigationMenuBuilder($this->createConfig(allowWaivers: 'Yes'));
        $menu = $builder->getMyTeamMenu();
        $this->assertNotNull($menu);

        $waiversLinks = array_filter(
            $menu['links'],
            static fn (array $link): bool => str_contains($link['rawHtml'] ?? '', 'Waivers')
        );

        $this->assertNotEmpty($waiversLinks, 'Waivers link should be present when allowed');
    }

    public function testWaiversLinksWhenNotAllowed(): void
    {
        $builder = new NavigationMenuBuilder($this->createConfig(allowWaivers: 'No'));
        $menu = $builder->getMyTeamMenu();
        $this->assertNotNull($menu);

        $waiversLinks = array_filter(
            $menu['links'],
            static fn (array $link): bool => str_contains($link['rawHtml'] ?? '', 'Waivers')
        );

        $this->assertEmpty($waiversLinks, 'Waivers link should not be present when not allowed');
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
