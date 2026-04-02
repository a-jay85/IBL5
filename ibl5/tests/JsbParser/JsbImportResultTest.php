<?php

declare(strict_types=1);

namespace Tests\JsbParser;

use JsbParser\JsbImportResult;
use PHPUnit\Framework\TestCase;

/**
 * @covers \JsbParser\JsbImportResult
 */
class JsbImportResultTest extends TestCase
{
    public function testDefaultValuesAreZero(): void
    {
        $result = new JsbImportResult();

        $this->assertSame(0, $result->inserted);
        $this->assertSame(0, $result->updated);
        $this->assertSame(0, $result->skipped);
        $this->assertSame(0, $result->errors);
        $this->assertSame([], $result->messages);
    }

    public function testAddInsertedIncrements(): void
    {
        $result = new JsbImportResult();
        $result->addInserted();
        $result->addInserted(5);

        $this->assertSame(6, $result->inserted);
    }

    public function testAddUpdatedIncrements(): void
    {
        $result = new JsbImportResult();
        $result->addUpdated(3);

        $this->assertSame(3, $result->updated);
    }

    public function testAddSkippedIncrements(): void
    {
        $result = new JsbImportResult();
        $result->addSkipped();
        $result->addSkipped();

        $this->assertSame(2, $result->skipped);
    }

    public function testAddErrorIncrementsAndAddsMessage(): void
    {
        $result = new JsbImportResult();
        $result->addError('Something went wrong');

        $this->assertSame(1, $result->errors);
        $this->assertSame(['ERROR: Something went wrong'], $result->messages);
    }

    public function testAddMessageAppendsWithoutIncrementingErrors(): void
    {
        $result = new JsbImportResult();
        $result->addMessage('Info message');

        $this->assertSame(0, $result->errors);
        $this->assertSame(['Info message'], $result->messages);
    }

    public function testMergeCombinesResults(): void
    {
        $a = new JsbImportResult();
        $a->addInserted(10);
        $a->addUpdated(5);
        $a->addMessage('First');

        $b = new JsbImportResult();
        $b->addInserted(3);
        $b->addSkipped(2);
        $b->addError('Oops');
        $b->addMessage('Second');

        $a->merge($b);

        $this->assertSame(13, $a->inserted);
        $this->assertSame(5, $a->updated);
        $this->assertSame(2, $a->skipped);
        $this->assertSame(1, $a->errors);
        $this->assertCount(3, $a->messages); // 'First', 'ERROR: Oops', 'Second'
    }

    public function testSummaryWithInserts(): void
    {
        $result = new JsbImportResult();
        $result->addInserted(10);

        $this->assertSame('10 inserted', $result->summary());
    }

    public function testSummaryWithMultipleCounters(): void
    {
        $result = new JsbImportResult();
        $result->addInserted(10);
        $result->addUpdated(5);
        $result->addError('test');

        $this->assertSame('10 inserted, 5 updated, 1 errors', $result->summary());
    }

    public function testSummaryWithNoChanges(): void
    {
        $result = new JsbImportResult();

        $this->assertSame('No changes', $result->summary());
    }

    public function testFromScoResultMapsFields(): void
    {
        $scoResult = [
            'success' => true,
            'gamesInserted' => 5,
            'gamesUpdated' => 3,
            'gamesSkipped' => 10,
            'linesProcessed' => 200,
            'messages' => [],
        ];

        $result = JsbImportResult::fromScoResult($scoResult);

        $this->assertSame(5, $result->inserted);
        $this->assertSame(3, $result->updated);
        $this->assertSame(10, $result->skipped);
        $this->assertSame(0, $result->errors);
    }

    public function testFromScoResultWithError(): void
    {
        $scoResult = [
            'success' => false,
            'gamesInserted' => 0,
            'gamesUpdated' => 0,
            'gamesSkipped' => 0,
            'linesProcessed' => 0,
            'messages' => ['Parsing started'],
            'error' => 'Failed to open .sco file',
        ];

        $result = JsbImportResult::fromScoResult($scoResult);

        $this->assertSame(1, $result->errors);
        $this->assertStringContainsString('Failed to open .sco file', $result->messages[0]);
    }

    public function testFromScoResultPreservesMessages(): void
    {
        $scoResult = [
            'success' => true,
            'gamesInserted' => 1,
            'gamesUpdated' => 0,
            'gamesSkipped' => 0,
            'linesProcessed' => 32,
            'messages' => ['Parsing .sco file...', 'Games inserted: 1'],
        ];

        $result = JsbImportResult::fromScoResult($scoResult);

        $this->assertCount(2, $result->messages);
        $this->assertSame('Parsing .sco file...', $result->messages[0]);
        $this->assertSame('Games inserted: 1', $result->messages[1]);
    }
}
