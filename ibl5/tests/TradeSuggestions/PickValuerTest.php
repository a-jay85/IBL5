<?php

declare(strict_types=1);

namespace Tests\TradeSuggestions;

use PHPUnit\Framework\TestCase;
use TradeSuggestions\PickValuer;

/**
 * @phpstan-import-type DraftSlot from \ProjectedDraftOrder\Contracts\ProjectedDraftOrderServiceInterface
 */
class PickValuerTest extends TestCase
{
    private const int ENDING_YEAR = 2027;

    private PickValuer $valuer;

    protected function setUp(): void
    {
        $this->valuer = new PickValuer();
    }

    public function testCurrentYearFirstRoundScalesBySlot(): void
    {
        $index = $this->valuer->buildSlotIndex(self::draftOrder(28));

        $this->assertSame(30.0, $this->valuer->value(self::ENDING_YEAR, 1, 1, $index, self::ENDING_YEAR));
        $this->assertSame(8.0, $this->valuer->value(self::ENDING_YEAR, 1, 28, $index, self::ENDING_YEAR));
        $this->assertSame(4.0, $this->valuer->value(self::ENDING_YEAR, 2, 1, $index, self::ENDING_YEAR));
        $this->assertSame(1.0, $this->valuer->value(self::ENDING_YEAR, 2, 28, $index, self::ENDING_YEAR));
    }

    public function testFutureAndUnknownSlotUseFlatValues(): void
    {
        $index = $this->valuer->buildSlotIndex(self::draftOrder(28));

        $this->assertSame(14.0, $this->valuer->value(self::ENDING_YEAR + 1, 1, 1, $index, self::ENDING_YEAR));
        $this->assertSame(2.0, $this->valuer->value(self::ENDING_YEAR + 1, 2, 1, $index, self::ENDING_YEAR));
        $this->assertSame(14.0, $this->valuer->value(self::ENDING_YEAR, 1, 99, $index, self::ENDING_YEAR));
    }

    public function testPastYearAndThirdRoundAreWorthless(): void
    {
        $index = $this->valuer->buildSlotIndex(self::draftOrder(28));

        $this->assertSame(0.0, $this->valuer->value(self::ENDING_YEAR - 1, 1, 1, $index, self::ENDING_YEAR));
        $this->assertSame(0.0, $this->valuer->value(self::ENDING_YEAR, 3, 1, $index, self::ENDING_YEAR));

        $picks = $this->valuer->toPicks([
            ['pickid' => 10, 'owner_teamid' => 5, 'teampick_teamid' => 1, 'year' => self::ENDING_YEAR - 1, 'round' => 1],
            ['pickid' => 11, 'owner_teamid' => 5, 'teampick_teamid' => 1, 'year' => self::ENDING_YEAR, 'round' => 3],
            ['pickid' => 12, 'owner_teamid' => 5, 'teampick_teamid' => 1, 'year' => self::ENDING_YEAR, 'round' => 1],
        ], $index, self::ENDING_YEAR);

        $this->assertCount(1, $picks);
        $this->assertSame(12, $picks[0]->pickId);
        $this->assertSame(1, $picks[0]->originalTid);
        $this->assertSame(self::ENDING_YEAR, $picks[0]->year);
        $this->assertSame(1, $picks[0]->round);
        $this->assertSame(30.0, $picks[0]->value);
    }

    public function testSlotIndexUsesListPositionNotPickNumber(): void
    {
        $order = self::draftOrder(3);
        // Round 2 lists pick numbers 29 onward and in a different team order.
        $order['round2'] = [self::slot(29, 3), self::slot(30, 1), self::slot(31, 2)];

        $index = $this->valuer->buildSlotIndex($order);

        $this->assertSame(1, $index['round2'][3]);
        $this->assertSame(2, $index['round2'][1]);
        $this->assertSame(3, $index['round2'][2]);
        $this->assertSame(3, $index['teamCount']);
    }

    /**
     * @return array{round1: list<DraftSlot>, round2: list<DraftSlot>}
     */
    private static function draftOrder(int $teams): array
    {
        $round1 = [];
        $round2 = [];
        for ($tid = 1; $tid <= $teams; $tid++) {
            $round1[] = self::slot($tid, $tid);
            $round2[] = self::slot($teams + $tid, $tid);
        }

        return ['round1' => $round1, 'round2' => $round2];
    }

    /**
     * @return DraftSlot
     */
    private static function slot(int $pick, int $teamId): array
    {
        return [
            'pick' => $pick, 'teamId' => $teamId, 'teamName' => 'Team ' . $teamId, 'wins' => 0, 'losses' => 0,
            'color1' => '000000', 'color2' => 'FFFFFF', 'ownerId' => $teamId, 'ownerName' => 'Team ' . $teamId,
            'ownerColor1' => '000000', 'ownerColor2' => 'FFFFFF', 'isTraded' => false, 'notes' => '',
            'movement' => null, 'player' => '',
        ];
    }
}
