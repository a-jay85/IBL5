<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\CandidateGenerator;
use TradeSuggestions\SuggestionPick;
use TradeSuggestions\SuggestionPlayer;
use TradeSuggestions\TeamAssets;
use TradeSuggestions\TradeCandidate;

class CandidateGeneratorTest extends TestCase
{
    public function testGeneratesThreeShapesInFixedOrder(): void
    {
        $user = new TeamAssets(1, [self::player(11, 20), self::player(12, 15)], [self::pick(101, 6.0)]);
        $partner = new TeamAssets(2, [self::player(21, 20), self::player(22, 15)], [self::pick(201, 6.0)]);

        $candidates = (new CandidateGenerator())->generate($user, [$partner]);

        $this->assertCount(8, $candidates);
        $shapes = array_map(static fn (TradeCandidate $c): array => [$c->userPids, $c->userPickIds, $c->partnerPids, $c->partnerPickIds], $candidates);
        $this->assertSame([
            [[11], [], [21], []],
            [[11], [], [22], []],
            [[12], [], [21], []],
            [[12], [], [22], []],
            [[11], [], [], [201]],
            [[12], [], [], [201]],
            [[], [101], [21], []],
            [[], [101], [22], []],
        ], $shapes);
        foreach ($candidates as $candidate) {
            $this->assertSame(1, $candidate->userTid);
            $this->assertSame(2, $candidate->partnerTid);
        }
    }

    public function testPoolTruncatesToTopPlayersByQuality(): void
    {
        $players = [];
        for ($pid = 1; $pid <= 10; $pid++) {
            $players[] = self::player($pid, 30 - $pid);
        }
        $user = new TeamAssets(1, $players, []);
        $partner = new TeamAssets(2, [self::player(50, 10)], []);

        $candidates = (new CandidateGenerator())->generate($user, [$partner]);

        $userPids = array_values(array_unique(array_merge(...array_map(static fn (TradeCandidate $c): array => $c->userPids, $candidates))));
        sort($userPids);
        $this->assertSame([1, 2, 3, 4, 5, 6, 7, 8], $userPids);
    }

    public function testStopsAtMaxCandidates(): void
    {
        $user = new TeamAssets(1, [self::player(11, 20), self::player(12, 15)], [self::pick(101, 6.0)]);
        $partner = new TeamAssets(2, [self::player(21, 20), self::player(22, 15)], [self::pick(201, 6.0)]);

        $candidates = (new CandidateGenerator(maxCandidates: 5))->generate($user, [$partner]);

        $this->assertCount(5, $candidates);
    }

    public function testSkipsPartnerWithUserTid(): void
    {
        $user = new TeamAssets(1, [self::player(11, 20)], [self::pick(101, 6.0)]);
        $same = new TeamAssets(1, [self::player(21, 20)], [self::pick(201, 6.0)]);

        $this->assertSame([], (new CandidateGenerator())->generate($user, [$same]));
    }

    public function testAfterTradeMovesAssetsWithoutMutatingOriginal(): void
    {
        $outgoing = self::player(11, 20);
        $keeper = self::player(12, 15);
        $incoming = self::player(21, 18);
        $pick = self::pick(101, 6.0);
        $incomingPick = self::pick(201, 1.0);
        $original = new TeamAssets(1, [$outgoing, $keeper], [$pick]);

        $after = $original->afterTrade([11], [101], [$incoming], [$incomingPick]);

        $this->assertSame([$outgoing, $keeper], $original->players);
        $this->assertSame([$pick], $original->picks);
        $this->assertSame([$keeper, $incoming], $after->players);
        $this->assertSame([$incomingPick], $after->picks);
        $this->assertSame(1, $after->tid);
    }

    private static function player(int $pid, int $quality): SuggestionPlayer
    {
        return new SuggestionPlayer($pid, 'Player ' . $pid, 'SF', 26, $quality, false, 1000, 2, 1000);
    }

    private static function pick(int $pickId, float $value): SuggestionPick
    {
        return new SuggestionPick($pickId, 1, 2027, 1, $value);
    }
}
