<?php

declare(strict_types=1);

namespace EngineBundle;

/**
 * Thrown when a sim window yields zero games to simulate, so the builder fails
 * fast in PHP with a clear message rather than emitting a bundle the engine
 * would reject (its {@code ErrNoSchedule}).
 */
final class EmptyScheduleException extends \RuntimeException
{
}
