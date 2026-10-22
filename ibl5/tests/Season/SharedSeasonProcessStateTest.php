<?php

declare(strict_types=1);

namespace Tests\Season;

use PHPUnit\Framework\TestCase;
use Season\SharedSeason;

/**
 * Deliberately has no setUp: this fails if the test bootstrap arms the memo
 * or an earlier test leaks an armed state into the process.
 */
final class SharedSeasonProcessStateTest extends TestCase
{
    public function testPhpunitProcessStartsUnarmed(): void
    {
        self::assertFalse(SharedSeason::isEnabled());
    }
}
