<?php

declare(strict_types=1);

/**
 * BoxscoreTest - Tests for Boxscore class
 */
class BoxscoreTest extends \PHPUnit\Framework\TestCase
{
    // ============================================
    // CONSTANT TESTS
    // ============================================

    public function testPlayerStatementPrepareConstantExists(): void
    {
        $this->assertTrue(defined('Boxscore::PLAYERSTATEMENT_PREPARE'));
    }

    public function testPlayerStatementPrepareContainsInsert(): void
    {
        $this->assertStringContainsString('INSERT INTO ibl_box_scores', \Boxscore::PLAYERSTATEMENT_PREPARE);
    }

    public function testTeamStatementPrepareConstantExists(): void
    {
        $this->assertTrue(defined('Boxscore::TEAMSTATEMENT_PREPARE'));
    }

    public function testTeamStatementPrepareContainsInsert(): void
    {
        $this->assertStringContainsString('INSERT INTO ibl_box_scores_teams', \Boxscore::TEAMSTATEMENT_PREPARE);
    }

    // ============================================
    // INSTANTIATION TESTS
    // ============================================

    public function testCanBeInstantiated(): void
    {
        $boxscore = new \Boxscore();

        $this->assertInstanceOf(\Boxscore::class, $boxscore);
    }

    // ============================================
    // PROPERTY TESTS - Game Date
    // ============================================

    public function testHasGameDateProperty(): void
    {
        $boxscore = new \Boxscore();

        $this->assertTrue(property_exists($boxscore, 'gameDate'));
    }

    public function testHasGameYearProperty(): void
    {
        $boxscore = new \Boxscore();

        $this->assertTrue(property_exists($boxscore, 'gameYear'));
    }

    public function testHasGameMonthProperty(): void
    {
        $boxscore = new \Boxscore();

        $this->assertTrue(property_exists($boxscore, 'gameMonth'));
    }

    public function testHasGameDayProperty(): void
    {
        $boxscore = new \Boxscore();

        $this->assertTrue(property_exists($boxscore, 'gameDay'));
    }

    public function testHasGameOfThatDayProperty(): void
    {
        $boxscore = new \Boxscore();

        $this->assertTrue(property_exists($boxscore, 'gameOfThatDay'));
    }

    // ============================================
    // PROPERTY TESTS - Teams
    // ============================================

    public function testHasVisitorTeamIDProperty(): void
    {
        $boxscore = new \Boxscore();

        $this->assertTrue(property_exists($boxscore, 'visitorTeamID'));
    }

    public function testHasHomeTeamIDProperty(): void
    {
        $boxscore = new \Boxscore();

        $this->assertTrue(property_exists($boxscore, 'homeTeamID'));
    }

    // ============================================
    // PROPERTY TESTS - Attendance
    // ============================================

    public function testHasAttendanceProperty(): void
    {
        $boxscore = new \Boxscore();

        $this->assertTrue(property_exists($boxscore, 'attendance'));
    }

    public function testHasCapacityProperty(): void
    {
        $boxscore = new \Boxscore();

        $this->assertTrue(property_exists($boxscore, 'capacity'));
    }

    // ============================================
    // PROPERTY TESTS - Records
    // ============================================

    public function testHasVisitorWinsProperty(): void
    {
        $boxscore = new \Boxscore();

        $this->assertTrue(property_exists($boxscore, 'visitorWins'));
    }

    public function testHasVisitorLossesProperty(): void
    {
        $boxscore = new \Boxscore();

        $this->assertTrue(property_exists($boxscore, 'visitorLosses'));
    }

    public function testHasHomeWinsProperty(): void
    {
        $boxscore = new \Boxscore();

        $this->assertTrue(property_exists($boxscore, 'homeWins'));
    }

    public function testHasHomeLossesProperty(): void
    {
        $boxscore = new \Boxscore();

        $this->assertTrue(property_exists($boxscore, 'homeLosses'));
    }

    // ============================================
    // PROPERTY TESTS - Quarter Scores
    // ============================================

    public function testHasVisitorQ1pointsProperty(): void
    {
        $boxscore = new \Boxscore();

        $this->assertTrue(property_exists($boxscore, 'visitorQ1points'));
    }

    public function testHasVisitorQ2pointsProperty(): void
    {
        $boxscore = new \Boxscore();

        $this->assertTrue(property_exists($boxscore, 'visitorQ2points'));
    }

    public function testHasVisitorQ3pointsProperty(): void
    {
        $boxscore = new \Boxscore();

        $this->assertTrue(property_exists($boxscore, 'visitorQ3points'));
    }

    public function testHasVisitorQ4pointsProperty(): void
    {
        $boxscore = new \Boxscore();

        $this->assertTrue(property_exists($boxscore, 'visitorQ4points'));
    }

    public function testHasVisitorOTpointsProperty(): void
    {
        $boxscore = new \Boxscore();

        $this->assertTrue(property_exists($boxscore, 'visitorOTpoints'));
    }

    public function testHasHomeQ1pointsProperty(): void
    {
        $boxscore = new \Boxscore();

        $this->assertTrue(property_exists($boxscore, 'homeQ1points'));
    }

    public function testHasHomeQ2pointsProperty(): void
    {
        $boxscore = new \Boxscore();

        $this->assertTrue(property_exists($boxscore, 'homeQ2points'));
    }

    public function testHasHomeQ3pointsProperty(): void
    {
        $boxscore = new \Boxscore();

        $this->assertTrue(property_exists($boxscore, 'homeQ3points'));
    }

    public function testHasHomeQ4pointsProperty(): void
    {
        $boxscore = new \Boxscore();

        $this->assertTrue(property_exists($boxscore, 'homeQ4points'));
    }

    public function testHasHomeOTpointsProperty(): void
    {
        $boxscore = new \Boxscore();

        $this->assertTrue(property_exists($boxscore, 'homeOTpoints'));
    }
}
