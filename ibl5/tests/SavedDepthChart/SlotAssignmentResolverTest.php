<?php

declare(strict_types=1);

namespace Tests\SavedDepthChart;

use PHPUnit\Framework\TestCase;
use SavedDepthChart\SlotAssignmentResolver;

/**
 * @covers \SavedDepthChart\SlotAssignmentResolver
 */
class SlotAssignmentResolverTest extends TestCase
{
    private SlotAssignmentResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new SlotAssignmentResolver();
    }

    /**
     * Twelve depth-chart POST fields for one form slot, each a distinct value,
     * so a mis-mapped key is visibly wrong rather than accidentally equal.
     *
     * Mixed casing is deliberate and load-bearing: pg/sg/sf/pf/c/canPlayInGame/min
     * are lowercase in the submitted form; OF/DF/OI/DI/BH are UPPERCASE.
     *
     * @return array<string, int>
     */
    private function slotFields(int $slot, int $base): array
    {
        return [
            'pg' . $slot => $base + 1,
            'sg' . $slot => $base + 2,
            'sf' . $slot => $base + 3,
            'pf' . $slot => $base + 4,
            'c' . $slot => $base + 5,
            'canPlayInGame' . $slot => $base + 6,
            'min' . $slot => $base + 7,
            'OF' . $slot => $base + 8,
            'DF' . $slot => $base + 9,
            'OI' . $slot => $base + 10,
            'DI' . $slot => $base + 11,
            'BH' . $slot => $base + 12,
        ];
    }

    // ── Case 1: loop bound of the pid strategy ──────────────────────────────

    public function testPidMatchAtSlotFifteenIsFound(): void
    {
        // Player sits at ordinal 1, but its pid is submitted in slot 15.
        // Mutant `$i < 15` → pid loop misses → ordinal fallback returns slot 1 → 201.
        $post = ['pid15' => 700, 'Name1' => 'Someone Else']
            + $this->slotFields(15, 100)
            + $this->slotFields(1, 200);

        $result = $this->resolver->resolveSlotSettings(
            ['pid' => 700, 'name' => 'Zed'],
            $post,
            1
        );

        $this->assertNotNull($result);
        $this->assertSame(101, $result['pg']);
    }

    // ── Case 2: loop bound of the name strategy ─────────────────────────────

    public function testNameMatchAtSlotFifteenIsFound(): void
    {
        // No pid fields at all, so the name loop is the only path to slot 15.
        // Mutant `$i < 15` → name loop misses → ordinal fallback returns slot 1 → 201.
        $post = ['Name15' => 'Zed', 'Name1' => 'Someone Else']
            + $this->slotFields(15, 100)
            + $this->slotFields(1, 200);

        $result = $this->resolver->resolveSlotSettings(
            ['pid' => 700, 'name' => 'Zed'],
            $post,
            1
        );

        $this->assertNotNull($result);
        $this->assertSame(101, $result['pg']);
    }

    // ── Case 4: the empty-name guard ────────────────────────────────────────

    public function testEmptySubmittedNameDoesNotMatchAnEmptyPlayerName(): void
    {
        // Player has neither pid nor name; both submitted Name fields are ''.
        // With the `$postName !== ''` guard the name loop matches nothing and the
        // ordinal fallback returns slot 2. Mutant (guard removed) matches Name1
        // against the empty player name and returns slot 1 → 101.
        $post = ['Name1' => '', 'Name2' => '']
            + $this->slotFields(1, 100)
            + $this->slotFields(2, 200);

        $result = $this->resolver->resolveSlotSettings([], $post, 2);

        $this->assertNotNull($result);
        $this->assertSame(201, $result['pg']);
    }

    // ── Case 5: strip_tags ──────────────────────────────────────────────────

    public function testHtmlTagsInSubmittedNameAreStripped(): void
    {
        // Interesting slot is 3; player ordinal is 1 and Name1 exists, so a
        // surviving mutant (strip_tags removed) falls through to slot 1 → 101.
        $post = ['Name1' => 'Bob', 'Name3' => '<b>Alice</b>']
            + $this->slotFields(1, 100)
            + $this->slotFields(3, 300);

        $result = $this->resolver->resolveSlotSettings(
            ['pid' => 500, 'name' => 'Alice'],
            $post,
            1
        );

        $this->assertNotNull($result);
        $this->assertSame(301, $result['pg']);
    }

    // ── Case 6: trim (separate from case 5 on purpose) ──────────────────────

    public function testWhitespacePaddedSubmittedNameIsTrimmed(): void
    {
        // Same shape as case 5 but exercising trim() alone — no tags involved,
        // so removing strip_tags cannot mask a removed trim() or vice versa.
        $post = ['Name1' => 'Bob', 'Name3' => '  Alice  ']
            + $this->slotFields(1, 100)
            + $this->slotFields(3, 300);

        $result = $this->resolver->resolveSlotSettings(
            ['pid' => 500, 'name' => 'Alice'],
            $post,
            1
        );

        $this->assertNotNull($result);
        $this->assertSame(301, $result['pg']);
    }

    // ── Case 7: strategy precedence ─────────────────────────────────────────

    public function testPidMatchWinsOverNameMatchWhenTheyPointToDifferentSlots(): void
    {
        // pid says slot 4, name says slot 2. pid must win.
        // Mutant that reorders the strategy chain (or drops the pid loop) → 201.
        $post = ['pid4' => 500, 'Name2' => 'Alice']
            + $this->slotFields(4, 400)
            + $this->slotFields(2, 200);

        $result = $this->resolver->resolveSlotSettings(
            ['pid' => 500, 'name' => 'Alice'],
            $post,
            1
        );

        $this->assertNotNull($result);
        $this->assertSame(401, $result['pg']);
    }

    // ── Case 8: duplicate names + fall-through ──────────────────────────────

    public function testDuplicateRosterNamesBothTakeTheFirstMatchingSlotAndUnmatchedPlayerFallsThrough(): void
    {
        // No pid fields — the name loop is the only strategy that can fire.
        // Documented pre-existing behavior: BOTH same-named players resolve to the
        // FIRST matching Name index (slot 1). A mutant that scans the name loop in
        // reverse, or starts it at $ordinal, sends the second player to slot 2 → 201.
        $post = ['Name1' => 'Alice', 'Name2' => 'Alice']
            + $this->slotFields(1, 100)
            + $this->slotFields(2, 200);

        $first = $this->resolver->resolveSlotSettings(['pid' => 500, 'name' => 'Alice'], $post, 1);
        $second = $this->resolver->resolveSlotSettings(['pid' => 501, 'name' => 'Alice'], $post, 2);
        // Third player matches nothing and has no Name3 → falls through to null.
        $third = $this->resolver->resolveSlotSettings(['pid' => 502, 'name' => 'Zoe'], $post, 3);

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame(101, $first['pg']);
        $this->assertSame(101, $second['pg']);
        $this->assertNull($third);
    }

    // ── Case 9: over-full chart (16th player) ───────────────────────────────

    public function testSixteenthPlayerOnAnOverFullChartResolvesToNull(): void
    {
        // All 15 slots submitted and occupied by other players; this player is 16th.
        $post = [];
        for ($i = 1; $i <= 15; $i++) {
            $post['pid' . $i] = $i;
            $post['Name' . $i] = 'Player ' . $i;
            $post += $this->slotFields($i, $i * 10);
        }

        $result = $this->resolver->resolveSlotSettings(
            ['pid' => 999, 'name' => 'Sixteenth Man'],
            $post,
            16
        );

        $this->assertNull($result);
    }

    // ── Case 10: empty POST ─────────────────────────────────────────────────

    public function testEmptyPostDataResolvesToNull(): void
    {
        $result = $this->resolver->resolveSlotSettings(
            ['pid' => 500, 'name' => 'Alice'],
            [],
            1
        );

        $this->assertNull($result);
    }

    // ── Case 11: the full 12-key mapping, including field-name casing ───────

    public function testEveryDepthChartKeyMapsFromTheMatchedSlot(): void
    {
        // assertSame on the whole array kills any key-rename or casing mutant
        // (e.g. 'OF' -> 'of', 'canPlayInGame' -> 'canplayingame').
        $post = ['pid7' => 500] + $this->slotFields(7, 700);

        $result = $this->resolver->resolveSlotSettings(
            ['pid' => 500, 'name' => 'Alice'],
            $post,
            1
        );

        $this->assertSame([
            'pg' => 701,
            'sg' => 702,
            'sf' => 703,
            'pf' => 704,
            'c' => 705,
            'canPlayInGame' => 706,
            'min' => 707,
            'of' => 708,
            'df' => 709,
            'oi' => 710,
            'di' => 711,
            'bh' => 712,
        ], $result);
    }

    // ── Case 12: extractIntFromPost coercion, incl. negative paths ──────────

    public function testNonNumericMissingAndNullPostValuesCoerceToZero(): void
    {
        $post = [
            'pid2' => 500,
            'pg2' => '12',    // numeric string -> 12
            'sg2' => 'abc',   // non-numeric string -> 0
            'sf2' => null,    // null -> 0 (?? fires)
            'pf2' => 7,       // int passes through
            'c2' => 1.9,      // float is NOT handled by extractIntFromPost -> 0
            // min2 / OF2 / DF2 / OI2 / DI2 / BH2 absent -> 0
        ];

        $result = $this->resolver->resolveSlotSettings(
            ['pid' => 500, 'name' => 'Alice'],
            $post,
            1
        );

        $this->assertNotNull($result);
        $this->assertSame(12, $result['pg']);
        $this->assertSame(0, $result['sg']);
        $this->assertSame(0, $result['sf']);
        $this->assertSame(7, $result['pf']);
        $this->assertSame(0, $result['c']);
        $this->assertSame(0, $result['min']);
        $this->assertSame(0, $result['bh']);
    }

    // ── Case 13: pid comparison is int-normalized on both sides ─────────────

    public function testNumericStringPidInPostStillMatchesAnIntegerPlayerPid(): void
    {
        // Form fields arrive as strings. Mutant that drops toInt() around the POST
        // value makes '500' === 500 false → falls through to slot 1 → 101.
        $post = ['pid3' => '500', 'Name1' => 'Someone Else']
            + $this->slotFields(3, 300)
            + $this->slotFields(1, 100);

        $result = $this->resolver->resolveSlotSettings(
            ['pid' => 500, 'name' => 'Alice'],
            $post,
            1
        );

        $this->assertNotNull($result);
        $this->assertSame(301, $result['pg']);
    }
}
