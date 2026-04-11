<?php

declare(strict_types=1);

namespace Tests\PlrParser;

use PHPUnit\Framework\TestCase;
use PlrParser\PlrTeamRowLayout;
use PlrParser\PlrTeamRowReconstructor;

/**
 * @covers \PlrParser\PlrTeamRowReconstructor
 * @covers \PlrParser\PlrTeamRowLayout
 */
class PlrTeamRowReconstructorTest extends TestCase
{
    public function testApplyRegularSeasonStatsOverlaysFieldsAtKnownOffsets(): void
    {
        $base = $this->buildSyntheticRow();

        $reconstructed = PlrTeamRowReconstructor::applyRegularSeasonStats($base, [
            'gp' => 18,
            'gpAlt' => 18,
            'twoGM' => 689,
            'twoGA' => 1421,
            'ftm' => 306,
            'fta' => 392,
            'threeGM' => 115,
            'threeGA' => 312,
            'orb' => 308,
            'drb' => 671,
            'ast' => 427,
            'stl' => 150,
            'tov' => 310,
            'blk' => 103,
            'pf' => 288,
        ]);

        $this->assertSame(PlrTeamRowLayout::FRANCHISE_ROW_LENGTH, strlen($reconstructed));
        $this->assertSame('  18', substr($reconstructed, 148, 4));
        $this->assertSame('  18', substr($reconstructed, 152, 4));
        $this->assertSame(' 689', substr($reconstructed, 156, 4));
        $this->assertSame('1421', substr($reconstructed, 160, 4));
        $this->assertSame(' 306', substr($reconstructed, 164, 4));
        $this->assertSame(' 392', substr($reconstructed, 168, 4));
        $this->assertSame(' 115', substr($reconstructed, 172, 4));
        $this->assertSame(' 312', substr($reconstructed, 176, 4));
        $this->assertSame(' 308', substr($reconstructed, 180, 4));
        $this->assertSame(' 671', substr($reconstructed, 184, 4));
        $this->assertSame(' 427', substr($reconstructed, 188, 4));
        $this->assertSame(' 150', substr($reconstructed, 192, 4));
        $this->assertSame(' 310', substr($reconstructed, 196, 4));
        $this->assertSame(' 103', substr($reconstructed, 200, 4));
        $this->assertSame(' 288', substr($reconstructed, 204, 4));
    }

    public function testBytesOutsideKnownFieldsArePreservedFromBase(): void
    {
        $base = $this->buildSyntheticRow();
        // Mark a recognizable byte at offset 50 (well outside the known regular-season block)
        $base = substr_replace($base, 'X', 50, 1);
        $base = substr_replace($base, 'Y', 600, 1);

        $reconstructed = PlrTeamRowReconstructor::applyRegularSeasonStats($base, [
            'gp' => 80,
            'twoGM' => 3000,
        ]);

        $this->assertSame('X', $reconstructed[50]);
        $this->assertSame('Y', $reconstructed[600]);
    }

    public function testMissingStatKeysLeaveBaseValuesIntact(): void
    {
        $base = $this->buildSyntheticRow();
        // Pre-populate the gp field with a specific value
        $base = substr_replace($base, '  42', 148, 4);

        // Apply only twoGM — gp should remain 42
        $reconstructed = PlrTeamRowReconstructor::applyRegularSeasonStats($base, [
            'twoGM' => 999,
        ]);

        $this->assertSame('  42', substr($reconstructed, 148, 4));
        $this->assertSame(' 999', substr($reconstructed, 156, 4));
    }

    public function testRejectsNonFranchiseRowLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PlrTeamRowReconstructor::applyRegularSeasonStats(str_repeat(' ', 607), ['gp' => 1]);
    }

    public function testRejectsValueWiderThanFieldWidth(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PlrTeamRowReconstructor::applyRegularSeasonStats($this->buildSyntheticRow(), [
            'twoGM' => 99999, // 5 digits, twoGM field is only 4 wide
        ]);
    }

    public function testFranchiseOrdinalMapping(): void
    {
        $this->assertSame(1441, PlrTeamRowLayout::franchiseOrdinal(1));
        $this->assertSame(1468, PlrTeamRowLayout::franchiseOrdinal(28));
        $this->assertTrue(PlrTeamRowLayout::isFranchiseOrdinal(1441));
        $this->assertTrue(PlrTeamRowLayout::isFranchiseOrdinal(1468));
        $this->assertFalse(PlrTeamRowLayout::isFranchiseOrdinal(1440));
        $this->assertFalse(PlrTeamRowLayout::isFranchiseOrdinal(1469));
    }

    private function buildSyntheticRow(): string
    {
        return str_repeat(' ', PlrTeamRowLayout::FRANCHISE_ROW_LENGTH);
    }
}
