<?php

declare(strict_types=1);

namespace Tests\Utilities;

use PHPUnit\Framework\TestCase;
use Utilities\StandingsGrouper;

/**
 * StandingsGrouperTest - Tests for standings grouping utility
 */
class StandingsGrouperTest extends TestCase
{
    public function testGetGroupingsForEasternConference(): void
    {
        $result = StandingsGrouper::getGroupingsFor('Eastern');
        
        $this->assertIsArray($result);
        $this->assertEquals('conference', $result['grouping']);
        $this->assertEquals('conf_gb', $result['groupingGB']);
        $this->assertEquals('conf_magic_number', $result['groupingMagicNumber']);
    }

    public function testGetGroupingsForWesternConference(): void
    {
        $result = StandingsGrouper::getGroupingsFor('Western');
        
        $this->assertEquals('conference', $result['grouping']);
        $this->assertEquals('conf_gb', $result['groupingGB']);
        $this->assertEquals('conf_magic_number', $result['groupingMagicNumber']);
    }

    public function testGetGroupingsForAtlanticDivision(): void
    {
        $result = StandingsGrouper::getGroupingsFor('Atlantic');
        
        $this->assertEquals('division', $result['grouping']);
        $this->assertEquals('div_gb', $result['groupingGB']);
        $this->assertEquals('div_magic_number', $result['groupingMagicNumber']);
    }

    public function testGetGroupingsForCentralDivision(): void
    {
        $result = StandingsGrouper::getGroupingsFor('Central');
        
        $this->assertEquals('division', $result['grouping']);
        $this->assertEquals('div_gb', $result['groupingGB']);
        $this->assertEquals('div_magic_number', $result['groupingMagicNumber']);
    }

    public function testGetGroupingsForMidwestDivision(): void
    {
        $result = StandingsGrouper::getGroupingsFor('Midwest');
        
        $this->assertEquals('division', $result['grouping']);
        $this->assertEquals('div_gb', $result['groupingGB']);
        $this->assertEquals('div_magic_number', $result['groupingMagicNumber']);
    }

    public function testGetGroupingsForPacificDivision(): void
    {
        $result = StandingsGrouper::getGroupingsFor('Pacific');
        
        $this->assertEquals('division', $result['grouping']);
        $this->assertEquals('div_gb', $result['groupingGB']);
        $this->assertEquals('div_magic_number', $result['groupingMagicNumber']);
    }

    public function testGetGroupingsForUnknownRegionDefaultsToConference(): void
    {
        $result = StandingsGrouper::getGroupingsFor('Unknown');
        
        $this->assertEquals('conference', $result['grouping']);
    }

    public function testIsConferenceReturnsTrueForConferences(): void
    {
        $this->assertTrue(StandingsGrouper::isConference('Eastern'));
        $this->assertTrue(StandingsGrouper::isConference('Western'));
    }

    public function testIsConferenceReturnsFalseForDivisions(): void
    {
        $this->assertFalse(StandingsGrouper::isConference('Atlantic'));
        $this->assertFalse(StandingsGrouper::isConference('Central'));
    }

    public function testIsDivisionReturnsTrueForDivisions(): void
    {
        $this->assertTrue(StandingsGrouper::isDivision('Atlantic'));
        $this->assertTrue(StandingsGrouper::isDivision('Central'));
        $this->assertTrue(StandingsGrouper::isDivision('Midwest'));
        $this->assertTrue(StandingsGrouper::isDivision('Pacific'));
    }

    public function testIsDivisionReturnsFalseForConferences(): void
    {
        $this->assertFalse(StandingsGrouper::isDivision('Eastern'));
        $this->assertFalse(StandingsGrouper::isDivision('Western'));
    }
}
