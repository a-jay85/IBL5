<?php

declare(strict_types=1);

namespace Tests\RecordHolders;

use PHPUnit\Framework\TestCase;
use RecordHolders\RecordTableRenderer;

/**
 * @covers \RecordHolders\RecordTableRenderer
 */
final class RecordTableRendererTest extends TestCase
{
    private RecordTableRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new RecordTableRenderer();
    }

    public function testRenderCategoryHeadingEscapesCategory(): void
    {
        $payload = '<script>alert(1)</script>';

        $html = $this->renderer->renderCategoryHeading($payload);

        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function testRenderCategoryHeadingWrapsInH3(): void
    {
        $html = $this->renderer->renderCategoryHeading('Most Points');

        self::assertStringContainsString('<h3', $html);
        self::assertStringContainsString('Most Points', $html);
    }

    public function testGetStatColumnLabelReturnsAbbreviationForKnownCategory(): void
    {
        self::assertSame('Pts', $this->renderer->getStatColumnLabel('Most Points in a Single Game'));
    }

    public function testGetStatColumnLabelReturnsAmountForUnknownCategory(): void
    {
        self::assertSame('Amount', $this->renderer->getStatColumnLabel('Unknown Category'));
    }

    public function testRenderCategoryTableContainsHeadingAndRows(): void
    {
        $html = $this->renderer->renderCategoryTable(
            'Most Points',
            'record-table--4col',
            '<col>',
            '<th>Team</th>',
            '<tr><td>row</td></tr>',
        );

        self::assertStringContainsString('Most Points', $html);
        self::assertStringContainsString('<tr><td>row</td></tr>', $html);
        self::assertStringContainsString('record-table--4col', $html);
    }
}
