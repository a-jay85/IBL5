<?php

declare(strict_types=1);

namespace Tests\Team;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Team\SplitStatsRepository;
use Tests\WideUnit\Mocks\MockDatabase;

final class SplitStatsRepositoryResolveDisplayAndSplitTest extends TestCase
{
    private SplitStatsRepository $repo;

    protected function setUp(): void
    {
        $this->repo = new SplitStatsRepository(new MockDatabase());
    }

    /**
     * @return iterable<string, array{string, mixed, string, ?string}>
     */
    public static function provideCases(): iterable
    {
        yield 'valid home' => ['split', 'home', 'split', 'home'];
        yield 'valid dynamic month' => ['split', 'month_11', 'split', 'month_11'];
        yield 'valid dynamic vs team' => ['split', 'vs_1', 'split', 'vs_1'];
        yield 'unknown key' => ['split', 'bogus_key', 'ratings', null];
        yield 'empty string' => ['split', '', 'ratings', null];
        yield 'case mismatch' => ['split', 'HOME', 'ratings', null];
        yield 'missing (null)' => ['split', null, 'ratings', null];
        yield 'array value' => ['split', ['home'], 'ratings', null];
        yield 'int value' => ['split', 0, 'ratings', null];
        yield 'bool true' => ['split', true, 'ratings', null];
        yield 'passthrough contracts' => ['contracts', 'home', 'contracts', null];
        yield 'passthrough ratings with junk' => ['ratings', 'bogus_key', 'ratings', null];
        yield 'passthrough unvalidated display' => ['not_a_mode', 'home', 'not_a_mode', null];
    }

    #[DataProvider('provideCases')]
    public function testResolvesTruthTable(string $display, mixed $rawSplit, string $expectedDisplay, ?string $expectedSplit): void
    {
        self::assertSame([$expectedDisplay, $expectedSplit], $this->repo->resolveDisplayAndSplit($display, $rawSplit));
    }

    public function testEveryValidKeyRoundTrips(): void
    {
        foreach ($this->repo->getValidSplitKeys() as $key) {
            self::assertSame(['split', $key], $this->repo->resolveDisplayAndSplit('split', $key));
        }

        self::assertGreaterThan(40, count($this->repo->getValidSplitKeys()));
    }

    public function testRejectsKeyWithTrailingWhitespace(): void
    {
        self::assertSame(['ratings', null], $this->repo->resolveDisplayAndSplit('split', 'home '));
    }
}
