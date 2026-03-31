<?php

declare(strict_types=1);

namespace Tests\PlrParser;

use PHPUnit\Framework\TestCase;
use PlrParser\PlrParseResult;

class PlrParseResultTest extends TestCase
{
    public function testSummaryWithNoChanges(): void
    {
        $result = new PlrParseResult();

        $this->assertSame('No changes', $result->summary());
    }

    public function testSummaryWithPlayersUpserted(): void
    {
        $result = new PlrParseResult();
        $result->playersUpserted = 10;

        $this->assertSame('10 players upserted', $result->summary());
    }

    public function testSummaryWithPartialCounters(): void
    {
        $result = new PlrParseResult();
        $result->playersUpserted = 5;

        $this->assertSame('5 players upserted', $result->summary());
    }

    public function testAddMessage(): void
    {
        $result = new PlrParseResult();
        $result->addMessage('Test message');

        $this->assertSame(['Test message'], $result->messages);
    }

    public function testMultipleMessages(): void
    {
        $result = new PlrParseResult();
        $result->addMessage('First');
        $result->addMessage('Second');

        $this->assertCount(2, $result->messages);
        $this->assertSame('First', $result->messages[0]);
        $this->assertSame('Second', $result->messages[1]);
    }
}
