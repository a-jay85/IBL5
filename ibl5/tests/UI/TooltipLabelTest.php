<?php

declare(strict_types=1);

namespace Tests\UI;

use PHPUnit\Framework\TestCase;
use UI\Components\TooltipLabel;

/**
 * @covers \UI\Components\TooltipLabel
 */
class TooltipLabelTest extends TestCase
{
    public function testRenderWithTooltipReturnsSpanWithIblTooltipClass(): void
    {
        $result = TooltipLabel::render('5', 'Returns: 2025-02-10');

        $this->assertStringContainsString('class="ibl-tooltip"', $result);
        $this->assertStringContainsString('title="Returns: 2025-02-10"', $result);
        $this->assertStringContainsString('tabindex="0"', $result);
        $this->assertStringContainsString('>5</span>', $result);
    }

    public function testRenderWithEmptyTooltipReturnsDisplayValueUnchanged(): void
    {
        $result = TooltipLabel::render('42', '');

        $this->assertSame('42', $result);
    }

    public function testRenderSanitizesTooltipText(): void
    {
        $result = TooltipLabel::render('5', '<script>alert("xss")</script>');

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testRenderWithCustomCssClass(): void
    {
        $result = TooltipLabel::render('Sim #3', 'Overall Sim #45', 'leaders-sim');

        $this->assertStringContainsString('class="ibl-tooltip leaders-sim"', $result);
        $this->assertStringContainsString('title="Overall Sim #45"', $result);
        $this->assertStringContainsString('>Sim #3</span>', $result);
    }

    public function testRenderPassesDisplayValueThroughRaw(): void
    {
        $html = '<strong>bold</strong>';
        $result = TooltipLabel::render($html, 'tooltip text');

        $this->assertStringContainsString('<strong>bold</strong>', $result);
    }
}
