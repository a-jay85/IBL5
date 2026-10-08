<?php

declare(strict_types=1);

namespace Tests\DataRetention;

use DataRetention\RetentionCliOptionParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RetentionCliOptionParserTest extends TestCase
{
    private const SPEC = ['--days' => 36500, '--dry-run' => null, '--help' => null];

    public function testParseAcceptsEqualsFormIntFlagAndBoolFlag(): void
    {
        self::assertSame(
            ['--days' => 30, '--dry-run' => true],
            RetentionCliOptionParser::parse(['--days=30', '--dry-run'], self::SPEC)
        );
        self::assertSame(
            ['--dry-run' => true, '--days' => 7],
            RetentionCliOptionParser::parse(['--dry-run', '--days=7'], self::SPEC)
        );
        self::assertSame([], RetentionCliOptionParser::parse([], self::SPEC));
    }

    public function testParseRejectsSpaceFormValue(): void
    {
        $this->assertParseFails(['--days', '30'], self::SPEC, [], "flag '--days' requires a value: use --days=N");
    }

    public function testParseRejectsUnknownFlag(): void
    {
        $this->assertParseFails(['--dayz=30'], self::SPEC, [], "unknown flag '--dayz'");
    }

    public function testParseRejectsPositionalArgument(): void
    {
        $this->assertParseFails(['30'], self::SPEC, [], "unexpected argument '30'");
    }

    #[DataProvider('invalidIntValues')]
    public function testParseRejectsNonPositiveOrNonIntegerValue(string $value): void
    {
        $this->assertParseFails(['--days=' . $value], self::SPEC, [], self::outOfRangeMessage($value));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidIntValues(): array
    {
        return [
            'zero' => ['0'],
            'negative' => ['-1'],
            'letters' => ['abc'],
            'decimal' => ['1.5'],
            'empty' => [''],
            'leading zero' => ['07'],
            'leading space' => [' 30'],
            'exponent' => ['1e3'],
        ];
    }

    public function testParseRejectsValueAboveMax(): void
    {
        $this->assertParseFails(['--days=36501'], self::SPEC, [], self::outOfRangeMessage('36501'));
        $this->assertParseFails(
            ['--days=999999999999999999999999999999'],
            self::SPEC,
            [],
            self::outOfRangeMessage('999999999999999999999999999999')
        );
        self::assertSame(['--days' => 36500], RetentionCliOptionParser::parse(['--days=36500'], self::SPEC));
    }

    public function testParseRejectsValueOnBoolFlag(): void
    {
        $this->assertParseFails(['--dry-run=yes'], self::SPEC, [], "flag '--dry-run' takes no value");
    }

    public function testParseRejectsDuplicateFlag(): void
    {
        $this->assertParseFails(['--days=30', '--days=60'], self::SPEC, [], "duplicate flag '--days'");
    }

    public function testParseRejectsMissingRequiredFlag(): void
    {
        $this->assertParseFails(
            ['--confirm'],
            ['--user-id' => 4294967295, '--confirm' => null],
            ['--user-id'],
            "missing required flag '--user-id'"
        );
        self::assertSame(
            ['--user-id' => 5, '--confirm' => true],
            RetentionCliOptionParser::parse(
                ['--user-id=5', '--confirm'],
                ['--user-id' => 4294967295, '--confirm' => null],
                ['--user-id']
            )
        );
    }

    private static function outOfRangeMessage(string $value): string
    {
        return "flag '--days' needs a positive integer up to 36500, got '" . $value . "'";
    }

    /**
     * @param list<string> $args
     * @param array<string, int|null> $spec
     * @param list<string> $required
     */
    private function assertParseFails(array $args, array $spec, array $required, string $expectedMessage): void
    {
        try {
            RetentionCliOptionParser::parse($args, $spec, $required);
        } catch (\InvalidArgumentException $e) {
            self::assertSame($expectedMessage, $e->getMessage());
            return;
        }
        self::fail('Expected InvalidArgumentException for ' . implode(' ', $args));
    }
}
