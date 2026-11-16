<?php

declare(strict_types=1);

namespace Tests\PlrParser;

use PHPUnit\Framework\TestCase;
use PlrParser\PlrDepthChartSelector;

/**
 * @covers \PlrParser\PlrDepthChartSelector
 */
class PlrDepthChartSelectorTest extends TestCase
{
    public function testTeamWithAnyNonZeroChartValueIsCharted(): void
    {
        $rows = [
            101 => JsbExportFixtures::chartRow(101, 3),
            102 => JsbExportFixtures::chartRow(102, 3, ['dc_sf_depth' => 2]),
            201 => JsbExportFixtures::chartRow(201, 4, ['dc_minutes' => 12]),
            202 => JsbExportFixtures::chartRow(202, 4),
        ];

        $charted = PlrDepthChartSelector::chartedByPid($rows);

        self::assertSame([101, 102, 201, 202], array_keys($charted));
        self::assertSame(2, PlrDepthChartSelector::countTeams($charted));
    }

    public function testAllZeroTeamIsNotCharted(): void
    {
        $rows = [
            101 => JsbExportFixtures::chartRow(101, 3, ['dc_of' => 2, 'dc_can_play_in_game' => 1]),
            102 => JsbExportFixtures::chartRow(102, 3),
            301 => JsbExportFixtures::chartRow(301, 0, ['dc_minutes' => 30]),
            401 => JsbExportFixtures::chartRow(401, 33, ['dc_c_depth' => 1]),
        ];

        $charted = PlrDepthChartSelector::chartedByPid($rows);

        self::assertSame([], $charted);
        self::assertSame(0, PlrDepthChartSelector::countTeams($charted));
    }
}
