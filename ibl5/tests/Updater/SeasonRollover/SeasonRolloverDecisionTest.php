<?php

declare(strict_types=1);

namespace Tests\Updater\SeasonRollover;

use PHPUnit\Framework\TestCase;
use Updater\SeasonRollover\RolloverOutcome;
use Updater\SeasonRollover\SeasonRolloverDecision;

final class SeasonRolloverDecisionTest extends TestCase
{
    public function testNoOpWhenArchiveMatchesCurrentSeason(): void
    {
        $result = SeasonRolloverDecision::decide(2025, 2025, 'Regular Season');

        self::assertSame(RolloverOutcome::NoOp, $result->outcome);
        self::assertNull($result->targetYear);
        self::assertNull($result->targetPhase);
    }

    public function testAdvanceWhenArchiveIsOneAhead(): void
    {
        $result = SeasonRolloverDecision::decide(2025, 2026, 'Preseason');

        self::assertSame(RolloverOutcome::Advance, $result->outcome);
        self::assertSame(2026, $result->targetYear);
        self::assertSame('Preseason', $result->targetPhase);
        self::assertTrue($result->shouldWrite());
    }

    public function testHaltWhenArchiveIsOneBehind(): void
    {
        $result = SeasonRolloverDecision::decide(2025, 2024, 'Playoffs');

        self::assertSame(RolloverOutcome::Halt, $result->outcome);
        self::assertNull($result->targetYear);
        self::assertFalse($result->shouldWrite());
    }

    public function testHaltWhenGapIsTwo(): void
    {
        $result = SeasonRolloverDecision::decide(2025, 2027, 'Preseason');

        self::assertSame(RolloverOutcome::Halt, $result->outcome);
        self::assertNull($result->targetYear);
    }

    public function testHaltWhenYearBelowPlausibleRange(): void
    {
        $result = SeasonRolloverDecision::decide(2025, 0, 'Preseason');

        self::assertSame(RolloverOutcome::Halt, $result->outcome);
        self::assertStringContainsString('plausible range', $result->reason);
    }

    public function testHaltWhenYearAbovePlausibleRange(): void
    {
        $result = SeasonRolloverDecision::decide(2025, 2101, 'Preseason');

        self::assertSame(RolloverOutcome::Halt, $result->outcome);
        self::assertStringContainsString('plausible range', $result->reason);
    }

    public function testHaltWhenPhaseIsWhitespaceOnly(): void
    {
        $result = SeasonRolloverDecision::decide(2025, 2026, '   ');

        self::assertSame(RolloverOutcome::Halt, $result->outcome);
        self::assertNull($result->targetYear);
    }

    public function testAdvanceWithTrimsPhase(): void
    {
        $result = SeasonRolloverDecision::decide(2025, 2026, '  Playoffs  ');

        self::assertSame(RolloverOutcome::Advance, $result->outcome);
        self::assertSame(2026, $result->targetYear);
        self::assertSame('Playoffs', $result->targetPhase);
    }

    public function testHaltForDegenerateEmptySettingsCase(): void
    {
        // settingYear=0 + 1 = 1; year 1 is below MIN_PLAUSIBLE_YEAR so this halts on range check
        $result = SeasonRolloverDecision::decide(0, 1, 'Preseason');

        self::assertSame(RolloverOutcome::Halt, $result->outcome);
    }
}
