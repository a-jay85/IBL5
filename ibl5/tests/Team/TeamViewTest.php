<?php

declare(strict_types=1);

namespace Tests\Team;

use PHPUnit\Framework\TestCase;
use Team\TeamView;
use Team\Contracts\TeamViewInterface;
use Discord\Discord;

/**
 * Tests for TeamView
 *
 * Validates HTML rendering from pre-computed page data
 */
class TeamViewTest extends TestCase
{
    private TeamView $view;

    protected function setUp(): void
    {
        $this->view = new TeamView();
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function createPageData(array $overrides = []): array
    {
        $team = new \stdClass();
        $team->name = 'Celtics';
        $team->color1 = 'FF0000';
        $team->color2 = '0000FF';
        $team->discord_id = null;

        if (isset($overrides['team'])) {
            $team = $overrides['team'];
        }

        return array_merge([
            'teamid' => 1,
            'team' => $team,
            'imagesPath' => 'images/',
            'yr' => null,
            'display' => 'ratings',
            'insertyear' => '',
            'isActualTeam' => true,
            'tableOutput' => '<table><caption class="team-table-caption"><div class="ibl-tabs">tabs</div></caption><tbody><tr><td>roster</td></tr></tbody></table>',
            'draftPicksTable' => '<table>picks</table>',
            'currentSeasonCard' => '<div class="team-card">current season</div>',
            'awardsCard' => '<div class="team-card">awards</div>',
            'franchiseHistoryCard' => '<div class="team-card">franchise history</div>',
            'rafters' => '<div>banners</div>',
            'userTeamName' => '',
            'isOwnTeam' => false,
            'extensionResult' => null,
            'extensionMsg' => null,
        ], $overrides);
    }

    // ============================================
    // RENDER TESTS
    // ============================================

    public function testRenderContainsPageLayout(): void
    {
        $output = $this->view->render($this->createPageData());

        $this->assertStringContainsString('team-page-layout', $output);
        $this->assertStringContainsString('team-page-main', $output);
    }

    public function testRenderContainsTeamLogo(): void
    {
        $output = $this->view->render($this->createPageData());

        $this->assertStringContainsString('images/logo/1.jpg', $output);
    }

    public function testRenderContainsTabsAndTable(): void
    {
        $output = $this->view->render($this->createPageData());

        $this->assertStringContainsString('<caption class="team-table-caption">', $output);
        $this->assertStringContainsString('<div class="ibl-tabs">tabs</div>', $output);
        $this->assertStringContainsString('roster', $output);
    }

    public function testRenderContainsDraftPicksForActualTeam(): void
    {
        $output = $this->view->render($this->createPageData());

        $this->assertStringContainsString('Draft Picks', $output);
        $this->assertStringContainsString('<table>picks</table>', $output);
    }

    public function testRenderOmitsDraftPicksForNonTeam(): void
    {
        $team = new \stdClass();
        $team->name = 'Free Agents';
        $team->color1 = '000000';
        $team->color2 = 'FFFFFF';
        $team->discord_id = null;

        $output = $this->view->render($this->createPageData([
            'teamid' => 0,
            'team' => $team,
            'isActualTeam' => false,
        ]));

        $this->assertStringNotContainsString('Draft Picks', $output);
    }

    public function testRenderContainsCardsRowForActualTeam(): void
    {
        $output = $this->view->render($this->createPageData());

        $this->assertStringContainsString('team-cards-row', $output);
        $this->assertStringContainsString('current season', $output);
        $this->assertStringContainsString('awards', $output);
        $this->assertStringContainsString('franchise history', $output);
    }

    public function testRenderOmitsCardsRowForNonTeam(): void
    {
        $team = new \stdClass();
        $team->name = 'Free Agents';
        $team->color1 = '000000';
        $team->color2 = 'FFFFFF';
        $team->discord_id = null;

        $output = $this->view->render($this->createPageData([
            'teamid' => 0,
            'team' => $team,
            'isActualTeam' => false,
        ]));

        $this->assertStringNotContainsString('team-cards-row', $output);
    }

    public function testRenderShowsYearHeadingAsHeadingTwoForHistoricalYear(): void
    {
        $output = $this->view->render($this->createPageData(['yr' => '2023']));

        $this->assertStringContainsString('<h2 class="ibl-title">2023 Celtics</h2>', $output);
        // Exactly one <h1> (the banner) — the year is an <h2>, not a second <h1>.
        $this->assertSame(1, substr_count($output, '<h1'), 'Historical actual team must still have exactly one <h1>.');
        // Banner <h1> appears BEFORE the year <h2> in DOM order.
        $this->assertLessThan(
            strpos($output, '<h2 class="ibl-title">2023 Celtics</h2>'),
            strpos($output, '<h1>'),
            'Banner h1 must precede the year h2.'
        );
        // The year h2 sits between the banner and the roster table.
        $this->assertLessThan(
            strpos($output, 'table-scroll-wrapper'),
            strpos($output, '<h2 class="ibl-title">2023 Celtics</h2>'),
            'Year h2 must appear before the roster table.'
        );
    }

    public function testRenderShowsBannerLogoAsHeadingOneForCurrentSeason(): void
    {
        $output = $this->view->render($this->createPageData(['yr' => null]));

        // Banner logo is wrapped in <h1>; its accessible name is the team name (no " logo").
        $this->assertMatchesRegularExpression(
            '/<h1>\s*<img[^>]*alt="Celtics"[^>]*>\s*<\/h1>/',
            $output
        );
        $this->assertStringNotContainsString('alt="Celtics logo"', $output);
        // The banner h1 must NOT carry ibl-title, and current-season has no text title at all.
        $this->assertStringNotContainsString('ibl-title', $output);
    }

    public function testCurrentSeasonActualTeamHasExactlyOneHeadingOneAndNoYearHeadingTwo(): void
    {
        $output = $this->view->render($this->createPageData(['yr' => null]));

        $this->assertSame(1, substr_count($output, '<h1'), 'Actual team current-season must have exactly one <h1> (the banner).');
        $this->assertStringNotContainsString('<h2 class="ibl-title">', $output, 'No year h2 row when yr is null.');
    }

    public function testFreeAgentsNonActualTeamShowsTextHeadingOneAndNoBanner(): void
    {
        $team = new \stdClass();
        $team->name = 'Free Agents';
        $team->color1 = '000000';
        $team->color2 = 'FFFFFF';
        $team->discord_id = null;

        $output = $this->view->render($this->createPageData([
            'teamid' => 0,
            'team' => $team,
            'isActualTeam' => false,
        ]));

        $this->assertStringContainsString('<h1 class="ibl-title">Free Agents</h1>', $output);
        $this->assertSame(1, substr_count($output, '<h1'), 'Free Agents page must have exactly one <h1>.');
        // The banner row is NOT rendered for non-actual teams.
        $this->assertStringNotContainsString('team-banner-row', $output);
        // The decorative fallback logo is present with empty alt (cannot be the h1).
        $this->assertStringContainsString('team-logo-fallback', $output);
        $this->assertStringContainsString('alt=""', $output);
    }

    public function testRenderContainsRaftersForActualTeam(): void
    {
        $output = $this->view->render($this->createPageData());

        $this->assertStringContainsString('team-page-rafters', $output);
        $this->assertStringContainsString('<div>banners</div>', $output);
    }

    // ============================================
    // TRADE & DISCORD BUTTON TESTS
    // ============================================

    public function testBannerHidesButtonsWhenNotLoggedIn(): void
    {
        $output = $this->view->render($this->createPageData([
            'userTeamName' => '',
            'isOwnTeam' => false,
        ]));

        $this->assertStringNotContainsString('name=Trading', $output);
        $this->assertStringNotContainsString('discord.com', $output);
    }

    public function testBannerTradeLinksToReviewtradeOnOwnTeam(): void
    {
        $output = $this->view->render($this->createPageData([
            'userTeamName' => 'Celtics',
            'isOwnTeam' => true,
        ]));

        $this->assertStringContainsString('op=reviewtrade', $output);
        $this->assertStringNotContainsString('op=offertrade', $output);
    }

    public function testBannerTradeLinksToOffertradeOnOtherTeam(): void
    {
        $output = $this->view->render($this->createPageData([
            'userTeamName' => 'Lakers',
            'isOwnTeam' => false,
        ]));

        $this->assertStringContainsString('op=offertrade', $output);
        $this->assertStringContainsString('partner=Celtics', $output);
        $this->assertStringNotContainsString('op=reviewtrade', $output);
    }

    public function testBannerDiscordLinksToGuildOnOwnTeam(): void
    {
        $output = $this->view->render($this->createPageData([
            'userTeamName' => 'Celtics',
            'isOwnTeam' => true,
        ]));

        $this->assertStringContainsString('discord.com/channels/' . Discord::getGuildID(), $output);
    }

    public function testBannerDiscordLinksToUserProfileOnOtherTeam(): void
    {
        $team = new \stdClass();
        $team->name = 'Celtics';
        $team->color1 = 'FF0000';
        $team->color2 = '0000FF';
        $team->discord_id = '123456789012345678';

        $output = $this->view->render($this->createPageData([
            'team' => $team,
            'userTeamName' => 'Lakers',
            'isOwnTeam' => false,
        ]));

        $this->assertStringContainsString('discord.com/users/123456789012345678', $output);
    }

    public function testBannerDiscordHiddenOnOtherTeamWhenNoDiscordID(): void
    {
        $output = $this->view->render($this->createPageData([
            'userTeamName' => 'Lakers',
            'isOwnTeam' => false,
        ]));

        $this->assertStringNotContainsString('discord.com/users', $output);
    }

    public function testBannerDiscordLinksOpenInNewTab(): void
    {
        $output = $this->view->render($this->createPageData([
            'userTeamName' => 'Celtics',
            'isOwnTeam' => true,
        ]));

        $this->assertStringContainsString('target="_blank"', $output);
        $this->assertStringContainsString('rel="noopener noreferrer"', $output);
    }

    public function testBannerHasLogoCssClass(): void
    {
        $output = $this->view->render($this->createPageData());

        $this->assertStringContainsString('team-banner-logo', $output);
    }

    // ============================================
    // EXTENSION FLASH MESSAGE TESTS
    // ============================================

    public function testRenderShowsExtensionAcceptedBanner(): void
    {
        $output = $this->view->render($this->createPageData([
            'extensionResult' => 'extension_accepted',
            'extensionMsg' => 'I accept your offer!',
        ]));

        $this->assertStringContainsString('ibl-alert--success', $output);
        $this->assertStringContainsString('I accept your offer!', $output);
        $this->assertStringContainsString('used up your successful extension', $output);
    }

    public function testRenderShowsExtensionRejectedBanner(): void
    {
        $output = $this->view->render($this->createPageData([
            'extensionResult' => 'extension_rejected',
            'extensionMsg' => 'No thanks, I want more money.',
        ]));

        $this->assertStringContainsString('ibl-alert--info', $output);
        $this->assertStringContainsString('No thanks, I want more money.', $output);
        $this->assertStringContainsString('another attempt next sim', $output);
    }

    public function testRenderShowsExtensionErrorBanner(): void
    {
        $output = $this->view->render($this->createPageData([
            'extensionResult' => 'extension_error',
            'extensionMsg' => 'Offer exceeds salary cap.',
        ]));

        $this->assertStringContainsString('ibl-alert--error', $output);
        $this->assertStringContainsString('Offer exceeds salary cap.', $output);
        $this->assertStringContainsString('will not be recorded', $output);
    }

    public function testRenderNoFlashWhenNoExtensionResult(): void
    {
        $output = $this->view->render($this->createPageData([
            'extensionResult' => null,
            'extensionMsg' => null,
        ]));

        $this->assertStringNotContainsString('ibl-alert--success', $output);
        $this->assertStringNotContainsString('ibl-alert--info', $output);
        $this->assertStringNotContainsString('ibl-alert--error', $output);
    }

    public function testRenderEscapesExtensionMessageContent(): void
    {
        $output = $this->view->render($this->createPageData([
            'extensionResult' => 'extension_accepted',
            'extensionMsg' => '<script>alert("xss")</script>',
        ]));

        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }
}
