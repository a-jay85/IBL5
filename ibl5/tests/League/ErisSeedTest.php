<?php

declare(strict_types=1);

namespace Tests\League;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\ErisSeed;

#[CoversNothing]
final class ErisSeedTest extends TestCase
{
    public function testResolveReturnsDefaultSeedWhenUnset(): void
    {
        self::assertSame(20261120, ErisSeed::resolve(false));
    }

    public function testResolveReturnsDefaultSeedWhenEmpty(): void
    {
        self::assertSame(20261120, ErisSeed::resolve(''));
    }

    public function testResolveUsesDigitsFromEnvironment(): void
    {
        self::assertSame(7, ErisSeed::resolve('7'));
        self::assertSame(20261121, ErisSeed::resolve('20261121'));
    }

    /** @return array<string, array{string}> */
    public static function nonDigitSeeds(): array
    {
        return [
            'letters' => ['abc'],
            'negative' => ['-5'],
            'leading space' => [' 42'],
            'decimal' => ['4.2'],
        ];
    }

    #[DataProvider('nonDigitSeeds')]
    public function testResolveRejectsNonDigitSeed(string $raw): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ErisSeed::resolve($raw);
    }
}
