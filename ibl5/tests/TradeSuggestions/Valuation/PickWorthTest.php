<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions\Valuation;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\TeamMode;
use TradeSuggestions\Valuation\DraftSlotIndex;
use TradeSuggestions\Valuation\GainWeights;
use TradeSuggestions\Valuation\PickWorth;

/**
 * @phpstan-import-type DraftSlot from \ProjectedDraftOrder\Contracts\ProjectedDraftOrderServiceInterface
 */
final class PickWorthTest extends TestCase
{
    public function testIndexUsesListPositionNotPickNumber(): void
    {
        $index = DraftSlotIndex::fromDraftOrder([
            'round1' => [self::slot(7, 4), self::slot(9, 2)],
            'round2' => [self::slot(35, 2), self::slot(40, 4), self::slot(41, 6)],
        ]);

        self::assertSame(1, $index->position(1, 4));
        self::assertSame(2, $index->position(1, 2));
        self::assertSame(1, $index->position(2, 2));
        self::assertSame(2, $index->position(2, 4));
        self::assertSame(2, $index->teamCount);
        self::assertNull($index->position(1, 99));
        self::assertNull($index->position(2, 99));
        self::assertNull($index->position(3, 4));
        self::assertNull($index->position(0, 4));
    }

    public function testEmptyDraftOrderGivesNullPositionAndZeroTeamCount(): void
    {
        $index = DraftSlotIndex::fromDraftOrder(['round1' => [], 'round2' => []]);

        self::assertNull($index->position(1, 4));
        self::assertSame(0, $index->teamCount);
        self::assertSame(2.5, PickWorth::worth(1, 0, 4, $index, TeamMode::Rebuild, 5, 1));
    }

    public function testSingleTeamIndexDoesNotDivideByZero(): void
    {
        self::assertSame(0.5, PickWorth::slotT(1, 1));
        self::assertSame(0.5, PickWorth::slotT(1, 0));
        self::assertSame(1.0, PickWorth::slotT(1, 2));
        self::assertSame(0.0, PickWorth::slotT(2, 2));
        self::assertSame(0.5, PickWorth::slotT(null, 28));
    }

    public function testTopPickIsFloorPlusSpreadAndLastPickIsFloor(): void
    {
        self::assertEqualsWithDelta(4.0, PickWorth::basePlaces(1, PickWorth::slotT(1, 28)), 1e-12);
        self::assertEqualsWithDelta(1.0, PickWorth::basePlaces(1, PickWorth::slotT(28, 28)), 1e-12);
        self::assertEqualsWithDelta(0.8, PickWorth::basePlaces(2, PickWorth::slotT(1, 28)), 1e-12);
        self::assertEqualsWithDelta(0.2, PickWorth::basePlaces(2, PickWorth::slotT(28, 28)), 1e-12);
    }

    public function testRoundTwoIsWorthLessThanRoundOneAtEverySlot(): void
    {
        foreach ([1, 14, 28] as $position) {
            $t = PickWorth::slotT($position, 28);
            self::assertLessThan(PickWorth::basePlaces(1, $t), PickWorth::basePlaces(2, $t));
        }
    }

    public function testRoundThreeAndPastYearAreWorthless(): void
    {
        $index = new DraftSlotIndex([4 => 1], [4 => 1], 28);

        self::assertSame(0.0, PickWorth::worth(3, 0, 4, $index, TeamMode::Rebuild, 5, 1));
        self::assertSame(0.0, PickWorth::worth(1, -1, 4, $index, TeamMode::Rebuild, 5, 1));
        self::assertSame(0.0, PickWorth::yearsAwayWeight(TeamMode::Rebuild, -1));
        self::assertSame(1.0, PickWorth::yearsAwayWeight(TeamMode::Rebuild, 0));
    }

    public function testRebuildValuesNearPicksMoreThanWinNow(): void
    {
        $index = new DraftSlotIndex([4 => 1], [4 => 1], 28);

        $rebuild = PickWorth::worth(1, 0, 4, $index, TeamMode::Rebuild, 5, 1);
        $winNow = PickWorth::worth(1, 0, 4, $index, TeamMode::WinNow, 5, 1);

        self::assertEqualsWithDelta(4.0, $rebuild, 1e-12);
        self::assertEqualsWithDelta(2.0, $winNow, 1e-12);
        self::assertGreaterThan(PickWorth::YEARS_AWAY_WEIGHTS['win_now'][0], PickWorth::YEARS_AWAY_WEIGHTS['middle'][0]);
        self::assertGreaterThan(PickWorth::YEARS_AWAY_WEIGHTS['middle'][0], PickWorth::YEARS_AWAY_WEIGHTS['rebuild'][0]);
    }

    public function testYearsAwayBeyondTableUsesLastEntry(): void
    {
        foreach (TeamMode::cases() as $mode) {
            self::assertSame(PickWorth::yearsAwayWeight($mode, 3), PickWorth::yearsAwayWeight($mode, 7));
            self::assertGreaterThan(
                PickWorth::yearsAwayWeight($mode, 3),
                PickWorth::yearsAwayWeight($mode, 2),
            );
        }
        self::assertSame(0.2, PickWorth::yearsAwayWeight(TeamMode::WinNow, 4));
    }

    public function testFutureYearIgnoresSlotIndex(): void
    {
        $index = new DraftSlotIndex([4 => 1], [4 => 1], 28);

        self::assertEqualsWithDelta(
            PickWorth::basePlaces(1, PickWorth::UNKNOWN_SLOT_T) * 0.9,
            PickWorth::worth(1, 1, 4, $index, TeamMode::Rebuild, 5, 1),
            1e-12,
        );
    }

    public function testGlutPenaltyAppliesBeyondOpenSpots(): void
    {
        $index = new DraftSlotIndex([4 => 1], [4 => 1], 28);

        self::assertSame(1.0, PickWorth::glutFactor(1, 1));
        self::assertSame(0.25, PickWorth::glutFactor(2, 1));
        self::assertEqualsWithDelta(1.0, PickWorth::worth(1, 0, 4, $index, TeamMode::Rebuild, 1, 2), 1e-12);
    }

    public function testGlutAtFullRosterPenalisesEveryPick(): void
    {
        self::assertSame(0.25, PickWorth::glutFactor(1, 0));
        self::assertSame(0.25, PickWorth::glutFactor(1, -1));
        self::assertSame(1.0, PickWorth::glutFactor(0, -1));
    }

    public function testModeKeysMatchGainWeightsKeys(): void
    {
        self::assertSame(array_keys(GainWeights::SEASON_WEIGHTS), array_keys(PickWorth::YEARS_AWAY_WEIGHTS));
    }

    /**
     * @return DraftSlot
     */
    private static function slot(int $pick, int $teamId): array
    {
        return [
            'pick' => $pick,
            'teamId' => $teamId,
            'teamName' => '',
            'wins' => 0,
            'losses' => 0,
            'color1' => '',
            'color2' => '',
            'ownerId' => $teamId,
            'ownerName' => '',
            'ownerColor1' => '',
            'ownerColor2' => '',
            'isTraded' => false,
            'notes' => '',
            'movement' => null,
            'player' => '',
        ];
    }
}
