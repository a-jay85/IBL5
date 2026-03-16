<?php

declare(strict_types=1);

namespace Tests\UI;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use UI\AlertRenderer;

#[CoversClass(AlertRenderer::class)]
class AlertRendererTest extends TestCase
{
    private const BANNERS = [
        'success_code' => ['class' => 'ibl-alert--success', 'message' => 'It worked!'],
        'warn_code' => ['class' => 'ibl-alert--warning', 'message' => 'Be careful.'],
    ];

    #[Test]
    public function fromCodeReturnsMatchingBanner(): void
    {
        $html = AlertRenderer::fromCode('success_code', self::BANNERS);

        $this->assertStringContainsString('ibl-alert--success', $html);
        $this->assertStringContainsString('It worked!', $html);
    }

    #[Test]
    public function fromCodeReturnsEmptyForNull(): void
    {
        $this->assertSame('', AlertRenderer::fromCode(null, self::BANNERS));
    }

    #[Test]
    public function fromCodeReturnsEmptyForUnknownCode(): void
    {
        $this->assertSame('', AlertRenderer::fromCode('unknown', self::BANNERS));
    }

    #[Test]
    public function fromCodePrioritizesErrorOverCode(): void
    {
        $html = AlertRenderer::fromCode('success_code', self::BANNERS, 'Something broke');

        $this->assertStringContainsString('ibl-alert--error', $html);
        $this->assertStringContainsString('Something broke', $html);
        $this->assertStringNotContainsString('It worked!', $html);
    }

    #[Test]
    public function renderEscapesHtml(): void
    {
        $html = AlertRenderer::render('<script>alert(1)</script>', 'ibl-alert--info');

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('ibl-alert--info', $html);
    }

    #[Test]
    public function renderProducesCorrectStructure(): void
    {
        $html = AlertRenderer::render('Test message', 'ibl-alert--success');

        $this->assertSame('<div class="ibl-alert ibl-alert--success">Test message</div>', $html);
    }
}
