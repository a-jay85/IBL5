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
        $this->assertStringContainsString('>Past drafts</a>', $html);
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
}
