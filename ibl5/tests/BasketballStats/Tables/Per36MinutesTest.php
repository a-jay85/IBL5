<?php

declare(strict_types=1);

namespace Tests\BasketballStats\Tables;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use BasketballStats\Tables\Per36Minutes;

/**
 * @covers \BasketballStats\Tables\Per36Minutes
 */
#[AllowMockObjectsWithoutExpectations]
class Per36MinutesTest extends TestCase
{
    private function createMockTeam(): \Team\Team
    {
        $team = $this->createMock(\Team\Team::class);
        $team->color1 = 'FF0000';
        $team->color2 = '0000FF';
        $team->teamid = 1;
        $team->name = 'Test Team';
        return $team;
    }

    /**
     * Characterization pin: render() with no $ariaLabel arg emits table open tag with NO aria-label
     */
    public function testTableOpenTagHasNoAriaLabel(): void
    {
        $mockDb = $this->createMock(\mysqli::class);
        $html = Per36Minutes::render($mockDb, [], $this->createMockTeam(), '', [], '');

        $this->assertStringContainsString('<table class="ibl-data-table team-table responsive-table sortable"', $html);
        $this->assertStringNotContainsString('aria-label', $html);
    }
}
