<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\CandidateGenerator;
use TradeSuggestions\SuggestionPick;
use TradeSuggestions\SuggestionPlayer;
use TradeSuggestions\TeamAssets;

class CandidateGeneratorTest extends TestCase
{
    /**
     * v1 only built 1-for-1 swaps and single-pick deals. Every shape the owner
     * asked for must now come out.
     */
    public function testGeneratesEveryDealShape(): void
    {
        $user = new TeamAssets(1, [self::player(11, 70), self::player(12, 60), self::player(13, 58)], [self::pick(101, 20.0), self::pick(102, 10.0)]);
        $partner = new TeamAssets(2, [self::player(21, 72), self::player(22, 61), self::player(23, 57)], [self::pick(201, 20.0), self::pick(202, 10.0)]);

        $shapes = [];
        foreach ((new CandidateGenerator())->generate($user, [$partner]) as $c) {
            $shapes[$c->shape()] = true;
        }

        foreach ([
            '1 player for 1 player',
            '2 players for 1 player',
            '1 player for 2 players',
            '1 player for 1 pick',
            '1 player for 2 picks',
            '1 pick for 1 player',
            '2 picks for 1 player',
            '1 player + 1 pick for 1 player',
            '1 player for 1 player + 1 pick',
        ] as $shape) {
            self::assertArrayHasKey($shape, $shapes, $shape);
        }
    }

    public function testTwoForOneNeedsTheOnePlayerToOutrateBoth(): void
    {
        $user = new TeamAssets(1, [self::player(11, 70), self::player(12, 60)], []);
        $partner = new TeamAssets(2, [self::player(21, 65), self::player(22, 75)], []);

        $twoForOne = [];
        foreach ((new CandidateGenerator())->generate($user, [$partner]) as $c) {
            if (count($c->userPids) === 2) {
                $twoForOne[] = $c->partnerPids;
            }
        }

        self::assertSame([[22]], $twoForOne);
    }

    public function testUntradeablePlayersStayOut(): void
    {
        $locked = new SuggestionPlayer(12, 'Locked', 'SF', 26, 80, false, 1000, 2, 1000, tradeable: false);
        $user = new TeamAssets(1, [self::player(11, 60), $locked], []);
        $partner = new TeamAssets(2, [self::player(21, 60)], []);

        foreach ((new CandidateGenerator())->generate($user, [$partner]) as $c) {
            self::assertNotContains(12, $c->userPids);
        }
    }

    /**
     * v1 capped candidates league-wide, so teams late in tid order got none.
     * The cap is per partner now.
     */
    public function testCapIsPerPartner(): void
    {
        $user = new TeamAssets(1, [self::player(11, 70), self::player(12, 60)], [self::pick(101, 6.0)]);
        $partners = [
            new TeamAssets(3, [self::player(31, 70), self::player(32, 60)], []),
            new TeamAssets(2, [self::player(21, 70), self::player(22, 60)], []),
        ];

        $byPartner = [];
        foreach ((new CandidateGenerator(maxPerPartner: 3))->generate($user, $partners) as $c) {
            $byPartner[$c->partnerTid] = ($byPartner[$c->partnerTid] ?? 0) + 1;
        }

        self::assertSame([2 => 3, 3 => 3], $byPartner);
    }

    public function testSkipsPartnerWithUserTid(): void
    {
        $user = new TeamAssets(1, [self::player(11, 60)], [self::pick(101, 6.0)]);
        $same = new TeamAssets(1, [self::player(21, 60)], [self::pick(201, 6.0)]);

        self::assertSame([], iterator_to_array((new CandidateGenerator())->generate($user, [$same]), false));
    }

    public function testAfterTradeMovesAssetsSalaryAndRosterCount(): void
    {
        $outgoing = self::player(11, 60);
        $keeper = self::player(12, 55);
        $incoming = new SuggestionPlayer(21, 'In', 'SF', 26, 58, false, 1500, 2, 1000);
        $pick = self::pick(101, 6.0);
        $incomingPick = self::pick(201, 1.0);
        $original = new TeamAssets(1, [$outgoing, $keeper], [$pick], 14, 300);

        $after = $original->afterTrade([11], [101], [$incoming], [$incomingPick]);

        self::assertSame([$outgoing, $keeper], $original->players);
        self::assertSame([$keeper, $incoming], $after->players);
        self::assertSame([$incomingPick], $after->picks);
        self::assertSame(14, $after->rosterCount());
        self::assertSame(300 + 1000 - 1500, $after->capRoom);
    }

    private static function player(int $pid, int $ovr): SuggestionPlayer
    {
        return new SuggestionPlayer($pid, 'Player ' . $pid, 'SF', 26, $ovr, false, 1000, 2, 1000);
    }

    private static function pick(int $pickId, float $value): SuggestionPick
    {
        return new SuggestionPick($pickId, 1, 2027, 1, $value);
    }
}
