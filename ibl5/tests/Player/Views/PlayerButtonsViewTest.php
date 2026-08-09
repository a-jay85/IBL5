<?php

declare(strict_types=1);

namespace Tests\Player\Views;

use PHPUnit\Framework\TestCase;
use Player\Views\PlayerButtonsView;

/** @covers \Player\Views\PlayerButtonsView */
class PlayerButtonsViewTest extends TestCase
{
    use SnapshotTestTrait;

    public function testRookieOptionUsedMessageSnapshot(): void
    {
        $html = PlayerButtonsView::renderRookieOptionUsedMessage();

        $this->assertSnapshotMatches($html, 'PlayerButtonsView-rookieUsed.html');
    }

    public function testRenegotiationButtonContainsPlayerID(): void
    {
        $html = PlayerButtonsView::renderRenegotiationButton(42);

        $this->assertStringContainsString('pid=42', $html);
    }

    public function testRenegotiationButtonSnapshot(): void
    {
        $html = PlayerButtonsView::renderRenegotiationButton(42);

        $this->assertSnapshotMatches($html, 'PlayerButtonsView-renegotiation.html');
    }

    public function testRookieOptionButtonContainsPlayerID(): void
    {
        $html = PlayerButtonsView::renderRookieOptionButton(99);

        $this->assertStringContainsString('pid=99', $html);
    }

    public function testRookieOptionButtonSnapshot(): void
    {
        $html = PlayerButtonsView::renderRookieOptionButton(99);

        $this->assertSnapshotMatches($html, 'PlayerButtonsView-rookieOption.html');
    }
}
