<?php

declare(strict_types=1);

namespace Tests\PlrParser;

use PlrParser\PlrWriteResult;
use PHPUnit\Framework\TestCase;

/**
 * @covers \PlrParser\PlrWriteResult
 */
class PlrWriteResultTest extends TestCase
{
    public function testInitialStateHasZeroCounters(): void
    {
        $result = new PlrWriteResult();

        $this->assertSame(0, $result->playersModified);
        $this->assertSame(0, $result->fieldsChanged);
        $this->assertSame(0, $result->errors);
    }

    public function testInitialChangeLogAndMessagesAreEmpty(): void
    {
        $result = new PlrWriteResult();

        $this->assertSame([], $result->changeLog);
        $this->assertSame([], $result->messages);
    }

    public function testAddPlayerChangesWithEmptyArrayIsNoOp(): void
    {
        $result = new PlrWriteResult();
        $result->addPlayerChanges(100, 'John Doe', []);

        $this->assertSame(0, $result->playersModified);
        $this->assertSame(0, $result->fieldsChanged);
        $this->assertSame([], $result->changeLog);
    }

    public function testAddPlayerChangesIncrementsPlayersModified(): void
    {
        $result = new PlrWriteResult();
        $result->addPlayerChanges(100, 'John Doe', [
            ['field' => 'fga', 'old' => 3, 'new' => 4],
        ]);

        $this->assertSame(1, $result->playersModified);
    }

    public function testAddPlayerChangesAccumulatesFieldsChanged(): void
    {
        $result = new PlrWriteResult();
        $result->addPlayerChanges(100, 'John Doe', [
            ['field' => 'fga', 'old' => 3, 'new' => 4],
            ['field' => 'fgp', 'old' => 2, 'new' => 3],
            ['field' => 'spd', 'old' => 4, 'new' => 5],
        ]);

        $this->assertSame(3, $result->fieldsChanged);
    }

    public function testAddPlayerChangesPopulatesChangeLog(): void
    {
        $changes = [
            ['field' => 'fga', 'old' => 3, 'new' => 4],
        ];

        $result = new PlrWriteResult();
        $result->addPlayerChanges(100, 'John Doe', $changes);

        $this->assertCount(1, $result->changeLog);
        $this->assertSame(100, $result->changeLog[0]['pid']);
        $this->assertSame('John Doe', $result->changeLog[0]['name']);
        $this->assertSame($changes, $result->changeLog[0]['changes']);
    }

    public function testAddPlayerChangesAccumulatesAcrossMultipleCalls(): void
    {
        $result = new PlrWriteResult();
        $result->addPlayerChanges(100, 'John Doe', [
            ['field' => 'fga', 'old' => 3, 'new' => 4],
        ]);
        $result->addPlayerChanges(200, 'Jane Smith', [
            ['field' => 'spd', 'old' => 4, 'new' => 5],
            ['field' => 'def', 'old' => 2, 'new' => 3],
        ]);

        $this->assertSame(2, $result->playersModified);
        $this->assertSame(3, $result->fieldsChanged);
        $this->assertCount(2, $result->changeLog);
    }

    public function testAddErrorIncrementsErrors(): void
    {
        $result = new PlrWriteResult();
        $result->addError('Player not found');

        $this->assertSame(1, $result->errors);
    }

    public function testAddErrorPrefixesMessageWithError(): void
    {
        $result = new PlrWriteResult();
        $result->addError('Player not found');

        $this->assertSame(['ERROR: Player not found'], $result->messages);
    }

    public function testAddMessageAppendsToMessages(): void
    {
        $result = new PlrWriteResult();
        $result->addMessage('Processing complete');

        $this->assertSame(['Processing complete'], $result->messages);
    }

    public function testSummaryReturnsNoChangesWhenAllZero(): void
    {
        $result = new PlrWriteResult();

        $this->assertSame('No changes', $result->summary());
    }

    public function testSummaryWithOnlyErrors(): void
    {
        $result = new PlrWriteResult();
        $result->addError('File not found');

        $this->assertSame('1 errors', $result->summary());
    }

    public function testSummaryOmitsZeroCounters(): void
    {
        $result = new PlrWriteResult();
        $result->addPlayerChanges(100, 'John Doe', [
            ['field' => 'fga', 'old' => 3, 'new' => 4],
        ]);

        $this->assertSame('1 players modified, 1 fields changed', $result->summary());
    }

    public function testSummaryIncludesAllNonZeroCounters(): void
    {
        $result = new PlrWriteResult();
        $result->addPlayerChanges(100, 'John Doe', [
            ['field' => 'fga', 'old' => 3, 'new' => 4],
        ]);
        $result->addError('Something went wrong');

        $this->assertSame('1 players modified, 1 fields changed, 1 errors', $result->summary());
    }
}
