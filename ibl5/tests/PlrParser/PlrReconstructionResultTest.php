<?php

declare(strict_types=1);

namespace Tests\PlrParser;

use PHPUnit\Framework\TestCase;
use PlrParser\PlrReconstructionResult;

/**
 * @covers \PlrParser\PlrReconstructionResult
 */
final class PlrReconstructionResultTest extends TestCase
{
    public function testStartsWithZeroCountsAndNoMessages(): void
    {
        $result = new PlrReconstructionResult();

        self::assertSame(0, $result->playersUpdated);
        self::assertSame(0, $result->playersUnchanged);
        self::assertSame(0, $result->teamsUpdated);
        self::assertSame(0, $result->teamsUnchanged);
        self::assertSame(0, $result->bytesWritten);
        self::assertSame([], $result->messages);
        self::assertSame([], $result->errors);
    }

    public function testAddMessageAppendsInOrder(): void
    {
        $result = new PlrReconstructionResult();
        $result->addMessage('first');
        $result->addMessage('second');

        self::assertSame(['first', 'second'], $result->messages);
        self::assertSame([], $result->errors);
        self::assertFalse($result->hasErrors());
    }

    public function testAddErrorAppendsAndHasErrorsReturnsTrue(): void
    {
        $result = new PlrReconstructionResult();
        $result->addError('missing car row');

        self::assertSame(['missing car row'], $result->errors);
        self::assertTrue($result->hasErrors());
    }

    public function testHasErrorsIsFalseWhenOnlyMessagesRecorded(): void
    {
        $result = new PlrReconstructionResult();
        $result->addMessage('note');

        self::assertFalse($result->hasErrors());
    }
}
