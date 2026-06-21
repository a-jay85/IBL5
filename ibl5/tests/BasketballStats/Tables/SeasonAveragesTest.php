<?php

declare(strict_types=1);

namespace Tests\BasketballStats\Tables;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use BasketballStats\Tables\SeasonAverages;

/**
 * @covers \BasketballStats\Tables\SeasonAverages
 */
#[AllowMockObjectsWithoutExpectations]
class SeasonAveragesTest extends TestCase
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

    private function createMockDb(): \mysqli
    {
        $mockResult = self::createStub(\mysqli_result::class);
        $mockResult->method('fetch_assoc')->willReturn(null);

        $mockStmt = self::createStub(\mysqli_stmt::class);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('bind_param')->willReturn(true);
        $mockStmt->method('get_result')->willReturn($mockResult);

        $mockDb = self::createStub(\mysqli::class);
        $mockDb->method('prepare')->willReturn($mockStmt);

        return $mockDb;
    }

    /**
     * Characterization pin: render() with no $ariaLabel arg emits table open tag with NO aria-label
     */
    public function testTableOpenTagHasNoAriaLabel(): void
    {
        $html = SeasonAverages::render($this->createMockDb(), [], $this->createMockTeam(), '', [], '');

        $this->assertStringContainsString('<table class="ibl-data-table team-table responsive-table sortable"', $html);
        $this->assertStringNotContainsString('aria-label', $html);
    }
}
