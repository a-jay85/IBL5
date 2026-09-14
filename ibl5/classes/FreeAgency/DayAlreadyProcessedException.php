<?php

declare(strict_types=1);

namespace FreeAgency;

/**
 * Thrown when a free agency day has already been executed for the active
 * (league, season, day) triple, so the signings transaction aborts rather than
 * replaying writes that are not idempotent once player state has moved on.
 */
final class DayAlreadyProcessedException extends \RuntimeException
{
}
