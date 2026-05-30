<?php

declare(strict_types=1);

namespace EngineBundle;

/**
 * Thrown when no rosterable players are found, so the builder fails fast rather
 * than emitting a bundle the engine would reject (its {@code ErrEmptyRoster}).
 */
final class EmptyRosterException extends \RuntimeException
{
}
