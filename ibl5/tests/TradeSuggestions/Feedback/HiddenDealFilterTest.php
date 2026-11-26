<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Feedback;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\Engine\ScoredDeal;
use TradeSuggestions\Engine\SideGain;
use TradeSuggestions\Feedback\DealKey;
use TradeSuggestions\Feedback\HiddenDealFilter;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TradeCandidate;

final class HiddenDealFilterTest extends TestCase
{
    public function testDropsOnEitherKeyAndReturnsDropped(): void
    {
        $a = self::deal([10], [20]);
        $b = self::deal([11], [21]);
        $c = self::deal([12], [22]);

        $result = HiddenDealFilter::apply(
            [$a, $b, $c],
            ['deal' => [DealKey::forCandidate($a->candidate)->deal], 'core' => [DealKey::forCandidate($b->candidate)->core]],
        );

        self::assertSame([$c], $result['kept']);
        self::assertSame([$a, $b], $result['dropped']);
    }

    public function testNothingHiddenKeepsEverything(): void
    {
        $a = self::deal([10], [20]);

        $result = HiddenDealFilter::apply([$a], ['deal' => [], 'core' => []]);

        self::assertSame([$a], $result['kept']);
        self::assertSame([], $result['dropped']);
    }

    public function testCoreKeyAlsoHidesAPickVariant(): void
    {
        $plain = self::deal([10], [20]);
        $withPick = new ScoredDeal(
            new TradeCandidate(1, 2, [10], [500], [20], []),
            'one_for_one',
            self::side(1),
            self::side(2),
            [],
            [],
        );

        $result = HiddenDealFilter::apply(
            [$withPick],
            ['deal' => [], 'core' => [DealKey::forCandidate($plain->candidate)->core]],
        );

        self::assertSame([], $result['kept']);
        self::assertSame([$withPick], $result['dropped']);
    }

    /**
     * @param list<int> $userPids
     * @param list<int> $partnerPids
     */
    private static function deal(array $userPids, array $partnerPids): ScoredDeal
    {
        return new ScoredDeal(
            new TradeCandidate(1, 2, $userPids, [], $partnerPids, []),
            'one_for_one',
            self::side(1),
            self::side(2),
            [],
            [],
        );
    }

    private static function side(int $tid): SideGain
    {
        return new SideGain(
            $tid,
            TeamMode::Middle,
            [0 => array_fill_keys(SideGain::KEYS, 0.0)],
            [1.0, 0.0, 0.0, 0.0],
            1.0,
        );
    }
}
