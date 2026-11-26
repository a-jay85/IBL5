<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Veto;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\TradeSuggestions\Veto\Fixtures\VetoDealFactory;
use TradeSuggestions\Veto\Contracts\VetoInterface;
use TradeSuggestions\Veto\DealView;
use TradeSuggestions\Veto\VetoSet;

/** @see VetoSet::evaluate() */
final class VetoSetTest extends TestCase
{
    public function testEvaluateCollectsEveryFiredId(): void
    {
        $set = new VetoSet([self::fake('B', true), self::fake('A', true), self::fake('C', false)]);

        self::assertSame(['A', 'B'], $set->evaluate(VetoDealFactory::quiet()['deal'])->firedIds());
    }

    public function testEvaluateWithNoVetoFiringIsNotFired(): void
    {
        $set = new VetoSet([self::fake('A', false), self::fake('B', false)]);

        self::assertFalse($set->evaluate(VetoDealFactory::quiet()['deal'])->fired());
    }

    public function testDuplicateIdsReportedOnce(): void
    {
        $set = new VetoSet([self::fake('A', true), self::fake('A', true)]);

        self::assertSame(['A'], $set->evaluate(VetoDealFactory::quiet()['deal'])->firedIds());
    }

    public function testThrowingVetoPropagates(): void
    {
        $throwing = new class implements VetoInterface {
            public function id(): string
            {
                return 'BOOM';
            }

            public function fires(DealView $deal): bool
            {
                throw new RuntimeException('veto failed');
            }
        };

        $this->expectException(RuntimeException::class);
        (new VetoSet([$throwing]))->evaluate(VetoDealFactory::quiet()['deal']);
    }

    public function testStandardSetHasSixIdsInF15Order(): void
    {
        self::assertSame([
            'YOUNGER_BETTER_CHEAPER_FOR_OLDER_WORSE',
            'WIN_NOW_WORSE_THIS_SEASON',
            'REBUILDER_OLD_LONG_MONEY_UNPAID',
            'PICK_TO_NON_BURDEN_TAKER',
            'ROSTER_OVER_MAX_AFTER',
            'STRONG_FOR_WEAK_WITHOUT_NET_GAIN',
        ], VetoSet::standard()->ids());
    }

    public function testTwoVetoFixtureReportsBothIds(): void
    {
        $report = VetoSet::standard()->evaluate(VetoDealFactory::twoVetoes()['deal']);

        self::assertSame(
            ['WIN_NOW_WORSE_THIS_SEASON', 'YOUNGER_BETTER_CHEAPER_FOR_OLDER_WORSE'],
            $report->firedIds(),
        );
    }

    public function testQuietFixtureFiresNothing(): void
    {
        self::assertFalse(VetoSet::standard()->evaluate(VetoDealFactory::quiet()['deal'])->fired());
    }

    private static function fake(string $id, bool $fires): VetoInterface
    {
        return new class ($id, $fires) implements VetoInterface {
            public function __construct(private readonly string $id, private readonly bool $fires)
            {
            }

            public function id(): string
            {
                return $this->id;
            }

            public function fires(DealView $deal): bool
            {
                return $this->fires;
            }
        };
    }
}
