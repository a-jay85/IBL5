<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\LaughTest\Fixtures;

use Tests\TradeSuggestions\Engine\Fixtures\SnapshotFactory;
use Tests\TradeSuggestions\Fixtures\LaughTestRows;
use TradeSuggestions\Engine\DealScorer;
use TradeSuggestions\Engine\LeagueSnapshot;
use TradeSuggestions\Engine\LeagueSnapshotBuilder;
use TradeSuggestions\Engine\LeagueSnapshotInputs;
use TradeSuggestions\Engine\ScoredDeal;
use TradeSuggestions\Projection\ProjectionPlayer;
use TradeSuggestions\TeamMode;
use TradeSuggestions\TradeCandidate;
use TradeSuggestions\Veto\DealView;

/**
 * Puts the `LaughTestRows` players into a v3 league: team 1 (WinNow) has
 * Dupree and two round-2 picks, team 2 (Rebuild) has Laettner, and the other
 * 26 teams are the `SnapshotFactory` league at 14 a team. This is the only
 * place the Dupree deal is spelled.
 */
final class LaughTestFactory
{
    public const string SHAPE_ID = 'ONE_FOR_ONE';

    private const array STARTER_SLOTS = ['PG', 'SG', 'SF', 'PF', 'C'];

    public static function league(): LeagueSnapshot
    {
        $in = SnapshotFactory::inputs(perTeam: 14, overrideRosters: [
            LaughTestRows::USER_TID => self::roster(LaughTestRows::USER_TID, LaughTestRows::dupree(), 'SF'),
            LaughTestRows::PARTNER_TID => self::roster(LaughTestRows::PARTNER_TID, LaughTestRows::laettner(), 'PF'),
        ]);
        $pickRows = $in->pickRows;
        $pickRows[LaughTestRows::USER_TID] = LaughTestRows::picks();
        $pickRows[LaughTestRows::PARTNER_TID] = [];

        return LeagueSnapshotBuilder::build(new LeagueSnapshotInputs(
            seasonYear: $in->seasonYear,
            advancesContractYears: $in->advancesContractYears,
            regularSeason: $in->regularSeason,
            playersByTid: $in->playersByTid,
            scale: $in->scale,
            standings: $in->standings,
            streaks: $in->streaks,
            teamFactors: $in->teamFactors,
            cashByTeamAndOffset: $in->cashByTeamAndOffset,
            pickRows: $pickRows,
            draftOrder: $in->draftOrder,
            modes: array_replace($in->modes, [
                LaughTestRows::USER_TID => TeamMode::WinNow,
                LaughTestRows::PARTNER_TID => TeamMode::Rebuild,
            ]),
            teamNames: $in->teamNames,
        ));
    }

    /**
     * Team 1 sends Dupree and both picks for Laettner.
     */
    public static function candidate(): TradeCandidate
    {
        return new TradeCandidate(
            LaughTestRows::USER_TID,
            LaughTestRows::PARTNER_TID,
            [LaughTestRows::DUPREE_PID],
            self::pickIds(),
            [LaughTestRows::LAETTNER_PID],
            [],
        );
    }

    public static function scored(LeagueSnapshot $s, ?TradeCandidate $c = null): ScoredDeal
    {
        return DealScorer::score($s, $c ?? self::candidate(), self::SHAPE_ID);
    }

    public static function dupreeForLaettner(LeagueSnapshot $s): DealView
    {
        return DealView::fromScoredDeal($s, self::scored($s));
    }

    /**
     * @return list<int>
     */
    public static function pickIds(): array
    {
        return array_map(static fn (array $row): int => $row['pickid'], LaughTestRows::picks());
    }

    /**
     * The star starts at `$starSlot`; the first filler at each other slot
     * starts there; the rest come off the bench.
     *
     * @param array{pid: int, name: string, pos: string, age: int, cy: int, salaries: list<int>, ratings: array<string, int>, injured: int, statsPts: int, minutes: int} $star
     * @return list<ProjectionPlayer>
     */
    private static function roster(int $tid, array $star, string $starSlot): array
    {
        $players = [LaughTestRows::toProjectionPlayer($star, $tid, [$starSlot => 1], 32)];
        $started = [$starSlot => true];
        $benchDepth = [];
        foreach (LaughTestRows::fillers($tid) as $spec) {
            $slot = $spec['pos'];
            if (in_array($slot, self::STARTER_SLOTS, true) && !isset($started[$slot])) {
                $started[$slot] = true;
                $players[] = LaughTestRows::toProjectionPlayer($spec, $tid, [$slot => 1], 30);
                continue;
            }
            $depthSlot = in_array($slot, self::STARTER_SLOTS, true) ? $slot : self::STARTER_SLOTS[count($players) % 5];
            $benchDepth[$depthSlot] = ($benchDepth[$depthSlot] ?? 1) + 1;
            $players[] = LaughTestRows::toProjectionPlayer($spec, $tid, [$depthSlot => $benchDepth[$depthSlot]], 10);
        }

        return $players;
    }
}
