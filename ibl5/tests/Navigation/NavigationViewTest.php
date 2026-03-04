<?php

declare(strict_types=1);

namespace Tests\Navigation;

use Navigation\NavigationConfig;
use Navigation\NavigationView;
use PHPUnit\Framework\TestCase;

class NavigationViewTest extends TestCase
{
    /**
     * Render navigation for a logged-in IBL user with the given settings.
     *
     * @return string Rendered HTML
     */
    private function renderNav(string $seasonPhase, string $showDraftLink, bool $loggedIn = true): string
    {
        $teamId = $loggedIn ? 1 : null;

        $config = new NavigationConfig(
            isLoggedIn: $loggedIn,
            username: $loggedIn ? 'TestUser' : null,
            currentLeague: 'ibl',
            teamId: $teamId,
            teamsData: null,
            seasonPhase: $seasonPhase,
            allowWaivers: 'No',
            showDraftLink: $showDraftLink,
            serverName: 'localhost',
            requestUri: '/ibl5/index.php',
        );

        $nav = new NavigationView($config);

        return $nav->render();
    }

    /**
     * The exact href for the Draft module link (distinguishes from DraftHistory, DraftPickLocator, etc.)
     */
    private const DRAFT_LINK_HREF = 'href="modules.php?name=Draft"';

    public function testDraftLinkWithLiveBadgeWhenPhaseIsDraft(): void
    {
        $html = $this->renderNav('Draft', 'Off');

        $this->assertStringContainsString(self::DRAFT_LINK_HREF, $html);
        $this->assertStringContainsString('LIVE', $html);
    }

    public function testDraftLinkWithLiveBadgeWhenPhaseIsDraftAndToggleOn(): void
    {
        $html = $this->renderNav('Draft', 'On');

        // During Draft phase, the LIVE badge should appear regardless of toggle
        $this->assertStringContainsString(self::DRAFT_LINK_HREF, $html);
        $this->assertStringContainsString('LIVE', $html);
    }

    public function testDraftLinkWithoutBadgeWhenRegularSeasonAndToggleOn(): void
    {
        $html = $this->renderNav('Regular Season', 'On');

        $this->assertStringContainsString(self::DRAFT_LINK_HREF, $html);
        // Should NOT have LIVE badge since we're not in Draft phase
        $this->assertStringNotContainsString('LIVE', $html);
    }

    public function testNoDraftLinkWhenRegularSeasonAndToggleOff(): void
    {
        $html = $this->renderNav('Regular Season', 'Off');

        $this->assertStringNotContainsString(self::DRAFT_LINK_HREF, $html);
    }

    public function testDraftLinkWithoutBadgeWhenPlayoffsAndToggleOn(): void
    {
        $html = $this->renderNav('Playoffs', 'On');

        $this->assertStringContainsString(self::DRAFT_LINK_HREF, $html);
        $this->assertStringNotContainsString('LIVE', $html);
    }

    public function testNoDraftLinkWhenNotLoggedInAndToggleOn(): void
    {
        $html = $this->renderNav('Regular Season', 'On', loggedIn: false);

        // The My Team menu is not rendered for logged-out users, so no Draft link
        $this->assertStringNotContainsString(self::DRAFT_LINK_HREF, $html);
    }

    // --- Orchestration Tests ---

    public function testRenderContainsDesktopAndMobileElements(): void
    {
        $html = $this->renderNav('Regular Season', 'Off');

        // Desktop nav wrapper
        $this->assertStringContainsString('hidden lg:flex', $html);
        // Mobile nav panel
        $this->assertStringContainsString('id="nav-mobile-menu"', $html);
        // Mobile overlay
        $this->assertStringContainsString('id="nav-overlay"', $html);
    }

    public function testRenderContainsLogo(): void
    {
        $html = $this->renderNav('Regular Season', 'Off');

        $this->assertStringContainsString('href="index.php"', $html);
        $this->assertStringContainsString('IBL', $html);
        $this->assertStringContainsString('Sim League', $html);
    }

    public function testRenderContainsNavBarBackground(): void
    {
        $html = $this->renderNav('Regular Season', 'Off');

        $this->assertStringContainsString('nav-bar-bg', $html);
        $this->assertStringContainsString('nav-grain', $html);
    }
}
