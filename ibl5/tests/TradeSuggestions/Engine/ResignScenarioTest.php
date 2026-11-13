<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Engine;

use PHPUnit\Framework\TestCase;
use Tests\TradeSuggestions\Engine\Fixtures\SnapshotFactory;
use TradeSuggestions\Engine\DealScorer;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\PairRecordMemo;
use TradeSuggestions\Engine\ResignOdds;
use TradeSuggestions\Engine\SideGain;
use TradeSuggestions\Engine\TeamStateRebuilder;
use TradeSuggestions\TradeCandidate;
use TradeSuggestions\Valuation\ResignLikelihood;

/**
 * F10 scenario mixing in `DealScorer`. The fixture's pid 302 (team 3) is a
 * likely walker on the books at offset 1 and off them at offset 2.
 */
final class ResignScenarioTest extends TestCase
{
    private const int WALKER = 302;

    private static ?LeagueSnapshot $league = null;

    private static function league(): LeagueSnapshot
    {
        $s = self::$league ??= SnapshotFactory::league();
        self::assertTrue($s->likelyToWalk[self::WALKER]);
        self::assertTrue($s->players[self::WALKER]->onRosterAt(1));

        return $s;
    }

    public function testReceiverGainIsLikelihoodWeightedMixOfOutcomes(): void
    {
        $s = self::league();
        $c = new TradeCandidate(1, 3, [], [], [self::WALKER], []);
        $f = self::factor($s, 1);
        $memo = new PairRecordMemo();
        $before = TeamStateRebuilder::before($s, 1, 1);
        $with = SideGain::fromStates($before, TeamStateRebuilder::after($s, $c, 1, $memo)['user'], $s, $c, 1);
        $without = SideGain::fromStates($before, TeamStateRebuilder::after($s, $c, 1, $memo, [self::WALKER])['user'], $s, $c, 1);
        self::assertNotSame($with['starter_slots'], $without['starter_slots']);

        $deal = DealScorer::score($s, $c, 'dump');

        self::assertEqualsWithDelta(
            $f * $with['starter_slots'] + (1 - $f) * $without['starter_slots'],
            $deal->user->components()[1]['starter_slots'],
            1e-9,
        );
        self::assertSame($with['payroll'], $deal->user->components()[1]['payroll']);
    }

    public function testSenderBeforeStateIsMixedWithSenderLikelihood(): void
    {
        $s = self::league();
        $c = new TradeCandidate(3, 1, [self::WALKER], [], [], []);
        $f = self::factor($s, 3);
        $memo = new PairRecordMemo();
        $after = TeamStateRebuilder::after($s, $c, 1, $memo)['user'];
        $with = SideGain::fromStates(TeamStateRebuilder::before($s, 3, 1), $after, $s, $c, 3);
        $without = SideGain::fromStates(TeamStateRebuilder::beforeWithout($s, 3, 1, $memo, [self::WALKER]), $after, $s, $c, 3);
        self::assertNotSame($with['starter_slots'], $without['starter_slots']);

        $deal = DealScorer::score($s, $c, 'dump');

        self::assertEqualsWithDelta(
            $f * $with['starter_slots'] + (1 - $f) * $without['starter_slots'],
            $deal->user->components()[1]['starter_slots'],
            1e-9,
        );
    }

    public function testReceivedWalkerLikelihoodUsesReceivingTeam(): void
    {
        $s = self::league();
        // Team 3 sends the walker, so team 1 is the partner who receives it.
        $c = new TradeCandidate(3, 1, [self::WALKER], [], [], []);
        $odds = ResignOdds::forTeam($s->players[self::WALKER], 1, $s->lineups[0], $s->slotRanks[0], $s->teamFactors);
        self::assertNotSame($s->resignLikelihood[self::WALKER], $odds['likelihood']);
        self::assertTrue($odds['walks']);
        $f = ResignLikelihood::futureValueFactor(1, true, true, $odds['likelihood']);
        self::assertGreaterThan(0.0, $f);
        self::assertLessThan(1.0, $f);
        $memo = new PairRecordMemo();
        $before = TeamStateRebuilder::before($s, 1, 1);
        $walkFactor = [self::WALKER => $f];
        $with = SideGain::fromStates($before, TeamStateRebuilder::after($s, $c, 1, $memo)['partner'], $s, $c, 1, $walkFactor);
        $without = SideGain::fromStates($before, TeamStateRebuilder::after($s, $c, 1, $memo, [self::WALKER])['partner'], $s, $c, 1, $walkFactor);
        self::assertNotSame($with['starter_slots'], $without['starter_slots']);

        $deal = DealScorer::score($s, $c, 'dump');

        self::assertEqualsWithDelta(
            $f * $with['starter_slots'] + (1 - $f) * $without['starter_slots'],
            $deal->partner->components()[1]['starter_slots'],
            1e-9,
        );
        self::assertSame($with['contracts'], $deal->partner->components()[1]['contracts']);
    }

    public function testUnmovedWalkersAreNotMixed(): void
    {
        $s = self::league();
        $other = null;
        foreach (array_keys($s->lineups[0][3]->players) as $pid) {
            if ($pid !== self::WALKER && !($s->likelyToWalk[$pid] ?? false)) {
                $other = $pid;
                break;
            }
        }
        self::assertNotNull($other);
        $c = new TradeCandidate(3, 1, [$other], [], [], []);

        $deal = DealScorer::score($s, $c, 'dump');

        foreach ([1, 2, 3] as $offset) {
            $plain = SideGain::fromStates(
                TeamStateRebuilder::before($s, 3, $offset),
                TeamStateRebuilder::after($s, $c, $offset, new PairRecordMemo())['user'],
                $s,
                $c,
                3,
            );
            self::assertEquals($plain, $deal->user->components()[$offset]);
        }
    }

    public function testOffsetZeroIsNeverMixed(): void
    {
        $s = self::league();
        $c = new TradeCandidate(1, 3, [], [], [self::WALKER], []);
        $plain = SideGain::fromStates(
            TeamStateRebuilder::before($s, 1, 0),
            TeamStateRebuilder::after($s, $c, 0, new PairRecordMemo())['user'],
            $s,
            $c,
            1,
        );

        $deal = DealScorer::score($s, $c, 'dump');

        self::assertEquals($plain, $deal->user->components()[0]);
    }

    /**
     * The F10 factor for the walker as seen by the team that holds him after
     * the trade (`$tid`): the sender's snapshot odds for team 3, the
     * receiving team's own odds otherwise.
     */
    private static function factor(LeagueSnapshot $s, int $tid): float
    {
        $likelihood = $tid === 3
            ? $s->resignLikelihood[self::WALKER]
            : ResignOdds::forTeam($s->players[self::WALKER], $tid, $s->lineups[0], $s->slotRanks[0], $s->teamFactors)['likelihood'];
        $f = ResignLikelihood::futureValueFactor(1, true, true, $likelihood);
        self::assertGreaterThan(0.0, $f);
        self::assertLessThan(1.0, $f);

        return $f;
    }
}
