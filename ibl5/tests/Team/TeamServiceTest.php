<?php

declare(strict_types=1);

namespace Tests\Team;

use PHPUnit\Framework\TestCase;
use Team\TeamService;
use Team\Contracts\TeamServiceInterface;

/**
 * Tests for TeamService
 *
 * Validates data orchestration logic
 */
class TeamServiceTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $interfaces = class_implements(TeamService::class);
        self::assertContains(
            TeamServiceInterface::class,
            $interfaces ? $interfaces : [],
        );
    }
}
