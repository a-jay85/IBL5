<?php

declare(strict_types=1);

namespace Tests\Team;

use PHPUnit\Framework\TestCase;
use Team\TeamApiHandler;

class TeamApiHandlerTest extends TestCase
{
    // ==================== Display Validation ====================

    public function testExtractValidatedDisplayDefaultsToRatings(): void
    {
        self::assertSame('ratings', TeamApiHandler::extractValidatedDisplay([]));
    }

    public function testExtractValidatedDisplayUsesValidMode(): void
    {
        self::assertSame('total_s', TeamApiHandler::extractValidatedDisplay(['display' => 'total_s']));
        self::assertSame('avg_s', TeamApiHandler::extractValidatedDisplay(['display' => 'avg_s']));
        self::assertSame('split', TeamApiHandler::extractValidatedDisplay(['display' => 'split']));
        self::assertSame('playoffs', TeamApiHandler::extractValidatedDisplay(['display' => 'playoffs']));
        self::assertSame('contracts', TeamApiHandler::extractValidatedDisplay(['display' => 'contracts']));
        self::assertSame('per36mins', TeamApiHandler::extractValidatedDisplay(['display' => 'per36mins']));
        self::assertSame('chunk', TeamApiHandler::extractValidatedDisplay(['display' => 'chunk']));
    }

    public function testExtractValidatedDisplayRejectsInvalidMode(): void
    {
        self::assertSame('ratings', TeamApiHandler::extractValidatedDisplay(['display' => 'bogus']));
        self::assertSame('ratings', TeamApiHandler::extractValidatedDisplay(['display' => '']));
    }

    public function testExtractValidatedDisplayRejectsNonString(): void
    {
        self::assertSame('ratings', TeamApiHandler::extractValidatedDisplay(['display' => 42]));
    }

    // ==================== Year Validation ====================

    public function testExtractValidatedYrReturnsNullWhenMissing(): void
    {
        self::assertNull(TeamApiHandler::extractValidatedYr([]));
    }

    public function testExtractValidatedYrAcceptsYearOnly(): void
    {
        self::assertSame('2024', TeamApiHandler::extractValidatedYr(['yr' => '2024']));
    }

    public function testExtractValidatedYrAcceptsYearMonth(): void
    {
        self::assertSame('2024-01', TeamApiHandler::extractValidatedYr(['yr' => '2024-01']));
    }

    public function testExtractValidatedYrRejectsInvalidFormat(): void
    {
        self::assertNull(TeamApiHandler::extractValidatedYr(['yr' => 'abc']));
        self::assertNull(TeamApiHandler::extractValidatedYr(['yr' => '24']));
        self::assertNull(TeamApiHandler::extractValidatedYr(['yr' => '2024-1']));
        self::assertNull(TeamApiHandler::extractValidatedYr(['yr' => '2024-123']));
    }

    public function testExtractValidatedYrRejectsEmpty(): void
    {
        self::assertNull(TeamApiHandler::extractValidatedYr(['yr' => '']));
    }

    public function testExtractValidatedYrRejectsNonString(): void
    {
        self::assertNull(TeamApiHandler::extractValidatedYr(['yr' => 2024]));
    }

    // ==================== Push URL Building ====================

    public function testBuildPushUrlIncludesAllParams(): void
    {
        $url = TeamApiHandler::buildPushUrl(5, 'split', 'home', '2024');
        self::assertSame('modules.php?name=Team&op=team&teamid=5&display=split&split=home&yr=2024', $url);
    }

    public function testBuildPushUrlOmitsSplitWhenNull(): void
    {
        $url = TeamApiHandler::buildPushUrl(3, 'total_s', null, '2024');
        self::assertSame('modules.php?name=Team&op=team&teamid=3&display=total_s&yr=2024', $url);
    }

    public function testBuildPushUrlOmitsYrWhenNull(): void
    {
        $url = TeamApiHandler::buildPushUrl(7, 'ratings', null, null);
        self::assertSame('modules.php?name=Team&op=team&teamid=7&display=ratings', $url);
    }

    // ==================== Unknown-team guard ====================

    public function testIsUnknownTeamFlagsPositiveIdWithNoRow(): void
    {
        self::assertTrue(TeamApiHandler::isUnknownTeam(99999, null));
    }

    public function testIsUnknownTeamAcceptsExistingTeam(): void
    {
        self::assertFalse(TeamApiHandler::isUnknownTeam(1, ['team_name' => 'Metros']));
    }

    public function testIsUnknownTeamTreatsFreeAgentsAsServable(): void
    {
        // teamid=0 → free agents, handled by TeamTableService; never a 404.
        self::assertFalse(TeamApiHandler::isUnknownTeam(0, null));
    }

    public function testIsUnknownTeamTreatsEntireLeagueAsServable(): void
    {
        // teamid=-1 → entire league roster; never a 404.
        self::assertFalse(TeamApiHandler::isUnknownTeam(-1, null));
    }
}
