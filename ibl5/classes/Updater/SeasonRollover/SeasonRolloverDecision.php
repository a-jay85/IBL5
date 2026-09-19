<?php

declare(strict_types=1);

namespace Updater\SeasonRollover;

/**
 * Pure decision function for automatic season rollover.
 *
 * Given the ending year currently stored in `ibl_settings` and the ending year
 * read from the uploaded archive's .lge file, decide whether to advance.
 * Only an exact +1 advances. Everything else halts without writing.
 */
final class SeasonRolloverDecision
{
    /** Lowest plausible .lge ending year; anything below is corrupt data, not a rollover. */
    public const MIN_PLAUSIBLE_YEAR = 1901;

    /** Highest plausible .lge ending year. */
    public const MAX_PLAUSIBLE_YEAR = 2100;

    public static function decide(int $settingYear, int $lgeEndingYear, string $lgePhase): SeasonRolloverDecisionResult
    {
        if ($lgeEndingYear < self::MIN_PLAUSIBLE_YEAR || $lgeEndingYear > self::MAX_PLAUSIBLE_YEAR) {
            return SeasonRolloverDecisionResult::halt(sprintf(
                'Archive season ending year %d is outside the plausible range %d-%d; leaving settings unchanged.',
                $lgeEndingYear,
                self::MIN_PLAUSIBLE_YEAR,
                self::MAX_PLAUSIBLE_YEAR,
            ));
        }

        if ($lgeEndingYear === $settingYear) {
            return SeasonRolloverDecisionResult::noOp(sprintf(
                'Archive season %d matches the current season; no rollover needed.',
                $settingYear,
            ));
        }

        if ($lgeEndingYear === $settingYear + 1) {
            $phase = trim($lgePhase);
            if ($phase === '') {
                return SeasonRolloverDecisionResult::halt(sprintf(
                    'Archive season %d is one ahead of %d but carries no usable phase; leaving settings unchanged.',
                    $lgeEndingYear,
                    $settingYear,
                ));
            }

            return SeasonRolloverDecisionResult::advance($lgeEndingYear, $phase, sprintf(
                'Archive season %d is one ahead of %d; advancing to %d / %s.',
                $lgeEndingYear,
                $settingYear,
                $lgeEndingYear,
                $phase,
            ));
        }

        return SeasonRolloverDecisionResult::halt(sprintf(
            'Archive season %d is not exactly one ahead of the current season %d; leaving settings unchanged.',
            $lgeEndingYear,
            $settingYear,
        ));
    }
}
