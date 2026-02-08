<?php

declare(strict_types=1);

namespace Tests\Api\Cache;

use Api\Cache\ETagHandler;
use PHPUnit\Framework\TestCase;

class ETagHandlerTest extends TestCase
{
    private ETagHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new ETagHandler();
    }

    public function testGenerateReturnsQuotedMd5(): void
    {
        $etag = $this->handler->generate('2026-01-15 12:00:00');

        $this->assertStringStartsWith('"', $etag);
        $this->assertStringEndsWith('"', $etag);
        $this->assertSame(34, strlen($etag)); // 32 hex + 2 quotes
    }

    public function testGenerateIsDeterministic(): void
    {
        $etag1 = $this->handler->generate('2026-01-15 12:00:00');
        $etag2 = $this->handler->generate('2026-01-15 12:00:00');

        $this->assertSame($etag1, $etag2);
    }

    public function testGenerateChangesWithDifferentTimestamps(): void
    {
        $etag1 = $this->handler->generate('2026-01-15 12:00:00');
        $etag2 = $this->handler->generate('2026-01-15 12:00:01');

        $this->assertNotSame($etag1, $etag2);
    }

    public function testGenerateFromCollectionConcatenatesTimestamps(): void
    {
        $rows = [
            ['updated_at' => '2026-01-15 12:00:00', 'name' => 'Player A'],
            ['updated_at' => '2026-01-15 13:00:00', 'name' => 'Player B'],
        ];

        $etag = $this->handler->generateFromCollection($rows);

        $this->assertStringStartsWith('"', $etag);
        $this->assertStringEndsWith('"', $etag);
    }

    public function testGenerateFromCollectionIsDeterministic(): void
    {
        $rows = [
            ['updated_at' => '2026-01-15 12:00:00'],
            ['updated_at' => '2026-01-15 13:00:00'],
        ];

        $etag1 = $this->handler->generateFromCollection($rows);
        $etag2 = $this->handler->generateFromCollection($rows);

        $this->assertSame($etag1, $etag2);
    }

    public function testGenerateFromCollectionHandlesEmptyRows(): void
    {
        $etag = $this->handler->generateFromCollection([]);

        $this->assertStringStartsWith('"', $etag);
        $this->assertStringEndsWith('"', $etag);
    }

    public function testGenerateFromCollectionSkipsRowsWithoutUpdatedAt(): void
    {
        $rows = [
            ['updated_at' => '2026-01-15 12:00:00'],
            ['name' => 'No timestamp'],
            ['updated_at' => '2026-01-15 13:00:00'],
        ];

        $etag = $this->handler->generateFromCollection($rows);

        // Should still produce a valid etag
        $this->assertStringStartsWith('"', $etag);
    }

    public function testMatchesReturnsTrueWhenHeaderMatches(): void
    {
        $etag = '"abc123"';
        $_SERVER['HTTP_IF_NONE_MATCH'] = '"abc123"';

        $this->assertTrue($this->handler->matches($etag));

        unset($_SERVER['HTTP_IF_NONE_MATCH']);
    }

    public function testMatchesReturnsFalseWhenHeaderDiffers(): void
    {
        $etag = '"abc123"';
        $_SERVER['HTTP_IF_NONE_MATCH'] = '"different"';

        $this->assertFalse($this->handler->matches($etag));

        unset($_SERVER['HTTP_IF_NONE_MATCH']);
    }

    public function testMatchesReturnsFalseWhenNoHeader(): void
    {
        unset($_SERVER['HTTP_IF_NONE_MATCH']);

        $this->assertFalse($this->handler->matches('"abc123"'));
    }

    public function testGetHeadersReturnsEtagAndCacheControl(): void
    {
        $headers = $this->handler->getHeaders('"abc123"', 120);

        $this->assertSame('"abc123"', $headers['ETag']);
        $this->assertSame('public, max-age=120', $headers['Cache-Control']);
    }

    public function testGetHeadersDefaultMaxAge(): void
    {
        $headers = $this->handler->getHeaders('"abc123"');

        $this->assertSame('public, max-age=60', $headers['Cache-Control']);
    }
}
