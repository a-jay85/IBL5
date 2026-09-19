<?php

declare(strict_types=1);

namespace Updater\SeasonRollover;

/**
 * Outcome of the season-rollover decision function.
 *
 * NoOp    = archive season matches the settings season; change nothing.
 * Advance = archive season is exactly settings + 1; write year + phase.
 * Halt    = anything else (backwards, gap of 2+, implausible year, unreadable
 *           archive metadata); write nothing and surface the reason.
 */
enum RolloverOutcome: string
{
    case NoOp = 'no-op';
    case Advance = 'advance';
    case Halt = 'halt';
}
