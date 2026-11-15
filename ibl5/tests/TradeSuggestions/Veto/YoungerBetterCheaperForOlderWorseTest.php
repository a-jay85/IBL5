<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Veto;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Veto\Fixtures\VetoDealFactory;
use TradeSuggestions\TeamMode;
use TradeSuggestions\Veto\VetoSet;
use TradeSuggestions\Veto\YoungerBetterCheaperForOlderWorse;

final class YoungerBetterCheaperForOlderWorseTest extends TestCase
{
    public function testFiresOnYoungerBetterCheaperPair(): void
    {
        self::assertTrue((new YoungerBetterCheaperForOlderWorse())->fires(VetoDealFactory::youngerBetterCheaper()['deal']));
    }

    public function testEqualAgeDoesNotFire(): void
    {
        $deal = self::pair(25, 25, 70.0, 45.0, 300, 400);

        self::assertFalse((new YoungerBetterCheaperForOlderWorse())->fires($deal));
    }

    public function testOlderGivenPlayerDoesNotFire(): void
    {
        $deal = self::pair(26, 25, 70.0, 45.0, 300, 400);

        self::assertFalse((new YoungerBetterCheaperForOlderWorse())->fires($deal));
    }

    public function testEqualAverageSalaryFires(): void
    {
        $deal = self::pair(22, 33, 70.0, 45.0, 300, 300);

        self::assertTrue((new YoungerBetterCheaperForOlderWorse())->fires($deal));
    }

    public function testDearerGivenPlayerDoesNotFire(): void
    {
        $deal = self::pair(22, 33, 70.0, 45.0, 301, 300);

        self::assertFalse((new YoungerBetterCheaperForOlderWorse())->fires($deal));
    }

    public function testMixedDominanceDoesNotFire(): void
    {
        $deal = self::pair(22, 33, 50.0, 50.0, 300, 400);

        self::assertFalse((new YoungerBetterCheaperForOlderWorse())->fires($deal));
    }

    /**
     * The first pair (7 for 7) is younger but mixed on dominance, so it is
     * passed over; the second pair (8 for 7) qualifies and the veto fires.
     */
    public function testNonDominantEarlierPairDoesNotHideALaterQualifyingPair(): void
    {
        $deal = VetoDealFactory::deal(
            [TeamMode::Middle, TeamMode::Middle],
            [
                7 => ['age' => 22, 'base' => 50.0, 'salary' => [1 => 300, 2 => 300]],
                8 => ['age' => 22, 'base' => 70.0, 'salary' => [1 => 300, 2 => 300]],
            ],
            [7 => ['age' => 33, 'base' => 50.0, 'salary' => [1 => 400, 2 => 400]]],
            [VetoDealFactory::uid(7), VetoDealFactory::uid(8)],
            [],
            [VetoDealFactory::pid(2, 7)],
            [],
        )['deal'];

        self::assertSame(VetoDealFactory::uid(7), $deal->user->sent[0]->pid);
        self::assertFalse((new YoungerBetterCheaperForOlderWorse())->fires(self::pair(22, 33, 50.0, 50.0, 300, 400)));
        self::assertTrue((new YoungerBetterCheaperForOlderWorse())->fires($deal));
    }

    /**
     * The first pair (7 for 7) sends an older player, so it is passed over;
     * the second pair (8 for 7) qualifies and the veto fires.
     */
    public function testOlderEarlierPairDoesNotHideALaterQualifyingPair(): void
    {
        $deal = VetoDealFactory::deal(
            [TeamMode::Middle, TeamMode::Middle],
            [
                7 => ['age' => 34, 'base' => 70.0, 'salary' => [1 => 300, 2 => 300]],
                8 => ['age' => 22, 'base' => 70.0, 'salary' => [1 => 300, 2 => 300]],
            ],
            [7 => ['age' => 33, 'base' => 45.0, 'salary' => [1 => 400, 2 => 400]]],
            [VetoDealFactory::uid(7), VetoDealFactory::uid(8)],
            [],
            [VetoDealFactory::pid(2, 7)],
            [],
        )['deal'];

        self::assertSame(VetoDealFactory::uid(7), $deal->user->sent[0]->pid);
        self::assertTrue((new YoungerBetterCheaperForOlderWorse())->fires($deal));
    }

    public function testQuietFixtureDoesNotFire(): void
    {
        self::assertFalse((new YoungerBetterCheaperForOlderWorse())->fires(VetoDealFactory::quiet()['deal']));
    }

    public function testOtherVetoesQuietOnFixture(): void
    {
        $report = VetoSet::standard()->evaluate(VetoDealFactory::youngerBetterCheaper()['deal']);

        self::assertSame(['YOUNGER_BETTER_CHEAPER_FOR_OLDER_WORSE'], $report->firedIds());
    }

    /**
     * The user (Middle) swaps its backup SF for the partner's backup SF.
     */
    private static function pair(int $givenAge, int $gotAge, float $givenBase, float $gotBase, int $givenSalary, int $gotSalary): \TradeSuggestions\Veto\DealView
    {
        return VetoDealFactory::deal(
            [TeamMode::Middle, TeamMode::Middle],
            [7 => ['age' => $givenAge, 'base' => $givenBase, 'salary' => [1 => $givenSalary, 2 => $givenSalary]]],
            [7 => ['age' => $gotAge, 'base' => $gotBase, 'salary' => [1 => $gotSalary, 2 => $gotSalary]]],
            [VetoDealFactory::uid(7)],
            [],
            [VetoDealFactory::pid(2, 7)],
            [],
        )['deal'];
    }
}
