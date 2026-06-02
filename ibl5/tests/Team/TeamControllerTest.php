<?php

declare(strict_types=1);

namespace Tests\Team;

use PHPUnit\Framework\TestCase;
use Team\TeamController;
use Team\Contracts\TeamControllerInterface;

/**
 * TeamControllerTest - Tests for TeamController
 */
class TeamControllerTest extends TestCase
{
    // ============================================
    // INSTANTIATION TESTS
    // ============================================

    public function testImplementsTeamControllerInterface(): void
    {
        self::assertContains(TeamControllerInterface::class, (array) class_implements(TeamController::class));
    }

}
