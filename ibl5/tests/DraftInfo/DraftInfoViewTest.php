<?php

declare(strict_types=1);

namespace Tests\DraftInfo;

use DraftInfo\DraftInfoView;
use PHPUnit\Framework\TestCase;

class DraftInfoViewTest extends TestCase
{
    private DraftInfoView $view;

    protected function setUp(): void
    {
        $this->view = new DraftInfoView();
    }

    public function testTabsWhitelistIsOrderPicksHistory(): void
    {
        $this->assertSame(['order', 'picks', 'history'], array_keys(DraftInfoView::TABS));
    }

    public function testRenderOutputsThreeTabsInFixedOrder(): void
    {
        $html = $this->view->render('order', '');

        $orderPos = strpos($html, 'data-tab="order"');
        $picksPos = strpos($html, 'data-tab="picks"');
        $historyPos = strpos($html, 'data-tab="history"');

        $this->assertNotFalse($orderPos);
        $this->assertNotFalse($picksPos);
        $this->assertNotFalse($historyPos);
        $this->assertLessThan($picksPos, $orderPos);
        $this->assertLessThan($historyPos, $picksPos);
        $this->assertStringContainsString('>Order</a>', $html);
        $this->assertStringContainsString('>Pick ownership</a>', $html);
        $this->assertStringContainsString('>History</a>', $html);
    }

    public function testRenderMarksOnlyActiveTabActive(): void
    {
        $html = $this->view->render('picks', '');

        $this->assertSame(1, substr_count($html, 'ibl-tab--active'));
        $this->assertStringContainsString('data-tab="picks" aria-current="page"', $html);
        $this->assertStringContainsString('class="ibl-tab ibl-tab--active"', $html);
    }

    public function testRenderUnknownTabMarksNoTabActive(): void
    {
        $html = $this->view->render('bogus', '');

        $this->assertSame(0, substr_count($html, 'ibl-tab--active'));
    }

    public function testTabLinksTargetDraftInfoTabParam(): void
    {
        $html = $this->view->render('order', '');

        $this->assertStringContainsString('href="modules.php?name=DraftInfo&amp;tab=history"', $html);
    }

    public function testRenderWrapsTabsAndPanelInOneCard(): void
    {
        $html = $this->view->render('order', '<p>body</p>');

        $this->assertStringStartsWith('<div class="ibl-tab-card"><div class="ibl-tabs">', $html);
        $this->assertStringEndsWith('<p>body</p></div></div>', $html);
    }

    public function testRenderEmbedsTabBodyVerbatim(): void
    {
        $body = '<table data-x="1"><td>A &amp; B <script>x</script></td></table>';

        $html = $this->view->render('order', $body);

        $this->assertStringContainsString($body, $html);
    }

    public function testRenderEscapesActiveTabAttribute(): void
    {
        $html = $this->view->render('"><script>', '');

        $this->assertStringNotContainsString('data-tab=""><script>', $html);
        $this->assertStringNotContainsString('"><script>', $html);
    }

    public function testHistoryTabLabelIsHistory(): void
    {
        $html = $this->view->render('history', '');

        $this->assertStringContainsString('>History</a>', $html);
        $this->assertStringNotContainsString('Past drafts', $html);
    }

    public function testHistorySubTabsLeagueHrefHasNoViewOrTeamParam(): void
    {
        $html = $this->view->renderHistorySubTabs('league', 'My team', null, '');

        $this->assertStringContainsString('href="modules.php?name=DraftInfo&amp;tab=history"', $html);
    }

    public function testHistorySubTabsMyTeamHrefUsesViewTeam(): void
    {
        $html = $this->view->renderHistorySubTabs('team', 'My team', null, '');

        $this->assertStringContainsString('href="modules.php?name=DraftInfo&amp;tab=history&amp;view=team"', $html);
        $this->assertStringNotContainsString('teamid=', $html);
    }

    public function testHistorySubTabsOtherTeamHrefUsesTeamid(): void
    {
        $html = $this->view->renderHistorySubTabs('team', 'Celtics', 7, '');

        $this->assertStringContainsString('&amp;tab=history&amp;teamid=7"', $html);
        $this->assertStringNotContainsString('view=team', $html);
    }

    public function testHistorySubTabsMarksActiveSubTab(): void
    {
        $html = $this->view->renderHistorySubTabs('team', 'My team', null, '');

        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
        $this->assertStringContainsString('data-tab="team" aria-current="page"', $html);
        $this->assertStringContainsString('class="ibl-tab" href="modules.php?name=DraftInfo&amp;tab=history" data-tab="league">', $html);
    }

    public function testHistorySubTabsEscapesTeamLabel(): void
    {
        $html = $this->view->renderHistorySubTabs('team', '<script>x</script>', 3, '');

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testHistorySubTabsWrapsPanelVerbatimWithModifier(): void
    {
        $html = $this->view->renderHistorySubTabs('league', 'My team', null, '<div id="draft-history-content">x</div>');

        $this->assertStringContainsString('class="ibl-tabs ibl-tabs--sub"', $html);
        $this->assertStringContainsString(
            '<div class="ibl-subtab-panel" data-subtab="league"><div id="draft-history-content">x</div></div>',
            $html
        );
    }

    public function testTeamLoginPromptLinksToLogin(): void
    {
        $html = $this->view->renderTeamLoginPrompt();

        $this->assertSame(
            '<p class="ibl-subtab-login-prompt"><a href="modules.php?name=YourAccount">Log in</a>'
            . ' to see your team&#039;s draft history.</p>',
            $html
        );
    }
}
