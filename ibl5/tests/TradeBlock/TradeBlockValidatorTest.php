<?php

declare(strict_types=1);

namespace Tests\TradeBlock;

use PHPUnit\Framework\TestCase;
use TradeBlock\TradeBlockValidator;

class TradeBlockValidatorTest extends TestCase
{
    private TradeBlockValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new TradeBlockValidator();
    }

    public function testCoercesCheckboxListToInts(): void
    {
        $result = $this->validator->sanitizeEdit([
            'on_block' => ['10', '11', '12'],
        ]);

        self::assertSame([10, 11, 12], $result['pids']);
    }

    public function testDropsNonPositivePids(): void
    {
        $result = $this->validator->sanitizeEdit([
            'on_block' => ['0', '-5', '7'],
        ]);

        self::assertSame([7], $result['pids']);
    }

    public function testDeduplicatesPids(): void
    {
        $result = $this->validator->sanitizeEdit([
            'on_block' => ['10', '10', '11'],
        ]);

        self::assertSame([10, 11], $result['pids']);
    }

    public function testTruncatesSeekingNoteTo255Chars(): void
    {
        $longNote = str_repeat('a', 300);

        $result = $this->validator->sanitizeEdit([
            'seeking_note' => $longNote,
        ]);

        self::assertSame(255, mb_strlen($result['seekingNote']));
    }

    public function testTruncatesPerPlayerNoteTo255Chars(): void
    {
        $longNote = str_repeat('b', 300);

        $result = $this->validator->sanitizeEdit([
            'note' => [10 => $longNote],
        ]);

        self::assertSame(255, mb_strlen($result['notes'][10]));
    }

    public function testTrimsNotes(): void
    {
        $result = $this->validator->sanitizeEdit([
            'seeking_note' => '   hello   ',
        ]);

        self::assertSame('hello', $result['seekingNote']);
    }

    public function testOnBlockAsStringIsRejectedWithoutCrash(): void
    {
        $result = $this->validator->sanitizeEdit([
            'on_block' => 'not-an-array',
        ]);

        self::assertSame([], $result['pids']);
    }

    public function testMissingKeysDefaultSafely(): void
    {
        $result = $this->validator->sanitizeEdit([]);

        self::assertSame([], $result['pids']);
        self::assertSame([], $result['notes']);
        self::assertSame('', $result['seekingNote']);
    }

    public function testEmptySeekingNoteStaysEmptyString(): void
    {
        $result = $this->validator->sanitizeEdit([
            'seeking_note' => '',
        ]);

        self::assertSame('', $result['seekingNote']);
    }
}
