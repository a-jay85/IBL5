<?php

declare(strict_types=1);

namespace Tests\DepthChart;

use DepthChart\DepthChartErrorHtmlRenderer;
use PHPUnit\Framework\TestCase;
use Validation\ValidationError;

/**
 * @covers \DepthChart\DepthChartErrorHtmlRenderer
 */
class DepthChartErrorHtmlRendererTest extends TestCase
{
    private function block(string $message, string $detail): string
    {
        return '<div class="text-center"><span class="text-red-500"><strong>'
            . \Security\HtmlSanitizer::safeHtmlOutput($message)
            . '</strong></span><p>'
            . \Security\HtmlSanitizer::safeHtmlOutput($detail)
            . '</p></div>';
    }

    public function testRendererIsFinalStaticAndStateless(): void
    {
        $rc = new \ReflectionClass(DepthChartErrorHtmlRenderer::class);

        $this->assertTrue($rc->isFinal());
        $this->assertSame([], $rc->getProperties());
        $this->assertNull($rc->getConstructor());
        $this->assertTrue($rc->getMethod('render')->isStatic());
        $this->assertCount(1, $rc->getMethods());
    }

    public function testRendererSourceHasNoDatabaseOrSessionAccess(): void
    {
        $rc = new \ReflectionClass(DepthChartErrorHtmlRenderer::class);
        $fileName = $rc->getFileName();
        $this->assertIsString($fileName);
        $src = file_get_contents($fileName);
        $this->assertIsString($src);

        foreach (['$_SESSION', 'mysqli', '$db', '$_POST', '$_GET', '$_COOKIE'] as $token) {
            $this->assertStringNotContainsString($token, $src);
        }
    }

    public function testRenderEmptyListReturnsEmptyString(): void
    {
        $this->assertSame('', DepthChartErrorHtmlRenderer::render([]));
    }

    public function testRenderMarkupIsByteExactAndEscapesMessage(): void
    {
        $message = '<b>Bad</b> & "Co" is set as starter (1st) at multiple positions.';
        $detail = 'Set this player as 1st at only one position and resubmit.';

        $html = DepthChartErrorHtmlRenderer::render([new ValidationError('t', $message, $detail)]);

        $expected = '<div class="text-center"><span class="text-red-500"><strong>'
            . \Security\HtmlSanitizer::safeHtmlOutput($message)
            . '</strong></span><p>Set this player as 1st at only one position and resubmit.</p></div>';
        $this->assertSame($expected, $html);
        $this->assertStringNotContainsString('<b>Bad</b>', $html);
    }

    public function testRenderEscapesDetail(): void
    {
        $html = DepthChartErrorHtmlRenderer::render([
            new ValidationError('t', 'msg', '<script>alert(1)</script>'),
        ]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testRenderConcatenatesInInputOrder(): void
    {
        $html = DepthChartErrorHtmlRenderer::render([
            new ValidationError('a', 'first', 'd1'),
            new ValidationError('b', 'second', 'd2'),
        ]);

        $this->assertSame($this->block('first', 'd1') . $this->block('second', 'd2'), $html);
    }
}
